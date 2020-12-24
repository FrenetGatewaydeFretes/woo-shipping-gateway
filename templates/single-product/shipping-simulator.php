<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div id="shipping-simulator" style="<?php echo esc_attr($style); ?>"
        data-product-ids="<?php echo esc_attr($ids); ?>"
        data-product-type="<?php echo esc_attr($product->product_type); ?>">
    <form method="post" class="cart">

        <label for="shipping">Calcular Frete <br>
            <input required type="text" name="zipcode" id="zipcode" maxlength="9" placeholder="00000-000"
                    value="<?php echo $zipcode; ?>">
        </label>

        <input type="hidden" name="instance_id" id="instance_id" value="<?php echo $instance_id; ?>">
        <input type="hidden" name="additional_time" id="additional_time" value="<?php echo $additional_time; ?>">
        <input type="hidden" name="qty_simulator" id="qty_simulator" class="qty_simulator" value="1">
        <button name="idx-calc_shipping" id="idx-calc_shipping" value="1" class="button">Ok</button>
        <br class="clear"/>
        <br>
        <div id='loading_simulator' style='display:none'>
            <p>Aguarde...</p>
        </div>
        <div id="simulator-data"></div>
        <!--display data -->

    </form>

</div>