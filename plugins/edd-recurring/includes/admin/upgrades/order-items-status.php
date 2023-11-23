<?php

/**
 * Manages the upgrade to mark order items with the edd_subscription status as complete.
 *
 * @since 2.11.7
 */
function edd_upgrade_render_recurring_update_order_item_status() {

	$migration_complete = edd_has_upgrade_completed( 'recurring_update_order_item_status' );

	if ( $migration_complete ) {
		?>
		<div id="edd-recurring-migration-complete" class="notice notice-success">
			<p>
				<?php
				printf(
					'<strong>%s:</strong> %s',
					esc_html__( 'Migration complete', 'edd-recurring' ),
					esc_html__( 'You have already completed the subscription order items upgrade.', 'edd-recurring' )
				);
				?>
			</p>
		</div>
		<?php
		return;
	}
	?>
	<div id="edd-recurring-migration-ready" class="notice notice-success" style="display: none;">
		<p>
			<?php
			printf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Database Upgrade Complete', 'edd-recurring' ),
				esc_html__( 'The order items migration has been completed. You may now leave this page.', 'edd-recurring' )
			);
			?>
		</p>
	</div>

	<div id="edd-migration-nav-warn" class="notice notice-info">
		<p>
			<?php
			printf(
				'<strong>%s:</strong> %s',
				esc_html__( 'Important', 'edd-recurring' ),
				esc_html__( 'Please leave this screen open and do not navigate away until the process completes.', 'edd-recurring' )
			);
			?>
		</p>
	</div>

	<div class="metabox-holder">
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e( 'Update subscription records', 'edd-recurring' ); ?></h2>
			<div class="inside update-subscription-records-control">
				<p>
					<?php esc_html_e( 'This update will change the order items status in the database for renewal orders.', 'edd-recurring' ); ?>
				</p>
				<form method="post" id="edd-recurring-update-order-items" class="edd-export-form edd-import-export-form">
					<span class="step-instructions-wrapper">

						<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>

						<?php if ( ! $migration_complete ) : ?>
							<span class="edd-migration allowed">
								<input type="submit" id="update-order-items-submit" value="<?php esc_html_e( 'Update Order Items', 'edd-recurring' ); ?>" class="button-primary"/>
							</span>
						<?php else : ?>
							<input type="submit" disabled id="migrate-logs-submit" value="<?php esc_html_e( 'Update Order Items', 'edd-recurring' ); ?>" class="button-secondary"/>
							&mdash; <?php esc_html_e( 'Order items have already been updated.', 'edd-recurring' ); ?>
						<?php endif; ?>

						<input type="hidden" name="edd-export-class" value="EDD_Recurring_Update_Order_Items" />
						<span class="spinner"></span>
					</span>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	</div>
	<?php
}

/**
 * Registers the order item status batch updater.
 *
 * @since 2.11.7
 * @return void
 */
function edd_recurring_register_order_item_status_update() {
	add_action( 'edd_batch_export_class_include', 'edd_recurring_include_order_items_processor' );
}
add_action( 'edd_register_batch_exporter', 'edd_recurring_register_order_item_status_update' );

/**
 * Loads the order items updater tool.
 *
 * @since 2.11.7
 * @param string $class
 * @return void
 */
function edd_recurring_include_order_items_processor( $class ) {
	if ( 'EDD_Recurring_Update_Order_Items' === $class ) {
		require_once EDD_RECURRING_PLUGIN_DIR . 'includes/admin/upgrades/class-edd-recurring-update-order-items.php';
	}
}
