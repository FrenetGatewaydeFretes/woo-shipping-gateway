<?php
defined( 'ABSPATH' ) || exit;

/**
 * Builds the Frenet shipment body (same body for POST /shipments and POST /orders).
 *
 * Rules validated live (2026-09-30): Volumes is ONE object, not a list; Order.Created is ISO 8601 UTC
 * with milliseconds; Quotation needs Carrier, CarrierCode, ShippingPrice and DeliveryTime (else HTTP 500);
 * Order.From needs a full address even with UseFrenetRegistration; To.Address.AddressNumber is required
 * ("Numero do destinatário inválido", 2026-10-02).
 */
class WC_Frenet_Labels_Payload {

	/**
	 * Store (sender) data from WooCommerce > Settings > General.
	 *
	 * @return array<string, string>
	 */
	public static function sender() {
		$country = explode( ':', (string) get_option( 'woocommerce_default_country', 'BR' ) );
		return array(
			'name'         => (string) get_bloginfo( 'name' ),
			'address_1'    => (string) get_option( 'woocommerce_store_address' ),
			'number'       => '',
			'address_2'    => '',
			'neighborhood' => (string) get_option( 'woocommerce_store_address_2' ),
			'city'         => (string) get_option( 'woocommerce_store_city' ),
			'state'        => isset( $country[1] ) ? (string) $country[1] : '',
			'postcode'     => (string) get_option( 'woocommerce_store_postcode' ),
			'document'     => WC_Frenet_Labels_Settings::get( 'sender_document' ),
		);
	}

	/**
	 * Whether the store address is complete enough for Frenet.
	 *
	 * @return bool
	 */
	public static function sender_ready() {
		$s = self::sender();
		return '' !== trim( $s['address_1'] ) && '' !== trim( $s['city'] ) && '' !== preg_replace( '/\D/', '', $s['postcode'] );
	}

