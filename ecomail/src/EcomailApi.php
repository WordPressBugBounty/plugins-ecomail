<?php

namespace Ecomail;

use Ecomail\Repositories\SettingsRepository;
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

	public function __construct( SettingsRepository $settings ) {
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

		return $this->handle_response( $this->api->getListsCollection() );
	}

	/**
	 * Add Subscriber
	 *
	 * @param       $list_id
	 * @param array   $data
	 *
	 * @return WP_Error
	 */
	public function add_subscriber( $list_id, array $data ) {
		$this->initialize();

		return $this->handle_response( $this->api->addSubscriber( $list_id, $data ) );
	}


	/**
	 * Bulk Add Subscribers
	 *
	 * @param       $list_id
	 * @param array   $data
	 *
	 * @return WP_Error
	 */
	public function bulk_add_subscribers( $list_id, array $data ) {
		$this->initialize();

		return $this->handle_response( $this->api->addSubscriberBulk( $list_id, $data ) );
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

		return $this->handle_response( $this->api->createNewTransaction( $data ) );
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

		return $this->handle_response( $this->api->addEvent( array( 'event' => $data ) ) );
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
	public function handle_response( $response ) {
		if ( ! empty( $response['error'] ) ) {
			return new WP_Error( $response['error'], sprintf( 'Error code %s', $response['error'] ) );
		}

		return $response;
	}
}
