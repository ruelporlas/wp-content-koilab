<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * The Subscription Class
 *
 * @since  2.4
 */
class EDD_Subscription {

	private $subs_db;

	public $id                    = 0;
	public $customer_id           = 0;
	public $period                = '';
	public $initial_amount        = '';
	public $initial_tax_rate      = '';
	public $initial_tax           = '';
	public $recurring_amount      = '';
	public $recurring_tax_rate    = '';
	public $recurring_tax         = '';
	public $bill_times            = 0;
	public $transaction_id        = '';
	public $parent_payment_id     = 0;
	public $product_id            = 0;
	public $price_id              = null;
	public $created               = '0000-00-00 00:00:00';
	public $expiration            = '0000-00-00 00:00:00';
	public $trial_period          = '';
	public $status                = 'pending';
	public $profile_id            = '';
	public $gateway               = '';

	/**
	 * @var EDD_Customer $customer
	 */
	public $customer;

	/**
	 * Get us started
	 *
	 * @since  2.4
	 * @return void
	 */
	function __construct( $_id_or_object = 0, $_by_profile_id = false ) {

		$this->subs_db = new EDD_Subscriptions_DB;

		if( $_by_profile_id ) {

			$_sub = $this->subs_db->get_by( 'profile_id', $_id_or_object );

			if( empty( $_sub ) ) {
				return false;
			}

			$_id_or_object = $_sub;

		}

		return $this->setup_subscription( $_id_or_object );
	}

	/**
	 * Setup the subscription object
	 *
	 * @since  2.4
	 * @return void
	 */
	private function setup_subscription( $id_or_object = 0 ) {

		if( empty( $id_or_object ) ) {
			return false;
		}

		if( is_numeric( $id_or_object ) ) {

			$sub = $this->subs_db->get( $id_or_object );

		} elseif( is_object( $id_or_object ) ) {

			$sub = $id_or_object;

		}

		if( empty( $sub ) ) {
			return false;
		}

		foreach( $sub as $key => $value ) {
			$this->$key = $value;
		}

		$this->customer = new EDD_Customer( $this->customer_id );
		$this->gateway  = edd_get_payment_gateway( $this->parent_payment_id );

		do_action( 'edd_recurring_setup_subscription', $this );

		return $this;
	}

	/**
	 * Magic __get function to dispatch a call to retrieve a private property
	 *
	 * @since 2.4
	 */
	public function __get( $key ) {

		if( method_exists( $this, 'get_' . $key ) ) {

			return call_user_func( array( $this, 'get_' . $key ) );

		} else {

			return new WP_Error( 'edd-subscription-invalid-property', sprintf( __( 'Can\'t get property %s', 'edd-recurring' ), $key ) );

		}

	}

	/**
	 * Creates a subscription
	 *
	 * @since  2.4
	 * @param  array  $data Array of attributes for a subscription
	 * @return mixed  false if data isn't passed and class not instantiated for creation
	 */
	public function create( $data = array() ) {

		if ( $this->id != 0 ) {
			return false;
		}

		$defaults = array(
			'customer_id'           => 0,
			'period'                => '',
			'initial_amount'        => '',
			'initial_tax_rate'      => '',
			'initial_tax'           => '',
			'recurring_amount'      => '',
			'recurring_tax_rate'    => '',
			'recurring_tax'         => '',
			'bill_times'            => 0,
			'parent_payment_id'     => 0,
			'product_id'            => 0,
			'price_id'              => null,
			'created'               => '',
			'expiration'            => '',
			'status'                => '',
			'profile_id'            => '',
		);

		$args = wp_parse_args( $data, $defaults );

		if( $args['expiration'] && strtotime( 'NOW', current_time( 'timestamp' ) ) > strtotime( $args['expiration'], current_time( 'timestamp' ) ) ) {

			if( 'active' == $args['status'] || 'trialling' == $args['status'] ) {

				// Force an active subscription to expired if expiration date is in the past
				$args['status'] = 'expired';

			}
		}

		do_action( 'edd_subscription_pre_create', $args );

		$id = $this->subs_db->insert( $args, 'subscription' );

		do_action( 'edd_subscription_post_create', $id, $args );

		$this->set_status( $args['status'] );

		return $this->setup_subscription( $id );

	}

