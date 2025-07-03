<?php

namespace Ecomail;

use Ecomail\Models\WooOrderModel;
use Ecomail\Repositories\SettingsRepository;
use Ecomail\Repositories\WooOrderRepository;

/**
 * Class Ecomail
 *
 * @package Ecomail
 * @property Plugin $plugin
 */
class Ecomail {

	const COOKIE_NAME = 'ecm_email';
	const INPUT_NAME = 'ecomail_not_subscribe';
	const OPTION_EMAIL_LISTS = 'ecomail_lists';
	const OPTION_BULK_USERS_UPLOAD_IDS = 'ecomail_users_upload_ids';
	const OPTION_BULK_ORDERS_UPLOAD_IDS = 'ecomail_orders_upload_ids';
	const SCHEDULE_ORDERS_LIMIT = 500;
	const SCHEDULE_USERS_LIMIT = 500;
	const SCHEDULE_USERS_WITH_ORDERS_LIMIT = 100;

	/**
	 * @var EcomailApi
	 */
	private $ecomail_api;

	/**
	 * @var WooOrderRepository
	 */
	private $order_repository;

	/**
	 * @var SettingsRepository
	 */
	private $settings;

	public function __construct(
		EcomailApi $ecomail_api,
		SettingsRepository $settings,
		WooOrderRepository $order_repository,
	) {
		$this->ecomail_api      = $ecomail_api;
		$this->order_repository = $order_repository;
		$this->settings         = $settings;

		$this->setup();
	}

	public function setup() {
		add_action( 'template_redirect', array( $this, 'maybe_save_email_cookie' ) );
		add_action( 'wp_head', array( $this, 'tracking_code' ) );
		add_action( 'admin_action_ecomail_refresh_lists', array( $this, 'refresh_lists' ) );
		add_action( 'admin_action_ecomail_bulk_upload_users', array( $this, 'maybe_schedule_users_upload' ) );
		add_action( 'admin_action_ecomail_bulk_upload_users_and_orders', array( $this, 'maybe_schedule_users_and_orders_upload' ) );
		add_action( 'ecomail_bulk_import_users', array( $this, 'bulk_import_users' ) );
		add_action( 'ecomail_bulk_import_orders', array( $this, 'bulk_import_orders' ) );
		add_action( 'admin_notices', array( $this, 'pending_bulk_upload_notice' ) );
		add_action( 'admin_notices', array( $this, 'api_status_notice' ) );
	}

	public function tracking_code() {
		$app_id = $this->settings->get_option( 'app_id' );
		$enable = $this->settings->get_option( 'enable_tracking_code' );
		if ( ! $app_id || ! $enable ) {
			return;
		}

		if ( $this->is_disabled_by_cookie() ) {
			return;
		}
		?>
        <!-- Ecomail starts growing -->
        <script type="text/javascript">
          ;(function (p, l, o, w, i, n, g) {
            if (!p[i]) {
              p.GlobalSnowplowNamespace = p.GlobalSnowplowNamespace || [];
              p.GlobalSnowplowNamespace.push(i);
              p[i] = function () {
                (p[i].q = p[i].q || []).push(arguments)
              };
              p[i].q = p[i].q || [];
              n = l.createElement(o);
              g = l.getElementsByTagName(o)[0];
              n.async = 1;
              n.src = w;
              g.parentNode.insertBefore(n, g)
            }
          }(window, document, "script", "//d1fc8wv8zag5ca.cloudfront.net/2.4.2/sp.js", "ecotrack"));
          window.ecotrack('newTracker', 'cf', 'd2dpiwfhf3tz0r.cloudfront.net', { // Initialise a tracker
            appId: '<?php echo esc_attr( $app_id ); ?>'
          });
          window.ecotrack('setUserIdFromLocation', 'ecmid');
		  <?php
		  $this->manual_tracking();
		  ?>

          window.ecotrack('trackPageView');

        </script>
        <!-- Ecomail stops growing -->
		<?php
	}

