<?php
/**
 * Deprecated functions.
 *
 * @since 2.11.8
 */

defined( 'ABSPATH' ) || exit;

/**
 * Update customer ID on subscriptions when payment's customer ID is updated
 *
 * @deprecated 2.11.8 in favor of `edd_recurring_update_customer_id_edited_purchase`
 * @access      public
 * @since       2.4.15
 * @return      void
 */
function edd_recurring_update_customer_id_on_payment_update( $meta_id, $object_id, $meta_key, $meta_value ) {

	_edd_deprecated_function( __FUNCTION__, '2.11.8', 'edd_recurring_update_customer_id_edited_purchase' );
	if ( '_edd_payment_customer_id' == $meta_key ) {

		$subs_db = new EDD_Subscriptions_DB();
		$subs    = $subs_db->get_subscriptions( array( 'parent_payment_id' => $object_id ) );
		if ( $subs ) {

			foreach ( $subs as $sub ) {

				$sub->update( array( 'customer_id' => $meta_value ) );

			}
		}
	}
}
