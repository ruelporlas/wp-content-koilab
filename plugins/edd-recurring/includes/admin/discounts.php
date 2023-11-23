<?php
/**
 * Discount handling for Recurring.
 */

namespace EDD\Recurring\Admin\Discounts;

/**
 * Adds the one time discount metadata to the discount.
 *
 * @since 2.12
 * @param array $args        The array of discount args.
 * @param int   $discount_id The discount ID.
 * @return void
 */
function add_one_time_discount_meta( $args, $discount_id ) {

	if ( empty( $_POST['edd-discount-nonce'] ) || ! isset( $_POST['recurring_one_time_discount'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edd-discount-nonce'] ) ), 'edd_discount_nonce' ) ) {
		return;
	}

	// Always save the metadata to the discount.
	$discount = \edd_get_discount( $discount_id );
	$discount->update_meta( 'recurring_one_time', sanitize_text_field( $_POST['recurring_one_time_discount'] ) );
}
add_action( 'edd_post_insert_discount', __NAMESPACE__ . '\add_one_time_discount_meta', 10, 2 );
add_action( 'edd_post_update_discount', __NAMESPACE__ . '\add_one_time_discount_meta', 10, 2 );

/**
 * Adds the one time discount setting to the discount screen.
 *
 * @since 2.12
 * @param int   $discount_id
 * @return void
 */
function render_one_time_discount_setting( $discount_id = 0 ) {
	?>
	<tr>
		<th scope="row" valign="top">
			<label for="edd-use-renewals">
				<?php esc_html_e( 'Discount Renewal Orders', 'edd-recurring' ); ?>
			</label>
		</th>
		<td>
			<select id="edd-use-renewals" name="recurring_one_time_discount">
				<?php
				$meta    = edd_recurring_get_discount_renewal_meta( $discount_id );
				$setting = edd_get_option( 'recurring_one_time_discounts', false );
				$options = array(
					/* translators: the current one time discount setting */
					''         => sprintf( __( 'Store Default (currently %s)', 'edd-recurring' ), $setting ? __( 'First Order Only', 'edd-recurring' ) : __( 'First Order and Renewals', 'edd-recurring' ) ),
					'first'    => __( 'First Order Only', 'edd-recurring' ),
					'renewals' => __( 'First Order and Renewals', 'edd-recurring' ),
				);
				foreach ( $options as $value => $label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $value ),
						selected( $value, $meta, false ),
						esc_html( $label )
					);
				}
				?>
			</select>
			<p class="description">
				<?php esc_html_e( 'Whether this discount will be used for renewal orders, or restricted to the first order only. Note: this will apply to new subscription/orders only.', 'edd-recurring' ); ?>
			</p>
		</td>
	</tr>
	<?php
}
add_action( 'edd_edit_discount_form_before_use_once', __NAMESPACE__ . '\render_one_time_discount_setting', 10, 2 );
add_action( 'edd_add_discount_form_before_use_once', __NAMESPACE__ . '\render_one_time_discount_setting', 10, 2 );
