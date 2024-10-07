<?php

namespace Ecomail\Repositories;

use Ecomail\Models\WooOrderModel;
use Ecomail\Plugin;
use Ecomail\PostTypes\WooOrderPostType;
use EcomailDeps\Wpify\Model\OrderRepository;

/**
 * @property Plugin $plugin
 */
class WooOrderRepository extends OrderRepository {
	public function model(): string {
		return WooOrderModel::class;
	}

	/**
	 * @return string
	 */
	public static function post_type(): string {
		return WooOrderPostType::NAME;
	}
}
