<?php

/**
 * WC_Frenet class.
 */
class WC_Frenet_Shipping_Simulator extends WC_Frenet
{
    /**
     * Rate limit window in seconds.
     */
    const RATE_LIMIT_WINDOW = 600;

    /**
     * Returns the asset version: plugin version plus the file modification time.
     *
     * @param string $relative_path
     * @return string
     */
    protected static function asset_version($relative_path)
    {
        $file = plugin_dir_path(dirname(__FILE__)) . $relative_path;

        return WC_Frenet_Main::VERSION . (is_readable($file) ? '.' . filemtime($file) : '');
    }

    /**
     * Returns the delivery time message in singular and plural forms.
     *
     * @return array
     */
    protected static function delivery_time_message()
    {
        return _n_noop('Delivery in %d working day', 'Delivery in %d working days', 'woo-shipping-gateway');
    }

    /**
     * Returns the simulator quote limit per IP.
     *
     * @return int
     */
    protected static function get_rate_limit_option()
    {
        $helper = new WC_Frenet_Helper;
        $options = $helper->get_options();

        if (!is_array($options) || !isset($options['simulator_rate_limit']) || '' === $options['simulator_rate_limit']) {
            return self::DEFAULT_SIMULATOR_RATE_LIMIT;
        }

        return max(0, (int) $options['simulator_rate_limit']);
    }

    /**
     * Counts the request for the visitor IP and checks whether it exceeded the limit.
     *
     * @return bool
     */
    protected static function is_rate_limited()
    {
        $limit = (int) apply_filters('wc_frenet_simulator_rate_limit', self::get_rate_limit_option());
        $window = (int) apply_filters('wc_frenet_simulator_rate_limit_window', self::RATE_LIMIT_WINDOW);

        if ($limit <= 0 || $window <= 0) {
            return false;
        }

        $key = 'wc_frenet_simulator_rl_' . md5(WC_Geolocation::get_ip_address());
        $now = time();
        $hits = get_transient($key);

        if (!is_array($hits) || !isset($hits['count'], $hits['expires']) || $hits['expires'] <= $now) {
            $hits = array('count' => 0, 'expires' => $now + $window);
        }

        $hits['count']++;
        set_transient($key, $hits, max(1, $hits['expires'] - $now));

        return $hits['count'] > $limit;
    }

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

        wp_enqueue_style('shipping-simulator', plugins_url('assets/css/simulator.css', plugin_dir_path(__FILE__)), array(), self::asset_version('assets/css/simulator.css'), 'all');
        wp_enqueue_script('shipping-simulator', plugins_url('assets/js/simulator.js', plugin_dir_path(__FILE__)), array('jquery'), self::asset_version('assets/js/simulator.js'), true);
        wp_localize_script(
            'shipping-simulator',
            'shipping_simulator',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'error_message' => __('Unable to simulate the shipping. Try adding the product to the cart and proceed to get the shipping cost.', 'woo-shipping-gateway'),
                'rate_limit_message' => __('Too many shipping simulations in a short time. Please wait a few minutes and try again.', 'woo-shipping-gateway'),
                'shipping_label' => __('Shipping', 'woo-shipping-gateway'),
                'cost_label' => __('Cost', 'woo-shipping-gateway'),
                'delivery_time_singular' => translate_nooped_plural(self::delivery_time_message(), 1, 'woo-shipping-gateway'),
                'delivery_time_plural' => translate_nooped_plural(self::delivery_time_message(), 2, 'woo-shipping-gateway'),
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

        $style = '';
        $ids = $product->get_id();

        if ('variable' === $product->get_type()) {
            $style = 'display: none';

            $ids = implode(',', $product->get_visible_children());
        }

        if ($product->is_in_stock() && $product->needs_shipping() && in_array($product->get_type(), array('simple', 'variable', 'composite'))) {

            $options = $helper->get_options();
            $instance_id = $helper->get_instance_id();
            $additional_time = $options['additional_time'];

            $current_user = get_current_user_id();
            $zipcode = trim((string) get_user_meta($current_user, 'shipping_postcode', true));

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
        if (!isset($post['instance_id']) || !self::isEnabledInstance($post['instance_id'])) {
            return false;
        }

        if (!isset($post['zipcode']) || !$post['zipcode']) {
            return false;
        }

        if (!isset($post['variation_id']) || !absint($post['variation_id'])) {
            return false;
        }

        if (!isset($post['quantity']) || wc_stock_amount($post['quantity']) <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the instance id belongs to an enabled Frenet shipping method.
     *
     * @param mixed $instance_id
     * @return boolean
     */
    protected static function isEnabledInstance($instance_id)
    {
        $helper = new WC_Frenet_Helper;
        $instances = $helper->get_instance_ids();

        if (!$instances) {
            return false;
        }

        return in_array(absint($instance_id), array_map('absint', wp_list_pluck($instances, 'instance_id')), true);
    }

    /**
     * Checks whether the product can be quoted.
     *
     * @param WC_Product $product
     * @return boolean
     */
    protected static function isQuotable($product)
    {
        if ($product->is_type('variable')) {
            return false;
        }

        return $product->is_purchasable() && $product->is_in_stock() && $product->needs_shipping();
    }

    /**
     * Return data products
     *
     * @param array $post
     * @return array|null
     */
    protected static function getProduct(array $post)
    {
        $variation = wc_get_product(absint($post['variation_id']));

        if ($variation) {
            return $variation;
        }

        if (!isset($post['product_id']) || !absint($post['product_id'])) {
            return false;
        }

        $variation = wc_get_product(absint($post['product_id']));

        if ($variation) {
            return $variation;
        }
        return null;
    }

    /**
     * Formats a Frenet shipping price with the store currency settings.
     *
     * @param string $price
     * @return string
     */
    protected static function format_shipping_price($price)
    {
        $amount = (float) str_replace(',', '.', (string) $price);

        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Simulator ajax response.
     *
     * @return string
     */
    public static function ajax_simulator()
    {
        if (self::is_rate_limited()) {
            wp_send_json(array(), 429);
        }

        $post = wp_unslash($_POST);
        $shippingValues = [];
        if (!self::validateData($post)) {
            wp_send_json($shippingValues);
        }

        if(!($variation = self::getProduct($post)) || !self::isQuotable($variation)) {
            wp_send_json($shippingValues);
        }

        $frenet = new WC_Frenet(absint($post['instance_id']));

        $package = array();
        $package['destination']['postcode'] = sanitize_text_field($post['zipcode']);
        $package['destination']['country'] = 'BR';
        $package['contents'][0]['data'] = $variation;
        $package['contents'][0]['quantity'] = wc_stock_amount($post['quantity']);

        $frenet->quoteByProduct=true;
        $shippingValues = $frenet->frenet_calculate($package, 'JSON');

        foreach ($shippingValues as $service) {
            $service->ShippingPriceFormatted = self::format_shipping_price($service->ShippingPrice);
        }

        if (!empty($shippingValues)) {
            $shippingValues['display_date'] = $frenet->get_option('display_date') === 'yes';
        }

        wp_send_json($shippingValues);
    }
}

new WC_Frenet_Shipping_Simulator();
