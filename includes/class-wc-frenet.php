<?php
/**
 * WC_Frenet class.
 */
class WC_Frenet extends WC_Shipping_Method {

    /**
     * Allowed range for the "timeout" setting, in seconds.
     */
    const MIN_TIMEOUT = 2;
    const MAX_TIMEOUT = 10;

    /**
     * Allowed range for the "attempts" setting: how many times a quote request is tried
     * (1 = a single try, no retry) before giving up and treating it as a failure.
     */
    const MIN_ATTEMPTS = 1;
    const MAX_ATTEMPTS = 3;

    /**
     * WC session key: whether the last live quote attempt failed. Read by
     * WC_Frenet_Main::block_checkout_if_frenet_quote_failed().
     */
    const SESSION_KEY_QUOTE_FAILED = 'frenet_last_quote_failed';

    protected $zip_origin;
    protected $minimum_height;
    protected $minimum_width;
    protected $minimum_length;
    protected $debug;
    protected $display_date;
    protected $additional_time;
    protected $token;
    protected $timeout;
    protected $attempts;
    protected $log;
    public $quoteByProduct = false;

    /**
     * @var string
     */
    protected $urlShipQuote = 'http://api.frenet.com.br/shipping/quote';

	/**
	 * Initialize the Frenet shipping method.
	 *
	 * @return void
	 */
	public function __construct($instance_id = 0 ) {
        $this->id           = 'frenet';
        $this->instance_id 	= absint( $instance_id );
		$this->method_title = __( 'Frenet', 'woo-shipping-gateway' );

        $this->supports              = array(
            'shipping-zones',
            'instance-settings'
        );

		$this->init();
	}

	/**
	 * Convert class to string.
	 *
	 * @return string Class ID.
	 */
	public function __toString()
	{
	    return 'WC_Frenet::' . $this->id . '::' . $this->instance_id . '::' . $this->method_title;
	}

