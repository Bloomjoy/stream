<?php

class WP_Stream_Query {

	public static $instance;

	/**
	 * @return WP_Stream_Query
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	/**
	 * Query Stream records
	 *
	 * @param  array|string $args Query args
	 * @return array              Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$defaults = array(
			// Search param
			'search'                => null,
			'search_field'          => 'summary',
			'record_after'          => null,
			// Date-based filters
			'date'                  => null,
			'date_from'             => null,
			'date_to'               => null,
			// Pagination params
			'records_per_page'      => get_option( 'posts_per_page' ),
			'paged'                 => 1,
			// Order
			'order'                 => 'desc',
			'orderby'               => 'date',
			// Meta/Taxonomy sub queries
			'meta'                  => array(),
			// Fields selection
			'fields'                => null,
		);

		// Additional property fields
		$properties = array(
			'record'        => null,
			'type'          => 'stream',
			'record_parent' => null,
			'author'        => null,
			'author_role'   => null,
			'ip'            => null,
			'object_id'     => null,
			'site_id'       => null,
			'blog_id'       => null,
			'visibility'    => null,
			'connector'     => null,
			'context'       => null,
			'action'        => null,
		);

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property => $default ) {
			if ( ! isset( $defaults[ $property ] ) ) {
				$defaults[ $property ] = $default;
			}
			$defaults[ "{$property}__in" ]     = array();
			$defaults[ "{$property}__not_in" ] = array();
		}

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @param  array  Array of query arguments
		 * @return array  Updated array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		$query   = array();
		$filters = array();
		$fields  = array();

		// PARSE SEARCH
		if ( $args['search'] ) {
			if ( $args['search_field'] ) {
				$search_field = $args['search_field'];
				$query['query']['match'][ $search_field ] = $args['search'];
			} else {
				$query['query']['match']['summary'] = $args['search'];
			}
		}

		// PARSE FIELDS
		if ( $args['fields'] ) {
			$fields = is_array( $args['fields'] ) ? $args['fields'] : explode( ',', $args['fields'] );
		}

		// PARSE DATE
		if ( $args['date_from'] ) {
			$filters[]['range']['created']['gte'] = date( 'c', strtotime( $args['date_from'] . ' 00:00:00' ) );
		}

		if ( $args['date_to'] ) {
			$filters[]['range']['created']['lte'] = date( 'c', strtotime( $args['date_to'] . ' 23:59:59' ) );
		}

		if ( $args['date'] ) {
			$filters[]['range']['created']['gte'] = date( 'c', strtotime( $args['date'] . ' 00:00:00' ) );
			$filters[]['range']['created']['lte']   = date( 'c', strtotime( $args['date'] . ' 23:59:59' ) );
		}

		// PARSE RECORD
		if ( $args['record_after'] ) {
			$filters[]['range']['created']['gt'] = date( 'c', strtotime( $args['record_after'] ) );
		}

		if ( $args['record__in'] ) {
			$filters[]['ids']['values'] = (array) $args['record__in'];
		}

		if ( $args['record__in'] ) {
			$filters[]['ids']['values'] = (array) $args['record__in'];
		}

		if ( $args['record__not_in'] ) {
			$filters[]['not']['ids']['values'] = (array) $args['record__not_in'];
		}

		foreach ( $properties as $property => $default ) {
			if ( $args[ $property ] ) {
				$filters[]['term'][ $property ] = $args[ $property ];
			}

			if ( $args["{$property}__in"] ) {
				$filters[]['term'][ $property ] = $args["{$property}__in"];
			}

			if ( $args["{$property}__not_in"] ) {
				$filters[]['not']['term'][ $property ] = $args["{$property}__in"];
			}
		}

		// PARSE PAGINATION
		if ( $args['records_per_page'] ) {
			$query['size'] = (int) $args['records_per_page'];
		} else {
			$query['size'] = get_option( 'posts_per_page', 20 );
		}

		if ( $args['paged'] ) {
			$query['from'] = ( (int) $args['paged'] - 1 ) * $query['size'];
		}

		// PARSE ORDER
		$query['sort'] = array();

		$orderby = ! empty( $args['orderby'] ) ? $args['orderby'] : 'created';
		$order   = ! empty( $args['order'] ) ? $args['order'] : 'desc';

		if ( 'date' === $orderby ) {
			$orderby = 'created';
		}

		$query['sort'][][ $orderby ]['order'] = $order;

		// PARSE META


		// Add filters to query
		if ( ! empty( $filters ) ) {
			if ( count( $filters ) > 1 ) {
				$query['filter']['and'] = $filters;
			} else {
				$query['filter'] = current( $filters );
			}
		}

		$query  = apply_filters( 'wp_stream_db_query', $query );
		$fields = apply_filters( 'wp_stream_db_fields', $fields );

		/**
		 * Query results
		 * @var  array
		 */
		return WP_Stream::$db->query( $query, $fields );
	}
}
