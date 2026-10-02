<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frenet labels (shipments) inside the official plugin: loads the classes and wires the hooks.
 * Nothing here touches the quote or the existing shipping method settings.
 */
require_once __DIR__ . '/class-wc-frenet-labels-compare.php';
require_once __DIR__ . '/class-wc-frenet-labels-nfe.php';
require_once __DIR__ . '/class-wc-frenet-labels-settings.php';
require_once __DIR__ . '/class-wc-frenet-labels-api.php';
require_once __DIR__ . '/class-wc-frenet-labels-payload.php';
require_once __DIR__ . '/class-wc-frenet-labels-service.php';
require_once __DIR__ . '/class-wc-frenet-labels-admin.php';

require_once dirname( __DIR__ ) . '/tracking/class-wc-frenet-tracking-module.php';

add_action( WC_Frenet_Labels_Service::POLL_HOOK, array( 'WC_Frenet_Labels_Service', 'poll' ), 10, 2 );
add_action(
	'rest_api_init',
	static function () {
		// NotificationUrl of "Adicionar saldo": Frenet credits the wallet itself; this only drops the cached balance.
		register_rest_route(
			'frenet/v1',
			'/deposit',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					delete_transient( WC_Frenet_Labels_Service::WALLET );
					return new WP_REST_Response( array( 'ok' => true ), 200 );
				},
			)
		);
	}
);
if ( is_admin() ) {
	WC_Frenet_Labels_Admin::init();
}
