<?php

namespace Ecomail;

use Ecomail\Models\WooOrderModel;
use Ecomail\Repositories\SettingsRepository;
use Ecomail\Repositories\WooOrderRepository;
use EcomailDeps\Wpify\Log\RotatingFileLog;
use WP_Error;

class WooCommerce {
	/** @var Ecomail */
	private Ecomail $ecomail;

	/** @var EcomailApi */
	private EcomailApi $ecomail_api;

	/** @var WooOrderRepository */
	private WooOrderRepository $order_repository;

	/** @var SettingsRepository */
	private SettingsRepository $settings;

	public function __construct(
		Ecomail $ecomail,
		EcomailApi $ecomail_api,
		WooOrderRepository $order_repository,
		SettingsRepository $settings,
		private RotatingFileLog $log
	) {
		$this->ecomail          = $ecomail;
		$this->ecomail_api      = $ecomail_api;
		$this->order_repository = $order_repository;
		$this->settings         = $settings;

		$this->setup();
	}

	/**
	 * Initialize the actions
	 *
	 * @return bool|void
	 */
	public function setup() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'order_created' ) );
		add_action( 'woocommerce_before_pay_action', array( $this, 'handle_order_pay_form' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_update_cart_flag' ) );
		add_action( 'ecomail_subscribe_contact', array( $this, 'subscribe_contact' ) );
		add_action( 'ecomail_unsubscribe_contact', array( $this, 'unsubscribe_contact' ) );
		add_action( 'ecomail_add_transaction', array( $this, 'add_transaction' ) );
		add_action( 'ecomail_clear_cart', array( $this, 'clear_cart' ) );
		add_action( 'woocommerce_checkout_after_terms_and_conditions', array( $this, 'add_checkbox' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'display_subscription_status' ) );
		if ( $this->settings->get_option( 'woocommerce_cart_tracking', false ) ) {
			add_action( 'woocommerce_cart_item_removed', array( $this, 'add_update_cart_flag' ) );
			add_filter( 'woocommerce_update_cart_action_cart_updated', array( $this, 'cart_updated' ), 1000 );
			add_action( 'wp_enqueue_scripts', array( $this, 'set_cart_tracking_data' ), 20 );
		}
		if ( $this->settings->get_option( 'woocommerce_order_tracking', false ) ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 3 );
			add_action( 'ecomail_update_transaction_status', array( $this, 'update_transaction_status' ) );
		}

		add_action( 'woocommerce_edit_account_form_start', [ $this, 'hide_checkbox_fields_with_css' ] );
	}

	/**
	 * Schedule actions on order created
	 *
	 * @param $order_id
	 */
	public function order_created( $order_id ) {
		$cart_tracking_enabled  = $this->settings->get_option( 'woocommerce_cart_tracking', false );
		$order_tracking_enabled = $this->settings->get_option( 'woocommerce_order_tracking', false );
		$checkout_subscribe     = $this->settings->get_option( 'woocommerce_checkout_subscribe', false );
		$checkbox_enabled       = $this->settings->get_option( 'woocommerce_checkout_subscribe_checkbox', false );
		$disabled_by_cookie     = $this->ecomail->is_disabled_by_cookie();
		$subscribe              = false;

		if ( $checkout_subscribe ) {
			if (
				! $checkbox_enabled
				||
				! filter_input( INPUT_POST, Ecomail::INPUT_NAME )
			) {
				$subscribe = true;
				as_schedule_single_action( time(), 'ecomail_subscribe_contact', array( 'order_id' => $order_id ) );
			} elseif (
				$checkbox_enabled
				&& filter_input( INPUT_POST, Ecomail::INPUT_NAME )
			) {
				$subscribe = true;
				as_schedule_single_action( time(), 'ecomail_unsubscribe_contact', array( 'order_id' => $order_id ) );
			}
		}

		$this->log->info( 'Order created', array(
			'order_id'                    => $order_id,
			'subscribe_enabled'           => $checkout_subscribe,
			'show_checkbox'               => $checkbox_enabled,
			'input_value'                 => filter_input( INPUT_POST, Ecomail::INPUT_NAME ),
			'subscribe'                   => $subscribe,
			'order_tracking_enabled'      => $order_tracking_enabled,
			'cart_tracking_enabled'       => $cart_tracking_enabled,
			'tracking_disabled_by_cookie' => $disabled_by_cookie,
			'track_order'                 => $order_tracking_enabled && ! $disabled_by_cookie,
			'track_cart'                  => $cart_tracking_enabled && ! $disabled_by_cookie,
		) );

		if ( $disabled_by_cookie ) {
			return;
		}

		if ( $order_tracking_enabled ) {
			as_schedule_single_action( time(), 'ecomail_add_transaction', array( 'order_id' => $order_id ) );
		}

		if ( $cart_tracking_enabled ) {
			as_schedule_single_action( time(), 'ecomail_clear_cart', array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Handle order status change.
	 *
	 * @param $order_id
	 * @param $old_status
	 * @param $new_status
	 *
	 * @return void
	 */
	public function order_status_changed( $order_id, $old_status, $new_status ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$wc_order = wc_get_order( $order_id );
		if ( $wc_order ) {
			as_schedule_single_action( time(), 'ecomail_update_transaction_status', array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Update transaction status.
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function update_transaction_status( $order_id ) {
		/** Order model. @var WooOrderModel $order */
		$order = $this->order_repository->get( $order_id );

		$this->ecomail_api->update_transaction( $order_id, $order->get_transaction_data() );
	}

	/**
	 * Handle order pay form
	 *
	 * @param $order
	 *
	 * @return void
	 */
	public function handle_order_pay_form( $order ) {
		$this->order_created( $order->get_id() );
	}

	/**
	 * Subscribe order contact
	 *
	 * @param $order_id
	 */
	public function subscribe_contact( $order_id ) {
		$this->submit_order_data( $order_id, true );
	}

	/**
	 * Unsubscribe order contact
	 *
	 * @param $order_id
	 */
	public function unsubscribe_contact( $order_id ) {
		$this->submit_order_data( $order_id, false );
	}

	private function submit_order_data( int $order_id, bool $subscribe ): void {
		/** Order model. @var WooOrderModel $order */
		$order = $this->order_repository->get( $order_id );

		// Save subscription preference as order meta
		$order->_ecomail_subscribe = $subscribe;
		$this->order_repository->save( $order );

		// Also save to customer user meta if customer exists
		$customer_id = $order->get_wc_order()->get_customer_id();
		if ( $customer_id > 0 ) {
			$existing_subscription = get_user_meta( $customer_id, '_ecomail_subscribe', true );
			if ( ! $existing_subscription || $subscribe ) {
				update_user_meta( $customer_id, '_ecomail_subscribe', 'SUBSCRIBED' );
			}
		}


		$tags = ( $subscribe ) ? array( 'wp_order', 'wp_newsletter' ) : array( 'wp_order' );

		$subscriber_data = $order->get_subscriber_data( $tags );

		// Validate email - skip if empty or invalid
		if ( empty( $subscriber_data['email'] ) || ! is_email( $subscriber_data['email'] ) ) {
			return;
		}

		// Merge the existing tags.
		$subscriber = $this->ecomail_api->get_subscriber( $this->settings->get_option( 'woocommerce_checkout_list_id' ), $subscriber_data['email'] );
		if ( $subscriber && ! is_wp_error( $subscriber ) && ! empty( $subscriber['subscriber'] ) ) {
			$existing_tags           = ! empty( $subscriber['subscriber']['tags'] ) ? $subscriber['subscriber']['tags'] : [];
			$subscriber_data['tags'] = array_unique( array_merge( $existing_tags, $tags ) );
		}

		if ( ! $subscribe ) {
			$subscriber_data['status'] = 2;
		}

		$data = array(
			'subscriber_data'        => $subscriber_data,
			'update_existing'        => boolval( $this->settings->get_option( 'woocommerce_checkout_update', false ) ),
			'skip_confirmation'      => boolval( $this->settings->get_option( 'woocommerce_checkout_skip_confirmation', false ) ),
			'trigger_autoresponders' => boolval( $this->settings->get_option( 'woocommerce_checkout_trigger_autoresponders', false ) ),
			'resubscribe'            => boolval( $this->settings->get_option( 'woocommerce_checkout_resubscribe', false ) ),
		);

		$this->ecomail_api->add_subscriber( $this->settings->get_option( 'woocommerce_checkout_list_id' ), $data );
	}

	/**
	 * Add transaction
	 *
	 * @param $order_id
	 */
	public function add_transaction( $order_id ) {
		/** Order model. @var WooOrderModel $order */
		$order = $this->order_repository->get( $order_id );

		$this->ecomail_api->add_transaction( $order->get_transaction_data() );
	}

	/**
	 * Clear Ecomail cart
	 *
	 * @param $order_id
	 */
	public function clear_cart( $order_id ) {
		$order = $this->order_repository->get( $order_id );
		$email = $order->get_wc_order()->get_billing_email();

		// Validate email
		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		$this->ecomail_api->update_cart( $email, array() );
	}

	/**
	 * Add cart data if requested - this is used for JS cart tracking
	 */
	public function set_cart_tracking_data() {
		if ( ! function_exists( 'WC' ) || ! WC() || empty( WC()->session ) || ! WC()->session->get( 'ecomail_update_cart' ) ) {
			return;
		}

		$email = $this->ecomail->get_customer_email();
		if ( ! $email ) {
			return;
		}

		$items = $this->get_cart_items();
		if ( null === $items ) {
			return;
		}

		if ( $this->ecomail->is_disabled_by_cookie() ) {
			return;
		}

		wp_localize_script(
			'ecomail',
			'ecomailCart',
			array(
				'items' => $items,
			)
		);
	}

	/**
	 * Get the cart items IDs and prices
	 *
	 * @return array|null
	 */
	public function get_cart_items() {
		if ( ! function_exists( 'WC' ) || ! WC() || empty( WC()->cart ) ) {
			return null;
		}

		return array_values(
			array_map(
				function ( $item ) {
					$product_id = $item['variation_id'] ?: $item['product_id'];

					return array(
						'product_id' => $product_id,
						'price'      => round( ( $item['line_total'] + $item['line_tax'] ) / $item['quantity'], wc_get_price_decimals() ),
					);
				},
				WC()->cart->get_cart()
			)
		);
	}

	/**
	 * Set the flag to update cart on next page load
	 */
	public function add_update_cart_flag() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'ecomail_update_cart', true );
	}

	/**
	 * Delete the flag to update cart on next page load
	 */
	public function delete_update_cart_flag() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'ecomail_update_cart', null );
	}

	/**
	 * Set update cart flag on updated cart
	 *
	 * @param $updated
	 */
	public function cart_updated( $updated ) {
		if ( $updated ) {
			$this->add_update_cart_flag();
		}
	}

	/**
	 * Track the cart
	 *
	 * @param $items
	 * @param $email
	 *
	 * @return WP_Error|array
	 */
	public function track_cart( $items, $email ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'wc_not_available', 'WooCommerce is not available' );
		}

		$products = array();
		foreach ( (array) $items as $item ) {
			$prod = wc_get_product( $item['product_id'] );
			if ( ! $prod ) {
				continue;
			}

			$products[] = array(
				'productId'   => $item['product_id'],
				'img_url'     => wp_get_attachment_image_url( $prod->get_image_id(), 'full' ),
				'url'         => $prod->get_permalink(),
				'name'        => $prod->get_name(),
				'price'       => $item['price'],
				'description' => $prod->get_short_description() ? wp_strip_all_tags( $prod->get_short_description() ) : wp_strip_all_tags( $prod->get_description() ),
			);
		}

		return $this->ecomail_api->update_cart( $email, $products );
	}

	/**
	 * Track the cart
	 *
	 * @param $product_id
	 * @param $email
	 *
	 * @return WP_Error|array
	 */
	public function track_product( $product_id, $email ) {
		return $this->ecomail_api->track_product( $product_id, $email );
	}

	public function add_checkbox() {
		if ( ! $this->settings->get_option( 'woocommerce_checkout_subscribe' ) || ! $this->settings->get_option( 'woocommerce_checkout_subscribe_checkbox' ) ) {
			return;
		}
		?>
		<p class="form-row">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
					   name="<?php echo esc_html( Ecomail::INPUT_NAME ); ?>"
					<?php
					checked( filter_input( INPUT_POST, Ecomail::INPUT_NAME ), true ); // WPCS: input var ok, csrf ok.
					?>
				/>
				<span class="woocommerce-terms-and-conditions-checkbox-text">
				<?php
				echo esc_html( sanitize_text_field( $this->settings->get_option( 'woocommerce_checkout_not_subscribe_text' ) ) );
				?>
					</span>&nbsp
			</label>
		</p>
		<?php
	}


	/**
	 * Display subscription status on WooCommerce account dashboard
	 *
	 * @return void
	 */
	public function display_subscription_status() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$subscription_status = get_user_meta( $user_id, '_ecomail_subscribe', true );
		$email               = $this->ecomail->get_customer_email();

		// if meta doesn't exist
		if ( ! $subscription_status ) {
			$ecomail_data = $this->ecomail_api->get_subscriber( $this->settings->get_option( 'woocommerce_checkout_list_id' ), $email );

			if ( is_wp_error( $ecomail_data ) || empty( $ecomail_data['subscriber'] ) ) {
				$subscription_status = 'UNSUBSCRIBED';
			} else {
				$ecomail_status      = $ecomail_data['subscriber']['status'] ?? 2;
				$subscription_status = $ecomail_status == 1 ? 'SUBSCRIBED' : 'UNSUBSCRIBED';
			}

			update_user_meta( $user_id, '_ecomail_subscribe', $subscription_status );
		}

		$status_text  = '';
		$status_class = '';

		if ( $subscription_status === 'SUBSCRIBED' ) {
			$status_text  = __( 'Subscribed', 'ecomail' );
			$status_class = 'ecomail-subscribed';
			$button_text  = __( 'Unsubscribe', 'ecomail' );
		} else {
			$status_text  = __( 'Unsubscribed', 'ecomail' );
			$status_class = 'ecomail-unsubscribed';
			$button_text  = __( 'Subscribe', 'ecomail' );
		}
		?>
		<style>
			.ecomail-subscribtion-info {
				background-color: rgba(0, 0, 0, 0.02);
				border: 1px solid rgba(0, 0, 0, 0.1);
				border-radius: 4px;
				padding: 1rem 1.2rem;
				margin: 1.5rem 0;
				display: flex;
				align-items: end;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 1rem;
			}

			.ecomail-subscribtion-info__text {
				flex: 1;
				min-width: 200px;
			}

			.ecomail-subscribtion-info__text p {
				margin: 0;
			}

			.ecomail-subscribtion-info__form {
				margin: 0;
				flex-shrink: 0;
			}

			.ecomail-subscribtion-info__badge {
				background-color: #3e4b52;
				color: #fff;
				padding: 0.2rem 0.5rem;
				border-radius: 4px;
				font-size: 0.8rem;
				text-transform: uppercase;
				font-weight: 600;
				letter-spacing: 0.05rem;
			}

			.ecomail-subscribtion-info__badge.ecomail-subscribed {
				background-color: #009d19;
			}

			.ecomail-subscribtion-info__badge.ecomail-unsubscribed {
				background-color: #d54e21;
			}
		</style>
		<div class="ecomail-subscribtion-info <?php echo esc_attr( $status_class ); ?>">
			<div class="ecomail-subscribtion-info__text">
				<h3><?php _e( 'Newsletter subscription', 'ecomail' ) ?></h3>
				<p><?php _e( 'Email:', 'ecomail' ) ?>
					<?php echo esc_html( $email ); ?></p>
				<span
					class="ecomail-subscribtion-info__badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
			</div>
			<form method="post" action="" class="ecomail-subscribtion-info__form">
				<?php wp_nonce_field( 'ecomail_toggle_subscription', 'ecomail_subscription_nonce' ); ?>
				<button type="submit" name="ecomail_toggle_subscription" value="1"
						class="button button-primary woocommerce-button wp-element-button">
					<?php echo esc_html( $button_text ); ?>
				</button>
			</form>
		</div>
		<?php
	}


	/**
	 * Hide field from My Account > Account Details page using CSS
	 */
	public function hide_checkbox_fields_with_css() {
		// Only output CSS on the account edit page
		if ( ! is_account_page() ) {
			return;
		}
		?>
		<style type="text/css">
			.woocommerce-EditAccountForm p[id*="ecomail/not_subscribe_field"] {
				display: none !important;
			}
		</style>
		<?php
	}
}
