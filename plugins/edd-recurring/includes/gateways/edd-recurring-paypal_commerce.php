<?php
/**
 * PayPal Commerce
 *
 * @package   edd-recurring
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     2.11
 */

use EDD\Gateways\PayPal\API;
use EDD\Gateways\PayPal\Exceptions\API_Exception;
use EDD\Gateways\PayPal\Exceptions\Gateway_Exception;

global $edd_recurring_paypal_commerce;

class EDD_Recurring_PayPal_Commerce extends EDD_Recurring_Gateway {
	/**
	 * ID of the gateway
	 *
	 * @var string
	 */
	public $id = 'paypal_commerce';

	/**
	 * @var string PayPal product ID used for this checkout.
	 */
	public $paypal_product_id;

	/**
	 * EDD_Recurring_PayPal_Commerce constructor.
	 *
	 * Ensures EDD 2.11+ is installed.
	 *
	 * @since 2.11
	 */
	public function __construct() {
		if ( ! class_exists( '\\EDD\\Gateways\\PayPal\\API' ) ) {
			return;
		}

		parent::__construct();
	}

	/**
	 * Get things started
	 *
	 * @since 2.11
	 */
	public function init() {
		$this->include_files();

		add_filter( 'edd_paypal_webhook_events', array( $this, 'webhook_events' ), 10, 2 );
		add_filter( 'edd_paypal_on_approve_action', array( $this, 'set_subscription_approval_action' ) );
		add_filter( 'edd_paypal_js_sdk_query_args', array( $this, 'set_sdk_intent' ) );
		add_filter( 'edd_payment_confirm_paypal_commerce', array( $this, 'payment_confirmation_page' ) );

		add_action( 'wp_ajax_nopriv_edd_recurring_approve_paypal_subscription', array( $this, 'activate_subscription' ) );
		add_action( 'wp_ajax_edd_recurring_approve_paypal_subscription', array( $this, 'activate_subscription' ) );

		add_action( 'wp_ajax_nopriv_edd_recurring_confirm_transaction', array( $this, 'confirm_transaction' ) );
		add_action( 'wp_ajax_edd_recurring_confirm_transaction', array( $this, 'confirm_transaction' ) );
	}

