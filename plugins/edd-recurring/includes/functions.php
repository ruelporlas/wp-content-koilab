<?php

/**
 * Gets the (first) subscription related to an order
 *
 * @since 2.10.4
 * @param \EDD\Orders\Order $order The order object (EDD 3.0)
 * @return array|bool              Returns an array of subscriptions, or false.
 */
function edd_recurring_get_order_subscriptions( $order ) {
	$is_sub = edd_get_order_meta( $order->id, '_edd_subscription_payment', true );
	$subs   = false;
	$args   = array(
		'status' => array( 'active', 'trialling' ),
	);

	// If this payment is the parent payment of a subscription.
	if ( $is_sub ) {

		$subs_db                   = new EDD_Subscriptions_DB();
		$args['parent_payment_id'] = $order->id;

		return $subs_db->get_subscriptions( $args );
	}

	// If this payment has a parent payment, and is possibly a renewal payment.
	if ( $order->parent ) {

		// Check if there's a sub ID attached to this payment.
		$sub_id = edd_get_order_meta( $order->id, 'subscription_id', true );
		if ( $sub_id ) {
			$subscription = new EDD_Subscription( $sub_id );
			if ( $subscription ) {
				return array( $subscription );
			}
		}

		// If no subscription was found attached to this payment, try searching subscriptions using the parent payment ID.
		$subs_db                   = new EDD_Subscriptions_DB();
		$args['parent_payment_id'] = $order->parent;

		return $subs_db->get_subscriptions( $args );
	}

	return $subs;
}

/**
 * Returns a list of all possible statuses for a subscription.
 *
 * @since 3.0
 *
 * @return array
 */
function edd_recurring_get_subscription_statuses() {
	return array(
		'pending'   => __( 'Pending', 'edd-recurring' ),
		'active'    => __( 'Active', 'edd-recurring' ),
		'cancelled' => __( 'Cancelled', 'edd-recurring' ),
		'expired'   => __( 'Expired', 'edd-recurring' ),
		'trialling' => __( 'Trialling', 'edd-recurring' ),
		'failing'   => __( 'Failing', 'edd-recurring' ),
		'completed' => __( 'Completed', 'edd-recurring' ),
	);
}

/**
 * Gets the recurring price text for notices.
 *
 * @since 2.11.8
 * @param array  $details
 * @param string $recurring_amount The recurring amount, if known. Added in 2.11.9
 * @return string
 */
function edd_recurring_get_subscription_billing_text( $details, $recurring_amount = '' ) {
	$details = wp_parse_args(
		$details,
		array(
			'period'       => false,
			'times'        => false,
			'trial_period' => false,
			'trial_unit'   => false,
		)
	);

	$amount = ! empty( $recurring_amount ) ? ' ' . edd_currency_filter( edd_format_amount( $recurring_amount, edd_currency_decimal_filter() ) ) : '';

	if ( empty( $details['times'] ) ) {
		/* translators: 1. the billing period 2. the recurring amount (if known) */
		$output = sprintf( __( 'Billed%2$s once per %1$s until cancelled', 'edd-recurring' ), edd_recurring_get_frequency_label( $details['period'] ), $amount );
		if ( $details['trial_period'] && $details['trial_unit'] ) {
			$output = sprintf(
				/* translators: 1. the billing period 2. the number of trial units 3. the trial period unit (week, month) 4. the recurring amount (if known) */
				__( 'Billed%4$s once per %1$s until cancelled, after a %2$s %3$s free trial', 'edd-recurring' ),
				edd_recurring_get_frequency_label( $details['period'] ),
				$details['trial_unit'],
				edd_recurring_get_frequency_label( $details['trial_period'] ),
				$amount
			);
		}
	} else {
		$output = sprintf(
			/* translators: 1. the billing period 2. the number of times it will be billed 3. the recurring amount (if known) */
			_n(
				'Billed%3$s once per %1$s, %2$s time',
				'Billed%3$s once per %1$s, %2$s times',
				$details['times'],
				'edd-recurring'
			),
			edd_recurring_get_frequency_label( $details['period'] ),
			$details['times'],
			$amount
		);
		if ( $details['trial_period'] && $details['trial_unit'] ) {
			$output = sprintf(
				/* translators: 1. the billing period 2. the number of times the subscription will be billed 3. the number of trial units 4. the trial period unit (week, month) 5. the recurring amount (if known)*/
				_n(
					'Billed%5$s once per %1$s, %2$s time, after a %3$s %4$s free trial',
					'Billed%5$s once per %1$s, %2$s times, after a %3$s %4$s free trial',
					$details['times'],
					'edd-recurring'
				),
				edd_recurring_get_frequency_label( $details['period'] ),
				$details['times'],
				$details['trial_unit'],
				edd_recurring_get_frequency_label( $details['trial_period'] ),
				$amount
			);
		}
	}

	return $output;
}

