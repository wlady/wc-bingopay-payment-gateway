jQuery(document).ready( function($) {
    var form = $('form[name=checkout]');
    if (form.length === 0) {
        form = $('form#order_review');
    }
    form.processing = function() {
        this.addClass( 'processing' );
        this.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    };
    form.processed = function() {
        this.removeClass( 'processing' );
        this.unblock();
    };
    form.on('submit', function(event) {
        if ($('#payment_method_bingopay_gateway').is(':checked')) {
            event.stopImmediatePropagation();
            event.stopPropagation();
            $('.bingopay-error-message').html('');
            if ($('#bingopay_gateway-card-number').val().length < 16) {
                $('.bingopay-error-message').html('Check card number field');
                return false;
            }
            var expire = $('#bingopay_gateway-card-expiry').val().replace(/\s/g, '');
            if (expire.length < 5) {
                $('.bingopay-error-message').html('Check card expire field');
                return false;
            }
            if ($('#bingopay_gateway-card-cvc').val().length < 3) {
                $('.bingopay-error-message').html('Check card CVV field');
                return false;
            }
            var name = $('#bingopay_gateway-card-holder-name').val().replace(/\s/g, '');
            if (name.length === 0) {
                $('.bingopay-error-message').html('Check card holder name field');
                return false;
            }
            $('#bingopay-3ds-window').attr('src', '').hide();
            $('.bingopay-iframe-loader').show();
            if (form.triggerHandler('checkout_place_order') !== false) {
                form.processing();
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
                        form.processed();
                        if (response.data) {
                            if (response.data.error_description) {
                                $('.bingopay-error-message').html(response.data.error_description);
                            } else if (response.data.error) {
                                $('.bingopay-error-message').html(response.data.message);
                            } else if (response.data.redirect_url.length !== 0) {
                                $('#bingoPayModal').modal('show');
                                $('.bingopay-iframe-loader').hide();
                                $('#bingopay-3ds-window').attr('src', response.data.redirect_url).show();
                            }
                        }
                    },
                    failure: function (response) {
                        form.processed();
                        if (response.data) {
                            if (response.data.error_description) {
                                $('.bingopay-error-message').html(response.data.error_description);
                            } else if (response.data.error) {
                                $('.bingopay-error-message').html(response.data.message);
                            } else {
                                $('.bingopay-error-message').html(response.code);
                            }
                        }
                    }
                });
            }
            return false;
        }
    });
});