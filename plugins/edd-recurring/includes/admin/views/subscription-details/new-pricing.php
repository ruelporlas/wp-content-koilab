<div class="edd-recurring-subscription-section edd-recurring-subscription__pricing">
	<h2><?php esc_html_e( 'Pricing', 'edd-recurring' ); ?></h2>
	<div class="edd-form-group">
		<label for="products" class="edd-form-group__label">
			<?php esc_html_e( 'Product:', 'edd-recurring' ); ?>
			<span class="required" aria-hidden="true">*</span>
		</label>
		<div class="edd-form-group__control">
			<?php
			add_filter( 'edd_product_dropdown_args', 'edd_recurring_product_dropdown_recurring_only' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo EDD()->html->product_dropdown(
				array(
					'name'       => 'product_id',
					'chosen'     => true,
					'variations' => false,
					'required'   => true,
				)
			);
			remove_filter( 'edd_product_dropdown_args', 'edd_recurring_product_dropdown_recurring_only' );
			?>
			<span class="edd-recurring-price-option-wrap"></span>
		</div>
	</div>

	<fieldset class="edd-form-group edd-recurring-subscription__billing">
		<legend class="edd-form-group__label">
			<?php esc_html_e( 'Price and Billing Cycle:', 'edd-recurring' ); ?>
			<span class="required" aria-hidden="true">*</span>
		</legend>
		<div class="edd-form-group__control">
			<?php
			$currency_position = edd_get_option( 'currency_position', 'before' );
			if ( 'before' === $currency_position ) {
				?>
				<label for="edd_recurring_initial_amount" class="edd-form-group__label screen-reader-text"><?php esc_html_e( 'Initial Amount', 'edd-recurring' ); ?></label>
				<span class="edd-amount-control__currency is-before"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>
				<input type="text" id="edd_recurring_initial_amount" name="initial_amount" class="medium-text edd-price-field" placeholder="0.00" value="" required/>
				<?php echo esc_html_x( 'then', 'Initial subscription amount then billing cycle and amount', 'edd-recurring' ); ?>
				<label for="edd_recurring_recurring_amount" class="edd-form-group__label screen-reader-text"><?php esc_html_e( 'Recurring Amount', 'edd-recurring' ); ?></label>
				<span class="edd-amount-control__currency is-before"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>
				<input type="text" id="edd_recurring_recurring_amount" name="recurring_amount" class="medium-text edd-price-field" placeholder="0.00" value="" required />
				<?php
			} else {
				?>
				<label for="edd_recurring_initial_amount" class="edd-form-group__label screen-reader-text"><?php esc_html_e( 'Initial Amount', 'edd-recurring' ); ?></label>
				<input type="text" id="edd_recurring_initial_amount" name="initial_amount" class="medium-text edd-price-field" placeholder="0.00" value="" required/>
				<span class="edd-amount-control__currency is-after"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>
				<?php echo esc_html_x( 'then', 'Initial subscription amount then billing cycle and amount', 'edd-recurring' ); ?>
				<label for="edd_recurring_recurring_amount" class="edd-form-group__label screen-reader-text"><?php esc_html_e( 'Recurring Amount', 'edd-recurring' ); ?></label>
				<input type="text" id="edd_recurring_initial_amount" name="recurring_amount" class="medium-text edd-price-field" placeholder="0.00" value="" required />
				<span class="edd-amount-control__currency is-after"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>
				<?php
			}
			?>
		</div>
		<div class="edd-form-group__control">
			<label for="edd_recurring_period" class="edd-form-group__label screen-reader-text"><?php esc_html_e( 'Subscription Period', 'edd-recurring' ); ?></label>
			<select id="edd_recurring_period" name="period">
				<?php $periods = EDD_Recurring()->periods(); ?>
				<?php foreach ( $periods as $key => $value ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_attr( $value ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</fieldset>
</div>
