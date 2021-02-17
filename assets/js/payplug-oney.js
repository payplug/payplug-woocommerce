(function ($) {
    var popupLoaded = false
    var initevent = function () {
        var showpopup = $("#oney-show-popup")
        var showpopuponey = $("#oney-show-popup").closest('.payplug-oney')
        var arrow = $("#oney-popup-arrow")
        var qtyInput = $('input[name=quantity]')
        var popup = $("#oney-popup")
        var loading = popup.find('.payplug-lds-roller')
        var oneyError = popup.find('#oney-popup-error')
        var showpopupF = function () {
            popup.show(0, function () {
                if (!$.browser.mobile) {
                    checkOneyError()
                    var top = showpopuponey.hasClass('disabled') ? 50 : 110
                    popup.css('position', 'fixed')
                    popup.position({
                        my: popupLoaded ? "left top-" + top : "left top-75",
                        at: popupLoaded ? "right+40 bottom" : "right bottom",
                        of: showpopup,
                    })
                    if(popupLoaded) {
                        showarrow()
                    }
                }
            })
        }

        showarrow = function () {
            arrow.show()
            arrow.position({
                my: "left top-43",
                at: "right+10 bottom",
                of: showpopup,
            })
        }

        calculTotals = function () {
            var qty = qtyInput.val()
            var price = showpopuponey.data('price')
            return qty * price
        }

        isInOneyRange = function () {
            var totalPrice = calculTotals()
            var minOney = showpopuponey.data('min-oney')
            var maxOney = showpopuponey.data('max-oney')
            return (totalPrice >= minOney && totalPrice <= maxOney)
        }

        checkOneyError = function () {
            if(showpopuponey.hasClass('disabled')) {
                popupLoaded = true
                popup.html('').append($(oneyError)).append($(arrow))
                popup.addClass('loaded')
                popup.find('.payplug-lds-roller').hide()
                popup.find('#oney-popup-error .oney-error').hide()
                qtyInput.val() >= 1000 ?
                    popup.find('#oney-popup-error .oney-error.qty').show() :
                    popup.find('#oney-popup-error .oney-error.range').show()
            } else {
                arrow.hide()
            }
        }

        qtyInput.unbind()
        qtyInput.on('change', function () {
            popupLoaded = false
            if (isInOneyRange()) {
                showpopuponey.removeClass('disabled')
                popup.removeClass('disabled').removeClass('loaded')
                popup.html('').append($(arrow))
                arrow.hide()
            } else {
                checkOneyError()
                showpopuponey.addClass('disabled')
                popup.addClass('disabled')
            }
        })

        showpopuponey.unbind()
        showpopuponey.on('click', function () {
            if (isInOneyRange() && qtyInput.val() < 1000) {
                if (popupLoaded) {
                    return
                }
                popup.html('').append($(loading))
                $.post(
                    payplug_config.ajax_url,
                    {
                        'action': payplug_config.ajax_action,
                        'price': calculTotals()
                    }, function (response) {
                        if (response.data.popup) {
                            popupLoaded = true
                            popup.addClass('loaded')
                            popup.html(response.data.popup)
                            showpopupF()
                        }
                    }
                )
            } else {
                checkOneyError()
            }
        })


        showpopuponey.on('mouseenter', function () {
            showpopupF()
        })
        showpopuponey.on('mouseleave', function () {
            popup.hide()
        })

        $(document).on('scroll', function () {
            if (!$.browser.mobile) {
                popup.hide()
            }
        })
    }

    $(document).on('ready', function () {
        initevent()
    })

    if (payplug_config.is_cart) {
        $(document).ajaxSuccess(function (event, request, settings) {
            if (settings.data.includes(payplug_config.ajax_action)) {
                return
            }
            initevent()
            popupLoaded = false
        })
    }
})(jQuery)