	/**
	 * Updates a subscription
	 *
	 * @since  2.4
	 * @param  array $args Array of fields to update.
	 * @return bool
	 */
	public function update( $args = array() ) {

		$current_product  = $this->product_id;
		$current_price_id = $this->price_id;

		$ret = $this->subs_db->update( $this->id, $args );

		if ( $ret ) {
			if ( isset( $args['status'] ) ) {
				$this->set_status( $args['status'] );
			}

			if ( isset( $args['product_id'] ) && $current_product != $args['product_id'] ) {
				$this->add_note( sprintf( __( 'Product ID changed from %d to %d.', 'edd-recurring' ), $current_product, $args['product_id'] ) );
			}

			if ( isset( $args['price_id'] ) && ! is_null( $args['price_id'] ) && $current_price_id != $args['price_id'] ) {
				$this->add_note( sprintf( __( 'Price ID changed from %d to %d.', 'edd-recurring' ), $current_price_id, $args['price_id'] ) );
			}
		}

		// Clear the object cache for this subscription.
		wp_cache_delete( $this->id, 'edd_subscription_objects' );

		do_action( 'edd_recurring_update_subscription', $this->id, $args, $this );

		return $ret;

	}

	/**
	 * Delete the subscription
	 *
	 * @since  2.4
	 * @return bool
	 */
	public function delete() {
		do_action( 'edd_recurring_before_delete_subscription', $this );
		$deleted = $this->subs_db->delete( $this->id );
		do_action( 'edd_recurring_after_delete_subscription', $deleted, $this );

		wp_cache_delete( $this->id, 'edd_subscription_objects' );

		return $deleted;
	}

	/**
	 * Retrieves the parent payment ID
	 *
	 * @since  2.4
	 * @return int
	 */
	public function get_original_payment_id() {

		return $this->parent_payment_id;

	}

	/**
	 * Retrieve renewal payments for a subscription
	 *
	 * @since  2.4
	 * @return EDD_Payment[]
	 */
	public function get_child_payments() {

		// EDD 3.0 maps these to the correct order parameters.
		$payments = edd_get_payments( array(
			'post_parent'    => (int) $this->parent_payment_id,
			'posts_per_page' => '999',
			'post_status'    => 'any',
			'post_type'      => 'edd_payment',
			'meta_key'       => 'subscription_id',
			'meta_value'     => $this->id,
			'output'         => 'payments',
		) );

		return (array) $payments;

	}

	/**
	 * Counts the number of payments made to the subscription
	 *
	 * @since  2.4
	 * @return int
	 */
	public function get_total_payments() {

		// EDD 3.0
		if ( function_exists( 'edd_count_orders' ) ) {
			return edd_count_orders(
				array(
					'parent'     => $this->parent_payment_id,
					'number'     => 999,
					'type'       => 'sale',
					'meta_query' => array(
						array(
							'key'   => 'subscription_id',
							'value' => $this->id,
						),
					),
				)
			) + 1;
		}

		$args = array(
			'post_parent'    => (int) $this->parent_payment_id,
			'number'         => '999',
			'status'         => 'any',
			'meta_key'       => 'subscription_id',
			'meta_value'     => $this->id,
			'output'         => 'payments',
		);

		$payments = new EDD_Payments_Query( $args );

		return count( $payments->get_payments() ) + 1;

	}

	/**
	 * Returns the number of times the subscription has been billed
	 *
	 * @since  2.6
	 * @return int
	 */
	public function get_times_billed() {

		// Times billed should not include refunds or revoked payments. Therefore we don't use $this->get_total_payments
		$args = array(
			'post_parent'    => (int) $this->parent_payment_id,
			'number'         => '999',
			'status'         => array( 'complete', 'publish', 'edd_subscription' ),
			'meta_key'       => 'subscription_id',
			'meta_value'     => $this->id,
			'output'         => 'payments',
		);

		$payments = new EDD_Payments_Query( $args );

		$times_billed = count( $payments->get_payments() ) + 1;

		if( ! empty( $this->trial_period ) ) {
			$times_billed -= 1;
		}

		return $times_billed;

	}

	/**
	 * Gets the lifetime value for the subscription
	 *
	 * @since  2.4
	 * @return float
	 */
	public function get_lifetime_value() {

		$amount = 0.00;

		$parent_payment   = edd_get_payment( $this->parent_payment_id );
		$ignored_statuses = array( 'refunded', 'pending', 'abandoned', 'failed' );

		if ( ! empty( $parent_payment->status ) && ! in_array( $parent_payment->status, $ignored_statuses, true ) && ! empty( $parent_payment->cart_details ) ) {
			foreach ( $parent_payment->cart_details as $cart_item ) {
				if ( (int) $this->product_id === (int) $cart_item['id'] ) {
					$amount += $cart_item['price'];
					break;
				}
			}
		}

		$children = $this->get_child_payments();

		if( $children ) {

			foreach( $children as $child ) {
				$child_payment = edd_get_payment( $child->ID );
				if ( 'refunded' === $child_payment->status ) {
					continue;
				}

				$amount += $child_payment->total;
			}
		}

		return $amount;

	}

