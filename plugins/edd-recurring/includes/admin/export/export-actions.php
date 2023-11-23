<?php
/**
 * Export Actions
 *
 * @package   edd-recurring
 * @copyright Copyright (c) 2021, Easy Digital Downloads
 * @license   GPL2+
 * @since     3.0
 */

namespace EDD_Recurring\Admin\Export;

/**
 * Includes our class file when the batch export runs.
 *
 * @since 3.0
 */
add_action( 'edd_batch_export_class_include', function ( $class ) {
	if ( ExportSubscriptions::class === $class ) {
		require_once EDD_RECURRING_PLUGIN_DIR . 'includes/admin/export/ExportSubscriptions.php';
	}
} );

/**
 * This is really hacky, but important until EDD core better supports namespaced classes.
 * During the download, `EDD_Recurring\Admin\Export\ExportSubscriptions` get converted to:
 * `EDD_Recurring\\\\Admin\\\\Export\\\\ExportSubscriptions` . This breaks the `class_exists()`
 * check in EDD core ( @see edd_process_batch_export_download() ). Therefore, we hook in before
 * that runs to strip slashes, which makes everything work again. Dumb, but temporarily necessary.
 *
 * @link https://github.com/easydigitaldownloads/easy-digital-downloads/issues/8887
 *
 * @since 3.0
 */
add_action( 'edd_download_batch_export', function() {
	if ( isset( $_REQUEST['class'] ) && ExportSubscriptions::class === stripslashes( $_REQUEST['class'] ) ) {
		$_REQUEST['class'] = ExportSubscriptions::class;
	}
}, -100 );

/**
 * Adds the Export Subscriptions form to the Export page.
 *
 * @since 3.0
 */
add_action( 'edd_reports_tab_export_content_bottom', function () {
	?>
	<div class="postbox edd-export-payment-history">
		<h2 class="hndle"><span><?php esc_html_e( 'Export Subscriptions', 'edd-recurring' ); ?></span></h2>
		<div class="inside">
			<p><?php esc_html_e( 'Download a CSV of all subscriptions. The datepickers can be used to filter by subscription start date; only subscriptions started within that range will be included.', 'edd-recurring' ); ?></p>

			<form id="edd-export-subscriptions" class="edd-export-form edd-import-export-form" method="post">
				<label for="edd-subscriptions-export-product" class="screen-reader-text">
					<?php esc_html_e( 'Select Product', 'edd-recurring' ); ?>
				</label>
				<?php
				echo EDD()->html->product_dropdown( array(
					'name'            => 'product_id',
					'id'              => 'edd-subscriptions-export-product',
					'selected'        => 'all',
					'show_option_all' => sprintf( __( 'All %s', 'edd-recurring' ), edd_get_label_plural() ),
					'chosen'          => true,
					/* translators: the plural post type label */
					'placeholder'     => sprintf( __( 'All %s', 'edd-recurring' ), edd_get_label_plural() ),
				) );
				?>

				<span class="edd-from-to-wrapper">
					<label for="edd-subscriptions-export-start" class="screen-reader-text">
						<?php esc_html_e( 'Set start date', 'edd-recurring' ); ?>
					</label>
					<?php
					echo EDD()->html->date_field( array(
						'id'          => 'edd-subscriptions-export-start',
						'name'        => 'start',
						'placeholder' => __( 'Choose start date', 'edd-recurring' )
					) );
					?>

					<label for="edd-subscriptions-export-end" class="screen-reader-text">
						<?php esc_html_e( 'Set end date', 'edd-recurring' ); ?>
					</label>
					<?php
					echo EDD()->html->date_field( array(
						'id'          => 'edd-subscriptions-export-end',
						'name'        => 'end',
						'placeholder' => __( 'Choose end date', 'edd-recurring' )
					) );
					?>
				</span>

				<label for="edd-subscriptions-status" class="screen-reader-text">
					<?php esc_html_e( 'Filter by status', 'edd-recurring' ); ?>
				</label>
				<?php
				echo EDD()->html->select( array(
					'id'               => 'edd-subscriptions-status',
					'name'             => 'status',
					'options'          => edd_recurring_get_subscription_statuses(),
					'show_option_none' => false,
					'selected'         => 'all',
				) );
				?>

				<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>
				<input type="hidden" name="edd-export-class" value="<?php echo esc_attr( ExportSubscriptions::class ); ?>"/>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Generate CSV', 'edd-recurring' ); ?></button>
			</form>

		</div><!-- .inside -->
	</div><!-- .postbox -->
	<?php
} );
