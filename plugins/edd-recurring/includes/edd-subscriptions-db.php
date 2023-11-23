<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Subscriptions DB Class
 *
 * @since  2.4
 */
class EDD_Subscriptions_DB extends EDD_DB {

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function __construct() {

		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'edd_subscriptions';
		$this->primary_key = 'id';
		$this->version     = '1.4.1.3';

		$db_version = get_option( $this->table_name . '_db_version' );
		if ( version_compare( $db_version, $this->version, '>=' ) ) {
			return;
		}

		$this->create_table();

	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function get_columns() {
		return array(
			'id'                    => '%d',
			'customer_id'           => '%d',
			'period'                => '%s',
			'initial_amount'        => '%s',
			'initial_tax_rate'      => '%s',
			'initial_tax'           => '%s',
			'recurring_amount'      => '%s',
			'recurring_tax_rate'    => '%s',
			'recurring_tax'         => '%s',
			'bill_times'            => '%d',
			'transaction_id'        => '%s',
			'parent_payment_id'     => '%d',
			'product_id'            => '%d',
			'price_id'              => '%d',
			'created'               => '%s',
			'expiration'            => '%s',
			'trial_period'          => '%s',
			'status'                => '%s',
			'notes'                 => '%s',
			'profile_id'            => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function get_column_defaults() {
		return array(
			'customer_id'        => 0,
			'period'             => '',
			'initial_amount'     => '',
			'initial_tax_rate'   => '',
			'initial_tax'        => '',
			'recurring_amount'   => '',
			'recurring_tax_rate' => '',
			'recurring_tax'      => '',
			'bill_times'         => 0,
			'transaction_id'     => '',
			'parent_payment_id'  => 0,
			'product_id'         => 0,
			'price_id'           => 0,
			'created'            => date( 'Y-m-d H:i:s' ),
			'expiration'         => date( 'Y-m-d H:i:s' ),
			'trial_period'       => '',
			'status'             => '',
			'notes'              => '',
			'profile_id'         => '',
		);
	}

	/**
	 * Retrieve all subscriptions for a customer
	 *
	 * @access  public
	 * @since   2.4
	 * @return EDD_Subscription[]
	 */
	public function get_subscriptions( $args = array() ) {
		global $wpdb;

		$args     = $this->parse_with_defaults( $args );
		$defaults = array(
			'number'  => 20,
			'offset'  => 0,
			'search'  => '',
			'orderby' => 'id',
			'order'   => 'DESC',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = ' WHERE 1=1 ';
		$join  = '';

		// Bill times.
		$where .= $this->get_bill_times_where( $args['bill_times'], $args['bill_times_operator'] );

		// ID.
		$where .= $this->get_id_where( $args['id'] );

		// Specific products.
		$where .= $this->get_product_id_where( $args['product_id'] );

		// Specific price_ids.
		$where .= $this->get_price_id_where( $args['price_id'] );

		// Specific parent payments.
		$where .= $this->get_parent_payment_id_where( $args['parent_payment_id'] );

		// Specific transaction IDs
		$where .= $this->get_transaction_id_where( $args['transaction_id'] );

		// Subscriptions for specific customers.
		$where .= $this->get_customer_id_where( $args['customer_id'] );

		// Subscriptions for specific profile IDs
		$where .= $this->get_profile_id_where( $args['profile_id'] );

		// Subscriptions for specific statuses
		$where .= $this->get_status_where( $args['status'] );

		// Subscriptions created for a specific date or in a date range
		$where .= $this->get_date_where( $args['date'] );

		// Subscriptions with a specific expiration date or in an expiration date range
		$where .= $this->get_expiration_where( $args['expiration'] );

		// Get the search query.
		if ( ! empty( $args['search'] ) ) {
			$where .= $this->parse_search( $args['search'] );
		}

		// Search by gateway
		if ( ! empty( $args['gateway'] ) ) {
			$gateway = sanitize_text_field( $args['gateway'] );

			if ( ! function_exists( 'edd_get_order' ) ) {

				// Pre EDD 3.0 join
				$join  .= " LEFT JOIN {$wpdb->prefix}postmeta m1 ON t1.parent_payment_id = m1.post_id ";
				$where .= $wpdb->prepare( " AND m1.meta_key = '_edd_payment_gateway' AND m1.meta_value = '%s'", $gateway );

			} else {

				// Post EDD 3.0 join
				$join  .= " LEFT JOIN {$wpdb->prefix}edd_orders o1 on t1.parent_payment_id = o1.id ";
				$where .= $wpdb->prepare( " AND o1.gateway = '%s' ", $gateway );

			}
		}

		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? 'id' : $args['orderby'];

		if( 'amount' == $args['orderby'] ) {
			$args['orderby'] = 't1.amount+0';
		}

		$cache_key = md5( 'edd_subscriptions_' . serialize( $args ) );

		$subscriptions = wp_cache_get( $cache_key, 'edd_subscriptions' );

		$args['orderby'] = esc_sql( $args['orderby'] );
		$args['order']   = esc_sql( $args['order'] );

		if ( false === $subscriptions ) {
			$query         = $wpdb->prepare( "SELECT t1.* FROM  $this->table_name t1 $join $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d,%d;", absint( $args['offset'] ), absint( $args['number'] ) );
			$subscriptions = $wpdb->get_results( $query, OBJECT );

			if( ! empty( $subscriptions ) ) {

				foreach( $subscriptions as $key => $subscription ) {

					$subscription_object = wp_cache_get( $subscription->id, 'edd_subscription_objects' );

					// If we didn't find the subscription in cache, get it.
					if ( false === $subscription_object ) {

						$subscription_object = new EDD_Subscription( $subscription );

						// If we got a valid subscription object, save it in cache for 1 hour.
						if ( ! empty( $subscription->id ) ) {
							wp_cache_set( $subscription->id, $subscription_object, 'edd_subscription_objects', 3600 );
						}
					}

					$subscriptions[ $key ] = $subscription_object;
				}

				wp_cache_set( $cache_key, $subscriptions, 'edd_subscriptions', 3600 );

			}

		}

		return $subscriptions;
	}

	/**
	 * Count the total number of subscriptions in the database
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function count( $args = array() ) {

		global $wpdb;

		$args  = $this->parse_with_defaults( $args );
		$where = ' WHERE 1=1 ';
		$join  = '';

		// Bill times.
		$where .= $this->get_bill_times_where( $args['bill_times'], $args['bill_times_operator'] );

		// ID.
		$where .= $this->get_id_where( $args['id'] );

		// Specific products.
		$where .= $this->get_product_id_where( $args['product_id'] );

		// Specific price_ids
		$where .= $this->get_price_id_where( $args['price_id'] );

		// Specific parent payments.
		$where .= $this->get_parent_payment_id_where( $args['parent_payment_id'] );

		// Subscriptions for specific customers.
		$where .= $this->get_customer_id_where( $args['customer_id'] );

		// Subscriptions for specific profile IDs.
		$where .= $this->get_profile_id_where( $args['profile_id'] );

		// Specific transaction IDs
		$where .= $this->get_transaction_id_where( $args['transaction_id'] );

		// Subscriptions for specific statuses
		$where .= $this->get_status_where( $args['status'] );

		// Subscriptions created for a specific date or in a date range
		$where .= $this->get_date_where( $args['date'] );

		// Subscriptions with a specific expiration date or in an expiration date range
		$where .= $this->get_expiration_where( $args['expiration'] );

		// Get the search query.
		if ( ! empty( $args['search'] ) ) {
			$where .= $this->parse_search( $args['search'] );
		}

		// Search by gateway
		if ( ! empty( $args['gateway'] ) ) {
			$gateway = sanitize_text_field( $args['gateway'] );

			if ( ! function_exists( 'edd_get_orders' ) ) {

				// Pre EDD 3.0 join
				$join  .= " LEFT JOIN {$wpdb->prefix}postmeta m1 ON t1.parent_payment_id = m1.post_id ";
				$where .= $wpdb->prepare( " AND m1.meta_key = '_edd_payment_gateway' AND m1.meta_value = '%s'", $gateway );

			} else {

				// Post EDD 3.0 join
				$join  .= " LEFT JOIN {$wpdb->prefix}edd_orders o1 on t1.parent_payment_id = o1.id ";
				$where .= $wpdb->prepare( " AND o1.gateway = '%s' ", $gateway );

			}
		}

		$cache_key = md5( 'edd_subscriptions_count' . serialize( $args ) );

		$count = wp_cache_get( $cache_key, 'edd_subscriptions' );

		if( $count === false ) {

			$sql   = "SELECT COUNT(t1.$this->primary_key) FROM " . $this->table_name . " t1" . "{$join}" . "{$where}";
			$count = $wpdb->get_var( $sql );

			wp_cache_set( $cache_key, $count, 'edd_subscriptions', 3600 );

		}

		return absint( $count );

	}

	/**
	 * Parses the search query and returns the SQL query string.
	 *
	 * @since 2.12
	 * @param string $search_query
	 * @return string
	 */
	private function parse_search( $search_query ) {
		$search = '';
		if ( empty( $search_query ) ) {
			return $search;
		}

		// If a customer can be retrieved from an email address, search for that customer by ID.
		if ( is_email( $search_query ) ) {
			$customer = new EDD_Customer( $search_query );
			if ( $customer && $customer->id > 0 ) {
				return " AND t1.customer_id = '" . esc_sql( $customer->id ) . "'";
			}

			return $search;
		}

		/**
		 * Search by property, if the string is %s:%s.
		 *
		 * Suggested searches include: id, profile_id, product_id, txn, customer_id, and gateway, but this would work for any property.
		 */
		$search_by_key = explode( ':', $search_query );
		if ( ! empty( $search_by_key[1] ) && in_array( $search_by_key[0], array_keys( $this->get_columns() ), true ) ) {
			return " AND t1." . esc_sql( $search_by_key[0] ) . "= '" . esc_sql( $search_by_key[1] ) . "'";
		} elseif ( 'txn' === $search_by_key[0] ) {
			return " AND t1.transaction_id= '" . esc_sql( $search_by_key[1] ) . "'";
		}

		// Search by download if one can be retrieved.
		$download = $this->get_download_from_title( trim( $search_query ) );
		if ( $download ) {
			return " AND t1.product_id = '" . esc_sql( $download ) . "'";
		}

		// General search fallback.
		return " AND (
			 t1.parent_payment_id LIKE '%%" . esc_sql( $search_query ) . "%%'
			 OR t1.profile_id LIKE '%%" . esc_sql( $search_query ) . "%%'
			 OR t1.transaction_id LIKE '%%" . esc_sql( $search_query ) . "%%'
			 OR t1.product_id LIKE '%%" . esc_sql( $search_query ) . "%%'
			 OR t1.id = '" . esc_sql( $search_query ) . "'
			 )";
	}

	/**
	 * Parse the args passed to the query with the defaults.
	 *
	 * @since 2.11.10
	 * @param array $args
	 * @return array
	 */
	private function parse_with_defaults( $args ) {
		$defaults = array(
			'customer_id'         => 0,
			'bill_times'          => null,
			'bill_times_operator' => '=',
			'price_id'            => false,
			'id'                  => null,
			'product_id'          => null,
			'parent_payment_id'   => null,
			'profile_id'          => null,
			'transaction_id'      => null,
			'status'              => null,
			'date'                => null,
			'expiration'          => null,
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Sets the price ID part of the MySQL query from the args passed to the query.
	 *
	 * @since 2.11.10
	 * @param mixed $price_id
	 *
	 * @return string
	 */
	private function get_price_id_where( $price_id ) {
		if ( false === $price_id ) {
			return '';
		}

		if ( is_null( $price_id ) ) {
			return " AND t1.price_id IS NULL ";
		}

		if ( is_array( $price_id ) ) {
			$price_ids = implode( ',', array_map( 'intval', $price_id ) );

			return " AND t1.price_id IN( {$price_ids} ) ";
		}

		$price_id = intval( $price_id );

		return " AND t1.price_id = {$price_id} ";
	}

	/**
	 * Get the where clause for the bill times.
	 *
	 * @since 2.11.10
	 * @param null|int $bill_times
	 * @param string $bill_times_operator
	 * @return string
	 */
	private function get_bill_times_where( $bill_times, $bill_times_operator ) {
		if ( is_null( $bill_times ) ) {
			return '';
		}

		if ( ! is_numeric( $bill_times ) ) {
			trigger_error( esc_html__( 'The bill_times argument should be a number but was not.', 'edd-recurring' ) );
			return '';
		}

		return " AND t1.bill_times {$bill_times_operator} '{$bill_times}'";
	}

	/**
	 * Get the where clause for the ID.
	 *
	 * @since 2.11.10
	 * @param null|int|array $id
	 * @return string
	 */
	private function get_id_where( $id ) {
		if ( empty( $id ) ) {
			return '';
		}

		if ( is_array( $id ) ) {
			$ids = implode( ',', array_map( 'intval', $id ) );

			return " AND t1.id IN( {$ids} ) ";
		}

		$id = intval( $id );

		return " AND t1.id = {$id} ";
	}

	/**
	 * Gets the product ID where clause.
	 *
	 * @param null|int|array $product_id
	 * @return string
	 */
	private function get_product_id_where( $product_id ) {
		if ( empty( $product_id ) ) {
			return '';
		}

		if ( is_array( $product_id ) ) {
			$product_ids = implode( ',', array_map( 'intval', $product_id ) );

			return " AND t1.product_id IN( {$product_ids} ) ";
		}

		$product_id = intval( $product_id );

		return " AND t1.product_id = {$product_id} ";
	}

	/**
	 * Gets the parent payment ID where clause.
	 *
	 * @since 2.11.10
	 * @param null|int|array $parent_payment_id
	 * @return string
	 */
	private function get_parent_payment_id_where( $parent_payment_id ) {
		if ( empty( $parent_payment_id ) ) {
			return '';
		}

		if ( is_array( $parent_payment_id ) ) {
			$parent_payment_ids = implode( ',', array_map( 'intval', $parent_payment_id ) );

			return " AND t1.parent_payment_id IN( {$parent_payment_ids} ) ";
		}

		$parent_payment_id = intval( $parent_payment_id );

		return " AND t1.parent_payment_id = {$parent_payment_id} ";
	}

	/**
	 * Gets the customer ID where clause.
	 *
	 * @since 2.11.10
	 * @param null|int|array $customer_id
	 * @return string
	 */
	private function get_customer_id_where( $customer_id ) {
		if ( empty( $customer_id ) ) {
			return '';
		}

		if ( is_array( $customer_id ) ) {
			$customer_ids = implode( ',', array_map( 'intval', $customer_id ) );

			return " AND t1.customer_id IN( {$customer_ids} ) ";
		}

		$customer_id = intval( $customer_id );

		return " AND t1.customer_id = {$customer_id} ";
	}

	/**
	 * Gets the where clause for profile ID.
	 *
	 * @since 2.11.10
	 * @param null|string|array $profile_id
	 * @return void
	 */
	private function get_profile_id_where( $profile_id ) {
		if ( empty( $profile_id ) ) {
			return '';
		}

		if ( is_array( $profile_id ) ) {
			$profile_ids = implode( "','", array_map( 'sanitize_text_field', $profile_id ) );

			return " AND t1.profile_id IN( '{$profile_ids}' ) ";
		}

		$profile_id = sanitize_text_field( $profile_id );

		return " AND t1.profile_id = '{$profile_id}' ";
	}

	/**
	 * Gets the where clause for the transaction ID.
	 *
	 * @since 2.11.10
	 * @param null|string|array $transaction_id
	 * @return void
	 */
	private function get_transaction_id_where( $transaction_id ) {
		if ( empty( $transaction_id ) ) {
			return '';
		}

		if ( is_array( $transaction_id ) ) {
			$transaction_ids = implode( "','", array_map( 'sanitize_text_field', $transaction_id ) );

			return " AND t1.transaction_id IN ( '{$transaction_ids}' ) ";
		}

		$transaction_id = sanitize_text_field( $transaction_id );

		return " AND t1.transaction_id = '{$transaction_id}' ";
	}

	/**
	 * Gets the where clause for the status.
	 *
	 * @since 2.11.10
	 * @param null|string|array $status
	 * @return void
	 */
	private function get_status_where( $status ) {
		if ( empty( $status ) ) {
			return '';
		}

		if ( is_array( $status ) ) {
			$statuses = implode( "','", array_map( 'sanitize_text_field', $status ) );

			return " AND t1.status IN( '{$statuses}' ) ";
		}

		$status = sanitize_text_field( $status );

		return " AND t1.status = '{$status}' ";
	}

	/**
	 * Gets the where clause for the date.
	 *
	 * @since 2.11.10
	 * @param null|string|array $type
	 * @return string
	 */
	private function get_date_where( $date ) {
		if ( empty( $date ) ) {
			return '';
		}

		if ( is_array( $date ) ) {
			$where = '';
			if ( ! empty( $date['start'] ) ) {
				$start  = date( 'Y-m-d H:i:s', strtotime( $date['start'] ) );
				$where .= " AND t1.created >= '{$start}'";
			}

			if ( ! empty( $date['end'] ) ) {
				$end    = date( 'Y-m-d H:i:s', strtotime( $date['end'] ) );
				$where .= " AND t1.created <= '{$end}'";
			}

			return $where;
		}

		$year  = date( 'Y', strtotime( $date ) );
		$month = date( 'm', strtotime( $date ) );
		$day   = date( 'd', strtotime( $date ) );

		return " AND $year = YEAR ( t1.created ) AND $month = MONTH ( t1.created ) AND $day = DAY ( t1.created )";
	}

	/**
	 * Gets the where clause for the expiration.
	 *
	 * @since 2.11.10
	 * @param null|string|array $expiration
	 * @return string
	 */
	private function get_expiration_where( $expiration ) {
		if ( empty( $expiration ) ) {
			return '';
		}

		if ( is_array( $expiration ) ) {

			$where = '';
			if ( ! empty( $expiration['start'] ) ) {
				$start  = date( 'Y-m-d H:i:s', strtotime( $expiration['start'] ) );
				$where .= " AND t1.expiration >= '{$start}'";
			}

			if ( ! empty( $expiration['end'] ) ) {
				$end    = date( 'Y-m-d H:i:s', strtotime( $expiration['end'] ) );
				$where .= " AND t1.expiration <= '{$end}'";
			}

			return $where;
		}

		$year  = date( 'Y', strtotime( $expiration ) );
		$month = date( 'm', strtotime( $expiration ) );
		$day   = date( 'd', strtotime( $expiration ) );

		return " AND $year = YEAR ( t1.expiration ) AND $month = MONTH ( t1.expiration ) AND $day = DAY ( t1.expiration )";
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function create_table() {

		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		/**
		 * For the 1.4.1.2 release, we need to remove some indexes as we are re-typing the columns for
		 * profile_id and transaction_id.
		 *
		 * @todo Remove this if statement for the next major release.
		 */
		if ( $this->installed( $this->table_name ) ) {
			$remove_profile_key = "ALTER TABLE " . $this->table_name . " DROP INDEX profile_id;";
			@$wpdb->query( $remove_profile_key );

			$remove_transaction_key = "ALTER TABLE " . $this->table_name . " DROP INDEX transaction;";
			@$wpdb->query( $remove_transaction_key );
		}

		$sql = "CREATE TABLE " . $this->table_name . " (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) NOT NULL,
		period varchar(20) NOT NULL,
		initial_amount mediumtext NOT NULL,
		initial_tax_rate mediumtext NOT NULL,
		initial_tax mediumtext NOT NULL,
		recurring_amount mediumtext NOT NULL,
		recurring_tax_rate mediumtext NOT NULL,
		recurring_tax mediumtext NOT NULL,
		bill_times bigint(20) NOT NULL,
		transaction_id varchar(255) NOT NULL COLLATE utf8_bin,
		parent_payment_id bigint(20) NOT NULL,
		product_id bigint(20) NOT NULL,
		price_id bigint(20) DEFAULT '0',
		created datetime NOT NULL,
		expiration datetime NOT NULL,
		trial_period varchar(20) NOT NULL,
		status varchar(20) NOT NULL,
		profile_id varchar(255) NOT NULL COLLATE utf8_bin,
		notes longtext NOT NULL,
		PRIMARY KEY  (id),
		KEY profile_id (profile_id),
		KEY transaction (transaction_id),
		KEY customer (customer_id),
		KEY customer_and_status ( customer_id, status),
		KEY product_id_price_id (product_id,price_id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

	/**
	 * Convert object to array
	 *
	 * @since 2.7.4
	 *
	 * @return array
	 */
	public function to_array(){
		$array = array();
		foreach( get_object_vars( $this )as $prop => $var ){
			$array[ $prop ] = $var;
		}

		return $array;
	}

	/**
	 * Gets the download ID from a string.
	 *
	 * @since 2.11.10
	 * @param string $title
	 * @return int|false
	 */
	private function get_download_from_title( $title ) {
		$download = new WP_Query(
			array(
				'post_type'              => 'download',
				'title'                  => $title,
				'post_status'            => 'all',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'orderby'                => 'post_date ID',
				'order'                  => 'ASC',
			)
		);

		return ! empty( $download->post ) ? $download->post->ID : false;
	}
}
