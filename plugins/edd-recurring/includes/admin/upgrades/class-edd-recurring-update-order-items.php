<?php
/**
 * Changes order items with the edd_subscription status to complete.
 *
 * @copyright   Copyright (c) 2022, Easy Digital Downloads
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.11.7
 */

class EDD_Recurring_Update_Order_Items extends EDD_Batch_Export {

	/**
		 * Our export type. Used for export-type specific filters/actions
		 * @var string
		 * @since 2.11.7
		 */
		public $export_type = '';

		/**
		 * Allows for a non-download batch processing to be run.
		 * @since  2.11.7
		 * @var boolean
		 */
		public $is_void = true;

		/**
		 * Sets the number of items to pull on each step
		 * @since  2.11.7
		 * @var integer
		 */
		public $per_step = 50;

		/**
		 * Get the Export Data
		 *
		 * @access public
		 * @since 2.11.7
		 * @return array $data The data for the CSV file
		 */
	public function get_data() {

		$step_items = $this->get_order_items();

		if ( ! is_array( $step_items ) ) {
			return false;
		}

		if ( empty( $step_items ) ) {
			return false;
		}

		foreach ( $step_items as $order_item ) {
			if ( 'pending' === $order_item->status ) {
				$order = edd_get_order( $order_item->order_id );
				if ( 'edd_subscription' !== $order->status ) {
					continue;
				}
			}
			edd_update_order_item(
				$order_item->id,
				array(
					'status' => 'complete',
				)
			);
		}

		return true;
	}

		/**
		 * Return the calculated completion percentage
		 *
		 * @since 2.11.7
		 * @return int
		 */
	public function get_percentage_complete() {

		$total      = get_transient( 'edd_recurring_order_item_counts' );
		$percentage = 100;

		if ( $total > 0 ) {
			$percentage = ( ( $this->step * $this->per_step ) / $total ) * 100;
		}

		if ( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

		/**
		 * Process a step
		 *
		 * @since 2.11.7
		 * @return bool
		 */
	public function process_step() {

		if ( ! $this->can_export() ) {
			wp_die(
				esc_html__( 'You do not have permission to run this upgrade.', 'edd-recurring' ),
				esc_html__( 'Error', 'edd-recurring' ),
				array( 'response' => 403 )
			);
		}

		$had_data = $this->get_data();

		if ( $had_data ) {
			$this->done = false;
			return true;
		} else {
			$this->done    = true;
			$this->message = __( 'Subscription records have been successfully updated.', 'edd-recurring' );
			edd_set_upgrade_complete( 'recurring_update_order_item_status' );
			delete_option( 'edd_doing_upgrade' );
			delete_transient( 'edd_recurring_order_item_counts' );
			return false;
		}
	}

	public function headers() {
		ignore_user_abort( true );

		if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}
	}

	/**
	 * Perform the export
	 *
	 * @access public
	 * @since 2.11.7
	 * @return void
	 */
	public function export() {

		// Set headers
		$this->headers();

		edd_die();
	}

	/**
	 * Get the subscription IDs (50 based on this->per_step) for the current step
	 *
	 * @since 2.11.7.5
	 *
	 * @global object $wpdb
	 * @return array
	 */
	private function get_order_items() {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.*
				FROM {$wpdb->edd_order_items} AS oi
				INNER JOIN {$wpdb->edd_orders} o ON (oi.order_id = o.id AND o.status = 'edd_subscription')
				WHERE oi.status IN('edd_subscription','pending')
				LIMIT %d",
				$this->per_step
			)
		);
	}
}
