<?php
/**
 * Class for evaluating if a user can download a file.
 */
namespace EDD\Recurring\Subscribers;

defined( 'ABSPATH' ) || exit;

class DownloadChecker {

	/**
	 * The subscriber to check.
	 *
	 * @var \EDD_Recurring_Subscriber
	 * @since 2.11.10
	 */
	private $customer;

	/**
	 * DownloadChecker constructor.
	 *
	 * @since 2.11.10
	 * @param \EDD_Recurring_Subscriber $customer
	 */
	public function __construct( $customer ) {
		$this->customer = $customer;
	}

	/**
	 * Checks if the current user can download a file.
	 *
	 * @since 2.11.10
	 * @param string   $download_id
	 * @param string   $payment_id
	 * @param null|int $price_id
	 * @return bool
	 */
	public function user_can_download( $download_id, $payment_id, $price_id ) {
		// No customer found so access is denied.
		if ( ! $this->customer->id > 0 ) {
			return false;
		}

		// An active subscription is always allowed.
		if ( $this->customer->has_active_product_subscription( $download_id, $price_id ) ) {
			return true;
		}

		// Check if the purchase included a bundle.
		$order_items = edd_get_order_items(
			array(
				'order_id' => $payment_id,
			)
		);

		foreach ( $order_items as $order_item ) {

			if ( ! edd_is_bundled_product( $order_item->product_id ) ) {
				// If the product is not a bundle and it's not recurring then allow the download.
				if ( $download_id == $order_item->product_id ) { //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					if ( ! $this->is_recurring( $order_item->product_id, $price_id ) ) {
						return true;
					}
				}
				continue;
			}

			// If the product is a bundle and it's not recurring then allow the download.
			if ( ! $this->is_recurring( $order_item->product_id, $order_item->price_id ) ) {
				return true;
			}

			if ( ! $this->is_product_in_bundle( $order_item, $download_id, $price_id ) ) {
				continue;
			}

			if ( $this->customer->has_active_product_subscription( $order_item->product_id, $order_item->price_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if a product is recurring.
	 *
	 * @since 2.11.10
	 * @param string   $download_id
	 * @param null|int $price_id
	 * @return bool
	 */
	private function is_recurring( $download_id, $price_id ) {
		if ( edd_recurring()::is_recurring( $download_id ) ) {
			return true;
		}
		if ( edd_recurring()::is_price_recurring( $download_id, $price_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if a product is in a bundle.
	 *
	 * @since 2.11.10
	 * @param \EDD_Order_Item $order_item
	 * @param string          $download_id
	 * @param null|int        $price_id
	 * @return bool
	 */
	private function is_product_in_bundle( $order_item, $download_id, $price_id ) {
		$bundled = edd_get_bundled_products( $order_item->product_id );
		if ( null === $price_id && ! in_array( $download_id, $bundled ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			return false;
		}

		if ( null !== $price_id && ! in_array( "{$download_id}_{$price_id}", $bundled, true ) ) {
			return false;
		}

		return true;
	}
}
