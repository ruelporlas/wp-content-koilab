<?php
/**
 * Webhook Event: PAYMENT.SALE.COMPLETED
 *
 * This fires when a new recurring payment is made.
 * Also fires when the first payment is made.
 *
 * @package    edd-recurring
 * @subpackage Gateways\PayPal
 * @copyright  Copyright (c) 2021, Sandhills Development, LLC
 * @license    GPL2+
 * @since      2.11
 */

namespace EDD_Recurring\Gateways\PayPal;

use EDD\Gateways\PayPal\Webhooks\Events\Webhook_Event;

class Payment_Sale_Completed extends Webhook_Event {

	/**
	 * Handles sale completion events.
	 *
	 * @since 2.11
	 * @throws \Exception
	 */
	protected function process_event() {
		if ( empty( $this->event->resource->billing_agreement_id ) ) {
			throw new \Exception( 'Missing subscription ID from event.', 200 );
		}

		$subscription = new \EDD_Subscription( $this->event->resource->billing_agreement_id, true );
		if ( empty( $subscription->id ) ) {
			throw new \Exception( sprintf( 'Failed to locate EDD subscription from PayPal ID %s', $this->event->resource->billing_agreement_id ), 200 );
		}

		$payment = edd_get_payment( $subscription->parent_payment_id );
		if ( $this->is_initial_payment( $this->event->resource, $payment ) && ( ! $payment->transaction_id || $payment->transaction_id === $this->event->resource->id ) ) {
			$this->handle_initial_payment( $payment, $subscription );
		} else {
			$this->handle_renewal_payment( $subscription );
		}
	}

	/**
	 * Determines whether or not the PayPal transaction is the first payment in a subscription.
	 * This is determined to be true if the timestamps are less than 24 hours apart.
	 *
	 * @since 2.11
	 *
	 * @param object       $transaction
	 * @param \EDD_Payment $payment
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function is_initial_payment( $transaction, \EDD_Payment $payment ) {
		$transaction_date = new \DateTime( $transaction->create_time );
		$payment_date     = new \DateTime( $payment->date );

		$difference = abs( $transaction_date->getTimestamp() - $payment_date->getTimestamp() );

		return $difference < DAY_IN_SECONDS;
	}

	/**
	 * Handles processing the initial payment in a subscription.
	 *
	 * @since 2.11
	 *
	 * @param \EDD_Payment      $payment
	 * @param \EDD_Subscription $subscription
	 *
	 * @throws \Exception
	 */
	private function handle_initial_payment( \EDD_Payment $payment, \EDD_Subscription $subscription ) {
		edd_debug_log( sprintf(
			'PayPal Recurring - Handling initial payment for subscription #%d. Payment state: %s',
			$subscription->id,
			esc_html( $this->event->resource->state )
		) );

		global $edd_recurring_paypal_commerce;

		switch ( strtolower( $this->event->resource->state ) ) {
			case 'declined' :
				$payment->status = 'failed';
				$payment->add_note( sprintf(
					__( 'PayPal payment declined. Details: %s', 'edd-recurring' ),
					( ! empty( $this->event->resource->status_details ) ? json_encode( $this->event->resource->status_details ) : __( 'n/a', 'edd-recurring' ) )
				) );
				$payment->save();
				$subscription->failing();

				return;
			case 'pending' :
				if ( ! empty( $this->event->resource->capture_status_details->reason ) ) {
					$reason = $edd_recurring_paypal_commerce::capture_status_to_note( $this->event->resource->capture_status_details->reason );
					$payment->add_note( __( 'Payment still processing in PayPal.', 'edd-recurring' ) . ' ' . $reason );
				}

				if ( 'processing' !== $payment->status ) {
					$payment->status = 'processing';
					$payment->save();
				}

				return;
			case 'completed' :
				try {
					$this->validate_transaction_for_payment( $this->event->resource, $payment );
					$payment->status = 'publish';
					$payment->save();

					edd_set_payment_transaction_id( $payment->ID, $this->event->resource->id );

					if ( 'complete' !== $subscription->status ) {
						$subscription->update( array( 'status' => 'active' ) );
					}
				} catch ( \Exception $e ) {
					$note = sprintf(
						/* Translators: %1$s error message; %2$s payment data from API */
						__( 'Payment failed. Error message: %1$s. Payment data: %2$s.', 'edd-recurring' ),
						$e->getMessage(),
						json_encode( $this->event->resource )
					);

					$payment->status = 'failed';
					$payment->add_note( $note );
					$payment->save();

					throw new \Exception( $note, 200, $e );
				}

				return;
		}
	}

