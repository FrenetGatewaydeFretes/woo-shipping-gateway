<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings of the Frenet labels screens, in their own option so the shipping method settings
 * (woocommerce_frenet_<instance>_settings, a contract with live stores) are never touched.
 */
class WC_Frenet_Labels_Settings {

	const OPTION      = 'woocommerce_frenet_labels_settings';
	const DEFAULT_URL = 'https://whitelabel.frenet.com.br/v1';

	/**
	 * Defaults: everything off and the safest choices.
	 *
	 * The address-by-CEP and the tracking sync start off: other Brazilian checkout plugins may
	 * already fill the address, and the sync calls the Frenet API every hour. The status e-mails
	 * start on because they only fire when the automatic status (off by default) changes an order.
	 *
	 * @return array<string, string>
	 */
	public static function defaults() {
		return array(
			'enabled'               => 'no',
			'token'                 => '',
			'partner_token'         => '',
			'api_url'               => self::DEFAULT_URL,
			'default_service'       => WC_Frenet_Labels_Compare::BEST_PRICE,
			'auto_best'             => 'no',
			'batch_print'           => 'yes',
			'wallet_payment'        => 'yes',
			'auto_create'           => 'no',
			'auto_create_rule'      => 'customer',
			'auto_create_consent'   => 'no',
			'auto_carrier_fallback' => 'best_price',
			'journey'               => 'here',
			'label_format'          => 'A4',
			'sender_name'           => '',
			'sender_postcode'       => '',
			'sender_address_1'      => '',
			'sender_number'         => '',
			'sender_address_2'      => '',
			'sender_neighborhood'   => '',
			'sender_city'           => '',
			'sender_state'          => '',
			'sender_email'          => '',
			'sender_document'       => '',
			'autofill'              => 'no',
			'tracking_sync'         => 'no',
			'tracking_status'       => 'no',
			'tracking_email'        => 'yes',
			// First steps (WC_Frenet_Labels_Setup): each one is "yes" once the merchant confirmed it.
			'setup_account'         => 'no',
			'setup_sender'          => 'no',
			'setup_payment'         => 'no',
		);
	}

