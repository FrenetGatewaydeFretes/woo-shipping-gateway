<?php
use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

defined( 'ABSPATH' ) || exit;

/**
 * Fills street, neighborhood, city and state from the CEP in the classic checkout, the checkout
 * and cart blocks and My Account > Addresses. Lookups go to the Frenet API (GET /CEP/Address) with
 * the store token, are cached for 30 days per CEP and the lookups that reach Frenet are throttled per
 * visitor and for the whole store. The storefront asks the public
 * read-only route GET /wp-json/frenet/v1/cep/{cep}, which works on cached pages (no nonce).
 */
class WC_Frenet_Autofill {

	const AJAX     = 'frenet_address_by_cep';
	const ROUTE    = 'frenet/v1';
	const CACHE    = 'wc_frenet_cep_';
	const THROTTLE = 30; // Lookups per visitor per minute.
	const GLOBAL   = 120;
	const RATE     = 'wc_frenet_cep_rl_';

	/**
	 * Hooks (only when switched on in Frenet > Settings).
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! WC_Frenet_Labels_Settings::on( 'autofill' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
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
		$deps = self::block_page() && wp_script_is( 'wc-blocks-data-store', 'registered' ) ? array( 'wp-data', 'wc-blocks-data-store' ) : array();
		wp_enqueue_script( 'frenet-autofill', $base . 'assets/js/frenet-autofill.js', $deps, $ver, true );
		wp_localize_script(
			'frenet-autofill',
			'frenetAutofill',
			array(
				'restUrl'  => rest_url( self::ROUTE . '/cep/' ),
				'loading'  => __( 'Looking up the CEP…', 'woo-shipping-gateway' ),
				'notFound' => __( 'We could not find this CEP. Check the number or fill in the address by hand.', 'woo-shipping-gateway' ),
			)
		);
	}

	/**
	 * Whether the page renders the cart or checkout block (in its content or in the block theme template).
	 *
	 * @return bool
	 */
	private static function block_page() {
		if ( has_block( 'woocommerce/checkout' ) || has_block( 'woocommerce/cart' ) ) {
			return true;
		}
		if ( ! class_exists( CartCheckoutUtils::class ) ) {
			return false;
		}
		if ( is_checkout() && method_exists( CartCheckoutUtils::class, 'is_checkout_block_default' ) ) {
			return CartCheckoutUtils::is_checkout_block_default();
		}
		if ( is_cart() && method_exists( CartCheckoutUtils::class, 'is_cart_block_default' ) ) {
			return CartCheckoutUtils::is_cart_block_default();
		}
		return false;
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
		$hit = self::cached( $cep );
		if ( $hit ) {
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
	 * Address of a CEP already in the 30-day cache.
	 *
	 * @param string $cep Digits.
	 * @return array{postcode: string, address_1: string, district: string, city: string, state: string}|null
	 */
	private static function cached( $cep ) {
		$hit = get_transient( self::CACHE . $cep );
		return is_array( $hit ) ? $hit : null;
	}

	/**
	 * Lookup asked by a visitor: cached CEPs answer at once, the others count against the limits first.
	 *
	 * @param string $cep Postcode in any format.
	 * @return array{postcode: string, address_1: string, district: string, city: string, state: string}|WP_Error
	 */
	private static function visitor_lookup( $cep ) {
		$cep = (string) preg_replace( '/\D/', '', (string) $cep );
		$hit = 8 === strlen( $cep ) ? self::cached( $cep ) : null;
		if ( $hit ) {
			return $hit;
		}
		if ( 8 === strlen( $cep ) && self::throttled() ) {
			return new WP_Error( 'frenet_cep_throttled', __( 'Too many lookups. Wait a minute and try again.', 'woo-shipping-gateway' ) );
		}
		return self::lookup( $cep );
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
	 * Public read-only REST route: address of a CEP.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::ROUTE,
			'/cep/(?P<cep>\d{8})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'cep' => array(
						'type'     => 'string',
						'pattern'  => '^\d{8}$',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * REST: address of a CEP (404 when unknown, 429 when throttled, 502 when Frenet fails).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest( WP_REST_Request $request ) {
		$address = self::visitor_lookup( (string) $request['cep'] );
		if ( is_wp_error( $address ) ) {
			$codes = array(
				'frenet_cep_invalid'   => 400,
				'frenet_cep_not_found' => 404,
				'frenet_cep_throttled' => 429,
			);
			return new WP_Error( $address->get_error_code(), $address->get_error_message(), array( 'status' => $codes[ $address->get_error_code() ] ?? 502 ) );
		}
		return rest_ensure_response( $address );
	}

	/**
	 * Counts a lookup that will reach Frenet; true when the visitor or the whole store is over its limit.
	 *
	 * @return bool
	 */
	private static function throttled() {
		/**
		 * CEP lookups that may reach Frenet per visitor IP per minute (0 = no limit).
		 *
		 * @since 3.0.0
		 *
		 * @param int $limit Lookups.
		 */
		$per_ip = (int) apply_filters( 'wc_frenet_cep_lookups_per_ip', self::THROTTLE );
		/**
		 * CEP lookups that may reach Frenet per minute for the whole store (0 = no limit).
		 *
		 * @since 3.0.0
		 *
		 * @param int $limit Lookups.
		 */
		$global = (int) apply_filters( 'wc_frenet_cep_lookups_per_minute', self::GLOBAL );
		if ( $per_ip > 0 && self::hit( md5( self::client_ip() ) ) > $per_ip ) {
			return true;
		}
		return $global > 0 && self::hit( 'all' ) > $global;
	}

	/**
	 * Adds one to a one-minute counter and returns it (object cache when there is one, transients otherwise).
	 *
	 * @param string $bucket Counter name.
	 * @return int
	 */
	private static function hit( $bucket ) {
		$key = self::RATE . $bucket;
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, 'wc_frenet', MINUTE_IN_SECONDS );
			$hits = wp_cache_incr( $key, 1, 'wc_frenet' );
			if ( false !== $hits ) {
				return (int) $hits;
			}
		}
		$hits = (int) get_transient( $key ) + 1;
		set_transient( $key, $hits, MINUTE_IN_SECONDS );
		return $hits;
	}

	/**
	 * Visitor IP: REMOTE_ADDR, or the header named by the wc_frenet_cep_client_ip_header filter (e.g. CF-Connecting-IP).
	 *
	 * @return string
	 */
	private static function client_ip() {
		/**
		 * Request header holding the visitor IP set by a trusted proxy (e.g. CF-Connecting-IP); empty uses REMOTE_ADDR.
		 *
		 * @since 3.0.0
		 *
		 * @param string $header Header name.
		 */
		$header = (string) apply_filters( 'wc_frenet_cep_client_ip_header', '' );
		$key    = '' === $header ? '' : 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );
		$values = array();
		if ( '' !== $key && isset( $_SERVER[ $key ] ) ) {
			$values[] = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0] );
		}
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$values[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		foreach ( $values as $ip ) {
			if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}

	/**
	 * AJAX: address of a CEP, for pages cached before the REST route (read-only, public, throttled).
	 *
	 * @deprecated Use the REST route frenet/v1/cep/{cep}.
	 * @return void
	 */
	public static function ajax() {
		$cep     = isset( $_POST['cep'] ) ? sanitize_text_field( wp_unslash( $_POST['cep'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only lookup; a nonce cached with the page would only break it.
		$address = self::visitor_lookup( $cep );
		if ( is_wp_error( $address ) && 'frenet_cep_throttled' === $address->get_error_code() ) {
			wp_send_json_error( array( 'message' => $address->get_error_message() ), 429 );
		}
		if ( is_wp_error( $address ) ) {
			wp_send_json_error( array( 'message' => $address->get_error_message() ) ); // 200: an unknown CEP is an answer, not a server error.
		}
		wp_send_json_success( $address );
	}
}
