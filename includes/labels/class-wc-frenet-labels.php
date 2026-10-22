<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frenet labels (shipments) inside the official plugin: loads the classes and wires the hooks.
 * Nothing here touches the quote or the existing shipping method settings.
 */
require_once __DIR__ . '/class-wc-frenet-labels-compare.php';
require_once __DIR__ . '/class-wc-frenet-labels-nfe.php';
require_once __DIR__ . '/class-wc-frenet-labels-settings.php';
require_once __DIR__ . '/class-wc-frenet-labels-carriers.php';
require_once __DIR__ . '/class-wc-frenet-labels-api.php';
require_once __DIR__ . '/class-wc-frenet-labels-payload.php';
require_once __DIR__ . '/class-wc-frenet-labels-service.php';
require_once __DIR__ . '/class-wc-frenet-labels-dropoff.php';
require_once __DIR__ . '/class-wc-frenet-labels-admin.php';
require_once __DIR__ . '/class-wc-frenet-labels-auto.php';
require_once __DIR__ . '/class-wc-frenet-labels-products.php';

require_once dirname( __DIR__ ) . '/tracking/class-wc-frenet-tracking-module.php';

add_action( WC_Frenet_Labels_Service::POLL_HOOK, array( 'WC_Frenet_Labels_Service', 'poll' ), 10, 2 );
// Automatic shipments listen to paid orders on the storefront too, not only in the admin.
WC_Frenet_Labels_Auto::init();
if ( is_admin() ) {
	WC_Frenet_Labels_Admin::init();
	WC_Frenet_Labels_Products::init();
}
