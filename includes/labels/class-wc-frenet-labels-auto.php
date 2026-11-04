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
	/** Product meta "Automatic shipment with": '', best_price, best_time or carrier:<CODE>. */
	const PRODUCT_META   = '_frenet_auto_carrier';
	const CARRIER_PREFIX = 'carrier:';
	/** What happens when the carrier the products ask for does not quote (auto_carrier_fallback). */
	const FALLBACK_BEST_PRICE = 'best_price';
	const FALLBACK_SKIP       = 'skip';
	/** Why choose_with_products() picked (or not) a service. */
	const WHY_CUSTOMER        = 'customer';
	const WHY_PRODUCT_CARRIER = 'product_carrier';
	const WHY_PRODUCT_RULE    = 'product_rule';
	const WHY_GENERAL         = 'general';
	const WHY_FALLBACK        = 'fallback';
	const WHY_CONFLICT        = 'conflict';
	const WHY_UNAVAILABLE     = 'unavailable';

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
	/**
	 * Rules the merchant can pick: "the carrier the customer chose" only exists while Frenet quotes at checkout.
	 *
	 * @return array<string, string> rule => label.
	 */
	public static function rule_options() {
		$out = array();
		if ( WC_Frenet_Labels_Settings::quotes_at_checkout() ) {
			$out[ self::RULE_CUSTOMER ] = __( 'The carrier the customer chose at checkout (best price when it is not available)', 'woo-shipping-gateway' );
		}
		$out[ WC_Frenet_Labels_Compare::BEST_PRICE ] = __( 'Best price', 'woo-shipping-gateway' );
		$out[ WC_Frenet_Labels_Compare::BEST_TIME ]  = __( 'Fastest', 'woo-shipping-gateway' );
		return $out;
	}

	/**
	 * Options of the product field "Automatic shipment with": the general rule, a rule, or always one carrier.
	 *
	 * @return array<string, string> value => label.
	 */
	public static function product_options() {
		$out = array(
			''                                   => __( 'The general rule', 'woo-shipping-gateway' ),
			WC_Frenet_Labels_Compare::BEST_PRICE => __( 'Best price', 'woo-shipping-gateway' ),
			WC_Frenet_Labels_Compare::BEST_TIME  => __( 'Fastest', 'woo-shipping-gateway' ),
		);
		foreach ( WC_Frenet_Labels_Carriers::LABELS as $code => $name ) {
			/* translators: %s: carrier name */
			$out[ self::CARRIER_PREFIX . $code ] = sprintf( __( 'Always %s', 'woo-shipping-gateway' ), $name );
		}
		return $out;
	}

	/**
	 * Whether a product value can be saved.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function valid_product_value( $value ) {
		return array_key_exists( (string) $value, self::product_options() );
	}

	/**
	 * Short human form of a product value ("Loggi", "best price").
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function product_label( $value ) {
		$value = (string) $value;
		if ( 0 === strpos( $value, self::CARRIER_PREFIX ) ) {
			return WC_Frenet_Labels_Carriers::label( substr( $value, strlen( self::CARRIER_PREFIX ) ) );
		}
		if ( WC_Frenet_Labels_Compare::BEST_TIME === $value ) {
			return __( 'fastest', 'woo-shipping-gateway' );
		}
		if ( WC_Frenet_Labels_Compare::BEST_PRICE === $value ) {
			return __( 'best price', 'woo-shipping-gateway' );
		}
		return __( 'the general rule', 'woo-shipping-gateway' );
	}

	/**
	 * Product name => value, for the products of the order that ask something (a variation answers with its own
	 * value, or its parent's).
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, string>
	 */
	public static function product_rules( WC_Order $order ) {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || $product->is_virtual() ) {
				continue;
			}
			$value = (string) $product->get_meta( self::PRODUCT_META );
			if ( '' === $value && $product->get_parent_id() ) {
				$parent = wc_get_product( $product->get_parent_id() );
				$value  = $parent ? (string) $parent->get_meta( self::PRODUCT_META ) : '';
			}
			if ( '' !== $value && self::valid_product_value( $value ) ) {
				$out[ $item->get_name() ] = $value;
			}
		}
		return $out;
	}

	/**
	 * "Loggi (Produto A), best price (Produto B)".
	 *
	 * @param array<string, string> $asks product_rules() result.
	 * @return string
	 */
	public static function product_summary( array $asks ) {
		$parts = array();
		foreach ( $asks as $name => $value ) {
			$parts[] = self::product_label( $value ) . ' (' . $name . ')';
		}
		return implode( ', ', $parts );
	}

	/**
	 * What the products ask: one carrier when every product that names one names the same; a rule (best price
	 * wins over fastest) when no product names a carrier; a conflict when two products name different carriers.
	 * Pure (unit tested).
	 *
	 * @param string[] $values Product values.
	 * @return array{carrier: string, rule: string, conflict: bool}
	 */
	public static function product_rule( array $values ) {
		$carriers = array();
		$rules    = array();
		foreach ( $values as $value ) {
			$value = trim( (string) $value );
			if ( 0 === strpos( $value, self::CARRIER_PREFIX ) ) {
				$carriers[ strtoupper( substr( $value, strlen( self::CARRIER_PREFIX ) ) ) ] = true;
			} elseif ( in_array( $value, array( WC_Frenet_Labels_Compare::BEST_PRICE, WC_Frenet_Labels_Compare::BEST_TIME ), true ) ) {
				$rules[ $value ] = true;
			}
		}
		if ( count( $carriers ) > 1 ) {
			return array(
				'carrier'  => '',
				'rule'     => '',
				'conflict' => true,
			);
		}
		if ( $carriers ) {
			return array(
				'carrier'  => (string) array_key_first( $carriers ),
				'rule'     => '',
				'conflict' => false,
			);
		}
		$rule = isset( $rules[ WC_Frenet_Labels_Compare::BEST_PRICE ] ) ? WC_Frenet_Labels_Compare::BEST_PRICE : (string) array_key_first( $rules );
		return array(
			'carrier'  => '',
			'rule'     => $rule,
			'conflict' => false,
		);
	}

	/**
	 * Service to buy once the products have had their say. Order: 1) the service the customer paid for, when the
	 * rule is "customer" and Frenet still offers it; 2) what the products ask; 3) the general rule. A carrier the
	 * products ask for that does not quote follows $fallback. Pure (unit tested).
	 *
	 * @param array    $services compare() services (with needs_nfe).
	 * @param string   $chosen   Service the customer chose ('' if not Frenet).
	 * @param string   $rule     General rule.
	 * @param bool     $has_nfe  Whether the order has a valid NF-e key.
	 * @param string[] $values   Product values.
	 * @param string   $fallback FALLBACK_BEST_PRICE | FALLBACK_SKIP.
	 * @return array{code: string, why: string, carrier: string}
	 */
	public static function choose_with_products( array $services, $chosen, $rule, $has_nfe, array $values, $fallback ) {
		$usable = self::usable( $services, $has_nfe );
		if ( ! $usable ) {
			return array(
				'code'    => '',
				'why'     => self::WHY_GENERAL,
				'carrier' => '',
			);
		}
		if ( self::RULE_CUSTOMER === $rule && '' !== (string) $chosen && in_array( (string) $chosen, array_column( $usable, 'code' ), true ) ) {
			return array(
				'code'    => (string) $chosen,
				'why'     => self::WHY_CUSTOMER,
				'carrier' => '',
			);
		}
		$ask = self::product_rule( $values );
		if ( $ask['conflict'] ) {
			return array(
				'code'    => self::choose( $services, $chosen, $rule, $has_nfe ),
				'why'     => self::WHY_CONFLICT,
				'carrier' => '',
			);
		}
		if ( '' !== $ask['carrier'] ) {
			$own = array_values(
				array_filter(
					$usable,
					static function ( $s ) use ( $ask ) {
						return strtoupper( (string) ( $s['carrier_code'] ?? '' ) ) === $ask['carrier'];
					}
				)
			);
			if ( $own ) {
				$best = WC_Frenet_Labels_Compare::best( $own );
				return array(
					'code'    => (string) ( $best['best_price'] ?? '' ),
					'why'     => self::WHY_PRODUCT_CARRIER,
					'carrier' => $ask['carrier'],
				);
			}
			if ( self::FALLBACK_SKIP === $fallback ) {
				return array(
					'code'    => '',
					'why'     => self::WHY_UNAVAILABLE,
					'carrier' => $ask['carrier'],
				);
			}
			return array(
				'code'    => self::choose( $services, '', WC_Frenet_Labels_Compare::BEST_PRICE, $has_nfe ),
				'why'     => self::WHY_FALLBACK,
				'carrier' => $ask['carrier'],
			);
		}
		if ( '' !== $ask['rule'] ) {
			return array(
				'code'    => self::choose( $services, '', $ask['rule'], $has_nfe ),
				'why'     => self::WHY_PRODUCT_RULE,
				'carrier' => '',
			);
		}
		return array(
			'code'    => self::choose( $services, $chosen, $rule, $has_nfe ),
			'why'     => self::WHY_GENERAL,
			'carrier' => '',
		);
	}

	/**
	 * Order note that explains why the products decided; '' for the customer or the general rule.
	 *
	 * @param array                 $pick choose_with_products() result.
	 * @param array<string, string> $asks product_rules() result.
	 * @return string
	 */
	public static function product_note( array $pick, array $asks ) {
		$summary = self::product_summary( $asks );
		switch ( $pick['why'] ) {
			case self::WHY_PRODUCT_CARRIER:
				/* translators: %s: products and what they ask */
				return sprintf( __( 'Carrier chosen by the products: %s.', 'woo-shipping-gateway' ), $summary );
			case self::WHY_PRODUCT_RULE:
				/* translators: %s: products and what they ask */
				return sprintf( __( 'Service chosen by the rule of the products: %s.', 'woo-shipping-gateway' ), $summary );
			case self::WHY_FALLBACK:
				/* translators: 1: carrier, 2: products and what they ask */
				return sprintf( __( '%1$s did not quote for this order: the best price was used instead (the products asked for %2$s).', 'woo-shipping-gateway' ), WC_Frenet_Labels_Carriers::label( $pick['carrier'] ), $summary );
			case self::WHY_CONFLICT:
				/* translators: %s: products and what they ask */
				return sprintf( __( 'The products ask for different carriers (%s): the general rule was used.', 'woo-shipping-gateway' ), $summary );
		}
		return '';
	}

	/**
	 * Services the order can really use: without an NF-e key, the carriers that require one are left out.
	 *
	 * @param array $services Services.
	 * @param bool  $has_nfe  Whether the order has a valid NF-e key.
	 * @return array
	 */
	private static function usable( array $services, $has_nfe ) {
		return $has_nfe ? $services : array_values(
			array_filter(
				$services,
				static function ( $s ) {
					return empty( $s['needs_nfe'] );
				}
			)
		);
	}

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
		$asks    = self::product_rules( $order );
		$pick    = self::choose_with_products( $cmp['services'], (string) $cmp['chosen'], $rule, $has_nfe, array_values( $asks ), WC_Frenet_Labels_Settings::get( 'auto_carrier_fallback' ) );
		$code    = $pick['code'];
		if ( '' === $code ) {
			$reason = self::WHY_UNAVAILABLE === $pick['why']
				/* translators: 1: carrier, 2: products and what they ask */
				? sprintf( __( '%1$s did not quote for this order and the products ask for it (%2$s).', 'woo-shipping-gateway' ), WC_Frenet_Labels_Carriers::label( $pick['carrier'] ), self::product_summary( $asks ) )
				: __( 'Every carrier available requires an NF-e key and the order has none. Add the key or create the shipment by hand.', 'woo-shipping-gateway' );
			self::failed( $order, $reason, true );
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
			self::decided_by( $pick['why'], $rule )
		);
		if ( WC_Frenet_Labels_Settings::on( 'wallet_payment' ) && empty( $res['paid'] ) ) {
			$note .= ' ' . __( 'It was not paid: see Notes and pay it in Labels.', 'woo-shipping-gateway' );
		}
		$order = wc_get_order( $order->get_id() );
		$order->add_order_note( $note );
		$why = self::product_note( $pick, $asks );
		if ( '' !== $why ) {
			$order->add_order_note( $why );
		}
		$order->update_meta_data( self::META_DONE, current_time( 'mysql' ) );
		$order->save();
	}

	/**
	 * Who decided, for the "(rule: …)" of the success note.
	 *
	 * @param string $why  choose_with_products() reason.
	 * @param string $rule General rule.
	 * @return string
	 */
	private static function decided_by( $why, $rule ) {
		if ( self::WHY_CUSTOMER === $why ) {
			return self::rule_label( self::RULE_CUSTOMER );
		}
		if ( in_array( $why, array( self::WHY_PRODUCT_CARRIER, self::WHY_PRODUCT_RULE ), true ) ) {
			return __( 'the products', 'woo-shipping-gateway' );
		}
		return self::rule_label( $rule );
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
