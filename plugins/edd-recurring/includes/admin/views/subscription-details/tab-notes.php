<?php
/**
 * @var EDD_Subscription $sub
 */
?>
<div class="edd-subscription-details__section-wrapper">
	<div class="customer-section customer-section__customer">
		<?php require_once 'existing-customer.php'; ?>
	</div>

	<?php do_action( 'edd_subscription_before_notes', $sub ); ?>

	<div class="customer-section customer-section__notes">
		<h2><?php esc_html_e( 'Notes:', 'edd-recurring' ); ?></h2>
		<?php
		$notes = $sub->get_notes( 1000 );
		if ( $notes ) {
			?>
			<div class="edd-subscription-notes">
				<?php
				foreach ( $notes as $key => $note ) {
					?>
					<div class="edd-subscription-note"><?php echo esc_html( $note ); ?></div>
					<?php
				}
				?>
			</div>
			<?php
		}

		?>
		<form id="edd-sub-add-note" method="POST">
			<div class="edd-form-group">
				<label for="edd_recurring_note" class="edd-form-group__label"><?php esc_html_e( 'Add Note:', 'edd-recurring' ); ?></label>
				<textarea id="edd_recurring_note" name="note" class="edd-form-group__input edd-subscription-note-input" style="width:100%;" rows="8"></textarea>
			</div>
			<?php wp_nonce_field( 'edd-recurring-add-note', '_wpnonce', false, true ); ?>
			<input type="hidden" name="sub_id" value="<?php echo absint( $sub->id ); ?>" />
			<input type="hidden" name="edd_action" value="add_subscription_note" />
			<p class="submit">
				<input type="submit" name="add_note" class="button alignright" value="<?php esc_attr_e( 'Add Note', 'edd-recurring' ); ?>"/>
			</p>
		</form>
	</div>

	<?php do_action( 'edd_subscription_after_notes', $sub ); ?>
</div>
