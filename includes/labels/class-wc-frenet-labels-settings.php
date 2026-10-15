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
			'enabled'         => 'no',
			'token'           => '',
			'partner_token'   => '',
			'api_url'         => self::DEFAULT_URL,
			'default_service' => WC_Frenet_Labels_Compare::BEST_PRICE,
			'auto_best'       => 'no',
			'batch_print'     => 'yes',
			'wallet_payment'  => 'yes',
			'journey'         => 'here',
			'label_format'    => 'A4',
			'sender_document' => '',
			'autofill'        => 'no',
			'tracking_sync'   => 'no',
			'tracking_status' => 'no',
			'tracking_email'  => 'yes',
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
		foreach ( array( 'enabled', 'auto_best', 'batch_print', 'wallet_payment', 'autofill', 'tracking_sync', 'tracking_status', 'tracking_email' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 'yes' : 'no';
		}
		$out['token']           = sanitize_text_field( (string) ( $input['token'] ?? '' ) );
		$partner                = sanitize_text_field( (string) ( $input['partner_token'] ?? '' ) );
		$out['partner_token']   = '' !== $partner ? $partner : $current['partner_token'];
		$url                    = esc_url_raw( trim( (string) ( $input['api_url'] ?? '' ) ) );
		$out['api_url']         = '' !== $url ? untrailingslashit( $url ) : self::DEFAULT_URL;
		$out['default_service'] = WC_Frenet_Labels_Compare::BEST_TIME === ( $input['default_service'] ?? '' ) ? WC_Frenet_Labels_Compare::BEST_TIME : WC_Frenet_Labels_Compare::BEST_PRICE;
		$out['journey']         = 'panel' === ( $input['journey'] ?? '' ) ? 'panel' : 'here';
		$out['label_format']    = '10x15' === ( $input['label_format'] ?? '' ) ? '10x15' : 'A4';
		$out['sender_document'] = preg_replace( '/\D/', '', (string) ( $input['sender_document'] ?? '' ) );
		if ( ! empty( $input['clear_partner_token'] ) ) {
			$out['partner_token'] = '';
		}
		update_option( self::OPTION, $out, false );
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
	 * True when the API is not the real Frenet one (local simulator): the screens show a warning.
	 *
	 * @return bool
	 */
	public static function simulated() {
		return (bool) apply_filters( 'woocommerce_frenet_labels_simulated', self::DEFAULT_URL !== self::get( 'api_url' ) );
	}
}
