<?php
/**
 * PayPal Commerce: Payment Processing
 *
 * This message is shown as we attempt to verify the initial payment in a subscription.
 *
 * @package   edd-recurring
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 */
?>
<form id="edd-payment-processing" action="<?php echo esc_url( edd_get_ajax_url() ); ?>" method="POST">
	<p>
		<?php
		printf(
			esc_html__( 'Please wait while your payment is confirmed. This may take up to a minute; the page will reload automatically.', 'edd-recurring' )
		)
		?>
	</p>

	<p id="edd-paypal-spinner">
		<span class="edd-loading-ajax edd-loading"></span>
	</p>

	<input type="hidden" name="action" value="edd_recurring_confirm_transaction">
	<input type="hidden" name="subscription_id" value="<?php echo ! empty( $_GET['subscription-id'] ) ? esc_attr( $_GET['subscription-id'] ) : 0; ?>"
	<?php wp_nonce_field( 'edd_recurring_confirm_paypal_transaction' ); ?>
</form>
