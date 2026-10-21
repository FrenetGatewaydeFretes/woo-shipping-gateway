<?php
/**
 * Automatic shipments: a paid order quotes itself, picks the service by the merchant's rule and buys the label,
 * without anyone opening "Criar envio" — only while the merchant keeps the explicit authorisation on, in the
 * settings ("Automação"). The work runs a minute later, out of the checkout request, through Action Scheduler
 * (WooCommerce ships it) or WP-Cron.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queue and worker of the automatic shipments.
 */
class WC_Frenet_Labels_Auto {

	const HOOK          = 'frenet_labels_auto_create';
	const META_QUEUED   = '_frenet_auto_queued';
	const META_DONE     = '_frenet_auto_done';
	const RULE_CUSTOMER = 'customer';
	const DELAY         = MINUTE_IN_SECONDS;

	/**
	 * Hooks: paid orders are queued; the queue runs later.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'schedule' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'schedule' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Whether the automation runs: labels on, tokens set, the switch on and the authorisation given.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return WC_Frenet_Labels_Settings::ready()
			&& WC_Frenet_Labels_Settings::on( 'auto_create' )
			&& WC_Frenet_Labels_Settings::on( 'auto_create_consent' );
	}

	/**
	 * Rule chosen in the settings: customer | best_price | best_time.
	 *
	 * @return string
	 */
	public static function rule() {
		$rule = WC_Frenet_Labels_Settings::get( 'auto_create_rule' );
		return in_array( $rule, array( self::RULE_CUSTOMER, WC_Frenet_Labels_Compare::BEST_PRICE, WC_Frenet_Labels_Compare::BEST_TIME ), true )
			? $rule : self::RULE_CUSTOMER;
	}

	/**
	 * The rule as people read it.
	 *
	 * @param string $rule Rule code; the configured one when empty.
	 * @return string
	 */
	public static function rule_label( $rule = '' ) {
		$rule = '' !== (string) $rule ? (string) $rule : self::rule();
		if ( WC_Frenet_Labels_Compare::BEST_TIME === $rule ) {
			return __( 'fastest', 'woo-shipping-gateway' );
		}
		if ( WC_Frenet_Labels_Compare::BEST_PRICE === $rule ) {
			return __( 'best price', 'woo-shipping-gateway' );
		}
		return __( 'the carrier the customer chose', 'woo-shipping-gateway' );
	}

