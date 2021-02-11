(function ($) {
    var popupLoaded = false
    var popupIsLoading = false
    var initevent = function () {
        var showpopup = $("#oney-show-popup")
        var showpopuponey = $("#oney-show-popup").closest('.payplug-oney')
        var disabled = showpopuponey.hasClass('disabled');
        var popup = $("#oney-popup")
        var showpopupF = function () {
            popup.show(0, function () {
                if (!$.browser.mobile) {
					if (disabled) {
                        var top = 50
                    } else {
                        var top = 110
                    }
                    popup.css('position', 'fixed')
                    popup.position({
                        my: popupLoaded ? "left top-" + top : "left top-75",
                        at: popupLoaded ? "right+40 bottom" : "right bottom",
                        of: showpopup,
                    })
                    showarrow()
                }
            })
        }
        showarrow = function () {
            var arrow = $("#oney-popup-arrow")
            arrow.position({
                my: "left top-30",
                at: "right+10 bottom",
                of: showpopup,
            })
        }

        showpopuponey.unbind()
        showpopuponey.on('click', function () {
            showpopupF()
            if (popupLoaded || popupIsLoading) {
                return
            }
            var price = $(this).data('price')
            popupIsLoading = true
            $.post(
                payplug_config.ajax_url,
                {
                    'action': payplug_config.ajax_action,
                    'price': price
                }, function (response) {
                    popupIsLoading = false
                    if (response.data.popup) {
                        popupLoaded = true
                        popup.addClass('loaded')
                        popup.html(response.data.popup)
                        showpopupF()
                    }
                }
            )
        })


        showpopuponey.on('mouseenter', function () {
            if (popupLoaded) {
                showpopupF()
            }
        })
        showpopuponey.on('mouseleave', function () {
            if (popupLoaded) {
                popup.hide()
            }
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
