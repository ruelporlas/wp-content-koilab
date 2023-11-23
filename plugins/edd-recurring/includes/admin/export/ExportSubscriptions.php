<?php
/**
 * ExportSubscriptions.php
 *
 * @package   edd-recurring
 * @copyright Copyright (c) 2021, Easy Digital Downloads
 * @license   GPL2+
 * @since     3.0
 */

namespace EDD_Recurring\Admin\Export;

class ExportSubscriptions extends \EDD_Batch_Export {

	/**
	 * @var \EDD_Subscriptions_DB
	 */
	private $db;

	private $per_page = 30;

	/**
	 * @var string Export type - used for export-specific filters/actions.
	 */
	public $export_type = 'recurring_subscriptions';

	public function __construct( $_step = 1 ) {
		parent::__construct( $_step );

		$this->db = new \EDD_Subscriptions_DB();
	}

	/**
	 * CSV column headers.
	 *
	 * @since 3.0
	 *
	 * @return array
	 */
	public function csv_cols() {
		return array(
			'id'                 => __( 'Subscription ID', 'edd-recurring' ),
			'customer_id'        => __( 'Customer ID', 'edd-recurring' ),
			'customer_email'     => __( 'Customer Email', 'edd-recurring' ),
			'period'             => __( 'Billing Period', 'edd-recurring' ),
			'currency'           => __( 'Currency', 'edd-recurring' ),
			'initial_amount'     => __( 'Initial Amount', 'edd-recurring' ),
			'initial_tax_rate'   => __( 'Initial Tax Rate', 'edd-recurring' ),
			'initial_tax'        => __( 'Initial Tax Amount', 'edd-recurring' ),
			'recurring_amount'   => __( 'Recurring Amount', 'edd-recurring' ),
			'recurring_tax_rate' => __( 'Recurring Tax Rate', 'edd-recurring' ),
			'recurring_tax'      => __( 'Recurring Tax Amount', 'edd-recurring' ),
			'bill_times'         => __( 'Bill Times', 'edd-recurring' ),
			'transaction_id'     => __( 'Transaction ID', 'edd-recurring' ),
			'parent_payment_id'  => __( 'Order ID', 'edd-recurring' ),
			'product_id'         => __( 'Product ID', 'edd-recurring' ),
			'price_id'           => __( 'Product Price ID', 'edd-recurring' ),
			'created'            => __( 'Start Date', 'edd-recurring' ),
			'expiration'         => __( 'Expiration Date', 'edd-recurring' ),
			'trial_period'       => __( 'Trial Period', 'edd-recurring' ),
			'status'             => __( 'Status', 'edd-recurring' ),
			'profile_id'         => __( 'Gateway Profile ID', 'edd-recurring' ),
			'gateway'            => __( 'Gateway', 'edd-recurring' ),
		);
	}

	/**
	 * Returns the common query args for 1) retrieving subscriptions; and 2) calculating percentage.
	 *
	 * @since 3.0
	 *
	 * @return array
	 */
	private function subscription_query_args() {
		$args = array();

		if ( ! empty( $this->download ) && 'all' !== $this->download ) {
			$args['product_id'] = $this->download;
		}

		if ( ! empty( $this->status ) ) {
			$args['status'] = $this->status;
		}

		if ( ! empty( $this->start ) ) {
			$args['date']['start'] = $this->start;
		}
		if ( ! empty( $this->end ) ) {
			$args['date']['end'] = $this->end;
		}

		return $args;
	}

	/**
	 * Retrieves the data for this batch.
	 *
	 * @since 3.0
	 *
	 * @return array|false Array of data if we have some, or false if none found.
	 */
	public function get_data() {
		$subscriptions = $this->db->get_subscriptions( wp_parse_args( array(
			'number' => $this->per_page,
			'offset' => $this->per_page * ( $this->step - 1 )
		), $this->subscription_query_args() ) );

		if ( empty( $subscriptions ) ) {
			return false;
		}

		$data = array();

		foreach ( $subscriptions as $subscription ) {
			/**
			 * @var \EDD\Orders\Order|\EDD_Payment|false
			 */
			$payment = function_exists( 'edd_get_order' ) ? edd_get_order( $subscription->parent_payment_id ) : edd_get_payment( $subscription->parent_payment_id );

			$data[] = array(
				'id'                 => $subscription->id,
				'customer_id'        => $subscription->customer_id,
				'customer_email'     => $subscription->customer instanceof \EDD_Customer
					? $subscription->customer->email : '',
				'period'             => $subscription->period,
				'currency'           => isset( $payment->currency ) ? $payment->currency : '',
				'initial_amount'     => edd_sanitize_amount( $subscription->initial_amount ),
				'initial_tax_rate'   => $subscription->initial_tax_rate,
				'initial_tax'        => $subscription->initial_tax,
				'recurring_amount'   => edd_sanitize_amount( $subscription->recurring_amount ),
				'recurring_tax_rate' => $subscription->recurring_tax_rate,
				'recurring_tax'      => $subscription->recurring_tax,
				'bill_times'         => $subscription->bill_times,
				'transaction_id'     => $subscription->get_transaction_id(),
				'parent_payment_id'  => $subscription->parent_payment_id,
				'product_id'         => $subscription->product_id,
				'price_id'           => $subscription->price_id,
				'created'            => $subscription->created,
				'expiration'         => $subscription->expiration,
				'trial_period'       => $subscription->trial_period,
				'status'             => $subscription->get_status_label(),
				'profile_id'         => $subscription->profile_id,
				'gateway'            => edd_get_gateway_admin_label( $payment->gateway ),
			);
		}

		return $data;
	}

	/**
	 * Calculates the percentage complete.
	 *
	 * @since 3.0
	 *
	 * @return int|float
	 */
	public function get_percentage_complete() {
		$total      = $this->db->count( $this->subscription_query_args() );
		$percentage = 100;

		if ( $total > 0 ) {
			$percentage = ( ( $this->per_page * $this->step ) / $total ) * 100;
		}

		if ( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Sets filter properties we support.
	 *
	 * @since 3.0
	 *
	 * @param array $request
	 */
	public function set_properties( $request ) {
		$this->start    = isset( $request['start'] ) ? sanitize_text_field( $request['start'] ) : '';
		$this->end      = isset( $request['end'] ) ? sanitize_text_field( $request['end'] ) : '';
		$this->status   = isset( $request['status'] ) && array_key_exists( $request['status'], edd_recurring_get_subscription_statuses() )
			? $request['status']
			: false;
		$this->download = isset( $request['product_id'] ) ? intval( $request['product_id'] ) : null;
	}

}
