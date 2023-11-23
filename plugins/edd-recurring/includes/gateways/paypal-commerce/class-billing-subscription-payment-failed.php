<?php
/**
 * Webhook Event: BILLING.SUBSCRIPTION.PAYMENT.FAILED
 *
 * @package    edd-recurring
 * @subpackage Gateways\PayPal
 * @copyright  Copyright (c) 2021, Sandhills Development, LLC
 * @license    GPL2+
 * @since      2.11
 */

namespace EDD_Recurring\Gateways\PayPal;

class Billing_Subscription_Payment_Failed extends Billing_Subscription {

	/**
	 * Handles subscription renewal payment failures.
	 *
	 * @since 2.11
	 * @throws \Exception
	 */
	protected function process_event() {
		$subscription = $this->get_subscription_from_event();
		$subscription->failing();

		if ( isset( $this->event->resource->billing_info->last_failed_payment ) ) {
			$subscription->add_note( sprintf(
			/* Translators: %s - information about the last failed payment */
				__( 'Failed payment details from PayPal: %s', 'edd-recurring' ),
				json_encode( $this->event->resource->billing_info->last_failed_payment )
			) );
		}

		/**
		 * Triggers after a recurring payment has failed.
		 *
		 * @param \EDD_Subscription $subscription
		 */
		do_action( 'edd_recurring_payment_failed', $subscription );
	}
}
