<?php
/**
 * Subscription List Table Class
 *
 * @package     EDD Recurring
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


// Load WP_List_Table if not loaded
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * EDD Subscriptions List Table Class
 *
 * @access      private
 */
class EDD_Subscription_Reports_Table extends WP_List_Table {

	/**
	 * Number of results to show per page
	 *
	 * @since       2.4
	 */

	public $per_page        = 30;
	public $total_count     = 0;
	public $active_count    = 0;
	public $pending_count   = 0;
	public $expired_count   = 0;
	public $completed_count = 0;
	public $trialling_count  = 0;
	public $cancelled_count = 0;
	public $failing_count   = 0;

	/**
	 * Get things started
	 *
	 * @access      private
	 * @since       2.4
	 * @return      void
	 */
	function __construct(){
		global $status, $page;

		// Set parent defaults
		parent::__construct( array(
			'singular'  => 'subscription',
			'plural'    => 'subscriptions',
			'ajax'      => false
		) );

		$this->get_subscription_counts();

		add_action( 'edd_admin_filter_bar_subscriptions', array( $this, 'filter_bar_items' ) );
		add_action( 'edd_after_admin_filter_bar_subscriptions', array( $this, 'filter_bar_searchbox' ) );
	}

	/**
	 * Adds the advanced filters to the subscriptions table.
	 *
	 * @since 2.11.8
	 * @return void
	 */
	public function advanced_filters() {
		if ( function_exists( 'edd_admin_filter_bar' ) ) {
			edd_admin_filter_bar( 'subscriptions' );
		} else {
			$this->do_filter_bar();
		}
	}

	/**
	 * Outputs the filter bar searchbox.
	 *
	 * @since 2.11.8
	 * @return void
	 */
	public function filter_bar_searchbox() {
		$this->search_box( __( 'Search', 'edd-recurring' ), 'subscriptions' );
	}

	/**
	 * Output the filter bar in EDD 2.x.
	 *
	 * @since 2.11.8
	 * @todo Remove when EDD minimum is 3.0.
	 * @return void
	 */
	private function do_filter_bar() {
		?>
		<div id="edd-payment-filters">
			<?php
			$this->filter_bar_items();
			$this->search_box( __( 'Search', 'edd-recurring' ), 'subscriptions' );
			?>
		</div>
		<?php
	}

	/**
	 * Adds the items to the filter bar.
	 *
	 * @since 2.11.8
	 * @return void
	 */
	public function filter_bar_items() {
		$this->gateway_filter();
		$this->product_filter();
		$this->status_filter();
		?>
		<span id="edd-after-core-filters">
			<input type="submit" class="button button-secondary" value="<?php esc_html_e( 'Filter', 'edd-recurring' ); ?>"/>
			<?php
			if ( ! empty( $this->get_gateway() ) || ! empty( $this->get_product_id() ) || ! empty( $this->get_status() ) ) :
				$clear_url = add_query_arg(
					array(
						'post_type' => 'download',
						'page'      => 'edd-subscriptions',
					),
					admin_url( 'edit.php' )
				);
				?>
				<a href="<?php echo esc_url( $clear_url ); ?>" class="button-secondary">
					<?php esc_html_e( 'Clear', 'edd-recurring' ); ?>
				</a>
			<?php endif; ?>
		</span>
		<?php
	}

	/**
	 * Renders the gateway filter.
	 *
	 * @since 2.11.8
	 * @return void
	 */
	private function gateway_filter() {
		$gateway      = $this->get_gateway();
		$gateways     = array(
			'' => __( 'All gateways', 'edd-recurring' ),
		);
		$all_gateways = edd_get_payment_gateways();

		// Add "all" and pluck labels.
		if ( ! empty( $all_gateways ) ) {
			$gateways = array_merge(
				$gateways,
				wp_list_pluck( $all_gateways, 'admin_label' )
			);
		}
		?>
		<span id="edd-gateway-filter">
			<label for="gateway" class="screen-reader-text"><?php esc_html_e( 'Filter subscriptions by gateway:', 'edd-recurring' ); ?></label>
			<?php
			echo EDD()->html->select(
				array(
					'options'          => $gateways,
					'name'             => 'gateway',
					'id'               => 'gateway',
					'selected'         => esc_attr( $gateway ),
					'show_option_all'  => false,
					'show_option_none' => false,
				)
			);
			?>
		</span>
		<?php
	}