	/**
	 * Recipient: shipping address, or billing when there is none. Brazilian fields (CPF/CNPJ, number, neighborhood)
	 * come from Brazilian Market ("_billing_cpf"...) or from block-checkout additional fields of any plugin
	 * ("_wc_other/br-checkout-fields/billing_cpf", "_wc_shipping/<namespace>/billing_number"...).
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, string>
	 */
	public static function recipient( WC_Order $order ) {
		$has_shipping = (bool) $order->get_shipping_address_1();
		$get          = static function ( $field ) use ( $order, $has_shipping ) {
			$m = ( $has_shipping ? 'get_shipping_' : 'get_billing_' ) . $field;
			return method_exists( $order, $m ) ? (string) $order->$m() : '';
		};
		$first        = static function ( $a, $b ) {
			$a = (string) $a;
			return '' !== trim( $a ) ? $a : (string) $b;
		};
		$meta         = self::meta_map( $order );
		$group        = $has_shipping ? array( 'shipping', 'billing' ) : array( 'billing' );
		$neighborhood = trim( self::br_field( $meta, 'neighborhood', $group ) );
		$document     = self::br_field( $meta, 'cpf', array( 'billing', 'other', 'shipping' ) );
		return array(
			'name'         => $first( trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) ), $order->get_formatted_billing_full_name() ),
			'address_1'    => $get( 'address_1' ),
			'number'       => self::br_field( $meta, 'number', $group ),
			'address_2'    => $neighborhood ? $get( 'address_2' ) : '',
			'neighborhood' => $neighborhood ? $neighborhood : $get( 'address_2' ),
			'city'         => $get( 'city' ),
			'state'        => $get( 'state' ),
			'postcode'     => $get( 'postcode' ),
			'phone'        => (string) $order->get_billing_phone(),
			'document'     => (string) preg_replace( '/\D/', '', $first( $document, self::br_field( $meta, 'cnpj', array( 'billing', 'other', 'shipping' ) ) ) ),
		);
	}

	/**
	 * Order meta as key => string value.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, string>
	 */
	private static function meta_map( WC_Order $order ) {
		$out = array();
		foreach ( $order->get_meta_data() as $m ) {
			$d = $m->get_data();
			if ( is_scalar( $d['value'] ) ) {
				$out[ (string) $d['key'] ] = (string) $d['value'];
			}
		}
		return $out;
	}

	/**
	 * First non-empty Brazilian field, trying each group in order: Brazilian Market keys ("_shipping_number") and then
	 * block-checkout additional fields ("_wc_shipping/<namespace>/shipping_number" or ".../billing_number",
	 * "_wc_other/<namespace>/billing_cpf"). Pure: unit tested.
	 *
	 * @param array<string, string> $meta   Order meta (key => value).
	 * @param string                $name   cpf, cnpj, number or neighborhood.
	 * @param array<int, string>    $groups billing, shipping and/or other, in order of preference.
	 * @return string
	 */
	public static function br_field( array $meta, $name, array $groups ) {
		foreach ( $groups as $g ) {
			$key = "_{$g}_{$name}";
			if ( isset( $meta[ $key ] ) && '' !== trim( $meta[ $key ] ) ) {
				return trim( $meta[ $key ] );
			}
			foreach ( $meta as $key => $value ) {
				if ( '' !== trim( $value ) && preg_match( '#^_wc_' . preg_quote( $g, '#' ) . '/[^/]+/(?:(?:billing|shipping)_)?' . preg_quote( $name, '#' ) . '$#', $key ) ) {
					return trim( $value );
				}
			}
		}
		return '';
	}

	/**
	 * Street and house number. Without the Brazilian Market number field, the number is split off the end of
	 * the street ("Rua X, 123", "Rua X nº 123"). Pure: unit tested.
	 *
	 * @param string $street Address line 1.
	 * @param string $number Number field ('' when absent).
	 * @return array{0: string, 1: string}
	 */
	public static function street_number( $street, $number ) {
		$street = trim( (string) $street );
		$number = trim( (string) $number );
		if ( '' === $number && preg_match( '/^(.+?)[\s,]+(?:n[º°o.]?\s*)?(\d+[A-Za-z]?|s\/?n)\s*$/iu', $street, $m ) ) {
			return array( rtrim( $m[1], ' ,' ), $m[2] );
		}
		return array( $street, $number );
	}

	/**
	 * Shippable items in kg/cm (virtual products skipped).
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, array<string, mixed>>
	 */
	public static function items( WC_Order $order ) {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$p = $item->get_product();
			if ( ! $p || $p->is_virtual() ) {
				continue;
			}
			$out[] = array(
				'item'   => $item,
				'weight' => (float) wc_get_weight( (float) $p->get_weight(), 'kg' ),
				'length' => (float) wc_get_dimension( (float) $p->get_length(), 'cm' ),
				'height' => (float) wc_get_dimension( (float) $p->get_height(), 'cm' ),
				'width'  => (float) wc_get_dimension( (float) $p->get_width(), 'cm' ),
				'qty'    => (int) $item->get_quantity(),
				'sku'    => (string) $p->get_sku(),
				'id'     => (string) $p->get_id(),
			);
		}
		return $out;
	}

	/**
	 * ShippingItemArray for POST /shipping/quote.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, array<string, mixed>>
	 */
	public static function quote_items( WC_Order $order ) {
		return array_map(
			static function ( $i ) {
				return array(
					'Weight'   => $i['weight'],
					'Length'   => $i['length'],
					'Height'   => $i['height'],
					'Width'    => $i['width'],
					'Quantity' => $i['qty'],
					'SKU'      => $i['sku'],
				);
			},
			self::items( $order )
		);
	}

	/**
	 * Shipment body for the chosen service.
	 *
	 * @param WC_Order             $order   Order.
	 * @param array<string, mixed> $service WC_Frenet_Labels_Compare::normalize() row.
	 * @return array<string, mixed>
	 */
	public static function build( WC_Order $order, array $service ) {
		$sender = self::sender();
		$to     = self::recipient( $order );
		$items  = array();
		$weight = 0.0;
		$len    = 0.0;
		$wid    = 0.0;
		$hei    = 0.0;
		foreach ( self::items( $order ) as $i ) {
			$weight += $i['weight'] * $i['qty'];
			$len     = max( $len, $i['length'] );
			$wid     = max( $wid, $i['width'] );
			$hei    += $i['height'] * $i['qty'];
			$items[] = array(
				'OrderId'     => (string) $order->get_id(),
				'ItemId'      => (string) $i['item']->get_id(),
				'ProductId'   => $i['id'],
				'ProductName' => $i['item']->get_name(),
				'SKU'         => $i['sku'],
				'Quantity'    => $i['qty'],
				'Price'       => round( (float) $order->get_item_total( $i['item'] ), 2 ),
				'Weight'      => $i['weight'],
				'Length'      => $i['length'],
				'Height'      => $i['height'],
				'Width'       => $i['width'],
				'IsFragile'   => false,
			);
		}
		$address  = static function ( array $a ) {
			list( $street, $number ) = self::street_number( $a['address_1'], $a['number'] );
			return array(
				'ZipCode'           => preg_replace( '/\D/', '', $a['postcode'] ),
				'City'              => $a['city'],
				'Street'            => $street,
				'AddressNumber'     => $number,
				'AddressComplement' => trim( $a['address_2'] ),
				'AddressQuarter'    => '' !== trim( $a['neighborhood'] ) ? trim( $a['neighborhood'] ) : '-',
				'AddressState'      => $a['state'],
				'Country'           => 'BR',
			);
		};
		$created  = $order->get_date_created();
		$value    = round( (float) $order->get_subtotal(), 2 );
		$shipment = array(
			'Order'     => array(
				'Id'                    => (string) $order->get_id(),
				'Value'                 => $value,
				'Created'               => gmdate( 'Y-m-d\TH:i:s.000\Z', $created ? $created->getTimestamp() : time() ),
				'UseFrenetRegistration' => true,
				'Items'                 => $items,
				'From'                  => array(
					'Name'     => $sender['name'],
					'Document' => $sender['document'],
					'Email'    => (string) get_option( 'admin_email' ),
					'Address'  => $address( $sender ),
				),
				'To'                    => array(
					'Name'     => $to['name'],
					'Document' => $to['document'],
					'Email'    => (string) $order->get_billing_email(),
					'Phone'    => $to['phone'],
					'Address'  => $address( $to ),
				),
			),
			'Volumes'   => array(
				'Weight'        => round( $weight, 3 ),
				'Length'        => $len,
				'Height'        => $hei,
				'Width'         => $wid,
				'Price'         => $value,
				'DeclaredValue' => $value,
				'OrderItemsId'  => array_column( $items, 'ItemId' ),
			),
			'Quotation' => array(
				'ShippingServiceCode'   => (string) $service['code'],
				'ShippingServiceName'   => (string) $service['name'],
				'PlatformShippingPrice' => (float) $order->get_shipping_total(),
				'ShippingPrice'         => (float) $service['price'],
				'DeliveryTime'          => (int) $service['days'],
				'Carrier'               => (string) $service['carrier'],
				'CarrierCode'           => (string) $service['carrier_code'],
				'Services'              => array(
					'DeclaredValue'       => false,
					'ReceiptNotification' => false,
					'OwnHand'             => false,
				),
			),
		);
		$invoice  = self::invoice( $order );
		if ( $invoice ) {
			$shipment['Order']['Invoice'] = $invoice;
		}
		return $shipment;
	}

	/**
	 * Order.Invoice from the NF-e key saved on the order, or null.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, mixed>|null
	 */
	public static function invoice( WC_Order $order ) {
		$parsed = WC_Frenet_Labels_Nfe::parse( (string) $order->get_meta( WC_Frenet_Labels_Nfe::META ) );
		if ( ! $parsed['ok'] ) {
			return null;
		}
		$date = $order->get_date_created();
		return array(
			'Key'    => $parsed['key'],
			'Number' => $parsed['number'],
			'Series' => $parsed['series'],
			'Value'  => round( (float) $order->get_total(), 2 ),
			'Date'   => $date ? $date->date( 'Y-m-d\TH:i:s' ) : gmdate( 'Y-m-d\TH:i:s' ),
		);
	}
}
