jQuery(document).ready( function($) {
    var form = $('form[name=checkout]');
    if (form.length === 0) {
        form = $('form#order_review');
    }
    form.on('submit', function(event) {
        if ($('#payment_method_bingopay_gateway').is(':checked')) {
            event.stopImmediatePropagation();
            event.stopPropagation();
            if ($('#bingopay_gateway-card-number').val().length < 16) {
                alert('Check card number field');
                return false;
            }
            var expire = $('#bingopay_gateway-card-expiry').val().replace(/\s/g, '');
            if (expire.length < 5) {
                alert('Check card expire field');
                return false;
            }
            if ($('#bingopay_gateway-card-cvc').val().length < 3) {
                alert('Check card CVV field');
                return false;
            }
            var name = $('#bingopay_gateway-card-holder-name').val().replace(/\s/g, '');
            if (name.length === 0) {
                alert('Check card holder name field');
                return false;
            }
            $('#bingopay-3ds-window').attr('src', '');
            $('#bingoPayModal').modal('show');
            $('.bingopay-3ds-window').hide();
            $('.iframe-loader').show();
            if (form.triggerHandler('checkout_place_order') !== false) {
                var fd = new FormData(form.get()[0]);
                fd.append('action', 'bignopay_3ds_form');
                fd.append('_ajax_nonce', ajax.nonce);
                fd.append('amount', currentOrderTotal);
                fd.append('order_id', currentOrderId);
                jQuery.ajax({
                    url: ajax.url,
                    method: "POST",
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (response) {
                        $('.bingopay-3ds-window').show();
                        $('.iframe-loader').hide();
                        if (response.data) {
                            if (response.data.redirect_url.length !== 0) {
                                $('#bingopay-3ds-window').attr('src', response.data.redirect_url);
                                $('#bingoPayModal').show();
                            }
                        }
                    }
                });
            }
            return false;
        }
    });
});