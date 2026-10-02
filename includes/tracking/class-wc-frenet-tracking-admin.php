<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin of the tracking: AJAX for "update now", saving a code by hand and the bulk import (preview
 * then apply). Needs manage_woocommerce, the "frenet_labels" nonce and only the store token: the
 * partner token is not required to track.
 */
class WC_Frenet_Tracking_Admin {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'sync', 'sync_all', 'save', 'preview', 'import' ) as $action ) {
			add_action( 'wp_ajax_frenet_tracking_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 ); // After admin-labels.js (priority 10).
	}

	/**
	 * Script of the tracking page and of the order box (after admin-labels.js, which defines frenetLabels).
	 *
	 * @return void
	 */
	public static function assets() {
		if ( ! wp_script_is( 'frenet-labels', 'enqueued' ) ) {
			return;
		}
		$base = plugins_url( '/', WOO_FRENET_PATH . 'woo-shipping-gateway.php' );
		wp_enqueue_script( 'frenet-tracking', $base . 'assets/js/admin-tracking.js', array( 'frenet-labels' ), WC_Frenet_Main::VERSION . '.' . filemtime( WOO_FRENET_PATH . 'assets/js/admin-tracking.js' ), true );
		wp_localize_script(
			'frenet-tracking',
			'frenetTracking',
			array(
				'i18n' => array(
					'updating'   => __( 'Checking with Frenet…', 'woo-shipping-gateway' ),
					'saving'     => __( 'Saving…', 'woo-shipping-gateway' ),
					'updated'    => __( 'Updated', 'woo-shipping-gateway' ),
					'saved'      => __( 'Code saved', 'woo-shipping-gateway' ),
					'removed'    => __( 'Code removed', 'woo-shipping-gateway' ),
					'paste'      => __( 'Paste at least one line "order;code".', 'woo-shipping-gateway' ),
					/* translators: %d: number of orders */
					'applyN'     => __( 'Apply to %d orders', 'woo-shipping-gateway' ),
					'applyOne'   => __( 'Apply to 1 order', 'woo-shipping-gateway' ),
					'nothing'    => __( 'Nothing to apply: check the lines marked below.', 'woo-shipping-gateway' ),
					/* translators: 1: applied, 2: skipped */
					'imported'   => __( '%1$d codes applied, %2$d lines skipped.', 'woo-shipping-gateway' ),
					/* translators: %s: line numbers */
					'invalid'    => __( 'Lines not understood (expected "order;code"): %s', 'woo-shipping-gateway' ),
					'resultAdd'  => __( 'New code', 'woo-shipping-gateway' ),
					'resultRep'  => __( 'Replaces the current code', 'woo-shipping-gateway' ),
					'resultSame' => __( 'Already has this code', 'woo-shipping-gateway' ),
					'resultNone' => __( 'Order not found', 'woo-shipping-gateway' ),
					'resultDup'  => __( 'Repeated order: only the first line counts', 'woo-shipping-gateway' ),
					/* translators: 1: synced orders, 2: status changes, 3: errors */
					'syncedAll'  => __( '%1$d orders checked, %2$d status changes, %3$d errors.', 'woo-shipping-gateway' ),
				),
			)
		);
	}

	/**
	 * Common guard: capability, nonce and the store token.
	 *
	 * @return void
	 */
	private static function guard() {
		if ( ! current_user_can( WC_Frenet_Labels_Admin::CAP ) || ! check_ajax_referer( WC_Frenet_Labels_Admin::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page.', 'woo-shipping-gateway' ) ), 403 );
		}
		if ( '' === WC_Frenet_Labels_Settings::store_token() ) {
			wp_send_json_error( array( 'message' => __( 'Frenet token not configured in the Frenet shipping method.', 'woo-shipping-gateway' ) ), 400 );
		}
	}

	/**
	 * Order of the request.
	 *
	 * @return WC_Order
	 */
	private static function order() {
		$order = wc_get_order( absint( $_POST['order'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'woo-shipping-gateway' ) ), 404 );
		}
		return $order;
	}

	/**
	 * Tracking summary for the screens.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	public static function summary( WC_Order $order ) {
		$t    = WC_Frenet_Tracking::get( $order );
		$last = $t['events'] ? end( $t['events'] ) : null;
		return array(
			'code'        => $t['code'],
			'url'         => $t['url'],
			'description' => $last ? $last['description'] : '',
			'when'        => $last ? trim( $last['date'] . ' · ' . $last['location'], ' ·' ) : '',
			'events'      => count( $t['events'] ),
			'status'      => wc_get_order_status_name( $order->get_status() ),
			'synced'      => $t['synced'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t['synced'] ) : '',
		);
	}

	/**
	 * Updates one order now.
	 *
	 * @return void
	 */
	public static function ajax_sync() {
		self::guard();
		$order = self::order();
		$r     = WC_Frenet_Tracking::sync( $order );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		$order = wc_get_order( $order->get_id() );
		wp_send_json_success( self::summary( $order ) );
	}

	/**
	 * Runs the hourly sync now.
	 *
	 * @return void
	 */
	public static function ajax_sync_all() {
		self::guard();
		wp_send_json_success( WC_Frenet_Tracking::cron_sync() );
	}

	/**
	 * Saves (or removes) a code typed on the order screen.
	 *
	 * @return void
	 */
	public static function ajax_save() {
		self::guard();
		$order  = self::order();
		$code   = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$notify = ! empty( $_POST['notify'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$clean  = WC_Frenet_Tracking::clean_code( $code );
		if ( '' !== $clean && ( strlen( $clean ) < 8 || strlen( $clean ) > 40 ) ) {
			wp_send_json_error( array( 'message' => __( 'This does not look like a tracking code: use letters and numbers, 8 to 40 characters.', 'woo-shipping-gateway' ) ) );
		}
		WC_Frenet_Tracking::set_code( $order, $clean, $notify );
		wp_send_json_success( self::summary( wc_get_order( $order->get_id() ) ) );
	}

	/**
	 * Pasted lines from the request.
	 *
	 * @return string
	 */
	private static function lines() {
		return sanitize_textarea_field( wp_unslash( $_POST['lines'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
	}

	/**
	 * Bulk import preview (changes nothing).
	 *
	 * @return void
	 */
	public static function ajax_preview() {
		self::guard();
		wp_send_json_success( WC_Frenet_Tracking::preview( self::lines() ) );
	}

	/**
	 * Applies the bulk import.
	 *
	 * @return void
	 */
	public static function ajax_import() {
		self::guard();
		$notify = ! empty( $_POST['notify'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		wp_send_json_success( WC_Frenet_Tracking::import( self::lines(), $notify ) );
	}
}
