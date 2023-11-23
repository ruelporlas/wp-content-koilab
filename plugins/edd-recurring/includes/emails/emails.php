<?php
/**
 * Recurring Email Functions
 *
 * @package    edd-recurring
 * @subpackage Emails
 * @copyright  Copyright (c) 2022, Easy Digital Downloads
 * @license    GPL2+
 * @since      2.12
 */

namespace EDD\Recurring\Emails;

/**
 * Registers new email tags for recurring products.
 *
 * @since 2.12
 * @return void
 */
function register_email_tags() {

	$new_tags = array(
		'subscription_details' => array(
			'description' => __( 'Include the details for purchased subscription(s).', 'edd-recurring' ),
			'callback'    => __NAMESPACE__ . '\subscription_details',
			'label'       => __( 'Subscription Details', 'edd-recurring' ),
		),
	);

	foreach ( $new_tags as $tag => $args ) {
		edd_add_email_tag(
			$tag,
			$args['description'],
			$args['callback'],
			$args['label']
		);
	}
}
add_action( 'edd_add_email_tags', __NAMESPACE__ . '\register_email_tags', 100 );

/**
 * Gets the recurring details for the purchase receipt email.
 *
 * @since 2.12
 * @param int $payment_id The order ID.
 * @return string
 */
function subscription_details( $payment_id ) {
	$cart_items = edd_get_payment_meta_cart_details( $payment_id );
	$output     = '';

	// Loop through purchases see which are recurring
	foreach ( $cart_items as $item ) {

		// If the item isn't recurring, skip it.
		if ( empty( $item['item_number']['options']['recurring'] ) ) {
			continue;
		}

		$output .= sprintf(
			'<li>%s: %s<br /><i>%s</i></li>',
			get_download_name( $item ),
			edd_currency_filter( edd_format_amount( $item['item_price'] ) ),
			edd_recurring_get_subscription_billing_text( get_recurring_args( $item ) )
		);
	}

	if ( empty( $output ) ) {
		return '';
	}

	return '<ul>' . $output . '</ul>';
}

/**
 * Gets the download name for the email.
 *
 * @since 2.12
 * @param array $item The cart item.
 * @return string
 */
function get_download_name( $item ) {
	$price_id = false;
	if ( isset( $item['item_number']['options']['price_id'] ) && edd_has_variable_prices( $item['id'] ) ) {
		$price_id = $item['item_number']['options']['price_id'];
	}
	if ( function_exists( 'edd_get_download_name' ) ) {
		return edd_get_download_name( $item['id'], $price_id );
	}

	$download_name = $item['name'];
	// If the product is variable, add the price name to the email.
	if ( $price_id ) {
		$download_name .= ' &emdash; ' . edd_get_price_name( $item['id'], array( 'price_id' => $price_id ) );
	}

	return $download_name;
}

/**
 * Parses the individual item recurring parameters with defaults.
 *
 * @since 2.12
 * @param array $item The cart item.
 * @return array
 */
function get_recurring_args( $item ) {
	$recurring = array(
		'period'       => false,
		'times'        => false,
		'trial_period' => false,
		'trial_unit'   => false,
	);
	$item_recurring = maybe_unserialize( $item['item_number']['options']['recurring'] );

	return wp_parse_args( $item_recurring, $recurring );
}
