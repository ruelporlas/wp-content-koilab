<?php
/**
 * Edit Reminder Notice
 *
 * @package     EDD Recurring
 * @copyright   Copyright (c) 2014, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $_GET['notice'] ) || ! is_numeric( $_GET['notice'] ) ) {
	//wp_die( __( 'Something went wrong.', 'edd-recurring' ), __( 'Error', 'edd-recurring' ) );
}

$notices  = new EDD_Recurring_Reminders();
$notice_id = absint( $_GET['notice'] );
$notice    = $notices->get_notice( $notice_id );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Edit Reminder Notice', 'edd-recurring' ); ?></h1>
	<a href="<?php echo esc_url( edd_recurring_get_email_settings_url() ); ?>"><?php esc_html_e( 'Return to Email Settings', 'edd-recurring' ); ?></a>

	<form id="edd-edit-reminder-notice" action="" method="post">
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row" valign="top">
					<label for="edd-notice-type"><?php _e( 'Notice Type', 'edd-recurring' ); ?></label>
				</th>
				<td>
					<select name="type" id="edd-notice-type">
						<?php foreach ( $notices->get_notice_types() as $type => $label ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"<?php selected( $type, $notice['type'] ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>

					<p class="description"><?php _e( 'Is this a renewal notice or an expiration notice?', 'edd-recurring' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top">
					<label for="edd-notice-subject"><?php _e( 'Email Subject', 'edd-recurring' ); ?></label>
				</th>
				<td>
					<input name="subject" id="edd-notice-subject" class="edd-notice-subject" type="text" value="<?php echo esc_attr( stripslashes( $notice['subject'] ) ); ?>" />

					<p class="description"><?php _e( 'The subject line of the reminder notice email', 'edd-recurring' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top">
					<label for="edd-notice-period"><?php _e( 'Email Period', 'edd-recurring' ); ?></label>
				</th>
				<td>
					<select name="period" id="edd-notice-period">
						<?php foreach ( $notices->get_notice_periods() as $period => $label ) : ?>
							<option value="<?php echo esc_attr( $period ); ?>"<?php selected( $period, $notice['send_period'] ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>

					<p class="description"><?php _e( 'When should this email be sent?', 'edd-recurring' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top">
					<label for="edd-notice-message"><?php _e( 'Email Message', 'edd-recurring' ); ?></label>
				</th>
				<td>
					<?php wp_editor( wpautop( wp_kses_post( wptexturize( $notice['message'] ) ) ), 'message', array( 'textarea_name' => 'message' ) ); ?>
					<p class="description"><?php esc_html_e( 'The email message to be sent with the reminder notice. The following template tags can be used in the message:', 'edd-recurring' ); ?></p>
					<ul>
						<li><code>{name}</code> <?php esc_html_e( 'The customer\'s name', 'edd-recurring' ); ?></li>
						<li><code>{subscription_name}</code> <?php esc_html_e( 'The name of the product the subscription belongs to', 'edd-recurring' ); ?></li>
						<li><code>{expiration}</code> <?php esc_html_e( 'The expiration date for the subscription', 'edd-recurring' ); ?></li>
						<li><code>{amount}</code> <?php esc_html_e( 'The recurring amount of the subscription', 'edd-recurring' ); ?></li>
						<li><code>{subscription_details}</code> <?php esc_html_e( 'The subscription details', 'edd-recurring' ); ?></li>
						<li><code>{subscription_period}</code> <?php esc_html_e( 'The subscription period', 'edd-recurring' ); ?></li>
					</ul>
				</td>
			</tr>

			</tbody>
		</table>
		<p class="submit">
			<input type="hidden" name="edd-action" value="recurring_edit_reminder_notice" />
			<input type="hidden" name="notice-id" value="<?php echo esc_attr( $notice_id ); ?>" />
			<input type="hidden" name="edd-recurring-reminder-notice-nonce" value="<?php echo wp_create_nonce( 'edd_recurring_reminder_nonce' ); ?>" />
			<input type="submit" value="<?php _e( 'Update Reminder Notice', 'edd-recurring' ); ?>" class="button-primary" />
		</p>
	</form>
</div>
