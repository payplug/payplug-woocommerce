/* global jQuery */
(function ($, undefined) {

    if (undefined === payplug_admin_config) {
        return;
    }

    var payplug_admin = {
        init: function () {
            $("#payplug-login, #payplug-logout").on('click', function(e) {
                window.onbeforeunload = null;
            })
            
            var email_input = $("#payplug_email")
            if (email_input.length) {
                email_input.focus()
                $("html").animate({
                    scrollTop: email_input.offset().top
                }, 800)
            }

            payplug_admin.xhr = false;

            // setup modal
            payplug_admin.$dialog = $('#payplug-refresh-keys-modal').dialog({
                autoOpen: false,
                modal: true,
                draggable: false,
                buttons: [
                    {
                        class: "ui-dialog-submit",
                        text: payplug_admin_config.btn_ok,
                        click: payplug_admin.refreshKeys
                    },
                    {
                        class: "ui-dialog-cancel",
                        text: payplug_admin_config.btn_label,
                        click: payplug_admin.onCancelClick
                    }
                ],
                show: true,
                hide: 100,
                close: payplug_admin.onDialogClose
            });

            // handle modal form submit
            payplug_admin.$dialog.find('form#payplug-refresh-keys-modal__form').on('submit', function (e) {
                e.preventDefault();
                payplug_admin.refreshKeys();
            });

            // open modal when user try to select live mode
            if ($('input[name=woocommerce_payplug_mode]').length) {
                this.$payplug_mode = $('input[name=woocommerce_payplug_mode]');
                this.$payplug_mode.on(
                    'click',
                    this.onClick
                )
            }

            if ($('#woocommerce_payplug_oneclick').length) {
                payplug_admin.$payplug_oneclick = $('#woocommerce_payplug_oneclick')
                payplug_admin.$payplug_oneclick_description = $('#woocommerce_payplug_title_advanced_settings').next()
                if (!payplug_admin.$payplug_oneclick.prop('disabled') || "0" === $('input[name=woocommerce_payplug_mode]:checked').val()) {
                    payplug_admin.$payplug_oneclick_description.hide()
                }
            }
        },
        onClick: function (event) {
            payplug_admin.$payplug_oneclick.prop('disabled', true)
            payplug_admin.oneclickPermissionsCheck()

            if ('0' === event.currentTarget.value || payplug_admin_config.has_live_key) {
                // ignore event if user choose TEST mode or already has LIVE keys
                return;
            }
            payplug_admin.$dialog.dialog('open');
        },
        onCancelClick: function () {
            payplug_admin.$dialog.dialog('close');
        },
        onDialogClose: function () {
            if (payplug_admin.xhr) {
                payplug_admin.xhr.abort();
                payplug_admin.xhr = false;
            }

            payplug_admin._clearMessage();

            payplug_admin.$dialog.find('form#payplug-refresh-keys-modal__form').get(0).reset();

            var live = payplug_admin.$payplug_mode.filter('#woocommerce_payplug_mode-yes');
            var test = payplug_admin.$payplug_mode.filter('#woocommerce_payplug_mode-no');
            if (payplug_admin_config.has_live_key && live.prop('checked') && !payplug_admin.is_oney_refresh) {
                test.prop('checked', 'checked');
            }
        },
        oneclickPermissionsCheck: () => {
            if ('0' === $('input[name=woocommerce_payplug_mode]:checked').val()) {
                payplug_admin.$payplug_oneclick.prop('disabled', false)
                payplug_admin.$payplug_oneclick_description.hide()
                return
            }

            payplug_admin.checkLivePermissions((res) => {
                const { can_save_cards } = res.data
                if (can_save_cards) {
                    payplug_admin.$payplug_oneclick.prop('disabled', false)
                    payplug_admin.$payplug_oneclick_description.hide()
                } else {
                    payplug_admin.$payplug_oneclick.prop('disabled', true)
                    payplug_admin.$payplug_oneclick.prop('checked', false)
                    payplug_admin.$payplug_oneclick_description.show()
                }
            })
        },
        checkLivePermissions: (callback) => {
            payplug_admin.xhr = $
                .post(
                    payplug_admin_config.ajax_url,
                    {
                        action: 'check_live_permissions',
                        livekey: $('#woocommerce_payplug_payplug_live_key').length ? $('#woocommerce_payplug_payplug_live_key').val() : ''
                    }
                ).done((res) => { callback(res) })
        },
        refreshKeys: function () {
            payplug_admin._clearMessage();
            payplug_admin._lockDialog();
            var form = payplug_admin.$dialog.find('form#payplug-refresh-keys-modal__form').get(0);

            if (payplug_admin.xhr) {
                payplug_admin.xhr.abort();
            }

            payplug_admin.xhr = $
                .post(
                    payplug_admin_config.ajax_url,
                    $(form).serializeArray()
                ).done(function (res) {
                    form.reset();
                    payplug_admin.xhr = false;
                    payplug_admin._unlockDialog();

                    if (false === res.success) {
                        payplug_admin._displayError(res.data.message);
                    } else {
                        payplug_admin._displaySuccess(res.data.message);
                        if (false === res.data.can_use_oney) {
                            payplug_admin.is_oney_refresh = true;
                            payplug_admin.$dialog.dialog('close');
                            payplug_admin.$dialogoney.dialog('open');
                        } else {
                            window.location.reload();
                        }
                    }
                })
                .fail(function (res) {
                    form.reset();
                    payplug_admin.xhr = false;
                    payplug_admin._unlockDialog();
                    payplug_admin._displayError(payplug_admin_config.general_error);
                });
        },
        _displaySuccess: function (msg) {
            payplug_admin._displayMessageHelper(msg, 'success');
        },
        _displayError: function (msg) {
            payplug_admin._displayMessageHelper(msg, 'error');
        },
        _clearMessage: function () {
            payplug_admin.$dialog.find('#dialog-msg').empty();
        },
        _lockDialog: function () {
            var buttons = payplug_admin._disabledHelper(payplug_admin.$dialog.dialog('option', 'buttons'), true);
            payplug_admin.$dialog.dialog('option', 'buttons', buttons);
        },
        _unlockDialog: function () {
            var buttons = payplug_admin._disabledHelper(payplug_admin.$dialog.dialog('option', 'buttons'), false);
            payplug_admin.$dialog.dialog('option', 'buttons', buttons);
        },
        _disabledHelper(items, disabled) {
            return $.each(items, function (i, val) {
                val.disabled = disabled;
            });
        },
        _displayMessageHelper(msg, type) {
            var msgHtml = payplug_admin.$dialog.find('#dialog-msg');
            msgHtml.text(msg).addClass(type);
        }
    };

    payplug_admin.init();

})(jQuery);