	/**
	 * Records a new payment on the subscription
	 *
	 * @since  2.4
	 * @param  array $args Array of values for the payment, including amount and transaction ID
	 * @return bool|integer False if no payment is crated, or the payment ID if successful.
	 */
	public function add_payment( $args = array() ) {

		/**
		 * For our default date, in case it isn't passed in, we need to determine if we
		 * are on EDD 2.x or 3.x, and account for the current_time being in GMT/UTC.
		 *
		 * EDD 3.0+ stores all dates in GMT/UTC, but current_time() defaults to using the WordPress timezone.
		 *
		 * Since we are updating all webhooks to send in a date, they are already accounting for this.
		 * This default will just be for anyone calling add_payment without the date argument.
		 */
		$use_gmt = function_exists( 'edd_get_order' ) ? true : false;

		$default_arguments = array(
			'amount'         => '', // This is the full amount that was charged at the gateway, INCLUDING tax.
			'tax'            => '', // This is going to be blank since 2.8, where taxes were no longer sent to the gateway. The only exception is when doing a manual renewal through the EDD subscription single view.
			'transaction_id' => '',
			'gateway'        => '',
			'date'           => current_time( 'mysql', $use_gmt ),
		);

		$args = wp_parse_args( $args, $default_arguments );
		$args = apply_filters( 'edd_recurring_add_payment_pre_args', $args, $this );

		if ( $this->payment_exists( $args['transaction_id'] ) ) {
			return false;
		}

		$payment                 = new EDD_Payment();
		$parent                  = edd_get_payment( $this->parent_payment_id );
		$payment->parent_payment = $this->parent_payment_id;
		$payment->customer_id    = $parent->customer_id;

		// Force the renewal to have no discount codes.
		$user_info               = $parent->user_info;
		$user_info['discount']   = 'none';
		$payment->user_info      = $user_info;

		$payment->user_id = $parent->user_id;
		$payment->address = $parent->address;

		// If the customer has a primary address set, use that instead.
		$address       = edd_get_customer_address( $payment->user_id );
		$address_check = array_filter( $address );
		if ( ! empty( $address_check ) ) {
			$payment->address = $address;
		}

		$customer = new EDD_Customer( $payment->customer_id );
		$email    = $customer->email?: $parent->email;

		$payment->email          = $email;
		$payment->currency       = $parent->currency;
		$payment->status         = 'edd_subscription';
		$payment->transaction_id = $args['transaction_id'];
		$payment->key            = $this->generate_order_payment_key( $email );
		$payment->total          = edd_sanitize_amount( sanitize_text_field( $args['amount'] ) );
		$payment->mode           = $parent->mode;
		// We set both dates here because if the completed date is differnt, the created date needs to match it.
		$payment->completed_date = $args['date'];
		$payment->date           = $args['date'];

		if( empty( $args['gateway'] ) ) {

			$payment->gateway    = $parent->gateway;

		} else {

			$payment->gateway    = $args['gateway'];

		}

		$tax = 0;
		if( ! empty( $args['tax'] ) ) {

			$tax = $args['tax'];

		} elseif( ! empty( $this->recurring_tax_rate ) ) {

			// The $args['amount'] includes the tax. We need to calculate the tax as though it is included in the amount.
			$tax = $args['amount'] - ( $args['amount'] / ( 1 + $this->recurring_tax_rate ) );

		} elseif( ! empty( $this->recurring_tax ) ) {

			$tax = $this->recurring_tax;

		}

		if ( ! empty( $this->recurring_tax_rate ) ) {
			$payment->tax_rate = $this->recurring_tax_rate;
		}

		// increase the earnings for each product in the subscription
		if ( $parent->downloads ) {
			foreach ( $parent->downloads as $download ) {

				if ( (int) $download['id'] !== (int) $this->product_id ) {
					continue;
				}

				$price_id    = isset( $download['options']['price_id'] ) && is_numeric( $download['options']['price_id'] ) ? $download['options']['price_id'] : false;
				$args['tax'] = is_numeric( $args['tax'] ) ? edd_format_amount( $args['tax'] ) : 0;

				// Set the amount for the EDD Payment based on the inclusive/exclusive of tax setting
				if ( edd_prices_include_tax() ) {
					$amount = $args['amount'];
				} else {
					$amount = $args['amount'] - $tax;
				}

				$payment->add_download( $download['id'], array(
					'item_price' => $amount,
					'tax'        => $tax,
					'price_id'   => $price_id
				) );

				if ( ! function_exists( 'edd_get_order' ) ) {
					edd_increase_earnings( $download['id'], $args['amount'] );
					$customer->increase_value( $args['amount'] );
				}
				break;

			}
		}

		$payment->save();
		$payment->update_meta( 'subscription_id', $this->id );

		if ( function_exists( 'edd_get_order' ) ) {
			$parent_order = edd_get_order( $this->parent_payment_id );
			if ( $parent_order instanceof \EDD\Orders\Order ) {
				if ( $parent_order->tax_rate_id ) {
					edd_update_order( $payment->ID, array(
						'tax_rate_id' => $parent_order->tax_rate_id
					) );
				} else {
					$custom_tax_rate = edd_get_order_meta( $parent_order->id, 'tax_rate', true );
					if ( ! empty( $custom_tax_rate ) ) {
						edd_update_order_meta( $payment->ID, 'tax_rate', sanitize_text_field( $custom_tax_rate ) );
					}
				}
			}

			$renewal_order = edd_get_order( $payment->ID );
			foreach ( $renewal_order->items as $item ) {
				edd_update_order_item(
					$item->id,
					array(
						'status' => 'complete',
					)
				);
			}
		}

		// Schedule the after payments actions for the order.
		if ( class_exists( '\EDD\Orders\DeferredActions' ) ) {
			$deferred_actions = new \EDD\Orders\DeferredActions();
			$deferred_actions->schedule_deferred_actions( $payment->ID );
		} else {
			edd_schedule_after_payment_action( $payment->ID );
		}

		do_action( 'edd_recurring_add_subscription_payment', $payment, $this );
		do_action( 'edd_recurring_record_payment', $payment->ID, $this->parent_payment_id, $args['amount'], $args['transaction_id'] );

		return $payment->ID;
	}

