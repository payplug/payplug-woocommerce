(function ($) {
    var is_cart = false
    var popupLoaded = false
    var _initevent = function () {
        var showpopup = $("#oney-show-popup")
        var oneyData = $("#oney-show-popup").closest('.payplug-oney')
        var showpopuponey = $("#oney-show-popup").closest('.payplug-oney-popup')
        var qtyInput = $('input[name=quantity]')
        var popup = $("#oney-popup")
        var loading = popup.find('.payplug-lds-roller')
        var oneyError = popup.find('#oney-popup-error')
        var totalsProduct = oneyData.data('total-products')
        var maxOneyQty = oneyData.data('max-oney-qty')
        is_cart = oneyData.data('is-cart')

        function _showPopup(show) {
            if (!oneyData.hasClass('disabled') && !popupLoaded && !show) {
                return
            }
            popup.show(0, function () {
                if (!$.browser.mobile) {
                    _checkOneyError()
                    var top = oneyData.hasClass('disabled') ? 50 : 110
                    popup.css('position', 'fixed')
                    popup.position({
                        my: popupLoaded ? "left top-" + top : "left top-75",
                        at: popupLoaded ? "right+40 bottom" : "right bottom",
                        of: showpopup,
                    })
                }
            })
        }

        function _hidePopup() {
            popup.hide();
        }

        function _calculTotals() {
            var price = oneyData.data('price')
            return qtyInput.length ? totalsProduct * price : price
        }

        function _isInOneyRange() {
            var totalPrice = _calculTotals();
            var minOney = oneyData.data('min-oney');
            var maxOney = oneyData.data('max-oney');
            return (totalPrice >= minOney && totalPrice <= maxOney);
        };

        function _checkOneyError() {
            if (oneyData.hasClass('disabled')) {
                popupLoaded = true
                popup.html('').append($(oneyError))
                popup.addClass('loaded')
                popup.find('.payplug-lds-roller').hide()
                popup.find('#oney-popup-error .oney-error').hide()
                totalsProduct >= maxOneyQty ?
                    popup.find('#oney-popup-error .oney-error.qty').show() :
                    popup.find('#oney-popup-error .oney-error.range').show()
            } 
        };

        function _bindCloseOneyPopup() {
            $(document).unbind('mouseup')
            $(document).mouseup(function (e) {
                // if the target of the click isn't the container nor a descendant of the container
                if (!popup.is(e.target) && popup.has(e.target).length === 0) {
                    _hidePopup();
                }
            });

            $('#oney-popup-close').unbind();
            $('#oney-popup-close').on('click', function () {
                _hidePopup();
            });
        }
        
        _bindCloseOneyPopup();
        qtyInput.unbind()
        qtyInput.on('change', function () {
            totalsProduct = $(this).val()
            popupLoaded = false
            if (_isInOneyRange() && totalsProduct < maxOneyQty) {
                oneyData.removeClass('disabled')
                popup.removeClass('disabled').removeClass('loaded')
                popup.html('')
            } else {
                _checkOneyError()
                oneyData.addClass('disabled')
                popup.addClass('disabled')
            }
        })
        showpopuponey.unbind();
        showpopuponey.on('click', function () {
            _showPopup(true);
            if (_isInOneyRange() && totalsProduct < maxOneyQty) {
                if (popupLoaded) {
                    return
                }
                popup.html('').append($(loading))
                $.post(
                    payplug_config.ajax_url,
                    {
                        'action': payplug_config.ajax_action,
                        'price': _calculTotals()
                    }, function (response) {
                        if (response.data.popup) {
                            popupLoaded = true;
                            popup.addClass('loaded');
                            popup.html(response.data.popup);
                            _showPopup();
                            _bindCloseOneyPopup()
                        }
                    }
                );
            } else {
                _checkOneyError()
            }
        });

        $(window).on('scroll', function () {
            if(popup.is(':visible')) {
                _showPopup()
            }
        })
    };

    $(document).on('ready', function () {
        _initevent();
        if (is_cart) {
            $(document).ajaxSuccess(function (event, request, settings) {
                if (settings.data.includes(payplug_config.ajax_action)) {
                    return;
                }
                _initevent();
                popupLoaded = false;
            });
        }
    });
})(jQuery);