<?php

namespace Ecomail\Managers;

use Ecomail\Api\EcomailApi;

final class ApiManager {

	public const REST_NAMESPACE = 'ecomail/v1';
	public const NONCE_ACTION   = 'wp_rest';

	public function __construct(
		EcomailApi $ecomail_api
	) {
		add_action( 'init', array( $this, 'enable_wc_frontend_in_rest' ) );
	}

	public function get_rest_url(): string {
		return rest_url( $this::REST_NAMESPACE );
	}

	public function enable_wc_frontend_in_rest() {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}

		if ( ! WC()->is_rest_api_request() ) {
			return;
		}

		WC()->frontend_includes();

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( WC()->session ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}
}