	/**
	 * Retrieves the transaction ID from the subscription
	 *
	 * @since  2.4.4
	 * @return bool
	 */
	public function get_transaction_id() {

		if( empty( $this->transaction_id ) ) {

			$txn_id = edd_get_payment_transaction_id( $this->parent_payment_id );

			if( ! empty( $txn_id ) && (int) $this->parent_payment_id !== (int) $txn_id ) {
				$this->set_transaction_id( $txn_id );
			}

		}

		return $this->transaction_id;

	}

	/**
	 * Stores the transaction ID for the subscription purchase
	 *
	 * @since  2.4.4
	 * @return bool
	 */
	public function set_transaction_id( $txn_id = '' ) {
		$this->update( array( 'transaction_id' => $txn_id ) );
		$this->transaction_id = $txn_id;
	}

	/**
	 * Renews a subscription
	 *
	 * @since  2.4
	 * @return bool
	 */
	public function renew( $payment_id = 0 ) {

		$expires = $this->get_expiration_time();

		// Determine what date to use as the start for the new expiration calculation
		if( $expires > current_time( 'timestamp' ) && $this->is_active() ) {

			$base_date  = $expires;

		} else {

			$base_date  = current_time( 'timestamp' );

		}

		$last_day = cal_days_in_month( CAL_GREGORIAN, date( 'n', $base_date ), date( 'Y', $base_date ) );

		if( 'quarter' == $this->period ) {

			$expiration = date( 'Y-m-d H:i:s', strtotime( '+3 months 23:59:59', $base_date ) );

		} else if ( 'semi-year' == $this->period ) {

			$expiration = date( 'Y-m-d H:i:s', strtotime( '+6 months 23:59:59', $base_date ) );

		} else {

			$expiration = date( 'Y-m-d H:i:s', strtotime( '+1 ' . $this->period . ' 23:59:59', $base_date ) );

		}

		if( date( 'j', $base_date ) == $last_day && 'day' != $this->period ) {
			$expiration = date( 'Y-m-d H:i:s', strtotime( $expiration . ' +2 days' ) );
		}

		$expiration  = apply_filters( 'edd_subscription_renewal_expiration', $expiration, $this->id, $this );

		// If a timestamp is passed in here, convert it to a date formatted string.
		if ( is_numeric( $expiration ) ) {
			$expiration = date( 'Y-m-d H:i:s', $expiration );
		}

		do_action( 'edd_subscription_pre_renew', $this->id, $expiration, $this );

		$times_billed = $this->get_times_billed();

		$args = array(
			'expiration' => $expiration,
			'status'     => 'active',
		);

		$this->update( $args );

		// Complete subscription if applicable
		if ( $this->bill_times > 0 && $times_billed >= $this->bill_times ) {
			$this->complete();
			$this->status = 'completed';
		}


		do_action( 'edd_subscription_post_renew', $this->id, $expiration, $this, $payment_id );
		do_action( 'edd_recurring_set_subscription_status', $this->id, $this->status, $this );

	}

