/**
 * product-codes.js — Thống kê mã định danh theo sản phẩm + biến thể
 * @package tgs_product_identifier
 */
(function ($) {
    'use strict';

    var currentPage = 1;
    var perPage     = parseInt($('#pcPerPage').val(), 10) || 20;
    var searchTimer = null;

    // Modal state
    var modalSku       = '';
    var modalComboHash = '';
    var modalExpDate   = '';
    var modalLotCode   = '';
    var modalPage      = 1;
    var modalPerPage   = 50;
    var modalProductName = '';

    /* =====================================================================
     * HELPERS
     * ===================================================================== */
    function ajax(action, data, done, fail) {
        data.action = action;
        data.nonce  = tgsIdtf.nonce;
        if (!data.blog_id) data.blog_id = tgsIdtf.blogId;
        $.post(tgsIdtf.ajaxUrl, data)
            .done(function (r) {
                if (r.success) { if (done) done(r.data); }
                else {
                    var msg = (r.data && r.data.message) ? r.data.message : 'Lỗi không xác định';
                    toast(msg, 'error');
                    if (fail) fail(msg);
                }
            })
            .fail(function () { toast('Lỗi máy chủ', 'error'); if (fail) fail(); });
    }
    function esc(s) { return $('<span>').text(s || '').html(); }
    function toast(msg, type) {
        var $t = $('<div class="idtf-toast ' + (type === 'error' ? 'toast-error' : 'toast-success') + '">' + esc(msg) + '</div>').appendTo('body');
        setTimeout(function () { $t.addClass('show'); }, 30);
        setTimeout(function () { $t.removeClass('show'); setTimeout(function () { $t.remove(); }, 300); }, 3000);
    }
    function fmtDate(iso) {
        if (!iso) return '—';
        var p = iso.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
    }
    function statusBadge(st) {
        if (st === 1) return '<span class="badge bg-label-success">Đã định danh</span>';
        if (st === 0) return '<span class="badge bg-label-info">Đã bán</span>';
        return '<span class="badge bg-label-secondary">' + st + '</span>';
    }

    /* =====================================================================
     * QUICK BARCODE SEARCH
     * ===================================================================== */
    function doQuickSearch() {
        var val = $.trim($('#pcQuickBarcode').val());
        if (val.length < 5) return;
        ajax('tgs_idtf_search_code', { barcode: val }, function (d) {
            var $r = $('#pcQuickResult').show();
            if (!d.found) {
                $r.html('<div class="pc-qr-card pc-qr-notfound"><i class="bx bx-x-circle me-1"></i>Không tìm thấy mã: <b>' + esc(val) + '</b></div>');
                return;
            }
            var st = parseInt(d.status, 10);
            var stClass = st === 1 ? 'bg-label-success' : (st === 0 ? 'bg-label-info' : 'bg-label-warning');
            var stLabel = st === 100 ? 'Mã trống' : (st === 1 ? 'Đã định danh' : (st === 0 ? 'Đã bán' : 'Trạng thái ' + st));

            var html = '<div class="pc-qr-card">';

            // Row 1: Barcode + Status
            html += '<div class="pc-qr-row">'
                + '<span class="badge bg-label-primary" style="font-size:13px;"><i class="bx bx-barcode me-1"></i>' + esc(d.barcode) + '</span> '
                + '<span class="badge ' + stClass + '" style="font-size:13px;">' + stLabel + '</span>'
                + '</div>';

            // Row 2: Product name + SKU
            if (d.product_name || d.sku) {
                html += '<div class="pc-qr-row">';
                if (d.product_name) html += '<i class="bx bx-package text-primary me-1"></i><b style="font-size:14px;">' + esc(d.product_name) + '</b>';
                if (d.sku) html += ' <span class="text-muted ms-2" style="font-size:12px;">SKU: ' + esc(d.sku) + '</span>';
                html += '</div>';
            }

            // Row 3: Variants
            if (d.variants && d.variants.length) {
                html += '<div class="pc-qr-row"><i class="bx bx-category text-muted me-1" style="font-size:13px;"></i>';
                $.each(d.variants, function (_, v) {
                    var lbl = v.variant_label || v.label || '';
                    var val2 = v.variant_value || v.value || '';
                    if (lbl || val2) {
                        html += '<span class="badge bg-label-primary me-1" style="font-size:11px;">' + esc(lbl) + ': ' + esc(val2) + '</span>';
                    }
                });
                html += '</div>';
            }

            // Row 4: HSD + Lot
            if (d.exp_date || d.lot_code) {
                html += '<div class="pc-qr-row">';
                if (d.exp_date) html += '<span class="badge bg-label-warning me-1" style="font-size:11px;"><i class="bx bx-calendar me-1"></i>HSD: ' + fmtDate(d.exp_date) + '</span>';
                if (d.lot_code) html += '<span class="badge bg-label-info me-1" style="font-size:11px;"><i class="bx bx-box me-1"></i>Lô: ' + esc(d.lot_code) + '</span>';
                html += '</div>';
            }

            // Row 5: Assignment info (phiếu + khối)
            if (d.assignment) {
                var a = d.assignment;
                html += '<div class="pc-qr-row" style="font-size:12px; color:#8592a3;">'
                    + '<i class="bx bx-file me-1"></i>Phiếu: ';
                if (a.ledger_code) html += '<b>' + esc(a.ledger_code) + '</b>';
                else html += '#' + esc(a.ledger_id);
                if (a.ledger_title) html += ' — ' + esc(a.ledger_title);
                if (a.block_position) {
                    html += ' · <i class="bx bx-package me-1"></i>Khối ' + a.block_position + '/' + a.total_blocks;
                    if (a.block_label) html += ' (' + esc(a.block_label) + ')';
                }
                html += '</div>';
            }

            html += '</div>';
            $r.html(html);
        });
    }

    $('#btnPcQuickSearch').on('click', doQuickSearch);
    $('#pcQuickBarcode').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doQuickSearch(); }
    });

    /* =====================================================================
     * PRODUCT LIST
     * ===================================================================== */
    function loadProducts(page) {
        currentPage = page || 1;
        var $c = $('#pcProductList').html('<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Đang tải...</div>');
        ajax('tgs_idtf_get_product_codes_list', {
            search: $.trim($('#pcProductSearch').val()),
            page: currentPage,
            per_page: perPage
        }, function (d) {
            renderProducts(d.products);
            renderPager(d.page, d.pages, d.total);
        });
    }

    function renderProducts(products) {
        var $c = $('#pcProductList').empty();
        if (!products || !products.length) {
            $c.html('<div class="card"><div class="card-body text-center py-5 text-muted"><i class="bx bx-package" style="font-size:40px; color:#d4d8dd;"></i><p class="mt-2 mb-0">Chưa có sản phẩm nào được định danh.</p></div></div>');
            return;
        }

        $.each(products, function (_, p) {
            var totalCodes = parseInt(p.total_codes) || 0;
            var identifiedCount = parseInt(p.identified_count) || 0;
            var soldCount = parseInt(p.sold_count) || 0;

            // Build combo rows
            var comboHtml = '';
            if (p.combos && p.combos.length) {
                $.each(p.combos, function (_, c) {
                    var varLabel = '';
                    if (!c.variant_combo_hash) {
                        varLabel = '<span class="text-muted" style="font-size:12px;">Không biến thể</span>';
                    } else if (c.variants && c.variants.length) {
                        $.each(c.variants, function (_, v) {
                            varLabel += '<span class="badge bg-label-primary me-1" style="font-size:10px;">' + esc(v.label) + ': ' + esc(v.value) + '</span>';
                        });
                    } else {
                        varLabel = '<span class="text-muted" style="font-size:12px;">Combo #' + esc(c.variant_combo_hash).substring(0, 8) + '</span>';
                    }

                    comboHtml += '<tr class="pc-combo-row" data-sku="' + esc(p.local_product_sku) + '" data-combo="' + (c.variant_combo_hash || 'null') + '" style="cursor:pointer;">'
                        + '<td>' + varLabel + '</td>'
                        + '<td class="text-center"><span class="badge bg-label-success">' + c.active_count + '</span></td>'
                        + '<td class="text-center"><span class="badge bg-label-info">' + c.sold_count + '</span></td>'
                        + '<td class="text-center fw-semibold">' + c.codes_count + '</td>'
                        + '<td class="text-end"><button class="btn btn-sm btn-outline-primary btn-pc-view" data-sku="' + esc(p.local_product_sku) + '" data-combo="' + (c.variant_combo_hash || 'null') + '" data-name="' + esc(p.local_product_name) + '"><i class="bx bx-show me-1"></i>Xem mã</button></td>'
                        + '</tr>';
                });
            }

            // No-combo row (view all)
            comboHtml += '<tr class="pc-combo-row table-light" data-sku="' + esc(p.local_product_sku) + '" data-combo="" style="cursor:pointer;">'
                + '<td><span class="fw-semibold text-primary" style="font-size:12px;"><i class="bx bx-collection me-1"></i>Tất cả (không lọc biến thể)</span></td>'
                + '<td class="text-center"><span class="badge bg-label-success">' + identifiedCount + '</span></td>'
                + '<td class="text-center"><span class="badge bg-label-info">' + soldCount + '</span></td>'
                + '<td class="text-center fw-semibold">' + totalCodes + '</td>'
                + '<td class="text-end"><button class="btn btn-sm btn-primary btn-pc-view" data-sku="' + esc(p.local_product_sku) + '" data-combo="" data-name="' + esc(p.local_product_name) + '"><i class="bx bx-show me-1"></i>Xem tất cả</button></td>'
                + '</tr>';

            $c.append(
                '<div class="card mb-3 pc-product-card">'
                + '<div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center">'
                + '  <div>'
                + '    <span class="fw-semibold">' + esc(p.local_product_name) + '</span>'
                + '    <div style="font-size:11px; color:#8592a3;">'
                + '      SKU: <b>' + esc(p.local_product_sku || '—') + '</b>'
                + (p.local_product_barcode_main ? ' · Barcode: ' + esc(p.local_product_barcode_main) : '')
                + '    </div>'
                + '  </div>'
                + '  <div class="d-flex gap-2">'
                + '    <span class="badge bg-label-success"><i class="bx bx-check me-1"></i>' + identifiedCount + ' định danh</span>'
                + '    <span class="badge bg-label-info"><i class="bx bx-cart me-1"></i>' + soldCount + ' đã bán</span>'
                + '    <span class="badge bg-primary"><i class="bx bx-barcode me-1"></i>' + totalCodes + ' tổng mã</span>'
                + '  </div>'
                + '</div>'
                + '<div class="card-body p-0">'
                + '  <table class="table table-sm mb-0">'
                + '    <thead class="table-light" style="font-size:12px;">'
                + '      <tr><th>Biến thể / Combo</th><th class="text-center" style="width:90px;">Định danh</th><th class="text-center" style="width:80px;">Đã bán</th><th class="text-center" style="width:70px;">Tổng</th><th style="width:110px;"></th></tr>'
                + '    </thead>'
                + '    <tbody>' + comboHtml + '</tbody>'
                + '  </table>'
                + '</div>'
                + '</div>'
            );
        });
    }

    function renderPager(page, pages, total) {
        $('#pcPageInfo').text('Tổng ' + total + ' sản phẩm');
        var $pager = $('#pcPager').empty();
        if (pages <= 1) return;
        if (page > 1) {
            $pager.append('<li class="page-item"><a class="page-link" href="#" data-page="' + (page - 1) + '">&laquo;</a></li>');
        }
        var start = Math.max(1, page - 3);
        var end = Math.min(pages, page + 3);
        for (var p = start; p <= end; p++) {
            $pager.append('<li class="page-item' + (p === page ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>');
        }
        if (page < pages) {
            $pager.append('<li class="page-item"><a class="page-link" href="#" data-page="' + (page + 1) + '">&raquo;</a></li>');
        }
    }

    // Events
    $('#pcProductSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadProducts(1); }, 400);
    });
    $('#pcPerPage').on('change', function () {
        perPage = parseInt($(this).val(), 10) || 20;
        loadProducts(1);
    });
    $(document).on('click', '#pcPager .page-link', function (e) {
        e.preventDefault();
        loadProducts(parseInt($(this).data('page'), 10));
    });

    // Init
    loadProducts(1);

    /* =====================================================================
     * MODAL: VIEW CODES
     * ===================================================================== */
    $(document).on('click', '.btn-pc-view', function () {
        modalSku       = $(this).data('sku');
        modalComboHash = $(this).data('combo');
        modalProductName = $(this).data('name');
        modalExpDate   = '';
        modalLotCode   = '';
        modalPage      = 1;
        modalPerPage   = parseInt($('#pcFilterPerPage').val(), 10) || 50;

        // Reset filter dropdowns
        $('#pcFilterExpDate').html('<option value="">-- HSD --</option>');
        $('#pcFilterLotCode').html('<option value="">-- Mã lô --</option>');

        // Show product info + combo info
        $('#pcModalProductInfo').html(
            '<div class="d-flex align-items-center gap-2">'
            + '<i class="bx bx-package" style="font-size:22px; color:#696cff;"></i>'
            + '<div><div class="fw-semibold">' + esc(modalProductName) + '</div>'
            + '<div style="font-size:11px; color:#8592a3;">SKU: <b>' + esc(modalSku) + '</b></div></div>'
            + '</div>'
        );

        var comboLabel = '';
        if (modalComboHash === '' || modalComboHash === undefined) {
            comboLabel = '<span class="badge bg-primary"><i class="bx bx-collection me-1"></i>Tất cả mã (không lọc biến thể)</span>';
        } else if (modalComboHash === 'null') {
            comboLabel = '<span class="badge bg-label-secondary">Không biến thể</span>';
        } else {
            // We'll get variant labels from the loaded data
            var $btn = $(this);
            var $row = $btn.closest('tr');
            var varBadges = $row.find('.badge.bg-label-primary');
            if (varBadges.length) {
                varBadges.each(function () { comboLabel += '<span class="badge bg-label-primary me-1">' + $(this).html() + '</span>'; });
            } else {
                comboLabel = '<span class="badge bg-label-secondary">Combo: ' + esc(String(modalComboHash).substring(0, 8)) + '…</span>';
            }
        }
        $('#pcModalComboInfo').html(comboLabel);

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProductCodes')).show();
        loadModalCodes();
    });

    // Filters
    $('#pcFilterExpDate').on('change', function () {
        modalExpDate = $(this).val();
        modalPage = 1;
        loadModalCodes();
    });
    $('#pcFilterLotCode').on('change', function () {
        modalLotCode = $(this).val();
        modalPage = 1;
        loadModalCodes();
    });
    $('#pcFilterPerPage').on('change', function () {
        modalPerPage = parseInt($(this).val(), 10) || 50;
        modalPage = 1;
        loadModalCodes();
    });

    function loadModalCodes() {
        var $tb = $('#pcModalCodesBody').html('<tr><td colspan="7" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Đang tải...</td></tr>');
        ajax('tgs_idtf_get_product_codes_detail', {
            sku: modalSku,
            combo_hash: modalComboHash === undefined ? '' : String(modalComboHash),
            exp_date: modalExpDate,
            lot_code: modalLotCode,
            page: modalPage,
            per_page: modalPerPage
        }, function (d) {
            renderModalCodes(d.lots, d.page, d.pages, d.total);
            // Update filter dropdowns (only on first load or when combo changes)
            if (d.filter_exp_dates) {
                var curExp = $('#pcFilterExpDate').val();
                var $sel = $('#pcFilterExpDate').html('<option value="">-- Tất cả HSD --</option>');
                $.each(d.filter_exp_dates, function (_, dt) {
                    $sel.append('<option value="' + dt + '"' + (dt === curExp ? ' selected' : '') + '>' + fmtDate(dt) + '</option>');
                });
            }
            if (d.filter_lot_codes) {
                var curLot = $('#pcFilterLotCode').val();
                var $sel2 = $('#pcFilterLotCode').html('<option value="">-- Tất cả mã lô --</option>');
                $.each(d.filter_lot_codes, function (_, lc) {
                    $sel2.append('<option value="' + esc(lc) + '"' + (lc === curLot ? ' selected' : '') + '>' + esc(lc) + '</option>');
                });
            }
        });
    }

    function renderModalCodes(rows, page, pages, total) {
        var $tb = $('#pcModalCodesBody').empty();
        if (!rows || !rows.length) {
            $tb.html('<tr><td colspan="7" class="text-center text-muted py-3">Không có mã nào.</td></tr>');
            $('#pcModalCodesInfo').text('');
            $('#pcModalPager').empty();
            return;
        }

        $.each(rows, function (i, r) {
            var st = parseInt(r.local_product_lot_is_active, 10);
            var varTags = '';
            if (r.variants && r.variants.length) {
                $.each(r.variants, function (_, v) {
                    varTags += '<span class="badge bg-label-primary me-1" style="font-size:10px;">' + esc(v.label) + ': ' + esc(v.value) + '</span>';
                });
            } else {
                varTags = '<span class="text-muted" style="font-size:11px;">—</span>';
            }

            $tb.append(
                '<tr>'
                + '<td>' + ((page - 1) * modalPerPage + i + 1) + '</td>'
                + '<td><code>' + esc(r.global_product_lot_barcode) + '</code></td>'
                + '<td>' + statusBadge(st) + '</td>'
                + '<td>' + varTags + '</td>'
                + '<td>' + fmtDate(r.exp_date) + '</td>'
                + '<td>' + esc(r.lot_code || '—') + '</td>'
                + '<td style="font-size:11px;">' + (r.updated_at ? r.updated_at.substring(0, 16) : '—') + '</td>'
                + '</tr>'
            );
        });

        $('#pcModalCodesInfo').text('Hiển thị ' + rows.length + ' / ' + total + ' mã');

        // Pager
        var $pager = $('#pcModalPager').empty();
        if (pages <= 1) return;
        if (page > 1) $pager.append('<li class="page-item"><a class="page-link pc-modal-page" href="#" data-page="' + (page - 1) + '">&laquo;</a></li>');
        var start = Math.max(1, page - 3), end = Math.min(pages, page + 3);
        for (var p = start; p <= end; p++) {
            $pager.append('<li class="page-item' + (p === page ? ' active' : '') + '"><a class="page-link pc-modal-page" href="#" data-page="' + p + '">' + p + '</a></li>');
        }
        if (page < pages) $pager.append('<li class="page-item"><a class="page-link pc-modal-page" href="#" data-page="' + (page + 1) + '">&raquo;</a></li>');
    }

    $(document).on('click', '.pc-modal-page', function (e) {
        e.preventDefault();
        modalPage = parseInt($(this).data('page'), 10);
        loadModalCodes();
    });

    /* =====================================================================
     * EXPORT EXCEL
     * ===================================================================== */
    $('#btnPcExport').on('click', function () {
        if (!modalSku) return;
        var url = tgsIdtf.ajaxUrl + '?action=tgs_idtf_export_product_codes'
            + '&nonce=' + encodeURIComponent(tgsIdtf.nonce)
            + '&sku=' + encodeURIComponent(modalSku)
            + '&combo_hash=' + encodeURIComponent(modalComboHash === undefined ? '' : String(modalComboHash))
            + '&exp_date=' + encodeURIComponent(modalExpDate || '')
            + '&lot_code=' + encodeURIComponent(modalLotCode || '');
        window.open(url, '_blank');
    });

})(jQuery);
