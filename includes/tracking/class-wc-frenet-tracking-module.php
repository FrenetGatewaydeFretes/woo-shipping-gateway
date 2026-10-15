<?php
defined( 'ABSPATH' ) || exit;

/**
 * Address by CEP, automatic tracking and bulk tracking codes. Loaded by the labels bootstrap; every
 * piece is switched in Frenet > Settings and none touches the quote or the product simulator.
 */
require_once __DIR__ . '/class-wc-frenet-autofill.php';
require_once __DIR__ . '/class-wc-frenet-order-statuses.php';
require_once __DIR__ . '/class-wc-frenet-tracking.php';
require_once __DIR__ . '/class-wc-frenet-tracking-admin.php';

WC_Frenet_Autofill::init();
WC_Frenet_Order_Statuses::init();
WC_Frenet_Tracking::init();
if ( is_admin() ) {
	WC_Frenet_Tracking_Admin::init();
}
