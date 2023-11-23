<?php
/**
 * Roles and Capabilities
 *
 * @package     Recurring
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2022, Easy Digital Downloads
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.11.9
 */

namespace EDD\Recurring\Roles;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add new shop-specific capabilities
 *
 * @access public
 * @since 2.11.9
 * @global WP_Roles $wp_roles
 */
function add_caps() {
	$wp_roles = wp_roles();

	if ( ! $wp_roles instanceof \WP_Roles ) {
		return;
	}

	$wp_roles->add_cap( 'shop_manager', 'view_subscriptions' );
	$wp_roles->add_cap( 'shop_manager', 'manage_subscriptions' );
	$wp_roles->add_cap( 'shop_manager', 'delete_subscriptions' );

	$wp_roles->add_cap( 'administrator', 'view_subscriptions' );
	$wp_roles->add_cap( 'administrator', 'manage_subscriptions' );
	$wp_roles->add_cap( 'administrator', 'delete_subscriptions' );

	$wp_roles->add_cap( 'shop_worker', 'view_subscriptions' );
	$wp_roles->add_cap( 'shop_worker', 'manage_subscriptions' );

	$wp_roles->add_cap( 'shop_accountant', 'view_subscriptions' );
}

/**
 * Remove core post type capabilities (called on uninstall)
 *
 * @since 2.11.9
 * @return void
 */
function remove_caps() {

	$wp_roles = wp_roles();

	if ( ! $wp_roles instanceof \WP_Roles ) {
		return;
	}

	$wp_roles->remove_cap( 'shop_manager', 'view_subscriptions' );
	$wp_roles->remove_cap( 'shop_manager', 'manage_subscriptions' );
	$wp_roles->remove_cap( 'shop_manager', 'delete_subscriptions' );

	$wp_roles->remove_cap( 'administrator', 'view_subscriptions' );
	$wp_roles->remove_cap( 'administrator', 'manage_subscriptions' );
	$wp_roles->remove_cap( 'administrator', 'delete_subscriptions' );

	$wp_roles->remove_cap( 'shop_worker', 'view_subscriptions' );
	$wp_roles->remove_cap( 'shop_worker', 'manage_subscriptions' );

	$wp_roles->remove_cap( 'shop_accountant', 'view_subscriptions' );
}
