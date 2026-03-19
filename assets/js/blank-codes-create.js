/**
 * blank-codes-create.js — Sinh mã định danh trống
 * @package tgs_product_identifier
 */
(function ($) {
    'use strict';

    var BATCH_SIZE = 200;

    /* ── Form submit ──────────────────────────────────────────── */
    $('#blankCodeForm').on('submit', function (e) {
        e.preventDefault();

        var qty = parseInt($('#blankQty').val(), 10);
        if (!qty || qty < 1 || qty > 10000) {
            showError('Số lượng phải từ 1 đến 10.000.');
            return;
        }

        $('#genOverlay').show();
        $('#btnGenBlank').prop('disabled', true);

        runBatch({
            title: $.trim($('#blankTitle').val()),
            note: $.trim($('#blankNote').val()),
            quantity: qty,
            offset: 0,
            ledger_id: 0
        });
    });

    /* ── Batch runner ─────────────────────────────────────────── */
    function runBatch(params) {
        $.post(tgsIdtf.ajaxUrl, {
            action: 'tgs_idtf_generate_blank_codes',
            nonce: tgsIdtf.nonce,
            title: params.title,
            note: params.note,
            quantity: params.quantity,
            batch_size: BATCH_SIZE,
            offset: params.offset,
            ledger_id: params.ledger_id,
            blog_id: tgsIdtf.blogId
        })
        .done(function (res) {
            if (!res.success) {
                showError(res.data && res.data.message ? res.data.message : 'Lỗi không xác định.');
                resetForm();
                return;
            }

            var d = res.data;
            var pct = Math.round((d.offset / d.total) * 100);
            $('#genProgressBar').css('width', pct + '%');
            $('#genProgressCount').text(d.offset + ' / ' + d.total);
            $('#genProgressText').text(d.done ? 'Hoàn tất!' : 'Đang sinh mã...');

            if (d.done) {
                setTimeout(function () {
                    // Redirect to detail page
                    var url = buildUrl('idtf-blank-detail') + '&ledger_id=' + d.ledger_id;
                    window.location.href = url;
                }, 600);
            } else {
                runBatch({
                    title: params.title,
                    note: params.note,
                    quantity: params.quantity,
                    offset: d.offset,
                    ledger_id: d.ledger_id
                });
            }
        })
        .fail(function () {
            showError('Lỗi kết nối máy chủ.');
            resetForm();
        });
    }

    /* ── Helpers ──────────────────────────────────────────────── */
    function resetForm() {
        $('#genOverlay').hide();
        $('#btnGenBlank').prop('disabled', false);
        $('#genProgressBar').css('width', '0%');
    }

    function showError(msg) {
        var $b = $('#errorBanner').text(msg).show();
        setTimeout(function () { $b.fadeOut(); }, 4000);
    }

    function buildUrl(view) {
        return window.location.pathname + '?page=tgs-shop-management&view=' + view;
    }

    /* ── Quick quantity preset chips ─────────────────────────── */
    $(document).on('click', '.idtf-qty-chip', function () {
        var qty = parseInt($(this).data('qty'), 10);
        $('#blankQty').val(qty).trigger('change');
        $('.idtf-qty-chip').removeClass('active');
        $(this).addClass('active');
    });

    // Sync chip state when user types manually
    $('#blankQty').on('input change', function () {
        var qty = parseInt($(this).val(), 10) || 0;
        $('.idtf-qty-chip').each(function () {
            $(this).toggleClass('active', parseInt($(this).data('qty')) === qty);
        });
        updatePaperEstimate(qty);
    });

    function updatePaperEstimate(qty) {
        if (!qty || qty <= 0) { $('#paperEstimate').text(''); return; }
        // 2 barcodes per row, ~14 rows per 70×22mm label sheet (A4)
        var rows = Math.ceil(qty / 2);
        var pages = Math.ceil(rows / 14);
        var txt = '~ ' + pages + ' trang giấy';
        if (qty <= 10) txt += ' (ít mã)';
        else if (qty >= 5000) txt += ' — nên chia nhỏ nếu cần';
        $('#paperEstimate').text(txt);
    }

    // Init estimate
    updatePaperEstimate(parseInt($('#blankQty').val(), 10) || 100);

})(jQuery);
