<div class="edd-recurring-subscription-section edd-recurring-subscription__customer">
	<h2><?php esc_html_e( 'Customer', 'edd-recurring' ); ?></h2>

	<div class="edd-recurring-selected-customer hidden">
		<span class="edd-recurring-selected-customer-details"></span> <br>
		<button type="button" class="button edd-recurring-change-customer"><?php esc_html_e( 'Change customer', 'edd-recurring' ); ?></button>
	</div>

	<div class="subscription-actions">
		<button type="button" class="button button-primary edd-recurring-select-customer"><?php esc_html_e( 'Select existing customer', 'edd-recurring' ); ?></button>
		<button type="button" class="button button-secondary edd-recurring-new-customer"><?php esc_html_e( 'Create new customer', 'edd-recurring' ); ?></button>
	</div>

	<div class="edd-form-group edd-recurring-customer-wrap-existing hidden">
		<label for="edd_recurring_customer_id" class="edd-form-group__label">
			<?php esc_html_e( 'Select Existing Customer:', 'edd-recurring' ); ?>
			<span class="required" aria-hidden="true"> *</span>
		</label>
		<?php
		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo EDD()->html->customer_dropdown(
			array(
				'id'       => 'edd_recurring_customer_id',
				'name'     => 'customer_id',
				'class'    => 'edd-form-group__input edd-recurring-customer',
				'required' => true,
			)
		);
		?>
		<button type="button" class="button edd-recurring-confirm-customer"><?php esc_html_e( 'Confirm customer', 'edd-recurring' ); ?></button>
	</div>
	<div class="edd-form-group edd-recurring-customer-wrap-new hidden">
		<label for="edd_recurring_customer_email" class="edd-form-group__label">
			<?php esc_html_e( 'New Customer Email:', 'edd-recurring' ); ?>
			<span class="required" aria-hidden="true">*</span>
		</label>
		<input
			type="email"
			class="edd-form-group__input regular-text edd-recurring-customer"
			id="edd_recurring_customer_email"
			name="customer_email"
			value=""
			placeholder="<?php esc_html_e( 'Enter customer email', 'edd-recurring' ); ?>"
			required
		/>
		<button type="button" class="button edd-recurring-confirm-customer"><?php esc_html_e( 'Confirm customer', 'edd-recurring' ); ?></button>
	</div>
</div>
