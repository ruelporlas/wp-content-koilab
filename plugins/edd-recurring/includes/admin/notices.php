<?php

/**
 * If the EDD Subscriber is set as the default role, display an admin notice
 * warning that it will be deprecated in 2.12.
 *
 * @since 2.11.7
 * @return void
 */
function edd_recurring_warn_subscriber_default_role() {
	$default_role = get_option( 'default_role', false );
	if ( 'edd_subscriber' !== $default_role ) {
		return;
	}
	?>
	<div class="notice error">
		<p>
			<?php
			esc_html_e( 'The default role on your site is the EDD Subscriber role, which will be removed in Recurring 2.12.', 'edd-recurring' );
			echo ' ';
			printf(
				/* translators: 1. opening anchor tag, do not translate; 2. closing anchor tag, do not translate. */
				__( '%1$sPlease update your default user role.%2$s', 'edd-recurring' ),
				'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">',
				'</a>'
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'edd_recurring_warn_subscriber_default_role' );
