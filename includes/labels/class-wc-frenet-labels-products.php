<?php
/**
 * Frenet › Produtos: weight, size and "Automatic shipment with" of every product that ships, editable in one
 * table (and the same field in the product's Shipping tab). Writes the WooCommerce product fields and the
 * `_frenet_auto_carrier` meta, so CSV imports and the product form see the same values.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data and saving of the Products screen.
 */
class WC_Frenet_Labels_Products {

	const PER_PAGE       = 50;
	const FILTER_ALL     = 'all';
	const FILTER_NO_SIZE = 'no_size';
	const FILTER_RULE    = 'rule';
	const ACTION         = 'frenet_labels_products_save';

	/**
	 * Hooks: the save of the screen and the field in the product's Shipping tab.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_product_options_shipping', array( __CLASS__, 'product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_field' ) );
	}

	/**
	 * One page of products.
	 *
	 * @param string $search Name or SKU.
	 * @param string $filter FILTER_* value.
	 * @param int    $page   Zero-based page.
	 * @return array{rows: array, total: int, pages: int}
	 */
	public static function query( $search, $filter, $page ) {
		$args  = self::args( $search, $filter );
		$found = null === $args ? null : wc_get_products(
			$args + array(
				'paginate' => true,
				'limit'    => self::PER_PAGE,
				'page'     => max( 0, (int) $page ) + 1,
			)
		);
		$total = $found ? (int) $found->total : 0;
		$rows  = array();
		foreach ( $found ? (array) $found->products : array() as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$rows[] = self::row( $product );
		}
		return array(
			'rows'  => $rows,
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
		);
	}

	/**
	 * Query of the products that ship (simple products and variations), filtered, by name; null when nothing matches.
	 *
	 * @param string $search Name or SKU.
	 * @param string $filter FILTER_* value.
	 * @return array<string, mixed>|null
	 */
	private static function args( $search, $filter ) {
		$base = array(
			'type'    => array( 'simple', 'variation' ),
			'status'  => array( 'publish', 'private', 'draft' ),
			'virtual' => false,
			'orderby' => 'title',
			'order'   => 'ASC',
			'return'  => 'ids',
		);
		if ( self::FILTER_NO_SIZE === $filter ) {
			$base['include'] = self::without_size();
			if ( ! $base['include'] ) {
				return null;
			}
		} elseif ( self::FILTER_RULE === $filter ) {
			$base['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin screen, paged.
				array(
					'key'     => WC_Frenet_Labels_Auto::PRODUCT_META,
					'value'   => '',
					'compare' => '!=',
				),
			);
		}
		if ( '' === $search ) {
			return $base;
		}
		$all     = array_merge( $base, array( 'limit' => -1 ) );
		$by_name = (array) wc_get_products( array_merge( $all, array( 's' => $search ) ) );
		$by_sku  = (array) wc_get_products(
			array_merge(
				$all,
				array(
					'meta_query' => array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin search.
						isset( $base['meta_query'] ) ? array( $base['meta_query'] ) : array(),
						array(
							array(
								'key'     => '_sku',
								'value'   => $search,
								'compare' => 'LIKE',
							),
						)
					),
				)
			)
		);

