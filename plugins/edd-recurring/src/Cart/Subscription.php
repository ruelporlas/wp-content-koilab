<?php
/**
 * Class for evaluating building a single subscription in a cart.
 *
 * @since 2.11.9
 * @package EDD_Recurring
 */

namespace EDD\Recurring\Cart;

defined( 'ABSPATH' ) || exit;

class Subscription {

	/**
	 * The cart details.
	 *
	 * @var array
	 */
	private $cart_details;

	/**
	 * The cart discounts.
	 *
	 * @var array
	 */
	private $cart_discounts;

	public function __construct( $cart_details = array() ) {
		$this->cart_details   = ! empty( $cart_details ) ? $cart_details : edd_get_cart_content_details();
		$this->cart_discounts = edd_get_cart_discounts();
	}

	/**
	 * Gets the subscription details.
	 *
	 * @since 2.11.9
	 * @param array $item
	 * @param int   $key
	 * @return array|false
	 */
	public function get( $item, $key ) {
		if ( ! isset( $item['item_number']['options'] ) || ! isset( $item['item_number']['options']['recurring'] ) ) {
			return false;
		}

		$recurring_amounts = $this->get_recurring_amount( $item, $key );

		$fees = $this->get_fees( $item );

		/**
		 * Determine tax amount for any fees if it's more than $0
		 *
		 * Fees (at this time) must be exclusive of tax
		 * @see EDD_Cart::get_tax_on_fees()
		 */
		add_filter( 'edd_prices_include_tax', '__return_false' );
		$fee_tax = $fees > 0 ? edd_calculate_tax( $fees ) : 0;
		remove_filter( 'edd_prices_include_tax', '__return_false' );

		// Get the tax rate.
		$tax_rate = $this->get_tax_rate();

		$args = array(
			'cart_index'         => $key,
			'id'                 => $item['id'],
			'name'               => $item['name'],
			'price_id'           => isset( $item['item_number']['options']['price_id'] ) ? (int) $item['item_number']['options']['price_id'] : null,
			'initial_amount'     => (float) edd_sanitize_amount( $item['price'] + $fees + $fee_tax ),
			'recurring_amount'   => (float) edd_sanitize_amount( $recurring_amounts['amount'] ),
			'initial_tax'        => edd_use_taxes() ? (float) edd_sanitize_amount( $item['tax'] + $fee_tax ) : 0,
			'initial_tax_rate'   => $tax_rate,
			'recurring_tax'      => (float) edd_sanitize_amount( $recurring_amounts['tax'] ),
			'recurring_tax_rate' => $tax_rate,
			'signup_fee'         => (float) edd_sanitize_amount( $fees ),
			'period'             => $item['item_number']['options']['recurring']['period'],
			'frequency'          => 1, // Hard-coded to 1 for now but here in case we offer it later. Example: charge every 3 weeks
			'bill_times'         => $item['item_number']['options']['recurring']['times'],
			'profile_id'         => '', // Profile ID for this subscription - This is set by the payment gateway
			'transaction_id'     => '',
		);

		/**
		 * Filter the subscription arguments.
		 *
		 * @param array $args
		 * @param array $item
		 */
		return apply_filters( 'edd_recurring_subscription_pre_gateway_args', $args, $item );
	}

	/**
	 * Gets the recurring amounts for the subscription.
	 *
	 * @param array $item The cart item.
	 * @param int   $key  The cart item index (if known).
	 * @return array An array containing the recurring amount (total charged) and tax for renewal orders.
	 */
	public function get_recurring_amount( $item, $key = false ) {

		/**
		 * Allows the recurring amounts to be filtered.
		 *
		 * @param array $recurring_amounts The calculated recurring amounts.
		 * @param array $item              The cart item.
		 * @param int   $key               The cart item index.
		 * @param array $cart_details      The cart details.
		 * @param array $cart_discounts    The cart discounts.
		 */
		return apply_filters( 'edd_recurring_subscription_recurring_amounts', $this->calculate_recurring_amounts( $item ), $item, $key, $this->cart_details, $this->cart_discounts );
	}