/**
 * Gets the frequency labels.
 *
 * @since 2.12
 * @param string $period
 * @param integer $count
 * @return string
 */
function edd_recurring_get_frequency_label( $period, $count = 1 ) {
	$frequency = '';
	// Format period details
	switch ( $period ) {
		case 'day':
			$frequency = _nx( 'day', 'days', $count, 'subscription term', 'edd-recurring' );
			break;
		case 'week':
			$frequency = _nx( 'week', 'weeks', $count, 'subscription term', 'edd-recurring' );
			break;
		case 'month':
			$frequency = _nx( 'month', 'months', $count, 'subscription term', 'edd-recurring' );
			break;
		case 'quarter':
			$frequency = _x( 'quarter', 'subscription term', 'edd-recurring' );
			break;
		case 'semi-year':
			$frequency = _x( 'six months', 'subscription term', 'edd-recurring' );
			break;
		case 'year':
			$frequency = _nx( 'year', 'years', $count, 'subscription term', 'edd-recurring' );
			break;
		default:
			$frequency = $period;
			break;
	}

	return $frequency;
}

/**
 * Modify the EDD product dropdown to query only products with Recurring enabled.
 *
 * @param array $args The array of parameters for the product dropdown.
 * @return array
 */
function edd_recurring_product_dropdown_recurring_only( $args ) {
	$args['meta_query'] = array(
		'relation' => 'OR',
		array(
			'key'     => 'edd_recurring',
			'value'   => 'yes',
			'compare' => '=',
		),
		array(
			'key'     => 'edd_variable_prices',
			'value'   => '"recurring";s:3:"yes"',
			'compare' => 'LIKE',
		),
	);

	return $args;
}

/**
 * In EDD 3.0, if an order item is part of a renewal,
 * make sure the status is set to complete instead of edd_subscription.
 *
 * @since 2.11.7
 * @param string $old_status    The old order item status.
 * @param string $new_status    The new order item status.
 * @param int    $order_item_id The order item ID.
 * @return void
 */
function edd_recurring_update_order_item_status( $old_status, $new_status, $order_item_id ) {

	if ( 'edd_subscription' !== $new_status ) {
		return;
	}

	edd_update_order_item(
		$order_item_id,
		array(
			'status' => 'complete',
		)
	);
}
add_action( 'edd_transition_order_item_status', 'edd_recurring_update_order_item_status', 10, 3 );

/**
 * Adds icons for recurring order status badges in EDD 3.0
 *
 * @since 2.12
 * @param string $icon HTML of the icon.
 * @param string $order_status  Order status.
 * @return string
 */
function edd_recurring_order_status_badges( $icon, $order_status ) {
	if ( 'edd_subscription' === $order_status ) {
		$icon = '<span class="edd-admin-order-status-badge__icon dashicons dashicons-update"></span>';
	}
	return $icon;
}
add_filter( 'edd_get_order_status_badge_icon', 'edd_recurring_order_status_badges', 10, 2 );

/**
 * Gets the recurring one time discount meta.
 *
 * @since 2.12
 * @param int $discount_id
 * @return string
 */
function edd_recurring_get_discount_renewal_meta( $discount_id ) {
	if ( empty( $discount_id ) ) {
		return '';
	}

	if ( function_exists( 'edd_get_adjustment_meta' ) && metadata_exists( 'edd_adjustment', $discount_id, 'recurring_one_time' ) ) {
		return edd_get_adjustment_meta( $discount_id, 'recurring_one_time', true );
	}

	$meta = get_post_meta( $discount_id, '_edd_discount_recurring_one_time', true );

	// If we made it here in EDD 3.0, the metadata didn't migrate and we need to update it.
	if ( function_exists( 'edd_update_adjustment_meta' ) ) {
		edd_update_adjustment_meta( $discount_id, 'recurring_one_time', $meta );
	}

	return $meta;
}
