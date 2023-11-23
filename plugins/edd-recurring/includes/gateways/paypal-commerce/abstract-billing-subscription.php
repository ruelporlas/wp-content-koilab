<?php
/**
 * BILLING.SUBSCRIPTION Event Abstract
 *
 * This can be used for all events where the resource type is `subscription`.
 *
 * @package   edd-recurring
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     2.11
 */

namespace EDD_Recurring\Gateways\PayPal;

use EDD\Gateways\PayPal\Webhooks\Events\Webhook_Event;

abstract class Billing_Subscription extends Webhook_Event {

	/**
	 * Retrieves an EDD subscription object from a subscription event.
	 *
	 * @since 2.11
	 *
	 * @return \EDD_Subscription
	 * @throws \Exception
	 */
	protected function get_subscription_from_event() {
		if ( empty( $this->event->resource_type ) || 'subscription' !== $this->event->resource_type ) {
			throw new \Exception( 'Invalid resource type.' );
		}

		if ( empty( $this->event->resource->id ) ) {
			throw new \Exception( 'Missing resource ID from payload.' );
		}

		$subscription = new \EDD_Subscription( $this->event->resource->id, true );
		if ( empty( $subscription->id ) ) {
			throw new \Exception( sprintf(
				'Failed to locate EDD subscription from PayPal profile ID: %s',
				$this->event->resource->id
			), 200 );
		}

		return $subscription;
	}

}
