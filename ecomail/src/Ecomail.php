<?php

namespace Ecomail;

use Ecomail\Repositories\SettingsRepository;

/**
 * Class Ecomail
 *
 * @package Ecomail
 * @property Plugin $plugin
 */
class Ecomail {

	/**
	 * @var EcomailApi
	 */
	private $ecomail_api;

	/**
	 * @var SettingsRepository
	 */
	private $settings;

	const COOKIE_NAME = 'ecm_email';

	public function __construct( EcomailApi $ecomail_api, SettingsRepository $settings ) {
		$this->ecomail_api = $ecomail_api;
		$this->settings    = $settings;

		$this->setup();
	}

	public function setup() {
		add_action( 'template_redirect', array( $this, 'maybe_save_email_cookie' ) );
		add_action( 'wp_head', array( $this, 'tracking_code' ) );
		add_action( 'admin_action_ecomail_refresh_lists', array( $this, 'refresh_lists' ) );
		add_action( 'admin_action_ecomail_bulk_upload_users', array( $this, 'schedule_users_upload' ) );
		add_action( 'ecomail_bulk_import_users', array( $this, 'bulk_import_users' ) );
		add_action( 'ecomail_bulk_import_users_finished', array( $this, 'finish_bulk_import_users' ) );
		add_action( 'admin_notices', array( $this, 'pending_bulk_upload_notice' ) );
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
		return get_option( 'ecomail_lists', array() );
	}

	public function refresh_lists() {
		$this->save_lists();
		wp_safe_redirect( $this->settings->get_settings_url() );
		exit();
	}

	public function save_lists() {
		$lists = $this->ecomail_api->get_lists();
		if ( ! is_wp_error( $lists ) ) {
			update_option( 'ecomail_lists', $lists );
		}

		return $lists;
	}

	public function maybe_save_email_cookie() {
		if ( isset( $_GET['ecmid'] ) ) {
			$this->save_email_cookie( sanitize_text_field( $_GET['ecmid'] ) );
		}
	}

	public function save_email_cookie( $email ) {
		setcookie( $this::COOKIE_NAME, $email, time() + ( 86400 * 30 ), '/' ); // 86400 = 1 day
	}

	public function get_email_cookie() {
		return $_COOKIE[ $this::COOKIE_NAME ] ?? '';
	}

	public function schedule_users_upload() {
		if ( get_option( 'ecomail_users_upload_pending' ) ) {
			wp_die( __( 'The upload is running on background', 'ecomail' ) );
		}

		update_option( 'ecomail_users_upload_pending', 1 );
		for ( $i = 1; $i < 1000000000; $i ++ ) {
			$args  = array(
				'number' => 500,
				'fields' => 'ID',
				'paged'  => $i,
			);
			$users = get_users( $args );
			if ( empty( $users ) ) {
				as_schedule_single_action( time(), 'ecomail_bulk_import_users_finished' );
				break;
			}

			as_schedule_single_action( time(), 'ecomail_bulk_import_users', array( 'ids' => $users ) );
		}
		wp_safe_redirect( admin_url() );
	}

	public function bulk_import_users( $ids ) {
		$args  = array(
			'include' => $ids,
			'limit'   => - 1,
		);
		$users = get_users( $args );
		$data  = array();
		foreach ( $users as $user ) {
			/** @var \WP_User $user */
			$customer_data['email'] = $user->user_email;
			$fields                 = $this->settings->get_option( 'woocommerce_checkout_subscribe_fields' );
			$customer               = new \WC_Customer( $user->ID );
			if ( in_array( 'first_name', $fields ) ) {
				$customer_data['name'] = $customer->get_billing_first_name();
			}
			if ( in_array( 'last_name', $fields ) ) {
				$customer_data['surname'] = $customer->get_billing_last_name();
			}
			if ( in_array( 'company', $fields ) ) {
				$customer_data['company'] = $customer->get_billing_company();
			}
			if ( in_array( 'city', $fields ) ) {
				$customer_data['city'] = $customer->get_billing_city();
			}
			if ( in_array( 'street', $fields ) ) {
				$customer_data['street'] = $customer->get_billing_address_1();
			}
			if ( in_array( 'postcode', $fields ) ) {
				$customer_data['zip'] = $customer->get_billing_postcode();
			}
			if ( in_array( 'country', $fields ) ) {
				$customer_data['country'] = $customer->get_billing_country();
			}
			if ( in_array( 'phone', $fields ) ) {
				$customer_data['phone'] = $customer->get_billing_phone();
			}

			if ( $this->settings->get_option( 'api_source' ) ) {
				$customer_data['source'] = $this->settings->get_option( 'api_source' );
			}
			$data[] = $customer_data;
		}

		$request_data = array(
			'subscriber_data'        => $data,
			'update_existing'        => boolval( $this->settings->get_option( 'woocommerce_checkout_update' ) ),
			'skip_confirmation'      => boolval( $this->settings->get_option( 'woocommerce_checkout_skip_confirmation' ) ),
			'trigger_autoresponders' => boolval( $this->settings->get_option( 'woocommerce_checkout_trigger_autoresponders' ) ),
		);

		return $this->ecomail_api->bulk_add_subscribers( $this->settings->get_option( 'woocommerce_checkout_list_id' ), $request_data );
	}

	public function finish_bulk_import_users() {
		delete_option( 'ecomail_users_upload_pending' );
	}

	public function pending_bulk_upload_notice() {
		if ( get_option( 'ecomail_users_upload_pending' ) ) {
			?>
			<div class="notice notice-warning">
				<p><?php _e( 'The bulk upload of users to Ecomail is pending.', 'ecomail' ); ?></p>
			</div>
			<?php
		}
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
