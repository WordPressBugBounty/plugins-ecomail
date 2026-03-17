<?php

namespace Ecomail;

use Ecomail\Models\WooOrderModel;
use Ecomail\Repositories\SettingsRepository;
use Ecomail\Repositories\WooOrderRepository;
use EcomailDeps\Wpify\Log\RotatingFileLog;

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
	const OPTION_BULK_ORDERS_UPDATE_IDS = 'ecomail_orders_update_ids';
	const OPTION_IMPORTED_ORDER_IDS = 'ecomail_imported_order_ids';
	const SCHEDULE_ORDERS_LIMIT = 500;
	const SCHEDULE_ORDERS_UPDATE_LIMIT = 200;
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
		private RotatingFileLog $log
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
		add_action( 'admin_action_ecomail_bulk_upload_users_and_orders', array(
			$this,
			'maybe_schedule_users_and_orders_upload'
		) );
		add_action( 'admin_action_ecomail_bulk_update_orders', array( $this, 'maybe_schedule_update_orders' ) );
		add_action( 'init', array( $this, 'handle_subscription_toggle' ) );
		add_action( 'ecomail_bulk_import_users', array( $this, 'bulk_import_users' ) );
		add_action( 'ecomail_bulk_import_orders', array( $this, 'bulk_import_orders' ) );
		add_action( 'ecomail_bulk_update_orders', array( $this, 'bulk_update_orders' ) );
		add_action( 'ecomail_sync_imported_orders', array( $this, 'sync_imported_orders' ) );
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

		if ( function_exists( 'WC' ) && WC() && ! empty( WC()->customer ) && ! empty( WC()->customer->get_billing_email() ) ) {
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
			wp_die( esc_html( __( 'The upload is running on background', 'ecomail' ) ) );
		}

		$this->add_user_ids_to_list();
		$this->log->info( 'Scheduled users bulk upload' );
		wp_safe_redirect( $this->settings->get_settings_url() );
	}

	/**
	 * Maybe schedule users and orders upload.
	 *
	 * @return void
	 */
	public function maybe_schedule_users_and_orders_upload() {
		if ( $this->is_import_running() || $this->is_sync_running() ) {
			wp_die( esc_html( __( 'The upload is running on background', 'ecomail' ) ) );
		}

		// Flag to schedule users and orders upload after sync finishes
		update_option( 'ecomail_sync_with_orders', true );

		// Sync imported orders first, users+orders will be scheduled after sync completes
		$this->schedule_sync_imported_orders();
		$this->log->info( 'Scheduled sync and users and orders bulk upload' );
		wp_safe_redirect( $this->settings->get_settings_url() );
	}

	/**
	 * Maybe schedule orders update.
	 *
	 * @return void
	 */
	public function maybe_schedule_update_orders() {
		if ( $this->is_import_running() || $this->is_sync_running() ) {
			wp_die( esc_html( __( 'The upload is running on background', 'ecomail' ) ) );
		}

		// Flag to schedule order updates after sync finishes
		update_option( 'ecomail_sync_with_update', true );

		// Sync imported orders first, update will be scheduled after sync completes
		$this->schedule_sync_imported_orders();
		$this->log->info( 'Scheduled bulk update orders' );
		wp_safe_redirect( $this->settings->get_settings_url() );
	}

	/**
	 * Add user IDs to list.
	 *
	 * @param int  $paged
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
		if ( ! class_exists( 'WC_Customer' ) ) {
			return;
		}

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
	 * Add order IDs to update list.
	 * Only adds orders that exist in Ecomail (imported orders).
	 *
	 * @return void
	 */
	public function add_order_ids_to_update_list() {
		// Get all imported order IDs from Ecomail
		$imported_order_ids = get_option( self::OPTION_IMPORTED_ORDER_IDS, array() );

		if ( empty( $imported_order_ids ) ) {
			$this->log->info( 'No imported orders found, skipping bulk update' );

			return;
		}

		// Convert imported order IDs to integers
		$imported_order_ids_int = array_map( 'intval', $imported_order_ids );


		// Process in batches to avoid memory issues with large datasets
		$chunks          = array_chunk( $imported_order_ids_int, 500 );
		$existing_orders = array();

		foreach ( $chunks as $chunk ) {
			$args = array(
				'post__in' => $chunk,
				'return'   => 'ids',
				'limit'    => - 1,
			);

			$found_order_ids = wc_get_orders( $args );
			if ( ! empty( $found_order_ids ) ) {
				$existing_orders = array_merge( $existing_orders, $found_order_ids );
			}
		}

		if ( empty( $existing_orders ) ) {
			$this->log->info( 'No matching orders to update found' );

			return;
		}

		// Add existing order IDs to update option
		$this->update_ids_option( self::OPTION_BULK_ORDERS_UPDATE_IDS, $existing_orders, true );

		$this->log->info( 'Added order IDs to update list', [
			'total_imported'  => count( $imported_order_ids ),
			'matching_orders' => count( $existing_orders )
		] );

		// Schedule first update batch
		if ( ! as_has_scheduled_action( 'ecomail_bulk_update_orders', array(), 'ecomail' ) ) {
			$this->schedule_orders_update();
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
	 * Schedule orders upload.
	 *
	 * @return void
	 */
	private function schedule_orders_update() {
		as_schedule_single_action( time(), 'ecomail_bulk_update_orders', array(), 'ecomail' );
	}

	/**
	 * Update IDs option.
	 *
	 * @param $key
	 * @param $ids
	 *
	 * @return void
	 */
	private function update_ids_option( $key, $ids, $rewrite = false ) {
		if ( $rewrite ) {
			$final_ids = $ids;
		} else {
			$current_ids = get_option( $key, array() );
			$final_ids   = array_merge( $current_ids, $ids );
		}

		$final_ids = array_unique( $final_ids );

		update_option( $key, $final_ids );

	}

	/**
	 * Is import running.
	 *
	 * @return bool
	 */
	private function is_import_running() {
		return (
			as_has_scheduled_action( 'ecomail_bulk_import_users', array(), 'ecomail' ) ||
			as_has_scheduled_action( 'ecomail_bulk_import_orders', array(), 'ecomail' ) ||
			as_has_scheduled_action( 'ecomail_bulk_update_orders', array(), 'ecomail' )
		);
	}

	/**
	 * Is sync running.
	 *
	 * @return bool
	 */
	private function is_sync_running() {
		return as_has_scheduled_action( 'ecomail_sync_imported_orders', array(), 'ecomail' );
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
		$this->update_ids_option( self::OPTION_BULK_USERS_UPLOAD_IDS, $user_ids, true );

		if ( empty( $user_ids_to_import ) ) {
			$this->log->info( 'Bulk import users finished. No more users to import' );

			return;
		}
		$this->log->info( 'Bulk import users', [
			'to_import_count' => count( $user_ids_to_import ),
			'to_import_left'  => count( $user_ids ),
		] );

		$args  = array(
			'include' => $user_ids_to_import,
			'limit'   => - 1,
		);
		$users = get_users( $args );
		$data  = array();

		if ( ! class_exists( 'WC_Customer' ) ) {
			$this->log->error( 'WC_Customer class not found' );

			return;
		}

		$skipped_invalid_emails = 0;
		foreach ( $users as $user ) {
			/** @var \WP_User $user */
			$customer        = new \WC_Customer( $user->ID );
			$subscriber_data = $this->get_subscribe_data_from_object( $customer );
			// Only add if we have valid subscriber data with email
			if ( ! empty( $subscriber_data['email'] ) && is_email( $subscriber_data['email'] ) ) {
				$data[] = $subscriber_data;
			} else {
				$skipped_invalid_emails ++;
			}
		}

		if ( $skipped_invalid_emails > 0 ) {
			$this->log->info( 'Skipped users with invalid or empty email', [ 'count' => $skipped_invalid_emails ] );
		}

		if ( ! empty( $data ) ) {
			$request_data = array(
				'subscriber_data'        => $data,
				'update_existing'        => boolval( $this->settings->get_option( 'woocommerce_checkout_update' ) ),
				'skip_confirmation'      => true,
				'resubscribe'            => false,
				'trigger_autoresponders' => false,
			);

			$this->ecomail_api->bulk_add_subscribers( $this->settings->get_option( 'woocommerce_checkout_list_id' ), $request_data );
		}

		if ( count( $user_ids ) !== 0 ) {
			$this->schedule_users_upload();
		}
	}

	/**
	 * Sync imported orders from Ecomail.
	 *
	 * @param int $page
	 *
	 * @throws \Exception
	 */
	public function sync_imported_orders( $page = 1 ) {
		// Reset imported order IDs on first page to prevent stale data from previous API key
		if ( $page === 1 ) {
			delete_option( self::OPTION_IMPORTED_ORDER_IDS );
		}

		$data = array(
			'page'     => $page,
			'per_page' => 200,
		);

		$response = $this->ecomail_api->bulk_get_transactions( $data );

		if ( is_wp_error( $response ) ) {
			$this->log->error( 'Failed to sync imported orders', [
				'page'  => $page,
				'error' => $response->get_error_message()
			] );

			return;
		}

		$imported_order_ids = get_option( self::OPTION_IMPORTED_ORDER_IDS, array() );
		$new_order_ids      = array();

		if ( ! empty( $response['data'] ) ) {
			foreach ( $response['data'] as $transaction ) {
				if ( ! empty( $transaction['order_id'] ) ) {
					$new_order_ids[] = (string) $transaction['order_id'];
				}
			}
		}

		if ( ! empty( $new_order_ids ) ) {
			$imported_order_ids = array_unique( array_merge( $imported_order_ids, $new_order_ids ) );
			update_option( self::OPTION_IMPORTED_ORDER_IDS, $imported_order_ids );

			$this->log->info( 'Synced imported orders', [
				'page'               => $page,
				'new_orders_count'   => count( $new_order_ids ),
				'total_orders_count' => count( $imported_order_ids )
			] );
		}

		// Continue to next page if needed
		$current_page = $response['current_page'] ?? $page;
		$to           = $response['to'] ?? 0;
		$total        = $response['total'] ?? 0;

		if ( $to < $total ) {
			$this->schedule_sync_imported_orders( $current_page + 1 );
		} else {
			$this->log->info( 'Sync imported orders finished', [
				'total_synced' => count( $imported_order_ids )
			] );

			// Schedule users and orders upload if requested
			if ( get_option( 'ecomail_sync_with_orders' ) ) {
				delete_option( 'ecomail_sync_with_orders' );
				$this->add_user_ids_to_list( 1, true );
				$this->log->info( 'Scheduled users and orders upload after sync' );
			}

			// Schedule order updates if requested
			if ( get_option( 'ecomail_sync_with_update' ) ) {
				delete_option( 'ecomail_sync_with_update' );
				$this->add_order_ids_to_update_list();
				$this->log->info( 'Scheduled order updates after sync' );
			}
		}
	}

	/**
	 * Schedule sync imported orders.
	 *
	 * @param int $page
	 *
	 * @return void
	 */
	private function schedule_sync_imported_orders( $page = 1 ) {
		as_schedule_single_action( time(), 'ecomail_sync_imported_orders', array( $page ), 'ecomail' );
	}

	/**
	 * Bulk import orders.
	 *
	 * @throws \Exception
	 */
	public function bulk_import_orders() {
		$order_ids           = get_option( self::OPTION_BULK_ORDERS_UPLOAD_IDS, array() );
		$order_ids_to_import = array_splice( $order_ids, 0, self::SCHEDULE_ORDERS_LIMIT );
		$this->update_ids_option( self::OPTION_BULK_ORDERS_UPLOAD_IDS, $order_ids, true );

		if ( empty( $order_ids_to_import ) ) {
			$this->log->info( 'Bulk import orders finished. No more orders to import' );

			return;
		}

		// Filter out already imported orders
		$imported_order_ids        = get_option( self::OPTION_IMPORTED_ORDER_IDS, array() );
		$imported_order_ids_lookup = array_flip( $imported_order_ids );
		$original_count            = count( $order_ids_to_import );

		$order_ids_to_import = array_filter( $order_ids_to_import, function ( $order_id ) use ( $imported_order_ids_lookup ) {
			return ! isset( $imported_order_ids_lookup[ (string) $order_id ] );
		} );

		$filtered_count     = count( $order_ids_to_import );
		$skipped_duplicates = $original_count - $filtered_count;

		if ( $skipped_duplicates > 0 ) {
			$this->log->info( 'Skipped already imported orders', [ 'count' => $skipped_duplicates ] );
		}

		if ( empty( $order_ids_to_import ) ) {
			$this->log->info( 'Bulk import orders: all orders already imported, skipping to next batch' );

			if ( count( $order_ids ) !== 0 ) {
				$this->schedule_orders_upload();
			}

			return;
		}

		$this->log->info( 'Bulk import orders', [
			'to_import_count'    => $filtered_count,
			'to_import_left'     => count( $order_ids ),
			'skipped_duplicates' => $skipped_duplicates
		] );

		$transactions   = array();
		$skipped_orders = 0;
		/** Order model. @var WooOrderModel $order */
		foreach ( $this->order_repository->find_by_ids( $order_ids_to_import ) as $order ) {
			if ( ! $order ) {
				$skipped_orders += 1;
				continue;
			}

			$transactions[] = $order->get_transaction_data();
		}

		if ( $skipped_orders > 0 ) {
			$this->log->warning( 'Skipped orders - not found', [ 'count' => $skipped_orders ] );
		}

		if ( ! empty( $transactions ) ) {
			$data = array(
				'transaction_data' => $transactions,
			);

			$response = $this->ecomail_api->bulk_add_transactions( $data );

			// If successful, add order IDs to imported list
			if ( ! is_wp_error( $response ) ) {
				$imported_order_ids = get_option( self::OPTION_IMPORTED_ORDER_IDS, array() );
				$new_imported_ids   = array_map( 'strval', $order_ids_to_import );
				$imported_order_ids = array_unique( array_merge( $imported_order_ids, $new_imported_ids ) );
				update_option( self::OPTION_IMPORTED_ORDER_IDS, $imported_order_ids );

				$this->log->info( 'Updated imported orders list', [
					'newly_imported' => count( $new_imported_ids ),
					'total_imported' => count( $imported_order_ids )
				] );
			}
		}

		if ( count( $order_ids ) !== 0 ) {
			$this->schedule_orders_upload();
		}
	}

	/**
	 * Bulk update orders.
	 * Updates existing orders in Ecomail using individual update_transaction API calls.
	 * Processes max 200 orders per batch with 1-minute delay between batches for rate limiting.
	 *
	 * @throws \Exception
	 */
	public function bulk_update_orders() {
		$order_ids           = get_option( self::OPTION_BULK_ORDERS_UPDATE_IDS, array() );
		$order_ids_to_update = array_splice( $order_ids, 0, self::SCHEDULE_ORDERS_UPDATE_LIMIT );
		$this->update_ids_option( self::OPTION_BULK_ORDERS_UPDATE_IDS, $order_ids, true );

		if ( empty( $order_ids_to_update ) ) {
			$this->log->info( 'Bulk update orders finished. No more orders to update' );

			return;
		}

		$this->log->info( 'Bulk update orders started', [
			'to_update_count' => count( $order_ids_to_update ),
			'to_update_left'  => count( $order_ids ),
		] );

		$log_data   = array(
			'successful'   => 0,
			'not_found'    => 0,
			'empty_data'   => 0,
			'update_error' => 0
		);
		$last_error = null;

		/** @var WooOrderModel $order */
		foreach ( $this->order_repository->find_by_ids( $order_ids_to_update ) as $order ) {
			if ( ! $order ) {
				$log_data['not_found'] ++;
				continue;
			}

			$transaction_data = $order->get_transaction_data();
			if ( empty( $transaction_data['transaction'] ) ) {
				$log_data['empty_data'] ++;
				continue;
			}

			// Use individual update_transaction API call (not bulk)
			$response = $this->ecomail_api->update_transaction(
				(int) $order->id,
				$transaction_data,
				true
			);

			if ( is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
				$log_data['update_error'] ++;
			} else {
				$log_data['successful'] ++;
			}
		}

		$log_data['remaining_orders'] = count( $order_ids );

		if ( ! empty( $last_error ) ) {
			$log_data['last_error'] = $last_error;
		}

		$this->log->info( 'Bulk update orders batch completed', $log_data );

		// Schedule next batch if there are more orders (with 1-minute delay for rate limiting)
		if ( count( $order_ids ) > 0 ) {
			as_schedule_single_action( time() + 60, 'ecomail_bulk_update_orders', array(), 'ecomail' );
			$this->log->info( 'Scheduled next update batch in 1 minute', [
				'remaining_orders' => count( $order_ids )
			] );
		} else {
			$this->log->info( 'Bulk update orders completed successfully' );
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

		if ( ! class_exists( 'WC_Customer' ) || ! class_exists( 'WC_Order' ) ) {
			return $data;
		}

		if ( ! is_a( $object, 'WC_Customer' ) && ! is_a( $object, 'WC_Order' ) ) {
			return $data;
		}

		$email = $object->get_billing_email();
		if ( ! $email ) {
			if ( is_a( $object, 'WC_Customer' ) ) {
				$email = $object->get_email();
			} else {
				$user  = $object->get_user();
				$email = $user ? $user->user_email : null;
			}
		}
		if ( ! $email || ! is_email( $email ) ) {
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
		if ( $this->is_import_running() || $this->is_sync_running() ) {
			$message = $this->is_sync_running() ?
				__( 'Synchronizing existing orders from Ecomail...', 'ecomail' ) :
				__( 'The bulk upload to Ecomail is pending.', 'ecomail' );
			?>
			<div class="notice notice-warning">
				<p><?php echo esc_html( $message ); ?></p>
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
					esc_html( __( 'API connection status: %1$s, Last request: %2$s. %3$s', 'ecomail' ) ),
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

	/**
	 * Handle subscription toggle request
	 *
	 * @return void
	 */
	public function handle_subscription_toggle() {
		if ( ! isset( $_POST['ecomail_toggle_subscription'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['ecomail_subscription_nonce'], 'ecomail_toggle_subscription' ) ) {
			$this->log->warning( 'Failed to verify nonce', [] );

			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			$this->log->warning( 'User is not logged in', [] );

			return;
		}

		/** @var \WP_User $user */
		$user           = wp_get_current_user();
		$current_status = get_user_meta( $user->ID, '_ecomail_subscribe', true );
		$subscribed     = $current_status === 'SUBSCRIBED';

		// Toggle subscription
		$success = $this->toggle_user_subscription( $user, ! $subscribed );

		// Add success notice
		if ( function_exists( 'wc_add_notice' ) ) {
			if ( $success ) {
				if ( ! $subscribed ) {
					wc_add_notice( __( 'Successfully subscribed to newsletter.', 'ecomail' ), 'success' );
				} else {
					wc_add_notice( __( 'Successfully unsubscribed from newsletter.', 'ecomail' ), 'success' );
				}
			} else {
				wc_add_notice( __( 'Failed to toggle subscription.', 'ecomail' ), 'error' );
			}
		}

		// Redirect back to account page
		wp_safe_redirect( wc_get_account_endpoint_url( 'dashboard' ) );
		exit;
	}

	/**
	 * Toggle user subscription status
	 *
	 * @param \WP_User $user
	 * @param bool     $subscribe
	 *
	 * @return void
	 */
	private function toggle_user_subscription( \WP_User $user, bool $subscribe ): bool {
		$user_id = $user->ID;
		$email   = $this->get_customer_email();
		$status  = $subscribe ? 'SUBSCRIBED' : 'UNSUBSCRIBED';
		$list_id = $this->settings->get_option( 'woocommerce_checkout_list_id' );

		// Validate email
		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->log->warning( 'Invalid or empty email for subscription toggle', [ 'email' => $email ] );

			return false;
		}

		$this->log->info( 'Toggle user subscription', [
			'user_id'   => $user_id,
			'email'     => $email,
			'list_id'   => $list_id,
			'to_status' => $status,
		] );

		// Check if subscriber exists in Ecomail
		$existing_subscriber = $this->ecomail_api->get_subscriber( $list_id, $email );
		$subscriber_exists   = $existing_subscriber && ! is_wp_error( $existing_subscriber ) && ! empty( $existing_subscriber['subscriber'] );

		// If user wants to unsubscribe
		if ( ! $subscribe ) {
			if ( $subscriber_exists ) {
				// Remove subscriber from Ecomail
				$remove_result = $this->ecomail_api->remove_subscriber( $list_id, array( 'email' => $email ) );

				if ( is_wp_error( $remove_result ) ) {
					return false;
				}

				$existing_tags = ! empty( $existing_subscriber['subscriber']['tags'] ) ? $existing_subscriber['subscriber']['tags'] : [];
				if ( $existing_tags && in_array( 'wp_newsletter', $existing_tags ) ) {
					unset( $existing_tags[ array_search( 'wp_newsletter', $existing_tags ) ] );
					$update_data   = array(
						'email'           => $email,
						'subscriber_data' => array(
							'tags' => array_unique( $existing_tags )
						)
					);
					$update_result = $this->ecomail_api->update_subscriber( $list_id, $update_data );

					if ( is_wp_error( $update_result ) ) {
						return false;
					}
				}
			} else {
				$this->log->info( 'Subscriber not found, nothing to do for unsubscribe', [] );
			}

			// Update user meta only after successful API call or if subscriber doesn't exist
			update_user_meta( $user_id, '_ecomail_subscribe', $status );

			return true;
		}

		if ( ! class_exists( 'WC_Customer' ) ) {
			$this->log->error( 'WC_Customer class not found' );

			return false;
		}

		$customer = new \WC_Customer( $user->ID );

		if ( ! $customer ) {
			$this->log->error( 'WC customer not exist' );

			return false;
		}

		$subscriber_data = $this->get_subscribe_data_from_object( $customer );

		// If subscriber exists, merge existing tags
		$existing_tags           = $subscriber_exists && ! empty( $existing_subscriber['subscriber']['tags'] ) ? $existing_subscriber['subscriber']['tags'] : [];
		$subscriber_data['tags'] = array_unique( array_merge( $existing_tags, array( 'wp_newsletter' ) ) );

		$subscriber_data['status'] = 1;
		$subscriber_data['email']  = $email;

		// Prepare API data
		$data = array(
			'subscriber_data'        => $subscriber_data,
			'update_existing'        => true, // Always update if exists
			'skip_confirmation'      => true, // Manual action by a user
			'trigger_autoresponders' => boolval( $this->settings->get_option( 'woocommerce_checkout_trigger_autoresponders', false ) ),
			'resubscribe'            => true,
		);

		// Send to Ecomail API
		$result = $this->ecomail_api->add_subscriber( $list_id, $data );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Update user meta only after successful API call
		update_user_meta( $user_id, '_ecomail_subscribe', $status );

		return true;
	}
}