	/**
	 * Calculates the recurring amounts for an item.
	 *
	 * @param array $item The cart item.
	 * @return array
	 */
	public function calculate_recurring_amounts( $item ) {
		$prices_include_tax = edd_use_taxes() && edd_prices_include_tax();
		$item_price         = $prices_include_tax ? $item['item_price'] : $item['subtotal'];
		$item_tax           = edd_calculate_tax( $item_price );

		// Define the default recurring amounts, which match the initial amount/tax.
		$recurring_amounts = array(
			'amount' => $prices_include_tax ? $item_price : $item_price + $item_tax,
			'tax'    => $item_tax,
		);

		// Return defaults if there are no discounts.
		if ( empty( $this->cart_discounts ) ) {
			return $recurring_amounts;
		}

		// Collect all discount codes that apply to renewals.
		$renewal_discounts = array();
		foreach ( $this->cart_discounts as $code ) {
			if ( $this->apply_discount_to_first_order_only( $code ) ) {
				continue;
			}
			$renewal_discounts[] = $code;
		}

		// Return the default price/tax if no renewal discounts were found.
		if ( empty( $renewal_discounts ) ) {
			return $recurring_amounts;
		}

		// Apply the discount amount to the item price.
		$item_price = $item_price - $this->get_renewal_discount_amount( $item, $this->cart_details, $renewal_discounts, $item_price );
		$item_tax   = 0;
		if ( edd_use_taxes() && ! edd_download_is_tax_exclusive( $item['id'] ) ) {
			$item_tax = edd_calculate_tax( $item_price );
		}

		return array(
			'amount' => $prices_include_tax ? $item_price : $item_price + $item_tax,
			'tax'    => $item_tax,
		);
	}

	/**
	 * Whether cart discounts should apply to renewal orders.
	 *
	 * @since 2.11.8
	 * @param string $code
	 * @return bool
	 */
	private function apply_discount_to_first_order_only( $code ) {
		$discount_id = edd_get_discount_id_by_code( $code );
		$meta        = edd_recurring_get_discount_renewal_meta( $discount_id );

		// Whether The discount should be applied to the first order only.
		if ( 'renewals' === $meta ) {
			return false;
		} elseif ( 'first' === $meta ) {
			return true;
		}

		return (bool) edd_get_option( 'recurring_one_time_discounts' );
	}

