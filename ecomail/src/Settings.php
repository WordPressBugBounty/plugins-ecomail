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
		$settings   = get_option( 'ecomail' );
		$additional = array();

		if ( is_array( $settings ) && ! empty( $settings['api_key'] ) && ! empty( $settings['app_id'] ) ) {
			$additional = array(
				array(
					'id'          => 'enable_tracking_code',
					'type'        => 'toggle',
					'title'       => __( 'Add tracking code to website', 'ecomail' ),
					'description' => __( 'Check to add tracking code to the website', 'ecomail' ),
				),
				array(
					'id'          => 'enable_manual_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable manual tracking', 'ecomail' ),
					'description' => __( 'Check if you want to identify the user by WP login details. The priorities are - Ecomail email, Customer email, WP User email', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_checkout_subscribe',
					'type'        => 'toggle',
					'title'       => __( 'Subscribe on checkout', 'ecomail' ),
					'description' => __( 'Check to enable Ecomail subscriptions on checkout', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_checkout_subscribe_checkbox',
					'type'        => 'toggle',
					'title'       => __( 'Show checkbox on checkout', 'ecomail' ),
					'description' => __( 'Check to display "I\'d like to receive newsletters" checkbox on checkout', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_checkout_subscribe_text',
					'type'        => 'text',
					'title'       => __( 'Text for Subscribe on checkout checkbox', 'ecomail' ),
					'description' => __( 'Enter the text that will appear on checkout subscription', 'ecomail' ),
					'placeholder' => __( 'I\'d like to receive newsletters', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_checkout_update',
					'type'        => 'toggle',
					'title'       => __( 'Update subscriber data in Ecomail', 'ecomail' ),
					'description' => __( 'Check if you want to update existing contacts in Ecomail with the details entered on checkout', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_checkout_list_id',
					'type'        => 'select',
					'title'       => __( 'List for checkout subscriptions', 'ecomail' ),
					'description' => sprintf(
						__( 'Select the list that you want subscribe the customers on checkout. Click <a href="%s">here</a> to refresh the lists', 'ecomail' ),
						add_query_arg( array( 'action' => 'ecomail_refresh_lists' ), admin_url() )
					),
					'options'     => $this->get_lists_select(),
				),
				array(
					'id'          => 'woocommerce_checkout_skip_confirmation',
					'type'        => 'toggle',
					'title'       => __( 'Skip confirmation', 'ecomail' ),
					'description' => __( 'Check to skip double opt-in', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_checkout_trigger_autoresponders',
					'type'        => 'toggle',
					'title'       => __( 'Trigger autoresponders', 'ecomail' ),
					'description' => __( 'Check to trigger Ecomail autoresponders when the user is added to the list', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_checkout_subscribe_fields',
					'type'        => 'multi_select',
					'title'       => __( 'Fields to register on checkout', 'ecomail' ),
					'description' => __( 'Select fields that you want to send to Ecomail on checkout subscription', 'ecomail' ),
					'multi'       => true,
					'options'     => array(
						array(
							'label' => __( 'First name', 'ecomail' ),
							'value' => 'first_name',
						),
						array(
							'label' => __( 'Last name', 'ecomail' ),
							'value' => 'last_name',
						),
						array(
							'label' => __( 'Street', 'ecomail' ),
							'value' => 'street',
						),
						array(
							'label' => __( 'City', 'ecomail' ),
							'value' => 'city',
						),
						array(
							'label' => __( 'Postcode', 'ecomail' ),
							'value' => 'postcode',
						),
						array(
							'label' => __( 'Country', 'ecomail' ),
							'value' => 'country',
						),
						array(
							'label' => __( 'Company', 'ecomail' ),
							'value' => 'company',
						),
						array(
							'label' => __( 'Phone', 'ecomail' ),
							'value' => 'phone',
						),
					),
				),
				array(
					'id'          => 'api_source',
					'type'        => 'text',
					'title'       => __( 'API Source', 'ecomail' ),
					'description' => __( 'Enter the contact source that you want to add to Ecomail.', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_order_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable order tracking', 'ecomail' ),
					'description' => __( 'Check if you want to send order data to Ecomail. Only for Marketer+ plan.', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_cart_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable cart tracking', 'ecomail' ),
					'description' => __( 'Check if you want to send customer carts to Ecomail. This data can be used for abandoned cart automation in Ecomail. Only for Marketer+ plan.', 'ecomail' ),
				),
				array(
					'id'          => 'woocommerce_last_product_tracking',
					'type'        => 'toggle',
					'title'       => __( 'Enable Last view (product) tracking', 'ecomail' ),
					'description' => __( 'Check if you want to send Last viewed product to Ecomail. This data can be used for automation in Ecomail (ECM_LAST_VIEW merge tag). Only for Marketer+ plan.', 'ecomail' ),
				),
				array(
					'id'          => 'bulk_upload_existing_customers',
					'type'        => 'button',
					'url'         => add_query_arg( array( 'action' => 'ecomail_bulk_upload_users' ), admin_url() ),
					'title'       => __( 'Bulk upload existing customers', 'ecomail' ),
					'description' => __(
						'<strong>The settings above will be used (List ID, fields), please make sure to save the settings first before clicking on the Bulk upload button.</strong> The users will be uploaded in background, in batches of 500.',
						'ecomail'
					),
				),
				array(
					'type'  => 'title',
					'label' => __( 'Marketing cookie', 'ecomail' ),
					'desc'  => __( 'You need consent from the visitor for marketing cookies. If you don`t enter the name and value of the marketing cookie the data will be sent as if consent had been given.', 'ecomail' ),
				),
				array(
					'id'    => 'cookie_name',
					'type'  => 'text',
					'label' => __( 'Marketing cookie name', 'ecomail' ),
					'desc'  => __( 'Enter the name of the cookie that represents the agreed marketing cookies. For example, in the case of using the "Complianz" plugin, this is <code>cmplz_marketing</code>.', 'ecomail' ),
				),
				array(
					'id'    => 'cookie_value',
					'type'  => 'text',
					'label' => __( 'Marketing cookie value', 'ecomail' ),
					'desc'  => __( 'Enter the value of the cookie that represents the agreed marketing cookies. For example, in the case of using the "Complianz" plugin, this is <code>allow</code>.', 'ecomail' ),
				),

			);
		}

		return array(
			'parent_slug' => 'options-general.php',
			'page_title'  => __( 'Ecomail Settings', 'ecomail' ),
			'menu_title'  => __( 'Ecomail', 'ecomail' ),
			'menu_slug'   => self::KEY,
			'capability'  => 'manage_options',
			'items'       => array(
				array(
					'type'  => 'group',
					'id'    => 'ecomail',
					'title' => __( 'Ecomail settings', 'ecomail' ),
					'items' => array_merge(
						array(
							array(
								'id'          => 'api_key',
								'type'        => 'text',
								'title'       => __( 'API key', 'ecomail' ),
								'description' => __( 'Enter API key', 'ecomail' ),
							),
							array(
								'id'          => 'app_id',
								'type'        => 'text',
								'title'       => __( 'App ID', 'ecomail' ),
								'description' => __( 'Enter App ID - this is first part of your Ecomail account URL.', 'ecomail' ),
							),
						),
						$additional,
					),
				),
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
