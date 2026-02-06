<?php

namespace Ecomail;

use Ecomail\Repositories\SettingsRepository;
use EcomailDeps\Wpify\Log\RotatingFileLog;
use WP_Error;

class EcomailApi {

	private $api_key = '';
	/**
	 * @var \EcomailDeps\Ecomail $api
	 */
	private $api;
	/**
	 * @var SettingsRepository
	 */
	private $settings;

	public function __construct( SettingsRepository $settings, private RotatingFileLog $log ) {
		$this->settings = $settings;
	}

	/**
	 * Initialize the API
	 */
	public function initialize() {
		static $initialized;
		if ( ! $initialized ) {
			$this->api_key = $this->settings->get_option( 'api_key' );
			$this->api     = new \EcomailDeps\Ecomail( $this->api_key );
			$initialized   = true;
		}
	}

	/**
	 * Get Ecomail Lists
	 *
	 * @return WP_Error
	 */
	public function get_lists() {
		$this->initialize();

		return $this->handle_response( $this->api->getListsCollection(), 'GET /lists' );
	}

	/**
	 * Get Ecomail Lists
	 *
	 * @return WP_Error
	 */
	public function get_subscriber( $list_id, $email ) {
		$this->initialize();

		return $this->handle_response( $this->api->getSubscriber( $list_id, $email ),
			'GET /subscribers', [ 'email' => $email ]
		);
	}

	/**
	 * Add Subscriber
	 *
	 * @param       $list_id
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function add_subscriber( $list_id, array $data ) {
		$this->initialize();

		return $this->handle_response( $this->api->addSubscriber( $list_id, $data ),
			'POST /lists/{list_id}/subscribe',
			[
				'list_id' => $list_id,
				'data'    => $data
			]
		);
	}

	/**
	 * Add Subscriber
	 *
	 * @param       $list_id
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function update_subscriber( $list_id, array $data ) {
		$this->initialize();

		return $this->handle_response( $this->api->updateSubscriber( $list_id, $data ),
			'POST /lists/{list_id}/update-subscriber',
			[
				'list_id' => $list_id,
				'data'    => $data
			]
		);
	}

	/**
	 * Remove Subscriber
	 *
	 * @param       $list_id
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function remove_subscriber( $list_id, array $data ) {
		$this->initialize();

		return $this->handle_response( $this->api->removeSubscriber( $list_id, $data ),
			'DELETE lists/{list_id}/unsubscribe',
			[
				'list_id' => $list_id,
				'data'    => $data
			]
		);
	}

	/**
	 * Bulk Add Subscribers
	 *
	 * @param       $list_id
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function bulk_add_subscribers( $list_id, array $data ) {
		$this->initialize();

		$log_data                    = $data;
		$log_data['subscriber_data'] = [ 'data'  => '... hidden ...',
		                                 'count' => count( $data['subscriber_data'] ?? [] )
		];

		return $this->handle_response( $this->api->addSubscriberBulk( $list_id, $data ),
			'POST lists/{list_id}/subscribe-bulk',
			[
				'list_id' => $list_id,
				'data'    => $log_data
			]
		);
	}

	/**
	 * Bulk Add Transactions
	 *
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function bulk_add_transactions( array $data ) {
		$this->initialize();

		// Prepare log data with hidden transaction details
		$log_data                     = $data;
		$log_data['transaction_data'] = [ 'data'  => '... hidden ...',
		                                  'count' => count( $data['transaction_data'] ?? [] )
		];

		return $this->handle_response( $this->api->createBulkTransactions( $data ),
			'POST tracker/transaction-bulk',
			$log_data
		);
	}

	/**
	 * Bulk Add Transactions
	 *
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function bulk_get_transactions( array $data ) {
		$this->initialize();

		$data['shop'] = site_url();

		return $this->handle_response( $this->api->getTransactions( $data ),
			'GET tracker/transactions',
			$data
		);
	}

	/**
	 * Add transaction
	 *
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function add_transaction( array $data ) {
		$this->initialize();

		return $this->handle_response( $this->api->createNewTransaction( $data ),
			'POST tracker/transaction',
			$data
		);
	}

	/**
	 * Update transaction
	 *
	 * @param int   $order_id
	 * @param array $data
	 *
	 * @return WP_Error
	 */
	public function update_transaction( int $order_id, array $data, bool $silent = false ) {
		$this->initialize();

		return $this->handle_response( $this->api->updateTransaction( $order_id, $data ),
			'PUT tracker/transaction/{order_id}',
			[
				'order_id' => $order_id,
				'data'     => $data
			],
			$silent
		);
	}

	/**
	 * Update the cart
	 *
	 * @param $email
	 * @param $products
	 *
	 * @return WP_Error
	 */
	public function update_cart( $email, $products ) {
		$this->initialize();

		$value = array(
			'data' => array(
				'data' => array(
					'action'   => 'Basket',
					'products' => $products,
				),
			),
		);
		$data  = array(
			'email'    => $email,
			'category' => 'ue',
			'action'   => 'Basket',
			'label'    => 'Basket',
			'value'    => json_encode( $value ),
		);

		return $this->handle_response( $this->api->addEvent( array( 'event' => $data ) ),
			'POST tracker/events',
			array(
				'email'    => $email,
				'category' => 'ue',
				'action'   => 'Basket',
				'label'    => 'Basket',
				'value'    => '{ ... hidden data ... }',
			)
		);
	}

	/**
	 * Track product view
	 *
	 * @param $product_id
	 * @param $email
	 *
	 * @return WP_Error
	 */
	public function track_product( $product_id, $email ) {
		$this->initialize();

		$data = array(
			'email'    => $email,
			'category' => 'ECM_PRODUCT_VIEW',
			'action'   => (string) $product_id,
		);

		return $this->handle_response( $this->api->addEvent( array( 'event' => $data ) ) );
	}

	/**
	 * @return string
	 */
	public function get_api_key(): string {
		return $this->api_key;
	}

	/**
	 * Handle API response
	 *
	 * @param $response
	 *
	 * @return WP_Error
	 */
	public function handle_response( $response, $endpoint = null, $request_args = [], $silent = false ) {
		$message = sprintf( 'API: %s', $endpoint ?: 'error' );;

		if ( ! empty( $response['error'] ) ) {
			if ( ! $silent ) {
				$this->log->error( $message, [ 'request' => $request_args, 'response' => $response ] );
			}

			return new WP_Error( $response['error'], sprintf( 'Error code %s', $response['error'] ) );
		}

		if ( ! empty( $endpoint ) && ! $silent ) {
			$this->log->info( $message, [ 'request' => $request_args, 'response' => 'OK' ] );
		}

		return $response;
	}
}
