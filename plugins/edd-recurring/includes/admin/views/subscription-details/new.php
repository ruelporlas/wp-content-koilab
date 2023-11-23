<?php

if ( ! current_user_can( 'manage_subscriptions' ) ) {
	edd_set_error( 'edd-no-access', __( 'You are not permitted to create new subscriptions.', 'edd-recurring' ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Add New Subscription', 'edd-recurring' ); ?></h1>
		<div class="error settings-error">
			<?php edd_print_errors(); ?>
		</div>
	</div>
	<?php
	return;
}
$form_action = add_query_arg(
	array(
		'post_type' => 'download',
		'page'      => 'edd-subscriptions',
	),
	admin_url( 'edit.php' )
);

?>
<div class="wrap edd-recurring-subscription" id="edd-recurring-new-subscription-wrap">
	<h1><?php esc_html_e( 'Add New Subscription', 'edd-recurring' ); ?></h1>

	<div id="edd-item-card-wrapper" class="edd-recurring-subscription__item-wrapper edd-recurring-subscription__item-wrapper_add-new">

		<?php do_action( 'edd_new_subscription_card_top' ); ?>

		<div class="info-wrapper item-section">
			<div class="section-wrap edd-subscription-card-wrapper section-content">

				<form id="edit-item-info" class="edd-subscription-details__section-wrapper" method="post" action="<?php echo esc_url( $form_action ); ?>">

					<div class="edd-subscription-details__warning">
						<p>
							<strong><?php esc_html_e( 'Note:', 'edd-recurring' ); ?></strong> <br>
							<?php echo wp_kses_post( 'This tool allows you to create a new subscription record. It will not create a payment profile in your merchant processor. Payment profiles in the merchant processor must be created through your merchant portal. Once created in the merchant portal, details such as transaction ID and billing profile ID, can be entered here.', 'edd-recurring' ); ?>
						</p>
					</div>

					<?php
					$sections = array( 'customer', 'pricing', 'details', 'status' );
					foreach ( $sections as $section ) {
						$class = '';
						if ( 'customer' != $section ) {
							$class = ' edd-recurring-subscription__reveal';
						}
						echo '<div class="customer-section customer-section__' . $section .  $class . '">';
						require_once "new-{$section}.php";
						echo '</div>';
					}
					?>
					<div id="item-edit-actions" class="subscription-actions  edd-recurring-subscription__reveal">
						<?php wp_nonce_field( 'edd-recurring-add-subscription', 'edd-recurring-add-subscription-nonce', false, true ); ?>
						<input type="hidden" name="edd_action" class="button button-primary" value="add_subscription"/>
						<input type="submit" name="edd_new_subscription" id="edd_add_subscription" class="button button-primary" value="<?php esc_html_e( 'Add Subscription', 'edd-recurring' ); ?>"/>
					</div>

				</form>
			</div>
		</div>
	</div>
	<?php do_action( 'edd_new_subscription_card_bottom' ); ?>
</div>