	/**
	 * Marks a subscription as completed
	 *
	 * Subscription is completed when the number of payments matches the billing_times field
	 *
	 * @since  2.4
	 * @return void
	 */
	public function complete() {

		// Prevent setting a subscription as complete if it was previously set as cancelled, except if the sub is being manually updated by the site owner.
		if ( 'cancelled' === $this->status && ! isset( $_POST['edd_update_subscription'] ) && empty( $_POST['edd_update_subscription'] ) ) {
			return;
		}

		$args = array(
			'status' => 'completed'
		);

		if ( $this->update( $args ) ) {
			do_action( 'edd_subscription_completed', $this->id, $this );
		}
	}

	/**
	 * Marks a subscription as expired
	 *
	 * Subscription is completed when the billing times is reached
	 *
	 * @since  2.4
	 * @param  $check_expiration bool True if expiration date should be checked with merchant processor before expiring
	 * @return void
	 */
	public function expire( $check_expiration = false ) {

		$expiration = $this->expiration;

		if( $check_expiration && $this->check_expiration() ) {

			// check_expiration() updates $this->expiration so compare to $expiration above

			if( $expiration < $this->get_expiration() && current_time( 'timestamp' ) < $this->get_expiration_time() ) {

				return false; // Do not mark as expired since real expiration date is in the future
			}

		}

		$args = array(
			'status' => 'expired'
		);

		if( $this->update( $args ) ) {
			do_action( 'edd_subscription_expired', $this->id, $this );
		}

	}

	/**
	 * Marks a subscription as failing
	 *
	 * @since  2.4.2
	 * @return void
	 */
	public function failing() {

		$args = array(
			'status' => 'failing'
		);

		if( $this->update( $args ) ) {
			do_action( 'edd_subscription_failing', $this->id, $this );
		}

	}

	/**
	 * Marks a subscription as cancelled
	 *
	 * @since  2.4
	 * @return void
	 */
	public function cancel() {

		if ( 'cancelled' === $this->status ) {
			return; // Already cancelled
		}

		$args = array(
			'status' => 'cancelled',
		);

		if ( $this->update( $args ) ) {

			if ( is_user_logged_in() ) {

				$userdata = get_userdata( get_current_user_id() );
				$user     = $userdata->user_login;

			} else {

				$user = __( 'gateway', 'edd-recurring' );

			}

			do_action( 'edd_recurring_cancel_' . $this->gateway . '_subscription', $this, true );

			/* translators: 1. the subscription ID; 2. the user who cancelled the subscription. */
			$note = sprintf( __( 'Subscription #%1$d cancelled by %2$s', 'edd-recurring' ), $this->id, $user );
			$this->add_note( $note );

			do_action( 'edd_subscription_cancelled', $this->id, $this );
		}

	}

	/**
	 * Determines if subscription can be cancelled
	 *
	 * This method is filtered by payment gateways in order to return true on subscriptions
	 * that can be cancelled with a profile ID through the merchant processor
	 *
	 * @since  2.4
	 * @return bool
	 */
	public function can_cancel() {
		return apply_filters( 'edd_subscription_can_cancel', false, $this );
	}

	/**
	 * Retrieves the URL to cancel subscription
	 *
	 * @since  2.4
	 * @return string
	 */
	public function get_cancel_url() {

		$url = wp_nonce_url( add_query_arg( array( 'edd_action' => 'cancel_subscription', 'sub_id' => $this->id ) ), "edd-recurring-cancel-{$this->id}" );

		return apply_filters( 'edd_subscription_cancel_url', $url, $this );
	}

	/**
	 * Determines if subscription can be manually renewed
	 *
	 * This method is filtered by payment gateways in order to return true on subscriptions
	 * that can be renewed manually
	 *
	 * @since  2.5
	 * @return bool
	 */
	public function can_renew() {
		return apply_filters( 'edd_subscription_can_renew', false, $this );
	}

	/**
	 * Retrieves the URL to renew a subscription
	 *
	 * @since  2.5
	 * @return string
	 */
	public function get_renew_url() {

		$url = wp_nonce_url( add_query_arg( array( 'edd_action' => 'renew_subscription', 'sub_id' => $this->id ) ), "edd-recurring-renew-{$this->id}" );

		return apply_filters( 'edd_subscription_renew_url', $url, $this );
	}

	/**
	 * Determines if subscription can have their payment method updated
	 *
	 * @since  2.4
	 * @return bool
	 */
	public function can_update() {
		return apply_filters( 'edd_subscription_can_update', false, $this );
	}

	/**
	 * Retrieves the URL to update subscription
	 *
	 * @since  2.4
	 * @return string
	 */
	public function get_update_url() {

		$url = add_query_arg( array( 'action' => 'update', 'subscription_id' => $this->id ) );

		return apply_filters( 'edd_subscription_update_url', $url, $this );
	}

