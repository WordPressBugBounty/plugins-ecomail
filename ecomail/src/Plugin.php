<?php

namespace Ecomail;

use Ecomail\Managers\ApiManager;
use Ecomail\Managers\PostTypesManager;

final class Plugin {
	public function __construct(
		ApiManager $api_manager,
		PostTypesManager $post_types_manager,
		Frontend $frontend,
		Settings $settings,
		Admin $admin
	) {
	}

	/**
	 * @param bool $network_wide
	 */
	public function activate( bool $network_wide ) {
	}

	/**
	 * @param bool $network_wide
	 */
	public function deactivate( bool $network_wide ) {
	}

	/**
	 *
	 */
	public function uninstall() {
	}
}
