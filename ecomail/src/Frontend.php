<?php

namespace Ecomail;

use Ecomail\Managers\ApiManager;
use Ecomail\Repositories\SettingsRepository;
use EcomailDeps\Wpify\Asset\AssetFactory;
use EcomailDeps\Wpify\PluginUtils\PluginUtils;

class Frontend {
	/** @var ApiManager */
	private $api_manager;

	/** @var PluginUtils */
	private $utils;

	/** @var AssetFactory */
	private $asset_factory;

	/** @var Ecomail */
	public $ecomail;

	/** @var SettingsRepository */
	public $settings;

	public function __construct(
		ApiManager $api_manager,
		PluginUtils $utils,
		AssetFactory $asset_factory,
		Ecomail $ecomail,
		SettingsRepository $settings
	) {
		$this->api_manager   = $api_manager;
		$this->utils         = $utils;
		$this->asset_factory = $asset_factory;
		$this->ecomail       = $ecomail;
		$this->settings      = $settings;

		$this->setup();
		add_action( 'wp_enqueue_scripts', array( $this, 'setup_assets' ) );
	}

	public function setup() {
	}

	public function setup_assets() {
		if ( $this->ecomail->is_disabled_by_cookie() ) {
			return;
		}

		$args = array(
			'restUrl'                    => site_url( 'wp-json/' . ApiManager::REST_NAMESPACE ),
			'cartTrackingEnabled'        => boolval( $this->settings->get_option( 'woocommerce_cart_tracking' ) ),
			'lastProductTrackingEnabled' => boolval( $this->settings->get_option( 'woocommerce_last_product_tracking' ) ),
			'emailExists'                => boolval( $this->ecomail->get_customer_email() ),
			'productId'                  => null,
		);
		if ( is_product() ) {
			$args['productId'] = get_the_ID();
		}
		$this->asset_factory->wp_script(
			$this->utils->get_plugin_path( 'build/plugin.js' ),
			array(
				'in_footer' => true,
				'handle'    => 'ecomail',
				'variables' => array(
					'ecomailArgs' => $args,
				),
			)
		);
	}

}
