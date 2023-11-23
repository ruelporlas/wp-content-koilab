<?php
/**
 * @var EDD_Subscription $sub
 */

$subscriber = new EDD_Recurring_Subscriber( $sub->customer_id );
$customer   = ! empty( $subscriber->name ) ? $subscriber->name : $subscriber->email;
$url        = add_query_arg(
	array(
		'post_type' => 'download',
		'page'      => 'edd-customers',
		'view'      => 'overview',
		'id'        => rawurlencode( $subscriber->id ),
	),
	admin_url( 'edit.php' )
)
?>
<div class="edd-recurring-subscription-section edd-recurring-subscription__customer">
	<h2><?php esc_html_e( 'Customer', 'edd-recurring' ); ?></h2>

	<div class="edd-item-info customer-info edd-sub">
		<div class="avatar-wrap" id="customer-avatar">
			<?php echo get_avatar( $subscriber->email, 30 ); ?><br />
		</div>

		<div class="customer-main-wrapper">
			<span>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . $subscriber->id ) ); ?>"><?php echo esc_html( $customer ); ?></a>
			</span>
		</div>
	</div>
</div>
