<?php
/**
 * WC_Frenet class.
 */
class WC_Frenet extends WC_Shipping_Method {

	/**
	 * Initialize the Frenet shipping method.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id           = 'frenet';
		$this->method_title = __( 'Frenet', 'woo-shipping-gateway' );
		$this->init();
	}

	/**
	 * Initializes the method.
	 *
	 * @return void
	 */
	public function init() {
		// Frenet Web Service.
		$this->webservice = 'http://services.frenet.com.br/logistics/ShippingQuoteWS.asmx?wsdl';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->enabled            = $this->settings['enabled'];
		$this->title              = $this->settings['title'];
		$this->zip_origin         = $this->settings['zip_origin'];
		$this->minimum_height     = $this->settings['minimum_height'];
		$this->minimum_width      = $this->settings['minimum_width'];
		$this->minimum_length     = $this->settings['minimum_length'];
		$this->debug              = $this->settings['debug'];
        $this->display_date       = $this->settings['display_date'];
        $this->login              = $this->settings['login'];
        $this->password           = $this->settings['password'];
        $this->additional_time    = $this->settings['additional_time'];

		// Active logs.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = $this->woocommerce_method()->logger();
			}
		}

		// Actions.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );
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
		$this->form_fields = array(
			'enabled' => array(
				'title'            => __( 'Enable/Disable', 'woo-shipping-gateway' ),
				'type'             => 'checkbox',
				'label'            => __( 'Enable this shipping method', 'woo-shipping-gateway' ),
				'default'          => 'no'
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
            'display_date' => array(
                'title'            => __( 'Estimated delivery', 'woo-shipping-gateway' ),
                'type'             => 'checkbox',
                'label'            => __( 'Enable', 'woo-shipping-gateway' ),
                'description'      => __( 'Display date of estimated delivery.', 'woo-shipping-gateway' ),
                'desc_tip'         => true,
                'default'          => 'no'
            ),
            'additional_time' => array(
                'title'            => __( 'Additional days', 'woo-shipping-gateway' ),
                'type'             => 'text',
                'description'      => __( 'Additional days to the estimated delivery.', 'woo-shipping-gateway' ),
                'desc_tip'         => true,
                'default'          => '0',
                'placeholder'      => '0'
            ),
            'login' => array(
                'title'            => __( 'User', 'woo-shipping-gateway' ),
                'type'             => 'text',
                'description'      => __( 'Your Frenet access key.', 'woo-shipping-gateway' ),
                'desc_tip'         => true
            ),
            'password' => array(
                'title'            => __( 'Password', 'woo-shipping-gateway' ),
                'type'             => 'password',
                'description'      => __( 'Your Frenet access key password.', 'woo-shipping-gateway' ),
                'desc_tip'         => true
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
				'description'      => sprintf( __( 'Log Frenet events, such as WebServices requests, inside %s.', 'woo-shipping-gateway' ), '<code>woocommerce/logs/frenet-' . sanitize_file_name( wp_hash( 'frenet' ) ) . '.txt</code>' )
			)
		);
	}

	/**
	 * Frenet options page.
	 *
	 * @return void
	 */
	public function admin_options() {
		echo '<h3>' . $this->method_title . '</h3>';
		echo '<p>' . __( 'Frenet is a brazilian delivery method.', 'woo-shipping-gateway' ) . '</p>';
		echo '<table class="form-table">';
			$this->generate_settings_html();
		echo '</table>';
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

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package );
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
	public function calculate_shipping( $package = array() ) {
		$rates  = array();
        $errors = array();
        $shipping_values = $this->frenet_calculate( $package );

        if ( ! empty( $shipping_values ) ) {
            foreach ( $shipping_values as $code => $shipping ) {

                if(!isset($shipping->ShippingPrice))
                    continue;

                // Set the shipping rates.
                $label='';
                $date=0;
                if(isset($shipping->ServiceDescription) )
                    $label=$shipping->ServiceDescription;

                if (isset($shipping->DeliveryTime))
                    $date=$shipping->DeliveryTime;

                $label = ( 'yes' == $this->display_date ) ? $this->estimating_delivery( $label, $date, $this->additional_time ) : $label;
                $cost  = floatval(str_replace(",", ".", (string) $shipping->ShippingPrice));

                array_push(
                    $rates,
                    array(
                        'id'    => $shipping->ServiceCode,
                        'label' => $label,
                        'cost'  => $cost,
                    )
                );
            }
            // Add rates.
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

    protected function frenet_calculate( $package ){
        $values = array();

        $RecipientCEP = $package['destination']['postcode'];

        // Checks if services and zipcode is empty.
        if (empty( $RecipientCEP )
            || empty( $this->zip_origin )
        ) {
            return $values;
        }

        // product array
        $shippingItemArray = array();
        $count = 0;

        // Shipping per item.
        foreach ( $package['contents'] as $item_id => $values ) {
            $product = $values['data'];
            $qty = $values['quantity'];

            if ( 'yes' == $this->debug ) {
                $this->log->add( $this->id, 'Product: ' . print_r($product, true));
            }

            $shippingItem = new stdClass();

            if ( $qty > 0 && $product->needs_shipping() ) {

                if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
                    $_height = wc_get_dimension( $this->fix_format( $product->height ), 'cm' );
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


                $shippingItem->Weight = $_weight * $qty;
                $shippingItem->Length = $_length;
                $shippingItem->Height = $_height;
                $shippingItem->Width = $_width;
                $shippingItem->Diameter = 0;
                $shippingItem->SKU = $product->get_sku();

                // wp_get_post_terms( your_id, 'product_cat' );
                $shippingItem->Category = '';
                $shippingItem->isFragile=false;

                if ( 'yes' == $this->debug ) {
                    $this->log->add( $this->id, 'shippingItem: ' . print_r($shippingItem, true));
                }

                $shippingItemArray[$count] = $shippingItem;
                $count++;
            }
        }

        if ( 'yes' == $this->debug ) {

            $this->log->add( $this->id, 'CEP ' . $package['destination']['postcode'] );
        }

        $service_param = array (
            'quoteRequest' => array(
                'Username' => $this->login,
                'Password' => $this->password,
                'SellerCEP' => $this->zip_origin,
                'RecipientCEP' => $RecipientCEP,
                'RecipientDocument' => '',
                'ShipmentInvoiceValue' => WC()->cart->cart_contents_total,
                'ShippingItemArray' => $shippingItemArray
            )
        );

        if ( 'yes' == $this->debug ) {
            $this->log->add( $this->id, 'Requesting the Frenet WebServices...');
            $this->log->add( $this->id, print_r($service_param, true));
        }

        // Gets the WebServices response.
        $client = new SoapClient($this->webservice, array("soap_version" => SOAP_1_1,"trace" => 1));
        $response = $client->__soapCall("GetShippingQuote", array($service_param));

        if ( 'yes' == $this->debug ) {
            $this->log->add( $this->id, $client->__getLastRequest());
            $this->log->add( $this->id, $client->__getLastResponse());
        }

        if ( is_wp_error( $response ) ) {
            if ( 'yes' == $this->debug ) {
                $this->log->add( $this->id, 'WP_Error: ' . $response->get_error_message() );
            }
        } else
        {
            if ( isset( $response->GetShippingQuoteResult ) ) {
                if(count($response->GetShippingQuoteResult->ShippingSevicesArray->ShippingSevices)==1)
                    $servicosArray[0] = $response->GetShippingQuoteResult->ShippingSevicesArray->ShippingSevices;
                else
                    $servicosArray = $response->GetShippingQuoteResult->ShippingSevicesArray->ShippingSevices;

                foreach($servicosArray as $servicos){

                    if ( 'yes' == $this->debug ) {
                        $this->log->add( $this->id, 'Percorrendo os serviços retornados');
                    }

                    if (!isset($servicos->ServiceCode) || $servicos->ServiceCode . '' == '' || !isset($servicos->ShippingPrice)) {
                        continue;
                    }

                    $code = (string) $servicos->ServiceCode;

                    if ( 'yes' == $this->debug ) {
                        $this->log->add( $this->id, 'WebServices response [' . $servicos->ServiceDescription . ']: ' . print_r( $servicos, true ) );
                    }

                    $values[ $code ] = $servicos;
                }

            }
        }

        return $values;

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

        if ( function_exists( 'libxml_disable_entity_loader' ) ) {
            $old = libxml_disable_entity_loader( true );
        }

        $dom    = new DOMDocument();

        $return = $dom->loadXML( $source, $options );

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
