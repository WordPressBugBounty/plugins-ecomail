<?php

namespace Ecomail\Models;

use Ecomail\Ecomail;
use EcomailDeps\Wpify\Model\Order;
use EcomailDeps\Wpify\Model\OrderItemLine;

class WooOrderModel extends Order {

	private $ic;
	private $dic;
	public $_ecomail_subscribe;

	/**
	 * @return mixed
	 */
	public function get_ic() {
		if ( $this->ic ) {
			return $this->ic;
		}

		$wc_order = $this->get_wc_order();
		if ( ! $wc_order ) {
			return null;
		}

		$this->ic = $wc_order->get_meta( '_billing_ic' );

		return $this->ic;
	}

	/**
	 * @return mixed
	 */
	public function get_dic() {
		if ( $this->dic ) {
			return $this->dic;
		}

		$wc_order = $this->get_wc_order();
		if ( ! $wc_order ) {
			return null;
		}

		$this->dic = $wc_order->get_meta( '_billing_dic' );

		return $this->dic;
	}

	/**
	 * @return array
	 */
	public function get_subscriber_data( array $tags = array() ): array {
		$wc_order = $this->get_wc_order();
		if ( ! $wc_order ) {
			return array();
		}

		$ecomail = ecomail_container()->get( Ecomail::class );
		$data    = $ecomail->get_subscribe_data_from_object( $wc_order, array( 'tags' => $tags ) );

		return apply_filters( 'ecomail_order_subscriber_data', $data, $this, $wc_order );
	}

	public function get_transaction_data() {
		$wc_order = $this->get_wc_order();
		if ( ! $wc_order ) {
			return array();
		}

		$date_created = $wc_order->get_date_created();
		$timestamp    = $date_created ? $date_created->getTimestamp() : time();

		$data = array(
			'transaction' => array(
				'order_id'  => (string) $wc_order->get_id(),
				'email'     => $wc_order->get_billing_email(),
				'shop'      => site_url(),
				'amount'    => floatval( $wc_order->get_total() ),
				'tax'       => floatval( $wc_order->get_total_tax() ),
				'shipping'  => floatval( $wc_order->get_shipping_total() ),
				'city'      => $wc_order->get_billing_city(),
				'country'   => $wc_order->get_billing_country(),
				'timestamp' => $timestamp,
				'status'    => $this->get_ecomail_status(),
			),
		);

		foreach ( $this->line_items as $item ) {
			/** @var OrderItemLine $item */
			/** @var \WC_Product $product */
			$product = $item->product;
			if ( ! $product ) {
				continue;
			}

			$categories = [];
			$product_id = $product->get_parent_id() ?: $product->get_id();
			if ( $product_id ) {
				$terms = wp_get_post_terms( $product_id, 'product_cat' );
				foreach ( $terms as $index => $term ) {
					if ( $index >= 20 ) {
						break; // Max 20 categories per item
					}
					$categories[] = mb_substr( $term->name, 0, 100 ); // Max 100 chars per category
				}
			}

			$data['transaction_items'][] = array(
				'code'       => $product->get_sku() ?? $product->get_id(),
				'title'      => $product->get_name(),
				'categories' => $categories,
				'price'      => $item->get_unit_price_tax_included() * $item->quantity,
				'amount'     => $item->quantity,
			);
		}

		return apply_filters( 'ecomail_order_transaction_data', $data, $this, $wc_order );
	}

	/**
	 * Get ecomail status.
	 */
	public function get_ecomail_status() {
		$wc_order = $this->get_wc_order();
		if ( ! $wc_order ) {
			return null;
		}

		$status = $wc_order->get_status();

		if ( 'cancelled' === $status ) {
			return 'canceled';
		} elseif ( in_array( $status, array(
			'on-hold',
		) ) ) {
			return 'pending';
		} elseif ( in_array( $status, array(
			'processing',
			'pending',
			'completed',
		) ) ) {
			return $status;
		}

		return null;
	}
}