	/**
	 * Renders the status filter.
	 *
	 * @since 2.11.8
	 * @return void
	 */
	private function status_filter() {
		$status       = $this->get_status();
		$statuses     = array(
			'' => __( 'All statuses', 'edd-recurring' ),
		);
		$all_statuses = edd_recurring_get_subscription_statuses();

		if ( ! empty( $all_statuses ) ) {
			$statuses = array_merge(
				$statuses,
				$all_statuses
			);
		}
		?>
		<span id="edd-recurring-status-filter">
			<label for="status" class="screen-reader-text"><?php esc_html_e( 'Filter subscriptions by status:', 'edd-recurring' ); ?></label>
			<?php
			echo EDD()->html->select(
				array(
					'options'          => $statuses,
					'name'             => 'status',
					'id'               => 'status',
					'selected'         => esc_attr( $status ),
					'show_option_all'  => false,
					'show_option_none' => false,
				)
			);
			?>
		</span>
		<?php
	}

	/**
	 * Renders the product filter.
	 *
	 * @since 2.11.8
	 * @return void
	 */
	private function product_filter() {
		$product_id = $this->get_product_id();
		add_filter( 'edd_product_dropdown_args', 'edd_recurring_product_dropdown_recurring_only' );
		echo '<span id="edd-product-filter">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo EDD()->html->product_dropdown(
			array(
				'name'       => 'product_id',
				'chosen'     => true,
				'variations' => false,
				'required'   => false,
				'selected'   => absint( $product_id ),
			)
		);
		echo '</span>';
		remove_filter( 'edd_product_dropdown_args', 'edd_recurring_product_dropdown_recurring_only' );
	}

	/**
	 * Retrieve the view types
	 *
	 * @access public
	 * @since 2.4
	 * @return array $views All the views available
	 */
	public function get_views() {

		$current         = $this->get_status();
		$total_count     = '&nbsp;<span class="count">(' . $this->total_count    . ')</span>';
		$active_count    = '&nbsp;<span class="count">(' . $this->active_count . ')</span>';
		$pending_count   = '&nbsp;<span class="count">(' . $this->pending_count . ')</span>';
		$expired_count   = '&nbsp;<span class="count">(' . $this->expired_count  . ')</span>';
		$completed_count = '&nbsp;<span class="count">(' . $this->completed_count . ')</span>';
		$trialling_count  = '&nbsp;<span class="count">(' . $this->trialling_count   . ')</span>';
		$cancelled_count = '&nbsp;<span class="count">(' . $this->cancelled_count   . ')</span>';
		$failing_count   = '&nbsp;<span class="count">(' . $this->failing_count   . ')</span>';

		$views = array(
			'all'       => sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( array( 'status', 'paged' ) ), $current === 'all' || $current == '' ? ' class="current"' : '', __('All','easy-digital-downloads' ) . $total_count ),
			'active'    => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'active', 'paged' => FALSE ) ), $current === 'active' ? ' class="current"' : '', __('Active','easy-digital-downloads' ) . $active_count ),
			'pending'   => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'pending', 'paged' => FALSE ) ), $current === 'pending' ? ' class="current"' : '', __('Pending','easy-digital-downloads' ) . $pending_count ),
			'expired'   => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'expired', 'paged' => FALSE ) ), $current === 'expired' ? ' class="current"' : '', __('Expired','easy-digital-downloads' ) . $expired_count ),
			'completed' => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'completed', 'paged' => FALSE ) ), $current === 'completed' ? ' class="current"' : '', __('Completed','easy-digital-downloads' ) . $completed_count ),
			'trialling'  => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'trialling', 'paged' => FALSE ) ), $current === 'trialling' ? ' class="current"' : '', __('Trialling','easy-digital-downloads' ) . $trialling_count ),
			'cancelled' => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'cancelled', 'paged' => FALSE ) ), $current === 'cancelled' ? ' class="current"' : '', __('Cancelled','easy-digital-downloads' ) . $cancelled_count ),
			'failing'   => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'failing', 'paged' => FALSE ) ), $current === 'failing' ? ' class="current"' : '', __('Failing','easy-digital-downloads' ) . $failing_count ),
		);

		return apply_filters( 'edd_recurring_subscriptions_table_views', $views );
	}

	/**
	 * Show the search field
	 *
	 * @since 2.5
	 * @access public
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 *
	 * @return void
	 */
	public function search_box( $text, $input_id ) {

		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		}
