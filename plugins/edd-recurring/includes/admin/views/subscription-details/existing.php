<?php
/**
 * @var EDD_Subscription $sub
 */

if ( ! current_user_can( 'view_subscriptions' ) ) {
	edd_set_error( 'edd-no-access', __( 'You are not permitted to view this data.', 'edd-recurring' ) );
}

$sub_id = filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT );
if ( ! $sub_id ) {
	edd_set_error( 'edd-invalid_subscription', __( 'Invalid subscription ID provided.', 'edd-recurring' ) );
}

$sub = new EDD_Subscription( $sub_id );
if ( empty( $sub->id ) ) {
	edd_set_error( 'edd-invalid_subscription', __( 'Invalid subscription ID provided.', 'edd-recurring' ) );
}

if ( edd_get_errors() ) {
	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'Subscription Details', 'edd-recurring' ); ?></h1>
		<div class="error settings-error">
			<?php edd_print_errors(); ?>
		</div>
	</div>
	<?php
	return;
}

$currency_code = edd_get_payment_currency_code( $sub->parent_payment_id );

$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'general';
$tabs = edd_recurring_get_subscription_tabs();
// @todo remove this condition and output when EDD minimum is 3.0.
$edd3 = function_exists( 'edd_get_order' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Subscription Details', 'edd_recurring' ); ?></h1>

	<div id="edd-item-wrapper" class="edd-item-has-tabs edd-clearfix edd-recurring-subscription__item-wrapper edd-vertical-sections edd-sections-wrap">
				<?php if ( ! $edd3 ) { ?>
					<div id="edd-item-tab-wrapper">
				<?php } ?>
					<ul id="edd-item-tab-wrapper-list" class="customer-tab-wrapper-list section-nav">
						<?php
						foreach ( $tabs as $key => $tab ) :
							$active = $key === $view;
							$url    = '';
							$class  = 'section-title';
							if ( $active ) {
								$class .= ' section-title--is-active active';
							} else {
								$class .= ' inactive';
							}
							?>

							<li class="<?php echo esc_attr( $class ); ?>">
								<?php if ( ! $active ) :
									$url = add_query_arg(
										array(
											'post_type' => 'download',
											'page'      => 'edd-subscriptions',
											'view'      => urlencode( $key ),
											'id'        => urlencode( $sub->id ),
										),
										admin_url( 'edit.php' )
									)
									?>
								<?php endif; ?>
								<a href="<?php echo esc_url( $url ); ?>">
									<span class="dashicons <?php echo sanitize_html_class( $tab['dashicon'] ); ?>" aria-hidden="true"></span>
									<span class="edd-item-tab-label"><?php echo esc_attr( $tab['title'] ); ?></span>
								</a>
							</li>

						<?php endforeach; ?>
					</ul>
				<?php if ( ! $edd3 ) { ?>
					</div>
				<?php } ?>
				<div class="subscription-details__id">
					#<?php echo esc_html( $sub->id ); ?>
					<?php
						if ( 'active' !== $sub->status ) {
							echo ' &mdash; ' . esc_html( $sub->get_status_label() );
						}
					?>
				</div>
				<div id="edd-item-card-wrapper" class="section-wrap edd-subscription-card-wrapper section-content">
					<div class="info-wrapper item-section">
						<?php
						$payments = $sub->get_child_payments();
						require_once "tab-{$view}.php";
						?>
					</div>
				</div>

		</div>
</div>
