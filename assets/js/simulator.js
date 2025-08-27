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

    variableValidate: function(variations) {
        let valid = true;
        variations.forEach((variation) => {
            if (!variation.value) {
                valid = false;
            }
        })

        return valid
    },

    /**
     * product ids are depends with product type, now same mode for getting product ids in quotation will be applied in page load
     */
    getProductIds: function() {
        var product_id;
        var simulator = jQuery('#shipping-simulator');
        var type = simulator.data('product-type');

        product_id = simulator.data('product-ids');
        if ('variable' === type) {
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

    const variations = document.querySelectorAll('.variations select');
    variations.forEach((variation) => {
        variation.addEventListener('change', () => {
            if (simulatorHelper.variableValidate(variations)) {
                jQuery('#shipping-simulator').slideDown(200);
            } else {
                jQuery('#shipping-simulator').hide();
            }

            simulatorHelper.simulatorClean();
        })
    })


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

                const shippingDiv = document.createElement('div');

                if (jQuery.isEmptyObject(response)) {
                    const shippingErrorMessage = document.createElement('p');

                    shippingErrorMessage.innerText = "Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor."
                    shippingDiv.appendChild(shippingErrorMessage)
                } else {

                    const shippingUl = document.createElement('ul');
                    shippingUl.setAttribute('id', 'shipping-rates');

                    jQuery.each(response, function (key, value) {
                        if (value.ServiceDescription !== undefined) {
                            let EstimatingDelivery = parseInt(value.DeliveryTime, 10) + parseInt(additional_time, 10);

                            const shippingLi = document.createElement('li');
                            shippingLi.classList.add('li-frenet');

                            const shippingSpan = document.createElement('span');
                            shippingSpan.classList.add('span-frenet');
                            shippingSpan.innerText = value.ServiceDescription + ': ';

                            shippingLi.appendChild(shippingSpan);

                            /*
                             Caso haja configuração de desconto no frete na plataforma, o valor original aparece taxado, reforçando a promoção para o cliente/usuário
                            (https://www.frenet.com.br/blog/criar-regra-de-frete-e-simples-assim/)
                            */
                            if(value.OriginalShippingPrice > value.ShippingPrice + 0.1) {
                                shippingLi.innerText += 'de: <s>R$' + value.OriginalShippingPrice + '</s> por: '
                            }
                            
                            shippingLi.innerText += 'R$' + value.ShippingPrice;

                            if (response.display_date === true) {
                                shippingLi.innerText += ' (Entrega em ' + EstimatingDelivery + ' dias úteis)';
                            }

                            shippingUl.appendChild(shippingLi);
                        }
                    });

                    shippingDiv.appendChild(shippingUl);
                }

                content.prepend(shippingDiv);
            }
        });

    });

});
