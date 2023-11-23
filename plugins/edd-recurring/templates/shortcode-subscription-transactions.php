<?php
/**
 *  EDD Template File for [edd_subscriptions] shortcode with the 'view_transactions' action.
 *
 * @description: Place this template file within your theme directory under /my-theme/edd_templates/
 *
 * @copyright  : http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      : 2.11.5
 */

if ( ! is_user_logged_in() ) {
	return;
}

$subscription = false;
if ( isset( $_GET['subscription_id'] ) && is_numeric( $_GET['subscription_id'] ) ) {
	$subscription_id = absint( $_GET['subscription_id'] );
	$subscription    = new EDD_Subscription( $subscription_id );
}
if ( ! $subscription instanceof EDD_Subscription || empty( $subscription->id ) ) {
	return;
}
$subscriber = new EDD_Recurring_Subscriber( get_current_user_id(), true );
if ( empty( $subscriber->id ) || $subscription->customer_id !== $subscriber->id ) {
	return;
}
$payments   = $subscription->get_child_payments();
$payments[] = edd_get_payment( $subscription->parent_payment_id );
if ( ! $payments ) {
	return;
}
$action_url = remove_query_arg( array( 'subscription_id', 'view_transactions' ), edd_get_current_page_url() );
?>
<a href="<?php echo esc_url( $action_url ); ?>">&larr;&nbsp;<?php esc_html_e( 'Back', 'edd-recurring' ); ?></a>
<h3><?php esc_html_e( 'Transactions for Subscription #', 'edd-recurring' ); ?><?php echo esc_html( $subscription->id ); ?></h3>
<table class="edd-recurring-subscription-transactions">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Order', 'edd-recurring' ); ?></th>
			<th><?php esc_html_e( 'Order Amount', 'edd-recurring' ); ?></th>
			<th><?php esc_html_e( 'Order Date', 'edd-recurring' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $payments as $payment ) {
			$receipt_uri = function_exists( 'edd_get_receipt_page_uri' ) ?
				edd_get_receipt_page_uri( $payment->ID ) :
				add_query_arg( 'payment_key', edd_get_payment_key( $payment->ID ), edd_get_success_page_uri() );
			?>
			<tr>
				<td>
					<a
						href="<?php echo esc_url( $receipt_uri ); ?>"
					>
						<?php echo esc_html( edd_get_payment_number( $payment->ID ) ); ?>
					</a>
					<?php
					if ( $payment->ID == $subscription->parent_payment_id ) {
						echo ' ' . esc_html__( '(original order)', 'edd-recurring' );
					}
					?>
				</td>
				<td><?php echo esc_html( edd_currency_filter( edd_format_amount( edd_get_payment_amount( $payment->ID ) ), edd_get_payment_currency_code( $payment->ID ) ) ); ?></td>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment->date ) ) ); ?></td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>
<?php
