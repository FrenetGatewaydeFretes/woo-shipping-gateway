<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shipping order statuses set by the Frenet tracking. Slugs stay ≤ 20 characters with "wc-".
 * They are registered while the automatic status is on, and also after it was ever used, so
 * orders already in one of them never disappear from the order list.
 */
class WC_Frenet_Order_Statuses {

	const TRANSIT   = 'wc-frenet-transit';
	const PICKUP    = 'wc-frenet-pickup';
	const DELIVERED = 'wc-frenet-delivered';
	const RETURNING = 'wc-frenet-returning';
	const USED      = 'woocommerce_frenet_statuses_used';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::active() ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_to_list' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'paid' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( __CLASS__, 'paid' ) );
		add_filter( 'woocommerce_analytics_actionable_order_statuses', array( __CLASS__, 'paid' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( __CLASS__, 'not_paid_yet' ) );
	}

	/**
	 * Whether the statuses exist on this store.
	 *
	 * @return bool
	 */
	public static function active() {
		return WC_Frenet_Labels_Settings::on( 'tracking_status' ) || (bool) get_option( self::USED );
	}

	/**
	 * Slug (with "wc-") → label.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			self::TRANSIT   => _x( 'In transit', 'Order status', 'woo-shipping-gateway' ),
			self::PICKUP    => _x( 'Awaiting pickup', 'Order status', 'woo-shipping-gateway' ),
			self::DELIVERED => _x( 'Delivered', 'Order status', 'woo-shipping-gateway' ),
			self::RETURNING => _x( 'Returning', 'Order status', 'woo-shipping-gateway' ),
		);
	}

	/**
	 * Registers the post statuses (also used by HPOS for the list counts).
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::labels() as $slug => $label ) {
			register_post_status(
				$slug,
				array(
					'label'                     => $label,
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					// Built by hand: the label is already translated, only the count varies.
					'label_count'               => array(
						0          => $label . ' <span class="count">(%s)</span>',
						1          => $label . ' <span class="count">(%s)</span>',
						'singular' => $label . ' <span class="count">(%s)</span>',
						'plural'   => $label . ' <span class="count">(%s)</span>',
						'context'  => null,
						'domain'   => null,
					),
				)
			);
		}
	}

	/**
	 * Inserts the statuses right after "Processing".
	 *
	 * @param array<string, string> $statuses WooCommerce statuses.
	 * @return array<string, string>
	 */
	public static function add_to_list( $statuses ) {
		$out = array();
		foreach ( $statuses as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$out = array_merge( $out, self::labels() );
			}
		}
		return $out + self::labels();
	}

	/**
	 * Paid statuses (without "wc-") for reports; "Returning" is not counted as a sale.
	 *
	 * @param array<int, string> $statuses Statuses.
	 * @return array<int, string>
	 */
	public static function paid( $statuses ) {
		foreach ( array_keys( self::labels() ) as $slug ) {
			if ( self::RETURNING !== $slug ) {
				$statuses[] = substr( $slug, 3 );
			}
		}
		return array_values( array_unique( $statuses ) );
	}

	/**
	 * A payment never moves an order out of a shipping status.
	 *
	 * @param array<int, string> $statuses Statuses.
	 * @return array<int, string>
	 */
	public static function not_paid_yet( $statuses ) {
		$ours = array_map(
			function ( $s ) {
				return substr( $s, 3 );
			},
			array_keys( self::labels() )
		);
		return array_values( array_diff( $statuses, $ours ) );
	}
}
