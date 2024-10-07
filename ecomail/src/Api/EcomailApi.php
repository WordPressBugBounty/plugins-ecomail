<?php

namespace Ecomail\Api;

use Ecomail\Ecomail;
use Ecomail\Managers\ApiManager;
use Ecomail\WooCommerce;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EcomailApi extends WP_REST_Controller {

	/** @var WooCommerce */
	private $woocommerce;

	/** @var Ecomail */
	private $ecomail;


	public function __construct(
		WooCommerce $woocommerce,
		Ecomail $ecomail
	) {
		$this->woocommerce = $woocommerce;
		$this->ecomail     = $ecomail;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			ApiManager::REST_NAMESPACE,
			'cart',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'track_cart' ),
					'permission_callback' => '__return_true',
				),
			)
		);
		register_rest_route(
			ApiManager::REST_NAMESPACE,
			'product',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'track_product' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'product_id' => array(
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Add box to cart
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function track_cart( $request ) {
		$email = $request->get_param( 'email' );
		if ( ! $email ) {
			$email = $this->ecomail->get_customer_email();
		}

		if ( ! $email ) {
			return new WP_REST_Response( array( 'status' => 'no-email' ) );
		}

		$items = $request->get_param( 'items' );
		if ( ! $items ) {
			$items = $this->woocommerce->get_cart_items();
		}

		$result = $this->woocommerce->track_cart( $items, $email );

		if ( ! is_wp_error( $result ) ) {
			$this->woocommerce->delete_update_cart_flag();
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Add box to cart
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function track_product( $request ) {
		$product_id = $request->get_param( 'product_id' );
		$email      = $this->ecomail->get_customer_email();

		if ( ! $email ) {
			return new WP_REST_Response( array( 'status' => 'no-email' ) );
		}

		$result = $this->woocommerce->track_product( $product_id, $email );

		return rest_ensure_response( $result );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return \WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		return true;
	}


	/**
	 * Prepare the item for the REST response
	 *
	 * @param mixed           $item    WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return array();
	}
}
