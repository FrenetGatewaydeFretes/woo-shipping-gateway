/** 
 * operations helpers for simulator
*/
var simulatorHelper = {

    /**
     * clean old data showed page by the simulator 
     */
    simulatorClean: function () {
        jQuery('#shipping-simulator #simulator-data').empty();
    },

    /**
     * product ids are depends with product type, now same mode for getting product ids in quotation will be applied in page load
     */
    getProductIds: function() {
        var product_id;
        var simulator = jQuery('#shipping-simulator');
        var type = simulator.data('product-type');

        if ('simple' == type) {
            product_id = simulator.data('product-ids');
        } else {
            product_id = jQuery('input[name="product_id"]').val();
        }

        // avoid error caused for product ids not found
        if (!product_id) product_id = "";

        return product_id;
    }
};

/* global shipping_simulator */
jQuery(document).ready(function ($) {

    jQuery(document).on('change', '.quantity .qty', function () {
        jQuery('.qty_simulator').attr('value', jQuery(this).val());
    });

    jQuery('body').on('show_variation', function () {
        
        var ids = simulatorHelper.getProductIds().toString().split(',');
        var variation_id = jQuery('.cart input[name="variation_id"]').val();

        if (-1 < jQuery.inArray(variation_id, ids)) {
            jQuery('#shipping-simulator').slideDown(200);
        }

        simulatorHelper.simulatorClean();
    });

    jQuery('#shipping-simulator').on('click', '.button', function (e) {

        e.preventDefault();

        jQuery('#loading_simulator').show();
        simulatorHelper.simulatorClean();

        var simulator = jQuery('#shipping-simulator');
        var content = jQuery('#shipping-simulator #simulator-data');

        var type = simulator.data('product-type');
        var zipcode = jQuery('#shipping-simulator #zipcode').val().trim(' ');
        var additional_time = jQuery('#additional_time').val();
        var instance_id = jQuery('#instance_id').val();
        var variation_id = jQuery('.cart input[name="variation_id"]').val();
        var quantity = jQuery('#qty_simulator').val();
        var product_id = simulatorHelper.getProductIds();

        if (!variation_id) {
            variation_id = product_id;
        }

        if (!additional_time) {
            additional_time = 0;
        } else {
            additional_time = parseInt(additional_time, 10);
        }

        /*
        console.log('ID do produto: ' + product_id);
        console.log('ID da variacão (se for variavel): ' + variation_id);
        console.log('CEP: ' + zipcode);
        console.log('Additional Time: ' + additional_time);
        */

        jQuery.ajax({
            type: 'POST',
            url: shipping_simulator.ajax_url,
            data: {
                action: 'ajax_simulator',
                type: type,
                zipcode: zipcode,
                product_id: product_id,
                variation_id: variation_id,
                instance_id: instance_id,
                additional_time: additional_time,
                quantity: quantity
            },
            success: function (response) {

                response = jQuery.parseJSON(response);
                jQuery('#loading_simulator').hide();

                var shipping = '<div>';

                if (jQuery.isEmptyObject(response)) {
                    shipping += '<p>Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor.</p>';
                } else {

                    // if (response.data.weight == undefined || response.data.weight == "") {
                    // shipping += '<p>Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor.</p>';
                    // }else {

                    shipping += '<ul id="shipping-rates">';
                    jQuery.each(response, function (key, value) {
                        if (value.ServiceDescription !== undefined) {
                            var EstimatingDelivery = parseInt(value.DeliveryTime, 10) + parseInt(additional_time, 10);
                            shipping += '<li class="li-frenet"><span class="span-frenet">' + value.ServiceDescription + '</span>: R$ ' + value.ShippingPrice + ' (Entrega em ' + EstimatingDelivery + ' dias úteis)</li>';
                        }
                    });
                    shipping += '</ul>';

                    // }

                }

                shipping += '</div>';
                content.prepend(shipping);
            }
        });

    });

});