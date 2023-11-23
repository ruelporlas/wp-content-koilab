<?php
/**
 * Cart Discounts
 *
 * @package EDD Recurring
 * @since   2.11.10
 */
namespace EDD\Recurring\Cart;

defined( 'ABSPATH' ) || exit;

class Discounts {

	/**
	 * Discounts constructor.
	 */
	public function __construct() {
		add_filter( 'edd_ajax_discount_response', array( $this, 'update_discount_response' ) );
		add_filter( 'edd_ajax_remove_discount_response', array( $this, 'update_discount_response' ) );
	}

	/**
	 * Filters the EDD discount response when a discount is added to or removed from the cart.
	 *
	 * @since 2.11.9
	 * @param array $data
	 * @return array
	 */
	public function update_discount_response( $data ) {
		if ( ! edd_recurring()->cart_contains_recurring() ) {
			return $data;
		}
		$cart_details = EDD()->cart->get_contents_details();
		$subscription = new Subscription( $cart_details );
		$to_update    = array();
		$total        = 0;
		foreach ( $cart_details as $key => $item ) {
			$price_id = isset( $item['item_number']['options']['price_id'] ) ? $item['item_number']['options']['price_id'] : null;
			if ( ! edd_recurring()->is_recurring( $item['id'] ) && ! edd_recurring()->is_price_recurring( $item['id'], $price_id ) ) {
				continue;
			}
			$recurring_amounts                                      = $subscription->get_recurring_amount( $item );
			$to_update[ "edd-recurring-sl-discount-amount-{$key}" ] = html_entity_decode( edd_currency_filter( edd_sanitize_amount( $recurring_amounts['amount'] ) ), ENT_COMPAT, 'UTF-8' );
			$total += $recurring_amounts['amount'];
		}

		if ( ! empty( $to_update ) ) {
			$data['recurring_sl'] = $to_update;
		}

		$data['recurring_total'] = html_entity_decode( edd_currency_filter( edd_sanitize_amount( $total ) ), ENT_COMPAT, 'UTF-8' );

		return $data;
	}
}
