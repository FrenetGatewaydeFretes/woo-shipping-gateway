<?php
/**
 * Uninstall: removes the options and cached data of the plugin. Orders, their Frenet data and the settings of the
 * Frenet shipping method are kept.
 *
 * @package woo-shipping-gateway
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the plugin data of the current site.
 *
 * @return void
 */
function wc_frenet_uninstall_site() {
	global $wpdb;
	$options = array(
		'woocommerce_frenet_labels_settings',
		'woocommerce_frenet_statuses_used',
		'woocommerce_frenet_labels_nfe_carriers',
		'woocommerce_frenet_labels_auto_since',
		'woocommerce_frenet_tracking_cursor',
		'woocommerce_frenet_tracking_schedule',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	foreach ( array( 'woocommerce_frenet_labels_carriers', 'woocommerce_frenet_labels_wallet', 'wc_frenet_shipping_cache_expired' ) as $transient ) {
		delete_transient( $transient );
	}
	$prefixes = array(
		'woocommerce_frenet_labels_wallet_',
		'wc_frenet_tracking_checked_',
		'wc_frenet_cep_',
		'wc_frenet_simulator_rl_',
		'frenet_labels_deposit_error_',
		'frenet_labels_setup_flash_',
	);
	foreach ( $prefixes as $prefix ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of transients with variable names.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
			)
		);
	}
	foreach ( array( 'woocommerce_frenet_tracking_sync', 'woocommerce_frenet_labels_poll', 'woocommerce_frenet_labels_recover', 'frenet_labels_auto_create' ) as $hook ) {
		wp_unschedule_hook( $hook );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $wc_frenet_site ) {
		switch_to_blog( $wc_frenet_site );
		wc_frenet_uninstall_site();
		restore_current_blog();
	}
} else {
	wc_frenet_uninstall_site();
}
