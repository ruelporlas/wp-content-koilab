<?php
/**
 * @var EDD_Subscription $sub      The subscription object.
 * @var array            $payments The payments for this subscription.
 */
do_action( 'edd_subscription_before_stats', $sub );
?>
<div id="edd-item-stats-wrapper" class="item-section subscription-stats-section">
	<ul>
		<li>
			<span class="dashicons dashicons-chart-area"></span>
			<?php echo esc_html( edd_currency_filter( edd_format_amount( $sub->get_lifetime_value() ), $currency_code ) ) . ' ' . esc_html__( 'Lifetime Value', 'edd-recurring' ); ?>
		</li>
		<li>
			<span class="dashicons dashicons-cart"></span>
			<?php echo count( $payments ) . ' ' . esc_html( _n( 'Renewal', 'Renewals', count( $payments ), 'edd-recurring' ) ); ?>
		</li>
		<?php do_action( 'edd_subscription_stats_list', $sub ); ?>
	</ul>
</div>
