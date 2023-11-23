<?php

/**
 * Display the customer's subscriptions on the customer card
 *
 * @since  2.4
 * @param  object $customer The Customer object
 * @return void
 */
function edd_recurring_customer_subscriptions_list( $customer ) {

	$subscriber    = new EDD_Recurring_Subscriber( $customer->id );
	$subscriptions = $subscriber->get_subscriptions();

	if ( ! $subscriptions ) {
		?>
		<p><?php esc_html_e( 'This customer has no subscriptions.', 'edd-recurring' ); ?></p>
		<?php
		return;
	}
	?>
	<h3><?php esc_html_e( 'Subscriptions', 'edd-recurring' ); ?></h3>
	<table class="wp-list-table widefat striped downloads">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Subscription', 'edd-recurring' ); ?></th>
				<th><?php echo esc_html( edd_get_label_singular() ); ?></th>
				<th><?php esc_html_e( 'Billing Details', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Order', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Renewal Date', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Status', 'edd-recurring' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $subscriptions as $subscription ) :
				$subscription_url = add_query_arg(
					array(
						'post_type' => 'download',
						'page'      => 'edd-subscriptions',
						'id'        => absint( $subscription->id ),
					),
					admin_url( 'edit.php' )
				);
				$payment_url      = add_query_arg(
					array(
						'post_type' => 'download',
						'page'      => 'edd-payment-history',
						'view'      => 'view-order-details',
						'id'        => absint( $subscription->parent_payment_id ),
					),
					admin_url( 'edit.php' )
				);
				$download_url     = add_query_arg(
					array(
						'post'   => absint( $subscription->product_id ),
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				);
				$renewal_date     = ! empty( $subscription->expiration ) ? date_i18n( get_option( 'date_format' ), strtotime( $subscription->expiration ) ) : __( 'N/A', 'edd-recurring' );
				$period           = EDD_Recurring()->get_pretty_subscription_frequency( $subscription->period );
				$billing_cycle    = edd_currency_filter( edd_format_amount( $subscription->recurring_amount ), edd_get_payment_currency_code( $subscription->parent_payment_id ) ) . ' / ' . $period;
				$initial_amount   = edd_currency_filter( edd_format_amount( $subscription->initial_amount ), edd_get_payment_currency_code( $subscription->parent_payment_id ) );
				$status           = $subscription->get_status_badge();
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $subscription_url ); ?>">#<?php echo esc_html( $subscription->id ); ?></a>
						<div class="row-actions"><span class="edit"><a href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'View Details', 'edd-recurring' ); ?></div>
					</td>
					<td><a href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html( get_the_title( $subscription->product_id ) ); ?></a></td>
					<td>
						<?php esc_html_e( 'Initial Amount', 'edd-recurring' ); ?>: <?php echo esc_html( $initial_amount ); ?><br>
						<?php echo esc_html( $billing_cycle ); ?>
					</td>
					<td><a href="<?php echo esc_url( $payment_url ); ?>"><?php echo esc_html( edd_get_payment_number( $subscription->parent_payment_id ) ); ?></a></td>
					<td><?php echo esc_html( $renewal_date ); ?></td>
					<td><?php echo $status; ?></td>
				</tr>
			<?php
			endforeach;
			?>
		</tbody>
	</table>
<?php
}

/**
 * Registers a customer view for subscriptions.
 *
 * @since 2.12
 * @param array $views
 * @return array
 */
function edd_recurring_register_subscription_customer_views( $views ) {
	$views['subscriptions'] = 'edd_recurring_customer_subscription_view';

	return $views;
}
add_filter( 'edd_customer_views', 'edd_recurring_register_subscription_customer_views' );

/**
 * Registers a subscriptions tab for the customer details screen.
 *
 * @since 2.12
 * @param array $tabs
 * @return array
 */
function edd_recurring_register_subscription_customer_tab( $tabs ) {
	$tabs['subscriptions'] = array(
		'dashicon' => 'dashicons-update',
		'title'    => __( 'Subscriptions', 'edd-recurring' ),
	);

	return $tabs;
}
add_filter( 'edd_customer_tabs', 'edd_recurring_register_subscription_customer_tab' );

/**
 * Outputs the customer subscriptions table.
 *
 * @since 2.12
 * @param EDD_Customer $customer
 * @return void
 */
function edd_recurring_customer_subscription_view( $customer ) {
	if ( function_exists( 'edd_render_customer_details_header' ) ) {
		edd_render_customer_details_header( $customer );
	} else {
		?>
		<div class="edd-item-header-small">
			<?php echo get_avatar( $customer->email, 30 ); ?> <span><?php echo esc_html( $customer->name ); ?></span>
		</div>
		<?php
	}
	echo '<div class="customer-section">';
	edd_recurring_customer_subscriptions_list( $customer );
	edd_recurring_customer_profile_ids( $customer );
	echo '</div>';
}

/**
 * Display a customer's recurring profile IDs on the customer card if they have them
 *
 * @since  2.4.2
 * @param  object $customer Customer Ojbect
 * @return void
 */
