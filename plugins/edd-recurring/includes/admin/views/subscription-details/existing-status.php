<?php
/**
 * @var EDD_Subscription $sub
 */
?>
<div class="edd-recurring-subscription-section edd-recurring-subscription__info">
	<h2><?php esc_html_e( 'Status', 'edd-recurring' ); ?></h2>

	<div class="edd-recurring-subscription-table">
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header"><?php esc_html_e( 'Date Created', 'edd-recurring' ); ?></div>
			<div class="edd-recurring-subscription-table_column-content">
				<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sub->created, current_time( 'timestamp' ) ) ) ); ?>
			</div>
		</div>
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header">
			<?php if ( 'trialling' === $sub->status ) : ?>
				<?php esc_html_e( 'Trialling Until', 'edd-recurring' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Expiration Date', 'edd-recurring' ); ?>
			<?php endif; ?>
			</div>
			<div class="edd-recurring-subscription-table_column-content">
				<span class="edd-sub-expiration"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sub->expiration, current_time( 'timestamp' ) ) ) ); ?></span>
				<?php if ( current_user_can( 'manage_subscriptions' ) ) : ?>
					<input type="text" id="edd_recurring_expiration" name="expiration" class="edd_datepicker hidden edd-sub-expiration" value="<?php echo esc_attr( $sub->expiration ); ?>" />
					<span>&nbsp;&ndash;&nbsp;</span>
					<a href="" class="edd-edit-sub-expiration"><?php esc_html_e( 'Edit', 'edd-recurring' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<div class="edd-recurring-subscription-table_column">
			<div class="edd-recurring-subscription-table_column-header"><?php esc_html_e( 'Subscription Status', 'edd-recurring' ); ?></div>
			<div class="edd-recurring-subscription-table_column-content">
				<?php
				echo $sub->get_status_badge();
				if ( current_user_can( 'manage_subscriptions' ) ) :
					echo EDD()->html->select(
						array(
							'id'               => 'status',
							'name'             => 'status',
							'class'            => 'hidden edd-sub-transaction-status',
							'options'          => edd_recurring_get_subscription_statuses(),
							'selected'         => $sub->status,
							'show_option_all'  => false,
							'show_option_none' => false,
						)
					);
					?>
					<span>&nbsp;&ndash;&nbsp;</span> <a href="" class="edd-edit-sub-status"><?php esc_html_e( 'Edit', 'edd-recurring' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>

</div> <!-- ends __info !-->