	/**
	 * Retrieves a discount amount for an item.
	 *
	 * Calculates an amount based on the context of other items.
	 * This function is nearly identical to `edd_get_item_discount_amount`.
	 *
	 * @since 2.11.8
	 *
	 * @global float $edd_flat_discount_total Track flat rate discount total for penny adjustments.
	 * @link https://github.com/easydigitaldownloads/easy-digital-downloads/issues/2757
	 *
	 * @param array                    $item {
	 *   Order Item data, matching Cart line item format.
	 *
	 *   @type string $id       Download ID.
	 *   @type array  $options {
	 *     Download options.
	 *
	 *     @type string $price_id Download Price ID.
	 *   }
	 *   @type int    $quantity Purchase quantity.
	 * }
	 * @param array                    $items     All items (including item being calculated).
	 * @param \EDD_Discount[]|string[] $discounts Discount to determine adjustment from.
	 *                                            A discount code can be passed as a string.
	 * @param int                      $item_unit_price (Optional) Pass in a defined price for a specific context, such as the cart.
	 * @return float Discount amount. 0 if Discount is invalid or no Discount is applied.
	 */
	private function get_renewal_discount_amount( $item, $items, $discounts, $item_price ) {
		global $edd_flat_discount_total;

		// Validate item.
		if ( empty( $item ) || empty( $item['id'] ) ) {
			return 0;
		}

		if ( ! isset( $item['quantity'] ) ) {
			return 0;
		}

		// Validate and normalize Discounts.
		$discounts = array_map(
			function( $discount ) {
				// Convert a Discount code to a Discount object.
				if ( is_string( $discount ) ) {
					$discount = edd_get_discount_by_code( $discount );
				}

				if ( ! $discount instanceof \EDD_Discount ) {
					return false;
				}

				return $discount;
			},
			$discounts
		);

		$discounts = array_filter( $discounts );
		if ( empty( $discounts ) ) {
			return 0;
		}

		$item_amount     = $item_price;
		$discount_amount = 0;

		foreach ( $discounts as $discount ) {
			$reqs                = $discount->get_product_reqs();
			$excluded_products   = $discount->get_excluded_products();
			$discount_not_global = is_callable( array( $discount, 'get_scope' ) ) ? 'global' !== $discount->get_scope() : $discount->is_not_global;

			// Make sure requirements are set and that this discount shouldn't apply to the whole cart.
			if ( ! empty( $reqs ) && $discount_not_global ) {
				// This is a product(s) specific discount.
				foreach ( $reqs as $download_id ) {
					if ( $download_id == $item['id'] && ! in_array( $item['id'], $excluded_products ) ) {
						$discount_amount += ( $item_amount - $discount->get_discounted_amount( $item_amount ) );
					}
				}
			} else {
				// This is a global cart discount.
				if ( ! in_array( $item['id'], $excluded_products ) ) {
					if ( 'flat' === $discount->get_type() ) {
						// In order to correctly record individual item amounts, global flat rate discounts
						// are distributed across all items.
						//
						// The discount amount is divided by the number of items in the cart and then a
						// portion is evenly applied to each item.
						$items_amount = 0;

						foreach ( $items as $i ) {
							if ( ! in_array( $i['id'], $excluded_products ) ) {
								if ( edd_has_variable_prices( $i['id'] ) ) {
									$i_amount = edd_get_price_option_amount( $i['id'], $i['options']['price_id'] );
								} else {
									$i_amount = edd_get_download_price( $i['id'] );
								}

								$items_amount += ( $i_amount * $i['quantity'] );
							}
						}

						$subtotal_percent = ! empty( $items_amount ) ? ( $item_amount / $items_amount ) : 0;
						$discount_amount += ( $discount->get_amount() * $subtotal_percent );

						$edd_flat_discount_total += round( $discount_amount, edd_currency_decimal_filter() );

						if ( $item['id'] === end( $items )['id'] && $edd_flat_discount_total < $discount->get_amount() ) {
							$adjustment       = ( $discount->get_amount() - $edd_flat_discount_total );
							$discount_amount += $adjustment;
						}

						if ( $discount_amount > $item_amount ) {
							$discount_amount = $item_amount;
						}
					} else {
						$discount_amount += ( $item_amount - $discount->get_discounted_amount( $item_amount ) );
					}
				}
			}
		}

		return $discount_amount;
	}

	/**
	 * Gets the fees for an item.
	 *
	 * @since 2.11.9
	 * @param array $item
	 * @return float|string
	 */
	private function get_fees( $item ) {
		$fees = $item['item_number']['options']['recurring']['signup_fee'];

		if ( ! empty( $item['fees'] ) ) {
			foreach ( $item['fees'] as $fee ) {

				// Negative fees are already accounted for on $item['price']
				if ( $fee['amount'] <= 0 ) {
					continue;
				}

				$fees += $fee['amount'];
			}
		}

		return $fees;
	}

	/**
	 * Gets the tax rate.
	 *
	 * @since 2.11.9
	 * @return float|string
	 */
	private function get_tax_rate() {
		if ( ! edd_use_taxes() ) {
			return 0;
		}
		// Format the tax rate.
		$tax_rate = ! empty( $this->cart_details['tax_rate'] ) ? $this->cart_details['tax_rate'] : edd_get_tax_rate();
		$tax_rate = round( floatval( $tax_rate ), 4 );
		if ( 4 > strlen( $tax_rate ) ) {
			/*
				* Enforce a minimum of 2 decimals for backwards compatibility.
				* @link https://github.com/easydigitaldownloads/edd-recurring/pull/1386#issuecomment-745350210
				*/
			$tax_rate = number_format( $tax_rate, 2, '.', '' );
		}

		return $tax_rate;
	}

	/**
	 * Whether the item has a trial.
	 *
	 * @since 2.11.10
	 * @param array $item
	 * @return bool
	 */
	private function item_has_trial( $item ) {
		return (bool) ( ! empty( $item['item_number']['options']['recurring']['trial_period']['unit'] ) && ! empty( $item['item_number']['options']['recurring']['trial_period']['quantity'] ) );
	}
}
