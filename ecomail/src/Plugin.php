<?php

namespace Ecomail;

use Ecomail\Managers\ApiManager;
use Ecomail\Managers\PostTypesManager;
use Ecomail\Managers\RepositoriesManager;

final class Plugin {
	public function __construct(
		ApiManager $api_manager,
		PostTypesManager $post_types_manager,
		RepositoriesManager $repositories_manager,
		Frontend $frontend,
		Settings $settings,
		Admin $admin,
		BlockSupport $block_support
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
