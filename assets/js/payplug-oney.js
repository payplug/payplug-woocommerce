(function ($) {
    function calculTotals(container) {
        var price = parseFloat(container.data('price'));
        var qtyInput = $('input[name=quantity]');

        if (qtyInput.length) {
            return parseFloat(qtyInput.val()) * price;
        }

        return price;
    }

    function refreshEligibility(container) {
        if (container.data('min-oney') === undefined || container.data('max-oney') === undefined) {
            // No thresholds means the badge is disabled for a reason quantity can't change
            // (e.g. country not allowed) - never re-enable it client-side.
            return;
        }

        var total = calculTotals(container);
        var min = parseFloat(container.data('min-oney'));
        var max = parseFloat(container.data('max-oney'));

        if (total >= min && total <= max) {
            container.removeClass('disabled');
        } else {
            container.addClass('disabled');
        }
    }

    function showSimulationPopin(container) {
        if (container.hasClass('disabled') || typeof loadOneyWidget !== 'function') {
            return;
        }

        var options = $.extend({}, window.payplug_oney_config, {
            payment_amount: calculTotals(container),
            filter_by: 'business_transaction_codes',
            errorCallback: function (status, response) {
                console.warn('Oney widget unavailable', status, response);
            },
        });

        loadOneyWidget(function () {
            if (typeof oneyMerchantApp === 'undefined') {
                return;
            }
            oneyMerchantApp.loadSimulationPopin({options: options});
        });
    }

    var checkoutSectionsLoaded = {};

    function loadCheckoutSection(gatewayId) {
        // The memo must reflect the placeholder currently in the DOM: WooCommerce replaces the
        // whole payment fragment on updated_checkout, so a previously mounted widget is gone.
        var placeholder = document.getElementById('oney-checkout-' + gatewayId);
        if (!placeholder) {
            delete checkoutSectionsLoaded[gatewayId];

            return;
        }

        if (checkoutSectionsLoaded[gatewayId] === placeholder || typeof loadOneyWidget !== 'function') {
            return;
        }

        var configEl = document.getElementById('oney-checkout-config-' + gatewayId);
        if (!configEl) {
            return;
        }

        var loading = document.getElementById('oney-checkout-loading-' + gatewayId);
        var hideLoading = function () {
            if (loading) {
                loading.style.display = 'none';
            }
        };

        var config = JSON.parse(configEl.textContent);
        var options = $.extend({}, config, {
            filter_by: 'business_transaction_code',
            checkout_placeholder: '#oney-checkout-' + gatewayId,
            successCallback: hideLoading,
            errorCallback: function (status, response) {
                hideLoading();
                console.warn('Oney checkout widget unavailable', status, response);
            },
        });

        loadOneyWidget(function () {
            if (typeof oneyMerchantApp === 'undefined') {
                hideLoading();

                return;
            }
            oneyMerchantApp.loadCheckoutSection({options: options});
            checkoutSectionsLoaded[gatewayId] = placeholder;
        });
    }

    function maybeLoadSelectedCheckoutSection() {
        var selected = $('input[name="payment_method"]:checked').val();
        if (selected && selected.indexOf('oney_') === 0) {
            loadCheckoutSection(selected);
        }
    }

    // Bound to the whole popup wrapper (logo + "?"), not just #oney-show-popup itself: the
    // pre-migration implementation made the entire wrapper clickable (see the old
    // showpopuponey = $('#oney-show-popup').closest('.payplug-oney-popup') pattern), and users
    // click the Oney logo as often as the small "?" icon next to it.
    $(document).on('click', '.payplug-oney-popup', function () {
        showSimulationPopin($(this).closest('.payplug-oney'));
    });

    $(document).on('change', 'input[name=quantity]', function () {
        $('.payplug-oney').each(function () {
            refreshEligibility($(this));
        });
    });

    $(function () {
        $('.payplug-oney').each(function () {
            refreshEligibility($(this));
        });
    });

    $(document).on('change', 'input[name="payment_method"]', maybeLoadSelectedCheckoutSection);
    $(document.body).on('updated_checkout', maybeLoadSelectedCheckoutSection);
    $(maybeLoadSelectedCheckoutSection);
})(jQuery);
