<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fills street, neighborhood, city and state from the CEP in the classic checkout, the checkout
 * and cart blocks and My Account > Addresses. Lookups go to the Frenet API (GET /CEP/Address) with
 * the store token, are cached for 30 days per CEP and throttled per visitor.
 */
class WC_Frenet_Autofill {

	const AJAX     = 'frenet_address_by_cep';
	const CACHE    = 'wc_frenet_cep_';
	const THROTTLE = 30; // Lookups per visitor per minute.

	/**
	 * Hooks (only when switched on in Frenet > Settings).
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! WC_Frenet_Labels_Settings::on( 'autofill' ) ) {
			return;
		}
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX, array( __CLASS__, 'ajax' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Script and style on the pages with address forms.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! ( is_checkout() || is_cart() || is_account_page() ) ) {
			return;
		}
		$base = plugins_url( '/', WOO_FRENET_PATH . 'woo-shipping-gateway.php' );
		$ver  = WC_Frenet_Main::VERSION . '.' . filemtime( WOO_FRENET_PATH . 'assets/js/frenet-autofill.js' );
		wp_enqueue_style( 'frenet-autofill', $base . 'assets/css/frenet-autofill.css', array(), $ver );
		// On block pages the script reads the cart store: declare it so WooCommerce loads it first.
		$deps = wp_script_is( 'wc-blocks-data-store', 'registered' ) ? array( 'wp-data', 'wc-blocks-data-store' ) : array();
		wp_enqueue_script( 'frenet-autofill', $base . 'assets/js/frenet-autofill.js', $deps, $ver, true );
		wp_localize_script(
			'frenet-autofill',
			'frenetAutofill',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX,
				'nonce'    => wp_create_nonce( self::AJAX ),
				'loading'  => __( 'Looking up the CEP…', 'woo-shipping-gateway' ),
				'notFound' => __( 'We could not find this CEP. Check the number or fill in the address by hand.', 'woo-shipping-gateway' ),
			)
		);
	}

	/**
	 * Address of a CEP, from the cache or the Frenet API.
	 *
	 * @param string $cep Postcode in any format.
	 * @return array{postcode: string, address_1: string, district: string, city: string, state: string}|WP_Error
	 */
	public static function lookup( $cep ) {
		$cep = (string) preg_replace( '/\D/', '', (string) $cep );
		if ( 8 !== strlen( $cep ) ) {
			return new WP_Error( 'frenet_cep_invalid', __( 'A CEP has 8 digits.', 'woo-shipping-gateway' ) );
		}
		$hit = get_transient( self::CACHE . $cep );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$data = WC_Frenet_Labels_Api::address( $cep );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$address = self::normalize( $cep, $data );
		if ( '' === $address['city'] ) {
			return new WP_Error( 'frenet_cep_not_found', __( 'CEP not found.', 'woo-shipping-gateway' ) );
		}
		set_transient( self::CACHE . $cep, $address, 30 * DAY_IN_SECONDS );
		return $address;
	}

	/**
	 * Frenet answer → WooCommerce address fields. Pure: unit tested.
	 *
	 * @param string               $cep  Digits.
	 * @param array<string, mixed> $data Frenet answer (PascalCase or camelCase).
	 * @return array{postcode: string, address_1: string, district: string, city: string, state: string}
	 */
	public static function normalize( $cep, array $data ) {
		$get = function ( $key ) use ( $data ) {
			$v = $data[ $key ] ?? $data[ lcfirst( $key ) ] ?? '';
			return trim( is_scalar( $v ) ? (string) $v : '' );
		};
		return array(
			'postcode'  => substr( $cep, 0, 5 ) . '-' . substr( $cep, 5 ),
			'address_1' => $get( 'Street' ),
			'district'  => $get( 'District' ),
			'city'      => $get( 'City' ),
			'state'     => strtoupper( $get( 'UF' ) ),
		);
	}

	/**
	 * AJAX: address of a CEP (read-only, public, throttled).
	 *
	 * @return void
	 */
	public static function ajax() {
		check_ajax_referer( self::AJAX, 'nonce' );
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key  = 'wc_frenet_cep_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= self::THROTTLE ) {
			wp_send_json_error( array( 'message' => __( 'Too many lookups. Wait a minute and try again.', 'woo-shipping-gateway' ) ), 429 );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		$cep     = isset( $_POST['cep'] ) ? sanitize_text_field( wp_unslash( $_POST['cep'] ) ) : '';
		$address = self::lookup( $cep );
		if ( is_wp_error( $address ) ) {
			wp_send_json_error( array( 'message' => $address->get_error_message() ) ); // 200: an unknown CEP is an answer, not a server error.
		}
		wp_send_json_success( $address );
	}
}
