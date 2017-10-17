<?php
/**
 * File for the WC_Product_Tables_Backwards_Compatibility class.
 *
 * @package WooCommerceProductTablesFeaturePlugin/Classes
 */

/**
 * Backwards compatibility layer for metadata access.
 *
 * @todo WP_Query meta query support? (IMO no. They should be using CRUD search helpers)
 */
class WC_Product_Tables_Backwards_Compatibility {

	/**
	 * WC_Product_Tables_Backwards_Compatibility constructor.
	 */
	public function __construct() {
		// Don't turn on backwards-compatibility if in the middle of a migration.
		if ( defined( 'WC_PRODUCT_TABLES_MIGRATING' ) && WC_PRODUCT_TABLES_MIGRATING ) {
			return;
		}

		add_filter( 'get_post_metadata', array( $this, 'get_metadata_from_tables' ), 99, 4 );
		add_filter( 'add_post_metadata', array( $this, 'add_metadata_to_tables' ), 99, 5 );
		add_filter( 'update_post_metadata', array( $this, 'update_metadata_in_tables' ), 99, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'delete_metadata_from_tables' ), 99, 5 );
	}

	/**
	 * Get product data from the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param bool   $single
	 * @return mixed $result
	 */
	public function get_metadata_from_tables( $result, $post_id, $meta_key, $single ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['get'];
		$mapped_func = $mapping[ $meta_key ]['get']['function'];
		$args = $mapping[ $meta_key ]['get']['args'];
		$args['product_id'] = $post_id;

		$query_results = call_user_func( $mapped_func, $args );
		if ( $single && $query_results ) {
			return $query_results[0];
		}

		if ( $single && empty( $query_results ) ) {
			return '';
		}

		return $query_results;
	}

	/**
	 * Add product data to the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param bool   $unique
	 * @return null/bool $result
	 */
	public function add_metadata_to_tables( $result, $post_id, $meta_key, $meta_value, $unique ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		if ( $unique ) {
			$existing = $this->get_metadata_from_tables( null, $post_id, $meta_key, false );
			if ( $existing ) {
				return false;
			}
		}

		$mapped_query = $mapping[ $meta_key ]['add'];
		$mapped_func = $mapping[ $meta_key ]['add']['function'];
		$args = $mapping[ $meta_key ]['add']['args'];
		$args['product_id'] = $post_id;
		$args['value'] = $meta_value;

		return (bool) call_user_func( $mapped_func, $args );
	}

	/**
	 * Update product data in the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param mixed  $prev_value
	 * @return null/bool $result
	 */
	public function update_metadata_in_tables( $result, $post_id, $meta_key, $meta_value, $prev_value ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['update'];

		// @todo: $prev_value support.
		$mapped_query = $mapping[ $meta_key ]['update'];
		$mapped_func = $mapping[ $meta_key ]['update']['function'];
		$args = $mapping[ $meta_key ]['update']['args'];
		$args['product_id'] = $post_id;
		$args['value'] = $meta_value;

		return (bool) call_user_func( $mapped_func, $args );
	}

	/**
	 * Delete product data from the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param bool   $delete_all
	 * @return null/bool $result
	 */
	public function delete_metadata_from_tables( $result, $post_id, $meta_key, $meta_value, $delete_all ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['delete'];

		// @todo $meta_value support
		// @todo $delete_all support
		$mapped_query = $mapping[ $meta_key ]['delete'];
		$mapped_func = $mapping[ $meta_key ]['delete']['function'];
		$args = $mapping[ $meta_key ]['delete']['args'];
		$args['product_id'] = $post_id;

		return (bool) call_user_func( $mapped_func, $args );
	}

	public function get_from_product_table( $args ) {
		global $wpdb;

		$defaults = array(
			'column' => '',
			'product_id' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['column'] || ! $args['product_id'] ) {
			return array();
		}

		$query = "SELECT %s from {$wpdb->prefix}wc_products WHERE product_id = %d";
		return $wpdb->get_results( $wpdb->prepare( $query, $args['column'], $args['product_id'] ) );
	}

	public function update_in_product_table( $args ) {
		global $wpdb;

		$defaults = array(
			'column' => '',
			'product_id' => 0,
			'format' => '%s',
			'value' => '',
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['column'] || ! $args['product_id'] ) {
			return false;
		}

		$format = $args['format'] ? array( $args['format'] ) : null;

		return (bool) $wpdb->update(
			$wpdb->prefix . 'wc_products',
			array( $args['column'] => $args['value'] ),
			array( 'product_id' => $args['product_id'] ),
			$format
		);
	}

	public function get_from_relationship_table( $args ) {
		global $wpdb;

		$defaults = array(
			'type' => '',
			'product_id' => '',
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['type'] || ! $args['product_id'] ) {
			return array();
		}

		$query = "SELECT object_id from {$wpdb->prefix}wc_product_relationships WHERE product_id = %d AND type = %s";
		return $wpdb->get_results( $wpdb->prepare( $query, $args['product_id'], $args['type'] ) );
	}

	public function update_relationship_table( $args ) {
		global $wpdb;

		$defaults = array(
			'type' => '',
			'product_id' => '',
			'value' => array(),
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['type'] || ! $args['product_id'] || ! is_array( $args['value'] ) ) {
			return false;
		}

		$new_values = $args['value'];
		$existing_relationship_data = $wpdb->get_results( $wpdb->prepare( "SELECT `object_id`, `type` FROM {$wpdb->prefix}wc_product_relationships WHERE `product_id` = %d AND `type` = %s ORDER BY `priority` ASC", $args['product_id'], $args['type'] ) );
		$old_values = wp_list_pluck( $existing_relationship_data, 'object_id' );
		$missing    = array_diff( $old_values, $new_values );

		// Delete from database missing values.
		foreach ( $missing as $object_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'wc_product_relationships', array(
					'object_id'  => $object_id,
					'product_id' => $args['product_id'],
				), array(
					'%d',
					'%d',
				)
			);
		}

		// Insert or update relationship.
		foreach ( $new_values as $key => $value ) {
			$relationship = array(
				'type'       => $args['type'],
				'product_id' => $args['product_id'],
				'object_id'  => $value,
				'priority'   => $key,
			);

			$wpdb->replace(
				"{$wpdb->prefix}wc_product_relationships",
				$relationship,
				array(
					'%s',
					'%d',
					'%d',
					'%d',
				)
			);
		}

		return true;
	}

	protected function get_mapping() {
		return array(

			/**
			 * In product table.
			 */
			'_sku' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'sku' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'sku', 'format' => '%s' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'sku', 'format' => '%s' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'sku', 'format' => '%s', 'value' => '' ),
				),
			),
			'_price' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'price' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'price', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'price', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'price', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_regular_price' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'regular_price' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'regular_price', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'regular_price', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'regular_price', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_sale_price' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'sale_price' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'sale_price', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'sale_price', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'sale_price', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_sale_price_dates_from' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'date_on_sale_from' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'date_on_sale_from', 'format' => '%d' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'date_on_sale_from', 'format' => '%d' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'date_on_sale_from', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_sale_price_dates_to' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'date_on_sale_to' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'date_on_sale_to', 'format' => '%d' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'date_on_sale_to', 'format' => '%d' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'date_on_sale_to', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'total_sales' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'total_sales' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'total_sales', 'format' => '%d' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'total_sales', 'format' => '%d' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'total_sales', 'format' => '%d', 'value' => 0 ),
				),
			),
			'_tax_status' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'tax_status' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'tax_status', 'format' => '%s' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'tax_status', 'format' => '%s' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'tax_status', 'format' => '%s', 'value' => 'taxable' ),
				),
			),
			'_tax_class' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'tax_class' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'tax_class', 'format' => '%s' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'tax_class', 'format' => '%s' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'tax_class', 'format' => '%s', 'value' => '' ),
				),
			),
			'_stock' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'stock_quantity' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'stock_quantity', 'format' => '%d' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'stock_quantity', 'format' => '%d' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'stock_quantity', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_stock_status' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'stock_status' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'stock_status', 'format' => '%s' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'stock_status', 'format' => '%s' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'stock_status', 'format' => '', 'value' => 'instock' ),
				),
			),
			'_length' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'length' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'length', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'length', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'length', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_width' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'width' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'width', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'width', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'width', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_height' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'height' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'height', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'height', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'height', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_weight' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'weight' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'weight', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'weight', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'weight', 'format' => '', 'value' => 'NULL' ),
				),
			),
			'_virtual' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'virtual' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'virtual', 'format' => '%d' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'virtual', 'format' => '%d' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'virtual', 'format' => '%d', 'value' => 0 ),
				),
			),
			'_downloadable' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'downloadable' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'downloadable', 'format' => '%d' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'downloadable', 'format' => '%d' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'downloadable', 'format' => '%d', 'value' => 0 ),
				),
			),
			'_wc_average_rating' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'average_rating' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'average_rating', 'format' => '%f' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'average_rating', 'format' => '%f' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'average_rating', 'format' => '%f', 'value' => 0 ),
				),
			),
			'_thumbnail_id' => array(
				'get' => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args' => array( 'column' => 'image_id' ),
				),
				'add' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'image_id', 'format' => '%d' ),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'image_id', 'format' => '%d' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args' => array( 'column' => 'image_id', 'format' => '%d', 'value' => 0 ),
				),
			),

			/**
			 * In relationship table.
			 */
			'_upsell_ids' => array(
				'get' => array(
					'function' => array( $this, 'get_from_relationship_table' ),
					'args' => array( 'type' => 'upsell' ),
				),
				'add' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args' => array( 'type' => 'upsell' ),
				),
				'update' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args' => array( 'type' => 'upsell' ),
				),
				'delete' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args' => array( 'type' => 'upsell', 'value' => array() ),
				),
			),
		);

		/*
			'_manage_stock', // Product table stock column. Null if not managing stock.
			'_upsell_ids', // Product relationship table
			'_crosssell_ids', // Product relationship table
			'_default_attributes', // Attributes table(s)
			'_product_attributes', // Attributes table(s)
			'_download_limit', // Product downloads table
			'_download_expiry', // Product downloads table
			'_featured', // Now a term.
			'_downloadable_files', // Product downloads table
			'_variation_description', // Now post excerpt @todo figure out a good way to handle this
			'_product_image_gallery', // Product relationship table
			'_visibility', // Now a term.
		*/
	}
}
new WC_Product_Tables_Backwards_Compatibility();
