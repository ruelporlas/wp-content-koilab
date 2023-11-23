<?php
/**
 * Edit Renewal Notice
 *
 * @package     EDD Software Licensing
 * @copyright   Copyright (c) 2014, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap wrap-general">
	<h1><?php esc_html_e( 'Add Renewal Notice', 'edd_sl' ); ?></h1>
	<?php
	$settings_page_url = add_query_arg(
		array(
			'post_type' => 'download',
			'page'      => 'edd-settings',
			'tab'       => 'emails',
			'section'   => 'software-licensing',
		),
		admin_url( 'edit.php' )
	);
	?>
	<a href="<?php echo esc_url( $settings_page_url ); ?>"><?php esc_html_e( 'Return to Settings', 'edd_sl' ); ?></a>
	<form id="edd-add-renewal-notice" action="" method="post">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-notice-subject"><?php esc_html_e( 'Email Subject', 'edd_sl' ); ?></label>
					</th>
					<td>
						<input name="subject" id="edd-notice-subject" class="edd-notice-subject regular-text" type="text" value="" />
						<p class="description"><?php esc_html_e( 'The subject line of the renewal notice email', 'edd_sl' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-notice-period"><?php esc_html_e( 'Email Period', 'edd_sl' ); ?></label>
					</th>
					<td>
						<select name="period" id="edd-notice-period">
							<?php foreach ( edd_sl_get_renewal_notice_periods() as $period => $label ) : ?>
								<option value="<?php echo esc_attr( $period ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'When should this email be sent?', 'edd_sl' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-notice-message"><?php esc_html_e( 'Email Message', 'edd_sl' ); ?></label>
					</th>
					<td>
						<?php wp_editor( edd_sl_get_default_renewal_notice_message(), 'message', array( 'textarea_name' => 'message' ) ); ?>
						<p class="description"><?php esc_html_e( 'The email message to be sent with the renewal notice. The following template tags can be used in the message:', 'edd_sl' ); ?></p>
						<?php do_action( 'edd_sl_after_renewal_notice_form' ); ?>
					</td>
				</tr>

			</tbody>
		</table>
		<p class="submit">
			<input type="hidden" name="edd-action" value="add_renewal_notice"/>
			<input type="hidden" name="edd-renewal-notice-nonce" value="<?php echo esc_attr( wp_create_nonce( 'edd_renewal_nonce' ) ); ?>"/>
			<input type="submit" value="<?php esc_attr_e( 'Add Renewal Notice', 'edd_sl' ); ?>" class="button-primary"/>
		</p>
	</form>
</div>
