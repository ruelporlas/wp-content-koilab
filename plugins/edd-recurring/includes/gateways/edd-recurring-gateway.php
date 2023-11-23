<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Recurring_Gateway {

	public $id;
	public $friendly_name = '';
	public $subscriptions = array();
	public $purchase_data = array();
	public $offsite = false;
	public $email = 0;
	public $customer_id = 0;
	public $user_id = 0;
	public $payment_id = 0;
	public $failed_subscriptions = array();
	public $custom_meta = array();

	/**
	 * Store \EDD_Subscriber object once retrieved.
	 *
	 * @since 2.9.0
	 *
	 * @type \EDD_Recurring_Subscriber
	 */
	public $subscriber;

	/**
	 * Registers additionally supported functionalities for specific gateways.
	 *
	 * @since 2.9.0
	 * @type array
	 */
	public $supports = array();

	/**
	 * Get things started
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function __construct() {

		$this->init();

		add_action( 'edd_checkout_error_checks', array( $this, 'checkout_errors' ), 0, 2 );
		add_action( 'edd_gateway_' . $this->id, array( $this, 'process_checkout' ), 0 );
		add_action( 'init', array( $this, 'require_login' ), 9 );
		add_action( 'init', array( $this, 'process_webhooks' ), 9 );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ), 10 );
		add_action( 'edd_cancel_subscription', array( $this, 'process_cancellation' ) );
		add_action( 'edd_reactivate_subscription', array( $this, 'process_reactivation' ) );
		add_filter( 'edd_subscription_can_cancel', array( $this, 'can_cancel' ), 10, 2 );
		add_filter( 'edd_subscription_can_update', array( $this, 'can_update' ), 10, 2 );
		add_filter( 'edd_subscription_can_reactivate', array( $this, 'can_reactivate' ), 10, 2 );
		add_filter( 'edd_subscription_can_retry', array( $this, 'can_retry' ), 10, 2 );
		add_filter( 'edd_recurring_retry_subscription_' . $this->id, array( $this, 'retry' ), 10, 2 );
		add_action( 'edd_recurring_cancel_' . $this->id . '_subscription', array( $this, 'cancel' ), 10, 2 );
		add_action( 'edd_recurring_reactivate_' . $this->id . '_subscription', array( $this, 'reactivate' ), 10, 2 );
		add_action( 'edd_recurring_update_payment_form', array( $this, 'update_payment_method_form' ), 10, 1 );
		add_action( 'edd_recurring_update_subscription_payment_method', array( $this, 'process_payment_method_update' ), 10, 3 );
		add_action( 'edd_recurring_update_' . $this->id . '_subscription', array( $this, 'update_payment_method' ), 10, 2 );
		add_action( 'edd_after_cc_fields', array( $this, 'after_cc_fields' ) );

		add_filter( 'edd_subscription_profile_link_' . $this->id, array( $this, 'link_profile_id' ), 10, 2 );
		add_filter( 'edd_recurring_subscription_pre_gateway_args', array( $this, 'maybe_update_subscription_for_trial' ), 10, 2 );
	}

	/**
	 * Setup gateway ID and possibly load API libraries
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function init() {

		$this->id = '';

	}

	/**
	 * Enqueue necessary scripts. Perhaps only enqueue when edd_is_checkout()
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function scripts() {
	}

	/**
	 * Validate checkout fields
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function validate_fields( $data, $posted ) {

		/*

		if( true ) {
			edd_set_error( 'error_id_here', __( 'Error message here', 'edd-recurring' ) );
		}

		*/

	}

	/**
	 * Whether or not payments should automatically be set to `complete` during `record_signup()`.
	 *
	 * Defaults to the opposite of `EDD_Recurring_Gateway::$offsite`.
	 *
	 * @since 2.11
	 *
	 * @return bool
	 */
	protected function should_auto_complete_payment() {
		return ! $this->offsite;
	}

	/**
	 * Creates subscription payment profiles and sets the IDs so they can be stored
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function create_payment_profiles() {

		// Gateways loop through each download and creates a payment profile and then sets the profile ID

		foreach ( $this->subscriptions as $key => $subscription ) {
			$this->subscriptions[ $key ]['profile_id'] = '1234';
		}

	}

	/**
	 * Finishes the signup process by redirecting to the success page or to an off-site payment page
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function complete_signup() {

		wp_redirect( edd_get_success_page_uri() );
		exit;
	}

	/**
	 * Processes webhooks from the payment processor
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function process_webhooks() {

		// set webhook URL to: home_url( 'index.php?edd-listener=' . $this->id );

		if ( empty( $_GET['edd-listener'] ) || $this->id !== $_GET['edd-listener'] ) {
			return;
		}

		// process webhooks here

	}

	/**
	 * Determines if a subscription can be cancelled through the gateway
	 *
	 * @access      public
	 * @since       2.4
	 * @return      bool
	 */
	public function can_cancel( $ret, $subscription ) {
		return $ret;
	}

	/**
	 * Returns an array of subscription statuses that can be cancelled
	 *
	 * @access      public
	 * @since       2.6.3
	 * @return      array
	 */
	public function get_cancellable_statuses() {
		return apply_filters( 'edd_recurring_cancellable_statuses', array( 'active', 'trialling', 'failing' ) );
	}

	/**
	 * Cancels a subscription. If possible, cancel at the period end. If not possible, cancel immediately.
	 *
	 * @access      public
	 * @since       2.4
	 * @param       EDD_Subscription $subscription The EDD_Subscription object for the EDD Subscription being cancelled.
	 * @param       bool             $valid Currently this defaults to be true at all times.
	 * @return      bool
	 */
	public function cancel( $subscription, $valid ) {}

	/**
	 * Cancels a subscription immediately.
	 *
	 * @access      public
	 * @since       2.4
	 * @param       EDD_Subscription $subscription The EDD_Subscription object for the EDD Subscription being cancelled.
	 * @return      bool
	 */
	public function cancel_immediately( $subscription ) {
		// Fallback to the original cancel method.
		return $this->cancel( $subscription, true );
	}

	/**
	 * Determines if a subscription can be reactivated through the gateway
	 *
	 * @access      public
	 * @since       2.7.10
	 * @return      bool
	 */
	public function can_reactivate( $ret, $subscription ) {
		return $ret;
	}

	/**
	 * Reactivates a cancelled subscription
	 *
	 * @access      public
	 * @since       2.7.10
	 * @return      bool
	 */
	public function reactivate( $subscription, $valid ) {}

	/**
	 * Determines if a subscription can be retried through the gateway
	 *
	 * @access      public
	 * @since       2.7.10
	 * @return      bool
	 */
	public function can_retry( $ret, $subscription ) {
		return $ret;
	}

	/**
	 * Retries a failing subscription
	 *
	 * This method is connected to a filter instead of an action so we can return a nice error message.
	 *
	 * @access      public
	 * @since       2.7.10
	 * @return      bool|WP_Error
	 */
	public function retry( $result, $subscription ) {
		return $result;
	}

	/**
	 * Determines if a subscription can be cancelled through a gateway
	 *
	 * @since  2.4
	 * @param  bool   $ret            Default stting (false)
	 * @param  object $subscription   The subscription
	 * @return bool
	 */
	public function can_update( $ret, $subscription ) {
		return $ret;
	}

	/**
	 * Process the update payment form
	 *
	 * @since  2.4
	 * @param  int  $subscriber    EDD_Recurring_Subscriber
	 * @param  int  $subscription  EDD_Subscription
	 * @return void
	 */
	public function update_payment_method( $subscriber, $subscription ) { }

	/**
	 * Outputs the payment method update form
	 *
	 * @since  2.4
	 * @param  object $subscription The subscription object
	 * @return void
	 */
	public function update_payment_method_form( $subscription ) {

		if ( $subscription->gateway !== $this->id ) {
			return;
		}

		ob_start();
		edd_get_cc_form();
		echo ob_get_clean();

	}

	/**
	 * Get the expiration date with merchant processor
	 *
	 * @since  2.6.6
	 * @param  object $subscription The subscription object
	 * @return string Expiration date in Y-n-d H:i:s format
	 */
	public function get_expiration( $subscription ) {

		// Return existing expiration date by default
		return date( 'Y-n-d H:i:s', $subscription->get_expiration_time() );
	}

	/**
	 * Outputs any information after the Credit Card Fields
	 *
	 * @since  2.4
	 * @return void
	 */
	public function after_cc_fields() {}

	/**
	 * Determines if the gateway allows multiple subscriptions to be purchased at once.

	 * @since 2.8.5
	 * @return bool
	 */
	public function can_purchase_multiple_subs() {
		return true;
	}


	/****************************************************************
	 * Below methods should not be extended except in rare cases
	 ***************************************************************/


	/**
	 * Processes the checkout screen and sends sets up the subscription data for hand-off to the gateway
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function process_checkout( $purchase_data ) {

		if ( ! edd_recurring()->is_purchase_recurring( $purchase_data ) ) {
			return; // Not a recurring purchase so bail
		}

		if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

		if ( $purchase_data['user_info']['id'] < 1 && ! class_exists( 'EDD_Auto_Register' ) ) {
			edd_set_error( 'edd_recurring_logged_in', __( 'You must log in or create an account to purchase a subscription', 'edd-recurring' ) );
		}

		// Never let a user_id be lower than 0 since WP Core absints when doing get_user_meta lookups
		if ( $purchase_data['user_info']['id'] < 1 ) {
			$purchase_data['user_info']['id'] = 0;
		}

		// Initial validation
		do_action( 'edd_recurring_process_checkout', $purchase_data, $this );

		$errors = edd_get_errors();

		if ( $errors ) {

			edd_send_back_to_checkout( '?payment-mode=' . $this->id );

		}

		$this->purchase_data = apply_filters( 'edd_recurring_purchase_data', $purchase_data, $this );
		$this->user_id       = $this->get_user_id();
		$this->email         = $this->get_email();

		// Never let a user_id be lower than 0 since WP Core absints when doing get_user_meta lookups
		if ( empty( $this->purchase_data['user_info']['id'] ) || $this->purchase_data['user_info']['id'] < 1 ) {
			$this->purchase_data['user_info']['id'] = 0;
		}

		$this->setup_customer_subscriber();
		$this->build_subscriptions();

		// Store this so we can detect if the count changes due to failed subscriptions
		$initial_subscription_count = count( $this->subscriptions );

		do_action( 'edd_recurring_pre_create_payment_profiles', $this );

		if ( ! is_user_logged_in() ) {
			edd_set_error( 'edd_recurring_login', __( 'You must be logged in to purchase a subscription.', 'edd-recurring' ) );

			$this->handle_errors( edd_get_errors() );
		}

		// Create subscription payment profiles in the gateway
		$this->create_payment_profiles();

		// See if the gateway reported some subscriptions that failed
		if ( ! empty( $this->failed_subscriptions ) ) {

			// See if any subscriptions failed and remove them if necessary
			foreach ( $this->failed_subscriptions as $failed_sub ) {

				$item_key = $failed_sub['key'];
				// Remove it from the subscriptions array so we don't create an EDD Subscription entry
				unset( $this->subscriptions[ $item_key ] );

				// Remove it from the cart details and downloads so we don't charge the customer and give accees to it
				unset( $this->purchase_data['downloads'][ $item_key ] );
				unset( $this->purchase_data['cart_details'][ $item_key ] );

			}

			// Since we allow subscriptions to be marked as failed, make sure that we at least have one valid subscription
			if ( count( $this->failed_subscriptions ) === $initial_subscription_count ) {
				if ( ! empty( $failed_sub['error'] ) ) {
					edd_set_error( 'recurring-failed-sub-error-' . $item_key, $failed_sub['error'] );
				} else {
					edd_set_error( 'recurring-all-subscriptions-failed', __( 'There was an error processing your order. Please contact support.', 'edd-recurring' ) );
				}
			}

		}

		do_action( 'edd_recurring_post_create_payment_profiles', $this );

		// Look for errors after trying to create payment profiles
		$errors = edd_get_errors();

		if ( $errors ) {
			$this->handle_errors( $errors );
		}

		// Record the subscriptions and finish up
		$this->record_signup();

		// Finish the signup process. Gateways can perform off-site redirects here if necessary
		$this->complete_signup();

		// Look for any last errors
		$errors = edd_get_errors();

		// We shouldn't usually get here, but just in case a new error was recorded, we need to check for it
		if ( $errors ) {
			$this->handle_errors( $errors );
		}
	}

	/**
	 * Sets up EDD_Customer (ID only) and EDD_Recurring_Subscriber based on purchase data.
	 *
	 * @since 2.11.8.1 Moved to the main gateway class.
	 * @since 2.9.0
	 */
	public function setup_customer_subscriber() {
		$user_id = $this->get_user_id();
		$email   = $this->get_email();
		if ( empty( $user_id ) ) {
			$subscriber = new EDD_Recurring_Subscriber( $email );
		} else {
			$subscriber = new EDD_Recurring_Subscriber( $user_id, true );
		}

		if ( empty( $subscriber->id ) ) {
			if ( empty( $user_id ) && email_exists( $email ) ) {
				edd_set_error( 'existing_email', __( 'This purchase cannot be completed with this email address. If you already have an account, please log in and try again.', 'edd-recurring' ) );
				return false;
			}
			$name = '';

			if ( ! empty( $this->purchase_data['user_info']['first_name'] ) ) {
				$name = $this->purchase_data['user_info']['first_name'];
			}

			if ( ! empty( $this->purchase_data['user_info']['last_name'] ) ) {
				$name .= ' ' . $this->purchase_data['user_info']['last_name'];
			}

			$subscriber_data = array(
				'name'    => $name,
				'email'   => $email,
				'user_id' => $user_id,
			);

			$subscriber->create( $subscriber_data );
		}

		$this->subscriber  = $subscriber;
		$this->customer_id = $subscriber->id;
	}

	/**
	 * Maps/normalizes cart data to a list of subscription data.
	 *
	 * @since 2.9.0
	 * @since 2.11.8 Moved from the Stripe gateway to be used by all gateways.
	 */
	public function build_subscriptions() {
		$cart_details          = ! empty( $this->purchase_data['cart_details'] ) ? $this->purchase_data['cart_details'] : edd_get_cart_content_details();
		$checkout_subscription = new EDD\Recurring\Cart\Subscription( $cart_details );
		foreach ( $cart_details as $key => $item ) {
			$subscription = $checkout_subscription->get( $item, $key );
			if ( ! $subscription ) {
				continue;
			}

			$this->subscriptions[ $key ] = $subscription;
		}
	}

	/**
	 * Maybe updates the subscription data with trial information.
	 *
	 * @param array $args
	 * @param array $item
	 * @param EDD_Subscriber $subscriber
	 * @return array
	 */
	public function maybe_update_subscription_for_trial( $args, $item ) {

		if ( empty( $item['item_number']['options']['recurring']['trial_period']['unit'] ) || empty( $item['item_number']['options']['recurring']['trial_period']['quantity'] ) ) {
			return $args;
		}

		if ( ! $this->subscriber ) {
			$this->setup_customer_subscriber();
		}
		// If the item in the cart has a free trial period.
		if ( ! edd_get_option( 'recurring_one_time_trials' ) || ! $this->subscriber->has_trialed( $item['id'] ) ) {
			$args['has_trial']        = true;
			$args['trial_unit']       = $item['item_number']['options']['recurring']['trial_period']['unit'];
			$args['trial_quantity']   = $item['item_number']['options']['recurring']['trial_period']['quantity'];
			$args['status']           = 'trialling';
			$args['initial_amount']   = 0;
			$args['initial_tax_rate'] = 0;
			$args['initial_tax']      = 0;
		}

		return $args;
	}

	/**
	 * Handles errors that occur during checkout processing.
	 *
	 * @param array|false $errors
	 *
	 * @since 2.11
	 */
	protected function handle_errors( $errors = false ) {
		edd_send_back_to_checkout( '?payment-mode=' . $this->id );
	}

	/**
	 * Records purchased subscriptions in the database and creates an edd_payment record
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function record_signup() {


		$payment_data = array(
			'price'        => $this->purchase_data['price'],
			'date'         => $this->purchase_data['date'],
			'user_email'   => $this->purchase_data['user_email'],
			'purchase_key' => $this->purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $this->purchase_data['downloads'],
			'user_info'    => $this->purchase_data['user_info'],
			'cart_details' => $this->purchase_data['cart_details'],
			'status'       => 'pending',
		);

		foreach( $this->subscriptions as $key => $item ) {

			if ( ! empty( $item['has_trial'] ) ) {
				$payment_data['cart_details'][ $key ]['item_price'] = $item['initial_amount'] - $item['initial_tax'];
				$payment_data['cart_details'][ $key ]['tax']        = $item['initial_tax'];
				$payment_data['cart_details'][ $key ]['price']      = 0;
				$payment_data['cart_details'][ $key ]['discount']   = 0;

			}

		}

		// Record the pending payment
		$this->payment_id = edd_insert_payment( $payment_data );
		$payment          = edd_get_payment( $this->payment_id );

		if ( $this->should_auto_complete_payment() ) {

			// Offsite payments get verified via a webhook so are completed in webhooks()
			$payment->status = 'publish';
			$payment->save();

		}

		// Set subscription_payment
		$payment->update_meta( '_edd_subscription_payment', true );


		/*
		 * We need to delete pending subscription records to prevent duplicates. This ensures no duplicate subscription records are created when a purchase is being recovered. See:
		 * https://github.com/easydigitaldownloads/edd-recurring/issues/707
		 * https://github.com/easydigitaldownloads/edd-recurring/issues/762
		 */
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}edd_subscriptions WHERE parent_payment_id = %d AND status = 'pending';", $this->payment_id ) );

		$subscriber = new EDD_Recurring_Subscriber( $this->customer_id );

		// Now create the subscription record(s)
		foreach ( $this->subscriptions as $subscription ) {

			if( isset( $subscription['status'] ) ) {
				$status  = $subscription['status'];
			} else {
				$status = ! $this->should_auto_complete_payment() ? 'pending' : 'active';
			}

			$trial_period = ! empty( $subscription['has_trial'] ) ? $subscription['trial_quantity'] . ' ' . $subscription['trial_unit'] : '';
			$expiration   = $subscriber->get_new_expiration( $subscription['id'], $subscription['price_id'], $trial_period );

			// Check and see if we have a custom recurring period from the Custom Prices extension.
			if ( defined( 'EDD_CUSTOM_PRICES' ) ) {

				$cart_item = $this->purchase_data['cart_details'][ $subscription['cart_index'] ];

				if ( isset( $cart_item['item_number']['options']['custom_price'] ) ) {
					switch( $subscription['period'] ) {

						case 'quarter' :

							$period = '+ 3 months';

							break;

						case 'semi-year' :

							$period = '+ 6 months';

							break;

						default :

							$period = '+ 1 ' . $subscription['period'];

							break;

					}

					$expiration = date( 'Y-m-d H:i:s', strtotime( $period . ' 23:59:59', current_time( 'timestamp' ) ) );
				}

			}

			$args = array(
				'product_id'            => $subscription['id'],
				'price_id'              => isset( $subscription['price_id'] ) ? $subscription['price_id'] : null,
				'user_id'               => $this->purchase_data['user_info']['id'],
				'parent_payment_id'     => $this->payment_id,
				'status'                => $status,
				'period'                => $subscription['period'],
				'initial_amount'        => $subscription['initial_amount'],
				'initial_tax_rate'      => $subscription['initial_tax_rate'],
				'initial_tax'           => $subscription['initial_tax'],
				'recurring_amount'      => $subscription['recurring_amount'],
				'recurring_tax_rate'    => $subscription['recurring_tax_rate'],
				'recurring_tax'         => $subscription['recurring_tax'],
				'bill_times'            => $subscription['bill_times'],
				'expiration'            => $expiration,
				'trial_period'          => $trial_period,
				'profile_id'            => $subscription['profile_id'],
				'transaction_id'        => $subscription['transaction_id'],
			);

			$args = apply_filters( 'edd_recurring_pre_record_signup_args', $args, $this );
			$sub = $subscriber->add_subscription( $args );

			if ( $this->should_auto_complete_payment() && $trial_period ) {
				$subscriber->add_meta( 'edd_recurring_trials', $subscription['id'] );
			}

			/**
			 * Triggers right after a subscription is created.
			 *
			 * @param EDD_Subscription      $sub          New subscription object.
			 * @param array                 $subscription Gateway subscription arguments.
			 * @param EDD_Recurring_Gateway $this         Gateway object.
			 *
			 * @since 2.10.2
			 */
			do_action( 'edd_recurring_post_record_signup', $sub, $subscription, $this );

		}

		// Now look if the gateway reported any failed subscriptions and log a payment note
		if ( ! empty( $this->failed_subscriptions ) ) {

			foreach ( $this->failed_subscriptions as $failed_subscription ) {
				$note = sprintf( __( 'Failed creating subscription for %s. Gateway returned: %s', 'edd-recurring' ), $failed_subscription['subscription']['name'], $failed_subscription['error'] );
				$payment->add_note( $note );
			}

			$payment->update_meta( '_edd_recurring_failed_subscriptions', $this->failed_subscriptions );
		}

		if ( ! empty( $this->custom_meta ) ) {
			foreach ( $this->custom_meta as $key => $value ) {
				$payment->update_meta( $key, $value );
			}
		}

	}

	/**
	 * Triggers the validate_fields() method for the gateway during checkout submission
	 *
	 * This should not be extended
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function checkout_errors( $data, $posted ) {

		if ( $this->id !== $posted['edd-gateway'] ) {
			return;
		}

		if ( ! edd_recurring()->cart_contains_recurring() ) {
			return;
		}

		if ( edd_recurring()->cart_is_mixed_with_trials() ) {
			edd_set_error( 'edd_recurring_mixed_trials_cart', __( 'Free trials and non-trials may not be purchased at the same time. Please purchase each separately.', 'edd-recurring' ) );
		}

		// Show errors related to mixed carts.

		$enabled_gateways = edd_get_enabled_payment_gateways();

		$show_mixed_error = (
			! in_array( 'mixed_cart', $this->supports, true ) &&
			edd_recurring()->cart_is_mixed()
		);

		if ( $show_mixed_error ) {

			if ( ! isset( $enabled_gateways['stripe'] ) ) {

				// Show generic error to non show managers if no other gateways can be used to complete the purchase.
				if ( ! current_user_can( 'manage_shop_settings' ) ) {
					edd_set_error( 'edd_recurring_mixed_cart', __( 'Subscriptions and non-subscriptions may not be purchased at the same time. Please purchase each separately.', 'edd-recurring' ) );

				// Alert shop managers that Stripe supports mixed carts.
				} else {
					edd_set_error(
						'edd_recurring_mixed_cart_install_gateway',
						wp_kses(
							wpautop(
								'<em>' . __( 'This message is showing because you are a shop manager', 'edd-recurring' ) . '</em>'
							) .
							wpautop(
								sprintf(
									/** translators: %1$s Opening anchor tag, do not translate. %2$s Closing anchor tag, do not translate. */
									__( 'Your active payment gateways do not support mixed carts. The %1$sStripe Payment Gateway%2$s allows customers to purchase carts containing both recurring subscriptions and one-time charges at the same time.', 'edd-recurring' ),
									'<a href="https://easydigitaldownloads.com/downloads/stripe-gateway/?utm_source=checkout&utm_medium=recurring&utm_campaign=admin" rel="noopener noreferrer" target="_blank">',
									'</a>'
								)
							),
							array(
								'em' => true,
								'p'  => true,
								'a'  => array(
									'href'   => true,
									'rel'    => true,
									'target' => true,
								),
							)
						)
					);
				}

			// Show an error to switch to the Stripe gateway to complete purchase.
			} else {

				$gateway_checkout_uri = add_query_arg(
					array(
						'payment-mode' => 'stripe',
					),
					edd_get_checkout_uri()
				);

				edd_set_error(
					'edd_recurring_mixed_cart_use_gateway',
					wp_kses(
						sprintf(
							__( 'Sorry, purchasing a subscription and non-subscription product is only supported when paying by credit card. %1$sSwitch to this payment method%2$s.', 'edd-recurring' ),
							'<a href="' . esc_url( $gateway_checkout_uri ) . '">',
							'</a>'
						),
						array(
							'a' => array(
								'href'   => true,
							),
						)
					)
				);
			}
		}

		$this->validate_fields( $data, $posted );
	}

	/**
	 * Process the update payment form
	 *
	 * @since  2.4
	 * @param  int  $user_id            User ID
	 * @param  int  $subscription_id    Subscription ID
	 * @param  bool $verified           Sanity check that the request to update is coming from a verified source
	 * @return void
	 */
	public function process_payment_method_update( $user_id, $subscription_id, $verified ) {

		if ( 1 !== $verified ) {
			wp_die( __( 'Unable to verify payment update.', 'edd-recurring' ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( __( 'You must be logged in to update a payment method.', 'edd-recurring' ) );
		}

		$subscription = new EDD_Subscription( $subscription_id );
		if ( $subscription->gateway !== $this->id ) {
			return;
		}

		if ( empty( $subscription->id ) ) {
			wp_die( __( 'Invalid subscription id.', 'edd-recurring' ) );
		}

		$subscriber = new EDD_Recurring_Subscriber( $subscription->customer_id );
		if ( empty( $subscriber->id ) ) {
			wp_die( __( 'Invalid subscriber.', 'edd-recurring' ) );
		}

		// Make sure the User doing the udpate is the user the subscription belongs to
		if ( $user_id != $subscriber->user_id ) {
			wp_die( __( 'User ID and Subscriber do not match.', 'edd-recurring' ) );
		}

		// make sure we don't have any left over errors present
		edd_clear_errors();

		do_action( 'edd_recurring_update_' . $subscription->gateway .'_subscription', $subscriber, $subscription );

		$errors = edd_get_errors();

		if ( empty( $errors ) ) {

			$url = add_query_arg( array( 'updated' => true ) );
			wp_redirect( $url );
			die();
		}

		$url = add_query_arg( array( 'action' => 'update', 'subscription_id' => $subscription->id ) );
		wp_redirect( $url );
		die();

	}

	/**
	 * Handles cancellation requests for a subscription
	 *
	 * This should not be extended
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function process_cancellation( $data ) {

		if ( empty( $data['sub_id'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$sub_id = absint( $data['sub_id'] );

		if ( ! wp_verify_nonce( $data['_wpnonce'], "edd-recurring-cancel-{$sub_id}" ) ) {
			wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

		$subscription = new EDD_Subscription( $sub_id );

		if ( ! $subscription->current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to cancel this subscription.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

		try {

			$subscription->cancel();

			if( is_admin() ) {

				wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-subscriptions&edd-message=cancelled&id=' . $subscription->id ) );
				exit;

			} else {

				$redirect = remove_query_arg( array( '_wpnonce', 'edd_action', 'sub_id' ), add_query_arg( array( 'edd-message' => 'cancelled' ) ) );
				$redirect = apply_filters( 'edd_recurring_cancellation_redirect', $redirect, $subscription );
				wp_safe_redirect( $redirect );
				exit;

			}

		} catch ( Exception $e ) {
			wp_die( $e->getMessage(), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

	}

	/**
	 * Process subscription reactivation
	 *
	 * @access      public
	 * @since       2.6
	 * @return      void
	 */
	public function process_reactivation( $data ) {

		if ( empty( $data['sub_id'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$sub_id = absint( $data['sub_id'] );

		if ( ! wp_verify_nonce( $data['_wpnonce'], "edd-recurring-reactivate-{$sub_id}" ) ) {
			wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

		$subscription = new EDD_Subscription( $sub_id );

		if ( ! $subscription->current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to cancel this subscription.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

		if ( ! $subscription->can_reactivate() ) {
			wp_die( __( 'This subscription cannot be reactivated', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

		try {

			do_action( 'edd_recurring_reactivate_' . $subscription->gateway . '_subscription', $subscription, true );

			$user = is_user_logged_in() ? wp_get_current_user()->user_login : 'gateway';
			$note = sprintf( __( 'Subscription reactivated by %s', 'edd-recurring' ), $user );
			$subscription->add_note( $note );

			if( is_admin() ) {

				wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-subscriptions&edd-message=reactivated&id=' . $subscription->id ) );
				exit;

			} else {

				$redirect = remove_query_arg( array( '_wpnonce', 'edd_action', 'sub_id' ), add_query_arg( array( 'edd-message' => 'reactivated' ) ) );
				$redirect = apply_filters( 'edd_recurring_reactivation_redirect', $redirect, $subscription );
				wp_safe_redirect( $redirect );
				exit;

			}

		} catch ( Exception $e ) {
			wp_die( $e->getMessage(), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
		}

	}

	/**
	 * Make it so that accounts are required
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	 */
	public function require_login() {

		$cart_items    = edd_get_cart_contents();
		$has_recurring = false;

		if ( empty( $cart_items ) ) {
			return;
		}


		// Loops through each item to see if any of them are recurring
		foreach( $cart_items as $item ) {

			if( ! isset( $item['options']['recurring'] ) ) {
				continue;
			}

			$has_recurring = true;

		}

		$auto_register = class_exists( 'EDD_Auto_Register' );

		if( $has_recurring && ! $auto_register ) {

			add_filter( 'edd_no_guest_checkout', '__return_true' );
			add_filter( 'edd_logged_in_only', '__return_true' );

		}

	}

	/**
	 * Retrieve subscription details
	 *
	 * This method should be extended by each gateway in order to call the gateway API to determine the status and expiration of the subscription
	 *
	 * @access      public
	 * @since       2.4
	 * @return      array
	 */
	public function get_subscription_details( EDD_Subscription $subscription ) {

		/*
		 * Return value for valid subscriptions should be an array containing the following keys:
		 *
		 * - status: The status of the subscription (active, cancelled, expired, completed, pending, failing)
		 * - expiration: The expiration / renewal date of the subscription
		 * - error: An instance of WP_Error with error code and message (if any)
		 */

		$ret = array(
			'status'     => '',
			'expiration' => '',
			'error'      => '',
		);

		return $ret;

	}

	public function link_profile_id( $profile_id, $subscription ) {
		return $profile_id;
	}

	/**
	 * Gets the user ID from the purchase data.
	 *
	 * @since 2.11.9
	 * @return void
	 */
	private function get_user_id() {
		if ( $this->user_id ) {
			return $this->user_id;
		}

		if ( ! empty( $this->purchase_data['user_info']['id'] ) ) {
			$this->user_id = $this->purchase_data['user_info']['id'];
		}

		return $this->user_id;
	}

	/**
	 * Gets the user email from the purchase data.
	 *
	 * @since 2.11.9
	 * @return void
	 */
	private function get_email() {
		if ( $this->email ) {
			return $this->email;
		}

		if ( ! empty( $this->purchase_data['user_info']['email'] ) ) {
			$this->email = $this->purchase_data['user_info']['email'];
		}

		return $this->email;
	}

	/**
	 * Convert a DateTime object of the order date and return it in the MySQL DATETIME format.
	 *
	 * Since EDD 3.0 uses GMT for all dates in the order records, we assume GMT first, but if we detect
	 * EDD 2.x, we need to convert this to the Store's timezone.
	 *
	 * @param DateTime $date_time The DateTime object of the order date.
	 *
	 * @return string The MySQL DATETIME formatted date, converted into the timezone depending on EDD version.
	 */
	protected function get_formatted_order_date( DateTime $date_time ) {
		/**
		 * EDD 3.0+ uses GMT format for all dates.
		 */
		$store_timezone = 'GMT';
		if ( ! function_exists( 'edd_get_order' ) ) {
			/**
			 * EDD 2.x used the store's timezone when creating orders.
			 *
			 * WP 5.3+ can use the wp_timezone_string, older versions cannot. We still need to verify if it is empty before assuming it exists.
			 */
			$store_timezone = function_exists( 'wp_timezone_string' ) && ! empty( wp_timezone_string() ) ? wp_timezone_string() : get_option( 'gmt_offset' );
		}

		// Set the timezone on the DateTime object.
		$date_time->setTimezone( new DateTimeZone( $store_timezone ) );

		// Now set the date into the arguments for creating the renewal payment.
		return $date_time->format( 'Y-m-d H:i:s' );
	}
}
