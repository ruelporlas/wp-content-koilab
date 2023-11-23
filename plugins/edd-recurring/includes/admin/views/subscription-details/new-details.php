<div class="edd-recurring-subscription-section edd-recurring-subscription__details edd-form-group-inline">
	<h2><?php esc_html_e( 'Subscription Details', 'edd-recurring' ); ?></h2>

	<div class="edd-form-group">
		<label for="edd_recurring_bill_times" class="edd-form-group__label"><?php esc_html_e( 'Times Billed:', 'edd-recurring' ); ?></label>
		<div>
			<div class="edd-form-group__control">
				<input type="number" min="0" step="1" id="edd_recurring_bill_times" name="bill_times" value="0" class="small-text" />
				<p class="edd-form-group__help description"><?php esc_html_e( 'This refers to the number of times the subscription will be billed before being marked as Completed and payments stopped. Enter 0 if payments continue indefinitely.', 'edd-recurring' ); ?></p>
			</div>
		</div>
	</div>

	<div class="edd-form-group">
		<label for="edd-recurring-select-payment" class="edd-form-group__label"><?php esc_html_e( 'Initial Purchase ID:', 'edd-recurring' ); ?></label>
		<div>
			<div class="edd-form-group__control">
				<select id="edd-recurring-select-payment" class="edd-recurring-select-payment">
					<option value="0"><?php esc_html_e( 'Create new payment record', 'edd-recurring' ); ?></option>
					<option value="1"><?php esc_html_e( 'Enter existing payment ID', 'edd-recurring' ); ?></option>
				</select>
				<label for="edd_recurring_parent_payment_id" class="screen-reader-text"><?php esc_html_e( 'Existing Order ID:', 'edd-recurring' ); ?></label>
				<input type="number" id="edd_recurring_parent_payment_id" min="1" name="edd_recurring_parent_payment_id" class="edd-recurring-payment-id hidden medium-text" value=""/>
				<p class="edd-form-group__help description"><?php esc_html_e( 'A payment record will be automatically created unless you choose to enter an existing ID. If using an existing payment record, enter the ID here.', 'edd-recurring' ); ?></p>
			</div>
		</div>
	</div>

	<div class="edd-form-group edd-recurring-gateway-wrap">
		<label for="edd_recurring_gateway" class="edd-form-group__label"><?php esc_html_e( 'Gateway:', 'edd-recurring' ); ?></label>
		<div class="edd-form-group__control">
			<select id="edd_recurring_gateway" name="gateway">
				<?php foreach ( edd_get_payment_gateways() as $key => $gateway ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $gateway['admin_label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="edd-form-group">
		<label for="profile_id" class="edd-form-group__label"><?php esc_html_e( 'Profile ID:', 'edd-recurring' ); ?></label>
		<div>
			<div class="edd-form-group__control">
				<input type="text" id="profile_id" name="profile_id" class="edd-sub-profile-id" value=""/>
				<p class="edd-form-group__help description"><?php esc_html_e( 'This is the unique ID of the subscription in the merchant processor, such as PayPal or Stripe.', 'edd-recurring' ); ?></p>
			</div>
		</div>
	</div>

	<div class="edd-form-group">
		<label for="edd_recurring_transaction_id" class="edd-form-group__label"><?php esc_html_e( 'Transaction ID:', 'edd-recurring' ); ?></label>
		<div>
			<div class="edd-form-group__control">
				<input type="text" id="edd_recurring_transaction_id" name="transaction_id" class="edd-sub-transaction-id" value="" />
				<p class="edd-form-group__help description"><?php esc_html_e( 'This is the unique ID of the initial transaction inside of the merchant processor, such as PayPal or Stripe.', 'edd-recurring' ); ?></p>
			</div>
		</div>
	</div>
</div>