	/**
	 * Determines if subscription can be reactivated
	 *
	 * This method is filtered by payment gateways in order to return true on subscriptions
	 * that can be reactivated with a profile ID through the merchant processor
	 *
	 * @since  2.7.10
	 * @return bool
	 */
	public function can_reactivate() {
		return apply_filters( 'edd_subscription_can_reactivate', false, $this );
	}

	/**
	 * Retrieves the URL to reactivate subscription
	 *
	 * @since  2.7.10
	 * @return string
	 */
	public function get_reactivation_url() {

		$url = wp_nonce_url( add_query_arg( array( 'edd_action' => 'reactivate_subscription', 'sub_id' => $this->id ) ), "edd-recurring-reactivate-{$this->id}" );

		return apply_filters( 'edd_subscription_reactivation_url', $url, $this );

	}


	/**
	 * Determines if subscription can be retried when failing.
	 *
	 * This method is filtered by payment gateways in order to return true on subscriptions
	 * that can be retried with a profile ID through the merchant processor
	 *
	 * @since  2.7.10
	 * @return bool
	 */
	public function can_retry() {

		return apply_filters( 'edd_subscription_can_retry', false, $this );
	}

	/**
	 * Retries a failing subscription
	 *
	 * @since  2.7.10
	 * @return bool|WP_Error
	 */
	public function retry() {

		// Only mark a subscription as complete if it's not already cancelled.
		if ( ! $this->can_retry() ) {
			return new WP_Error( 'edd_recurring_not_failing', __( 'This subscription is not failing so cannot be retried.', 'edd-recurring' ) );
		}

		$result = false;

		/**
		 * Filter the response and allow gateways to hook in to handle the retry.
		 *
		 * This result is expected to be true or an instance of WP_Error on failure.
		 *
		 * @since  2.8
		 */
		$result = apply_filters( 'edd_recurring_retry_subscription_' . $this->gateway, $result, $this );

		do_action( 'edd_subscription_retry', $this->id, $this );

		if( ! $result ) {
			// Set up a generic error response
			$result = new WP_Error( 'edd_recurring_retry_failed', __( 'An error was encountered. Please check your merchant account logs.', 'edd-recurring' ) );
		}

		return $result;

	}

	/**
	 * Retrieves the URL to retry a failing subscription
	 *
	 * @since  2.7.10
	 * @return string
	 */
	public function get_retry_url() {

		$url = wp_nonce_url( add_query_arg( array( 'edd_action' => 'retry_subscription', 'sub_id' => $this->id ) ), "edd-recurring-retry-{$this->id}" );

		return apply_filters( 'edd_subscription_retry_url', $url, $this );
	}

	/**
	 * Determines if subscription is active
	 *
	 * @since  2.4
	 * @return bool
	 */
	public function is_active() {

		$is_active = false;
		if ( in_array( $this->status, $this->get_active_statuses(), true ) && ! $this->is_expired() ) {
			$is_active = true;
		}

		/**
		 * Filter to determine if a subscription is active.
		 *
		 * @param bool $is_active Whether or not the subscription is active.
		 * @param int $subscription_id The ID of the subscription.
		 * @param EDD_Subscription $subscription The subscription object.
		 */
		return apply_filters( 'edd_subscription_is_active', $is_active, $this->id, $this );
	}

	/**
	 * Determines if subscription is expired
	 *
	 * @since  2.4
	 * @return bool
	 */
	public function is_expired() {

		$is_expired = false;

		if ( 'expired' === $this->status ) {
			$is_expired = true;
		} elseif ( in_array( $this->status, array( 'active', 'cancelled', 'trialling' ), true ) ) {

			$expiration = $this->get_expiration_time();
			if ( $expiration && strtotime( 'NOW', current_time( 'timestamp' ) ) > $expiration ) {
				$is_expired = true;
				if ( 'active' === $this->status || $this->status == 'trialling'  ) {
					$this->expire();
				}
			}
		}

		/**
		 * Filter to allow the expiration status to be overridden.
		 *
		 * @param bool $is_expired Whether the subscription is expired
		 * @param int  $id         Subscription ID
		 * @param EDD_Subscription $subscription Subscription object
		 */
		return apply_filters( 'edd_subscription_is_expired', $is_expired, $this->id, $this );
	}

	/**
	 * Retrieves the expiration date
	 *
	 * @since  2.4
	 * @return string
	 */
	public function get_expiration() {
		return $this->expiration;
	}