	/**
	 * Initializes the method.
	 *
	 * @return void
	 */
	public function init() {
		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->enabled            = $this->get_option('enabled');
		$this->title              = $this->get_option('title');
		$this->zip_origin         = $this->get_option('zip_origin');
		$this->minimum_height     = $this->get_option('minimum_height');
		$this->minimum_width      = $this->get_option('minimum_width');
		$this->minimum_length     = $this->get_option('minimum_length');
		$this->debug              = $this->get_option('debug');
        $this->display_date       = $this->get_option('display_date');
        $this->additional_time    = $this->get_option('additional_time');
        $this->debug              = $this->get_option( 'debug' );
        $this->token              = $this->get_option('token');
        $this->timeout            = $this->get_timeout_option();
        $this->attempts           = $this->get_attempts_option();

		// Active logs.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = $this->woocommerce_method()->logger();
			}
		}

		// Actions.
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Reads the "timeout" setting, clamped to the supported range.
	 *
	 * @return int
	 */
	protected function get_timeout_option() {
		$timeout = (int) $this->get_option( 'timeout', 5 );

		if ( $timeout < self::MIN_TIMEOUT ) {
			$timeout = self::MIN_TIMEOUT;
		} elseif ( $timeout > self::MAX_TIMEOUT ) {
			$timeout = self::MAX_TIMEOUT;
		}

		return $timeout;
	}

	/**
	 * Reads the "attempts" setting, clamped to the supported range.
	 *
	 * @return int
	 */
	protected function get_attempts_option() {
		$attempts = (int) $this->get_option( 'attempts', 3 );

		if ( $attempts < self::MIN_ATTEMPTS ) {
			$attempts = self::MIN_ATTEMPTS;
		} elseif ( $attempts > self::MAX_ATTEMPTS ) {
			$attempts = self::MAX_ATTEMPTS;
		}

		return $attempts;
	}

	/**
	 * Backwards compatibility with version prior to 2.1.
	 *
	 * @return object Returns the main instance of WooCommerce class.
	 */
	protected function woocommerce_method() {
		if ( function_exists( 'WC' ) ) {
			return WC();
		} else {
			global $woocommerce;
			return $woocommerce;
		}
	}

	/**
	 * Admin options fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'enabled' => array(
				'title'            => __( 'Enable/Disable', 'woo-shipping-gateway' ),
				'type'             => 'checkbox',
				'label'            => __( 'Enable this shipping method', 'woo-shipping-gateway' ),
				'default'          => 'yes'
			),
			'title' => array(
				'title'            => __( 'Title', 'woo-shipping-gateway' ),
				'type'             => 'text',
				'description'      => __( 'This controls the title which the user sees during checkout.', 'woo-shipping-gateway' ),
				'desc_tip'         => true,
				'default'          => __( 'Frenet', 'woo-shipping-gateway' )
			),
            'zip_origin' => array(
                'title'            => __( 'Origin Zip Code', 'woo-shipping-gateway' ),
                'type'             => 'text',
                'description'      => __( 'Zip Code from where the requests are sent.', 'woo-shipping-gateway' ),
                'desc_tip'         => true
            ),
            'shipping_class_id'  => array(
                'title'       => __( 'Shipping Class', 'woo-shipping-gateway' ),
                'type'        => 'select',
                'description' => __( 'Select a shipping class to use with this method.', 'woo-shipping-gateway' ),
                'desc_tip'    => true,
                'default'     => '',
                'options'     => $this->get_shipping_classes(),
            ),
            'simulator' => array(
                'title' => __('Shipping Simulator', 'woo-shipping-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable', 'woo-shipping-gateway'),
                'description' => __('Display shipping simulator in single product', 'woo-shipping-gateway'),
                'desc_tip' => true,
                'default' => 'yes'
            ),
            'display_date' => array(
                'title'            => __( 'Estimated delivery', 'woo-shipping-gateway' ),
                'type'             => 'checkbox',
                'label'            => __( 'Enable', 'woo-shipping-gateway' ),
                'description'      => __( 'Display date of estimated delivery.', 'woo-shipping-gateway' ),
                'desc_tip'         => true,
                'default'          => 'yes'
            ),
            'additional_time' => array(
                'title'            => __( 'Additional days', 'woo-shipping-gateway' ),
                'type'             => 'text',
                'description'      => __( 'Additional days to the estimated delivery.', 'woo-shipping-gateway' ),
                'desc_tip'         => true,
                'default'          => '0',
                'placeholder'      => '0'
            ),
            'token' => array(
                'title'            => __( 'Token', 'woo-shipping-gateway' ),
                'type'             => 'password',
                'description'      => __( 'Your Frenet token.', 'woo-shipping-gateway' ),
                'desc_tip'         => true
            ),
            'timeout' => array(
                'title'             => __( 'Request Timeout (seconds)', 'woo-shipping-gateway' ),
                'type'              => 'number',
                'description'       => __( 'How long to wait for the Frenet API to respond before giving up and retrying. Recommended: between 2 and 10 seconds.', 'woo-shipping-gateway' ),
                'desc_tip'          => true,
                'default'           => '5',
                'custom_attributes' => array(
                    'min'  => self::MIN_TIMEOUT,
                    'max'  => self::MAX_TIMEOUT,
                    'step' => 1,
                ),
            ),
            'attempts' => array(
                'title'             => __( 'Request Attempts', 'woo-shipping-gateway' ),
                'type'              => 'number',
                'description'       => __( 'How many times to try the Frenet API before giving up (1 = no retry). Each attempt can take up to the Request Timeout above, so the worst case is Attempts × Timeout.', 'woo-shipping-gateway' ),
                'desc_tip'          => true,
                'default'           => '3',
                'custom_attributes' => array(
                    'min'  => self::MIN_ATTEMPTS,
                    'max'  => self::MAX_ATTEMPTS,
                    'step' => 1,
                ),
            ),
			'package_standard' => array(
				'title'            => __( 'Package Standard', 'woo-shipping-gateway' ),
				'type'             => 'title',
				'description'      => __( 'Sets a minimum measure for the package.', 'woo-shipping-gateway' ),
				'desc_tip'         => true,
			),
			'minimum_height' => array(
				'title'            => __( 'Minimum Height', 'woo-shipping-gateway' ),
				'type'             => 'text',
				'description'      => __( 'Minimum height of the package. Frenet needs at least 2 cm.', 'woo-shipping-gateway' ),
				'desc_tip'         => true,
				'default'          => '2'
			),
			'minimum_width' => array(
				'title'            => __( 'Minimum Width', 'woo-shipping-gateway' ),
				'type'             => 'text',
				'description'      => __( 'Minimum width of the package. Frenet needs at least 11 cm.', 'woo-shipping-gateway' ),
				'desc_tip'         => true,
				'default'          => '11'
			),
			'minimum_length' => array(
				'title'            => __( 'Minimum Length', 'woo-shipping-gateway' ),
				'type'             => 'text',
				'description'      => __( 'Minimum length of the package. Frenet needs at least 16 cm.', 'woo-shipping-gateway' ),
				'desc_tip'         => true,
				'default'          => '16'
			),
			'testing' => array(
				'title'            => __( 'Testing', 'woo-shipping-gateway' ),
				'type'             => 'title'
			),
			'debug' => array(
				'title'            => __( 'Debug Log', 'woo-shipping-gateway' ),
				'type'             => 'checkbox',
				'label'            => __( 'Enable logging', 'woo-shipping-gateway' ),
				'default'          => 'no',
				'description'      => sprintf( __( 'Log Frenet events, such as WebServices requests, inside %s. Quote failures are always logged to the WooCommerce log regardless of this setting.', 'woo-shipping-gateway' ), '<code>woocommerce/logs/frenet-' . sanitize_file_name( wp_hash( 'frenet' ) ) . '.txt</code>' )
			)
		);

        $this->form_fields = $this->instance_form_fields;
	}

	/**
	 * Validates the "timeout" field, clamping it to the supported range.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted value.
	 * @return string
	 */
	public function validate_timeout_field( $key, $value ) {
		$original = (int) $value;
		$clamped  = $original;

		if ( $clamped < self::MIN_TIMEOUT ) {
			$clamped = self::MIN_TIMEOUT;
		} elseif ( $clamped > self::MAX_TIMEOUT ) {
			$clamped = self::MAX_TIMEOUT;
		}

		if ( $clamped !== $original ) {
			$this->add_error( sprintf(
				/* translators: 1: submitted value, 2: minimum allowed, 3: maximum allowed, 4: value actually saved */
				__( 'Frenet: Request Timeout must be between %2$d and %3$d seconds. %1$d was adjusted to %4$d.', 'woo-shipping-gateway' ),
				$original,
				self::MIN_TIMEOUT,
				self::MAX_TIMEOUT,
				$clamped
			) );
		}

		return (string) $clamped;
	}

	/**
	 * Validates the "attempts" field, clamping it to the supported range.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted value.
	 * @return string
	 */
	public function validate_attempts_field( $key, $value ) {
		$original = (int) $value;
		$clamped  = $original;

		if ( $clamped < self::MIN_ATTEMPTS ) {
			$clamped = self::MIN_ATTEMPTS;
		} elseif ( $clamped > self::MAX_ATTEMPTS ) {
			$clamped = self::MAX_ATTEMPTS;
		}

		if ( $clamped !== $original ) {
			$this->add_error( sprintf(
				/* translators: 1: submitted value, 2: minimum allowed, 3: maximum allowed, 4: value actually saved */
				__( 'Frenet: Request Attempts must be between %2$d and %3$d. %1$d was adjusted to %4$d.', 'woo-shipping-gateway' ),
				$original,
				self::MIN_ATTEMPTS,
				self::MAX_ATTEMPTS,
				$clamped
			) );
		}

		return (string) $clamped;
	}

	/**
	 * Frenet options page.
	 *
	 * @return void
	 */
    public function admin_options()
    {
        $html = '<h3>' . esc_html($this->method_title) . '</h3>';
        $html .= '<p>' . __( esc_html('Frenet is a brazilian delivery method.'), 'woo-shipping-gateway' ) . '</p>';
        $html .= '<table class="form-table">';
        echo $html;
        $this->generate_settings_html();
        $html = '</table>';
        echo $html;
	}

	/**
	 * Checks if the method is available.
	 *
	 * @param array $package Order package.
	 *
	 * @return bool
	 */
	public function is_available( $package ) {
		$is_available = true;

		if ( 'no' == $this->enabled ) {
			$is_available = false;
		}

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this );
	}

	/**
	 * Replace comma by dot.
	 *
	 * @param  mixed $value Value to fix.
	 *
	 * @return mixed
	 */
	private function fix_format( $value ) {
		$value = str_replace( ',', '.', $value );

		return $value;
	}

	/**
	 * Fix number format for SimpleXML.
	 *
	 * @param  float $value  Value with dot.
	 *
	 * @return string        Value with comma.
	 */
	private function fix_simplexml_format( $value ) {
		$value = str_replace( '.', ',', $value );

		return $value;
	}

	/**
	 * Fix Zip Code format.
	 *
	 * @param mixed $zip Zip Code.
	 *
	 * @return int
	 */
	protected function fix_zip_code( $zip ) {
		$fixed = preg_replace( '([^0-9])', '', $zip );

		return $fixed;
	}

	/**
	 * Get fee.
	 *
	 * @param  mixed $fee
	 * @param  mixed $total
	 *
	 * @return float
	 */
	public function get_fee( $fee, $total ) {
		if ( strstr( $fee, '%' ) ) {
			$fee = ( $total / 100 ) * str_replace( '%', '', $fee );
		}

		return $fee;
	}

	/**
	 * Calculates the shipping rate.
	 *
	 * @param array $package Order package.
	 *
	 * @return void
	 */
	public function calculate_shipping( $package = [] ) {
		$rates  = [];
        $errors = [];

        $shipping_values = $this->frenet_calculate($package);

        if (!$this->has_shipping_class($package)) {
            return;
        }

        if ( ! empty( $shipping_values ) ) {
            foreach ( $shipping_values as $code => $shipping ) {

                if (!isset($shipping->ShippingPrice)) {
                    continue;
                }

                $label='';
                $date=0;
                if (isset($shipping->ServiceDescription) ) {
                    $label=$shipping->ServiceDescription;
                }

                if (isset($shipping->DeliveryTime)) {
                    $date=$shipping->DeliveryTime;
                }

                $label = ( 'yes' === $this->display_date )
                    ? $this->estimating_delivery( $label, $date, $this->additional_time )
                    : $label;
                $cost  = (float) str_replace(",", ".", (string) $shipping->ShippingPrice);

                $rates[] = array(
                    'id' => 'FRENET_' . $shipping->ServiceCode,
                    'label' => $label,
                    'cost' => $cost,
                    'meta_data' => array('FRENET_ID' => 'FRENET_' . $shipping->ServiceCode)
                );
            }

            foreach ( $rates as $rate ) {
                $this->add_rate( $rate );
            }
        }
	}

    /**
     * Estimating Delivery.
     *
     * @param string $label
     * @param string $date
     * @param int    $additional_time
     *
     * @return string
     */
    protected function estimating_delivery( $label, $date, $additional_time = 0 ) {
        $name = $label;
        $additional_time = intval( $additional_time );

        if ( $additional_time > 0 ) {
            $date += intval( $additional_time );
        }

        if ( $date > 0 ) {
            $name .= ' (' . sprintf( _n( 'Delivery in %d working day', 'Delivery in %d working days', $date, 'woo-shipping-gateway' ),  $date ) . ')';
        }

        return $name;
    }

    /***
     * Getting the coupom
     */
    protected function get_coupom($package) {
        $coupom = "";
        if (in_array( "applied_coupons", array_keys( $package ) ) && count($package["applied_coupons"]) > 0) {
            $coupom = $package["applied_coupons"][0];
        }
        return $coupom;
    }

    /**
     * Calculate shipping at frenet
     * @param array $package
     * @return array
     */
    protected function frenet_calculate( $package ){

        $values = array();
        $context = $this->describe_package( $package );

        try {
            $RecipientCEP = $package['destination']['postcode'];
            $RecipientCountry = $package['destination']['country'];

            // Checks if services and zipcode is empty.
            if (empty( $RecipientCEP ) && $RecipientCountry =='BR') {
                $this->log_error( "Frenet quote skipped: destination postcode (RecipientCEP) is empty. [{$context}]" );
                return $values;
            }

            if (empty( $this->zip_origin )) {
                $this->log_error( "Frenet quote skipped: origin postcode (zip_origin) is not configured on the shipping method. [{$context}]" );
                return $values;
            }

            $coupom = $this->get_coupom($package);

            // product array
            $shippingItemArray = array();
            $count = 0;

            $shipmentInvoiceValue=0;

            // Shipping per item.
            foreach ( $package['contents'] as $cart_item ) {

                $product = $cart_item['data'];
                $qty = $cart_item['quantity'];
                if (!is_numeric($qty)) {
                    $this->log('there is a package configuration mistake in store, numeric expected, but string found '.$qty);
                    $qty = 0;
                }

                if ( 'yes' == $this->debug ) {
                    $this->log->add( $this->id, 'Product: ' . print_r($product, true));
                }

                $shippingItem = new stdClass();

                if ( $qty > 0 && $product->needs_shipping() ) {

                    if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '>=' ) ) {
                        $_height =  wc_get_dimension( $this->fix_format( $product->get_height() ), 'cm' );
                        $_width  = wc_get_dimension( $this->fix_format( $product->get_width() ), 'cm' );
                        $_length = wc_get_dimension( $this->fix_format( $product->get_length() ), 'cm' );
                        $_weight = wc_get_weight( $this->fix_format( $product->get_weight() ), 'kg' );
                    }
                    else if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
                        $_height =  wc_get_dimension( $this->fix_format( $product->height ), 'cm' );
                        $_width  = wc_get_dimension( $this->fix_format( $product->width ), 'cm' );
                        $_length = wc_get_dimension( $this->fix_format( $product->length ), 'cm' );
                        $_weight = wc_get_weight( $this->fix_format( $product->weight ), 'kg' );
                    } else {
                        $_height = woocommerce_get_dimension( $this->fix_format( $product->height ), 'cm' );
                        $_width  = woocommerce_get_dimension( $this->fix_format( $product->width ), 'cm' );
                        $_length = woocommerce_get_dimension( $this->fix_format( $product->length ), 'cm' );
                        $_weight = woocommerce_get_weight( $this->fix_format( $product->weight ), 'kg' );
                    }

                    if(empty($_height))
                        $_height= $this->minimum_height;

                    if(empty($_width))
                        $_width= $this->minimum_width;

                    if(empty($_length))
                        $_length = $this->minimum_length;

                    if(empty($_weight))
                        $_weight = 1;

                    $shippingItem->Weight = $_weight;
                    $shippingItem->Length = $_length;
                    $shippingItem->Height = $_height;
                    $shippingItem->Width = $_width;
                    $shippingItem->Diameter = 0;
                    $shippingItem->SKU = $product->get_sku();
                    $price = $product->get_price();

                    if (!is_numeric($price)) {
                        $this->log('there is a package price configuration mistake in store, numeric expected, but string found '.$price);
                        $price = 0.0;
                    }

                    $shipmentInvoiceValue += $price * $qty;

                    // wp_get_post_terms( your_id, 'product_cat' );
                    if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '>=' ) ) {

                        if( $product->get_parent_id() ){
                            $terms = wp_get_post_terms($product->get_parent_id(), 'product_cat');
                        }else{
                            $terms = wp_get_post_terms($product->get_id(), 'product_cat');
                        }

                    } else {

                        if( $product->parent_id ){
                            $terms = wp_get_post_terms($product->parent_id, 'product_cat');
                        }else{
                            $terms = wp_get_post_terms($product->id, 'product_cat');
                        }

                    }

					$categories = '';
                    foreach ($terms as $term) {
                        $categories =  $categories . $term->slug . '|';
                    }

                    $shippingItem->Category = $categories;
                    $shippingItem->isFragile=false;

                    if ( 'yes' == $this->debug ) {
                        $this->log->add( $this->id, 'shippingItem: ' . print_r($shippingItem, true));
                    }

                    $shippingItem->Quantity = $qty;
                    $shippingItemArray[$count] = $shippingItem;
                    $count++;
                }
            }

            // No shippable items (e.g. cart has only virtual/downloadable products) — skip
            // the request instead of sending an empty payload.
            if ( empty( $shippingItemArray ) ) {
                $this->log_error( "Frenet quote skipped: the shipping payload has no items (ShippingItemArray is empty). Check that the cart has shippable products with weight/dimensions set. [{$context}]" );
                return $values;
            }

            if ( 'yes' == $this->debug ) {
                $this->log->add( $this->id, 'CEP ' . $package['destination']['postcode'] );
            }

            if(!$this->quoteByProduct) {
                $shipmentInvoiceValue = WC()->cart->cart_contents_total;
            }

            $serviceParam = array(
                'Token' => $this->token,
                'Coupom' => $coupom,
                'PlatformName' => 'WOOCOMMERCE',// Identificar que está foi uma chamada do woocommerce
                'PlatformVersion' => WOOCOMMERCE_VERSION,// Identificar que está foi uma chamada do woocommerce
                'SellerCEP' => $this->zip_origin,
                'RecipientCEP' => $RecipientCEP,
                'RecipientDocument' => '',
                'ShipmentInvoiceValue' => $shipmentInvoiceValue,
                'ShippingItemArray' => $shippingItemArray,
                'RecipientCountry' => $RecipientCountry
            );
            $values = $this->requestJson($serviceParam, $context);
        } catch ( Throwable $e ) {
            // Catches Throwable, not just Exception: a TypeError or other Error must not turn
            // into a fatal error / white screen on the cart or checkout page.
            $this->log_error( 'Frenet quote raised an unexpected exception: ' . $e->getMessage() . " [{$context}]" );
            $this->mark_quote_result( true );
        }

        return $values;

    }

    /**
     * @return array
     */
    protected function get_shipping_classes() {
        $shipping_classes = WC()->shipping->get_shipping_classes();
        $options          = array(
            '-1' => __( 'Any Shipping Class', 'woo-shipping-gateway' ),
            '0'  => __( 'No Shipping Class', 'woo-shipping-gateway' ),
        );

        if (!empty($shipping_classes)) {
            $options += wp_list_pluck($shipping_classes, 'name', 'term_id');
        }

        return $options;
    }

    /**
     * @param  array $package
     * @return bool
     */
    protected function has_shipping_class($package) {
        $same_class = true;
        $class_id = $this->get_option('shipping_class_id');

        if ($class_id === '' || (int) $class_id === -1) {
            return $same_class;
        }

        $class_id = (int) $class_id;

        foreach ($package['contents'] as $item) {
            $product  = $item['data'];
            $quantity = $item['quantity'];

            if (($quantity > 0 && $product->needs_shipping()) && $class_id !== (int)$product->get_shipping_class_id()) {
                $same_class = false;
                break;
            }
        }

        return $same_class;
    }

    /**
     * Log message
     *
     * @param string $mensage
     * @return void
     */
    protected function log($menssage) {
        if ( 'yes' == $this->debug ) {
            $this->log->add( $this->id, $menssage);
        }
    }

    /**
     * Request Json
     *
     * @param array  $serviceParam
     * @param string $context Identifies which cart/session this quote is for, for logging.
     * @return array Usable carrier services keyed by ServiceCode; empty when the quote failed.
     */
    protected function requestJson(array $serviceParam, $context = '')
    {
        $this->log('Requesting the Frenet WebServices...');
        $this->log(print_r($serviceParam, true));
        $this->log('URL: ' . $this->urlShipQuote);

        $values   = array();
        $response = $this->request_quote_with_retry($serviceParam, $context);

        // Every attempt failed at the transport level (already logged by the retry helper).
        if ( null === $response ) {
            $this->mark_quote_result( true );
            return $values;
        }

        $services = isset( $response->ShippingSevicesArray ) ? (array) $response->ShippingSevicesArray : array();

        foreach ( $services as $service ) {
            if ( ! empty( $service->Error ) ) {
                $msg = ( isset( $service->Msg ) && '' !== $service->Msg ) ? $service->Msg : '(no message)';
                $this->log_error( "Frenet carrier returned an error: {$msg} [{$context}]" );
                continue;
            }

            if ( ! isset( $service->ServiceCode ) || '' === (string) $service->ServiceCode || ! isset( $service->ShippingPrice ) ) {
                $this->log( '*continue* (service without ServiceCode or ShippingPrice)' );
                continue;
            }

            $this->log( 'WebServices response [' . ( isset( $service->ServiceDescription ) ? $service->ServiceDescription : '' ) . ']: ' . print_r( $service, true ) );
            $values[ (string) $service->ServiceCode ] = $service;
        }

        // HTTP 200 but nothing usable came back: empty ShippingSevicesArray, or every carrier
        // reported an error. Treat it as a failed quote so the result is not cached by
        // WooCommerce and, when Frenet is the chosen method, checkout stays blocked.
        if ( empty( $values ) ) {
            $this->log_error( "Frenet quote returned HTTP 200 with no usable carrier (empty or all-error ShippingSevicesArray). [{$context}]" );
            $this->mark_quote_result( true );
            return $values;
        }

        $this->mark_quote_result( false );
        return $values;
    }

    /**
     * Requests the Frenet quote with the configured timeout, retrying on failure.
     *
     * @param array  $serviceParam
     * @param string $context For logging: identifies which cart/session this quote is for.
     * @return \stdClass|null Decoded response on success, null once every attempt failed.
     */
    protected function request_quote_with_retry( array $serviceParam, $context = '' ) {
        $last_error    = '';
        $attempts_made = 0;

        for ( $attempt = 1; $attempt <= $this->attempts; $attempt++ ) {
            $attempts_made = $attempt;

            try {
                $curlResponse = wp_remote_post(
                    $this->urlShipQuote,
                    array(
                        'timeout' => $this->timeout,
                        'body'    => wp_json_encode( $serviceParam ),
                        'headers' => array(
                            'Content-Type' => 'application/json',
                            'token'        => $this->token,
                        ),
                    )
                );

                if ( is_wp_error( $curlResponse ) ) {
                    throw new Exception( $curlResponse->get_error_message() );
                }

                $status_code = (int) wp_remote_retrieve_response_code( $curlResponse );

                if ( $status_code >= 400 && $status_code < 500 ) {
                    // Client-side error (bad token, malformed request...) — retrying the exact
                    // same request won't change the outcome, so stop instead of burning the
                    // full retry budget on a request that can never succeed.
                    $last_error = "Unexpected HTTP status {$status_code} (client error, request not retried)";
                    break;
                }

                if ( $status_code < 200 || $status_code >= 300 ) {
                    throw new Exception( "Unexpected HTTP status {$status_code}" );
                }

                $content_type = wp_remote_retrieve_header( $curlResponse, 'content-type' );
                if ( $content_type && ! str_contains( (string) $content_type, 'application/json' ) ) {
                    throw new Exception( 'Unexpected Content-Type: ' . $content_type );
                }

                $body    = wp_remote_retrieve_body( $curlResponse );
                $decoded = json_decode( $body );

                if ( JSON_ERROR_NONE !== json_last_error() || null === $decoded ) {
                    throw new Exception( 'Invalid JSON response: ' . json_last_error_msg() );
                }

                $this->log( 'Curl response: ' . $body );

                return $decoded;
            } catch ( Throwable $e ) {
                $last_error = $e->getMessage();
                $this->log_error( "Frenet quote request failed (attempt {$attempt}/" . $this->attempts . "): {$last_error} [{$context}]" );

                if ( $attempt < $this->attempts ) {
                    usleep( 300000 ); // 300ms backoff before retrying.
                }
            }
        }

        $attempt_word = ( 1 === $attempts_made ) ? 'attempt' : 'attempts';
        $this->log_error( "Frenet quote request gave up after {$attempts_made} {$attempt_word}. Last error: {$last_error} [{$context}]" );

        return null;
    }


    /**
     * Records in the WC session whether the last live quote attempt failed.
     *
     * @param bool $failed
     * @return void
     */
    protected function mark_quote_result( $failed ) {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        $was_already_failed = (bool) WC()->session->get( self::SESSION_KEY_QUOTE_FAILED );

        WC()->session->set( self::SESSION_KEY_QUOTE_FAILED, (bool) $failed );

        // Inform the shopper, but as a non-error notice: an "error" notice makes the Store
        // API reject the whole checkout (HTTP 409), which would block the order even when
        // another carrier is selected. Blocking checkout while Frenet is the chosen method
        // is done separately by WC_Frenet_Main::block_checkout_if_frenet_quote_failed().
        // Only queued on the transition into a failed state, so repeatedly reloading the
        // same broken cart page does not stack duplicate notices.
        if ( $failed && ! $was_already_failed && function_exists( 'wc_add_notice' ) ) {
            wc_add_notice(
                __( 'Frenet shipping quote is currently unavailable. Refresh the page or re-enter the zip code to try again, or select another shipping method.', 'woo-shipping-gateway' ),
                'notice'
            );
        }
    }

    /**
     * Builds a compact "which cart" identifier (session, user, postcode, items) for logs.
     *
     * @param array $package
     * @return string
     */
    protected function describe_package( $package ) {
        $parts = array( 'instance=' . $this->instance_id );

        if ( function_exists( 'WC' ) && WC()->session ) {
            $parts[] = 'session=' . WC()->session->get_customer_id();
        }

        $customer_id = get_current_user_id();
        if ( $customer_id ) {
            $parts[] = 'user=' . $customer_id;
        }

        if ( ! empty( $package['destination']['postcode'] ) ) {
            $parts[] = 'postcode=' . $package['destination']['postcode'];
        }

        $items = array();
        foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
            $product = isset( $item['data'] ) ? $item['data'] : null;
            $id      = ( $product && is_object( $product ) && method_exists( $product, 'get_id' ) ) ? $product->get_id() : '?';
            $qty     = isset( $item['quantity'] ) ? $item['quantity'] : '?';
            $items[] = "{$id}x{$qty}";
        }
        $parts[] = 'items=' . ( $items ? implode( ',', $items ) : '(none)' );

        return implode( ' | ', $parts );
    }

    /**
     * Logs an error unconditionally, independent of the Debug Log setting.
     *
     * @param string $message
     * @return void
     */
    protected function log_error( $message ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->error( $message, array( 'source' => $this->id ) );
        }

        $this->log( $message );
    }

    /**
     * Safe load XML.
     *
     * @param  string $source
     * @param  int    $options
     *
     * @return SimpleXMLElement|bool
     */
    protected function safe_load_xml( $source, $options = 0 ) {
        $old = null;

        /**
         *  OLD
         */
        if ( function_exists( 'libxml_disable_entity_loader' ) ) {
            $old = libxml_disable_entity_loader( true );
        }

        $dom    = new DOMDocument();

        $return = $dom->loadXML( $source, $options );

        /**
         *  OLD
         */
        if ( ! is_null( $old ) ) {
            libxml_disable_entity_loader( $old );
        }

        if ( ! $return ) {
            return false;
        }

        if ( isset( $dom->doctype ) ) {
            throw new Exception( 'Unsafe DOCTYPE Detected while XML parsing' );

            return false;
        }

        return simplexml_import_dom( $dom );
    }
}
