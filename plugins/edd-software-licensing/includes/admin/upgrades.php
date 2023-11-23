<?php

function edd_sl_register_upgrades_page() {

	if( ! function_exists( 'EDD' ) ) {
		return;
	}

	add_submenu_page( null, __( 'EDD SL Upgrades', 'edd_sl' ), __( 'EDD Upgrades', 'edd_sl' ), 'install_plugins', 'edd-sl-upgrades', 'edd_sl_upgrades_screen' );
}
add_action( 'admin_menu', 'edd_sl_register_upgrades_page', 10 );

function edd_sl_upgrades_screen() {
	add_filter( 'edd_load_admin_scripts', '__return_true' );
	?>
	<div class="wrap">
		<h2><?php _e( 'Software Licensing - Upgrades', 'edd_sl' ); ?></h2>
		<?php
		$routine = sanitize_key( $_GET['edd-upgrade'] );
		do_action( 'edd_sl_render_' . $routine );
		?>
	</div>
	<?php
}

/**
 * Triggers all upgrade functions
 *
 * @since 2.2
 * @return void
*/
function edd_sl_show_upgrade_notice() {
	global $wpdb;

	if( ! function_exists( 'EDD' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$current_screen = get_current_screen();
	if ( 'dashboard_page_edd-sl-upgrades' === $current_screen->id ) {
		return;
	}

	$edd_sl_version = get_option( 'edd_sl_version' );
	if ( version_compare( $edd_sl_version, '3.6 ', '<' ) ) {
		edd_software_licensing()->roles->add_caps();
	}

	$licenses_migrated       = edd_has_upgrade_completed( 'migrate_licenses' );
	$bundle_licenses_updated = edd_has_upgrade_completed( 'migrate_license_parent_child' );
	$license_logs_updated    = edd_has_upgrade_completed( 'migrate_license_logs' );
	$removed_legacy_licenses = edd_has_upgrade_completed( 'remove_legacy_licenses' );

	// See https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1499
	$fix_no_url_check_activation_counts = edd_has_upgrade_completed( 'fix_no_url_check_activation_counts' );

	// See https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1456
	$increase_license_key_column = edd_has_upgrade_completed( 'increase_license_key_column' );

	if ( ! $licenses_migrated ) {

		// Check to see if we have licenses in the Database
		$results      = $wpdb->get_row( "SELECT count(ID) as has_licenses FROM $wpdb->posts WHERE post_type = 'edd_license' LIMIT 0, 1" );
		$has_licenses = ! empty( $results->has_licenses ) ? true : false;

		if ( ! $has_licenses ) {
			edd_set_upgrade_complete( 'migrate_licenses' );
			edd_set_upgrade_complete( 'migrate_license_parent_child' );
			edd_set_upgrade_complete( 'migrate_license_logs' );
			edd_set_upgrade_complete( 'remove_legacy_licenses' );
		} elseif ( function_exists( 'edd_get_orders' ) && ! edd_has_upgrade_completed( 'migrate_orders' ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html__( 'Your Software Licensing database needs an upgrade. Once you have completed the migration to Easy Digital Downloads 3.0, the upgrade prompt will appear here.', 'edd_sl' )
			);
		} else {
			printf(
				'<div class="updated">' .
				'<p>' .
				__( 'Easy Digital Downloads - Software Licensing needs to upgrade the licenses database, click <a href="%s">here</a> to start the upgrade. <a href="#" onClick="jQuery(this).parent().next(\'p\').slideToggle()">Learn more about this upgrade</a>.', 'edd_sl' ) .
				'</p>' .
				'<p style="display: none;">' .
				__( '<strong>About this upgrade:</strong><br />This is a <strong><em>mandatory</em></strong> update that will migrate all licenses and their meta data to a new custom database table. This upgrade should provide better performance and scalability.', 'edd_sl' ) .
				'<br /><br />' .
				__( '<strong>Please back up your database before starting this upgrade.</strong> This upgrade routine will make irreversible changes to the database.', 'edd_sl' ) .
				'<br /><br />' .
				__( '<strong>Advanced User?</strong><br />This upgrade can also be run via WP-CLI with the following command:<br /><code>wp edd-sl migrate_licenses</code>', 'edd_sl' ) .
				'<br /><br />' .
				__( 'For large sites, this is the recommended method of upgrading.', 'edd_sl' ) .
				'</p>' .
				'</div>',
				esc_url( admin_url( 'index.php?page=edd-sl-upgrades&edd-upgrade=licenses_migration' ) )
			);
		}
	}

	if ( $licenses_migrated && ( ! $bundle_licenses_updated || ! $license_logs_updated ) ) {

		printf(
			'<div class="error">' .
			'<p>' .
			__( 'Easy Digital Downloads - Software Licensing still needs to complete the upgrade to the licenses database, click <a href="%s">here</a> to continue the upgrade.', 'edd_sl' ) .
			'</p>' .
			'</div>',
			esc_url( admin_url( 'index.php?page=edd-sl-upgrades&edd-upgrade=licenses_migration' ) )
		);

	}

	if ( ( $licenses_migrated && $bundle_licenses_updated && $license_logs_updated ) && ! $removed_legacy_licenses ) {
		printf(
			'<div class="updated">' .
			'<p>' .
			__( 'Easy Digital Downloads - Software Licensing has <strong>finished upgrading the licenses database</strong>. The final step is to <a href="%s">remove the legacy data</a>. <a href="#" onClick="jQuery(this).parent().next(\'p\').slideToggle()">Learn more about this process</a>.', 'edd_sl' ) .
			'</p>' .
			'<p style="display: none;">' .
			__( '<strong>Removing legacy data:</strong><br />All licenses have been migrated to their own custom table. Now all old data needs to be removed.', 'edd_sl' ) .
			'<br /><br />' .
			__( '<strong>If you have not already, back up your database</strong> as this upgrade routine will be making changes to the database that are not reversible.', 'edd_sl' ) .
			'</p>' .
			'</div>',
			esc_url( admin_url( 'index.php?page=edd-sl-upgrades&edd-upgrade=licenses_migration' ) )
		);
	}

	if ( $licenses_migrated && ! $fix_no_url_check_activation_counts ) {
		$url_checking_disabled = edd_software_licensing()->force_increase();
		if ( false === $url_checking_disabled ) {
			edd_set_upgrade_complete( 'fix_no_url_check_activation_counts' );
		} else {
			printf(
				'<div class="updated">' .
				'<p>' .
				__( 'Easy Digital Downloads - Software Licensing needs to <a href="%s">update your license activation records</a>.', 'edd_sl' ) .
				'</p>' .
				'</div>',
				esc_url( admin_url( 'index.php?page=edd-sl-upgrades&edd-upgrade=fix_no_url_check_activation_counts' ) )
			);
		}
	}

	if ( ! $increase_license_key_column ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'edd_licenses';
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY license_key varchar(255) NOT NULL;" );
		edd_set_upgrade_complete( 'increase_license_key_column' );
	}
}
add_action( 'admin_notices', 'edd_sl_show_upgrade_notice' );

function edd_sl_render_licenses_migration() {
	global $wpdb;

	$migration_complete = edd_has_upgrade_completed( 'migrate_licenses' );

	$has_child_licenses     = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_type = 'edd_license' AND post_parent != 0 LIMIT 1" );
	$relationships_complete = edd_has_upgrade_completed( 'migrate_license_parent_child' );
	if ( empty( $has_child_licenses ) ) {
		edd_set_upgrade_complete( 'migrate_license_parent_child' );
		$relationships_complete = true;
	}

	$has_license_logs = $wpdb->get_var( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_edd_sl_log_license_id' LIMIT 1" );
	$logs_complete    = edd_has_upgrade_completed( 'migrate_license_logs' );
	if ( empty( $has_license_logs ) ) {
		edd_set_upgrade_complete( 'migrate_license_logs' );
		$logs_complete = true;
	}

	$removal_complete   = edd_has_upgrade_completed( 'remove_legacy_licenses' );

	if ( $migration_complete && $removal_complete && $logs_complete && $removal_complete ) : ?>
		<div id="edd-sl-migration-complete" class="notice notice-success">
			<p>
				<?php _e( '<strong>Migration complete:</strong> You have already completed the migration of licenses to custom database tables.', 'edd_sl' ); ?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div id="edd-sl-migration-ready" class="notice notice-success" style="display: none;">
		<p>
			<?php _e( '<strong>Database Upgrade Complete:</strong> All database upgrades have been completed. We recommended you now verify your store\'s operations are functioning as expected.', 'edd_sl' ); ?>
			<br /><br />
			<?php _e( 'You may now leave this page.', 'edd_sl' ); ?>
		</p>
	</div>
	<?php

	$step = 1;
	?>
	<div id="edd-sl-migration-nav-warn" class="notice notice-info">
		<p>
			<?php _e( '<strong>Important:</strong> Please leave this screen open and do not navigate away until the process completes.', 'edd_sl' ); ?>
		</p>
	</div>

	<style>
		.dashicons.dashicons-yes { display: none; color: rgb(0, 128, 0); vertical-align: middle; }
	</style>
	<?php if ( ! $removal_complete ) : ?>
	<?php wp_enqueue_script( 'edd-admin-tools-export' ); ?>
	<script>
	( function ( $ ) {
		$( document ).ready(function() {
			$(document).on("DOMNodeInserted", function (e) {
				var element = e.target;

				if ( element.id === 'edd-batch-success' ) {
					element = $(element);

					element.parent().prev().find('.edd-sl-migration.allowed').hide();
					element.parent().prev().find('.edd-sl-migration.unavailable').show();
					var element_wrapper = element.parents().eq(4);
					element_wrapper.find('.dashicons.dashicons-yes').show();

					var auto_start_next_step = true;

					if (element.find('.edd-sl-new-count')) {
						var new_count = element.find('.edd-sl-new-count').text(),
							old_count = element.find('.edd-sl-old-count').text();

						auto_start_next_step = new_count === old_count;
					}

					var next_step_wrapper = element_wrapper.next();
					if ( next_step_wrapper.find('.postbox').length) {
						next_step_wrapper.find('.edd-sl-migration.allowed').show();
						next_step_wrapper.find('.edd-sl-migration.unavailable').hide();

						if ( auto_start_next_step ) {
							next_step_wrapper.find('.edd-export-form').submit();
						}
					} else {
						$('#edd-sl-migration-nav-warn').hide();
						$('#edd-sl-migration-ready').slideDown();
					}

				}
			});
		});
	} );
	</script>
	<?php endif; ?>

	<div class="metabox-holder">
		<div class="postbox">
			<h2 class="hndle">
				<span><?php printf( __( 'Step %d: Upgrade Licenses Database', 'edd_sl' ), $step ); ?></span>
				 <span class="dashicons dashicons-yes"></span>
			</h2>
			<div class="inside migrate-licenses-control">
				<p>
					<?php _e( 'This will upgrade the licenses database for improved performance and reliability.', 'edd_sl' ); ?>
				</p>
				<form method="post" id="edd-sl-migrate-licenses-form" class="edd-export-form edd-import-export-form">
					<span class="step-instructions-wrapper">

						<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>

						<?php if ( ! $removal_complete ) : ?>
							<span class="edd-sl-migration allowed" style="<?php echo ! $migration_complete ? '' : 'display: none'; ?>">
								<input type="submit" id="migrate-licenses-submit" value="<?php _e( 'Upgrade Database', 'edd_sl' ); ?>" class="button-primary"/>
							</span>

							<span class="edd-sl-migration unavailable" style="<?php echo $migration_complete ? '' : 'display: none'; ?>">
								<input type="submit" disabled="disabled" id="migrate-licenses-submit" value="<?php _e( 'Upgrade Database', 'edd_sl' ); ?>" class="button-secondary"/>
								&mdash; <?php _e( 'Your licenses database has been upgraded.', 'edd_sl' ); ?>
							</span>
						<?php else: ?>
							<input type="submit" disabled="disabled" id="migrate-licenses-submit" value="<?php _e( 'Upgrade Database', 'edd_sl' ); ?>" class="button-secondary"/>
							&mdash; <?php _e( 'Legacy data has already been removed, migration is not possible at this time.', 'edd_sl' ); ?>
						<?php endif; ?>

						<input type="hidden" name="edd-export-class" value="EDD_SL_License_Migration" />
						<span class="spinner"></span>

					</span>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	</div>
	<?php $step++; ?>

	<?php if ( ! empty( $has_child_licenses ) ) : ?>
	<div class="metabox-holder">
		<div class="postbox">
			<h2 class="hndle">
				<span><?php printf( __( 'Step %d: Update Bundled Licenses', 'edd_sl' ), $step ); ?></span>
				 <span class="dashicons dashicons-yes"></span>
			</h2>
			<div class="inside migrate-licenses-control">
				<p>
					<?php _e( 'This restores child licenses with their new parent license IDs.', 'edd_sl' ); ?>
				</p>
				<form method="post" id="edd-sl-fix-bundle-form" class="edd-export-form edd-import-export-form">
				<span class="step-instructions-wrapper">

					<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>

					<?php if ( ! $relationships_complete ) : ?>
						<span class="edd-sl-migration allowed" style="<?php echo $migration_complete ? '' : 'display: none'; ?>">
							<input type="submit" id="migrate-bundles-submit" value="<?php _e( 'Update Bundles', 'edd_sl' ); ?>" class="button-primary"/>
						</span>

						<span class="edd-sl-migration unavailable" style="<?php echo ! $migration_complete ? '' : 'display: none'; ?>">
							<input type="submit" disabled="disabled" id="migrate-bundles-submit" value="<?php _e( 'Update Bundles', 'edd_sl' ); ?>" class="button-secondary"/>
							&mdash; <?php _e( 'Please complete the previous step before updating bundled licenses.', 'edd_sl' ); ?>
						</span>
					<?php else : ?>
						<input type="submit" disabled="disabled" id="migrate-bundles-submit" value="<?php _e( 'Update Bundles', 'edd_sl' ); ?>" class="button-secondary"/>
						&mdash; <?php _e( 'Bundled licenses already updated.', 'edd_sl' ); ?>
					<?php endif; ?>

					<input type="hidden" name="edd-export-class" value="EDD_SL_Bundle_License_Migration" />
					<span class="spinner"></span>

				</span>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	</div>
	<?php $step++; ?>
	<?php endif; ?>

	<?php if ( ! empty( $has_license_logs ) ) : ?>
	<div class="metabox-holder">
		<div class="postbox">
			<h2 class="hndle">
				<span><?php printf( __( 'Step %d: Update License Logs', 'edd_sl' ), $step ); ?></span>
				 <span class="dashicons dashicons-yes"></span>
			</h2>
			<div class="inside migrate-licenses-control">
				<p>
					<?php _e( 'This updates the license logs with the new license data.', 'edd_sl' ); ?>
				</p>
				<form method="post" id="edd-sl-fix-license-logs-form" class="edd-export-form edd-import-export-form">
				<span class="step-instructions-wrapper">

					<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>

					<?php if ( ! $logs_complete ) : ?>
						<span class="edd-sl-migration allowed" style="<?php echo $migration_complete ? '' : 'display: none'; ?>">
							<input type="submit" id="migrate-logs-submit" value="<?php _e( 'Update License Logs', 'edd_sl' ); ?>" class="button-primary"/>
						</span>

						<span class="edd-sl-migration unavailable" style="<?php echo ! $migration_complete ? '' : 'display: none'; ?>">
							<input type="submit" disabled="disabled" id="migrate-logs-submit" value="<?php _e( 'Update License Logs', 'edd_sl' ); ?>" class="button-secondary"/>
							&mdash; <?php _e( 'Please complete the previous steps before updating the license logs.', 'edd_sl' ); ?>
						</span>
					<?php else: ?>
						<input type="submit" disabled="disabled" id="migrate-logs-submit" value="<?php _e( 'Update License Logs', 'edd_sl' ); ?>" class="button-secondary"/>
						&mdash; <?php _e( 'License logs have already been updated.', 'edd_sl' ); ?>
					<?php endif; ?>

					<input type="hidden" name="edd-export-class" value="EDD_SL_License_Log_Migration" />
					<span class="spinner"></span>

				</span>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	</div>
	<?php $step++; ?>
	<?php endif; ?>

	<?php if ( $migration_complete && $relationships_complete && $logs_complete ) : ?>
	<div class="metabox-holder">
		<div class="postbox">
			<h2 class="hndle">
				<span><?php printf( __( 'Step %d: Remove Legacy Data', 'edd_sl' ), $step ); ?></span>
				 <span class="dashicons dashicons-yes"></span>
			</h2>
			<div class="inside migrate-licenses-control">
				<p>
					<?php _e( 'This will remove all legacy license data.', 'edd_sl' ); ?>
				</p>
				<p>
					<?php _e( '<strong>Important:</strong> Please be sure to back up your database prior to completing this step. The actions taken during this step are irreversible.', 'edd_sl' ); ?>
				</p>
				<form method="post" id="edd-sl-remove-legacy-licenses-form" class="edd-export-form edd-import-export-form">
					<span class="step-instructions-wrapper">

						<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>

						<?php if ( ! $removal_complete ) : ?>
							<span class="edd-sl-migration allowed">
								<input type="submit" id="remove-legacy-licenses-submit" value="<?php _e( 'Remove Legacy Data', 'edd_sl' ); ?>" class="button-primary"/>
							</span>
						<?php elseif ( $removal_complete ): ?>
							<input type="submit" disabled="disabled" id="remove-legacy-licenses-submit" value="<?php _e( 'Remove Legacy Data', 'edd_sl' ); ?>" class="button-secondary"/>
							&mdash; <?php _e( 'Legacy data has already been removed.', 'edd_sl' ); ?>
						<?php endif; ?>

						<input type="hidden" name="edd-export-class" value="EDD_SL_Remove_Legacy_Licenses" />
					<span class="spinner"></span>

					</span>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	</div>
	<?php
	endif;
}
add_action( 'edd_sl_render_licenses_migration', 'edd_sl_render_licenses_migration' );

// Register all the Batch Processors for the Migration:
function edd_sl_register_batch_license_migration() {
	add_action( 'edd_batch_export_class_include', 'edd_sl_include_sl_license_migration_batch_processor', 10, 1 );
}
add_action( 'edd_register_batch_exporter', 'edd_sl_register_batch_license_migration', 10 );


function edd_sl_include_sl_license_migration_batch_processor( $class ) {

	if ( 'EDD_SL_License_Migration' === $class ) {
		require_once EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-license-migration.php';
	}

}

function edd_sl_register_batch_bundle_license_migration() {
	add_action( 'edd_batch_export_class_include', 'edd_sl_include_sl_bundle_license_migration_batch_processor', 10, 1 );
}
add_action( 'edd_register_batch_exporter', 'edd_sl_register_batch_bundle_license_migration', 10 );


function edd_sl_include_sl_bundle_license_migration_batch_processor( $class ) {

	if ( 'EDD_SL_Bundle_License_Migration' === $class ) {
		require_once EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-bundle-license-migration.php';
	}

}

function edd_sl_register_batch_license_log_migration() {
	add_action( 'edd_batch_export_class_include', 'edd_sl_include_sl_license_log_migration_batch_processor', 10, 1 );
}
add_action( 'edd_register_batch_exporter', 'edd_sl_register_batch_license_log_migration', 10 );


function edd_sl_include_sl_license_log_migration_batch_processor( $class ) {

	if ( 'EDD_SL_License_Log_Migration' === $class ) {
		require_once EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-license-log-migration.php';
	}

}

function edd_sl_register_batch_legacy_license_removal() {
	add_action( 'edd_batch_export_class_include', 'edd_sl_include_sl_legacy_license_removal_batch_processor', 10, 1 );
}
add_action( 'edd_register_batch_exporter', 'edd_sl_register_batch_legacy_license_removal', 10 );


function edd_sl_include_sl_legacy_license_removal_batch_processor( $class ) {

	if ( 'EDD_SL_Remove_Legacy_Licenses' === $class ) {
		require_once EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-legacy-license-removal.php';
	}

}

/**
 * Fix an issue that was causing the _edd_sl_activation_count meta from being removed from all licenses when licenses
 * with the meta key defined were deleted.
 *
 * @since 3.6.8
 */
function edd_sl_render_fix_no_url_check_activation_counts() {
	if ( edd_has_upgrade_completed( 'fix_no_url_check_activation_counts' ) ) {
		?>
		<div class="notice notice-success">
			<p>
				<?php _e( 'This upgrade has already been completed.', 'edd_sl' ); ?>
			</p>
		</div>
		<?php
		return;
	}
	?>
	<div id="edd-sl-migration-nav-warn" class="notice notice-info">
		<p>
			<?php _e( '<strong>Important:</strong> Please leave this screen open and do not navigate away until the process completes.', 'edd_sl' ); ?>
		</p>
	</div>

	<style>
		.dashicons.dashicons-yes { display: none; color: rgb(0, 128, 0); vertical-align: middle; }
	</style>

	<?php wp_enqueue_script( 'jquery' ); ?>
	<script>
		( function( $ ) {
			$( document ).ready(function() {
				$(document).on("DOMNodeInserted", function (e) {
					var element = e.target;

					if ( element.id === 'edd-batch-success' ) {
						element = $(element);
						var element_wrapper = element.parents().eq(4);
						element_wrapper.find('.dashicons.dashicons-yes').show();
						$('.edd-sl-fix-license-activations').hide();
					}
				});
			});
		}(jQuery) );
	</script>

	<div class="metabox-holder">
		<div class="postbox">
			<h2 class="hndle">
				<span><?php _e( 'Update License Activation Records', 'edd_sl' ); ?></span>
				<span class="dashicons dashicons-yes"></span>
			</h2>
			<div class="inside update-licenses-control">
				<p>
					<?php _e( 'This will check your license activation counts, and use the activation logs to correct any discrepancies in the activation counts.', 'edd_sl' ); ?>
				</p>
				<form method="post" id="edd-sl-fix-license-activation-form" class="edd-export-form edd-import-export-form">
					<span class="step-instructions-wrapper">

						<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>

						<span class="edd-sl-fix-license-activations allowed">
							<input type="submit" id="fix-license-activation-submit" value="<?php _e( 'Update Activation Counts', 'edd_sl' ); ?>" class="button-primary"/>
						</span>

						<input type="hidden" name="edd-export-class" value="EDD_SL_License_Activation_Count_Fix" />
						<span class="spinner"></span>

					</span>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	</div>
	<?php
}
add_action( 'edd_sl_render_fix_no_url_check_activation_counts', 'edd_sl_render_fix_no_url_check_activation_counts' );

// Register the batch processor for the activation count fix:
function edd_sl_register_batch_license_activation_count_fix() {
	add_action( 'edd_batch_export_class_include', 'edd_sl_include_sl_license_activation_count_fix', 10, 1 );
}
add_action( 'edd_register_batch_exporter', 'edd_sl_register_batch_license_activation_count_fix', 10 );


function edd_sl_include_sl_license_activation_count_fix( $class ) {

	if ( 'EDD_SL_License_Activation_Count_Fix' === $class ) {
		require_once EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-fix-license-activation-counts.php';
	}

}
