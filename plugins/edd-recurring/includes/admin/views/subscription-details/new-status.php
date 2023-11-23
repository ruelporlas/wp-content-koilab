<div class="edd-recurring-subscription-section edd-recurring-subscription__info edd-form-group-inline">
	<h2><?php esc_html_e( 'Status', 'edd-recurring' ); ?></h2>

	<div class="edd-form-group">
		<label for="edd_recurring_created" class="edd-form-group__label"><?php esc_html_e( 'Date Created:', 'edd-recurring' ); ?></label>
		<div>
			<div class="edd-form-group__control">
				<input type="text" id="edd_recurring_created" name="created" class="edd_datepicker edd-sub-created" value="" />
				<p class="edd-form-group__help description"><?php esc_html_e( 'Optional. The date this subscription was created.', 'edd-recurring' ); ?></p>
			</div>
		</div>
	</div>

	<div class="edd-form-group">
		<label for="expiration" class="edd-form-group__label">
			<?php esc_html_e( 'Expiration Date:', 'edd-recurring' ); ?>
			<span class="required" aria-hidden="true">*</span>
		</label>
		<div>
			<div class="edd-form-group__control">
				<input type="text" id="expiration" name="expiration" class="edd_datepicker edd-sub-expiration" value="" required />
				<p class="edd-form-group__help description"><?php esc_html_e( 'The date the subscription expires or the date of the next automatic renewal payment.', 'edd-recurring' ); ?></p>
			</div>
		</div>
	</div>

	<div class="edd-form-group">
		<label for="edd_recurring_status" class="edd-form-group__label"><?php esc_html_e( 'Subscription Status:', 'edd-recurring' ); ?></label>
		<div class="edd-form-group__control">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo EDD()->html->select(
				array(
					'id'               => 'edd_recurring_status',
					'name'             => 'status',
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'options'          => edd_recurring_get_subscription_statuses(),
					'required'         => true,
					'show_option_all'  => false,
					'show_option_none' => false,
				)
			);
			?>
		</div>
	</div>

</div>
