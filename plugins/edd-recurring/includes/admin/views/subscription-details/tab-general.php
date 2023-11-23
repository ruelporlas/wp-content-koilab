<?php
/**
 * @var EDD_Subscription $sub           The subscription object.
 * @var string           $currency_code The currency code for the first payment
 * @var array            $payments      The subscription payments.
 */
do_action( 'edd_subscription_card_top', $sub );
?>
<form id="edit-item-info" class="edd-subscription-details__section-wrapper" method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-subscriptions&id=' . $sub->id ) ); ?>">

	<?php
	$sections = array( 'customer', 'stats', 'pricing', 'details', 'status' );
	foreach ( $sections as $section ) {
		echo '<div class="customer-section customer-section__' . $section . '">';
		require_once "existing-{$section}.php";
		echo '</div>';
	}
	?>

	<?php do_action( 'edd_subscription_after_tables', $sub ); ?>

	<div id="edd-sub-notices">
		<div class="notice notice-info inline hidden" id="edd-sub-expiration-update-notice">
			<p>
				<?php esc_html_e( 'Changing the expiration date will not affect when renewal payments are processed.', 'edd-recurring' ); ?>
			</p>
		</div>
		<div class="notice notice-info inline hidden" id="edd-sub-product-update-notice">
			<p>
				<?php esc_html_e( 'Changing the product assigned will not automatically adjust any pricing.', 'edd-recurring' ); ?>
			</p>
		</div>
		<div class="notice notice-warning inline hidden" id="edd-sub-profile-id-update-notice">
			<p>
				<?php esc_html_e( 'Changing the profile ID can result in renewals not being processed. Do this with caution.', 'edd-recurring' ); ?>
			</p>
		</div>
	</div>

	<div id="item-edit-actions" class="subscription-actions">
		<?php wp_nonce_field( "edd-recurring-update-{$sub->id}", 'edd-recurring-update-nonce', false, true ); ?>
		<input type="hidden" name="sub_id" value="<?php echo absint( $sub->id ); ?>" />
		<?php if ( $sub->current_user_can() ) : ?>
			<input type="submit" name="edd_update_subscription" id="edd_update_subscription" class="button button-primary" value="<?php esc_html_e( 'Update Subscription', 'edd-recurring' ); ?>"/>
		<?php endif; ?>
		<?php if ( $sub->current_user_can() && $sub->can_cancel() ) : ?>
			<a class="button button-secondary edd-cancel-subscription" href="<?php echo esc_url( $sub->get_cancel_url() ); ?>"><?php esc_html_e( 'Cancel Subscription', 'edd-recurring' ); ?></a>
		<?php endif; ?>
		<?php if ( $sub->current_user_can() && $sub->can_reactivate() ) : ?>
			<a class="button" href="<?php echo esc_url( $sub->get_reactivation_url() ); ?>" ><?php esc_html_e( 'Reactivate Subscription', 'edd-recurring' ); ?></a>
		<?php endif; ?>
		<?php if ( $sub->current_user_can() && $sub->can_retry() ) : ?>
			<a class="button" href="<?php echo esc_url( $sub->get_retry_url() ); ?>" ><?php esc_html_e( 'Retry Renewal', 'edd-recurring' ); ?></a>
		<?php endif; ?>
		<?php if ( $sub->current_user_can( 'delete_subscriptions' ) ) : ?>
			<input type="submit" name="edd_delete_subscription" class="edd-delete-subscription button" value="<?php esc_html_e( 'Delete Subscription', 'edd-recurring' ); ?>"/>
		<?php endif; ?>
	</div>

</form>
