<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin of the Frenet labels: top-level "Frenet" menu (Configuração, Criar envio, Etiquetas, Impressão,
 * Rastreios and the "em breve" items), AJAX actions, the order list bulk action and the order box.
 * Every action needs manage_woocommerce and the "frenet_labels" nonce.
 */
class WC_Frenet_Labels_Admin {

	const CAP   = 'manage_woocommerce';
	const NONCE = 'frenet_labels';
	const SLUG  = 'frenet';
	const PANEL = 'https://painel.frenet.com.br';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_frenet_labels_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_frenet_labels_print', array( __CLASS__, 'print_pdf' ) );
		add_action( 'admin_post_frenet_labels_deposit', array( __CLASS__, 'deposit' ) );
		foreach ( array( 'quote', 'create', 'pay', 'cancel', 'nfe', 'tracking', 'wallet' ) as $action ) {
			add_action( 'wp_ajax_frenet_labels_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
		foreach ( array( 'edit-shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			add_filter( 'bulk_actions-' . $screen, array( __CLASS__, 'bulk_action' ) );
			add_filter( 'handle_bulk_actions-' . $screen, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
	}

	/**
	 * Pages of the menu: slug → [menu title, page title, renderer].
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function pages() {
		return array(
			self::SLUG               => array( __( 'Settings', 'woo-shipping-gateway' ), __( 'Frenet settings', 'woo-shipping-gateway' ), 'settings' ),
			self::SLUG . '-create'   => array( __( 'Create shipment', 'woo-shipping-gateway' ), __( 'Create shipment', 'woo-shipping-gateway' ), 'create' ),
			self::SLUG . '-labels'   => array( __( 'Labels', 'woo-shipping-gateway' ), __( 'Manage your labels', 'woo-shipping-gateway' ), 'labels' ),
			self::SLUG . '-print'    => array( __( 'Printing', 'woo-shipping-gateway' ), __( 'Label printing', 'woo-shipping-gateway' ), 'printing' ),
			self::SLUG . '-tracking' => array( __( 'Tracking', 'woo-shipping-gateway' ), __( 'Tracking', 'woo-shipping-gateway' ), 'tracking' ),
			self::SLUG . '-whatsapp' => array( 'WhatsApp', 'WhatsApp', 'soon' ),
			self::SLUG . '-reverse'  => array( __( 'Returns', 'woo-shipping-gateway' ), __( 'Returns', 'woo-shipping-gateway' ), 'soon' ),
			self::SLUG . '-receipt'  => array( __( 'Proof of delivery', 'woo-shipping-gateway' ), __( 'Proof of delivery', 'woo-shipping-gateway' ), 'soon' ),
			self::SLUG . '-tower'    => array( __( 'Control tower', 'woo-shipping-gateway' ), __( 'Control tower', 'woo-shipping-gateway' ), 'soon' ),
		);
	}

	/**
	 * Registers the top-level menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page( 'Frenet', 'Frenet', self::CAP, self::SLUG, array( __CLASS__, 'render' ), self::menu_icon(), 56 );
		foreach ( self::pages() as $slug => $page ) {
			$title = 'soon' === $page[2] ? $page[0] . ' · ' . __( 'soon', 'woo-shipping-gateway' ) : $page[0];
			add_submenu_page( self::SLUG, $page[1], $title, self::CAP, $slug, array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Frenet hexagon mark as an SVG data URI; WordPress recolours it with the admin colour scheme.
	 *
	 * @return string
	 */
	public static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M8.2 3.1 3.4 5.9a1.6 1.6 0 0 0-.8 1.4v5.5c0 .6.3 1.1.8 1.4l4.8 2.7c.5.3 1.1.3 1.6 0l4.8-2.7c.5-.3.8-.8.8-1.4V7.3c0-.6-.3-1.1-.8-1.4L9.8 3.1a1.6 1.6 0 0 0-1.6 0Zm.8 3 3.6 2.1v4.2L9 14.5l-3.6-2.1V8.2Z"/><path fill="black" d="m12.6 1.6 3.8 2.2c.4.2.6.6.6 1.1v4.3l-1.6-.9V5.3L12.2 3.4Z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress menu icons need a data URI.
	}

	/**
	 * Current admin page slug ('' outside the Frenet pages).
	 *
	 * @return string
	 */
	public static function current_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return isset( self::pages()[ $page ] ) ? $page : '';
	}

	/**
	 * Renders the current page inside the common frame.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woo-shipping-gateway' ), 403 );
		}
		$slug = self::current_page();
		$page = self::pages()[ $slug ];
		$mark = plugins_url( 'assets/images/frenet-mark.png', WOO_FRENET_PATH . 'woo-shipping-gateway.php' );
		echo '<div class="wrap frenet-labels">';
		echo '<header class="frenet-header">';
		echo '<span class="frenet-brand"><img src="' . esc_url( $mark ) . '" alt="" width="28" height="28"> <span class="frenet-brand__word">frenet</span></span>';
		echo '<h1 class="frenet-title">' . esc_html( $page[1] ) . '</h1>';
		echo '</header>';
		echo '<hr class="wp-header-end">';
		if ( WC_Frenet_Labels_Settings::simulated() ) {
			echo '<p class="frenet-sim" role="note"><strong>' . esc_html__( 'Simulator mode', 'woo-shipping-gateway' ) . '</strong> ' . esc_html__( 'Labels, balance and lists come from the local test simulator. Nothing is sent to Frenet or charged.', 'woo-shipping-gateway' ) . '</p>';
		}
		// Tracking only needs the store token; the label screens need the partner token too.
		if ( ! in_array( $page[2], array( 'settings', 'soon', 'tracking' ), true ) && ! WC_Frenet_Labels_Settings::ready() ) {
			self::view( 'not-ready' );
			echo '</div>';
			return;
		}
		self::view( $page[2], array( 'page' => $page ) );
		echo '</div>';
	}

	/**
	 * Includes a view from views/.
	 *
	 * @param string               $name View name.
	 * @param array<string, mixed> $vars Variables.
	 * @return void
	 */
	public static function view( $name, array $vars = array() ) {
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- local view variables.
		include __DIR__ . '/views/' . $name . '.php';
	}

	/**
	 * Admin URL of a Frenet page.
	 *
	 * @param string               $slug Page slug.
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	public static function url( $slug, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * CSS and JS on the Frenet pages and order screens.
	 *
	 * @param string $hook Admin hook suffix.
	 * @return void
	 */
	public static function assets( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress passes the hook suffix.
		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_order = $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
		if ( '' === self::current_page() && ! $is_order ) {
			return;
		}
		$base = plugins_url( '/', WOO_FRENET_PATH . 'woo-shipping-gateway.php' );
		wp_enqueue_style( 'frenet-labels', $base . 'assets/css/admin-labels.css', array(), WC_Frenet_Main::VERSION . '.' . filemtime( WOO_FRENET_PATH . 'assets/css/admin-labels.css' ) );
		wp_enqueue_script( 'frenet-labels', $base . 'assets/js/admin-labels.js', array(), WC_Frenet_Main::VERSION . '.' . filemtime( WOO_FRENET_PATH . 'assets/js/admin-labels.js' ), true );
		wp_localize_script(
			'frenet-labels',
			'frenetLabels',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'journey' => WC_Frenet_Labels_Settings::get( 'journey' ),
				'wallet'  => WC_Frenet_Labels_Settings::on( 'wallet_payment' ),
				'i18n'    => self::js_strings(),
			)
		);
	}

	/**
	 * Strings used by admin-labels.js.
	 *
	 * @return array<string, string>
	 */
	private static function js_strings() {
		return array(
			'calculating'   => __( 'Calculating freight…', 'woo-shipping-gateway' ),
			'selectOrders'  => __( 'Select at least one order.', 'woo-shipping-gateway' ),
			'bestPrice'     => __( 'Best price', 'woo-shipping-gateway' ),
			'bestTime'      => __( 'Fastest', 'woo-shipping-gateway' ),
			'customer'      => __( 'Customer choice', 'woo-shipping-gateway' ),
			/* translators: %d: business days */
			'days'          => __( '%d business days', 'woo-shipping-gateway' ),
			/* translators: %d: business days (always 1) */
			'day'           => __( '%d business day', 'woo-shipping-gateway' ),
			'noService'     => __( 'No carrier', 'woo-shipping-gateway' ),
			/* translators: 1: number of labels, 2: total amount */
			'buyHere'       => __( 'Buy %1$d labels · %2$s', 'woo-shipping-gateway' ),
			/* translators: %s: amount */
			'buyHereOne'    => __( 'Buy 1 label · %s', 'woo-shipping-gateway' ),
			/* translators: %d: number of orders */
			'sendPanel'     => __( 'Send %d orders to the Frenet panel', 'woo-shipping-gateway' ),
			'sendPanelOne'  => __( 'Send 1 order to the Frenet panel', 'woo-shipping-gateway' ),
			/* translators: %d: number of shipments */
			'createOnly'    => __( 'Create %d shipments (pay later in Labels)', 'woo-shipping-gateway' ),
			'working'       => __( 'Working… do not close this page.', 'woo-shipping-gateway' ),
			'done'          => __( 'Done', 'woo-shipping-gateway' ),
			'failed'        => __( 'Not created', 'woo-shipping-gateway' ),
			'balanceAfter'  => __( 'Balance after', 'woo-shipping-gateway' ),
			'notEnough'     => __( 'Your balance does not cover this total. Add balance in the Frenet panel or select fewer orders.', 'woo-shipping-gateway' ),
			'confirmCancel' => __( 'Cancel this Frenet label? If it was paid, Frenet returns the amount to the wallet (usually overnight).', 'woo-shipping-gateway' ),
			/* translators: 1: number of labels, 2: total amount */
			'confirmPay'    => __( 'Pay %1$d labels with the Frenet wallet (%2$s)?', 'woo-shipping-gateway' ),
			'saved'         => __( 'Saved', 'woo-shipping-gateway' ),
			'error'         => __( 'Something went wrong. Reload the page and try again.', 'woo-shipping-gateway' ),
		);
	}

	/* ------------------------------------------------------------------ settings */

	/**
	 * Saves the settings form.
	 *
	 * @return void
	 */
	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( self::NONCE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woo-shipping-gateway' ), 403 );
		}
		$input = isset( $_POST['frenet_labels'] ) && is_array( $_POST['frenet_labels'] ) ? wp_unslash( $_POST['frenet_labels'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized key by key in save().
		WC_Frenet_Labels_Settings::save( $input );
		delete_transient( WC_Frenet_Labels_Service::WALLET );
		wp_safe_redirect( self::url( self::SLUG, array( 'saved' => 1 ) ) );
		exit;
	}

	/**
	 * "Adicionar saldo": opens the Mercado Pago checkout of a Frenet wallet deposit.
	 *
	 * @return void
	 */
	public static function deposit() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( self::NONCE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woo-shipping-gateway' ), 403 );
		}
		$value = (float) str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ) ) );
		$back  = wp_get_referer() ? wp_get_referer() : self::url( self::SLUG );
		$back  = remove_query_arg( array( 'deposit', 'saved' ), $back );
		$url   = WC_Frenet_Labels_Service::deposit( $value, $back, rest_url( WC_Frenet_Labels_Service::DEPOSIT_ROUTE ) );
		if ( is_wp_error( $url ) ) {
			wp_die(
				esc_html( $url->get_error_message() ),
				esc_html__( 'Add balance', 'woo-shipping-gateway' ),
				array(
					'back_link' => true,
					'response'  => 502,
				)
			);
		}
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Mercado Pago checkout (checked in the service).
		exit;
	}

	/* ------------------------------------------------------------------ AJAX */

	/**
	 * Common guard of every AJAX action.
	 *
	 * @return void
	 */
	private static function guard() {
		if ( ! current_user_can( self::CAP ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page.', 'woo-shipping-gateway' ) ), 403 );
		}
		if ( ! WC_Frenet_Labels_Settings::ready() ) {
			wp_send_json_error( array( 'message' => __( 'Frenet labels are not configured. Open Frenet > Settings.', 'woo-shipping-gateway' ) ), 400 );
		}
	}

	/**
	 * Order from the request, or a JSON error.
	 *
	 * @return WC_Order
	 */
	private static function request_order() {
		$order = wc_get_order( absint( $_POST['order_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'woo-shipping-gateway' ) ), 404 );
		}
		return $order;
	}

	/**
	 * Carrier comparison of one order.
	 *
	 * @return void
	 */
	public static function ajax_quote() {
		self::guard();
		$order = self::request_order();
		$cmp   = WC_Frenet_Labels_Service::compare( $order );
		if ( is_wp_error( $cmp ) ) {
			wp_send_json_error( array( 'message' => $cmp->get_error_message() ) );
		}
		wp_send_json_success( $cmp );
	}

	/**
	 * Creates the shipment of one order (the screen calls it once per order, so a failure never stops the rest).
	 *
	 * @return void
	 */
	public static function ajax_create() {
		self::guard();
		$order   = self::request_order();
		$service = sanitize_text_field( wp_unslash( $_POST['service'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$journey = sanitize_key( wp_unslash( $_POST['journey'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$res     = WC_Frenet_Labels_Service::create( $order, $service, $journey );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		$res['status_label'] = WC_Frenet_Labels_Service::status_label( (int) $res['status'] );
		$res['tone']         = WC_Frenet_Labels_Service::status_tone( (int) $res['status'] );
		wp_send_json_success( $res );
	}

	/**
	 * Pays the selected shipments (cart).
	 *
	 * @return void
	 */
	public static function ajax_pay() {
		self::guard();
		$ids = array_map( 'strval', array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		if ( ! array_filter( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one unpaid label.', 'woo-shipping-gateway' ) ) );
		}
		wp_send_json_success( WC_Frenet_Labels_Service::pay( $ids ) );
	}

	/**
	 * Cancels one shipment.
	 *
	 * @return void
	 */
	public static function ajax_cancel() {
		self::guard();
		$res = WC_Frenet_Labels_Service::cancel( (string) absint( $_POST['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		$res['status_label'] = WC_Frenet_Labels_Service::status_label( $res['status'] );
		$res['message']      = $res['refunded']
			? __( 'Label cancelled. Frenet returns the amount to the wallet, usually overnight.', 'woo-shipping-gateway' )
			: __( 'Unpaid shipment deleted.', 'woo-shipping-gateway' );
		wp_send_json_success( $res );
	}

	/**
	 * Saves (or removes) the NF-e key of an order.
	 *
	 * @return void
	 */
	public static function ajax_nfe() {
		self::guard();
		$order = self::request_order();
		$raw   = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		if ( '' === WC_Frenet_Labels_Nfe::normalize( $raw ) ) {
			$order->delete_meta_data( WC_Frenet_Labels_Nfe::META );
			$order->save();
			wp_send_json_success( array( 'summary' => '' ) );
		}
		$parsed = WC_Frenet_Labels_Nfe::parse( $raw );
		if ( ! $parsed['ok'] ) {
			wp_send_json_error( array( 'message' => WC_Frenet_Labels_Nfe::error_message( $parsed['error'] ) ) );
		}
		$order->update_meta_data( WC_Frenet_Labels_Nfe::META, $parsed['key'] );
		$order->save();
		wp_send_json_success( array( 'summary' => WC_Frenet_Labels_Nfe::summary( $parsed ) ) );
	}

	/**
	 * Last tracking event of a label.
	 *
	 * @return void
	 */
	public static function ajax_tracking() {
		self::guard();
		$service = sanitize_text_field( wp_unslash( $_POST['service'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$number  = sanitize_text_field( wp_unslash( $_POST['number'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$res     = WC_Frenet_Labels_Api::tracking( $service, $number );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		$events = (array) ( $res['TrackingEvents'] ?? array() );
		$last   = $events ? (array) end( $events ) : array();
		wp_send_json_success(
			array(
				'description' => (string) ( $last['EventDescription'] ?? __( 'No movement yet. Events appear once the carrier scans the package.', 'woo-shipping-gateway' ) ),
				'date'        => (string) ( $last['EventDateTime'] ?? '' ),
				'location'    => (string) ( $last['EventLocation'] ?? '' ),
			)
		);
	}

	/**
	 * Fresh wallet.
	 *
	 * @return void
	 */
	public static function ajax_wallet() {
		self::guard();
		$w = WC_Frenet_Labels_Service::wallet( true );
		if ( is_wp_error( $w ) ) {
			wp_send_json_error( array( 'message' => $w->get_error_message() ) );
		}
		wp_send_json_success( $w );
	}

	/* ------------------------------------------------------------------ printing */

	/**
	 * Streams one PDF with the selected labels; for a single label Frenet cannot batch, opens its own URL.
	 *
	 * @return void
	 */
	public static function print_pdf() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( self::NONCE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woo-shipping-gateway' ), 403 );
		}
		$raw = isset( $_REQUEST['ids'] ) ? wp_unslash( $_REQUEST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to int below.
		$ids = array_values( array_filter( array_map( 'absint', is_array( $raw ) ? $raw : explode( ',', (string) $raw ) ) ) );
		if ( isset( $_REQUEST['format'] ) ) {
			WC_Frenet_Labels_Api::$format = '10x15' === sanitize_text_field( wp_unslash( $_REQUEST['format'] ) ) ? '10x15' : 'A4';
		}
		if ( ! $ids ) {
			wp_die(
				esc_html__( 'Select at least one paid label to print.', 'woo-shipping-gateway' ),
				esc_html__( 'Label printing', 'woo-shipping-gateway' ),
				array(
					'back_link' => true,
					'response'  => 400,
				)
			);
		}
		$res = WC_Frenet_Labels_Service::batch_pdf( array_map( 'strval', $ids ) );
		if ( is_wp_error( $res ) && 1 === count( $ids ) ) {
			$label = WC_Frenet_Labels_Service::label( (string) $ids[0] );
			if ( ! is_wp_error( $label ) && '' !== $label['label_url'] ) {
				wp_redirect( $label['label_url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the label lives on the Frenet panel.
				exit;
			}
		}
		if ( is_wp_error( $res ) ) {
			wp_die(
				esc_html( $res->get_error_message() ),
				esc_html__( 'Label printing', 'woo-shipping-gateway' ),
				array(
					'back_link' => true,
					'response'  => 502,
				)
			);
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $res['pdf'] ) );
		header( 'Content-Disposition: inline; filename="frenet-etiquetas-' . gmdate( 'Ymd-His' ) . '.pdf"' );
		echo $res['pdf']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF.
		exit;
	}

	/**
	 * URL that prints the given shipments.
	 *
	 * @param array<int, string> $ids    Shipment ids.
	 * @param string             $format A4 or 10x15 ('' = settings).
	 * @return string
	 */
	public static function print_url( array $ids, $format = '' ) {
		$args = array(
			'action' => 'frenet_labels_print',
			'ids'    => implode( ',', array_map( 'absint', $ids ) ),
		);
		if ( '' !== $format ) {
			$args['format'] = $format;
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::NONCE );
	}

	/* ------------------------------------------------------------------ orders list and order box */

	/**
	 * Bulk action on the orders list.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public static function bulk_action( $actions ) {
		if ( WC_Frenet_Labels_Settings::ready() ) {
			$actions['frenet_labels_create'] = __( 'Create Frenet shipment', 'woo-shipping-gateway' );
		}
		return $actions;
	}

	/**
	 * Sends the selected orders to "Criar envio".
	 *
	 * @param string          $redirect Redirect URL.
	 * @param string          $action   Action.
	 * @param array<int, int> $ids      Order ids.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'frenet_labels_create' !== $action ) {
			return $redirect;
		}
		return self::url( self::SLUG . '-create', array( 'orders' => implode( ',', array_map( 'absint', (array) $ids ) ) ) );
	}

	/**
	 * Frenet box on the order screen (legacy and HPOS).
	 *
	 * @return void
	 */
	public static function meta_box() {
		// Labels need both tokens; tracking only the store token.
		if ( ! WC_Frenet_Labels_Settings::ready() && '' === WC_Frenet_Labels_Settings::store_token() ) {
			return;
		}
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box( 'frenet-labels', __( 'Frenet shipment', 'woo-shipping-gateway' ), array( __CLASS__, 'render_box' ), $screen, 'side', 'high' );
		}
	}

	/**
	 * Order box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post (legacy) or order (HPOS).
	 * @return void
	 */
	public static function render_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( WC_Frenet_Labels_Settings::ready() ) {
			self::view( 'order-box', array( 'order' => $order ) );
			return;
		}
		echo '<div class="frenet-box">';
		self::view( 'order-tracking', array( 'order' => $order ) );
		echo '</div>';
	}
}
