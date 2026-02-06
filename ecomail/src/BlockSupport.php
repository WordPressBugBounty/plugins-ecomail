<?php

namespace Ecomail;

use Ecomail\Repositories\SettingsRepository;

class BlockSupport {
	private WooCommerce $woo_commerce;
	private SettingsRepository $settings;
	private Ecomail $ecomail;

	public function __construct( WooCommerce $woo_commerce, SettingsRepository $settings, Ecomail $ecomail ) {
		$this->woo_commerce = $woo_commerce;
		$this->settings = $settings;
		$this->ecomail = $ecomail;

		add_action( 'woocommerce_init', [ $this, 'register_checkout_fields' ] );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'handle_block_checkout_order' ] );
		add_filter( 'woocommerce_set_additional_field_value', [ $this, 'save_field_metadata' ], 10, 4 );
	}

	public function register_checkout_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		if ( ! $this->settings->get_option( 'woocommerce_checkout_subscribe' ) || ! $this->settings->get_option( 'woocommerce_checkout_subscribe_checkbox' ) ) {
			return;
		}

		$field_label = $this->settings->get_option( 'woocommerce_checkout_not_subscribe_text' );

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'ecomail/not_subscribe',
				'label'    => $field_label,
				'location' => 'contact',
				'type'     => 'checkbox',
				'default'  => 0,
				'meta_key' => Ecomail::INPUT_NAME,
			)
		);
	}

	public function handle_block_checkout_order( $order ) {
		$this->woo_commerce->order_created( $order->get_id() );
	}

	public function save_field_metadata( $key, $value, $group, $wc_object ) {
		if ( $key === 'ecomail/not_subscribe' ) {
			$wc_object->update_meta_data( Ecomail::INPUT_NAME, $value );
		}
	}
}
