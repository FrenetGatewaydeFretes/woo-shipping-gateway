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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_single_product_summary', array(__CLASS__, 'simulator'), 40);
    }

    /**
     * Shipping simulator scripts.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        if (!is_product()) {
            return;
        }

        $helper = new WC_Frenet_Helper;
        if (!$helper->is_simulator_enabled()) {
            return;
        }

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

        $helper = new WC_Frenet_Helper;
        if (!$helper->is_simulator_enabled()) {
            return;
        }

        if ('variable' == $product->get_type()) {
            $style = 'display: none';
            $ids = array();

            foreach ($product->get_available_variations() as $variation) {
                $_variation = self::getProduct($variation);
                $ids[] = $_variation->variation_id;
            }

            $ids = implode(',', array_filter($ids));
        } else {
            $style = '';
            $ids = $product->get_id();
        }

        if ($product->is_in_stock() && in_array($product->get_type(), array('simple', 'variable'))) {

            $options = $helper->get_options();
            $instance_id = $helper->get_instance_id();
            $additional_time = $options['additional_time'];

            $current_user = get_current_user_id();
            $zipcode = get_user_meta($current_user, 'shipping_postcode', true).trim(' ');

            wc_get_template('single-product/shipping-simulator.php', array(
                'instance_id' => $instance_id,
                'additional_time' => $additional_time,
                'zipcode' => $zipcode,
                'product' => $product,
                'style' => $style,
                'ids' => $ids,
            ), '', WC_Frenet_Main::get_templates_path());

        }

    }

    /**
     * Validate datas
     *
     * @param array $post
     * @return boolean
     */
    protected static function validateData(array $post)
    {
        if (!isset($post['instance_id']) || !$post['instance_id']) {
            return false;
        }

        if (!isset($post['zipcode']) || !$post['zipcode']) {
            return false;
        }

        if (!isset($post['variation_id']) || !$post['variation_id']) {
            return false;
        }

        if (!isset($post['quantity']) || !$post['quantity']) {
            return false;
        }

        return true;
    }

    /**
     * Return data products
     *
     * @param array $post
     * @return array|null
     */
    protected static function getProduct(array $post)
    {
        $variation = wc_get_product(sanitize_text_field($post['variation_id']));

        if ($variation) {
            return $variation;
        }

        if (!isset($post['product_id']) || !$post['product_id']) {
            return false;
        }

        $variation = wc_get_product(sanitize_text_field($post['product_id']));

        if ($variation) {
            return $variation;
        }
        return null;
    }

    /**
     * Simulator ajax response.
     *
     * @return string
     */
    public static function ajax_simulator()
    {
        $post = $_POST;
        $shippingValues = [];
        if (!self::validateData($post)) {
            echo wp_json_encode($shippingValues);
            return;
        } 

        if(!($variation = self::getProduct($post))) {
            echo wp_json_encode($shippingValues);
            return;
        }

        $frenet = new WC_Frenet(sanitize_text_field($post['instance_id']));

        $package = array();
        $package['destination']['postcode'] = sanitize_text_field($post['zipcode']);
        $package['destination']['country'] = 'BR';
        $package['contents'][0]['data'] = $variation;
        $package['contents'][0]['quantity'] = sanitize_text_field($post['quantity']);

        $frenet->quoteByProduct=true;
        $shippingValues = $frenet->frenet_calculate($package, 'JSON');

        echo wp_json_encode($shippingValues);
        die;
    }
}

new WC_Frenet_Shipping_Simulator();