		$base['include'] = array_values( array_unique( array_map( 'intval', array_merge( $by_name, $by_sku ) ) ) );
		return $base['include'] ? $base : null;
	}

	/**
	 * Ids of the products and variations without weight or one of the sizes, counting what a variation inherits
	 * from its parent product, as the rows show it.
	 *
	 * @return int[]
	 */
	private static function without_size() {
		global $wpdb;
		$joins   = '';
		$missing = array();
		foreach ( array( '_weight', '_length', '_width', '_height' ) as $i => $key ) {
			$joins    .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} o{$i} ON o{$i}.post_id = p.ID AND o{$i}.meta_key = %s LEFT JOIN {$wpdb->postmeta} r{$i} ON r{$i}.post_id = p.post_parent AND r{$i}.meta_key = %s", $key, $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names and aliases only.
			$missing[] = "CAST( REPLACE( IF( o{$i}.meta_value IS NULL OR o{$i}.meta_value IN ( '', '0' ), IFNULL( r{$i}.meta_value, '' ), o{$i}.meta_value ), ',', '.' ) AS DECIMAL( 20, 6 ) ) <= 0";
		}
		$sql = "SELECT p.ID FROM {$wpdb->posts} p{$joins} WHERE p.post_type IN ( 'product', 'product_variation' ) AND p.post_status IN ( 'publish', 'private', 'draft' ) AND ( " . implode( ' OR ', $missing ) . ' )';
		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- built above from prepared parts; admin screen.
	}

	/**
	 * One row of the table.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private static function row( WC_Product $product ) {
		$rule   = (string) $product->get_meta( WC_Frenet_Labels_Auto::PRODUCT_META );
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
		return array(
			'id'        => $product->get_id(),
			'name'      => $product->get_name(),
			'sku'       => (string) $product->get_sku(),
			'variation' => (bool) $parent,
			'weight'    => self::measure( $product->get_weight() ),
			'length'    => self::measure( $product->get_length() ),
			'width'     => self::measure( $product->get_width() ),
			'height'    => self::measure( $product->get_height() ),
			'rule'      => WC_Frenet_Labels_Auto::valid_product_value( $rule ) ? $rule : '',
			'inherits'  => $parent && '' === $rule ? (string) $parent->get_meta( WC_Frenet_Labels_Auto::PRODUCT_META ) : '',
			'complete'  => '' !== self::measure( $product->get_weight() ) && '' !== self::measure( $product->get_length() ) && '' !== self::measure( $product->get_width() ) && '' !== self::measure( $product->get_height() ),
			'edit_url'  => get_edit_post_link( $parent ? $parent->get_id() : $product->get_id(), 'raw' ),
		);
	}

	/**
	 * A stored measure as typed text ("1.5"), '' when missing or zero.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function measure( $value ) {
		$number = (float) str_replace( ',', '.', (string) $value );
		return $number > 0 ? rtrim( rtrim( number_format( $number, 3, '.', '' ), '0' ), '.' ) : '';
	}

	/**
	 * Saves the rows the shopkeeper changed (changed[]): weight, size and the rule.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( WC_Frenet_Labels_Admin::CAP ) || ! check_admin_referer( self::ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woo-shipping-gateway' ), 403 );
		}
		$rows    = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized below.
		$changed = isset( $_POST['changed'] ) ? array_unique( array_map( 'absint', (array) wp_unslash( $_POST['changed'] ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint.
		$saved   = 0;
		foreach ( $changed as $id ) {
			$row     = isset( $rows[ $id ] ) && is_array( $rows[ $id ] ) ? $rows[ $id ] : null;
			$product = $id ? wc_get_product( $id ) : null;
			if ( ! $row || ! $product ) {
				continue;
			}
			foreach ( array( 'weight', 'length', 'width', 'height' ) as $field ) {
				$value = isset( $row[ $field ] ) ? (float) str_replace( ',', '.', sanitize_text_field( (string) $row[ $field ] ) ) : 0;
				if ( $value > 0 ) {
					$product->{'set_' . $field }( wc_format_decimal( $value, 3 ) );
				}
			}
			$rule = sanitize_text_field( (string) ( $row['rule'] ?? '' ) );
			if ( WC_Frenet_Labels_Auto::valid_product_value( $rule ) ) {
				$product->update_meta_data( WC_Frenet_Labels_Auto::PRODUCT_META, $rule );
			}
			$product->save();
			++$saved;
		}
		$back = array( 'saved' => $saved );
		foreach ( array( 'q', 'f', 'p' ) as $key ) {
			if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
				$back[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
			}
		}
		wp_safe_redirect( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-products', $back ) );
		exit;
	}

	/**
	 * "Automatic Frenet shipment with" in the product's Shipping tab (the same value the Products screen edits).
	 *
	 * @return void
	 */
	public static function product_field() {
		woocommerce_wp_select(
			array(
				'id'          => WC_Frenet_Labels_Auto::PRODUCT_META,
				'label'       => __( 'Automatic Frenet shipment with', 'woo-shipping-gateway' ),
				'options'     => WC_Frenet_Labels_Auto::product_options(),
				'desc_tip'    => true,
				'description' => __( 'Carrier of the automatic shipment when this product is in the order. Empty: the general rule of Frenet > Settings > Automation. Also editable in Frenet > Products.', 'woo-shipping-gateway' ),
			)
		);
	}

	/**
	 * Saves the Shipping tab field.
	 *
	 * @param WC_Product $product Product being saved.
	 * @return void
	 */
	public static function save_product_field( $product ) {
		if ( ! isset( $_POST[ WC_Frenet_Labels_Auto::PRODUCT_META ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product form.
			return;
		}
		$rule = sanitize_text_field( wp_unslash( $_POST[ WC_Frenet_Labels_Auto::PRODUCT_META ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product form.
		if ( WC_Frenet_Labels_Auto::valid_product_value( $rule ) ) {
			$product->update_meta_data( WC_Frenet_Labels_Auto::PRODUCT_META, $rule );
		}
	}
}
