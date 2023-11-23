<?php
/**
 * @var EDD_Subscription $sub The subscription object.
 */
?>
<div class="edd-recurring-subscription-section edd-recurring-subscription__details">
	<h2><?php esc_html_e( 'Subscription Details', 'edd-recurring' ); ?></h2>

	<div class="edd-recurring-subscription-table">
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header"><?php esc_html_e( 'Times Billed', 'edd-recurring' ); ?></div>
			<div class="edd-recurring-subscription-table_column-content">
			<?php
			$output = $sub->get_times_billed() . ' / ';
			if ( empty( $sub->bill_times ) ) {
				$output .= __( 'Until Cancelled', 'edd-recurring' );
			} else {
				$output .= $sub->bill_times;
			}
			echo esc_html( $output );
			?>
			</div>
		</div>
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header"><?php esc_html_e( 'Initial Purchase ID', 'edd-recurring' ); ?></div>
			<div class="edd-recurring-subscription-table_column-content">
				<?php
				if ( current_user_can( 'edit_shop_payments', $sub->parent_payment_id ) ) :
					$url = add_query_arg(
						array(
							'id'        => urlencode( $sub->parent_payment_id ),
							'post_type' => 'download',
							'page'      => 'edd-payment-history',
							'view'      => 'view-order-details',
						),
						admin_url( 'edit.php' )
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( edd_get_payment_number( $sub->parent_payment_id ) ); ?></strong></a>
				<?php endif; ?>
			</div>
		</div>
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header"><?php esc_html_e( 'Gateway', 'edd-recurring' ); ?></div>
			<div class="edd-recurring-subscription-table_column-content">
				<?php echo esc_html( edd_get_gateway_admin_label( edd_get_payment_gateway( $sub->parent_payment_id ) ) ); ?>
			</div>
		</div>
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header"><?php esc_html_e( 'Profile ID', 'edd-recurring' ); ?></div>
			<div class="edd-recurring-subscription-table_column-content">
				<span class="edd-sub-profile-id">
					<?php echo wp_kses_post( apply_filters( "edd_subscription_profile_link_{$sub->gateway}", $sub->profile_id, $sub ) ); ?>
				</span>
				<input type="text" id="edd_recurring_profile_id" name="profile_id" class="hidden edd-sub-profile-id" value="<?php echo esc_attr( $sub->profile_id ); ?>" />
				<?php if ( current_user_can( 'manage_subscriptions' ) ) : ?>
					<span>&nbsp;&ndash;&nbsp;</span>
					<a href="" class="edd-edit-sub-profile-id"><?php esc_html_e( 'Edit', 'edd-recurring' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header"><?php esc_html_e( 'Transaction ID', 'edd-recurring' ); ?></div>
			<div class="edd-recurring-subscription-table_column-content">
				<span class="edd-sub-transaction-id"><?php echo esc_html( apply_filters( 'edd_subscription_details_transaction_id_' . $sub->gateway, $sub->get_transaction_id(), $sub ) ); ?></span>
				<?php if ( current_user_can( 'manage_subscriptions' ) ) : ?>
				<input type="text" id="edd_recurring_transaction_id" name="transaction_id" class="hidden edd-sub-transaction-id" value="<?php echo esc_attr( $sub->get_transaction_id() ); ?>" />
					<span>&nbsp;&ndash;&nbsp;</span>
					<a href="" class="edd-edit-sub-transaction-id"><?php esc_html_e( 'Edit', 'edd-recurring' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>

</div> <!-- ends __details !-->