	public function manual_tracking() {
		if ( ! $this->settings->get_option( 'enable_manual_tracking' ) ) {
			return;
		}

		$email = $this->get_customer_email();
		if ( ! $email ) {
			return;
		}
		printf( "window.ecotrack('setUserId', '%s')", esc_attr( $email ) );
	}

	/**
	 * Get customer email
	 *
	 * @return string|null
	 */
	public function get_customer_email(): ?string {
		if ( $this->get_email_cookie() ) {
			return $this->get_email_cookie();
		}

		if ( ! empty( WC()->customer ) && ! empty( WC()->customer->get_billing_email() ) ) {
			return WC()->customer->get_billing_email();
		}
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			return $user->user_email;
		}

		return null;
	}


	public function get_lists() {
		return get_option( self::OPTION_EMAIL_LISTS, array() );
	}

	public function refresh_lists() {
		$this->save_lists();
		wp_safe_redirect( $this->settings->get_settings_url() );
		exit();
	}

	public function save_lists() {
		$lists = $this->ecomail_api->get_lists();
		if ( ! is_wp_error( $lists ) ) {
			update_option( self::OPTION_EMAIL_LISTS, $lists );
		}

		return $lists;
	}

	public function maybe_save_email_cookie() {
		if ( filter_input( INPUT_GET, 'ecmid' ) ) {
			$this->save_email_cookie( sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'ecmid' ) ) ) );
		}
	}

	public function save_email_cookie( $email ) {
		setcookie( self::COOKIE_NAME, $email, time() + ( 86400 * 30 ), '/' ); // 86400 = 1 day
	}

	public function get_email_cookie() {
		return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ?? '' ) );
	}

	/**
	 * Maybe schedule users upload.
	 *
	 * @return void
	 */
	public function maybe_schedule_users_upload() {
		if ( $this->is_import_running() ) {
			wp_die( esc_html( __( 'The upload is running on background', 'ecomail-woocommerce' ) ) );
		}

		$this->add_user_ids_to_list();
		wp_safe_redirect( $this->settings->get_settings_url() );
	}

	/**
	 * Maybe schedule users and orders upload.
	 *
	 * @return void
	 */
	public function maybe_schedule_users_and_orders_upload() {
		if ( $this->is_import_running() ) {
			wp_die( esc_html( __( 'The upload is running on background', 'ecomail-woocommerce' ) ) );
		}

		$this->add_user_ids_to_list( 1, true );
		wp_safe_redirect( $this->settings->get_settings_url() );
	}

	/**
	 * Add user IDs to list.
	 *
	 * @param int $paged
	 * @param bool $with_orders
	 *
	 * @return void
	 */
	public function add_user_ids_to_list( $paged = 1, $with_orders = false ) {
		$limit = ( $with_orders ) ? self::SCHEDULE_USERS_WITH_ORDERS_LIMIT : self::SCHEDULE_USERS_LIMIT;

		$args     = array(
			'number' => $limit,
			'fields' => 'ID',
			'paged'  => $paged,
		);
		$user_ids = get_users( $args );
		if ( ! empty( $user_ids ) ) {
			$this->update_ids_option( self::OPTION_BULK_USERS_UPLOAD_IDS, $user_ids );
			if ( ! as_has_scheduled_action( 'ecomail_bulk_import_users', array(), 'ecomail' ) ) {
				$this->schedule_users_upload();
			}

			if ( count( $user_ids ) === $limit ) {
				$this->add_user_ids_to_list( ( $paged + 1 ), $with_orders );
			}

			if ( $with_orders ) {
				foreach ( $user_ids as $user_id ) {
					$this->add_user_orders_to_list( $user_id );
				}
			}
		}
	}

	/**
	 * Add user orders to list.
	 *
	 * @param $user_id
	 *
	 * @return void
	 * @throws \EcomailDeps\Wpify\Model\Exceptions\RepositoryNotInitialized
	 */
	public function add_user_orders_to_list( $user_id ) {
		$customer  = new \WC_Customer( $user_id );
		$order_ids = array();
		/** Order model. @var WooOrderModel $order */
		foreach ( $this->order_repository->find_by_customer( $customer->get_id() ) as $order ) {
			$order_ids[] = $order->id;
		}
		if ( ! empty( $order_ids ) ) {
			$this->update_ids_option( self::OPTION_BULK_ORDERS_UPLOAD_IDS, $order_ids );

			if ( ! as_has_scheduled_action( 'ecomail_bulk_import_orders', array(), 'ecomail' ) ) {
				$this->schedule_orders_upload();
			}
		}
	}

	/**
	 * Schedule users upload.
	 *
	 * @return void
	 */
	private function schedule_users_upload() {
		as_schedule_single_action( time(), 'ecomail_bulk_import_users', array(), 'ecomail' );
	}

	/**
	 * Schedule orders upload.
	 *
	 * @return void
	 */
	private function schedule_orders_upload() {
		as_schedule_single_action( time(), 'ecomail_bulk_import_orders', array(), 'ecomail' );
	}

	/**
	 * Update IDs option.
	 *
	 * @param $key
	 * @param $ids
	 *
	 * @return void
	 */
	private function update_ids_option( $key, $ids ) {
		$current_ids = array_merge( get_option( $key, array() ), $ids );
		update_option( $key, $current_ids );
	}

	/**
	 * Is import running.
	 *
	 * @return bool
	 */
	private function is_import_running() {
		return (
			as_has_scheduled_action( 'ecomail_bulk_import_users', array(), 'ecomail' ) ||
			as_has_scheduled_action( 'ecomail_bulk_import_orders', array(), 'ecomail' )
		);
	}

	/**
	 * Bulk import users.
	 *
	 *
	 * @throws \Exception
	 */
	public function bulk_import_users() {
		$user_ids           = get_option( self::OPTION_BULK_USERS_UPLOAD_IDS, array() );
		$user_ids_to_import = array_splice( $user_ids, 0, self::SCHEDULE_USERS_LIMIT );
		$this->update_ids_option( self::OPTION_BULK_USERS_UPLOAD_IDS, $user_ids );

		if ( empty( $user_ids_to_import ) ) {
			return;
		}

		$args  = array(
			'include' => $user_ids_to_import,
			'limit'   => - 1,
		);
		$users = get_users( $args );
		$data  = array();
		foreach ( $users as $user ) {
			/** @var \WP_User $user */
			$customer = new \WC_Customer( $user->ID );
			$data[]   = $this->get_subscribe_data_from_object( $customer );
		}

		$request_data = array(
			'subscriber_data'        => $data,
			'update_existing'        => boolval( $this->settings->get_option( 'woocommerce_checkout_update' ) ),
			'skip_confirmation'      => boolval( $this->settings->get_option( 'woocommerce_checkout_skip_confirmation' ) ),
			'trigger_autoresponders' => boolval( $this->settings->get_option( 'woocommerce_checkout_trigger_autoresponders' ) ),
		);

		$this->ecomail_api->bulk_add_subscribers( $this->settings->get_option( 'woocommerce_checkout_list_id' ), $request_data );

		if ( count( $user_ids ) !== 0 ) {
			$this->schedule_users_upload();
		}
	}

	/**
	 * Bulk import orders.
	 *
	 * @throws \Exception
	 */
	public function bulk_import_orders() {
		$order_ids           = get_option( self::OPTION_BULK_ORDERS_UPLOAD_IDS, array() );
		$order_ids_to_import = array_splice( $order_ids, 0, self::SCHEDULE_ORDERS_LIMIT );
		$this->update_ids_option( self::OPTION_BULK_ORDERS_UPLOAD_IDS, $order_ids );

		if ( empty( $order_ids_to_import ) ) {
			return;
		}

		$transactions = array();
		/** Order model. @var WooOrderModel $order */
		foreach ( $this->order_repository->find_by_ids( $order_ids_to_import ) as $order ) {
			$transactions[] = $order->get_transaction_data();
		}

		if ( ! empty( $transactions ) ) {
			$data = array(
				'transaction_data' => $transactions,
			);

			$this->ecomail_api->bulk_add_transactions( $data );
		}

		if ( count( $order_ids ) !== 0 ) {
			$this->schedule_orders_upload();
		}
	}

	/**
	 * Get subscribe data from WC Customer or WC Order.
	 *
	 * @param $object
	 * @param $additional_data
	 *
	 * @return array
	 */
	public function get_subscribe_data_from_object( $object, $additional_data = array() ) {
		$data = array();

		if ( ! is_a( $object, 'WC_Customer' ) && ! is_a( $object, 'WC_Order' ) ) {
			return $data;
		}

		$email = $object->get_billing_email();
		if ( ! $email ) {
			if ( is_a( $object, 'WC_Customer' ) ) {
				$email = $object->get_email();
			} else {
				$email = $object->get_user()->user_email;
			}
		}
		if ( ! $email ) {
			return $data;
		}


		$data['email'] = $email;

		$fields = $this->settings->get_option( 'woocommerce_checkout_subscribe_fields' );

		if ( in_array( 'first_name', $fields ) ) {
			$data['name'] = $object->get_billing_first_name();
		}
		if ( in_array( 'last_name', $fields ) ) {
			$data['surname'] = $object->get_billing_last_name();
		}
		if ( in_array( 'company', $fields ) ) {
			$data['company'] = $object->get_billing_company();
		}
		if ( in_array( 'city', $fields ) ) {
			$data['city'] = $object->get_billing_city();
		}
		if ( in_array( 'street', $fields ) ) {
			$data['street'] = $object->get_billing_address_1();
		}
		if ( in_array( 'postcode', $fields ) ) {
			$data['zip'] = $object->get_billing_postcode();
		}
		if ( in_array( 'country', $fields ) ) {
			$data['country'] = $object->get_billing_country();
		}
		if ( in_array( 'phone', $fields ) ) {
			$data['phone'] = $object->get_billing_phone();
		}

		if ( $this->settings->get_option( 'api_source' ) ) {
			$data['source'] = $this->settings->get_option( 'api_source' );
		}

		if ( is_array( $additional_data ) && ! empty( $additional_data ) ) {
			$data = array_merge( $data, $additional_data );
		}

		return $data;
	}

	public function pending_bulk_upload_notice() {
		if ( $this->is_import_running() ) {
			?>
            <div class="notice notice-warning">
                <p><?php echo esc_html( __( 'The bulk upload to Ecomail is pending.', 'ecomail-woocommerce' ) ); ?></p>
            </div>
			<?php
		}
	}

	public function api_status_notice() {
		global $pagenow;

		if ( 'options-general.php' !== $pagenow && 'ecomail' !== filter_input( INPUT_GET, 'page' ) ) {
			return;
		}

		$api_status = array(
			'success' => true,
		);
		$response   = $this->ecomail_api->get_lists();
		if ( is_wp_error( $response ) ) {
			$api_status = array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status = ( $api_status['success'] ) ? 'success' : 'error';
		?>
        <div class="notice notice-<?php echo esc_html( $status ); ?>">

            <p>
				<?php printf(
				/* Translators: %1$s API status, %2$s last request date */
					esc_html( __( 'API connection status: %1$s, Last request: %2$s. %3$s', 'ecomail-woocommerce' ) ),
					esc_html( $status ),
					esc_html( wp_date( 'd. m. Y H:i:s' ) ),
					esc_html( $api_status['message'] ?? '' ),
				); ?>
            </p>
        </div>
		<?php
	}

	public function is_disabled_by_cookie() {
		$cookie_name  = $this->settings->get_option( 'cookie_name' );
		$cookie_value = $this->settings->get_option( 'cookie_value' );
		if ( $cookie_name && $cookie_value && isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] != $cookie_value ) {
			return true;
		}

		return false;
	}

}
