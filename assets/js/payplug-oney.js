(function ($) {
    var is_cart = false
    var popupLoaded = false
    var initevent = function () {
        var showpopup = $("#oney-show-popup");
        var oneyData = $("#oney-show-popup").closest('.payplug-oney');
        var showpopuponey = $("#oney-show-popup").closest('.payplug-oney-popup');
        var arrow = $("#oney-popup-arrow");
        var qtyInput = $('input[name=quantity]');
        var popup = $("#oney-popup");
        var loading = popup.find('.payplug-lds-roller');
        var oneyError = popup.find('#oney-popup-error');
        var totalsProduct = oneyData.data('total-products');
        var maxOneyQty = oneyData.data('max-oney-qty');
        is_cart = oneyData.data('is-cart');
        var showpopupF = function (show) {
            if(!oneyData.hasClass('disabled') && !popupLoaded && !show) {
                return;
            }
            popup.show(0, function () {
                if (!$.browser.mobile) {
                    checkOneyError();
                    var top = oneyData.hasClass('disabled') ? 50 : 110;
                    popup.css('position', 'fixed');
                    popup.position({
                        my: popupLoaded ? "left top-" + top : "left top-75",
                        at: popupLoaded ? "right+40 bottom" : "right bottom",
                        of: showpopup
                    });
                    if(popupLoaded) {
                        showarrow();
                    }
                }
            });
        };
        showarrow = function () {
            arrow.show();
            arrow.position({
                my: "left top-43",
                at: "right+10 bottom",
                of: showpopup
            });
        };
        calculTotals = function () {
            var price = oneyData.data('price');
            return qtyInput.length ? totalsProduct * price : price;
        };
        isInOneyRange = function () {
            var totalPrice = calculTotals();
            var minOney = oneyData.data('min-oney');
            var maxOney = oneyData.data('max-oney');
            return (totalPrice >= minOney && totalPrice <= maxOney);
        };
        checkOneyError = function () {

            if(oneyData.hasClass('disabled')) {
                popupLoaded = true;
                popup.html('').append($(oneyError)).append($(arrow));
                popup.addClass('loaded');
                popup.find('.payplug-lds-roller').hide();
                popup.find('#oney-popup-error .oney-error').hide();
                totalsProduct >= maxOneyQty ?
                    popup.find('#oney-popup-error .oney-error.qty').show() :
                    popup.find('#oney-popup-error .oney-error.range').show();
            } else {
                arrow.hide();
            }
        };
        qtyInput.unbind();
        qtyInput.on('change', function () {
            totalsProduct = $(this).val();
            popupLoaded = false;
            if (isInOneyRange() && totalsProduct < maxOneyQty) {
                oneyData.removeClass('disabled');
                popup.removeClass('disabled').removeClass('loaded');
                popup.html('').append($(arrow));
                arrow.hide();
            } else {
                checkOneyError();
                oneyData.addClass('disabled');
                popup.addClass('disabled');
            }
        });
        showpopuponey.unbind();
        showpopuponey.on('click', function () {
            showpopupF(true);
            if (isInOneyRange() && totalsProduct < maxOneyQty) {
                if (popupLoaded) {
                    return;
                }
                popup.html('').append($(loading));
                $.post(
                    payplug_config.ajax_url,
                    {
                        'action': payplug_config.ajax_action,
                        'price': calculTotals()
                    }, function (response) {
                        if (response.data.popup) {
                            popupLoaded = true;
                            popup.addClass('loaded');
                            popup.html(response.data.popup);
                            showpopupF();
                            
                            var closepopup = $('#oney-popup-close');
                            closepopup.on('click', function () {
                                popup.hide()
                            });
                            
                            $(document).mouseup(function(e) 
                            {
                                // if the target of the click isn't the container nor a descendant of the container
                                if (!popup.is(e.target) && popup.has(e.target).length === 0) 
                                {
                                    popup.hide();
                                }
                            });
                        }
                    }
                );
            } else {
                checkOneyError();
            }
        });
    };
    
    $(document).on('ready', function () {
        initevent();
        if (is_cart) {
            $(document).ajaxSuccess(function (event, request, settings) {
                if (settings.data.includes(payplug_config.ajax_action)) {
                    return;
                }
                initevent();
                popupLoaded = false;
            });
        }
    });
    
    
})(jQuery);