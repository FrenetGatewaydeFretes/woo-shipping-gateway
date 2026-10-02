<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shipments and labels through the Frenet WhiteLabel API.
 *
 * Two journeys, chosen in the settings and on the button:
 *  - "panel": POST /orders → the shipment waits for payment and is paid/printed in the Frenet panel.
 *  - "here":  POST /shipments → POST /shipments/checkout (wallet) → GET /shipments/{id}/label (cron polls).
 * Safety rules validated live (2026-09-30): the shipment id is stored before paying; the checkout answer is
 * not reliable, so the real status (GET /shipments/{id}) decides; DELETE on a paid label does nothing, so
 * paid labels are cancelled with POST /{id}/cancel and the status read afterwards decides.
 *
 * Order meta: _frenet_shipment_id, _frenet_shipment_status, _frenet_label_journey, _frenet_service_code,
 * _frenet_shipment_price, _frenet_tracking_code, _frenet_nfe_key.
 */
class WC_Frenet_Labels_Service {

	const POLL_HOOK = 'woocommerce_frenet_labels_poll';
	const POLL_MAX  = 10;
	const WALLET    = 'woocommerce_frenet_labels_wallet';

	/** REST route (under /wp-json/) Frenet calls when a wallet deposit changes. */
	const DEPOSIT_ROUTE = 'frenet/v1/deposit';

	const STATUS_CREATED          = 1;
	const STATUS_PENDING_PAYMENT  = 2;
	const STATUS_PAYMENT_FAILED   = 3;
	const STATUS_PAID             = 4;
	const STATUS_POSTED           = 5;
	const STATUS_CANCEL_SCHEDULED = 6;
	const STATUS_CANCELLED        = 7;
	const STATUS_DELETED          = 9;
	const STATUS_AT_DROP_OFF      = 18;
	const PAID_STATUSES           = array( 4, 5, 18 );
	const GONE_STATUSES           = array( 6, 7, 9 );
	const UNPAID_STATUSES         = array( 1, 2, 3 );

