<div class="edd-form-group">
	<div class="edd-form-group__label"><?php esc_html_e( 'Billing Cycle:', 'edd-recurring' ); ?></div>
	<div class="edd-form-group__control">
		<?php
		$frequency      = EDD_Recurring()->get_pretty_subscription_frequency( $sub->period );
		$billing        = edd_currency_filter( edd_format_amount( $sub->recurring_amount ), $currency_code ) . ' / ' . $frequency;
		$initial        = edd_currency_filter( edd_format_amount( $sub->initial_amount ), $currency_code );
		$has_tax_rate   = ! empty( (float) $sub->initial_tax_rate ) || ! empty( (float) $sub->recurring_tax_rate );
		$has_tax_amount = ! empty( (float) $sub->initial_tax ) || ! empty( (float) $sub->recurring_tax );
		printf( esc_html_x( '%1$s then %2$s', 'Initial subscription amount then billing cycle and amount', 'edd-recurring' ), $initial, $billing );
		?>
		<?php if ( $has_tax_rate || $has_tax_amount ) { ?>
			<span>&nbsp;&ndash;&nbsp;</span>
			<a class="edd-item-toggle-next-hidden-row" href=""><?php echo esc_html_x( 'View Details', 'view billing cycle details on single subscription admin page', 'edd-recurring' ); ?></a>
			<div class="edd-item-hidden-row" style="display: none;">
				<?php if ( $has_tax_rate ) { ?>

					<div class="edd-item-hidden-row__item">
						<strong><?php esc_html_e( 'Tax Rate:', 'edd-recurring' ); ?></strong>
						<?php
						$initial_tax_rate   = ! empty( $sub->initial_tax_rate ) && is_numeric( $sub->initial_tax_rate ) ? ( $sub->initial_tax_rate * 100 ) : 0.00;
						$recurring_tax_rate = ! empty( $sub->recurring_tax_rate ) && is_numeric( $sub->recurring_tax_rate ) ? ( $sub->recurring_tax_rate * 100 ) : 0.00;
						printf(
							/* translators: %1$s Initial tax rate. %2$s Billing tax rate and cycle length */
							esc_html_x( '%1$s then %2$s', 'edd-recurring' ),
							esc_html( $initial_tax_rate ) . '%',
							esc_html( $recurring_tax_rate . '% / ' . $frequency )
						);
						?>
					</div>

					<?php
				}
				if ( $has_tax_amount ) {
					?>

					<div class="edd-item-hidden-row__item">
						<strong><?php esc_html_e( 'Tax Amount:', 'edd-recurring' ); ?></strong>
						<?php
						printf(
							/* translators: %1$s Initial tax value. %2$s Billing tax value and cycle length */
							esc_html_x( '%1$s then %2$s', 'Initial subscription tax value then recurring tax value and billing cycle.', 'edd-recurring' ),
							edd_currency_filter( edd_format_amount( $sub->initial_tax ), $currency_code ),
							edd_currency_filter( edd_format_amount( $sub->recurring_tax ), $currency_code ) . ' / ' . $frequency
						);
						?>
					</div>

				<?php } ?>
			</div>
		<?php } ?>
	</div>
</div>
