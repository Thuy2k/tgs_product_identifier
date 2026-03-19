/**
 * blank-codes-detail.js — Chi tiết phiếu sinh mã trống
 * @package tgs_product_identifier
 */
(function ($) {
    'use strict';

    var ledgerId = parseInt($('#detailLedgerId').val(), 10);
    var currentPage = 1;
    var selected = {};   // lot_id → barcode
    var searchTimer = null;

    if (!ledgerId) return;

    /* ── Init ──────────────────────────────────────────────────── */
    loadDetail();
    loadLots(1);

    /* ── Search ────────────────────────────────────────────────── */
    $('#lotSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { currentPage = 1; loadLots(1); }, 400);
    });

    /* ── Select / Deselect ─────────────────────────────────────── */
    $('#btnSelectAll').on('click', function () {
        $('#lotsTableBody .lot-check').each(function () {
            $(this).prop('checked', true);
            var id = $(this).data('lotid');
            selected[id] = $(this).data('barcode');
        });
        updateSelectedUI();
    });

    $('#btnDeselectAll').on('click', function () {
        $('#lotsTableBody .lot-check').prop('checked', false);
        selected = {};
        updateSelectedUI();
    });

    $('#checkAll').on('change', function () {
        var checked = $(this).prop('checked');
        $('#lotsTableBody .lot-check').each(function () {
            $(this).prop('checked', checked);
            var id = $(this).data('lotid');
            if (checked) {
                selected[id] = $(this).data('barcode');
            } else {
                delete selected[id];
            }
        });
        updateSelectedUI();
    });

    $(document).on('change', '.lot-check', function () {
        var id = $(this).data('lotid');
        if ($(this).prop('checked')) {
            selected[id] = $(this).data('barcode');
        } else {
            delete selected[id];
        }
        updateSelectedUI();
    });

    /* ── Print ─────────────────────────────────────────────────── */
    $('#btnPrintSelected').on('click', function () {
        var barcodes = Object.values(selected);
        if (!barcodes.length) return;

        // Nếu ít mã (< 200) → GET trực tiếp, nhanh hơn
        if (barcodes.length < 200) {
            var url = tgsIdtf.ajaxUrl + '?action=tgs_idtf_print_blank_codes'
                + '&nonce=' + encodeURIComponent(tgsIdtf.nonce)
                + '&barcodes=' + encodeURIComponent(barcodes.join(','));
            window.open(url, '_blank');
            return;
        }

        // Nhiều mã → lưu tạm qua AJAX (transient) rồi mở bằng key
        $.post(tgsIdtf.ajaxUrl, {
            action: 'tgs_idtf_prepare_print',
            nonce: tgsIdtf.nonce,
            barcodes: JSON.stringify(barcodes)
        }).done(function (res) {
            if (res.success && res.data.print_key) {
                var url = tgsIdtf.ajaxUrl + '?action=tgs_idtf_print_blank_codes'
                    + '&nonce=' + encodeURIComponent(tgsIdtf.nonce)
                    + '&print_key=' + encodeURIComponent(res.data.print_key);
                window.open(url, '_blank');
            }
        });
    });

    /* ── Load detail ───────────────────────────────────────────── */
    function loadDetail() {
        $.post(tgsIdtf.ajaxUrl, {
            action: 'tgs_idtf_get_blank_detail',
            nonce: tgsIdtf.nonce,
            ledger_id: ledgerId
        })
        .done(function (res) {
            if (!res.success) return;
            var d = res.data, l = d.ledger, s = d.stats;
            $('#ticketCode').text(l.local_ledger_code || '—');
            $('#infoTitle').text(l.local_ledger_title || '—');
            $('#infoUser').text(l.user_name || '—');
            $('#infoDate').text(l.created_at ? l.created_at.substring(0, 16) : '—');
            $('#infoNote').text(l.local_ledger_note || '—');
            $('#statTotal').text(s.total);
            $('#statBlank').text(s.blank);
            $('#statIdentified').text(s.identified);
            $('#statSold').text(s.sold);
        });
    }

    /* ── Load lots ─────────────────────────────────────────────── */
    function loadLots(page) {
        $.post(tgsIdtf.ajaxUrl, {
            action: 'tgs_idtf_get_blank_lots',
            nonce: tgsIdtf.nonce,
            ledger_id: ledgerId,
            page: page,
            per_page: 100,
            search: $.trim($('#lotSearch').val())
        })
        .done(function (res) {
            if (!res.success) return;
            var d = res.data;
            currentPage = d.page;
            renderLots(d.lots);
            renderLotsPager(d.page, d.pages, d.total);
        });
    }

    /* ── Render lots table ─────────────────────────────────────── */
    function renderLots(rows) {
        var $tb = $('#lotsTableBody').empty();
        if (!rows || !rows.length) {
            $tb.html('<tr><td colspan="6" class="text-center py-4 text-muted">Không có dữ liệu.</td></tr>');
            return;
        }

        $.each(rows, function (i, r) {
            var st = parseInt(r.local_product_lot_is_active, 10);
            var badge = statusBadge(st, r.local_product_name);
            var isChecked = selected[r.global_product_lot_id] ? ' checked' : '';
            var dateStr = r.created_at ? r.created_at.substring(0, 16) : '—';

            $tb.append(
                '<tr class="lot-row' + (isChecked ? ' selected' : '') + '">'
                + '<td><input type="checkbox" class="form-check-input lot-check" data-lotid="' + r.global_product_lot_id + '" data-barcode="' + esc(r.global_product_lot_barcode) + '"' + isChecked + ' /></td>'
                + '<td>' + ((currentPage - 1) * 100 + i + 1) + '</td>'
                + '<td><code>' + esc(r.global_product_lot_barcode) + '</code></td>'
                + '<td>' + badge + '</td>'
                + '<td>' + esc(r.local_product_name || '—') + '</td>'
                + '<td>' + dateStr + '</td>'
                + '</tr>'
            );
        });
    }

    function statusBadge(st, productName) {
        if (st === 100) return '<span class="badge bg-label-warning">Trống</span>';
        if (st === 1) return '<span class="badge bg-label-success">Đã định danh</span>';
        if (st === 0) return '<span class="badge bg-label-info">Đã bán</span>';
        return '<span class="badge bg-label-secondary">' + st + '</span>';
    }

    /* ── Lots pager ────────────────────────────────────────────── */
    function renderLotsPager(page, pages, total) {
        $('#lotsInfo').text('Tổng ' + total + ' mã');
        var $pager = $('#lotsPager').empty();
        if (pages <= 1) return;

        for (var p = 1; p <= pages; p++) {
            $pager.append(
                '<li class="page-item' + (p === page ? ' active' : '') + '">'
                + '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>'
            );
        }

        $pager.on('click', '.page-link', function (e) {
            e.preventDefault();
            loadLots(parseInt($(this).data('page'), 10));
        });
    }

    /* ── UI ─────────────────────────────────────────────────────── */
    function updateSelectedUI() {
        var count = Object.keys(selected).length;
        $('#selectedCount, #printCount').text(count);
        if (count > 0) {
            $('#selectedBadge').show();
            $('#btnPrintSelected').prop('disabled', false);
        } else {
            $('#selectedBadge').hide();
            $('#btnPrintSelected').prop('disabled', true);
        }
    }

    function esc(s) { return $('<span>').text(s || '').html(); }

})(jQuery);