	/**
	 * Checks the expiration date and returns the new date if it is different
	 *
	 * Will return true only if the expiration date retrieved is further in the future of the existing date.
	 *
	 * @since  2.6.6
	 * @return bool True if expiration changes
	 */
	public function check_expiration() {

		$ret   = false;

		$gateway = edd_recurring()->get_gateway( $this->gateway );

		if ( $gateway ) {

			if( is_callable( array( $gateway, 'get_expiration' ) ) ) {

				$expiration = $gateway->get_expiration( $this );

				if( ! is_wp_error( $expiration ) && $this->get_expiration_time() < strtotime( $expiration, current_time( 'timestamp' ) ) ) {

					// Update expiration date
					$this->update( array( 'expiration' => $expiration ) );
					$this->expiration = $expiration;
					$ret = true;

					$this->add_note( sprintf( __( 'Expiration synced with gateway and updated to %s', 'edd-recurring' ), $expiration ) );

					do_action( 'edd_recurring_check_expiration', $this, $expiration );

				}

			}

		}

		return $ret;
	}

	/**
	 * Retrieves the expiration date in a timestamp
	 *
	 * @since  2.4
	 * @return int
	 */
	public function get_expiration_time() {
		return strtotime( $this->expiration, current_time( 'timestamp' ) );
	}

	/**
	 * Retrieves the subscription status
	 *
	 * @since  2.4
	 * @return int
	 */
	public function get_status() {

		// Monitor for page load delays on pages with large subscription lists (IE: Subscriptions table in admin)
		$this->is_expired();
		return $this->status;
	}

	/**
	 * Retrieves the subscription status label
	 *
	 * @since  2.4
	 * @return int
	 */
	public function get_status_label() {

		switch ( $this->get_status() ) {
			case 'active':
				$status = __( 'Active', 'edd-recurring' );
				break;

			case 'cancelled':
				$status = __( 'Cancelled', 'edd-recurring' );
				break;

			case 'expired':
				$status = __( 'Expired', 'edd-recurring' );
				break;

			case 'pending':
				$status = __( 'Pending', 'edd-recurring' );
				break;

			case 'failing':
				$status = __( 'Failing', 'edd-recurring' );
				break;

			case 'trialling':
				$status = __( 'Trialling', 'edd-recurring' );
				break;

			case 'completed':
				$status = __( 'Completed', 'edd-recurring' );
				break;

			default:
				$status = ucfirst( $this->get_status() );
				break;
		}

		return $status;
	}

	/**
	 * Retrieves the subscription status badge
	 *
	 * @since  2.12
	 * @return string
	 */
	public function get_status_badge() {
		ob_start();
		?>
		<span class="edd-admin-subscription-status-badge edd-admin-subscription-status-badge--<?php echo esc_attr( $this->get_status() ); ?>">
			<span class="edd-admin-subscription-status-badge__text">
				<?php echo esc_html( $this->get_status_label() ); ?>
			</span>
		</span>
		<?php
		return ob_get_clean();
	}

	/**
	 * Determines if a payment exists with the specified transaction ID
	 *
	 * @since  2.4
	 * @param  string $txn_id The transaction ID from the merchant processor
	 * @return bool
	 */
	public function payment_exists( $txn_id = '' ) {

		if ( empty( $txn_id ) ) {
			return false;
		}

		$txn_id = esc_sql( $txn_id );

		if ( function_exists( 'edd_get_order_transaction_by' ) ) {
			$purchase = edd_get_order_transaction_by( 'transaction_id', $txn_id );
		} else {
			global $wpdb;
			$purchase = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_edd_payment_transaction_id' AND meta_value = '{$txn_id}' LIMIT 1" );
		}

		return ! empty( $purchase );
	}

	/**
	 * Get the parsed notes for a subscription as an array
	 *
	 * @since  2.7
	 * @param  integer $length The number of notes to get
	 * @param  integer $paged What note to start at
	 * @return array           The notes requested
	 */
	public function get_notes( $length = 20, $paged = 1 ) {

		$length = is_numeric( $length ) ? $length : 20;
		$offset = is_numeric( $paged ) && $paged != 1 ? ( ( absint( $paged ) - 1 ) * $length ) : 0;

		$all_notes   = $this->get_raw_notes();
		$notes_array = array_reverse( array_filter( explode( "\n\n", $all_notes ) ) );

		$desired_notes = array_slice( $notes_array, $offset, $length );

		return $desired_notes;

	}

	/**
	 * Get the total number of notes we have after parsing
	 *
	 * @since  2.7
	 * @return int The number of notes for the subscription
	 */
	public function get_notes_count() {

		$all_notes = $this->get_raw_notes();
		$notes_array = array_reverse( array_filter( explode( "\n\n", $all_notes ) ) );

		return count( $notes_array );

	}