?>
		<p class="search-box">
			<?php do_action( 'edd_recurring_subscription_search_box' ); ?>
			<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
			<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?><br/>
		</p>
<?php
	}

	/**
	 * Render most columns
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	protected function column_default( $item, $column_name ) {
		if ( 'id' !== $column_name ) {
			return $item->$column_name;
		}
		if ( ! current_user_can( 'view_subscriptions' ) ) {
			return '#' . $item->id;
		}
		$url     = add_query_arg(
			array(
				'post_type' => 'download',
				'page'      => 'edd-subscriptions',
				'id'        => urlencode( $item->id ),
			),
			admin_url( 'edit.php' )
		);
		$output  = '<a href="' . esc_url( $url ) . '">#' . esc_html( $item->id ) . '</a>';
		$output .= '<div class="row-actions"><a href="' . esc_url( $url ) . '">' . esc_html__( 'View Details', 'edd-recurring' ) . '</a></div>';

		return $output;
	}

	/**
	 * Customer column
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	function column_customer_id( $item ) {
		$subscriber = new EDD_Recurring_Subscriber( $item->customer_id );
		$customer   = ! empty( $subscriber->name ) ? $subscriber->name : $subscriber->email;

		return '<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . $subscriber->id ) ) . '">' . $customer . '</a>';
	}


	/**
	 * Status column
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	function column_status( $item ) {
		return $item->get_status_badge();
	}

	/**
	 * Period column
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	function column_period( $item ) {
		$period         = EDD_Recurring()->get_pretty_subscription_frequency( $item->period );
		$billing_cycle  = edd_currency_filter( edd_format_amount( $item->recurring_amount ), edd_get_payment_currency_code( $item->parent_payment_id ) ) . ' / ' . $period;
		$initial_amount = edd_currency_filter( edd_format_amount( $item->initial_amount ), edd_get_payment_currency_code( $item->parent_payment_id ) );
		ob_start();
		?>
			<?php esc_html_e( 'Initial Amount', 'edd-recurring' ); ?>: <?php echo esc_html( $initial_amount ); ?><br>
			<?php echo esc_html( $billing_cycle ); ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Initial Amount column
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	function column_initial_amount( $item ) {
		return edd_currency_filter( edd_format_amount( $item->initial_amount ), edd_get_payment_currency_code( $item->parent_payment_id ) );
	}

	/**
	 * Renewal date column
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	function column_renewal_date( $item ) {
		return $renewal_date = ! empty( $item->expiration ) ? date_i18n( get_option( 'date_format' ), strtotime( $item->expiration ) ) : __( 'N/A', 'edd-recurring' );
	}

	/**
	 * Payment column
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	function column_parent_payment_id( $item ) {
		return '<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $item->parent_payment_id ) ) . '">' . edd_get_payment_number( $item->parent_payment_id ) . '</a>';
	}

	/**
	 * Gets the gateway column text.
	 *
	 * @since 2.11.8
	 * @param EDD_Subscription $item
	 * @return string
	 */
	function column_gateway( $item ) {
		return edd_get_gateway_admin_label( $item->gateway );
	}

	/**
	 * Product ID column
	 *
	 * @access      private
	 * @since       2.4
	 * @return      string
	 */
	function column_product_id( $item ) {
		$download = edd_get_download( $item->product_id );

		if ( $download instanceof  EDD_Download ) {
			$product_name = $download->get_name();
			if ( ! is_null( $item->price_id ) && $download->has_variable_prices() ) {
				$prices = $download->get_prices();
				if ( isset( $prices[ $item->price_id ] ) && ! empty( $prices[ $item->price_id ]['name'] ) ) {
					$product_name .= ' &mdash; ' . $prices[ $item->price_id ]['name'];
				}
			}

			return '<a href="' . esc_url( admin_url( 'post.php?action=edit&post=' . $item->product_id ) ) . '">' . $product_name . '</a>';
		} else {
			return '&mdash;';
		}

	}

	/**
	 * Retrieve the table columns
	 *
	 * @access      public
	 * @since       2.4
	 * @return      array
	 */

	public function get_columns() {
		$columns = array(
			'id'                => __( 'Subscription', 'edd-recurring' ),
			'customer_id'       => __( 'Customer', 'edd-recurring' ),
			'product_id'        => edd_get_label_singular(),
			'period'            => __( 'Billing Details', 'edd-recurring' ),
			'parent_payment_id' => __( 'Order', 'edd-recurring' ),
			'renewal_date'      => __( 'Renewal Date', 'edd-recurring' ),
			'status'            => __( 'Status', 'edd-recurring' ),
			'gateway'           => __( 'Gateway', 'edd-recurring' ),
		);

		return apply_filters( 'edd_report_subscription_columns', $columns );
	}

	/**
	 * Retrieve the current page number
	 *
	 * @access      private
	 * @since       2.4
	 * @return      int
	 */
	function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	/**
	 * Gets the currently queried gateway.
	 *
	 * @since 2.11.8
	 * @return string
	 */
	private function get_gateway() {
		return isset( $_GET['gateway'] ) ? sanitize_text_field( $_GET['gateway'] ) : '';
	}

	/**
	 * Gets the currently queried status.
	 *
	 * @since 2.11.8
	 * @return string
	 */
	private function get_status() {
		return isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
	}

	/**
	 * Gets the currently queried product ID.
	 *
	 * @since 2.11.8
	 * @return string
	 */
	private function get_product_id() {
		return isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : '';
	}

	/**
	 * Retrieve the subscription counts
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function get_subscription_counts() {

		global $wp_query;

		$db = new EDD_Subscriptions_DB;

		$search = ! empty( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

		$this->total_count     = $db->count( array( 'search' => $search ) );
		$this->active_count    = $db->count( array( 'status' => 'active', 'search' => $search ) );
		$this->pending_count   = $db->count( array( 'status' => 'pending', 'search' => $search ) );
		$this->expired_count   = $db->count( array( 'status' => 'expired', 'search' => $search ) );
		$this->trialling_count  = $db->count( array( 'status' => 'trialling', 'search' => $search ) );
		$this->cancelled_count = $db->count( array( 'status' => 'cancelled', 'search' => $search ) );
		$this->completed_count = $db->count( array( 'status' => 'completed', 'search' => $search ) );
		$this->failing_count   = $db->count( array( 'status' => 'failing', 'search' => $search ) );

	}

	/**
	 * Setup the final data for the table
	 *
	 * @access      private
	 * @since       2.4
	 * @uses        $this->_column_headers
	 * @uses        $this->items
	 * @uses        $this->get_columns()
	 * @uses        $this->get_sortable_columns()
	 * @uses        $this->get_pagenum()
	 * @uses        $this->set_pagination_args()
	 * @return      array
	 */
	function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = array(); // No hidden columns
		$status   = $this->get_status();
		$gateway  = $this->get_gateway();
		$sortable = $this->get_sortable_columns();
		$product  = $this->get_product_id();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();

		$db     = new EDD_Subscriptions_DB();
		$search = ! empty( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$args   = array(
			'number' => $this->per_page,
			'offset' => $this->per_page * ( $this->get_paged() - 1 ),
		);

		if ( $search ) {
			$args['search'] = $search;
		}

		if ( $status ) {
			$args['status'] = $status;
		}

		if ( ! empty( $gateway ) ) {
			$args['gateway'] = $gateway;
		}

		if ( ! empty( $product ) ) {
			$args['product_id'] = $product;
		}

		$this->items = $db->get_subscriptions( $args );

		switch ( $status ) {
			case 'active':
				$total_items = $this->active_count;
				break;
			case 'pending':
				$total_items = $this->pending_count;
				break;
			case 'expired':
				$total_items = $this->expired_count;
				break;
			case 'cancelled':
				$total_items = $this->cancelled_count;
				break;
			case 'failing':
				$total_items = $this->failing_count;
				break;
			case 'trialling':
				$total_items = $this->trialling_count;
				break;
			case 'completed':
				$total_items = $this->completed_count;
				break;
			case 'any':
			default:
				$total_items = $this->total_count;
				break;
		}

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $total_items / $this->per_page )
		) );
	}
}