	/**
	 * Includes extra files for PayPal Commerce.
	 */
	private function include_files() {
		require_once EDD_RECURRING_PLUGIN_DIR . 'includes/gateways/paypal-commerce/abstract-billing-subscription.php';

		// Dynamically load all webhook event classes.
		foreach ( array_keys( $this->get_webhook_events() ) as $event_name ) {
			$file_path = EDD_RECURRING_PLUGIN_DIR . 'includes/gateways/paypal-commerce/class-' . strtolower( str_replace( '.', '-', $event_name ) ) . '.php';
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
	}

	/**
	 * Determines if the gateway allows multiple subscriptions to be purchased at once.
	 *
	 * @since 2.11
	 * @return bool
	 */
	public function can_purchase_multiple_subs() {
		return false;
	}

	/**
	 * Ensures that payments are created as `pending` in `record_signup()`.
	 * We complete it ourselves in `activate_subscription()`.
	 *
	 * @since 2.11
	 *
	 * @return false
	 */
	protected function should_auto_complete_payment() {
		return false;
	}

	/**
	 * Loads our script for the payment confirmation page.
	 *
	 * @since 2.11
	 */
	public function scripts() {
		if ( ! empty( $_GET['payment-confirmation'] ) && $this->id === $_GET['payment-confirmation'] ) {
			EDD\Gateways\PayPal\maybe_enqueue_polyfills();

			wp_enqueue_script(
				'edd-frontend-recurring-paypal',
				EDD_RECURRING_PLUGIN_URL . 'assets/js/edd-frontend-recurring-paypal.js',
				array(),
				EDD_RECURRING_VERSION
			);

			$timestamp = time();

			wp_localize_script( 'edd-frontend-recurring-paypal', 'eddRecurringPayPal', array(
				'ajaxurl'   => edd_get_ajax_url(),
				'nonce'     => wp_create_nonce( 'edd_recurring_confirm_paypal_transaction' ),
				'timestamp' => $timestamp,
				'token'     => \EDD\Utils\Tokenizer::tokenize( $timestamp ),
			) );
		}
	}

	/**
	 * Initial field validation before ever creating profiles or customers
	 *
	 * @since 2.11
	 */
	public function validate_fields( $data, $posted ) {
		if ( count( edd_get_cart_contents() ) > 1 && ! $this->can_purchase_multiple_subs() ) {
			edd_set_error( 'subscription_invalid', __( 'Only one subscription may be purchased through PayPal per checkout.', 'edd-recurring' ) );
		}

		if ( 'INR' === edd_get_currency() ) {
			edd_set_error( 'unsupported_currency', __( 'PayPal subscriptions cannot be made with Indian Rupees.', 'edd-recurring' ) );
		}
	}

	/**
	 * Sets the intent to `subscription`.
	 *
	 * @link  https://developer.paypal.com/docs/checkout/reference/customize-sdk/#intent
	 *
	 * @since 2.11
	 *
	 * @param array $args JS SDK query args.
	 *
	 * @return array
	 */
	public function set_sdk_intent( $args ) {
		if ( edd_recurring()->cart_contains_recurring() ) {
			$args['intent'] = 'subscription';
			$args['vault']  = 'true';
		}

		return $args;
	}

	/**
	 * Changes the approval action used for `onApprove`.
	 *
	 * @since 2.11
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	public function set_subscription_approval_action( $action ) {
		if ( edd_recurring()->cart_contains_recurring() ) {
			return 'edd_recurring_approve_paypal_subscription';
		}

		return $action;
	}

	/**
	 * Checkout error handler.
	 *
	 * PayPal Commerce uses AJAX for all checkout processing, so we always want to send errors
	 * back via JSON.
	 *
	 * @param array|false $errors
	 *
	 * @since 2.11
	 * @return void
	 */
	protected function handle_errors( $errors = array() ) {
		if ( ! is_array( $errors ) || empty( $errors ) ) {
			$errors = array(
				'paypal-error' => __( 'An unexpected error occurred.', 'edd-recurring' )
			);
		}

		wp_send_json_error( edd_build_errors_html( $errors ) );
	}

	/**
	 * Creates the subscription in PayPal.
	 *
	 * @since 2.11
	 */
	public function create_payment_profiles() {
		edd_debug_log( 'PayPal Recurring - create_payment_profiles()' );

		if ( ! \EDD\Gateways\PayPal\has_rest_api_connection() ) {
			edd_record_gateway_error(
				__( 'PayPal Gateway Error', 'edd-recurring' ),
				__( 'Missing PayPal Commerce credentials.', 'edd-recurring' )
			);

			$error_message = current_user_can( 'manage_options' )
				? __( 'Please connect your PayPal account in the gateway settings.', 'edd-recurring' )
				: __( 'Unexpected authentication error. Please contact a site administrator.', 'edd-recurring' );

			$this->handle_errors( array(
				'paypal-error' => $error_message
			) );
		}

		try {
			try {
				foreach ( $this->subscriptions as $key => $subscription ) {

					if ( empty( $subscription['has_trial'] ) ) {
						$cart_total         = edd_format_amount( EDD()->cart->total );
						$sub_initial_amount = edd_format_amount( $subscription['initial_amount'] );

						if ( $sub_initial_amount !== $cart_total ) {
							$message = sprintf( 'The cart total %s does not match the subscription amount %s.', edd_currency_filter( edd_sanitize_amount( $cart_total ) ), edd_currency_filter( edd_sanitize_amount( $subscription['initial_amount'] ) ) );
							$this->handle_errors( array(
								'paypal-error' => $message,
							) );
							throw new Gateway_Exception(
								__( 'The cart total does not match the subscription amount.', 'edd-recurring' ),
								403,
								$message
							);
						}
					}
					edd_debug_log( sprintf( 'PayPal Recurring - Processing subscription for download #%d', $subscription['id'] ) );

					$this->paypal_product_id = $this->get_or_create_paypal_product( $subscription['id'] );

					edd_debug_log( sprintf( 'PayPal Recurring - Found product ID %s', $this->paypal_product_id ) );

					$plan_id = $this->get_or_create_paypal_plan( $this->paypal_product_id, $subscription );

					edd_debug_log( sprintf( 'PayPal Recurring - Found plan ID %s', $plan_id ) );

					$subscription_id = $this->create_paypal_subscription( $plan_id, $subscription );

					edd_debug_log( sprintf( 'PayPal Recurring - Created subscription ID %s', $subscription_id ) );

					$this->subscriptions[ $key ]['profile_id'] = $subscription_id;
					$this->subscriptions[ $key ]['status']     = 'pending';

					// We can only do one.
					break;
				}
			} catch ( \EDD\Gateways\PayPal\Exceptions\Authentication_Exception $e ) {
				throw new Gateway_Exception( __( 'An authentication error occurred. Please try again.', 'edd-recurring' ), $e->getCode(), $e->getMessage() );
			} catch ( API_Exception $e ) {
				throw new Gateway_Exception( __( 'An error occurred while communicating with PayPal. Please try again.', 'edd-recurring' ), $e->getCode(), $e->getMessage() );
			}
		} catch ( Gateway_Exception $e ) {
			$e->record_gateway_error();

			$this->handle_errors( edd_build_errors_html( array(
				'paypal-error' => $e->getMessage()
			) ) );
		}
	}

	/**
	 * Send JSON success or error at the end of the signup process.
	 *
	 * @since 2.11
	 */
	public function complete_signup() {
		foreach ( $this->subscriptions as $subscription ) {
			if ( ! empty( $subscription['profile_id'] ) ) {
			    $timestamp = time();
				wp_send_json_success( array(
					'paypal_order_id' => $subscription['profile_id'],
					'edd_order_id'    => $this->payment_id,
					'nonce'           => wp_create_nonce( 'edd_process_paypal' ),
					'timestamp'       => $timestamp,
					'token'           => \EDD\Utils\Tokenizer::tokenize( $timestamp ),
				) );
			}
		}

		wp_send_json_error();
	}

	/**
	 * Determines whether or not a product exists.
	 *
	 * This is run for cached products in `get_or_create_paypal_product()`. This helps confirm
	 * that the saved product ID was created with the same credentials currently in use.
	 *
	 * @since 2.11
	 *
	 * @param string $product_id PayPal product ID.
	 *
	 * @return bool
	 */
	protected function paypal_product_exists( $product_id ) {
		try {
			$api = new API();
			$api->make_request( 'v1/catalogs/products/' . urlencode( $product_id ), array(), array(), 'GET' );

			return 200 === $api->last_response_code;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Retrieves the PayPal product ID for a given EDD product.
	 * If no product ID has been saved, a new one is created and saved.
	 *
	 * @since 2.11
	 *
	 * @param int $download_id EDD product ID.
	 *
	 * @return string ID of the PayPal product.
	 * @throws API_Exception
	 * @throws Gateway_Exception
	 */
	protected function get_or_create_paypal_product( $download_id ) {
		$download = new EDD_Download( $download_id );

		if ( empty( $download->ID ) ) {
			throw new Gateway_Exception(
				__( 'An unexpected error has occurred. Please try again.', 'edd-recurring' ),
				500,
				sprintf( 'Error while retrieving EDD_Download for ID %s', $download_id )
			);
		}

		$meta_key = '_paypal_product_id';
		if ( edd_is_test_mode() ) {
			$meta_key .= '_sandbox';
		}

		$paypal_product_id = get_post_meta( $download->ID, $meta_key, true );
		if ( ! empty( $paypal_product_id ) && $this->paypal_product_exists( $paypal_product_id ) ) {
			return $paypal_product_id;
		}

		$product_args = array(
			'name'     => substr( html_entity_decode( $download->get_name() ), 0, 127 ),
			'type'     => 'DIGITAL',
			/*
			 * This is commented out for now because PayPal seems to have odd/strict standards when
			 * validating these and will reject the entire API request for some local URLs. Instead
			 * of breaking local testing, we're omitting this parameter until we can figure out
			 * a possible way to pre-validate it ourselves.
			 */
			//'home_url' => get_permalink( $download->ID )
		);

		/**
		 * Filters the product arguments sent to PayPal.
		 *
		 * @since 2.11
		 *
		 * @param array        $product_args API arguments to send to PayPal.
		 * @param EDD_Download $download     Product object.
		 */
		$product_args = apply_filters( 'edd_recurring_paypal_product_args', $product_args, $download );

		$api      = new API();
		$response = $api->make_request( 'v1/catalogs/products', $product_args );

		if ( 201 !== $api->last_response_code ) {
			throw new API_Exception( sprintf(
				'Unexpected response code from PayPal: %d. Response: %s',
				$api->last_response_code,
				json_encode( $response )
			) );
		}

		if ( empty( $response->id ) ) {
			throw new API_Exception( sprintf(
				'PayPal product creation response missing product ID. Response: %s',
				json_encode( $response )
			) );
		}

		update_post_meta( $download->ID, $meta_key, sanitize_text_field( $response->id ) );

		return $response->id;
	}

	/**
	 * Determines whether or not a plan exists.
	 *
	 * This is run for cached plans in `get_or_create_paypal_plan()`. This helps confirm
	 * that the saved plan ID was created with the same credentials currently in use.
	 *
	 * @since 2.11
	 *
	 * @param string $plan_id
	 *
	 * @return bool
	 */
	protected function paypal_plan_exists( $plan_id ) {
		try {
			$api      = new API();
			$response = $api->make_request( 'v1/billing/plans/' . urlencode( $plan_id ), array(), array(), 'GET' );

			if ( ! empty( $response->payment_preferences->auto_bill_outstanding ) ) {
				edd_debug_log( sprintf( 'PayPal Recurring - Updating existing plan. Plan string: %s', $plan_id ) );
				$this->update_existing_paypal_plan( $plan_id );
			}

			return 200 === $api->last_response_code;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Updates an existing PayPal plan to set the payment failure threshold to 2 and disable auto-billing outstanding balances.
	 *
	 * @since 2.11.10
	 * @param string $plan_id
	 * @return bool
	 */
	protected function update_existing_paypal_plan( $plan_id ) {
		try {
			$api = new API();
			$api->make_request(
				'v1/billing/plans/' . urlencode( $plan_id ),
				array(
					array(
						'op'    => 'replace',
						'path'  => '/payment_preferences/payment_failure_threshold',
						'value' => 2,
					),
					array(
						'op'    => 'replace',
						'path'  => '/payment_preferences/auto_bill_outstanding',
						'value' => false,
					),
				),
				array(),
				'PATCH'
			);

			return 200 === $api->last_response_code;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Returns the ID of a PayPal plan that matches the provided
	 * subscription details. If a plan exists in our map, that ID
	 * is returned. Otherwise, a new plan is created.
	 *
	 * @since 2.11
	 *
	 * @param string $product_id   PayPal product ID.
	 * @param array  $subscription Subscription details.
	 *
	 * @return string PayPal plan ID.
	 * @throws API_Exception
	 */
	protected function get_or_create_paypal_plan( $product_id, $subscription ) {
		$plan_string    = $this->get_plan_string( $product_id, $subscription );
		$existing_plans = get_option( 'edd_paypal_plans', array() );

		if ( ! empty( $existing_plans ) ) {
			$existing_plans = json_decode( $existing_plans, true );
		} else {
			$existing_plans = array();
		}

		if ( is_array( $existing_plans ) && ! empty( $existing_plans[ $plan_string ] ) && $this->paypal_plan_exists( $existing_plans[ $plan_string ] ) ) {
			edd_debug_log( sprintf( 'PayPal Recurring - Using existing plan. Plan string: %s', $plan_string ) );

			return $existing_plans[ $plan_string ];
		}

		edd_debug_log( sprintf( 'PayPal Recurring - Creating new PayPal plan. Plan string: %s', $plan_string ) );

		$args = self::build_plan_api_args( $product_id, $subscription );

		$api      = new API();
		$response = $api->make_request( 'v1/billing/plans', $args );

		if ( 201 !== $api->last_response_code ) {
			throw new API_Exception( sprintf(
				'Unexpected HTTP response code: %d; Response: %s',
				$api->last_response_code,
				json_encode( $response )
			) );
		}

		if ( empty( $response->id ) ) {
			throw new API_Exception( sprintf( 'Missing plan ID from PayPal response. Response: %s', json_encode( $response ) ) );
		}

		$existing_plans[ $plan_string ] = sanitize_text_field( $response->id );
		update_option( 'edd_paypal_plans', json_encode( $existing_plans ) );

		return $response->id;
	}

	/**
	 * Gets a unique string that determines the ID for a plan.
	 *
	 * @since 2.11.2
	 * @param string $product_id   PayPal product ID.
	 * @param array  $subscription Subscription details.
	 *
	 * @return string The unique plan string.
	 */
	private function get_plan_string( $product_id, $subscription ) {

		$currency    = edd_get_currency();
		$plan_string = sprintf(
			'%1$s-%2$s-%3$s-%4$s-%5$s-%6$s-%7$s-currency-%8$s',
			$product_id,
			( isset( $subscription['price_id'] ) && is_numeric( $subscription['price_id'] ) ? intval( $subscription['price_id'] ) : 'none' ),
			$subscription['initial_amount'],
			$subscription['recurring_amount'],
			$subscription['period'],
			$subscription['frequency'],
			$subscription['bill_times'],
			$currency
		);

		if ( ! empty( $subscription['has_trial'] ) ) {
			$plan_string .= sprintf(
				'-trial-%1$s-%2$s',
				$subscription['trial_unit'],
				$subscription['trial_quantity']
			);
		}

		if ( ! empty( $subscription['recurring_tax_rate'] ) && (float) $subscription['recurring_tax_rate'] > 0 ) {
			$plan_string .= sprintf(
				'-taxrate-%s',
				$subscription['recurring_tax_rate']
			);
		}

		if ( edd_is_test_mode() ) {
			$plan_string .= '-sandbox';
		}

		/**
		 * Developers can filter the unique plan string.
		 *
		 * @since 2.11.2
		 * @param string $plan_string  The unique plan string.
		 * @param string $product_id   PayPal product ID.
		 * @param array  $subscription Subscription details.
		 */
		return sanitize_key( apply_filters( 'edd_recurring_paypal_plan_string', $plan_string, $product_id, $subscription ) );
	}

	/**
	 * Creates a new subscription in PayPal.
	 *
	 * @since 2.11
	 *
	 * @param string $plan_id      PayPal plan ID.
	 * @param array  $subscription Subscription details.
	 *
	 * @return string
	 * @throws API_Exception
	 */
	protected function create_paypal_subscription( $plan_id,  $subscription = array() ) {
		$subscription_args = array(
			'plan_id'             => $plan_id,
			'custom_id'           => $this->purchase_data['purchase_key'],
			'application_context' => array(
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'SUBSCRIBE_NOW',
				/*
				 * These are commented out for now because PayPal seems to have odd/strict standards when
				 * validating these and will reject the entire API request for some local URLs. Instead
				 * of breaking local testing, we're omitting these parameters until we can figure out
				 * a possible way to pre-validate the values ourselves.
				 */
				//'return_url'          => edd_get_success_page_uri(),
				//'cancel_url'          => edd_get_failed_transaction_uri(),
			)
		);

		/**
		 * Filters the subscription arguments.
		 * This is a generic filter that runs for _all_ gateways. The next filter is
		 * PayPal Commerce only.
		 *
		 * @since 2.11.2
		 *
		 * @param array                         $subscription_args API arguments.
		 * @param array                         $downloads         Downloads being purchased in this whole order.
		 * @param string                        $id                Gateway ID (slug).
		 * @param int                           $download_id       ID of the download for this subscription.
		 * @param int|false                     $price_id          Price ID being purchased.
		 * @param array                         $subscription      All subscription data.
		 * @param EDD_Recurring_PayPal_Commerce $this              Gateway object.
		 */
		$subscription_args = apply_filters(
			'edd_recurring_create_subscription_args',
			$subscription_args,
			$this->purchase_data['downloads'],
			$this->id,
			$subscription['id'],
			$subscription['price_id'],
			$subscription,
			$this
		);

		/**
		 * Filters the subscription arguments sent to PayPal.
		 *
		 * @since 2.11
		 *
		 * @param array                         $subscription_args API arguments.
		 * @param EDD_Recurring_PayPal_Commerce $this              Gateway object.
		 */
		$subscription_args = apply_filters( 'edd_recurring_paypal_subscription_args', $subscription_args, $this );

		$api      = new API();
		$response = $api->make_request( 'v1/billing/subscriptions', $subscription_args );

		if ( 201 !== $api->last_response_code ) {
			throw new API_Exception( sprintf(
				'Unexpected HTTP response code: %d; Response: %s',
				$api->last_response_code,
				json_encode( $response )
			) );
		}

		if ( empty( $response->id ) ) {
			throw new API_Exception( sprintf( 'Missing subscription ID from response. Response: %s', json_encode( $response ) ) );
		}

		return $response->id;
	}

	/**
	 * Builds the arguments for creating a PayPal plan.
	 *
	 * @since 2.11
	 *
	 * @param string $product_id   PayPal product ID.
	 * @param array  $subscription Subscription details.
	 *
	 * @return array
	 */
	public static function build_plan_api_args( $product_id, $subscription ) {
		$product_name = $subscription['name'];
		if ( edd_has_variable_prices( $subscription['id'] ) && isset( $subscription['price_id'] ) && false !== $subscription['price_id'] ) {
			$product_name .= ' - ' . edd_get_price_option_name( $subscription['id'], $subscription['price_id'] );
		}

		$args = array(
			'product_id'          => $product_id,
			'name'                => substr( html_entity_decode( $product_name ), 0, 127 ),
			'status'              => 'ACTIVE',
			'payment_preferences' => array(
				'auto_bill_outstanding'     => false,
				'setup_fee_failure_action'  => 'CANCEL',
				'payment_failure_threshold' => 2,
			),
		);

		/*
		 * Build billing cycles.
		 */
		$billing_cycles   = array();
		$current_sequence = 1;

		// First add a trial if there is one.
		if ( ! empty( $subscription['has_trial'] ) && isset( $subscription['trial_unit'] ) && isset( $subscription['trial_quantity'] ) ) {
			$billing_cycles[] = array(
				'frequency'   => self::subscription_frequency_to_paypal_args( $subscription['trial_unit'], $subscription['trial_quantity'] ),
				'tenure_type' => 'TRIAL',
				'sequence'    => $current_sequence
			);

			$current_sequence ++;
		}

		// Now add the main subscription.
		if ( empty( $subscription['has_trial'] ) && $subscription['initial_amount'] !== $subscription['recurring_amount'] ) {
			$billing_cycles[] = array(
				'frequency'      => self::subscription_frequency_to_paypal_args( $subscription['period'], $subscription['frequency'] ),
				'tenure_type'    => 'TRIAL',
				'sequence'       => $current_sequence,
				'pricing_scheme' => array(
					'fixed_price' => array(
						'currency_code' => strtoupper( edd_get_currency() ),
						'value'         => (string) $subscription['initial_amount']
					)
				),
				'total_cycles'   => 1
			);

			$current_sequence ++;
		}

		$billing_cycles[] = array(
			'frequency'      => self::subscription_frequency_to_paypal_args( $subscription['period'], $subscription['frequency'] ),
			'tenure_type'    => 'REGULAR',
			'sequence'       => $current_sequence,
			'pricing_scheme' => array(
				'fixed_price' => array(
					'currency_code' => strtoupper( edd_get_currency() ),
					'value'         => (string) $subscription['recurring_amount']
				)
			),
			'total_cycles'   => ! empty( $subscription['bill_times'] ) ? intval( $subscription['bill_times'] ) : 0
		);

		$args['billing_cycles'] = $billing_cycles;

		/*
		 * Add tax rate. We only send PayPal the percentage, inclusive.
		 */
		if ( ! empty( $subscription['recurring_tax_rate'] ) && (float) $subscription['recurring_tax_rate'] > 0 ) {
			$args['taxes'] = array(
				'percentage' => (string) ( $subscription['recurring_tax_rate'] * 100 ),
				'inclusive'  => true
			);
		}

		/**
		 * Filters the arguments used to create a plan.
		 *
		 * @since 2.11
		 *
		 * @param array  $args         API arguments.
		 * @param string $product_id   PayPal product ID.
		 * @param array  $subscription Subscription arguments.
		 */
		return apply_filters( 'edd_recurring_paypal_plan_args', $args, $product_id, $subscription );
	}

	/**
	 * Converts EDD subscription frequency settings to arguments PayPal will accept.
	 *
	 * @link  https://developer.paypal.com/docs/api/subscriptions/v1/#definition-frequency
	 *
	 * @since 2.11
	 *
	 * @param string $unit     Billing cycle unit.
	 * @param int    $quantity Billing cycle quantity.
	 *
	 * @return array
	 */
	public static function subscription_frequency_to_paypal_args( $unit, $quantity ) {
		$new_unit     = $unit;
		$new_quantity = $quantity;

		switch ( $unit ) {
			case 'quarter' :
				$new_unit     = 'month';
				$new_quantity = $quantity * 3;
				break;
			case 'semi-year' :
				$new_unit     = 'month';
				$new_quantity = $quantity * 6;
				break;
		}

		return array(
			'interval_unit'  => strtoupper( $new_unit ),
			'interval_count' => intval( $new_quantity )
		);
	}

	/**
	 * Returns a list of our webhooke vents and associated class handlers.
	 *
	 * @since 2.11
	 * @return string[]
	 */
	private function get_webhook_events() {
		return array(
			'BILLING.SUBSCRIPTION.ACTIVATED'      => '\\EDD_Recurring\\Gateways\\PayPal\\Billing_Subscription_Activated',
			'BILLING.SUBSCRIPTION.EXPIRED'        => '\\EDD_Recurring\\Gateways\\PayPal\\Billing_Subscription_Expired',
			'BILLING.SUBSCRIPTION.CANCELLED'      => '\\EDD_Recurring\\Gateways\\PayPal\\Billing_Subscription_Cancelled',
			'BILLING.SUBSCRIPTION.SUSPENDED'      => '\\EDD_Recurring\\Gateways\\PayPal\\Billing_Subscription_Suspended',
			'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => '\\EDD_Recurring\\Gateways\\PayPal\\Billing_Subscription_Payment_Failed',
			'PAYMENT.SALE.COMPLETED'              => '\\EDD_Recurring\\Gateways\\PayPal\\Payment_Sale_Completed',
			'PAYMENT.SALE.REFUNDED'               => '\\EDD_Recurring\\Gateways\\PayPal\\Payment_Sale_Refunded',
		);
	}

	/**
	 * Adds subscription events to the PayPal webhook.
	 *
	 * @param string[] $events Registered events.
	 * @param string   $mode   PayPal API mode.
	 *
	 * @since 2.11
	 * @return string[]
	 */
	public function webhook_events( $events, $mode ) {
		return array_merge( $events, $this->get_webhook_events() );
	}

	/**
	 * Determines if a subscription can be cancelled.
	 *
	 * @since 2.11
	 *
	 * @param bool             $ret
	 * @param EDD_Subscription $subscription
	 *
	 * @return bool
	 */
	public function can_cancel( $ret, $subscription ) {
		if ( $this->id === $subscription->gateway && ! empty( $subscription->profile_id ) && in_array( $subscription->status, $this->get_cancellable_statuses() ) && \EDD\Gateways\PayPal\has_rest_api_connection() ) {
			return true;
		}

		return $ret;
	}

	/**
	 * Cancels a subscription in PayPal.
	 *
	 * @since 2.11
	 *
	 * @param EDD_Subscription $subscription
	 * @param bool             $valid
	 *
	 * @return bool
	 */
	public function cancel( $subscription, $valid ) {
		$details = $this->get_subscription_details( $subscription );
		if ( ! empty( $details['status'] ) && 'cancelled' === strtolower( $details['status'] ) ) {
			return true;
		}

		try {
			$api = new API();

			$api->make_request( sprintf( 'v1/billing/subscriptions/' . urlencode( $subscription->profile_id ) . '/cancel' ), array(
				'reason' => esc_html__( 'Customer requested cancellation.', 'edd-recurring' )
			) );

			if ( 204 !== $api->last_response_code ) {
				throw new API_Exception( sprintf( 'Unexpected HTTP response code: %d', $api->last_response_code ) );
			}

			return true;
		} catch ( \Exception $e ) {
			$subscription->add_note( sprintf(
				__( 'Failed to cancel subscription in PayPal. Message: %s', 'edd-recurring' ),
				esc_html( $e->getMessage() )
			) );

			return false;
		}
	}

	/**
	 * Retrieves the subscription's expiration date from PayPal.
	 *
	 * @since 2.11
	 *
	 * @param EDD_Subscription $subscription
	 *
	 * @return string|WP_Error
	 */
	public function get_expiration( $subscription ) {
		$details = $this->get_subscription_details( $subscription );

		return ! empty( $details['expiration'] ) ? $details['expiration'] : $details['error'];
	}

	/**
	 * Retrieves the subscription details from PayPal directly.
	 *
	 * @since 2.11
	 *
	 * @param EDD_Subscription $subscription
	 *
	 * @return string[]
	 */
	public function get_subscription_details( EDD_Subscription $subscription ) {
		$details = array(
			'status'     => '',
			'expiration' => '',
			'error'      => ''
		);

		try {
			if ( empty( $subscription->profile_id ) ) {
				throw new \Exception( __( 'Missing profile ID.', 'edd-recurring' ) );
			}

			$api      = new API();
			$response = $api->make_request( 'v1/billing/subscriptions/' . urlencode( $subscription->profile_id ), array(), array(), 'GET' );

			if ( 200 !== $api->last_response_code ) {
				throw new API_Exception( sprintf(
				/* Translators: %d - the HTTP response code */
					__( 'Unexpected HTTP response code: %d.', 'edd-recurring' ),
					$api->last_response_code
				) );
			}

			if ( empty( $response->id ) ) {
				throw new API_Exception( sprintf(
				/* Translators: %s - response from PayPal */
					__( 'PayPal response missing subscription ID. Response: %s', 'edd-recurring' ),
					json_encode( $response )
				) );
			}

			$details['status']     = strtolower( $response->status );
			$details['expiration'] = isset( $response->billing_info->next_billing_time ) ? date( 'Y-m-d H:i:s', strtotime( $response->billing_info->next_billing_time ) ) : '';

			// Let's add all the other details too.
			$response = (array) json_decode( json_encode( $response ), true );
			$details  = wp_parse_args( $details, $response );
		} catch ( \EDD\Gateways\PayPal\Exceptions\Authentication_Exception $e ) {
			$details['error'] = new WP_Error( 'authentication_exception', __( 'An authentication exception occurred.', 'edd-recurring' ) );
		} catch ( API_Exception $e ) {
			$details['error'] = new WP_Error( 'api_exception', sprintf(
			/* Translators: %d - HTTP response code; %s - Error message; %s */
				__( 'An error occurred in the response from PayPal. HTTP code: %d; Message: %s', 'edd-recurring' ),
				( isset( $api ) ? $api->last_response_code : 0 ),
				$e->getMessage()
			) );
		} catch ( \Exception $e ) {
			$details['error'] = new WP_Error( 'exception', sprintf(
			/* Translators: %s - error message */
				__( 'An unexpected error occurred. Message: %s', 'edd-recurring' ),
				$e->getMessage()
			) );
		}

		return $details;
	}

	/**
	 * Links the subscription ID to the corresponding PayPal page.
	 *
	 * @link  https://sandbox.paypal.com/billing/subscriptions/
	 *
	 * @since 2.11
	 *
	 * @param string           $profile_id
	 * @param EDD_Subscription $subscription
	 *
	 * @return string
	 */
	public function link_profile_id( $profile_id, $subscription ) {
		if ( empty( $profile_id ) ) {
			return $profile_id;
		}

		$subdomain = edd_is_test_mode() ? 'sandbox.' : '';
		$payment   = edd_get_payment( $subscription->parent_payment_id );
		if ( ! empty( $payment ) ) {
			$subdomain = ( empty( $payment->mode ) || 'live' === $payment->mode ) ? '' : 'sandbox.';
		}

		$url = 'https://' . $subdomain . 'paypal.com/billing/subscriptions/' . urlencode( $profile_id );

		return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $profile_id ) . '</a>';
	}

	/**
	 * Activates the EDD subscription at the end of the checkout process.
	 *
	 * PayPal has actually already activated the subscription for us, so this step is for us to
	 * confirm that PayPal did, then update EDD records accordingly.
	 *
	 * @since 2.11
	 */
	public function activate_subscription() {
		edd_debug_log( 'PayPal Recurring - activate_subscription()' );

		/*
		 * Note: at this point, PayPal has activated the subscription, so all error messages
		 * should imply that payment has succeeded and recommend contacting the site owner.
		 */
		try {

			$token     = isset( $_POST['token'] )     ? sanitize_text_field( $_POST['token'] )     : '';
			$timestamp = isset( $_POST['timestamp'] ) ? sanitize_text_field( $_POST['timestamp'] ) : '';

			if ( ! empty( $timestamp ) && ! empty( $token ) ) {
				if ( !\EDD\Utils\Tokenizer::is_token_valid( $token, $timestamp ) ) {
					throw new Gateway_Exception(
						__('A validation error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring'),
						403,
						'Token validation failed - activate_subscription()'
					);
				}
			} elseif ( empty( $token ) && ! empty( $_POST['edd_process_paypal_nonce'] ) ) {
				if ( ! wp_verify_nonce( $_POST['edd_process_paypal_nonce'], 'edd_process_paypal' ) ) {
					throw new Gateway_Exception(
						__( 'A validation error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring' ),
						403,
						'Nonce validation failed - activate_subscription()'
					);
				}
			} else {
				throw new Gateway_Exception(
					__( 'A validation error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring' ),
					400,
					'Missing validation fields - activate_subscription()'
				);
			}

			$default_error_message = __( 'An unexpected error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring' );

			if ( empty( $_POST['paypal_subscription_id'] ) ) {
				throw new Gateway_Exception(
					$default_error_message,
					400,
					'Missing PayPal subscription ID during approval.'
				);
			}

			if ( ! empty( $_POST['paypal_order_id'] ) ) {
				edd_debug_log( sprintf( 'PayPal Recurring - PayPal order ID %s', esc_html( $_POST['paypal_order_id'] ) ) );
			}

			// Get the associated subscription in EDD.
			$subscription = new EDD_Subscription( sanitize_text_field( $_POST['paypal_subscription_id'] ), true );

			if ( empty( $subscription->id ) ) {
				throw new Gateway_Exception(
					$default_error_message,
					400,
					sprintf(
						'Unable to find EDD subscription with this PayPal ID: %s',
						sanitize_text_field( $_POST['paypal_subscription_id'] )
					)
				);
			}

			// Get the subscription details in PayPal... let's make sure it's active.
			$paypal_sub = $this->get_subscription_details( $subscription );
			if ( empty( $paypal_sub['status'] ) || 'active' !== strtolower( $paypal_sub['status'] ) ) {
				throw new Gateway_Exception(
					$default_error_message,
					400,
					sprintf(
						'Unexpected status in PayPal subscription. Data: %s',
						json_encode( $paypal_sub )
					)
				);
			}

			if ( 'pending' === $subscription->status ) {
				$new_status = 'active';
				if ( ! empty( $subscription->trial_period ) ) {
					$new_status = 'trialling';
					$subscriber = new EDD_Recurring_Subscriber( $subscription->customer_id );
					$subscriber->add_meta( 'edd_recurring_trials', $subscription->product_id );
				}
				edd_debug_log( sprintf( 'PayPal Recurring - Setting subscription to %s.', $new_status ) );
				$subscription->update( array(
					'status' => $new_status,
				) );
			} else {
				edd_debug_log( sprintf( 'PayPal Recurring - Subscription status is already %s', $subscription->status ) );
			}

			// If this was a trial, complete the payment.
			if ( ! empty( $subscription->trial_period ) ) {
				edd_update_payment_status( $subscription->parent_payment_id, 'publish' );
			}

			$redirect_url = edd_get_success_page_uri();
			if ( empty( $subscription->trial_period ) ) {
				$redirect_url = add_query_arg( array(
					'payment-confirmation' => 'paypal_commerce',
					'payment-id'           => urlencode( $subscription->parent_payment_id ),
					'subscription-id'      => urlencode( $subscription->id )
				), edd_get_success_page_uri() );
			}

			wp_send_json_success( array(
				'redirect_url' => $redirect_url
			) );
		} catch ( Gateway_Exception $e ) {
			$e->record_gateway_error();

			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Filters the content on the confirmation page.
	 *
	 * @since 2.11
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function payment_confirmation_page( $content ) {
		if ( empty( $_GET['subscription-id'] ) && ! edd_get_purchase_session() ) {
			return $content;
		}

		$subscription_id = ! empty( $_GET['subscription-id'] ) ? absint( $_GET['subscription-id'] ) : false;
		$payment_id      = ! empty( $_GET['payment-id'] ) ? absint( $_GET['payment-id'] ) : false;
		if ( ! $payment_id ) {
			$session    = edd_get_purchase_session();
			$payment_id = edd_get_purchase_id_by_key( $session['purchase_key'] );
		}

		$payment = $payment_id ? edd_get_payment( $payment_id ) : false;
		if ( empty( $payment ) ) {
			return $content;
		}

		$subscription = new EDD_Subscription( $subscription_id );
		if ( empty( $subscription->id ) ) {
			return $content;
		}

		if ( 'pending' === $payment->status ) {
			ob_start();

			edd_get_template_part( 'paypal-commerce', 'processing' );

			return ob_get_clean();
		}

		return $content;
	}

	/**
	 * Confirms the initial payment in the subscription.
	 *
	 * @since 2.11
	 */
	public function confirm_transaction() {
		edd_debug_log( 'PayPal Recurring - confirm_transaction()' );

		$response = array(
			'redirect_url' => edd_get_success_page_uri()
		);

		try {

			$token     = isset( $_POST['token'] )     ? sanitize_text_field( $_POST['token'] )     : '';
			$timestamp = isset( $_POST['timestamp'] ) ? sanitize_text_field( $_POST['timestamp'] ) : '';

			if ( ! empty( $timestamp ) && ! empty( $token ) ) {
				if ( !\EDD\Utils\Tokenizer::is_token_valid( $token, $timestamp ) ) {
					throw new Gateway_Exception(
						__('A validation error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring'),
						403,
						'Token validation failed - confirm_transaction()'
					);
				}
			} elseif ( empty( $token ) && ! empty( $_POST['nonce'] ) ) {
				if ( ! wp_verify_nonce( $_POST['nonce'], 'edd_recurring_confirm_paypal_transaction' ) ) {
					throw new Gateway_Exception(
						__( 'A validation error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring' ),
						403,
						'Nonce validation failed - confirm_transaction()'
					);
				}
			} else {
				throw new Gateway_Exception(
					__( 'A validation error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring' ),
					400,
					'Missing validation fields - confirm_transaction()'
				);
			}

			$default_error_message = __( 'An unexpected error occurred, but your payment may have gone through. Please contact the site administrator.', 'edd-recurring' );

			if ( empty( $_POST['subscription_id'] ) ) {
				throw new Gateway_Exception(
					$default_error_message,
					400,
					'Missing EDD subscription ID during transaction confirmation.'
				);
			}

			$subscription = new EDD_Subscription( intval( $_POST['subscription_id'] ) );
			if ( empty( $subscription->id ) ) {
				throw new Gateway_Exception(
					$default_error_message,
					400,
					sprintf( 'Failed to retrieve EDD subscription via ID: %s', sanitize_text_field( $_POST['subscription_id'] ) )
				);
			}

			$attempt_number = ! empty( $_POST['attempt_number'] ) ? absint( $_POST['attempt_number'] ) : 1;

			edd_debug_log( sprintf( 'PayPal Recurring - Attempt #%d to verify that initial payment for subscription #%d was successful.', $attempt_number, $subscription->id ) );

			// Now let's make sure we have a valid payment.
			try {
				$payment = edd_get_payment( $subscription->parent_payment_id );
				if ( empty( $payment ) ) {
					throw new \Exception( sprintf(
						'Unable to locate payment ID %d',
						$subscription->parent_payment_id
					) );
				}

				if ( in_array( $payment->status, array( 'complete', 'publish' ) ) ) {
					wp_send_json_success( $response );
				}

				$transactions = $this->get_subscription_transactions( $subscription );
				if ( empty( $transactions ) || ! is_array( $transactions ) ) {
					throw new \Exception( 'No transactions found.' );
				}

				edd_empty_cart();

				foreach ( $transactions as $transaction ) {
					if ( $this->is_transaction_valid_for_payment( $transaction, $payment ) ) {
						$payment->transaction_id = $transaction->id;
						$payment->status         = 'complete';
						$payment->save();

						wp_send_json_success( $response );
					} else {
						edd_debug_log( sprintf(
							'PayPal Recurring - Invalid transaction: %s',
							json_encode( $transaction )
						) );

						wp_send_json_error( $response );
					}
				}

			} catch ( \Exception $e ) {
				/*
				 * If we're here, that means we were unable to confirm that the first payment
				 * was made. Hopefully the webhook will pick it up for us.
				 */
				edd_debug_log( sprintf(
					'PayPal Recurring - Exception while verifying initial payment status for EDD subscription %d. PayPal ID: %s; Message: %s',
					$subscription->id,
					$subscription->profile_id,
					esc_html( $e->getMessage() )
				) );

				if ( $attempt_number < 5 ) {
					wp_send_json_error( array(
						'retry'        => true,
						'milliseconds' => $attempt_number * 2000
					) );
				} else {
					wp_send_json_error( array_merge( $response, array(
						'retry'         => false,
						'error_message' => __( 'Exceeded maximum attempts.', 'edd-recurring' ),
					) ) );
				}
			}
		} catch ( Gateway_Exception $e ) {
			$e->record_gateway_error();

			wp_send_json_error( array_merge( $response, array(
				'retry'         => false,
				'error_message' => $e->getMessage()
			) ) );
		}
	}

	/**
	 * Retrieves a list of transactions for a given subscription.
	 *
	 * @since 2.11
	 *
	 * @param EDD_Subscription $subscription Subscription to retrieve transactions for.
	 * @param string           $start        The start time of the range of transactions to list.
	 *                                       Accepts any format that can be parsed by `strtotime()`.
	 * @param string           $end          The end time of the range of transactions to list.
	 *                                       Accepts any format that can be parsed by `strtotime()`.
	 *
	 * @return object[]
	 * @throws API_Exception
	 */
	public function get_subscription_transactions( EDD_Subscription $subscription, $start = '', $end = '' ) {
		$start = ! empty( $start ) ? strtotime( $start ) : strtotime( '-1 day' );
		$end   = ! empty( $end ) ? strtotime( $end ) : time();

		$start = date( 'Y-m-d\TH:i:s\Z', $start );
		$end   = date( 'Y-m-d\TH:i:s\Z', $end );

		$api      = new API();
		$endpoint = add_query_arg( array(
			'start_time' => urlencode( $start ),
			'end_time'   => urlencode( $end )
		), 'v1/billing/subscriptions/' . urlencode( $subscription->profile_id ) . '/transactions' );
		$response = $api->make_request( $endpoint, array(), array(), 'GET' );

		if ( 200 !== $api->last_response_code ) {
			throw new API_Exception( sprintf(
				'Invalid HTTP response code: %d. Response: %s',
				$api->last_response_code,
				json_encode( $response )
			) );
		}

		return ! empty( $response->transactions ) ? $response->transactions : array();
	}

	/**
	 * Determines whether or not a given PayPal transaction is a valid match for a given EDD Payment.
	 * This validates that the PayPal transaction is COMPLETED, has the correct amount, and the
	 * correct currency.
	 *
	 * @since 2.11
	 *
	 * @param object      $transaction
	 * @param EDD_Payment $payment
	 *
	 * @return bool
	 */
	public function is_transaction_valid_for_payment( $transaction, \EDD_Payment $payment ) {
		if ( empty( $transaction->id ) || empty( $transaction->status ) || 'COMPLETED' !== strtoupper( $transaction->status ) ) {
			edd_debug_log( 'PayPal Recurring - Missing transaction ID or status.' );

			return false;
		}

		// Verify amount and currency.
		if ( ! isset( $transaction->amount_with_breakdown->gross_amount->value ) ) {
			edd_debug_log( 'PayPal Recurring - Gross amount value not set on transaction.' );

			return false;
		}

		if ( (float) $transaction->amount_with_breakdown->gross_amount->value < (float) $payment->total ) {
			edd_debug_log( sprintf(
				'PayPal Recurring - Transaction amount (%s) is less than payment amount (%s).',
				(float) $transaction->amount_with_breakdown->gross_amount->value,
				$payment->total
			) );

			return false;
		}

		if ( strtoupper( $payment->currency ) !== strtoupper( $transaction->amount_with_breakdown->gross_amount->currency_code ) ) {
			edd_debug_log( sprintf(
				'PayPal Recurring - PayPal transaction currency (%s) doesn\'t match EDD payment currency (%s).',
				strtoupper( $transaction->amount_with_breakdown->gross_amount->currency_code ),
				strtoupper( $payment->currency )
			) );

			return false;
		}

		return true;
	}

	/**
	 * Returns a "note-ready" explanation of a capture status reason.
	 *
	 * @link  https://developer.paypal.com/docs/api/payments/v2/#definition-capture_status_details
	 *
	 * @since 2.11
	 *
	 * @param string $reason
	 *
	 * @return string
	 */
	public static function capture_status_to_note( $reason ) {
		switch ( strtoupper( $reason ) ) {
			case 'BUYER_COMPLAINT' :
				return __( 'The payer has initiated a dispute for this payment.', 'edd-recurring' );
			case 'CHARGEBACK' :
				return __( 'The captured funds were reversed in response to the payer disputing this payment.', 'edd-recurring' );
			case 'ECHECK' :
				return __( 'The payment was made using an eCheck that has not yet cleared.', 'edd-recurring' );
			case 'INTERNATIONAL_WITHDRAWAL' :
				return __( 'Visit your PayPal account to accept or deny this payment in your "Account Overview."', 'edd-recurring' );
			case 'PENDING_REVIEW' :
				return __( 'This payment is pending manual review.', 'edd-recurring' );
			case 'RECEIVING_PREFERENCE_MANDATES_MANUAL_ACTION' :
				return __( 'You have not yet set up appropriate receiving preferences for your account. Visit your PayPal account for more information.', 'edd-recurring' );
			case 'REFUNDED' :
				return __( 'The captured funds were refunded.', 'edd-recurring' );
			case 'TRANSACTION_APPROVED_AWAITING_FUNDING' :
				return __( 'Waiting for the payer to send the funds for this payment.', 'edd-recurring' );
			case 'UNILATERAL' :
				return __( 'You do not appear to have a PayPal account. Please contact PayPal for more information.', 'edd-recurring' );
			case 'VERIFICATION_REQUIRED' :
				return __( 'Your PayPal account has not yet been verified. Check your account or contact PayPal for more information.', 'edd-recurring' );
			default :
				return __( 'No reason has been provided. For more information about this payment, visit your PayPal account or contact PayPal directly.', 'edd-recurring' );
		}
	}
}

$edd_recurring_paypal_commerce = new EDD_Recurring_PayPal_Commerce();
