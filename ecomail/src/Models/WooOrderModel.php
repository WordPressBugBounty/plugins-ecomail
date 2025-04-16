<?php

namespace Ecomail\Models;

use Ecomail\Ecomail;
use EcomailDeps\Wpify\Model\Order;
use EcomailDeps\Wpify\Model\OrderItemLine;

class WooOrderModel extends Order {

	private $ic;
	private $dic;

	/**
	 * @return mixed
	 */
	public function get_ic() {
		if ( $this->ic ) {
			return $this->ic;
		}
		$this->ic = $this->get_wc_order()->get_meta( '_billing_ic' );

		return $this->ic;
	}

	/**
	 * @return mixed
	 */
	public function get_dic() {
		if ( $this->dic ) {
			return $this->dic;
		}

		$this->dic = $this->get_wc_order()->get_meta( '_billing_dic' );

		return $this->dic;
	}

	/**
	 * @return array
	 */
	public function get_subscriber_data(): array {
		$wc_order = $this->get_wc_order();
		$ecomail  = ecomail_container()->get( Ecomail::class );
		$data     = $ecomail->get_subscribe_data_from_object( $wc_order, array( 'tags' => array( 'woo_order' ) ) );

		return apply_filters( 'ecomail_order_subscriber_data', $data, $this, $wc_order );
	}

	public function get_transaction_data() {
		$wc_order = $this->get_wc_order();
		$data     = array(
			'transaction' => array(
				'order_id'  => (string) $wc_order->get_id(),
				'email'     => $wc_order->get_billing_email(),
				'shop'      => site_url(),
				'amount'    => $wc_order->get_total() - $wc_order->get_total_tax(),
				'tax'       => $wc_order->get_total_tax(),
				'shipping'  => $wc_order->get_shipping_total(),
				'city'      => $wc_order->get_billing_city(),
				'country'   => $wc_order->get_billing_country(),
				'timestamp' => $wc_order->get_date_created()->getTimestamp(),
				'status'    => $this->get_ecomail_status(),
			),
		);

		foreach ( $this->line_items as $item ) {
			/** @var OrderItemLine $item */
			/** @var \WC_Product $product */
			$product  = $item->product;
			$category = '';
			foreach ( wp_get_post_terms( $product->get_parent_id() ?: $product->get_id(), 'product_cat' ) as $term ) {
				$category = $term->name;
				break;
			}

			$data['transaction_items'][] = array(
				'code'     => $product->get_sku() ?? $product->get_id(),
				'title'    => $product->get_name(),
				'category' => $category,
				'price'    => $item->get_unit_price_tax_included(),
				'amount'   => $item->quantity,
			);
		}

		return apply_filters( 'ecomail_order_transaction_data', $data, $this, $wc_order );
	}

	/**
	 * Get ecomail status.
	 */
	public function get_ecomail_status() {
		$wc_order = $this->get_wc_order();
		$status   = $wc_order->get_status();

		if ( 'cancelled' === $status ) {
			return 'canceled';
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
