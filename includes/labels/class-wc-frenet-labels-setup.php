<?php
defined( 'ABSPATH' ) || exit;

/**
 * First steps (Frenet > Primeiros passos): connect the account and confirm the sender. Adding balance lives in
 * the wallet of the brand strip, on every screen, and how labels are paid stays in Settings.
 * Each step is saved on its own and marked done only after it is checked (tokens against the live wallet,
 * sender against what Frenet requires), so the merchant never reaches "Criar envio" with a setup that fails.
 * Without a partner token only the first step applies (basic mode): the sender is for labels.
 * Stores that were configured before this screen existed keep working: nothing here blocks the label screens.
 */
class WC_Frenet_Labels_Setup {

	const PAGE   = 'frenet-setup';
	const ACTION = 'frenet_labels_setup';
	const FLASH  = 'frenet_labels_setup_flash_';
	const STEPS  = array( 'account', 'sender' );

	/** Brazilian states, for the sender UF. */
	const UFS = array( 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_frenet_labels_setup_cep', array( __CLASS__, 'ajax_cep' ) );
		// A store with nothing configured lands on the first steps instead of the full settings form.
		add_action( 'load-toplevel_page_' . WC_Frenet_Labels_Admin::SLUG, array( __CLASS__, 'maybe_redirect' ) );
	}

	/* ------------------------------------------------------------------ state */

	/**
	 * Whether a step is done.
	 *
	 * @param string $step Step.
	 * @return bool
	 */
	public static function done( $step ) {
		switch ( $step ) {
			case 'account':
				return WC_Frenet_Labels_Settings::on( 'setup_account' ) && 'none' !== WC_Frenet_Labels_Settings::mode();
			case 'sender':
				return WC_Frenet_Labels_Settings::on( 'setup_sender' ) && ! self::sender_errors( WC_Frenet_Labels_Payload::sender() );
		}
		return false;
	}

