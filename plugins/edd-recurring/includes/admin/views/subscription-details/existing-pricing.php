<?php
/**
 * @var EDD_Subscription $sub           The subscription object.
 * @var string           $currency_code The currency code for the first payment
 */
?>
<div class="edd-recurring-subscription-section edd-recurring-subscription__pricing">
	<h2><?php esc_html_e( 'Pricing', 'edd-recurring' ); ?></h2>
	<div class="edd-form-group">
		<?php
		$selected = $sub->product_id;
		$download = edd_get_download( $selected );
		if ( ! is_null( $sub->price_id ) && edd_has_variable_prices( $sub->product_id ) ) {
			$selected .= '_' . $sub->price_id;
		}
		?>
		<label for="edd_recurring_product_id" class="edd-form-group__label">
			<?php
			esc_html_e( 'Product:', 'edd-recurring' );
			if ( $download instanceof EDD_Download && current_user_can( 'edit_product', $sub->product_id ) ) {
				$view_download = add_query_arg(
					array(
						'post'   => urlencode( $sub->product_id ),
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				);
				?>
				(<a href="<?php echo esc_url( $view_download ); ?>"><?php printf( esc_html__( 'View %s', 'edd-recurring' ), esc_html( edd_get_label_singular() ) ); ?></a>)
				<?php
			}
			?>
		</label>
		<div class="edd-form-group__control">
			<?php
			if ( current_user_can( 'manage_subscriptions' ) ) {
				add_filter( 'edd_product_dropdown_args', 'edd_recurring_product_dropdown_recurring_only' );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo EDD()->html->product_dropdown(
					array(
						'selected'             => $selected,
						'chosen'               => true,
						'id'                   => 'edd_recurring_product_id',
						'name'                 => 'product_id',
						'class'                => 'edd-sub-product-id',
						'variations'           => true,
						'show_variations_only' => true,
						'required'             => true,
					)
				);
				remove_filter( 'edd_product_dropdown_args', 'edd_recurring_product_dropdown_recurring_only' );
			} else {
				echo esc_html( function_exists( 'edd_get_download_name' ) ? edd_get_download_name( (int) $sub->product_id ) : get_the_title( (int) $sub->product_id ) );
			}
			?>
		</div>
	</div>

	<?php require_once 'existing-cycle.php'; ?>
</div> <!-- ends __pricing !-->
