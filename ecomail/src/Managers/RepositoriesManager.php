<?php

namespace Ecomail\Managers;

use Ecomail\Plugin;
use Ecomail\Repositories\WooOrderRepository;
use EcomailDeps\DI\Container;
use EcomailDeps\Wpify\Model\Manager;

/**
 * Class RepositoriesManager
 *
 * @package Wpify\Managers
 * @property Plugin $plugin
 */
class RepositoriesManager {
	public function __construct(
		Container $container,
		Manager $manager,
		WooOrderRepository $woo_order_repository,
	) {
		foreach ( $manager->get_repositories() as $repository ) {
			$container->set( $repository::class, $repository );
		}

		$custom_repositories = array(
			$woo_order_repository,
		);

		foreach ( $custom_repositories as $repository ) {
			$manager->register_repository( $repository );
		}
	}
}
