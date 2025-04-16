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

	/**
	 * Find orders by customer ID.
	 *
	 * @param $customer_id
	 *
	 * @return array
	 * @throws \EcomailDeps\Wpify\Model\Exceptions\RepositoryNotInitialized
	 */
	public function find_by_customer( $customer_id ): array {
		$args = array(
			'customer_id' => $customer_id,
		);

		return $this->find( $args );
	}
}