	/**
	 * ServiceCode the customer chose (woo-shipping-gateway stores the rate id as FRENET_ID = "FRENET_<code>").
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function chosen_service( WC_Order $order ) {
		foreach ( $order->get_shipping_methods() as $item ) {
			$id = (string) $item->get_meta( 'FRENET_ID' );
			if ( 0 === strpos( $id, 'FRENET_' ) ) {
				return substr( $id, 7 );
			}
		}
		return '';
	}

	/**
	 * Whether the order still has a live Frenet shipment (not cancelled/deleted).
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function has_shipment( WC_Order $order ) {
		return '' !== (string) $order->get_meta( '_frenet_shipment_id' )
			&& ! in_array( (int) $order->get_meta( '_frenet_shipment_status' ), self::GONE_STATUSES, true );
	}

	/**
	 * Every carrier for the order, with the cheapest/fastest marked and the default choice.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function compare( WC_Order $order ) {
		$items = WC_Frenet_Labels_Payload::quote_items( $order );
		if ( ! $items ) {
			return new WP_Error( 'frenet_labels_no_items', __( 'This order has no product to ship.', 'woo-shipping-gateway' ) );
		}
		$to  = WC_Frenet_Labels_Payload::recipient( $order );
		$raw = WC_Frenet_Labels_Api::quote( $to['postcode'], (float) $order->get_subtotal(), $items );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$services = WC_Frenet_Labels_Compare::normalize( $raw );
		if ( ! $services ) {
			return new WP_Error( 'frenet_labels_no_service', __( 'No carrier delivers this package to this CEP right now. Check the weight, the size and the address.', 'woo-shipping-gateway' ) );
		}
		$chosen = self::chosen_service( $order );
		return array(
			'services' => $services,
			'best'     => WC_Frenet_Labels_Compare::best( $services ),
			'chosen'   => $chosen,
			'default'  => WC_Frenet_Labels_Compare::default_choice( $services, $chosen, WC_Frenet_Labels_Settings::on( 'auto_best' ), WC_Frenet_Labels_Settings::get( 'default_service' ) ),
		);
	}

	/**
	 * Creates the Frenet shipment of the order with the chosen service.
	 *
	 * @param WC_Order $order        Order.
	 * @param string   $service_code Chosen ServiceCode.
	 * @param string   $journey      "here" or "panel".
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( WC_Order $order, $service_code, $journey ) {
		if ( self::has_shipment( $order ) ) {
			return new WP_Error( 'frenet_labels_exists', __( 'This order already has a Frenet shipment. Cancel it first.', 'woo-shipping-gateway' ) );
		}
		if ( ! WC_Frenet_Labels_Payload::sender_ready() ) {
			return new WP_Error( 'frenet_labels_sender', __( 'Fill in the store address (address, city and postcode) in WooCommerce > Settings > General: Frenet requires the sender. Nothing was charged.', 'woo-shipping-gateway' ) );
		}
		// Re-quote now: Frenet needs the current carrier, price and delivery time.
		$cmp = self::compare( $order );
		if ( is_wp_error( $cmp ) ) {
			return $cmp;
		}
		$service = null;
		foreach ( $cmp['services'] as $s ) {
			if ( $s['code'] === (string) $service_code ) {
				$service = $s;
			}
		}
		if ( ! $service ) {
			return new WP_Error( 'frenet_labels_service_gone', __( 'This carrier service is no longer available for this order. Calculate the freight again and choose another one.', 'woo-shipping-gateway' ) );
		}
		$journey = 'panel' === $journey ? 'panel' : 'here';
		// 1) Create WITHOUT charging; store the id at once so a later failure never loses a shipment.
		$res = WC_Frenet_Labels_Api::whitelabel( 'POST', 'panel' === $journey ? '/orders' : '/shipments', array( WC_Frenet_Labels_Payload::build( $order, $service ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$item   = (array) ( WC_Frenet_Labels_Api::field( (array) ( WC_Frenet_Labels_Api::field( $res, 'items' ) ?? array() ), 0 ) ?? array() );
		$errors = (array) ( WC_Frenet_Labels_Api::field( $item, 'errors' ) ?? array() );
		if ( $errors ) {
			$message = WC_Frenet_Labels_Api::clean_error( (string) ( WC_Frenet_Labels_Api::field( (array) $errors[0], 'message' ) ?? '' ) );
			if ( WC_Frenet_Labels_Nfe::is_invoice_error( $message ) ) {
				$message = $order->get_meta( WC_Frenet_Labels_Nfe::META )
					? __( 'Frenet did not accept the NF-e key of this order. Check that it is the invoice of this order. Nothing was charged.', 'woo-shipping-gateway' )
					: __( 'This carrier requires an invoice. Paste the NF-e key of the order and try again, or choose another carrier. Nothing was charged.', 'woo-shipping-gateway' );
			}
			return new WP_Error( 'frenet_labels_refused', $message );
		}
		$id = (string) ( WC_Frenet_Labels_Api::field( $item, 'shipmentId' ) ?? '' );
		if ( '' === $id ) {
			return new WP_Error( 'frenet_labels_no_id', __( 'Frenet did not return a shipment. Nothing was charged.', 'woo-shipping-gateway' ) );
		}
		$order->update_meta_data( '_frenet_shipment_id', $id );
		$order->update_meta_data( '_frenet_shipment_status', (string) self::STATUS_PENDING_PAYMENT );
		$order->update_meta_data( '_frenet_label_journey', $journey );
		$order->update_meta_data( '_frenet_service_code', $service['code'] );
		$order->update_meta_data( '_frenet_shipment_price', (string) $service['price'] );
		$order->add_order_note(
			'panel' === $journey
				/* translators: 1: shipment id, 2: carrier service */
				? sprintf( __( 'Frenet shipment %1$s (%2$s) sent to the Frenet panel, waiting for payment there.', 'woo-shipping-gateway' ), $id, $service['name'] )
				/* translators: 1: shipment id, 2: carrier service */
				: sprintf( __( 'Frenet shipment %1$s (%2$s) created, not paid yet.', 'woo-shipping-gateway' ), $id, $service['name'] )
		);
		$order->save();
		$out = array(
			'shipment_id' => $id,
			'status'      => self::STATUS_PENDING_PAYMENT,
			'price'       => $service['price'],
			'service'     => $service['name'],
			'journey'     => $journey,
			'paid'        => false,
		);
		if ( 'here' !== $journey || ! WC_Frenet_Labels_Settings::on( 'wallet_payment' ) ) {
			return $out;
		}
		// 2) Pay with the wallet.
		$pay              = self::pay( array( $id ) );
		$out['status']    = $pay['statuses'][ $id ] ?? self::STATUS_PENDING_PAYMENT;
		$out['paid']      = in_array( $out['status'], self::PAID_STATUSES, true );
		$out['message']   = $pay['message'];
		$out['label_url'] = '';
		return $out;
	}

	/**
	 * Pays shipments with the wallet (the "cart"). The real status of each one decides.
	 *
	 * @param array<int, string> $ids Shipment ids.
	 * @return array{statuses: array<string, int>, total: float, message: string, unknown: bool}
	 */
	public static function pay( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'strval', $ids ), 'ctype_digit' ) ) );
		$res = WC_Frenet_Labels_Api::whitelabel( 'POST', '/shipments/checkout', array_map( 'intval', $ids ) );
		delete_transient( self::WALLET );
		$unknown  = is_wp_error( $res );
		$message  = '';
		$statuses = array();
		if ( ! $unknown ) {
			$errors = (array) ( WC_Frenet_Labels_Api::field( $res, 'errors' ) ?? array() );
			if ( $errors && is_array( $errors[0] ) ) {
				$message = WC_Frenet_Labels_Api::clean_error( (string) ( WC_Frenet_Labels_Api::field( $errors[0], 'message' ) ?? '' ) );
			}
		}
		foreach ( $ids as $id ) {
			$status = self::real_status( $id );
			if ( null === $status ) {
				$status = ( ! $unknown && 1 === (int) ( WC_Frenet_Labels_Api::field( $res, 'status' ) ?? 0 ) ) ? self::STATUS_PAID : self::STATUS_PENDING_PAYMENT;
			}
			$statuses[ $id ] = $status;
			$order           = self::order_for( $id );
			if ( ! $order ) {
				continue;
			}
			$order->update_meta_data( '_frenet_shipment_status', (string) $status );
			if ( in_array( $status, self::PAID_STATUSES, true ) ) {
				/* translators: 1: shipment id, 2: amount */
				$order->add_order_note( sprintf( __( 'Frenet label %1$s paid (%2$s).', 'woo-shipping-gateway' ), $id, self::money( (float) $order->get_meta( '_frenet_shipment_price' ) ) ) );
				$order->save();
				if ( ! self::fetch_label( $order ) ) {
					self::schedule_poll( $order->get_id(), 1 );
				}
			} elseif ( $unknown ) {
				// Unknown outcome (timeout): the poll reads the real status and fetches the label if Frenet charged.
				$order->save();
				self::schedule_poll( $order->get_id(), 1 );
			} else {
				$order->save();
			}
		}
		if ( $unknown ) {
			/* translators: %s: error */
			$message = sprintf( __( 'The payment did not answer (%s). The store checks again every minute; do not pay twice.', 'woo-shipping-gateway' ), $res->get_error_message() );
		} elseif ( '' === $message && array_diff( $statuses, self::PAID_STATUSES ) ) {
			$message = __( 'Not enough balance in the Frenet wallet. The shipments stay unpaid: add balance and pay them in Labels.', 'woo-shipping-gateway' );
		}
		$total = 0.0;
		foreach ( $statuses as $id => $status ) {
			$order  = in_array( $status, self::PAID_STATUSES, true ) ? self::order_for( (string) $id ) : null;
			$total += $order ? (float) $order->get_meta( '_frenet_shipment_price' ) : 0.0;
		}
		return array(
			'statuses' => $statuses,
			'total'    => $total,
			'message'  => $message,
			'unknown'  => $unknown,
		);
	}

	/**
	 * GET /shipments/{id}/label.
	 *
	 * @param string $id Shipment id.
	 * @return array{status: int|null, label_url: string, declaration_url: string, tracking_url: string, tracking: string}|WP_Error
	 */
	public static function label( $id ) {
		$res = WC_Frenet_Labels_Api::whitelabel( 'GET', '/shipments/' . rawurlencode( (string) $id ) . '/label' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$status   = WC_Frenet_Labels_Api::field( $res, 'shipmentStatus' );
		$tracking = (string) ( WC_Frenet_Labels_Api::field( $res, 'trackingUrl' ) ?? '' );
		return array(
			'status'          => is_numeric( $status ) ? (int) $status : null,
			'label_url'       => (string) ( WC_Frenet_Labels_Api::field( $res, 'labelUrl' ) ?? '' ),
			'declaration_url' => (string) ( WC_Frenet_Labels_Api::field( $res, 'declarationUrl' ) ?? '' ),
			'tracking_url'    => $tracking,
			'tracking'        => preg_match( '#/([A-Za-z0-9]{8,})/?$#', $tracking, $m ) ? $m[1] : '',
		);
	}

	/**
	 * Reads the label of an order; stores status and tracking code (label URLs are NOT stored: they carry
	 * the store token and expire, so they are fetched again when printing).
	 *
	 * @param WC_Order $order Order.
	 * @return bool Whether the label is ready.
	 */
	public static function fetch_label( WC_Order $order ) {
		$id = (string) $order->get_meta( '_frenet_shipment_id' );
		if ( '' === $id ) {
			return false;
		}
		$label = self::label( $id );
		if ( is_wp_error( $label ) ) {
			return false;
		}
		if ( null !== $label['status'] ) {
			$order->update_meta_data( '_frenet_shipment_status', (string) $label['status'] );
		}
		if ( '' !== $label['tracking'] ) {
			$order->update_meta_data( '_frenet_tracking_code', $label['tracking'] );
		}
		$ready = '' !== $label['label_url'];
		if ( $ready && ! $order->get_meta( '_frenet_label_ready' ) ) {
			$order->update_meta_data( '_frenet_label_ready', '1' );
			$order->add_order_note( __( 'Frenet label ready to print.', 'woo-shipping-gateway' ) );
		}
		$order->save();
		return $ready;
	}

	/**
	 * Schedules a label check in one minute.
	 *
	 * @param int $order_id Order id.
	 * @param int $attempt  1-based attempt.
	 * @return void
	 */
	public static function schedule_poll( $order_id, $attempt ) {
		$args = array( (int) $order_id, (int) $attempt );
		if ( $attempt <= self::POLL_MAX && ! wp_next_scheduled( self::POLL_HOOK, $args ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::POLL_HOOK, $args );
		}
	}

	/**
	 * Removes the pending checks of one order (they carry [order_id, attempt]).
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public static function unschedule_poll( $order_id ) {
		for ( $attempt = 1; $attempt <= self::POLL_MAX; $attempt++ ) {
			wp_clear_scheduled_hook( self::POLL_HOOK, array( (int) $order_id, $attempt ) );
		}
	}

	/**
	 * Cron: retries the label; after POLL_MAX attempts leaves a note.
	 *
	 * @param int $order_id Order id.
	 * @param int $attempt  Attempt.
	 * @return void
	 */
	public static function poll( $order_id, $attempt ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! self::has_shipment( $order ) || $order->get_meta( '_frenet_label_ready' ) ) {
			return;
		}
		if ( self::fetch_label( $order ) ) {
			return;
		}
		if ( $attempt >= self::POLL_MAX ) {
			$order->add_order_note( __( 'Frenet has not released the label after 10 minutes. Check the shipment in the Frenet panel.', 'woo-shipping-gateway' ) );
			$order->save();
			return;
		}
		self::schedule_poll( $order_id, $attempt + 1 );
	}

	/**
	 * Real status at Frenet (GET /shipments/{id}); null when Frenet does not answer.
	 *
	 * @param string $id Shipment id.
	 * @return int|null
	 */
	public static function real_status( $id ) {
		$res = WC_Frenet_Labels_Api::whitelabel( 'GET', '/shipments/' . rawurlencode( (string) $id ) );
		if ( is_wp_error( $res ) ) {
			return null;
		}
		$status = WC_Frenet_Labels_Api::field( $res, 'shipmentStatus' );
		return is_numeric( $status ) ? (int) $status : null;
	}

	/**
	 * Cancels a shipment: paid → POST /cancel (Frenet refunds the wallet), unpaid → DELETE.
	 * Frenet may answer an error and still schedule the cancellation, so the status read afterwards decides.
	 *
	 * @param string $id Shipment id.
	 * @return array{status: int, refunded: bool}|WP_Error
	 */
	public static function cancel( $id ) {
		$id     = (string) $id;
		$order  = self::order_for( $id );
		$status = self::real_status( $id );
		if ( null === $status ) {
			$status = $order ? (int) $order->get_meta( '_frenet_shipment_status' ) : 0;
		}
		$paid = false;
		if ( ! in_array( $status, self::GONE_STATUSES, true ) ) {
			$paid = ! in_array( $status, self::UNPAID_STATUSES, true );
			$paid
				? WC_Frenet_Labels_Api::whitelabel( 'POST', '/shipments/' . rawurlencode( $id ) . '/cancel' )
				: WC_Frenet_Labels_Api::whitelabel( 'DELETE', '/shipments/' . rawurlencode( $id ) );
			$after = self::real_status( $id );
			if ( null !== $after && ! in_array( $after, self::GONE_STATUSES, true ) ) {
				/* translators: 1: shipment id, 2: status name */
				return new WP_Error( 'frenet_labels_cancel', sprintf( __( 'Frenet did not cancel shipment %1$s (%2$s). Cancel it in the Frenet panel.', 'woo-shipping-gateway' ), $id, self::status_label( $after ) ) );
			}
			$status = null !== $after ? $after : ( $paid ? self::STATUS_CANCEL_SCHEDULED : self::STATUS_DELETED );
		}
		delete_transient( self::WALLET );
		if ( $order ) {
			$order->update_meta_data( '_frenet_shipment_status', (string) $status );
			$order->delete_meta_data( '_frenet_label_ready' );
			self::unschedule_poll( $order->get_id() );
			$order->add_order_note(
				$paid
					/* translators: %s: shipment id */
					? sprintf( __( 'Frenet label %s cancelled. Frenet returns the amount to the wallet (usually overnight).', 'woo-shipping-gateway' ), $id )
					/* translators: %s: shipment id */
					: sprintf( __( 'Unpaid Frenet shipment %s deleted.', 'woo-shipping-gateway' ), $id )
			);
			$order->save();
		}
		return array(
			'status'   => $status,
			'refunded' => $paid,
		);
	}

	/**
	 * Mercado Pago checkout of a wallet deposit (POST /wallet/deposit → PreferenceId).
	 * Frenet credits the wallet when Mercado Pago confirms; NotificationUrl only tells this store to refresh the balance.
	 *
	 * @param float  $value   Amount in BRL.
	 * @param string $back    Admin URL Mercado Pago returns to (gets deposit=success|failure|pending).
	 * @param string $notify  URL Frenet calls when the deposit changes.
	 * @return string|WP_Error Checkout URL.
	 */
	public static function deposit( $value, $back, $notify ) {
		$value = round( (float) $value, 2 );
		if ( $value < 1 ) {
			return new WP_Error( 'frenet_labels_deposit_value', __( 'Type an amount of at least R$ 1,00.', 'woo-shipping-gateway' ) );
		}
		$res = WC_Frenet_Labels_Api::whitelabel(
			'POST',
			'/wallet/deposit',
			array(
				'Value'           => $value,
				'NotificationUrl' => $notify,
				'CallbackUrls'    => array(
					'SuccessUrl' => add_query_arg( 'deposit', 'success', $back ),
					'FailureUrl' => add_query_arg( 'deposit', 'failure', $back ),
					'PendingUrl' => add_query_arg( 'deposit', 'pending', $back ),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$preference = (string) ( WC_Frenet_Labels_Api::field( $res, 'preferenceId' ) ?? '' );
		if ( '' === $preference ) {
			return new WP_Error( 'frenet_labels_deposit', __( 'Frenet did not start the payment. Nothing was charged; try again or add balance in the Frenet panel.', 'woo-shipping-gateway' ) );
		}
		delete_transient( self::WALLET );
		$init_point = (string) ( WC_Frenet_Labels_Api::field( $res, 'initPoint' ) ?? '' );
		return self::allowed_checkout( $init_point, WC_Frenet_Labels_Settings::simulated() ) ? $init_point : self::checkout_url( $preference );
	}

	/**
	 * Whether a payment link Frenet returned can be opened: Mercado Pago over https, or anything in simulator mode.
	 * Pure: unit tested.
	 *
	 * @param string $url       Link.
	 * @param bool   $simulated Simulator mode.
	 * @return bool
	 */
	public static function allowed_checkout( $url, $simulated ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		return $simulated || ( 0 === strpos( $url, 'https://' ) && (bool) preg_match( '/(^|\.)mercadopago\.com(\.[a-z]{2})?$/', $host ) );
	}

	/**
	 * Mercado Pago Checkout Pro URL of a preference. Pure: unit tested.
	 *
	 * @param string $preference PreferenceId.
	 * @return string
	 */
	public static function checkout_url( $preference ) {
		return 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=' . rawurlencode( (string) $preference );
	}

	/**
	 * Wallet (GET /wallet), cached five minutes.
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array{balance: float, labels: int}|WP_Error
	 */
	public static function wallet( $fresh = false ) {
		$cached = get_transient( self::WALLET );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached;
		}
		$res = WC_Frenet_Labels_Api::whitelabel( 'GET', '/wallet' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$wallet = array(
			'balance' => (float) ( WC_Frenet_Labels_Api::field( $res, 'balance' ) ?? 0 ),
			'labels'  => (int) ( WC_Frenet_Labels_Api::field( $res, 'labelLimit' ) ?? 0 ),
		);
		set_transient( self::WALLET, $wallet, 5 * MINUTE_IN_SECONDS );
		return $wallet;
	}

	/**
	 * One page of the account's shipments (page starts at 0).
	 *
	 * @param int $page     Page.
	 * @param int $per_page Rows.
	 * @return array{total: int, shipments: array<int, array<string, mixed>>}|WP_Error
	 */
	public static function shipments( $page, $per_page ) {
		$res = WC_Frenet_Labels_Api::whitelabel( 'GET', '/shipments?page=' . max( 0, (int) $page ) . '&per_page=' . max( 1, (int) $per_page ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'total'     => (int) ( WC_Frenet_Labels_Api::field( $res, 'total' ) ?? 0 ),
			'shipments' => array_values( (array) ( WC_Frenet_Labels_Api::field( $res, 'shipments' ) ?? array() ) ),
		);
	}

	/**
	 * One PDF with every label (POST /shipments/batch/label/generate, "document" in base64).
	 *
	 * @param array<int, string> $ids Shipment ids.
	 * @return array{pdf: string, failed: array<int, string>}|WP_Error
	 */
	public static function batch_pdf( array $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'frenet_labels_none', __( 'Select at least one paid label.', 'woo-shipping-gateway' ) );
		}
		$res = WC_Frenet_Labels_Api::whitelabel( 'POST', '/shipments/batch/label/generate', $ids );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$pdf    = base64_decode( (string) ( WC_Frenet_Labels_Api::field( $res, 'document' ) ?? '' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the Frenet API returns the PDF in base64.
		$failed = array();
		foreach ( (array) ( WC_Frenet_Labels_Api::field( $res, 'items' ) ?? array() ) as $item ) {
			if ( is_array( $item ) && ! empty( WC_Frenet_Labels_Api::field( $item, 'errors' ) ) ) {
				$failed[] = (string) WC_Frenet_Labels_Api::field( $item, 'shipmentId' );
			}
		}
		if ( ! is_string( $pdf ) || 0 !== strpos( $pdf, '%PDF' ) ) {
			return new WP_Error( 'frenet_labels_pdf', __( 'Frenet did not generate the PDF. Only paid labels can be printed; try again in a minute.', 'woo-shipping-gateway' ) );
		}
		return array(
			'pdf'    => $pdf,
			'failed' => array_values( array_unique( $failed ) ), // Frenet repeats the item of a label it cannot print.
		);
	}

	/**
	 * Local order of a Frenet shipment, or null.
	 *
	 * @param string $id Shipment id.
	 * @return WC_Order|null
	 */
	public static function order_for( $id ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_frenet_shipment_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed lookup by shipment id.
				'meta_value' => (string) $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
			)
		);
		return $orders && $orders[0] instanceof WC_Order ? $orders[0] : null;
	}

	/**
	 * Status name for the shopkeeper.
	 *
	 * @param int $status ShipmentStatus.
	 * @return string
	 */
	public static function status_label( $status ) {
		$names = self::status_names();
		/* translators: %d: status number */
		return $names[ (int) $status ] ?? sprintf( __( 'Status %d', 'woo-shipping-gateway' ), (int) $status );
	}

	/**
	 * All status names.
	 *
	 * @return array<int, string>
	 */
	public static function status_names() {
		return array(
			self::STATUS_CREATED          => __( 'Created', 'woo-shipping-gateway' ),
			self::STATUS_PENDING_PAYMENT  => __( 'Awaiting payment', 'woo-shipping-gateway' ),
			self::STATUS_PAYMENT_FAILED   => __( 'Payment not completed', 'woo-shipping-gateway' ),
			self::STATUS_PAID             => __( 'Paid', 'woo-shipping-gateway' ),
			self::STATUS_POSTED           => __( 'Posted', 'woo-shipping-gateway' ),
			self::STATUS_CANCEL_SCHEDULED => __( 'Cancellation scheduled', 'woo-shipping-gateway' ),
			self::STATUS_CANCELLED        => __( 'Cancelled', 'woo-shipping-gateway' ),
			self::STATUS_DELETED          => __( 'Deleted', 'woo-shipping-gateway' ),
			self::STATUS_AT_DROP_OFF      => __( 'Delivered at the drop-off point', 'woo-shipping-gateway' ),
		);
	}

	/**
	 * Pill tone: ok, wait, bad, off.
	 *
	 * @param int $status ShipmentStatus.
	 * @return string
	 */
	public static function status_tone( $status ) {
		$status = (int) $status;
		if ( in_array( $status, self::PAID_STATUSES, true ) ) {
			return 'ok';
		}
		if ( self::STATUS_PAYMENT_FAILED === $status ) {
			return 'bad';
		}
		return in_array( $status, self::GONE_STATUSES, true ) ? 'off' : 'wait';
	}

	/**
	 * "R$ 1.234,50".
	 *
	 * @param float $value Amount.
	 * @return string
	 */
	public static function money( $value ) {
		return 'R$ ' . number_format( (float) $value, 2, ',', '.' );
	}
}
