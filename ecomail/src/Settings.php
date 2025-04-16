<?php

namespace Ecomail;

use EcomailDeps\Wpify\CustomFields\CustomFields;

/**
 * Class Settings
 *
 * @package Wpify\Settings
 */
class Settings {
	/**
	 * @var CustomFields
	 */
	public $wcf;

	/**
	 * @var Ecomail
	 */
	public $ecomail;

	/**
	 * @var array
	 */
	public $options = array();

	/**
	 * Option key, and option page slug
	 *
	 * @var string
	 */
	const KEY = 'ecomail';

	public function __construct( CustomFields $wcf, Ecomail $ecomail ) {
		$this->wcf     = $wcf;
		$this->ecomail = $ecomail;

		$this->setup();
	}

	public function setup() {
		$this->wcf->create_options_page( $this->get_args() );
	}

	public function get_args() {
		$settings   = get_option( self::KEY );
		$additional = array();

		if ( is_array( $settings ) && ! empty( $settings['api_key'] ) && ! empty( $settings['app_id'] ) ) {
			$additional = array(
				array(
					'id'          => 'enable_tracking_code',
					'type'        => 'toggle',
					'title'       => __( 'Add tracking code to website', 'ecomail-woocommerce' ),
					'description' => __( 'Check to add tracking code to the website', 'ecomail-woocommerce' ),
				),
				array(
					'id'          => 'enable_manual_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable manual tracking', 'ecomail-woocommerce' ),
					'description' => __( 'Check if you want to identify the user by WP login details. The priorities are - Ecomail email, Customer email, WP User email', 'ecomail-woocommerce' ),
				),
				array(
					'id'          => 'woocommerce_checkout_subscribe',
					'type'        => 'toggle',
					'title'       => __( 'Subscribe on checkout', 'ecomail-woocommerce' ),
					'description' => __( 'Check to enable Ecomail subscriptions on checkout', 'ecomail-woocommerce' ),
				),
				array(
					'id'          => 'woocommerce_checkout_subscribe_checkbox',
					'type'        => 'toggle',
					'title'       => __( 'Show checkbox on checkout', 'ecomail-woocommerce' ),
					'description' => __( 'Check to display "I dont\'n like to receive newsletters" checkbox on checkout', 'ecomail-woocommerce' ),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
				),
				array(
					'id'          => 'woocommerce_checkout_not_subscribe_text',
					'type'        => 'text',
					'title'       => __( 'Text for Not subscribe on checkout checkbox', 'ecomail-woocommerce' ),
					'description' => __( 'Enter the text that will appear on checkout to disable subscription', 'ecomail-woocommerce' ),
					'default'     => __( 'I don\'t like to receive newsletters', 'ecomail-woocommerce' ),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
				),
				array(
					'id'          => 'woocommerce_checkout_update',
					'type'        => 'toggle',
					'title'       => __( 'Update subscriber data in Ecomail', 'ecomail-woocommerce' ),
					'description' => __( 'Check if you want to update existing contacts in Ecomail with the details entered on checkout', 'ecomail-woocommerce' ),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
				),
				array(
					'id'          => 'woocommerce_checkout_resubscribe',
					'type'        => 'toggle',
					'title'       => __( 'Resubscribe subscriber with new order', 'ecomail-woocommerce' ),
					'description' => __( 'If a contact unsubscribe and places a new order - the option to subscribe them back.', 'ecomail-woocommerce' ),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
				),
				array(
					'id'          => 'woocommerce_checkout_list_id',
					'type'        => 'select',
					'title'       => __( 'List for checkout subscriptions', 'ecomail-woocommerce' ),
					'description' => sprintf(
						/* Translators: %s URL */
						__( 'Select the list that you want to subscribe the customers on checkout. Click <a href="%s">here</a> to refresh the lists', 'ecomail-woocommerce' ),
						add_query_arg( array( 'action' => 'ecomail_refresh_lists' ), admin_url() )
					),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
					'options'     => $this->get_lists_select(),
				),
				array(
					'id'          => 'woocommerce_checkout_skip_confirmation',
					'type'        => 'toggle',
					'title'       => __( 'Skip confirmation', 'ecomail-woocommerce' ),
					'description' => __( 'Check to skip double opt-in', 'ecomail-woocommerce' ),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
				),
				array(
					'id'          => 'woocommerce_checkout_trigger_autoresponders',
					'type'        => 'toggle',
					'title'       => __( 'Trigger autoresponders', 'ecomail-woocommerce' ),
					'description' => __( 'Check to trigger Ecomail autoresponders when the user is added to the list', 'ecomail-woocommerce' ),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
				),
				array(
					'id'          => 'woocommerce_checkout_subscribe_fields',
					'type'        => 'multi_select',
					'title'       => __( 'Fields to register on checkout', 'ecomail-woocommerce' ),
					'description' => __( 'Select fields that you want to send to Ecomail on checkout subscription', 'ecomail-woocommerce' ),
					'conditions'  => array(
						array( 'field' => 'woocommerce_checkout_subscribe', 'value' => true ),
					),
					'multi'       => true,
					'options'     => array(
						array(
							'label' => __( 'First name', 'ecomail-woocommerce' ),
							'value' => 'first_name',
						),
						array(
							'label' => __( 'Last name', 'ecomail-woocommerce' ),
							'value' => 'last_name',
						),
						array(
							'label' => __( 'Street', 'ecomail-woocommerce' ),
							'value' => 'street',
						),
						array(
							'label' => __( 'City', 'ecomail-woocommerce' ),
							'value' => 'city',
						),
						array(
							'label' => __( 'Postcode', 'ecomail-woocommerce' ),
							'value' => 'postcode',
						),
						array(
							'label' => __( 'Country', 'ecomail-woocommerce' ),
							'value' => 'country',
						),
						array(
							'label' => __( 'Company', 'ecomail-woocommerce' ),
							'value' => 'company',
						),
						array(
							'label' => __( 'Phone', 'ecomail-woocommerce' ),
							'value' => 'phone',
						),
					),
				),
				array(
					'id'          => 'api_source',
					'type'        => 'text',
					'title'       => __( 'API Source', 'ecomail-woocommerce' ),
					'description' => __( 'Enter the contact source that you want to add to Ecomail.', 'ecomail-woocommerce' ),
				),
				array(
					'id'          => 'woocommerce_order_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable order tracking', 'ecomail-woocommerce' ),
					'description' => __( 'Check if you want to send order data to Ecomail. Only for Marketer+ plan.', 'ecomail-woocommerce' ),
				),
				array(
					'id'          => 'woocommerce_cart_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable cart tracking', 'ecomail-woocommerce' ),
					'description' => __( 'Check if you want to send customer carts to Ecomail. This data can be used for abandoned cart automation in Ecomail. Only for Marketer+ plan.', 'ecomail-woocommerce' ),
				),
				array(
					'id'          => 'woocommerce_last_product_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable Last view (product) tracking', 'ecomail-woocommerce' ),
					'description' => __( 'Check if you want to send Last viewed product to Ecomail. This data can be used for automation in Ecomail (ECM_LAST_VIEW merge tag). Only for Marketer+ plan.', 'ecomail-woocommerce' ),
				),
				array(
					'id'          => 'bulk_upload_existing_customers',
					'type'        => 'button',
					'url'         => add_query_arg( array( 'action' => 'ecomail_bulk_upload_users' ), admin_url() ),
					'title'       => __( 'Bulk upload existing customers', 'ecomail-woocommerce' ),
					'description' => __(
						'<strong>The settings above will be used (List ID, fields), please make sure to save the settings first before clicking on the Bulk upload button.</strong> The users will be uploaded in background, in batches of 500.',
						'ecomail-woocommerce'
					),
				),
				array(
					'id'          => 'bulk_upload_existing_customers_and_orders',
					'type'        => 'button',
					'url'         => add_query_arg( array( 'action' => 'ecomail_bulk_upload_users_and_orders' ), admin_url() ),
					'title'       => __( 'Bulk upload existing customers and their orders', 'ecomail-woocommerce' ),
					'description' => __(
						'<strong>The settings above will be used (List ID, fields), please make sure to save the settings first before clicking on the Bulk upload button.</strong> The users and orders will be uploaded in background, in batches of 500.',
						'ecomail-woocommerce'
					),
				),
				array(
					'type'  => 'title',
					'label' => __( 'Marketing cookie', 'ecomail-woocommerce' ),
					'desc'  => __( 'You need consent from the visitor for marketing cookies. If you don`t enter the name and value of the marketing cookie the data will be sent as if consent had been given.', 'ecomail-woocommerce' ),
				),
				array(
					'id'    => 'cookie_name',
					'type'  => 'text',
					'label' => __( 'Marketing cookie name', 'ecomail-woocommerce' ),
					'desc'  => __( 'Enter the name of the cookie that represents the agreed marketing cookies. For example, in the case of using the "Complianz" plugin, this is <code>cmplz_marketing</code>.', 'ecomail-woocommerce' ),
				),
				array(
					'id'    => 'cookie_value',
					'type'  => 'text',
					'label' => __( 'Marketing cookie value', 'ecomail-woocommerce' ),
					'desc'  => __( 'Enter the value of the cookie that represents the agreed marketing cookies. For example, in the case of using the "Complianz" plugin, this is <code>allow</code>.', 'ecomail-woocommerce' ),
				),

			);
		}

		return array(
			'parent_slug' => 'options-general.php',
			'page_title'  => __( 'Ecomail Settings', 'ecomail-woocommerce' ),
			'menu_title'  => __( 'Ecomail', 'ecomail-woocommerce' ),
			'menu_slug'   => self::KEY,
			'capability'  => 'manage_options',
			'option_name' => self::KEY,
			'items'       => array_merge(
				array(
					array(
						'id'          => 'api_key',
						'type'        => 'text',
						'title'       => __( 'API key', 'ecomail-woocommerce' ),
						'description' => __( 'Enter API key', 'ecomail-woocommerce' ),
					),
					array(
						'id'          => 'app_id',
						'type'        => 'text',
						'title'       => __( 'App ID', 'ecomail-woocommerce' ),
						'description' => __( 'Enter App ID - this is first part of your Ecomail account URL.', 'ecomail-woocommerce' ),
					),
				),
				$additional,
			),
		);
	}

	public function get_lists_select() {
		$lists = $this->ecomail->get_lists();
		if ( ! is_array( $lists ) ) {
			return array();
		}

		return array_map(
			function ( $item ) {
				return array(
					'label' => $item['name'],
					'value' => $item['id'],
				);
			},
			$lists
		);
	}
}
