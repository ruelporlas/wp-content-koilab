<div class="edd-subscription-details__section-wrapper">
	<div class="customer-section customer-section__customer">
		<?php require_once 'existing-customer.php'; ?>
	</div>

	<?php do_action( 'edd_subscription_before_tables', $sub ); ?>

	<div class="customer-section customer-section__renewals">
		<h2><?php esc_html_e( 'Renewal Payments:', 'edd-recurring' ); ?></h2>
		<?php if ( 'manual' === $sub->gateway ) : ?>
			<p><strong><?php esc_html_e( 'Note:', 'edd-recurring' ); ?></strong> <?php printf( esc_html__( 'subscriptions purchased with the %s gateway will not renew automatically.', 'edd-recurring' ), esc_html( edd_get_gateway_admin_label( $sub->gateway ) ) ); ?></p>
		<?php endif; ?>
		<table class="wp-list-table widefat striped payments">
			<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Date', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Status', 'edd-recurring' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'edd-recurring' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $payments ) ) : ?>
				<?php foreach ( $payments as $payment ) : ?>
					<tr>
						<td><?php echo esc_html( edd_get_payment_number( $payment->ID ) ); ?></td>
						<td><?php echo esc_html( edd_currency_filter( edd_format_amount( $payment->total ), $payment->currency ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment->date ) ) ); ?></td>
						<td><?php echo esc_html( $payment->status_nicename ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $payment->ID ) ); ?>"><?php esc_html_e( 'View Details', 'edd-recurring' ); ?></a>
							<?php do_action( 'edd_subscription_payments_actions', $sub, $payment ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No Payments Found', 'edd-recurring' ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ( current_user_can( 'publish_shop_payments' ) ) : ?>
		<div class="customer-section customer-section__add-payment">
			<div class="edd-recurring-subscription__add-payment">
				<h2><?php esc_html_e( 'Manually Record a Renewal Payment', 'edd-recurring' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Note: this does not initiate a charge in your merchant processor. This should only be used for recording a missed payment or one that was manually collected.', 'edd-recurring' ); ?></p>
				<?php require_once 'existing-cycle.php'; ?>
				<form id="edd-sub-add-renewal" method="POST">
					<div class="edd-form-group">
						<label for="edd_recurring_amount" class="edd-form-group__label"><?php esc_html_e( 'Total:', 'edd-recurring' ); ?></label>
						<input type="text" class="regular-text edd-price-field" id="edd_recurring_amount" name="amount" value="" placeholder="0.00"/>
					</div>
					<?php if ( edd_use_taxes() ) : ?>
						<div class="edd-form-group">
							<label for="edd_recurring_tax" class="edd-form-group__label"><?php esc_html_e( 'Tax:', 'edd-recurring' ); ?></label>
							<input type="text" class="regular-text edd-price-field" id="edd_recurring_tax" name="tax" value="" placeholder="0.00"/>
						</div>
					<?php endif; ?>
					<div class="edd-form-group edd-recurring-order-date">
						<label for="date" class="edd-form-group__label"><?php esc_html_e( 'Order Date:', 'edd-recurring' ); ?></label>
						<div class="edd-form-group__control">
							<input type="text" id="date" name="date" class="edd_datepicker" value=""/>
							<fieldset>
								<label for="hour" class="screen-reader-text"><?php esc_html_e( 'Order Hour:', 'edd-recurring' ); ?></label>
								<input type="number" id="hour" name="hour" class="edd-form-group__input small-text" max="23" min="0" step="1" placeholder="HH"/> :
								<label for="minute" class="screen-reader-text"><?php esc_html_e( 'Order Minute:', 'edd-recurring' ); ?></label>
								<input type="number" id="minute" name="minute" class="edd-form-group__input small-text" max="59" min="0" step="1" placeholder="MM"/>
							</fieldset>
							<p class="edd-form-group__help description"><?php esc_html_e( 'Optionally set the order date and time (leave it blank to use today\'s date and time). Use your store\'s timezone when choosing a date.', 'edd-recurring' ); ?></p>
						</div>
					</div>
					<div class="edd-form-group">
						<label for="edd_recurring_txn_id" class="edd-form-group__label"><?php esc_html_e( 'Transaction ID:', 'edd-recurring' ); ?></label>
						<input type="text" class="medium-text" id="edd_recurring_txn_id" name="txn_id" value="" placeholder=""/>
					</div>
					<div class="edd-recurring-add-renewal-actions">
						<?php wp_nonce_field( 'edd-recurring-add-renewal-payment', '_wpnonce', false, true ); ?>
						<input type="hidden" name="sub_id" value="<?php echo absint( $sub->id ); ?>" />
						<input type="hidden" name="edd_action" value="add_renewal_payment" />
						<input type="submit" name="renew_and_add_payment" class="button" value="<?php esc_attr_e( 'Record Payment and Renew Subscription', 'edd-recurring' ); ?>"/>
						<input type="submit" name="add_payment_only" class="button" value="<?php esc_attr_e( 'Record Payment Only', 'edd-recurring' ); ?>"/>
					</div>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>
