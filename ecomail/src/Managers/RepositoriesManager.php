<?php

namespace Ecomail\Managers;

use Ecomail\Plugin;
use Ecomail\Repositories\WooOrderRepository;

/**
 * Class RepositoriesManager
 *
 * @package Wpify\Managers
 * @property Plugin $plugin
 */
class RepositoriesManager {
	public function __construct() {
	}

	protected $modules = array(
		WooOrderRepository::class,
	);
}
