/* global woo_wallet_admin_order_param, wc_meta_boxes_order_items, woocommerce_admin_meta_boxes */

jQuery(function ($) {
    var wc_wallet_payment_order_items = {
        init: function () {
            if (woo_wallet_admin_order_param.payment_method === 'wallet') {
                $('.refund-actions .button.tips.disabled').remove();
            }
            if (!woo_wallet_admin_order_param.is_rechargeable_order) {
                $('.refund-actions .do-manual-refund').before('<button type="button" class="button button-primary do-wallet-refund">Refund <span class="wc-order-refund-amount">' + woo_wallet_admin_order_param.default_price + '</span> via Wallet payments</button>');
                $('#woocommerce-order-items').on('click', '.refund-actions .do-wallet-refund', this.do_wallet_refund);
            }
        },
        do_wallet_refund: function () {
            wc_wallet_payment_order_items.block();
            if (window.confirm(woocommerce_admin_meta_boxes.i18n_do_refund)) {
                var refund_amount = $('input#refund_amount').val();
                var refund_reason = $('input#refund_reason').val();
                // Get line item refunds
                var line_item_qtys = {};
                var line_item_totals = {};
                var line_item_tax_totals = {};
                $('.refund input.refund_order_item_qty').each(function (index, item) {
                    if ($(item).closest('tr').data('order_item_id')) {
                        if (item.value) {
                            line_item_qtys[ $(item).closest('tr').data('order_item_id') ] = item.value;
                        }
                    }
                });

                $('.refund input.refund_line_total').each(function (index, item) {
                    if ($(item).closest('tr').data('order_item_id')) {
                        line_item_totals[ $(item).closest('tr').data('order_item_id') ] = accounting.unformat(item.value, woocommerce_admin.mon_decimal_point);
                    }
                });

                $('.refund input.refund_line_tax').each(function (index, item) {
                    if ($(item).closest('tr').data('order_item_id')) {
                        var tax_id = $(item).data('tax_id');

                        if (!line_item_tax_totals[ $(item).closest('tr').data('order_item_id') ]) {
                            line_item_tax_totals[ $(item).closest('tr').data('order_item_id') ] = {};
                        }

                        line_item_tax_totals[ $(item).closest('tr').data('order_item_id') ][ tax_id ] = accounting.unformat(item.value, woocommerce_admin.mon_decimal_point);
                    }
                });
                var data = {
                    action: 'wc_wallet_payment_order_refund',
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    refund_amount: refund_amount,
                    refund_reason: refund_reason,
                    line_item_qtys: JSON.stringify(line_item_qtys, null, ''),
                    line_item_totals: JSON.stringify(line_item_totals, null, ''),
                    line_item_tax_totals: JSON.stringify(line_item_tax_totals, null, ''),
                    api_refund: $(this).is('.do-api-refund'),
                    restock_refunded_items: $('#restock_refunded_items:checked').length ? 'true' : 'false',
                    security: woocommerce_admin_meta_boxes.order_item_nonce
                };
                $.post(woocommerce_admin_meta_boxes.ajax_url, data, function (response) {
                    if (true === response.success) {
                        wc_wallet_payment_order_items.reload_items();

                        if ('fully_refunded' === response.data.status) {
                            // Redirect to same page for show the refunded status
                            window.location.href = window.location.href;
                        }
                    } else {
                        window.alert(response.data.error);
                        wc_wallet_payment_order_items.unblock();
                    }
                });
            } else {
                wc_wallet_payment_order_items.unblock();
            }
        },
        block: function () {
            $('#woocommerce-order-items').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },
        unblock: function () {
            $('#woocommerce-order-items').unblock();
        },
        reload_items: function () {
            var data = {
                order_id: woocommerce_admin_meta_boxes.post_id,
                action: 'woocommerce_load_order_items',
                security: woocommerce_admin_meta_boxes.order_item_nonce
            };

            wc_wallet_payment_order_items.block();

            $.ajax({
                url: woocommerce_admin_meta_boxes.ajax_url,
                data: data,
                type: 'POST',
                success: function (response) {
                    $('#woocommerce-order-items').find('.inside').empty();
                    $('#woocommerce-order-items').find('.inside').append(response);
                    wc_wallet_payment_order_items.init_tiptip();
                    wc_wallet_payment_order_items.unblock();
                    wc_wallet_payment_order_items.init();
                }
            });
        },
        init_tiptip: function () {
            $('#tiptip_holder').removeAttr('style');
            $('#tiptip_arrow').removeAttr('style');
            $('.tips').tipTip({
                'attribute': 'data-tip',
                'fadeIn': 50,
                'fadeOut': 50,
                'delay': 200
            });
        }
    };
    wc_wallet_payment_order_items.init();
});