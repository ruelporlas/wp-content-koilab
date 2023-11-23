<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue admin scripts
 *
 * @since 2.6
 */
function edd_sl_admin_scripts() {
	$screen = get_current_screen();

	if ( ! is_object( $screen ) ) {
		return;
	}

	$allowed_screens = array(
		'download',
		'download_page_edd-licenses',
		'download_page_edd-license-renewal-notice',
		'download_page_edd-reports',
		'download_page_edd-settings',
		'download_page_edd-tools',
		'download_page_edd-payment-history',
		'download_page_edd-customers',
	);

	$allowed_screens = apply_filters( 'edd-sl-admin-script-screens', $allowed_screens );

	if( ! in_array( $screen->id, $allowed_screens ) ) {
		return;
	}

	// Use minified libraries if SCRIPT_DEBUG is turned off
	$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

	wp_enqueue_script( 'edd-sl-admin', plugins_url( '/assets/js/edd-sl-admin' . $suffix . '.js', EDD_SL_PLUGIN_FILE ), array( 'jquery' ), EDD_SL_VERSION );

	if( $screen->id === 'download' ) {
		wp_localize_script( 'edd-sl-admin', 'edd_sl', array(
			'download'      => get_the_ID(),
			'no_prices'     => __( 'N/A', 'edd_sl' ),
			'add_banner'    => __( 'Add Banner', 'edd_sl' ),
			'use_this_file' => __( 'Use This Image', 'edd_sl' ),
			'new_media_ui'  => apply_filters( 'edd_use_35_media_ui', 1 ),
			'readme_nonce'  => wp_create_nonce( 'edd_sl_readme_cache_nonce' ),
		) );
	} else {
		wp_localize_script( 'edd-sl-admin', 'edd_sl', array(
			'ajaxurl'           => edd_get_ajax_url(),
			'delete_license'    => __( 'Are you sure you wish to delete this license?', 'edd_sl' ),
			'action_edit'       => __( 'Edit', 'edd_sl' ),
			'action_cancel'     => __( 'Cancel', 'edd_sl' ),
			'send_notice'       => __( 'Send Renewal Notice', 'edd_sl' ),
			'cancel_notice'     => __( 'Cancel Renewal Notice', 'edd_sl' ),
			'regenerate_notice' => __( 'Regenerating a license key is not reversible. Click "OK" to continue.', 'edd_sl' ),
		) );
	}

	wp_enqueue_style( 'edd-sl-admin-styles', plugins_url( '/assets/css/edd-sl-admin' . $suffix . '.css', EDD_SL_PLUGIN_FILE ), false, EDD_SL_VERSION );
	wp_enqueue_style( 'edd-sl-styles', plugins_url( '/assets/css/edd-sl' . $suffix . '.css', EDD_SL_PLUGIN_FILE ), false, EDD_SL_VERSION );
}
add_action( 'admin_enqueue_scripts', 'edd_sl_admin_scripts' );

/**
 * Enqueue frontend scripts
 *
 * @since 3.2
 */
function edd_sl_scripts() {
	global $post;

	if ( ! is_object( $post ) ) {
		return;
	}

	if( ! function_exists( 'edd_is_checkout' ) ) {
		return;
	}

	$load_scripts_manually = apply_filters( 'edd_sl_load_styles', false );

	// Use minified libraries if SCRIPT_DEBUG is turned off
	$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	wp_register_style( 'edd-sl-styles', plugins_url( '/assets/css/edd-sl' . $suffix . '.css', EDD_SL_PLUGIN_FILE ), false, EDD_SL_VERSION );

	$should_load_styles = false;
	if ( is_admin() || edd_is_checkout() ) {
		$should_load_styles = true;
	}

	if ( has_shortcode( $post->post_content, 'purchase_history' ) || has_shortcode( $post->post_content, 'edd_license_keys' ) ) {
		$should_load_styles = true;
	}

	$inline_upgrade_links_enabled = edd_get_option( 'edd_sl_inline_upgrade_links', false );
	if ( $inline_upgrade_links_enabled && ( has_shortcode( $post->post_content, 'purchase_link' ) || has_shortcode( $post->post_content, 'downloads' ) ) ) {
		$should_load_styles = true;
	}

	if ( $inline_upgrade_links_enabled && $post->post_type === 'download' ) {
		$should_load_styles = true;
	}

	if ( true === $should_load_styles || true === $load_scripts_manually ) {
		wp_enqueue_style( 'edd-sl-styles' );
	}

}
add_action( 'wp_enqueue_scripts', 'edd_sl_scripts' );

/**
 * Output the SL JavaScript for the checkout page
 *
 * @param boolean $force Optional parameter to allow the script within the shortcode.
 * @since  3.2
 * @return void
 */
function edd_sl_checkout_js( $force = false ) {

	if ( ! function_exists( 'edd_is_checkout' ) ) {
		return;
	}

	if ( ! edd_is_checkout() && ! $force ) {
		return;
	}

	$is_checkout = edd_is_checkout() ? 'true' : 'false';
	$script      = "jQuery(document).ready(function($) {
		var hide = {$is_checkout};
		if ( hide ) {
			$( '.edd-sl-renewal-form-fields' ).hide();
			$( '#edd_sl_show_renewal_form, #edd-cancel-license-renewal' ).click(function(e) {
				e.preventDefault();
				$( '.edd-sl-renewal-form-fields, #edd_sl_show_renewal_form' ).toggle();
				$( '#edd-license-key' ).focus();
			} );
		}

		$( '#edd-license-key' ).keyup(function(e) {
			var input    = $( '#edd-license-key' );
			var disabled = ! input.val();

			$( '#edd-add-license-renewal' ).prop( 'disabled', disabled );
		} );
	} );";
	if ( function_exists( 'wp_add_inline_script' ) ) {
		wp_add_inline_script( 'edd-ajax', $script );
	} else {
		wp_print_scripts( 'jquery' );
		echo "<script>{$script}</script>";
	}
}
add_action( 'wp_enqueue_scripts', 'edd_sl_checkout_js' );

function edd_sl_load_edd_admin_scripts( $should_load, $hook ) {
	if ( 'widgets.php' === $hook ) {
		$should_load = true;
	}

	return $should_load;
}
add_filter( 'edd_load_admin_scripts', 'edd_sl_load_edd_admin_scripts', 10, 2 );