	/**
	 * Whether every step is done.
	 *
	 * @return bool
	 */
	public static function complete() {
		foreach ( self::steps() as $step ) {
			if ( ! self::done( $step ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Steps that apply: all of them with a partner token, only the account without one (basic mode).
	 *
	 * @return array<int, string>
	 */
	public static function steps() {
		return '' !== WC_Frenet_Labels_Settings::get( 'partner_token' ) ? self::STEPS : array( 'account' );
	}

	/**
	 * Title and short description of each step.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function titles() {
		return array(
			'account' => array( __( 'Connect your account', 'woo-shipping-gateway' ), __( 'Store token, and partner token for labels', 'woo-shipping-gateway' ) ),
			'sender'  => array( __( 'Confirm the sender', 'woo-shipping-gateway' ), __( 'Who sends the parcels', 'woo-shipping-gateway' ) ),
		);
	}

	/**
	 * Steps still to do.
	 *
	 * @return int
	 */
	public static function remaining() {
		return count( array_filter( self::steps(), array( __CLASS__, 'pending' ) ) );
	}

	/**
	 * Whether a step is still to do (array_filter callback).
	 *
	 * @param string $step Step.
	 * @return bool
	 */
	public static function pending( $step ) {
		return ! self::done( $step );
	}

	/**
	 * Step on screen: the one asked in the URL, otherwise the first one not done, otherwise "done".
	 *
	 * @return string
	 */
	public static function current() {
		$asked = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		if ( in_array( $asked, self::steps(), true ) || 'done' === $asked ) {
			return $asked;
		}
		foreach ( self::steps() as $step ) {
			if ( ! self::done( $step ) ) {
				return $step;
			}
		}
		return 'done';
	}

	/**
	 * URL of a step.
	 *
	 * @param string $step Step ('' = the first one not done).
	 * @return string
	 */
	public static function url( $step = '' ) {
		return WC_Frenet_Labels_Admin::url( self::PAGE, '' !== $step ? array( 'step' => $step ) : array() );
	}

	/**
	 * Sends a store without tokens from Frenet > Settings to the first steps.
	 *
	 * @return void
	 */
	public static function maybe_redirect() {
		// "full": the merchant chose the whole form (tracking and CEP autofill need no partner token).
		if ( isset( $_GET['full'] ) || isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flags only.
			return;
		}
		if ( '' !== WC_Frenet_Labels_Settings::get( 'partner_token' ) || self::complete() ) {
			return; // Configured by hand on the full form: respect it.
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/* ------------------------------------------------------------------ sender checks */

	/**
	 * What the sender still lacks for Frenet, by field. Pure: unit tested.
	 *
	 * @param array<string, string> $s Sender (WC_Frenet_Labels_Payload::SENDER_FIELDS).
	 * @return array<string, string> Field → message (empty when the sender is complete).
	 */
	public static function sender_errors( array $s ) {
		$get    = function ( $key ) use ( $s ) {
			return trim( (string) ( $s[ $key ] ?? '' ) );
		};
		$errors = array();
		if ( '' === $get( 'name' ) ) {
			$errors['name'] = __( 'Type the sender name.', 'woo-shipping-gateway' );
		}
		if ( '' === $get( 'document' ) ) {
			$errors['document'] = __( 'Type the CPF or CNPJ of the Frenet account.', 'woo-shipping-gateway' );
		} elseif ( ! self::valid_document( $get( 'document' ) ) ) {
			$errors['document'] = __( 'This CPF/CNPJ is not valid. Check the digits.', 'woo-shipping-gateway' );
		}
		if ( ! is_email( $get( 'email' ) ) ) {
			$errors['email'] = __( 'Type a valid e-mail.', 'woo-shipping-gateway' );
		}
		if ( 8 !== strlen( (string) preg_replace( '/\D/', '', $get( 'postcode' ) ) ) ) {
			$errors['postcode'] = __( 'A CEP has 8 digits.', 'woo-shipping-gateway' );
		}
		$required = array(
			'address_1'    => __( 'Type the street.', 'woo-shipping-gateway' ),
			'number'       => __( 'Type the number (or S/N).', 'woo-shipping-gateway' ),
			'neighborhood' => __( 'Type the neighborhood.', 'woo-shipping-gateway' ),
			'city'         => __( 'Type the city.', 'woo-shipping-gateway' ),
		);
		foreach ( $required as $key => $message ) {
			if ( '' === $get( $key ) ) {
				$errors[ $key ] = $message;
			}
		}
		if ( ! in_array( strtoupper( $get( 'state' ) ), self::UFS, true ) ) {
			$errors['state'] = __( 'Choose the state (UF).', 'woo-shipping-gateway' );
		}
		return $errors;
	}

	/**
	 * CPF (11 digits) or CNPJ (14 characters, numeric or the alphanumeric format of July 2026) with valid
	 * check digits. Pure: unit tested.
	 *
	 * @param string $doc Document, with or without punctuation.
	 * @return bool
	 */
	public static function valid_document( $doc ) {
		$doc = strtoupper( (string) preg_replace( '/[^0-9A-Za-z]/', '', (string) $doc ) );
		if ( preg_match( '/^\d{11}$/', $doc ) ) {
			if ( preg_match( '/^(\d)\1{10}$/', $doc ) ) {
				return false;
			}
			for ( $t = 9; $t < 11; $t++ ) {
				$sum = 0;
				for ( $i = 0; $i < $t; $i++ ) {
					$sum += (int) $doc[ $i ] * ( $t + 1 - $i );
				}
				if ( (int) $doc[ $t ] !== ( $sum * 10 ) % 11 % 10 ) {
					return false;
				}
			}
			return true;
		}
		// CNPJ: each character is worth its ASCII code minus 48 (0-9 → 0-9, A → 17...); the check digits stay numeric.
		if ( ! preg_match( '/^[0-9A-Z]{12}\d{2}$/', $doc ) || preg_match( '/^(\d)\1{13}$/', $doc ) ) {
			return false;
		}
		foreach ( array( 12, 13 ) as $len ) {
			$sum    = 0;
			$weight = $len - 7;
			for ( $i = 0; $i < $len; $i++ ) {
				$sum   += ( ord( $doc[ $i ] ) - 48 ) * $weight;
				$weight = 2 === $weight ? 9 : $weight - 1;
			}
			$rest = $sum % 11;
			if ( (int) $doc[ $len ] !== ( $rest < 2 ? 0 : 11 - $rest ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Sender to confirm: the saved one, with what the CEP knows filling the empty street and neighborhood
	 * (WooCommerce has no neighborhood field). Network only when something is missing; cached 30 days per CEP.
	 *
	 * @return array{sender: array<string, string>, from_cep: array<int, string>}
	 */
	public static function sender_draft() {
		$sender   = WC_Frenet_Labels_Payload::sender();
		$from_cep = array();
		$missing  = '' === trim( $sender['neighborhood'] ) || '' === trim( $sender['address_1'] );
		if ( $missing && class_exists( 'WC_Frenet_Autofill' ) && '' !== WC_Frenet_Labels_Settings::store_token() ) {
			$address = WC_Frenet_Autofill::lookup( $sender['postcode'] );
			if ( ! is_wp_error( $address ) ) {
				foreach ( array(
					'neighborhood' => 'district',
					'address_1'    => 'address_1',
					'city'         => 'city',
					'state'        => 'state',
				) as $key => $from ) {
					if ( '' === trim( $sender[ $key ] ) && '' !== $address[ $from ] ) {
						$sender[ $key ] = $address[ $from ];
						$from_cep[]     = $key;
					}
				}
			}
		}
		return array(
			'sender'   => $sender,
			'from_cep' => $from_cep,
		);
	}

	/* ------------------------------------------------------------------ requests */

	/**
	 * Saves one step (admin-post) and goes to the next one, or back to the same one with the errors.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( WC_Frenet_Labels_Admin::CAP ) || ! check_admin_referer( WC_Frenet_Labels_Admin::NONCE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woo-shipping-gateway' ), 403 );
		}
		$step  = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$input = isset( $_POST['frenet_labels'] ) && is_array( $_POST['frenet_labels'] ) ? wp_unslash( $_POST['frenet_labels'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized key by key below.
		switch ( $step ) {
			case 'account':
				$next = self::save_account( $input );
				break;
			case 'sender':
				$next = self::save_sender( $input );
				break;
			default:
				$next = '';
		}
		wp_safe_redirect( self::url( $next ) );
		exit;
	}

	/**
	 * Step 1: saves the tokens and reads the wallet with them; only a wallet answer marks the account connected.
	 * The partner token is optional: without it the account works in basic mode (no labels).
	 *
	 * @param array<string, mixed> $input Form values.
	 * @return string Next step.
	 */
	private static function save_account( array $input ) {
		$token   = sanitize_text_field( (string) ( $input['token'] ?? '' ) );
		$partner = sanitize_text_field( (string) ( $input['partner_token'] ?? '' ) );
		$before  = WC_Frenet_Labels_Settings::get( 'partner_token' );
		$values  = array(
			'enabled'       => 'yes',
			'token'         => $token,
			'setup_account' => 'no',
		);
		if ( '' !== $partner ) {
			$values['partner_token'] = $partner;
		}
		WC_Frenet_Labels_Settings::update( $values );
		$errors = array();
		if ( '' === WC_Frenet_Labels_Settings::store_token() ) {
			$errors['token'] = __( 'Paste the store token (Frenet panel > Integrations).', 'woo-shipping-gateway' );
		}
		if ( $errors ) {
			self::flash( 'error', __( 'Fill in the highlighted fields.', 'woo-shipping-gateway' ), $errors, array( 'token' => $token ) );
			return 'account';
		}
		delete_transient( WC_Frenet_Labels_Service::WALLET );
		$wallet = WC_Frenet_Labels_Service::wallet( true );
		$labels = '' !== WC_Frenet_Labels_Settings::get( 'partner_token' );
		// The wallet only checks the store token: the label list checks the partner token too, without buying anything.
		if ( $labels && ! is_wp_error( $wallet ) ) {
			$list = WC_Frenet_Labels_Service::shipments( 0, 1 );
			if ( is_wp_error( $list ) ) {
				$data   = (array) $list->get_error_data();
				$wallet = new WP_Error( 'partner' === ( $data['refused'] ?? '' ) ? 'frenet_labels_partner_token' : 'frenet_labels_list', $list->get_error_message() );
			}
		}
		if ( is_wp_error( $wallet ) ) {
			switch ( $wallet->get_error_code() ) {
				case 'frenet_labels_store_token':
					$errors = array( 'token' => __( 'Frenet did not recognise this token. Copy it again from the Frenet panel > Integrations.', 'woo-shipping-gateway' ) );
					break;
				case 'frenet_labels_partner_token':
					$errors = array( 'partner_token' => __( 'Frenet did not accept this partner token. Paste it again or check it with Frenet.', 'woo-shipping-gateway' ) );
					// A refused token typed now must not turn the label screens on: keep the one that was there. The store
					// token did answer, so the account stays connected for everything that needs only that one.
					if ( '' !== $partner ) {
						WC_Frenet_Labels_Settings::update(
							array(
								'partner_token' => $before,
								'setup_account' => 'yes',
							)
						);
					}
					self::flash( 'error', __( 'Frenet did not accept this partner token, so it was not saved. The store token works: quotes, tracking and the balance keep working.', 'woo-shipping-gateway' ), $errors, array( 'token' => $token ) );
					return 'account';
			}
			$message = $errors
				? __( 'Frenet did not accept the tokens. Nothing was saved as connected.', 'woo-shipping-gateway' )
				/* translators: %s: Frenet answer */
				: sprintf( __( 'Could not check the account with Frenet: %s Try again in a minute.', 'woo-shipping-gateway' ), $wallet->get_error_message() );
			self::flash( 'error', $message, $errors, array( 'token' => $token ) );
			return 'account';
		}
		WC_Frenet_Labels_Settings::update( array( 'setup_account' => 'yes' ) );
		$balance = WC_Frenet_Labels_Service::money( $wallet['balance'] );
		self::flash(
			'success',
			$labels
				/* translators: %s: wallet balance, e.g. R$ 10,00 */
				? sprintf( __( 'Account connected. Wallet balance: %s.', 'woo-shipping-gateway' ), $balance )
				/* translators: %s: wallet balance, e.g. R$ 10,00 */
				: sprintf( __( 'Account connected in basic mode. Wallet balance: %s.', 'woo-shipping-gateway' ), $balance )
		);
		return self::current_after( 'account' );
	}

	/**
	 * Step 2: saves the sender as typed and marks it confirmed only when Frenet would accept it.
	 *
	 * @param array<string, mixed> $input Form values.
	 * @return string Next step.
	 */
	private static function save_sender( array $input ) {
		$values = WC_Frenet_Labels_Settings::sender_input( $input );
		$typed  = array();
		foreach ( WC_Frenet_Labels_Payload::SENDER_FIELDS as $key ) {
			$typed[ $key ] = sanitize_text_field( (string) ( $input[ 'sender_' . $key ] ?? '' ) );
		}
		$overrides = array();
		foreach ( WC_Frenet_Labels_Payload::SENDER_FIELDS as $key ) {
			$overrides[ $key ] = $values[ 'sender_' . $key ];
		}
		$errors = self::sender_errors( WC_Frenet_Labels_Payload::merge_sender( WC_Frenet_Labels_Payload::store_sender(), $overrides ) );
		if ( $errors ) {
			// Nothing is saved: the form comes back with what was typed and each problem next to its field.
			self::flash( 'error', __( 'Check the highlighted fields.', 'woo-shipping-gateway' ), $errors, $typed );
			return 'sender';
		}
		WC_Frenet_Labels_Settings::update( array_merge( $values, array( 'setup_sender' => 'yes' ) ) );
		self::flash( 'success', __( 'Sender confirmed. It goes on every label.', 'woo-shipping-gateway' ) );
		return self::current_after( 'sender' );
	}

	/**
	 * First step not done after the given one ('done' when all are).
	 *
	 * @param string $step Step just saved.
	 * @return string
	 */
	private static function current_after( $step ) {
		$steps = self::steps();
		$after = array_slice( $steps, (int) array_search( $step, $steps, true ) + 1 );
		foreach ( array_merge( $after, $steps ) as $next ) {
			if ( ! self::done( $next ) ) {
				return $next;
			}
		}
		return 'done';
	}

	/**
	 * AJAX: address of the CEP typed in the sender step (works before the checkout autofill is switched on).
	 *
	 * @return void
	 */
	public static function ajax_cep() {
		if ( ! current_user_can( WC_Frenet_Labels_Admin::CAP ) || ! check_ajax_referer( WC_Frenet_Labels_Admin::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page.', 'woo-shipping-gateway' ) ), 403 );
		}
		$address = class_exists( 'WC_Frenet_Autofill' ) ? WC_Frenet_Autofill::lookup( isset( $_POST['cep'] ) ? sanitize_text_field( wp_unslash( $_POST['cep'] ) ) : '' ) : new WP_Error( 'frenet_cep', '' );
		if ( is_wp_error( $address ) ) {
			wp_send_json_error( array( 'message' => $address->get_error_message() ) );
		}
		wp_send_json_success( $address );
	}

	/* ------------------------------------------------------------------ flash messages */

	/**
	 * Keeps a message (and the field errors and typed values) for the next page load of this user.
	 *
	 * @param string                $type    success|error.
	 * @param string                $message Message.
	 * @param array<string, string> $errors  Field → message.
	 * @param array<string, string> $input   Typed values to show again.
	 * @return void
	 */
	private static function flash( $type, $message, array $errors = array(), array $input = array() ) {
		set_transient( self::FLASH . get_current_user_id(), compact( 'type', 'message', 'errors', 'input' ), MINUTE_IN_SECONDS );
	}

	/**
	 * Reads and clears the message of the last request.
	 *
	 * @return array{type: string, message: string, errors: array<string, string>, input: array<string, string>}
	 */
	public static function take_flash() {
		$key   = self::FLASH . get_current_user_id();
		$flash = get_transient( $key );
		delete_transient( $key );
		return array_merge(
			array(
				'type'    => '',
				'message' => '',
				'errors'  => array(),
				'input'   => array(),
			),
			is_array( $flash ) ? $flash : array()
		);
	}
}
