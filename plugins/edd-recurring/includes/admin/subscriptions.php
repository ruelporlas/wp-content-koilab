<?php
/**
 * Render the Subscriptions table
 *
 * @access      public
 * @since       2.4
 * @return      void
 */
function edd_subscriptions_page() {

	if ( ! empty( $_GET['id'] ) ) {

		edd_recurring_subscription_details();

		return;

	} elseif ( isset( $_GET['edd-action'] ) && 'add_subscription' === $_GET['edd-action'] ) {

		edd_recurring_new_subscription_details();

		return;
	}
	?>
	<div class="wrap">

		<h1 class="wp-heading-inline"><?php esc_html_e( 'Subscriptions', 'edd-recurring' ); ?></h1>
		<?php
		if ( current_user_can( 'manage_subscriptions' ) ) {
			printf(
				'<a href="%s" class="page-title-action">%s</a>',
				esc_url( add_query_arg( array( 'edd-action' => 'add_subscription' ) ) ),
				esc_html__( 'Add New', 'edd-recurring' )
			);
		}
		$subscribers_table = new EDD_Subscription_Reports_Table();
		$subscribers_table->prepare_items();
		?>

		<form id="subscribers-filter" method="get">

			<input type="hidden" name="post_type" value="download" />
			<input type="hidden" name="page" value="edd-subscriptions" />
			<?php
			$subscribers_table->views();
			$subscribers_table->advanced_filters();
			$subscribers_table->display();
			?>

		</form>
		<?php esc_html_e( 'To narrow results, search can be prefixed with the following:', 'edd-recurring' ); ?><code>id:</code>, <code>profile_id:</code>, <code>product_id:</code>, <code>txn:</code>, <code>customer_id:</code>
	</div>
	<?php
}

/**
 * Force subscription forms screens to register as a single view for EDD 3.0.
 *
 * @since 2.12
 * @param bool $is_single
 * @return bool
 */
add_filter( 'edd_admin_is_single_view', function( $is_single ) {
	if ( isset( $_GET['edd-action'] ) && 'add_subscription' === $_GET['edd-action'] ) {
		return true;
	} elseif ( ! empty( $_GET['id'] ) ) {
		$screen = get_current_screen();
		if ( 'download_page_edd-subscriptions' === $screen->id ) {
			return true;
		}
	}

	return $is_single;
} );

/**
 * Recurring Subscription Details
 * @description Outputs the subscriber details
 * @since       2.5
 */
function edd_recurring_new_subscription_details() {
	include 'views/subscription-details/new.php';
}

/**
 * Gets the tabs for existing subscriptions.
 *
 * @since 3.0
 * @return array
 */
function edd_recurring_get_subscription_tabs() {
	return array(
		'general'  => array(
			'dashicon' => 'dashicons-editor-table',
			'title'    => __( 'Overview', 'edd-recurring' ),
			'view'     => 'general',
		),
		'renewals' => array(
			'dashicon' => 'dashicons-update',
			'title'    => __( 'Renewals', 'edd-recurring' ),
			'view'     => 'renewals',
		),
		'notes'    => array(
			'dashicon' => 'dashicons-admin-comments',
			'title'    => __( 'Notes', 'edd-recurring' ),
			'view'     => 'notes',
		),
	);
}

/**
 * Recurring Subscription Details
 * @description Outputs the subscriber details
 * @since       2.4
 *
 */
function edd_recurring_subscription_details() {
	include 'views/subscription-details/existing.php';
}

/**
 * Handles subscription update
 *
 * @access      public
 * @since       2.4
 * @return      void
 */