function edd_recurring_customer_profile_ids( $customer ) {
	$subscriber = new EDD_Recurring_Subscriber( $customer->id );
	$profiles   = $subscriber->get_recurring_customer_ids();

	if ( ! is_array( $profiles ) || empty( $profiles ) ) {
		return;
	}
	?>
	<h3><?php esc_html_e( 'Recurring Profiles', 'edd-recurring' ); ?></h3>
	<table class="wp-list-table widefat striped downloads">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Gateway', 'edd-recurring' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'Profile ID', 'edd-recurring' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $profiles as $gateway => $profile ) : ?>
			<?php
			$gateway = EDD_Recurring()->get_gateway( $gateway );
			if ( false === $gateway ) {
				continue;
			}
			?>
			<tr>
				<td><?php echo esc_html( $gateway->friendly_name ); ?></td>
				<td><?php echo esc_html( $profile ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Allow the customer recount tool to include edd_subscription payment status.
 *
 * @since  2.4.5
 * @param  array $payment_statuses Array of post statuses.
 * @return array                   Array of post statuses with edd_subscription included.
 */
function edd_recurring_customer_recount_status( $payment_statuses ) {

	$payment_statuses[] = 'edd_subscription';

	return $payment_statuses;

}
add_filter( 'edd_recount_customer_payment_statuses', 'edd_recurring_customer_recount_status', 10, 1 );

/**
 * Allow the customer recount tool to process a subscription payment.
 *
 * @todo Deprecate after EDD 3.0.
 *
 * @since  2.4.5
 * @param  bool   $ret      Base status for if the payment should be processed.
 * @param  object $payment  WP_Post object of the payment being checked.
 * @return bool             If it's an edd_subscription, return true, otherwise return the supplied return.
 */
function edd_recurring_should_process_payment( $ret, $payment ) {

	// This does not need to be updated for EDD 3.0.
	if ( 'edd_subscription' === $payment->post_status ) {
		$ret = true;
	}

	return $ret;
}
add_filter( 'edd_customer_recount_should_process_payment', 'edd_recurring_should_process_payment', 10, 2 );

/**
 * Find any customers with subscription customer IDs
 *
 * @since  2.4
 * @param  array $items Current items to remove from the reset
 * @return array        The items with any subscription customer entires
 */
function edd_recurring_reset_delete_sub_customer_ids( $items ) {

	global $wpdb;

	$sql      = "SELECT umeta_id FROM $wpdb->usermeta WHERE meta_key = '_edd_recurring_id'";
	$meta_ids = $wpdb->get_col( $sql );

	foreach ( $meta_ids as $id ) {
		$items[] = array(
			'id'   => (int) $id,
			'type' => 'edd_subscriber_id',
		);
	}

	return $items;
}
add_filter( 'edd_reset_store_items', 'edd_recurring_reset_delete_sub_customer_ids', 10, 1 );

/**
 * Isolate any subscriber Customer IDs to remove from the db on reset
 *
 * @since  2.4
 * @param  stirng $type The type of item to remove from the initial findings
 * @param  array  $item The item to remove
 * @return string       The determine item type
 */
function edd_recurring_reset_recurring_customer_ids( $type, $item ) {

	if ( 'edd_subscriber_id' === $item['type'] ) {
		$type = $item['type'];
	}

	return $type;

}
add_filter( 'edd_reset_item_type', 'edd_recurring_reset_recurring_customer_ids', 10, 2 );

/**
 * Add an SQL item to the reset process for the usermeta with the given umeta_ids
 *
 * @since  2.4
 * @param  array  $sql An Array of SQL statements to run
 * @param  string $ids The IDs to remove for the given item type
 * @return array       Returns the array of SQL statements with statements added
 */
function edd_recurring_reset_customer_queries( $sql, $ids ) {

	global $wpdb;
	$sql[] = "DELETE FROM $wpdb->usermeta WHERE umeta_id IN ($ids)";

	return $sql;

}
add_filter( 'edd_reset_add_queries_edd_subscriber_id', 'edd_recurring_reset_customer_queries', 10, 2 );

/**
 * Cancels subscriptions and deletes them when a customer is deleted
 *
 * @since  2.5
 * @param  int  $customer_id ID of the customer being deleted
 * @param  bool $confirm     Whether site admin has confirmed they wish to delete the customer
 * @param  bool $remove_data Whether associated data should be deleted
 * @return void
 */
function edd_recurring_delete_customer_and_subscriptions( $customer_id, $confirm, $remove_data ) {

	if( empty( $customer_id ) || ! $customer_id > 0 ) {
		return;
	}

	$subscriber       = new EDD_Recurring_Subscriber( $customer_id );
	$subscriptions    = $subscriber->get_subscriptions();
	$subscriptions_db = new EDD_Subscriptions_DB;

	if( ! is_array( $subscriptions ) ) {
		return;
	}

	foreach( $subscriptions as $sub ) {

		if( $sub->can_cancel() ) {

			$gateway = edd_recurring()->get_gateway( $sub->gateway );

			// Attempt to cancel the subscription in the gateway
			if( $gateway ) {
				$gateway->cancel( $sub, true );
			}

		}

		if( $remove_data ) {

			// Delete the subscription from the database
			$subscriptions_db->delete( $sub->id );

		}

	}

}
add_action( 'edd_pre_delete_customer', 'edd_recurring_delete_customer_and_subscriptions', 10, 3 );

/**
 * Cancels subscriptions when customer is anonymized.
 *
 * @since  2.12
 * @param  EDD_Customer $customer The EDD_Customer object.
 * @return void
 */
function edd_recurring_anonymize_customer( $customer ) {
	if ( empty( $customer ) || ! $customer->id > 0 ) {
		return;
	}

	$subscriber    = new EDD_Recurring_Subscriber( $customer->id );
	$subscriptions = $subscriber->get_subscriptions();

	if ( ! is_array( $subscriptions ) ) {
		return;
	}

	foreach ( $subscriptions as $subscription ) {
		if ( $subscription->can_cancel() ) {
			$subscription->cancel();
			$subscription->add_note( __( 'Subscription has been cancelled due to customer requesting anonymization.', 'edd-recurring' ) );
		}
	}
}
add_action( 'edd_anonymize_customer', 'edd_recurring_anonymize_customer', 10, 1 );
