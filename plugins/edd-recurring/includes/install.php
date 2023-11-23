<?php
/**
 * Installation
 *
 * @package   edd-recurring
 * @copyright Copyright (c) 2021, Easy Digital Downloads
 * @license   GPL2+
 * @since     3.0
 */

/**
 * Checks to see if the Recurring installer should run due to the option set by the activation hook.
 *
 * @since 2.11.4
 *
 * @return void
 */
add_action( 'init', function() {
	$run_install = get_option( 'edd_recurring_run_install' );
	if ( false === $run_install ) {
		return;
	}

	edd_recurring_install();

	delete_option( 'edd_recurring_run_install' );
} );

/**
 * Installs Recurring.
 *
 * @since 2.4
 *
 * @return void
*/
function edd_recurring_install() {

	global $wpdb;

	$db = new EDD_Subscriptions_DB();
	@$db->create_table();

	require_once EDD_RECURRING_PLUGIN_DIR . 'includes/admin/roles.php';
	EDD\Recurring\Roles\add_caps();

	$version = get_option( 'edd_recurring_version' );

	if ( ! is_admin() ) {
		// Make sure our admin files with edd_recurring_needs_24_stripe_fix() definition are loaded
		EDD_Recurring()->includes_admin();
	}

	if ( ! function_exists( 'edd_set_upgrade_complete' ) ) {
		require_once EDD_PLUGIN_DIR . 'includes/admin/upgrades/upgrade-functions.php';
	}

	if ( empty( $version ) ) {

		// This is a new install or an update from pre 2.4, look to see if we have recurring products
		$has_recurring = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'edd_period' OR ( meta_key = 'edd_variable_prices' AND meta_value LIKE '%recurring%' AND meta_value LIKE '%yes%' ) AND 1=1 LIMIT 1" );
		$needs_upgrade = ! empty( $has_recurring );

		if ( ! $needs_upgrade ) {
			// Make sure this upgrade routine is never shown as needed
			edd_set_upgrade_complete( 'upgrade_24_subscriptions' );
		}

		// Set any other upgrades as completed on a fresh install.
		edd_set_upgrade_complete( 'recurring_paypalproexpress_logs' );
		edd_set_upgrade_complete( 'recurring_add_price_id_column' );
		edd_set_upgrade_complete( 'recurring_update_price_id_column' );
		edd_set_upgrade_complete( 'recurring_cancel_subs_if_times_met' );
		edd_set_upgrade_complete( 'recurring_add_tax_columns_to_subs_table' );
		edd_set_upgrade_complete( 'recurring_27_subscription_meta' );
		edd_set_upgrade_complete( 'recurring_increase_transaction_profile_id_cols_and_collate' );
		edd_set_upgrade_complete( 'recurring_wipe_invalid_paypal_plan_ids' );
		edd_set_upgrade_complete( 'recurring_update_order_item_status' );
		edd_set_upgrade_complete( 'recurring_update_subscription_roles' );
	}

	if ( false === edd_recurring_needs_24_stripe_fix() ) {
		edd_set_upgrade_complete( 'fix_24_stripe_customers' );
	}
	if ( ! edd_has_upgrade_completed( 'recurring_increase_transaction_profile_id_cols_and_collate' ) ) {
		@$db->create_table();
		edd_set_upgrade_complete( 'recurring_increase_transaction_profile_id_cols_and_collate' );
	}

	update_option( 'edd_recurring_version', EDD_RECURRING_VERSION );

	if ( class_exists( 'EDD_Recurring_PayPal_Commerce' ) && function_exists( '\\EDD\\Gateways\\PayPal\\Webhooks\\sync_webhook' ) && \EDD\Gateways\PayPal\has_rest_api_connection() ) {
		try {
			global $wp_rewrite;

			/*
			 * If `$wp_rewrite` isn't available, we can't get the REST API endpoint URL, which
			 * would cause a fatal during webhook syncing.
			 * @link https://github.com/easydigitaldownloads/edd-recurring/pull/1451#issuecomment-871515068
			 */
			if ( empty( $wp_rewrite ) ) {
				$wp_rewrite = new WP_Rewrite();
			}

			\EDD\Gateways\PayPal\Webhooks\sync_webhook();
		} catch ( \Exception $e ) {

		}
	}

}