	/**
	 * Determines if a sale transaction is valid for a payment.
	 *
	 * Annoyingly, this is a duplicate of EDD_Recurring_PayPal_Commerce::is_transaction_valid_for_payment(), but
	 * the resource structure here is different, so we can't use that same method.
	 *
	 * @see   \EDD_Recurring_PayPal_Commerce::is_transaction_valid_for_payment()
	 *
	 * @since 2.11
	 * @since 2.11.4 Throws an exception on failure instead of returning `false`.
	 *
	 * @param object       $transaction
	 * @param \EDD_Payment $payment
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function validate_transaction_for_payment( $transaction, \EDD_Payment $payment ) {
		if ( empty( $transaction->id ) || empty( $transaction->state ) ) {
			throw new \Exception( 'Missing transaction ID or state.' );
		}

		if ( 'COMPLETED' !== strtoupper( $transaction->state ) ) {
			throw new \Exception( sprintf( 'Transaction is not complete. State: %s', $transaction->state ) );
		}

		// Verify amount and currency.
		if ( ! isset( $transaction->amount->total ) ) {
			throw new \Exception( 'Missing transaction amount total.' );
		}

		if ( (float) $transaction->amount->total < (float) $payment->total ) {
			throw new \Exception( sprintf( 'Transaction total (%s) is less than payment total (%s).', $transaction->amount->total, edd_format_amount( $payment->total ) ) );
		}

		if ( strtoupper( $payment->currency ) !== strtoupper( $transaction->amount->currency ) ) {
			throw new \Exception( sprintf(
				'PayPal Recurring - PayPal transaction currency (%s) doesn\'t match EDD payment currency (%s).',
				strtoupper( $transaction->amount->currency ),
				strtoupper( $payment->currency )
			) );
		}
	}

	/**
	 * Handles processing a renewal payment.
	 *
	 * @since 2.11
	 *
	 * @param \EDD_Subscription $subscription
	 *
	 * @throws \Exception
	 */
	private function handle_renewal_payment( \EDD_Subscription $subscription ) {
		edd_debug_log( sprintf( 'PayPal Recurring - Handling renewal payment for subscription #%d', $subscription->id ) );

		// I don't think this will ever happen because we should get a different event.
		if ( 'declined' === strtolower( $this->event->resource->state ) ) {
			edd_debug_log( 'PayPal Recurring - Payment status is declined.' );

			$subscription->failing();
			$subscription->add_note( __( 'Renewal payment processing failed at PayPal.', 'edd-recurring' ) );

			return;
		}

		if ( 'completed' === strtolower( $this->event->resource->state ) ) {
			edd_debug_log( 'PayPal Recurring - Adding renewal payment.' );

			$subscription_currency = edd_get_payment_currency_code( $subscription->parent_payment_id );
			if ( ! empty( $this->event->resource->amount->currency ) && strtoupper( $subscription_currency ) !== strtoupper( $this->event->resource->amount->currency ) ) {
				$subscription->add_note( sprintf(
				/* Translators: %s - currency code */
					__( 'Renewal payment processing failed due to invalid currency. PayPal currency: %s', 'edd-recurring' ),
					strtoupper( sanitize_text_field( $this->event->resource->amount->currency ) )
				) );

				throw new \Exception( sprintf( 'Invalid currency code. Expected: %s; Actual: %s.', $subscription_currency, strtoupper( $this->event->resource->amount->currency ) ), 200 );
			}

			$subscription_payment_args = array(
				'amount'         => $this->event->resource->amount->total,
				'transaction_id' => $this->event->resource->id,
			);

			/**
			 * Create a DateTime object of the create_time, so we can adjust as needed.
			 *
			 * PayPal Webhooks send these in a Zulu Time, which is equivilent to GMT/UTC.
			*/
			$subscription_payment_date = new \DateTime( $this->event->resource->create_time );

			// To make sure we don't inadvertently fail, make sure the date was parsed correctly before working with it.
			if ( $subscription_payment_date instanceof \DateTime ) {
				if ( ! function_exists( 'edd_get_order' ) ) {
					/**
					 * EDD 2.x used the store's timezone when creating orders. So we need to convert it.
					 *
					 * WP 5.3+ can use the wp_timezone_string, older versions cannot. We still need to verify if it is empty before assuming it exists.
					 */
					$store_timezone = function_exists( 'wp_timezone_string' ) && ! empty( wp_timezone_string() ) ? wp_timezone_string() : get_option( 'gmt_offset' );
					$subscription_payment_date->setTimezone( new \DateTimeZone( $store_timezone ) );
				}

				// Now set the date into the arguments for creating the renewal payment.
				$subscription_payment_args['date'] = $subscription_payment_date->format( 'Y-m-d H:i:s' );
			}

			$payment_id = $subscription->add_payment( $subscription_payment_args );

			if ( ! empty( $payment_id ) ) {
				$subscription->renew( $payment_id );
			}
		} else {
			throw new \Exception( sprintf( 'Unexpected payment status: %s. ID: %s', $this->event->resource->state, $this->event->resource->id ), 200 );
		}
	}
}
