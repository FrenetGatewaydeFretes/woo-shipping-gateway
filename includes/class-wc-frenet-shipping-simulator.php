<?php

/**
 * WC_Frenet class.
 */
class WC_Frenet_Shipping_Simulator extends WC_Frenet
{
    /**
     * Shipping simulator actions.
     */
    public function __construct()
    {

        add_action('wp_enqueue_scripts', array($this, 'scritps'));
        add_action('woocommerce_single_product_summary', array(__CLASS__, 'simulator'), 40);
    }

    /**
     * Shipping simulator scripts.
     *
     * @return void
     */
    public function scritps()
    {
        if (is_product()) {
            wp_enqueue_style('shipping-simulator', plugins_url('assets/css/simulator.css', plugin_dir_path(__FILE__)), array(), WC_Frenet_Main::VERSION, 'all');
            wp_enqueue_script('shipping-simulator', plugins_url('assets/js/simulator.js', plugin_dir_path(__FILE__)), array('jquery'), WC_Frenet_Main::VERSION, true);
            wp_localize_script(
                'shipping-simulator',
                'shipping_simulator',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'error_message' => __('Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor')
                )
            );
        }
    }


    /**
     * Display the simulator.
     *
     * @return string Simulator HTML.
     */
    public static function simulator()
    {
        global $product;

        if (!is_product()) {
            return;
        }

        if ('variable' == $product->product_type) {
            $style = 'display: none';
            $ids = array();

            foreach ($product->get_available_variations() as $variation) {
                $_variation = get_product($variation['variation_id']);
                $ids[] = $_variation->variation_id;
            }

            $ids = implode(',', array_filter($ids));
        } else {
            $style = '';
            $ids = $product->id;
        }

        if ($product->is_in_stock() && in_array($product->product_type, array('simple', 'variable'))) {
            wc_get_template('single-product/shipping-simulator.php', array(
                'product' => $product,
                'style'       => $style,
                'ids' => $ids,
            ), '', WC_Frenet_Main::get_templates_path());

        }
    }


    /**
     * Simulator ajax response.
     *
     * @return string
     */
    public function ajax_simulator()
    {
        $frenet = new WC_Frenet($_POST['instance_id']);

        $package = array();
        $package['destination']['postcode'] = $_POST['zipcode'];
        $package['destination']['country'] = 'BR';
        $variation = wc_get_product($_POST['variation_id']);

        if (false === $variation) {
            $variation = wc_get_product($_POST['product_id']);
        }

        $package['contents'][0]['data'] = $variation;
        $package['contents'][0]['quantity'] = $_POST['quantity'];;

        $frenet->quoteByProduct=true;
        $shipping_values = $frenet->frenet_calculate($package);

        echo json_encode($shipping_values);
        die;

    }
}

new WC_Frenet_Shipping_Simulator();