	/**
	 * Add a note for the subscription
	 *
	 * @since  2.7
	 * @param string $note The note to add
	 * @return string|boolean The new note if added successfully, false otherwise
	 */
	public function add_note( $note = '' ) {

		$note = trim( $note );
		if ( empty( $note ) ) {
			return false;
		}

		$notes = $this->get_raw_notes();

		if( empty( $notes ) ) {
			$notes = '';
		}

		$note_string = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) . ' - ' . $note;
		$new_note    = apply_filters( 'edd_subscription_add_note_string', $note_string );
		$notes      .= "\n\n" . $new_note;

		do_action( 'edd_subscription_pre_add_note', $new_note, $this->id );

		$updated = $this->update( array( 'notes' => $notes ) );

		if ( $updated ) {
			$this->notes = $this->get_notes();
		}

		do_action( 'edd_subscription_post_add_note', $this->notes, $new_note, $this->id );

		// Return the formatted note, so we can test, as well as update any displays
		return $new_note;

	}

	/**
	 * Get the notes column for the subscription
	 *
	 * @since  2.7
	 * @return string The Notes for the subscription, non-parsed
	 */
	private function get_raw_notes() {

		$all_notes = $this->subs_db->get_column( 'notes', $this->id );

		return (string) $all_notes;

	}

	/**
	 * Convert object to array
	 *
	 * @since 2.7.4
	 *
	 * @return array
	 */
	public function to_array() {

		$array = array();
		foreach( get_object_vars( $this ) as $prop => $var ){

			if( is_object( $var ) && is_callable( array( $var, 'to_array' ) ) ) {

				$array[ get_class( $var ) ] = $var->to_array();

			} else {

				$array[ $prop ] = $var;

			}

		}

		return $array;
	}

	/**
	 * Whether the current user can manage a specific subscription.
	 *
	 * @since 2.11.8
	 * @param string $capability The capability required if the user is not the subscription owner.
	 * @return bool
	 */
	public function current_user_can( $capability = 'manage_subscriptions' ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( $capability ) ) {
			return true;
		}

		if ( (int) get_current_user_id() === (int) $this->customer->user_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Set the status property internally.  All places where the status of the subscription gets changed end up going
	 * going through here so the action here is reliable for hooking in on any status change.
	 *
	 * Method should only be called when the status for the subscription has actually been changed in the db.
	 *
	 * @since 2.7.14
	 * @param string  $new_status
	 */
	protected function set_status( $new_status ) {
		$old_status   = $this->status;
		$this->status = $new_status;

		if( is_user_logged_in() ) {

			$userdata = get_userdata( get_current_user_id() );
			$user     = $userdata->user_login;

		} else {

			$user = __( 'gateway', 'edd-recurring' );

		}

		if( strtolower( $this->status ) !== strtolower( $old_status ) ) {
			$this->add_note( sprintf( __( 'Status changed from %s to %s by %s', 'edd-recurring' ), $old_status, $this->status, $user ) );
		}
		do_action( 'edd_subscription_status_change', $old_status, $new_status, $this );
	}

	/**
	 * Generates the payment key for the order.
	 * In EDD 3.0, this just uses edd_generate_order_payment_key.
	 * In EDD 2.x, this function replicates the code used in edd_generate_order_payment_key.
	 *
	 * @since 2.11.7
	 * @todo  Remove compatibility shim once Recurring minimum is 3.0.
	 *
	 * @param string $key The email address for the order.
	 * @return string
	 */
	private function generate_order_payment_key( $key ) {
		if ( function_exists( 'edd_generate_order_payment_key' ) ) {
			return edd_generate_order_payment_key( $key );
		}
		$auth_key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$payment_key = strtolower( md5( $key . gmdate( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'edd', true ) ) );

		/**
		 * Filters the payment key
		 *
		 * @since 2.11.7
		 * @param string $payment_key The value to be filtered
		 * @param string $key Additional string used to help randomize key.
		 * @return string
		 */
		return apply_filters( 'edd_generate_order_payment_key', $payment_key, $key );
	}

	/**
	 * Helper function to get the array of statuses considered to be active subscription statuses.
	 *
	 * @return array
	 */
	private function get_active_statuses() {
		$active_statuses = array(
			'active', // Active is obviously an active state.
			'cancelled', // Cancelled is an active state because it just means it won't renew, but is also not yet expired.
			'trialling', // Trialing is an active state because people should have access to downloads during a trial.
		);

		// Check if completed subscriptions should be treated as "Active" subscriptions.
		if ( edd_get_option( 'recurring_treat_completed_subs_as_active' ) ) {
			$active_statuses[] = 'completed';
		}

		return $active_statuses;
	}
}