	/**
	 * All settings merged with the defaults.
	 *
	 * @return array<string, string>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? array_map( 'strval', $saved ) : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? trim( (string) $all[ $key ] ) : '';
	}

	/**
	 * Whether a yes/no setting is on.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Saves only the known keys, sanitized. An empty partner token field keeps the saved one
	 * (the field is never printed back into the page).
	 *
	 * @param array<string, mixed> $input Raw form values.
	 * @return void
	 */
	public static function save( array $input ) {
		$current = self::all();
		$out     = $current;
		foreach ( array( 'enabled', 'auto_best', 'batch_print', 'wallet_payment', 'auto_create', 'auto_create_consent', 'autofill', 'tracking_sync', 'tracking_status', 'tracking_email' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 'yes' : 'no';
		}
		$out['token']           = sanitize_text_field( (string) ( $input['token'] ?? '' ) );
		$partner                = sanitize_text_field( (string) ( $input['partner_token'] ?? '' ) );
		$out['partner_token']   = '' !== $partner ? $partner : $current['partner_token'];
		$url                    = esc_url_raw( trim( (string) ( $input['api_url'] ?? '' ) ) );
		$out['api_url']         = '' !== $url ? untrailingslashit( $url ) : self::DEFAULT_URL;
		$out['default_service'] = WC_Frenet_Labels_Compare::BEST_TIME === ( $input['default_service'] ?? '' ) ? WC_Frenet_Labels_Compare::BEST_TIME : WC_Frenet_Labels_Compare::BEST_PRICE;
		// The labels are always bought from the store (the "send to the Frenet panel" choice was dropped on 2026-10-20).
		$out['journey']          = 'here';
		$out['label_format']     = '10x15' === ( $input['label_format'] ?? '' ) ? '10x15' : 'A4';
		$rule                    = (string) ( $input['auto_create_rule'] ?? '' );
		$out['auto_create_rule'] = in_array( $rule, array( 'customer', 'best_price', 'best_time' ), true ) ? $rule : 'customer';
		// Without Frenet quoting at checkout the customer never chooses: that rule cannot be saved.
		if ( 'customer' === $out['auto_create_rule'] && ! self::quotes_at_checkout() ) {
			$out['auto_create_rule'] = 'best_price';
		}
		$out['auto_carrier_fallback'] = 'skip' === ( $input['auto_carrier_fallback'] ?? '' ) ? 'skip' : 'best_price';
		// The automation needs the merchant's explicit authorisation to debit the wallet without confirming each order.
		if ( 'yes' !== $out['auto_create_consent'] ) {
			$out['auto_create'] = 'no';
		}
		$out = array_merge( $out, self::sender_input( $input ) );
		if ( ! empty( $input['clear_partner_token'] ) ) {
			$out['partner_token'] = '';
		}
		update_option( self::OPTION, $out, false );
	}

	/**
	 * Saves some keys and keeps the rest (the first steps save one step at a time).
	 *
	 * @param array<string, string> $values Already sanitized values.
	 * @return void
	 */
	public static function update( array $values ) {
		$out = array_merge( self::all(), array_intersect_key( $values, self::defaults() ) );
		update_option( self::OPTION, array_map( 'strval', $out ), false );
	}

	/**
	 * Sender fields of a form, sanitized, as "sender_<key>" settings.
	 * A value equal to the store's is saved empty, so it keeps following WooCommerce > Settings > General.
	 *
	 * @param array<string, mixed> $input Raw form values.
	 * @return array<string, string>
	 */
	public static function sender_input( array $input ) {
		$store = WC_Frenet_Labels_Payload::store_sender();
		$out   = array();
		foreach ( WC_Frenet_Labels_Payload::SENDER_FIELDS as $key ) {
			$value = sanitize_text_field( (string) ( $input[ 'sender_' . $key ] ?? '' ) );
			if ( 'postcode' === $key ) {
				$value = (string) preg_replace( '/\D/', '', $value );
			} elseif ( 'document' === $key ) {
				// Letters stay: the CNPJ is alphanumeric since July 2026.
				$value = strtoupper( (string) preg_replace( '/[^0-9A-Za-z]/', '', $value ) );
			} elseif ( 'state' === $key ) {
				$value = strtoupper( substr( $value, 0, 2 ) );
			} elseif ( 'email' === $key ) {
				$value = sanitize_email( $value );
			}
			$out[ 'sender_' . $key ] = trim( (string) $store[ $key ] ) === $value ? '' : $value;
		}
		return $out;
	}

	/**
	 * Sender values set by the merchant, keyed like WC_Frenet_Labels_Payload::SENDER_FIELDS ('' = the store's).
	 *
	 * @return array<string, string>
	 */
	public static function sender_overrides() {
		$out = array();
		foreach ( WC_Frenet_Labels_Payload::SENDER_FIELDS as $key ) {
			$out[ $key ] = self::get( 'sender_' . $key );
		}
		return $out;
	}

	/**
	 * Store (client) token: the one set here, otherwise the one of the Frenet shipping method.
	 *
	 * @return string
	 */
	public static function store_token() {
		$own = self::get( 'token' );
		if ( '' !== $own ) {
			return $own;
		}
		$opts = self::shipping_options();
		return trim( (string) ( $opts['token'] ?? '' ) );
	}

	/**
	 * Origin CEP of the Frenet shipping method, else the store postcode.
	 *
	 * @return string
	 */
	public static function origin_cep() {
		$opts = self::shipping_options();
		$cep  = (string) ( $opts['zip_origin'] ?? '' );
		if ( '' === preg_replace( '/\D/', '', $cep ) ) {
			$cep = (string) get_option( 'woocommerce_store_postcode' );
		}
		return (string) preg_replace( '/\D/', '', $cep );
	}

	/**
	 * Options of the first enabled Frenet shipping method (through the plugin's own helper).
	 *
	 * @return array<string, mixed>
	 */
	public static function shipping_options() {
		if ( ! class_exists( 'WC_Frenet_Helper' ) ) {
			return array();
		}
		$helper = new WC_Frenet_Helper();
		$opts   = $helper->get_options(); // The option may be missing (false) on a store without a Frenet zone.
		return $opts ? (array) $opts : array();
	}

	/**
	 * Labels can be used: switched on, partner token and store token present.
	 *
	 * @return bool
	 */
	public static function ready() {
		return self::on( 'enabled' ) && '' !== self::get( 'partner_token' ) && '' !== self::store_token();
	}

	/**
	 * How far the account goes: "labels" (both tokens: everything), "basic" (store token only: quotes, simulator,
	 * tracking, address by CEP and the wallet balance) or "none" (no token yet).
	 *
	 * @return string
	 */
	public static function mode() {
		if ( self::ready() ) {
			return 'labels';
		}
		return '' !== self::store_token() ? 'basic' : 'none';
	}

	/**
	 * Whether customers see Frenet carriers at checkout: the Frenet shipping method is enabled in some zone.
	 * When it is not, they pay another method (flat rate, free shipping) and the store decides the carrier.
	 *
	 * @return bool
	 */
	public static function quotes_at_checkout() {
		if ( ! class_exists( 'WC_Frenet_Helper' ) ) {
			return false;
		}
		return (bool) ( new WC_Frenet_Helper() )->get_instance_ids();
	}
}