	/**
	 * Whether an order is one the automation handles: paid, ships to Brazil, no live Frenet shipment yet.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function applies( WC_Order $order ) {
		if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return false;
		}
		if ( ! $order->needs_shipping_address() ) {
			return false;
		}
		$country = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
		if ( 'BR' !== $country ) {
			return false;
		}
		return ! WC_Frenet_Labels_Service::has_shipment( $order );
	}

	/**
	 * Queues the order once (payment_complete and status hooks can both fire for the same order).
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public static function schedule( $order_id ) {
		if ( ! self::enabled() ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::applies( $order ) || '' !== (string) $order->get_meta( self::META_QUEUED ) ) {
			return;
		}
		$order->update_meta_data( self::META_QUEUED, current_time( 'mysql' ) );
		$order->save();
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + self::DELAY, self::HOOK, array( 'order_id' => (int) $order->get_id() ), 'woo-shipping-gateway' );
		} else {
			wp_schedule_single_event( time() + self::DELAY, self::HOOK, array( (int) $order->get_id() ) );
		}
	}

	/**
	 * Worker: quotes, chooses and creates the shipment of one order.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public static function run( $order_id ) {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return;
		}
		if ( ! self::enabled() || ! self::applies( $order ) ) {
			// Turned off, cancelled or shipped by hand meanwhile: nothing to do, and it can be queued again later.
			$order->delete_meta_data( self::META_QUEUED );
			$order->save();
			return;
		}
		$rule = self::rule();
		$cmp  = WC_Frenet_Labels_Service::compare( $order );
		if ( is_wp_error( $cmp ) ) {
			self::failed( $order, $cmp->get_error_message(), true );
			return;
		}
		$has_nfe = WC_Frenet_Labels_Nfe::parse( (string) $order->get_meta( WC_Frenet_Labels_Nfe::META ) )['ok'];
		$code    = self::choose( $cmp['services'], (string) $cmp['chosen'], $rule, $has_nfe );
		if ( '' === $code ) {
			self::failed( $order, __( 'Every carrier available requires an NF-e key and the order has none. Add the key or create the shipment by hand.', 'woo-shipping-gateway' ), true );
			return;
		}
		$res = WC_Frenet_Labels_Service::create( $order, $code, 'here' );
		if ( is_wp_error( $res ) ) {
			// create() already kept the reason in the order's Notes.
			self::failed( $order, $res->get_error_message(), false );
			return;
		}
		$service = self::service_label( $cmp['services'], $code );
		$note    = sprintf(
			/* translators: 1: Frenet shipment id, 2: carrier and service, 3: price, 4: rule */
			__( 'Frenet shipment %1$s created automatically: %2$s, %3$s (rule: %4$s).', 'woo-shipping-gateway' ),
			(string) ( $res['shipment_id'] ?? $res['id'] ?? '' ),
			$service['name'],
			WC_Frenet_Labels_Service::money( $service['price'] ),
			self::rule_label( $rule )
		);
		if ( WC_Frenet_Labels_Settings::on( 'wallet_payment' ) && empty( $res['paid'] ) ) {
			$note .= ' ' . __( 'It was not paid: see Notes and pay it in Labels.', 'woo-shipping-gateway' );
		}
		$order = wc_get_order( $order->get_id() );
		$order->add_order_note( $note );
		$order->update_meta_data( self::META_DONE, current_time( 'mysql' ) );
		$order->save();
	}

	/**
	 * ServiceCode for the rule, among the services the order can really use; '' when there is none.
	 *
	 * Pure (unit tested). Without an NF-e key the carriers that require one are left out; when every carrier
	 * requires it there is nothing to choose and the merchant has to add the key.
	 *
	 * @param array<int, array<string, mixed>> $services Services of WC_Frenet_Labels_Service::compare() (with needs_nfe).
	 * @param string                           $chosen   ServiceCode the customer chose at checkout ('' if none).
	 * @param string                           $rule     customer | best_price | best_time.
	 * @param bool                             $has_nfe  Whether the order has a valid NF-e key.
	 * @return string
	 */
	public static function choose( array $services, $chosen, $rule, $has_nfe ) {
		$usable = $has_nfe ? $services : array_values(
			array_filter(
				$services,
				static function ( $s ) {
					return empty( $s['needs_nfe'] );
				}
			)
		);
		if ( ! $usable ) {
			return '';
		}
		if ( self::RULE_CUSTOMER === $rule && '' !== (string) $chosen && in_array( (string) $chosen, array_column( $usable, 'code' ), true ) ) {
			return (string) $chosen;
		}
		$best = WC_Frenet_Labels_Compare::best( $usable );
		$key  = WC_Frenet_Labels_Compare::BEST_TIME === $rule ? 'best_time' : 'best_price';
		return (string) ( $best[ $key ] ?? '' );
	}

	/**
	 * Name and price of a service by its code.
	 *
	 * @param array<int, array<string, mixed>> $services Services.
	 * @param string                           $code     ServiceCode.
	 * @return array{name: string, price: float}
	 */
	private static function service_label( array $services, $code ) {
		foreach ( $services as $s ) {
			if ( (string) $s['code'] === (string) $code ) {
				$carrier = trim( (string) ( $s['carrier'] ?? '' ) );
				$service = trim( (string) ( $s['name'] ?? $code ) );
				// Loggi quotes a service called "Loggi": name the carrier once.
				$name = strcasecmp( $carrier, $service ) === 0 || '' === $carrier ? $service : $carrier . ' · ' . $service;
				return array(
					'name'  => $name,
					'price' => (float) ( $s['price'] ?? 0 ),
				);
			}
		}
		return array(
			'name'  => (string) $code,
			'price' => 0.0,
		);
	}

	/**
	 * The automatic attempt did not go through: the order history says why, and Notes too when asked.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $message Reason.
	 * @param bool     $store   Keep the reason in the order's Notes (create() does it itself).
	 * @return void
	 */
	private static function failed( WC_Order $order, $message, $store ) {
		if ( $store ) {
			WC_Frenet_Labels_Service::set_issue( $order, $message );
		}
		$order = wc_get_order( $order->get_id() );
		/* translators: %s: reason */
		$order->add_order_note( sprintf( __( 'Automatic Frenet shipment not created: %s', 'woo-shipping-gateway' ), wp_strip_all_tags( (string) $message ) ) );
		$order->update_meta_data( self::META_DONE, current_time( 'mysql' ) );
		$order->save();
	}
}
