<?php
/**
 * @var $order EDD_Order        The current order object.
 * @var $sub   EDD_Subscription The current subscription object.
 */
if ( ! $sub instanceof EDD_Subscription ) {
	return;
}

$sub_url = add_query_arg(
	array(
		'post_type' => 'download',
		'page'      => 'edd-subscriptions',
		'id'        => urlencode( $sub->id ),
	),
	admin_url( 'edit.php' )
);
?>
<div class="edd-recurring-subscription-details">
	<div class="edd-recurring-subscription-details--id">
		<span class="dashicons dashicons-update"></span>
		<?php esc_html_e( 'Subscription ID:', 'edd_recurring' ); ?>
		<a href="<?php echo esc_url( $sub_url ); ?>"><?php echo (int) $sub->id; ?></a>
	</div>
	<div class="edd-recurring-subscription-details--status">
		<?php
		printf(
			/* translators: the subscription status */
			esc_html__( 'Status: %s', 'edd-recurring' ),
			esc_html( $sub->get_status_label() )
		);
		?>
	</div>
	<?php
	if ( ! empty( $sub->expiration ) && in_array( $sub->status, array( 'active', 'trialling' ), true ) ) {
		?>
		<div class="edd-recurring-subscription-details--renewal">
			<?php
			printf(
				/* translators: the next order date */
				esc_html__( 'Renewing On: %s', 'edd-recurring' ),
				esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sub->expiration ) ) )
			);
			?>
		</div>
		<?php
	}
	?>
</div>
<?php
$initial_payment_id    = ! empty( $order->parent ) ? $order->parent : $order->id;
$subscription_payments = array(
	edd_get_payment( $initial_payment_id ),
);
$renewal_payments      = $sub->get_child_payments();
if ( $renewal_payments ) {
	$subscription_payments = array_merge( $subscription_payments, array_reverse( $renewal_payments ) );
}
if ( empty( $subscription_payments ) ) {
	return;
}
?>
<div>
	<h3><?php esc_html_e( 'Associated Orders', 'edd-recurring' ); ?>:</h3>
	<table class="wp-list-table widefat striped">
		<thead>
			<tr>
				<th class="column-primary"><?php esc_html_e( 'Order', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Total', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Date', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Status', 'edd-recurring' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $subscription_payments as $payment ) {
				$url = add_query_arg(
					array(
						'post_type' => 'download',
						'page'      => 'edd-payment-history',
						'view'      => 'view-order-details',
						'id'        => urlencode( $payment->ID ),
					),
					admin_url( 'edit.php' )
				);
				?>
				<tr>
					<td class="column-primary">
						<?php
						printf(
							'#<a href="%s">%s</a> %s',
							esc_url( $url ),
							esc_html( edd_get_payment_number( $payment->ID ) ),
							(int) $payment->ID === (int) $order->id ? '(' . esc_html__( 'this order', 'edd-recurring' ) . ')' : ''
						);
						?>
						<button type="button" class="toggle-row">
							<span class="screen-reader-text"><?php esc_html_e( 'Show order details', 'edd-recurring' ); ?></span>
						</button>
					</td>
					<td data-colname="<?php esc_html_e( 'Total', 'edd-recurring' ); ?>"><?php echo edd_currency_filter( edd_format_amount( $payment->total ), $payment->currency ); ?></td>
					<td data-colname="<?php esc_html_e( 'Date', 'edd-recurring' ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment->date ) ) ); ?></td>
					<td data-colname="<?php esc_html_e( 'Status', 'edd-recurring' ); ?>"><?php echo esc_html( $payment->status_nicename ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>