function edd_recurring_process_subscription_update() {

	if ( empty( $_POST['sub_id'] ) ) {
		return;
	}

	if ( empty( $_POST['edd_update_subscription'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_subscriptions' ) ) {
		return;
	}

	$subscription_id = absint( $_POST['sub_id'] );

	if ( ! wp_verify_nonce( $_POST['edd-recurring-update-nonce'], "edd-recurring-update-{$subscription_id}" ) ) {
		wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$subscription    = new EDD_Subscription( $subscription_id );

	if ( ! $subscription->current_user_can() ) {
		wp_die( esc_html__( 'You do not have permission to update this subscription.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$expiration     = date( 'Y-m-d 23:59:59', strtotime( $_POST['expiration'] ) );
	$profile_id     = sanitize_text_field( $_POST['profile_id'] );
	$transaction_id = sanitize_text_field( $_POST['transaction_id'] );
	$product_id     = sanitize_text_field( $_POST['product_id'] );
	$status         = sanitize_text_field( $_POST['status'] );

	$product_details = explode( '_', $product_id );
	$product_id      = $product_details[0];
	$has_variations  = edd_has_variable_prices( $product_id );
	if ( $has_variations ) {
		if ( ! isset( $product_details[1] ) ) {
			wp_die( __( 'A variation is required for the selected product', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 401 ) );
		}

		$price_id = $product_details[1];
	}

	$args            = array(
		'status'         => $status,
		'expiration'     => $expiration,
		'profile_id'     => $profile_id,
		'product_id'     => $product_id,
		'transaction_id' => $transaction_id,
	);

	if ( $has_variations && isset( $price_id ) ) {
		$args['price_id'] = $price_id;
	}

	if( 'pending' !== $status && 'active' !== $status ) {
		unset( $args['status'] );
	}

	$subscription->update( $args  );


	switch( $status ) {

		case 'cancelled' :

			$subscription->cancel();
			break;

		case 'expired' :

			$subscription->expire();
			break;

		case 'completed' :

			$subscription->complete();
			break;

		case 'failing' :

			$subscription->failing();
			break;

	}

	wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-subscriptions&edd-message=updated&id=' . $subscription->id ) );
	exit;

}
add_action( 'admin_init', 'edd_recurring_process_subscription_update', 1 );

/**
 * Handles subscription creation
 *
 * @access      public
 * @since       2.5
 * @return      void
 */
function edd_recurring_process_subscription_creation() {

	if ( empty( $_POST['edd_new_subscription'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_subscriptions' ) ) {
		return;
	}

	$die_args = array(
		'response'  => 403,
		'back_link' => true,
	);
	if( ! wp_verify_nonce( $_POST['edd-recurring-add-subscription-nonce'], 'edd-recurring-add-subscription' ) ) {
		wp_die( esc_html__( 'Nonce verification failed.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), $die_args );
	}

	if( empty( $_POST['expiration'] ) ) {
		wp_die( esc_html__( 'Please enter an expiration date.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), $die_args );
	}

	if( empty( $_POST['product_id'] ) ) {
		wp_die( esc_html__( 'Please select a product.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), $die_args );
	}

	if( empty( $_POST['recurring_amount'] ) ) {
		wp_die( esc_html__( 'Please enter a recurring amount.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), $die_args );
	}

	if( ! empty( $_POST['created'] ) ) {
		$created_date = date( 'Y-m-d ' . date( 'H:i:s', current_time( 'timestamp' ) ), strtotime( $_POST['created'], current_time( 'timestamp' ) ) );
	} else {
		$created_date = date( 'Y-m-d H:i:s',current_time( 'timestamp' ) );
	}

	if( ! empty( $_POST['customer_id'] ) ) {

		$customer    = new EDD_Recurring_Subscriber( absint( $_POST['customer_id'] ) );
		$customer_id = $customer->id;
		$email       = $customer->email;

	} else {

		$email       = sanitize_email( $_POST['customer_email'] );
		$user        = get_user_by( 'email', $email );
		$user_id     = $user ? $user->ID : 0;
		$customer    = new EDD_Recurring_Subscriber;
		$customer_id = $customer->create( array( 'email' => $email, 'user_id' => $user_id ) );

	}

	$customer_id = absint( $customer_id );
	if ( empty( $customer_id ) ) {
		wp_die( esc_html__( 'A customer must be assigned to or created for this subscription.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), array( 'response' => 400, 'back_link' => true ) );
	}

	if ( ! empty( $_POST['edd_recurring_parent_payment_id'] ) ) {

		$payment_id = absint( $_POST['edd_recurring_parent_payment_id'] );
		$payment    = edd_get_payment( $payment_id );

		if ( ! $payment ) {
			/* translators: the existing payment ID. */
			wp_die( sprintf( esc_html__( 'Payment %s does not exist.', 'edd-recurring' ), absint( $payment_id ) ), esc_html__( 'Error', 'edd-recurring' ), array( 'response' => 400, 'back_link' => true ) );
		}
	} else {

		$options = array();
		if ( ! empty( $_POST['edd_price_option'] ) ) {
			$options['price_id'] = absint( $_POST['edd_price_option'] );
		}

		$payment = new EDD_Payment();
		$payment->add_download( absint( $_POST['product_id'] ), $options );
		$payment->customer_id = $customer_id;
		$payment->email       = $email;
		$payment->user_id     = $customer->user_id;
		$payment->gateway     = sanitize_text_field( $_POST['gateway'] );
		$payment->total       = edd_sanitize_amount( sanitize_text_field( $_POST['initial_amount'] ) );
		$payment->date        = $created_date;
		$payment->status      = 'pending';
		$payment->mode        = edd_is_test_mode() ? 'test' : 'live';
		$payment->save();
		$payment->status = 'complete';
		$payment->save();
	}

	$args = array(
		'expiration'        => date( 'Y-m-d 23:59:59', strtotime( $_POST['expiration'], current_time( 'timestamp' ) ) ),
		'created'           => $created_date,
		'status'            => sanitize_text_field( $_POST['status'] ),
		'profile_id'        => sanitize_text_field( $_POST['profile_id'] ),
		'transaction_id'    => sanitize_text_field( $_POST['transaction_id'] ),
		'initial_amount'    => edd_sanitize_amount( sanitize_text_field( $_POST['initial_amount'] ) ),
		'recurring_amount'  => edd_sanitize_amount( sanitize_text_field( $_POST['recurring_amount'] ) ),
		'bill_times'        => absint( $_POST['bill_times'] ),
		'period'            => sanitize_text_field( $_POST['period'] ),
		'parent_payment_id' => $payment->ID,
		'product_id'        => absint( $_POST['product_id'] ),
		'price_id'          => isset( $_POST['edd_price_option'] ) ? absint( $_POST['edd_price_option'] ) : null,
		'customer_id'       => $customer_id,
	);

	$subscription = new EDD_Subscription;
	$subscription->create( $args );

	if( 'trialling' === $subscription->status ) {
		$customer->add_meta( 'edd_recurring_trials', $subscription->product_id );
	}

	$payment->update_meta( '_edd_subscription_payment', true );

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'   => 'download',
				'page'        => 'edd-subscriptions',
				'edd-message' => 'created',
				'id'          => urlencode( $subscription->id ),
			),
			admin_url( 'edit.php' )
		)
	);
	exit;

}
add_action( 'edd_add_subscription', 'edd_recurring_process_subscription_creation', 1 );

/**
 * Handles subscription cancellation
 *
 * @access      public
 * @since       2.4
 * @return      void
 */
function edd_recurring_process_subscription_cancel() {

	if( empty( $_POST['sub_id'] ) ) {
		return;
	}

	if( empty( $_POST['edd_cancel_subscription'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_subscriptions' ) ) {
		return;
	}

	if( ! wp_verify_nonce( $_POST['_wpnonce'], 'edd-recurring-cancel' ) ) {
		wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$subscription    = new EDD_Subscription( absint( $_POST['sub_id'] ) );
	$subscription->cancel();

	wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-subscriptions&edd-message=cancelled&id=' . $subscription->id ) );
	exit;

}
add_action( 'admin_init', 'edd_recurring_process_subscription_cancel', 1 );


/**
 * Handles adding a manual renewal payment
 *
 * @access      public
 * @since       2.4
 * @return      void
 */
function edd_recurring_process_add_renewal_payment() {

	if ( empty( $_POST['sub_id'] ) ) {
		return;
	}

	if ( ! current_user_can( 'publish_shop_payments' ) ) {
		return;
	}

	if( ! wp_verify_nonce( $_POST['_wpnonce'], 'edd-recurring-add-renewal-payment' ) ) {
		wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$amount = isset( $_POST['amount'] ) ? edd_sanitize_amount( $_POST['amount'] ) : '0.00';
	$tax    = isset( $_POST['tax'] ) ? edd_sanitize_amount( $_POST['tax'] ) : 0;
	$txn_id = isset( $_POST['txn_id'] ) ? sanitize_text_field( $_POST['txn_id'] ) : md5( strtotime( 'NOW' ) );
	$args   = array(
		'amount'         => $amount,
		'transaction_id' => $txn_id,
		'tax'            => $tax,
	);

	if ( isset( $_POST['date'] ) ) {
		$date = sanitize_text_field( $_POST['date'] );

		// We only care about the time if the date was submitted.
		$hour = isset( $_POST['hour'] ) && is_numeric( $_POST['hour'] ) ? intval( $_POST['hour'] ) : false;

		// Just a final check that the value is within the range we expect.
		if ( false === $hour || ( $hour > 23 || $hour < 0 ) ) {
			$hour = wp_date( 'H' );
		}

		// We only care about the time if the date was submitted.
		$minute = isset( $_POST['minute'] ) && is_numeric( $_POST['minute'] ) ? intval( $_POST['minute'] ) : false;

		// Just a final check that the value is within the range we expect.
		if ( false === $minute || ( $minute > 23 || $minute < 0 ) ) {
			$minute = wp_date( 'i' );
		}

		// Build the string for the date, so we can validate it.
		$date_string = $date . ' ' . $hour . ':' . $minute . ':00';

		/**
		 * Now make a datetime string so we can convert this over to GMT/UTC to store in the database.
		 *
		 * We expect users to submit this in their own timezone. And we asked them to do so.
		 * WP 5.3+ can use the wp_timezone_string, older versions cannot. We still need to verify if it is empty before assuming it exists.
		 */
		$store_timezone = function_exists( 'wp_timezone_string' ) && ! empty( wp_timezone_string() ) ? wp_timezone_string() : get_option( 'gmt_offset' );
		$date           = new DateTime( $date_string, new DateTimeZone( $store_timezone ) );

		// Now set it to GMT, format it, and use it for the subscription arguments.
		$args['date'] = $date->setTimezone( new DateTimeZone( 'GMT' ) )->format( 'Y-m-d H:i:s' );
	}

	$sub        = new EDD_Subscription( absint( $_POST['sub_id'] ) );
	$payment_id = $sub->add_payment( $args );

	if( ! empty( $_POST['renew_and_add_payment'] ) ) {
		$sub->renew( $payment_id );
	}

	if( $payment_id ) {
		$message = 'renewal-added';
	} else {
		$message = 'renewal-not-added';
	}

	$url = add_query_arg(
		array(
			'post_type'   => 'download',
			'page'        => 'edd-subscriptions',
			'view'        => 'renewals',
			'edd-message' => urlencode( $message ),
			'id'          => urlencode( $sub->id ),
		),
		admin_url( 'edit.php' )
	);
	wp_safe_redirect( $url );
	exit;

}
add_action( 'edd_add_renewal_payment', 'edd_recurring_process_add_renewal_payment', 1 );


/**
 * Handles retrying a renewal payment for a failing subscription
 *
 * @access      public
 * @since       2.8
 * @return      void
 */
function edd_recurring_process_renewal_charge_retry() {

	if ( empty( $_GET['sub_id'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_subscriptions' ) ) {
		return;
	}

	$sub_id = absint( $_GET['sub_id'] );

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], "edd-recurring-retry-{$sub_id}" ) ) {
		wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$sub = new EDD_Subscription( $sub_id );

	if( ! $sub->can_retry() ) {
		wp_die( __( 'This subscription does not support being retried.', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$result = $sub->retry();

	if( $result && ! is_wp_error( $result ) ) {
		$message = 'retry-success';
	} else {
		$message = 'retry-failed&error-message=' . urlencode( $result->get_error_message() );
	}

	wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-subscriptions&edd-message=' . $message . '&id=' . $sub->id ) );
	exit;

}
add_action( 'edd_retry_subscription', 'edd_recurring_process_renewal_charge_retry', 1 );

/**
 * Handles adding a subscription note
 *
 * @access      public
 * @since       2.7
 * @return      void
 */
function edd_recurring_process_add_subscription_note() {

	if ( empty( $_POST['sub_id'] ) ) {
		return;
	}

	if ( ! current_user_can( 'view_subscriptions' ) ) {
		return;
	}

	if( ! wp_verify_nonce( $_POST['_wpnonce'], 'edd-recurring-add-note' ) ) {
		wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$note    = trim( sanitize_text_field( $_POST['note'] ) );
	$sub     = new EDD_Subscription( absint( $_POST['sub_id'] ) );
	$added   = $sub->add_note( $note );

	if( $added ) {
		$message = 'subscription-note-added';
	} else {
		$message = 'subscription-note-not-added';
	}

	$url = add_query_arg(
		array(
			'post_type'   => 'download',
			'page'        => 'edd-subscriptions',
			'view'        => 'notes',
			'edd-message' => urlencode( $message ),
			'id'          => urlencode( $sub->id ),
		),
		admin_url( 'edit.php' )
	);
	wp_safe_redirect( $url );
	exit;

}
add_action( 'edd_add_subscription_note', 'edd_recurring_process_add_subscription_note', 1 );

/**
 * Handles subscription deletion
 *
 * @access      public
 * @since       2.4
 * @return      void
 */
function edd_recurring_process_subscription_deletion() {

	if ( empty( $_POST['sub_id'] ) ) {
		return;
	}

	if ( empty( $_POST['edd_delete_subscription'] ) ) {
		return;
	}

	$subscription_id = absint( $_POST['sub_id'] );

	if ( ! wp_verify_nonce( $_POST['edd-recurring-update-nonce'], "edd-recurring-update-{$subscription_id}" ) ) {
		wp_die( __( 'Nonce verification failed', 'edd-recurring' ), __( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$subscription = new EDD_Subscription( $subscription_id );

	if ( ! $subscription->current_user_can( 'delete_subscriptions' ) ) {
		wp_die( esc_html__( 'You do not have permission to delete this subscription.', 'edd-recurring' ), esc_html__( 'Error', 'edd-recurring' ), array( 'response' => 403 ) );
	}

	$payment = new EDD_Payment( $subscription->parent_payment_id );
	if ( $payment ) {
		$payment->delete_meta( '_edd_subscription_payment' );
	}

	// Delete subscription from list of trials customer has used
	$subscription->customer->delete_meta( 'edd_recurring_trials', $subscription->product_id );

	$subscription->delete();

	wp_safe_redirect( admin_url( 'edit.php?post_type=download&page=edd-subscriptions&edd-message=deleted' ) );
	exit;

}
add_action( 'admin_init', 'edd_recurring_process_subscription_deletion', 2 );

add_action( 'edd_updated_edited_purchase', 'edd_recurring_update_customer_id_edited_purchase' );
/**
 * When an order is updated, maybe update the customer ID for the related subscription.
 *
 * @since 2.11.8
 * @param int $order_id
 * @return void
 */
function edd_recurring_update_customer_id_edited_purchase( $order_id ) {

	if ( function_exists( 'edd_get_order' ) ) {
		$order       = edd_get_order( $order_id );
		$customer_id = $order->customer_id;
	} else {
		$customer_id = edd_get_payment_customer_id( $order_id );
	}
	if ( ! $customer_id ) {
		return;
	}
	$subs_db = new EDD_Subscriptions_DB();
	$subs    = $subs_db->get_subscriptions( array( 'parent_payment_id' => $order_id ) );
	if ( $subs ) {
		foreach ( $subs as $sub ) {
			if ( (int) $sub->customer_id !== (int) $customer_id ) {
				$sub->update( array( 'customer_id' => $customer_id ) );
			}
		}
	}
}

/**
 * Find all subscription IDs
 *
 * @since  2.4
 * @param  array $items Current items to remove from the reset
 * @return array        The items with all subscriptions
 */
function edd_recurring_reset_delete_subscriptions( $items ) {

	$db = new EDD_Subscriptions_DB;

	$args = array(
		'number'  => -1,
		'orderby' => 'id',
		'order'   => 'ASC',
	);

	$subscriptions = $db->get_subscriptions( $args );

	foreach ( $subscriptions as $subscription ) {
		$items[] = array(
			'id'   => (int) $subscription->id,
			'type' => 'edd_subscription',
		);
	}

	return $items;
}
add_filter( 'edd_reset_store_items', 'edd_recurring_reset_delete_subscriptions', 10, 1 );

/**
 * Isolate the subscription items during the reset process
 *
 * @since  2.4
 * @param  stirng $type The type of item to remove from the initial findings
 * @param  array  $item The item to remove
 * @return string       The determine item type
 */
function edd_recurring_reset_recurring_type( $type, $item ) {

	if ( 'edd_subscription' === $item['type'] ) {
		$type = $item['type'];
	}

	return $type;

}
add_filter( 'edd_reset_item_type', 'edd_recurring_reset_recurring_type', 10, 2 );

/**
 * Add an SQL item to the reset process for the given subscription IDs
 *
 * @since  2.4
 * @param  array  $sql An Array of SQL statements to run
 * @param  string $ids The IDs to remove for the given item type
 * @return array       Returns the array of SQL statements with subscription statement added
 */
function edd_recurring_reset_queries( $sql, $ids ) {

	global $wpdb;
	$table = $wpdb->prefix . 'edd_subscriptions';
	$sql[] = "DELETE FROM $table WHERE id IN ($ids)";

	return $sql;

}
add_filter( 'edd_reset_add_queries_edd_subscription', 'edd_recurring_reset_queries', 10, 2 );

/**
 * Populates the subscription details (price, recurring amount, period, expiration) on product selection.
 *
 * @since 2.11.7
 * @return void
 */
function edd_recurring_update_subscription_product_details() {
	if ( ! current_user_can( 'manage_subscriptions' ) ) {
		wp_send_json_error();
	}
	$download_id         = absint( $_GET['download_id'] );
	$price_id            = null;
	$has_variable_prices = edd_has_variable_prices( $download_id );
	if ( $has_variable_prices ) {
		$price_id = isset( $_GET['price_id'] ) && is_numeric( $_GET['price_id'] ) ? absint( $_GET['price_id'] ) : edd_get_default_variable_price( $download_id );
	}
	if ( ! edd_recurring()->is_recurring( $download_id ) && ! edd_recurring()->is_price_recurring( $download_id, $price_id ) ) {
		wp_send_json_error();
	}
	// Get the price.
	if ( $has_variable_prices ) {
		$prices = edd_get_variable_prices( $download_id );
		if ( null !== $price_id && isset( $prices[ $price_id ] ) ) {
			$price  = edd_get_price_option_amount( $download_id, $price_id );
			$period = edd_recurring()->get_period( $price_id, $download_id );
		} else {
			$price  = edd_get_lowest_price_option( $download_id );
			$period = edd_recurring()->get_period_single( $download_id );
		}
	} else {
		$price  = edd_get_download_price( $download_id );
		$period = edd_recurring()->get_period_single( $download_id );
	}
	$initial_amount = $price;
	if ( edd_recurring()->has_free_trial( $download_id, $price_id ) ) {
		$initial_amount = 0.00;
	}

	wp_send_json_success(
		array(
			'price'            => edd_sanitize_amount( $initial_amount ),
			'recurring_amount' => edd_sanitize_amount( $price ),
			'period'           => sanitize_text_field( $period ),
		)
	);

}
add_action( 'wp_ajax_edd_recurring_update_product_details', 'edd_recurring_update_subscription_product_details' );
