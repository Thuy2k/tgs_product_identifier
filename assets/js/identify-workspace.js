/**
 * identify-workspace.js — Workspace Định danh sản phẩm (MÀN HÌNH CHÍNH)
 * @package tgs_product_identifier
 */
(function ($) {
    'use strict';

    /* =====================================================================
     * STATE
     * ===================================================================== */
    var openTabs   = [];         // [{ledger_id, title, ticket_code}]
    var activeTab  = null;       // ledger_id
    var searchTimer = null;
    var scanPending = [];        // [{lot_id, barcode}]
    var currentScanBlockId = 0;
    var viewBlockId = 0;
    var viewCodesPage = 1;
    var blocksMap = {};           // {block_id: blockData} — for modal product info

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
                    var msg = (r.data && r.data.message) ? r.data.message : (typeof r.data === 'string' ? r.data : 'Lỗi không xác định');
                    toast(msg, 'error');
                    if (fail) fail(msg);
                }
            })
            .fail(function (xhr) {
                var msg = 'Lỗi máy chủ';
                if (xhr.status) msg += ' (' + xhr.status + ')';
                // Thử parse response body
                try {
                    var body = JSON.parse(xhr.responseText);
                    if (body && body.data && body.data.message) msg = body.data.message;
                    else if (typeof body.data === 'string') msg = body.data;
                } catch (e) {
                    if (xhr.responseText && xhr.responseText.length < 300) msg += ': ' + xhr.responseText.substring(0, 200);
                }
                toast(msg, 'error');
                if (fail) fail(msg);
            });
    }
    function esc(s) { return $('<span>').text(s || '').html(); }
    function toast(msg, type) {
        var $t = $('<div class="idtf-toast ' + (type === 'error' ? 'toast-error' : 'toast-success') + '">' + esc(msg) + '</div>').appendTo('body');
        setTimeout(function () { $t.addClass('show'); }, 30);
        setTimeout(function () { $t.removeClass('show'); setTimeout(function () { $t.remove(); }, 300); }, 3000);
    }
    function buildUrl(view) { return window.location.pathname + '?page=tgs-shop-management&view=' + view; }

    /**
     * Render product info banner inside scan/view modal
     */
    function renderModalProductInfo(selector, blockId) {
        var $el = $(selector);
        var b = blocksMap[blockId];
        if (!b) { $el.hide(); return; }

        var varHtml = '';
        if (b.variants && b.variants.length) {
            $.each(b.variants, function (_, v) {
                varHtml += '<span class="badge bg-label-primary me-1" style="font-size:11px;">'
                    + esc(v.variant_label) + ': ' + esc(v.variant_value) + '</span>';
            });
        }

        var metaBadges = '';
        if (b.exp_date) {
            var ep = b.exp_date.split('-');
            metaBadges += '<span class="badge bg-label-warning me-1" style="font-size:11px;"><i class="bx bx-calendar me-1"></i>HSD: ' + ep[2] + '/' + ep[1] + '/' + ep[0] + '</span>';
        }
        if (b.lot_code) {
            metaBadges += '<span class="badge bg-label-info me-1" style="font-size:11px;"><i class="bx bx-purchase-tag me-1"></i>Lô: ' + esc(b.lot_code) + '</span>';
        }

        $el.html(
            '<div class="d-flex align-items-start gap-2">'
            + '<i class="bx bx-package" style="font-size:22px; color:#696cff; margin-top:2px;"></i>'
            + '<div class="flex-grow-1" style="min-width:0;">'
            + '  <div class="fw-semibold" style="font-size:13px;">' + esc(b.local_product_name || 'Sản phẩm #' + b.local_product_name_id) + '</div>'
            + '  <div style="font-size:11px; color:#8592a3;">SKU: <b>' + esc(b.local_product_sku || '—') + '</b>'
            + (b.local_product_barcode_main ? ' · Barcode: ' + esc(b.local_product_barcode_main) : '') + '</div>'
            + (varHtml ? '<div class="mt-1">' + varHtml + '</div>' : '')
            + (metaBadges ? '<div class="mt-1">' + metaBadges + '</div>' : '')
            + '</div>'
            + '</div>'
        ).show();
    }

    /* =====================================================================
     * SOUND FEEDBACK — Tiếng beep khi quét mã
     * ===================================================================== */
    var audioCtx = null;
    function getAudioCtx() {
        if (!audioCtx) {
            try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
        }
        return audioCtx;
    }
    function playBeep(freq, duration, type) {
        var ctx = getAudioCtx();
        if (!ctx) return;
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = freq;
        osc.type = type || 'sine';
        gain.gain.value = 0.3;
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
        osc.stop(ctx.currentTime + duration);
    }
    function beepSuccess() { playBeep(880, 0.15, 'sine'); }
    function beepError()   { playBeep(200, 0.3, 'square'); setTimeout(function(){ playBeep(150, 0.3, 'square'); }, 150); }
    function beepWarning() { playBeep(440, 0.2, 'triangle'); }

    /* =====================================================================
     * A. SIDEBAR — Phiếu định danh
     * ===================================================================== */
    function loadSidebar(search) {
        ajax('tgs_idtf_get_identify_ledgers', { search: search || '' }, function (d) {
            var $list = $('#sidebarList').empty();
            if (!d.ledgers || !d.ledgers.length) {
                $list.html('<div class="text-center py-3" style="font-size:12px; color:#8592a3;">'
                    + '<i class="bx bx-folder-open" style="font-size:28px; display:block; margin-bottom:4px;"></i>'
                    + 'Chưa có phiếu nào.<br><small>Bấm + để tạo phiếu mới</small></div>');
                return;
            }
            $.each(d.ledgers, function (_, l) {
                var cls = activeTab === parseInt(l.local_ledger_id) ? ' active' : '';
                var blocks = parseInt(l.blocks_count) || 0;
                var codes  = parseInt(l.total_codes) || 0;
                // Status dot: green=has codes, yellow=has blocks but no codes, gray=empty
                var dotCls = codes > 0 ? 'dot-green' : (blocks > 0 ? 'dot-yellow' : 'dot-gray');
                $list.append(
                    '<div class="idtf-sidebar-item' + cls + '" data-id="' + l.local_ledger_id + '">'
                    + '<div class="d-flex justify-content-between align-items-start">'
                    + '<div class="flex-grow-1 overflow-hidden">'
                    + '  <div class="item-title"><span class="idtf-status-dot ' + dotCls + '"></span>' + esc(l.local_ledger_title) + '</div>'
                    + '  <div class="item-meta">' + esc(l.local_ledger_code) + ' · ' + blocks + ' khối'
                    + (codes > 0 ? ' · ' + codes + ' mã' : '') + '</div>'
                    + '</div>'
                    + '<button class="btn btn-sm p-0 text-danger item-delete" data-id="' + l.local_ledger_id + '" data-title="' + esc(l.local_ledger_title) + '" data-codes="' + codes + '" title="Xóa"><i class="bx bx-trash"></i></button>'
                    + '</div></div>'
                );
            });
        });
    }

    // Click sidebar item → open tab
    $(document).on('click', '.idtf-sidebar-item', function (e) {
        if ($(e.target).closest('.item-delete').length) return;
        var id = parseInt($(this).data('id'));
        openLedgerTab(id);
    });

    // Delete ledger from sidebar
    $(document).on('click', '.item-delete', function (e) {
        e.stopPropagation();
        var id = parseInt($(this).data('id'));
        var title = $(this).data('title') || '';
        var codes = parseInt($(this).data('codes')) || 0;
        var msg = 'Xóa phiếu' + (title ? ' "' + title + '"' : '') + '?';
        if (codes > 0) msg += '\n\n⚠ ' + codes + ' mã đã gắn sẽ bị gỡ và trở về trạng thái mã trống.';
        if (!confirm(msg)) return;
        ajax('tgs_idtf_delete_identify_ledger', { ledger_id: id }, function () {
            toast('Đã xóa phiếu.');
            closeTab(id);
            loadSidebar();
        });
    });

    // Sidebar search
    $('#sidebarSearch').on('input', function () {
        clearTimeout(searchTimer);
        var val = $.trim($(this).val());
        searchTimer = setTimeout(function () { loadSidebar(val); }, 400);
    });

    /* =====================================================================
     * B. TAB MANAGEMENT
     * ===================================================================== */
    function openLedgerTab(ledgerId) {
        ledgerId = parseInt(ledgerId);
        var exists = openTabs.filter(function (t) { return t.ledger_id === ledgerId; });
        if (!exists.length) {
            // Need to load ledger data first to get title
            ajax('tgs_idtf_get_identify_detail', { ledger_id: ledgerId }, function (d) {
                // Re-check to prevent race condition (double-click)
                var already = openTabs.filter(function (t) { return t.ledger_id === ledgerId; });
                if (already.length) { activateTab(ledgerId); renderTabs(); return; }
                openTabs.push({
                    ledger_id: parseInt(d.ledger.local_ledger_id),
                    title: d.ledger.local_ledger_title,
                    ticket_code: d.ledger.local_ledger_code
                });
                activateTab(ledgerId);
                renderTabs();
                renderLedgerContent(d);
            });
        } else {
            activateTab(ledgerId);
            renderTabs();
            loadLedgerContent(ledgerId);
        }
    }

    function activateTab(id) {
        activeTab = parseInt(id);
        $('.idtf-sidebar-item').removeClass('active');
        $('.idtf-sidebar-item[data-id="' + id + '"]').addClass('active');
        $('#emptyState').hide();
        $('#ledgerContent').show();
    }

    function closeTab(id) {
        id = parseInt(id);
        openTabs = openTabs.filter(function (t) { return t.ledger_id !== id; });
        if (activeTab === id) {
            if (openTabs.length) {
                activateTab(openTabs[openTabs.length - 1].ledger_id);
                loadLedgerContent(activeTab);
            } else {
                activeTab = null;
                $('#ledgerContent').hide();
                $('#emptyState').show();
            }
        }
        renderTabs();
    }

    function renderTabs() {
        var $c = $('#tabsContainer').empty();
        $.each(openTabs, function (_, t) {
            var cls = t.ledger_id === activeTab ? ' active' : '';
            $c.append(
                '<div class="idtf-tab' + cls + '" data-id="' + t.ledger_id + '">'
                + '<span class="tab-label">' + esc(t.title || t.ticket_code).substring(0, 25) + '</span>'
                + '<span class="tab-close" data-id="' + t.ledger_id + '">&times;</span>'
                + '</div>'
            );
        });
    }

    // Tab click
    $(document).on('click', '.idtf-tab', function (e) {
        if ($(e.target).closest('.tab-close').length) return;
        var id = parseInt($(this).data('id'));
        activateTab(id);
        renderTabs();
        loadLedgerContent(id);
        loadSidebar();
    });

    // Tab close
    $(document).on('click', '.tab-close', function (e) {
        e.stopPropagation();
        closeTab(parseInt($(this).data('id')));
        loadSidebar();
    });

    // New tab buttons
    $('#btnAddTab, #btnNewLedger, #btnEmptyNew').on('click', function () {
        var m = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNewLedger'));
        m.show();
    });

    /* =====================================================================
     * C. NEW LEDGER MODAL
     * ===================================================================== */
    $('#btnConfirmNewLedger').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        ajax('tgs_idtf_create_identify_ledger', {
            title: $.trim($('#newLedgerTitle').val()),
            note: $.trim($('#newLedgerNote').val())
        }, function (d) {
            $btn.prop('disabled', false);
            bootstrap.Modal.getInstance(document.getElementById('modalNewLedger')).hide();
            $('#newLedgerTitle, #newLedgerNote').val('');
            toast('Đã tạo phiếu: ' + d.ticket_code);
            openTabs.push({
                ledger_id: parseInt(d.ledger_id),
                title: d.title,
                ticket_code: d.ticket_code
            });
            activateTab(d.ledger_id);
            renderTabs();
            loadLedgerContent(d.ledger_id);
            loadSidebar();
        }, function () { $btn.prop('disabled', false); });
    });

    /* =====================================================================
     * D. LEDGER CONTENT — Form + Blocks
     * ===================================================================== */
    function loadLedgerContent(ledgerId) {
        ajax('tgs_idtf_get_identify_detail', { ledger_id: ledgerId }, function (d) {
            renderLedgerContent(d);
        });
    }

    function renderLedgerContent(d) {
        var l = d.ledger;
        $('#ledgerTitle').val(l.local_ledger_title || '');
        $('#ledgerUser').val(l.user_name || tgsIdtf.userName);
        $('#ledgerDate').val(l.created_at ? l.created_at.substring(0, 16) : '—');
        $('#addBlockArea').show();

        renderBlocks(d.blocks || []);
    }

    // Save ledger title
    $('#btnSaveLedger').on('click', function () {
        toast('Phiếu đã lưu.', 'success');
    });

    /* =====================================================================
     * E. BLOCKS RENDERING
     * ===================================================================== */
    function renderBlocks(blocks) {
        var $c = $('#blocksContainer').empty();
        if (!blocks || !blocks.length) {
            $c.html('<div class="idtf-empty-blocks">'
                + '<i class="bx bx-package" style="font-size:40px; color:#d4d8dd;"></i>'
                + '<p class="text-muted mt-2 mb-0">Chưa có khối sản phẩm nào.</p>'
                + '<p class="text-muted" style="font-size:12px;">Bấm "Thêm khối sản phẩm" bên dưới để bắt đầu.</p>'
                + '</div>');
            return;
        }

        // Store blocks in map for modal product info
        blocksMap = {};
        $.each(blocks, function (_, b) { blocksMap[parseInt(b.block_id)] = b; });

        // Calculate totals for summary
        var totalCodes = 0;
        $.each(blocks, function (_, b) { totalCodes += parseInt(b.codes_count) || 0; });
        $c.append('<div class="idtf-blocks-summary mb-3">'
            + '<span class="badge bg-label-primary me-2"><i class="bx bx-cube me-1"></i>' + blocks.length + ' khối</span>'
            + '<span class="badge bg-label-success"><i class="bx bx-barcode me-1"></i>' + totalCodes + ' mã đã gắn</span>'
            + '</div>');

        $.each(blocks, function (_, b) {
            var varTags = '';
            if (b.variants && b.variants.length) {
                $.each(b.variants, function (_, v) {
                    varTags += '<span class="idtf-variant-tag">' + esc(v.variant_label) + ': ' + esc(v.variant_value) + '</span>';
                });
            }
            var count = parseInt(b.codes_count) || 0;

            var metaLine = '';
            if (b.exp_date) {
                var ep = b.exp_date.split('-');
                metaLine += '<span class="badge bg-label-warning me-1" style="font-size:10px;"><i class="bx bx-calendar me-1"></i>HSD: ' + ep[2] + '/' + ep[1] + '/' + ep[0] + '</span>';
            }
            if (b.lot_code) {
                metaLine += '<span class="badge bg-label-info me-1" style="font-size:10px;"><i class="bx bx-purchase-tag me-1"></i>Lô: ' + esc(b.lot_code) + '</span>';
            }

            $c.append(
                '<div class="idtf-block" data-block-id="' + b.block_id + '">'
                + '<div class="idtf-block-header">'
                + '  <div class="flex-grow-1">'
                + '    <div class="idtf-block-title">' + esc(b.local_product_name || 'Sản phẩm #' + b.local_product_name_id) + '</div>'
                + '    <div style="font-size:11px; color:#8592a3;">SKU: ' + esc(b.local_product_sku || '—') + '</div>'
                + '  </div>'
                + '  <div class="idtf-block-badge' + (count > 0 ? '' : ' empty') + '">'
                + '    <i class="bx bx-barcode"></i> ' + count + ' mã'
                + '  </div>'
                + '</div>'
                + '<div class="idtf-block-body">'
                + '  <div class="idtf-block-variants">' + (varTags || '<span class="text-muted" style="font-size:12px;"><i class="bx bx-info-circle me-1"></i>Không có biến thể</span>') + '</div>'
                + (metaLine ? '<div class="mt-1">' + metaLine + '</div>' : '')
                + '</div>'
                + '<div class="idtf-block-actions">'
                + '  <button class="btn btn-sm btn-primary btn-scan" data-block-id="' + b.block_id + '"><i class="bx bx-qr-scan me-1"></i>Quét mã</button>'
                + '  <button class="btn btn-sm btn-outline-info btn-view-codes" data-block-id="' + b.block_id + '" data-count="' + count + '"><i class="bx bx-list-check me-1"></i>Xem mã (' + count + ')</button>'
                + '  <button class="btn btn-sm btn-outline-danger btn-remove-block" data-block-id="' + b.block_id + '" data-count="' + count + '" data-name="' + esc(b.local_product_name || '') + '"><i class="bx bx-trash me-1"></i>Xóa</button>'
                + '</div></div>'
            );
        });
    }

    /* =====================================================================
     * F. ADD BLOCK MODAL
     * ===================================================================== */
    var blockProductTimer = null;
    var blockVariantsData = []; // loaded variant list
    var blockSelectedVars = []; // selected variant IDs

    $('#btnAddBlock').on('click', function () {
        resetAddBlockModal();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAddBlock')).show();
    });

    function resetAddBlockModal() {
        $('#blockProductSearch').val('').show();
        $('#blockProductId').val('');
        $('#blockProductDropdown').hide().empty();
        $('#blockProductInfo').hide();
        $('#blockVariantSection').hide();
        $('#blockVariantList').empty();
        $('#btnConfirmAddBlock').prop('disabled', true);
        $('#blockExpDateDisplay').val('');
        $('#blockExpDatePicker').val('');
        $('#blockExpDate').val('');
        $('#blockLotCode').val('');
        blockVariantsData = [];
        blockSelectedVars = [];
    }

    /* -- HSD date picker sync -- */
    $('#blockExpDatePicker').on('change', function () {
        var iso = $(this).val(); // yyyy-mm-dd
        if (iso) {
            var parts = iso.split('-');
            $('#blockExpDateDisplay').val(parts[2] + '/' + parts[1] + '/' + parts[0]);
            $('#blockExpDate').val(iso);
        }
    });

    $('#blockExpDateDisplay').on('input', function () {
        var v = $(this).val().replace(/[^0-9/]/g, '');
        $(this).val(v);
        // Auto-add slashes
        if (v.length === 2 && v.indexOf('/') === -1) $(this).val(v + '/');
        if (v.length === 5 && v.lastIndexOf('/') === 2) $(this).val(v + '/');
    }).on('change', function () {
        var v = $.trim($(this).val());
        var m = v.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (m) {
            var iso = m[3] + '-' + m[2] + '-' + m[1];
            $('#blockExpDate').val(iso);
            $('#blockExpDatePicker').val(iso);
        } else {
            $('#blockExpDate').val('');
            $('#blockExpDatePicker').val('');
        }
    });

    // Product search in Add Block modal
    $('#blockProductSearch').on('input', function () {
        clearTimeout(blockProductTimer);
        var val = $.trim($(this).val());
        if (val.length < 2) { $('#blockProductDropdown').hide(); return; }
        blockProductTimer = setTimeout(function () {
            ajax('tgs_idtf_search_products', { keyword: val }, function (d) {
                renderProductDropdown(d.products, '#blockProductDropdown', function (p) {
                    selectBlockProduct(p);
                });
            });
        }, 300);
    });

    function renderProductDropdown(products, selector, onSelect) {
        var $dd = $(selector).empty();
        if (!products || !products.length) {
            $dd.html('<div class="p-3 text-center text-muted" style="font-size:12px;">Không tìm thấy sản phẩm.</div>'
                + '<div class="idtf-dd-add" id="ddAddProduct"><i class="bx bx-plus me-1"></i>Thêm nhanh SP</div>');
            $dd.show();
            return;
        }

        $.each(products, function (_, p) {
            var $item = $('<div class="idtf-dd-item">'
                + '<div class="idtf-dd-name">' + esc(p.local_product_name) + '</div>'
                + '<div class="idtf-dd-meta"><span>SKU: ' + esc(p.local_product_sku) + '</span>'
                + '<span>Barcode: ' + esc(p.local_product_barcode_main || '—') + '</span></div>'
                + '</div>');
            $item.on('click', function () { onSelect(p); $dd.hide(); });
            $dd.append($item);
        });

        $dd.append('<div class="idtf-dd-add" id="ddAddProduct"><i class="bx bx-plus me-1"></i>Thêm nhanh SP</div>');
        $dd.show();
    }

    $(document).on('click', '#ddAddProduct', function () {
        // Pre-fill name from search text
        var searchText = $.trim($('#blockProductSearch').val());
        $('#qpName').val(searchText);
        // Auto-generate SKU
        fetchNewSku();
        // Reset other fields
        $('#qpBarcode, #qpDescription').val('');
        $('#qpPriceAfterTax').val(0);
        $('#qpTax').val(8);
        $('#qpPriceBeforeTax').val(0);
        $('#qpUnit').val('Cái');
        // Ẩn addBlock trước, đợi hẳn rồi mới mở quickProduct (tránh stacked modals)
        var addBlockEl = document.getElementById('modalAddBlock');
        var addBlockInstance = bootstrap.Modal.getInstance(addBlockEl);
        $(addBlockEl).one('hidden.bs.modal', function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQuickProduct')).show();
        });
        if (addBlockInstance) addBlockInstance.hide();
    });

    function selectBlockProduct(p) {
        $('#blockProductId').val(p.local_product_name_id);
        $('#blockProductName').text(p.local_product_name);
        $('#blockProductSku').text(p.local_product_sku || '—');
        $('#blockProductSearch').hide();
        $('#blockProductInfo').show();
        $('#btnConfirmAddBlock').prop('disabled', false);

        // Load variants for this product
        loadBlockVariants(p.local_product_name_id);
    }

    $('#blockClearProduct').on('click', function () {
        $('#blockProductId').val('');
        $('#blockProductSearch').val('').show();
        $('#blockProductInfo').hide();
        $('#blockVariantSection').hide();
        $('#btnConfirmAddBlock').prop('disabled', true);
        blockVariantsData = [];
        blockSelectedVars = [];
    });

    function loadBlockVariants(productId) {
        ajax('tgs_idtf_get_variants', { product_id: productId }, function (d) {
            blockVariantsData = d.variants || [];
            blockSelectedVars = [];
            // Luôn hiện section — để nhân viên có thể thêm biến thể mới
            $('#blockVariantSection').show();
            renderBlockVariantChips();
        });
    }

    function renderBlockVariantChips() {
        var $list = $('#blockVariantList').empty();
        if (!blockVariantsData.length) {
            $list.html('<span class="text-muted" style="font-size:12px;">Chưa có biến thể. Bấm nút bên dưới để thêm.</span>');
            return;
        }
        $.each(blockVariantsData, function (_, v) {
            var sel = blockSelectedVars.indexOf(parseInt(v.variant_id)) >= 0 ? ' selected' : '';
            $list.append(
                '<div class="idtf-var-chip' + sel + '" data-vid="' + v.variant_id + '">'
                + esc(v.variant_label) + ': ' + esc(v.variant_value)
                + '</div>'
            );
        });
    }

    // Toggle variant chip selection
    $(document).on('click', '#blockVariantList .idtf-var-chip', function () {
        var vid = parseInt($(this).data('vid'));
        var idx = blockSelectedVars.indexOf(vid);
        if (idx >= 0) {
            blockSelectedVars.splice(idx, 1);
            $(this).removeClass('selected');
        } else {
            blockSelectedVars.push(vid);
            $(this).addClass('selected');
        }
    });

    // Quick variant inside Add Block modal
    $('#btnBlockQuickVariant').on('click', function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQuickVariant')).show();
    });

    // Confirm add block
    $('#btnConfirmAddBlock').on('click', function () {
        var productId = parseInt($('#blockProductId').val());
        if (!productId || !activeTab) return;

        var $btn = $(this).prop('disabled', true);
        ajax('tgs_idtf_add_product_block', {
            ledger_id: activeTab,
            product_id: productId,
            variant_ids: JSON.stringify(blockSelectedVars),
            exp_date: $.trim($('#blockExpDate').val()),
            lot_code: $.trim($('#blockLotCode').val())
        }, function (d) {
            $btn.prop('disabled', false);
            bootstrap.Modal.getInstance(document.getElementById('modalAddBlock')).hide();
            toast('Đã thêm khối sản phẩm.');
            loadLedgerContent(activeTab);
        }, function () { $btn.prop('disabled', false); });
    });

    /* =====================================================================
     * G. REMOVE BLOCK
     * ===================================================================== */
    $(document).on('click', '.btn-remove-block', function () {
        var blockId = parseInt($(this).data('block-id'));
        var count = parseInt($(this).data('count')) || 0;
        var name = $(this).data('name') || '';
        var msg = 'Xóa khối' + (name ? ' "' + name + '"' : '') + '?';
        if (count > 0) msg += '\n\n⚠ ' + count + ' mã đã gắn sẽ bị gỡ và trở lại trạng thái mã trống.';
        if (!confirm(msg)) return;

        var $btn = $(this).prop('disabled', true);
        ajax('tgs_idtf_remove_product_block', { block_id: blockId }, function () {
            toast('Đã xóa khối.');
            loadLedgerContent(activeTab);
            loadSidebar();
        }, function () { $btn.prop('disabled', false); });
    });

    /* =====================================================================
     * H. SCAN CODES MODAL
     * ===================================================================== */
    $(document).on('click', '.btn-scan', function () {
        currentScanBlockId = parseInt($(this).data('block-id'));
        scanPending = [];
        $('#scanBlockId').val(currentScanBlockId);
        $('#scanInput').val('');
        $('#scanAlert').hide();
        $('#scanPendingSection').hide();
        $('#scanPendingList').empty();
        $('#scanPendingCount, #scanConfirmCount').text('0');
        $('#scanBigCount').text('0');
        $('#btnScanConfirm').prop('disabled', true);
        renderModalProductInfo('#scanProductInfo', currentScanBlockId);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalScanCodes')).show();
        setTimeout(function () { $('#scanInput').focus(); }, 400);
    });

    // Scan input — debounced barcode scan
    var scanInputTimer = null;
    $('#scanInput').on('input', function () {
        clearTimeout(scanInputTimer);
        var val = $.trim($(this).val());
        if (val.length < 8) return;
        scanInputTimer = setTimeout(function () {
            validateAndAddCode(val);
        }, 200);
    });

    // Also support Enter key
    $('#scanInput').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(scanInputTimer);
            var val = $.trim($(this).val());
            if (val.length >= 8) validateAndAddCode(val);
        }
    });

    function validateAndAddCode(barcode) {
        // Check duplicate in pending
        for (var i = 0; i < scanPending.length; i++) {
            if (scanPending[i].barcode === barcode) {
                showScanAlert('Mã ' + barcode + ' đã có trong danh sách.', 'warning');
                beepWarning();
                $('#scanInput').val('').focus();
                return;
            }
        }

        ajax('tgs_idtf_scan_code', { barcode: barcode }, function (d) {
            if (!d.found) {
                showScanAlert('Mã ' + barcode + ' không tồn tại trong hệ thống.', 'danger');
                beepError();
                flashScanInput('danger');
            } else if (!d.can_assign) {
                var alertMsg = d.status_label || 'Mã không thể gắn.';
                if (d.assignment) {
                    var a = d.assignment;
                    alertMsg += ' | Phiếu: ' + (a.ledger_title || a.ledger_code);
                    if (a.block_position) alertMsg += ' (khối ' + a.block_position + '/' + a.total_blocks + ')';
                }
                if (d.warning_msg) alertMsg += ' ⚠ ' + d.warning_msg;
                showScanAlert(alertMsg, 'warning');
                beepWarning();
                flashScanInput('warning');
            } else {
                scanPending.push({ lot_id: d.lot_id, barcode: d.barcode });
                showScanAlert('&#10003; ' + barcode + ' — Sẵn sàng', 'success');
                beepSuccess();
                flashScanInput('success');
                renderScanPending();
            }
            $('#scanInput').val('').focus();
        });
    }

    function flashScanInput(type) {
        var cls = 'idtf-flash-' + type;
        $('#scanInput').addClass(cls);
        setTimeout(function () { $('#scanInput').removeClass(cls); }, 500);
    }

    function showScanAlert(msg, type) {
        var icon = type === 'success' ? 'bx-check-circle' : (type === 'danger' ? 'bx-error-circle' : 'bx-error');
        $('#scanAlert').html('<div class="alert alert-' + type + ' py-2 mb-0 d-flex align-items-center" style="font-size:13px;">'
            + '<i class="bx ' + icon + ' me-2" style="font-size:18px;"></i>' + msg + '</div>').show();
        // Auto-hide after 4 seconds
        clearTimeout(showScanAlert._timer);
        showScanAlert._timer = setTimeout(function () { $('#scanAlert').fadeOut(200); }, 4000);
    }

    function renderScanPending() {
        var $list = $('#scanPendingList').empty();
        $.each(scanPending, function (i, item) {
            $list.append(
                '<div class="idtf-scan-item">'
                + '<div><span class="idtf-scan-num">' + (i + 1) + '</span><code>' + esc(item.barcode) + '</code></div>'
                + '<span class="scan-remove" data-idx="' + i + '" title="Gỡ mã này"><i class="bx bx-x"></i></span>'
                + '</div>'
            );
        });
        // Scroll to bottom
        if ($list[0]) $list.scrollTop($list[0].scrollHeight);
        var count = scanPending.length;
        $('#scanPendingCount, #scanConfirmCount').text(count);
        $('#scanBigCount').text(count);
        $('#scanPendingSection').toggle(count > 0);
        $('#btnScanConfirm').prop('disabled', count === 0);
    }

    // Remove single pending
    $(document).on('click', '.scan-remove', function () {
        scanPending.splice(parseInt($(this).data('idx')), 1);
        renderScanPending();
    });

    // Clear all pending
    $('#btnScanClearAll').on('click', function () {
        scanPending = [];
        renderScanPending();
    });

    // Confirm assign
    $('#btnScanConfirm').on('click', function () {
        if (!scanPending.length || !currentScanBlockId) return;
        var $btn = $(this).prop('disabled', true);
        var lotIds = scanPending.map(function (s) { return s.lot_id; });

        ajax('tgs_idtf_assign_codes_to_block', {
            block_id: currentScanBlockId,
            lot_ids: JSON.stringify(lotIds)
        }, function (d) {
            $btn.prop('disabled', false);
            bootstrap.Modal.getInstance(document.getElementById('modalScanCodes')).hide();
            beepSuccess();
            toast('Đã gắn ' + d.assigned + ' mã. Tổng: ' + d.codes_count);
            loadLedgerContent(activeTab);
            loadSidebar();
        }, function () { $btn.prop('disabled', false); });
    });

    /* =====================================================================
     * I. VIEW CODES MODAL
     * ===================================================================== */
    $(document).on('click', '.btn-view-codes', function () {
        viewBlockId = parseInt($(this).data('block-id'));
        viewCodesPage = 1;
        $('#viewBlockId').val(viewBlockId);
        $('#viewCodesSearch').val('');
        $('#btnViewCodesSearchClear').hide();
        renderModalProductInfo('#viewProductInfo', viewBlockId);
        loadViewCodes(1);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalViewCodes')).show();
    });

    // Search within view codes
    var viewCodesSearchTimer = null;
    $('#viewCodesSearch').on('input', function () {
        clearTimeout(viewCodesSearchTimer);
        var val = $.trim($(this).val());
        $('#btnViewCodesSearchClear').toggle(val.length > 0);
        viewCodesSearchTimer = setTimeout(function () {
            viewCodesPage = 1;
            loadViewCodes(1);
        }, 400);
    });
    $('#viewCodesSearch').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(viewCodesSearchTimer);
            viewCodesPage = 1;
            loadViewCodes(1);
        }
    });
    $('#btnViewCodesSearchClear').on('click', function () {
        $('#viewCodesSearch').val('').focus();
        $(this).hide();
        viewCodesPage = 1;
        loadViewCodes(1);
    });

    function loadViewCodes(page) {
        var keyword = $.trim($('#viewCodesSearch').val());
        var params = { block_id: viewBlockId, page: page };
        if (keyword) params.keyword = keyword;
        ajax('tgs_idtf_get_block_codes', params, function (d) {
            viewCodesPage = d.page;
            var $tb = $('#viewCodesBody').empty();
            if (!d.lots || !d.lots.length) {
                $tb.html('<tr><td colspan="5" class="text-center text-muted py-3">Chưa có mã nào.</td></tr>');
                $('#viewCodesInfo').text('');
                $('#viewCodesPager').empty();
                return;
            }

            $.each(d.lots, function (i, r) {
                var st = parseInt(r.local_product_lot_is_active);
                var badge = '', canUnassign = false;
                if (st === 1) {
                    badge = '<span class="badge bg-label-success">Định danh</span>';
                    canUnassign = true;
                } else if (st === 0) {
                    badge = '<span class="badge bg-label-danger">Đã bán/xuất</span>';
                } else if (st === 100) {
                    badge = '<span class="badge bg-label-secondary">Trống</span>';
                } else {
                    badge = '<span class="badge bg-label-warning">TT: ' + st + '</span>';
                }
                var actionBtn = canUnassign
                    ? '<button class="btn btn-sm btn-outline-danger btn-unassign" data-lot-id="' + r.global_product_lot_id + '"><i class="bx bx-unlink"></i></button>'
                    : '<span class="text-muted" title="Không thể gỡ — trạng thái đã thay đổi"><i class="bx bx-lock" style="font-size:16px;"></i></span>';
                $tb.append(
                    '<tr' + (canUnassign ? '' : ' class="table-warning"') + '>'
                    + '<td>' + ((page - 1) * 50 + i + 1) + '</td>'
                    + '<td><code>' + esc(r.global_product_lot_barcode) + '</code></td>'
                    + '<td>' + badge + '</td>'
                    + '<td>' + (r.updated_at ? r.updated_at.substring(0, 16) : '—') + '</td>'
                    + '<td>' + actionBtn + '</td>'
                    + '</tr>'
                );
            });

            $('#viewCodesInfo').text('Tổng ' + d.total + ' mã');
            renderViewCodesPager(d.page, d.pages);
        });
    }

    function renderViewCodesPager(page, pages) {
        var $pager = $('#viewCodesPager').empty();
        if (pages <= 1) return;
        for (var p = 1; p <= pages; p++) {
            $pager.append(
                '<li class="page-item' + (p === page ? ' active' : '') + '">'
                + '<a class="page-link vc-page" href="#" data-page="' + p + '">' + p + '</a></li>'
            );
        }
    }

    $(document).on('click', '.vc-page', function (e) {
        e.preventDefault();
        loadViewCodes(parseInt($(this).data('page'), 10));
    });

    // Unassign code
    $(document).on('click', '.btn-unassign', function () {
        var lotId = parseInt($(this).data('lot-id'));
        if (!confirm('Gỡ mã này khỏi khối?')) return;
        var $btn = $(this).prop('disabled', true);
        ajax('tgs_idtf_unassign_code', { lot_id: lotId, block_id: viewBlockId }, function () {
            toast('Đã gỡ mã.');
            loadViewCodes(viewCodesPage);
            loadLedgerContent(activeTab);
        }, function () { $btn.prop('disabled', false); });
    });

    /* =====================================================================
     * J. QUICK SEARCH BAR (top)
     * ===================================================================== */
    $('#btnQuickSearch').on('click', doQuickSearch);
    $('#quickSearchCode').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doQuickSearch(); }
    });

    function doQuickSearch() {
        var code = $.trim($('#quickSearchCode').val());
        if (code.length < 5) return;

        ajax('tgs_idtf_search_code', { barcode: code }, function (d) {
            var $r = $('#quickSearchResult').show();
            if (!d.found) {
                $r.html('<div class="qs-found qs-notfound"><i class="bx bx-x-circle me-1"></i>Mã không tồn tại</div>');
                return;
            }
            var st = parseInt(d.status);
            var cls = st === 100 ? 'qs-blank' : (st === 1 ? 'qs-identified' : 'qs-notfound');
            var label = st === 100 ? 'Mã trống' : (st === 1 ? 'Đã định danh: ' + esc(d.product_name || 'N/A') : (d.status_label || 'Mã đã bán/xuất'));
            var vars = '';
            if (d.variants && d.variants.length) {
                vars = ' — ';
                $.each(d.variants, function (_, v) {
                    vars += '<span class="idtf-variant-tag ms-1" style="font-size:10px;">' + esc(v.variant_label) + ': ' + esc(v.variant_value) + '</span>';
                });
            }
            // Hiển thị thông tin phiếu + khối nếu có
            var assignInfo = '';
            if (d.assignment) {
                var a = d.assignment;
                assignInfo = '<div style="font-size:11px; margin-top:4px; color:#5f61e6;">' 
                    + '<i class="bx bx-file me-1"></i>Phiếu: <b>' + esc(a.ledger_title || a.ledger_code) + '</b>'
                    + ' <span style="color:#8592a3;">(' + esc(a.ledger_code) + ')</span>';
                if (a.block_position) {
                    assignInfo += ' — <i class="bx bx-cube me-1"></i>Khối thứ ' + a.block_position + '/' + a.total_blocks;
                    if (a.block_label) assignInfo += ' (' + esc(a.block_label) + ')';
                }
                assignInfo += '</div>';
            }
            var warning = '';
            if (d.warning) {
                warning = '<div class="text-danger mt-1" style="font-size:11px;"><i class="bx bx-error me-1"></i>' + esc(d.warning_msg) + '</div>';
            }
            $r.html('<div class="qs-found ' + cls + '"><i class="bx bx-barcode me-1"></i><code>' + esc(d.barcode) + '</code> — ' + label + vars + assignInfo + warning + '</div>');
        });
    }

    /* =====================================================================
     * K. QUICK VARIANT MODAL
     * ===================================================================== */
    var varPresets = {
        size: ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        color: ['Đỏ', 'Xanh', 'Vàng', 'Trắng', 'Đen', 'Hồng'],
        flavor: ['Vanilla', 'Chocolate', 'Dâu', 'Cam', 'Nho'],
        weight: ['100g', '250g', '500g', '1kg', '2kg'],
        age_range: ['0-6th', '6-12th', '1-3t', '3-6t', '6+'],
        expiry: ['3th', '6th', '9th', '12th', '18th', '24th', '36th'],
        custom: []
    };

    var varLabelMap = {
        size: 'Kích cỡ', color: 'Màu sắc', expiry: 'Hạn sử dụng',
        flavor: 'Hương vị', weight: 'Trọng lượng', age_range: 'Độ tuổi', custom: ''
    };

    $('#qvType').on('change', function () {
        var type = $(this).val();
        var chips = varPresets[type] || [];
        var $area = $('#qvPresetChips').empty();
        if (!chips.length) { $('#qvPresetsArea').hide(); } else {
            $('#qvPresetsArea').show();
            $.each(chips, function (_, c) {
                $area.append('<span class="idtf-preset-chip">' + c + '</span>');
            });
        }
        // Auto-fill label + clear value/suffix
        $('#qvLabel').val(varLabelMap[type] || '');
        $('#qvValue').val('');
        $('#qvSkuSuffix').val('');
    }).trigger('change');

    $(document).on('click', '#qvPresetChips .idtf-preset-chip', function () {
        var val = $(this).text();
        $('#qvValue').val(val);
        // Auto-fill SKU suffix from value
        $('#qvSkuSuffix').val('-' + val.replace(/\s+/g, '').substring(0, 10));
    });

    // Save quick variant
    $('#btnQvSave').on('click', function () {
        var productId = parseInt($('#blockProductId').val());
        if (!productId) { toast('Chưa chọn sản phẩm.', 'error'); return; }

        var $btn = $(this).prop('disabled', true);
        ajax('tgs_idtf_save_variant', {
            product_id: productId,
            variant_type: $('#qvType').val(),
            variant_label: $.trim($('#qvLabel').val()),
            variant_value: $.trim($('#qvValue').val()),
            variant_sku_suffix: $.trim($('#qvSkuSuffix').val())
        }, function (d) {
            $btn.prop('disabled', false);
            bootstrap.Modal.getInstance(document.getElementById('modalQuickVariant')).hide();
            toast('Đã thêm biến thể.');
            $('#qvLabel, #qvValue, #qvSkuSuffix').val('');
            loadBlockVariants(productId);
        }, function () { $btn.prop('disabled', false); });
    });

    /* =====================================================================
     * L. QUICK PRODUCT MODAL
     * ===================================================================== */

    /* --- SKU generation --- */
    function fetchNewSku() {
        $('#qpSku').val('Đang tạo...');
        ajax('tgs_idtf_generate_sku', {}, function (d) {
            $('#qpSku').val(d.sku || '');
        }, function () {
            // Fallback: generate client-side
            var sku = '1' + String(Math.floor(Math.random() * 100000000)).padStart(8, '0');
            $('#qpSku').val(sku);
        });
    }

    $('#btnQpRefreshSku').on('click', function () {
        fetchNewSku();
    });

    /* --- Price calculation --- */
    function calcPriceBeforeTax() {
        var priceAfter = parseFloat($('#qpPriceAfterTax').val()) || 0;
        var tax = parseFloat($('#qpTax').val()) || 0;
        var priceBefore = tax > 0 ? Math.round(priceAfter / (1 + tax / 100)) : priceAfter;
        $('#qpPriceBeforeTax').val(priceBefore);
    }

    $('#qpPriceAfterTax, #qpTax').on('input change', calcPriceBeforeTax);

    /* --- Save product --- */
    $('#btnQpSave').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Đang tạo...');

        calcPriceBeforeTax();

        ajax('tgs_idtf_quick_create_product', {
            product_name:           $.trim($('#qpName').val()),
            product_sku:            $.trim($('#qpSku').val()),
            product_barcode:        $.trim($('#qpBarcode').val()),
            product_description:    $.trim($('#qpDescription').val()),
            product_price_after_tax: $('#qpPriceAfterTax').val(),
            product_tax:            $('#qpTax').val(),
            product_price:          $('#qpPriceBeforeTax').val(),
            product_unit:           $('#qpUnit').val()
        }, function (d) {
            $btn.prop('disabled', false).html('<i class="bx bx-check me-1"></i>Tạo sản phẩm & chọn ngay');

            var product = d.product;
            var qpEl = document.getElementById('modalQuickProduct');

            // Đóng quickProduct → đợi hẳn → mở lại addBlock → đợi hiện xong → fill product
            $(qpEl).one('hidden.bs.modal', function () {
                var addBlockEl = document.getElementById('modalAddBlock');

                if (product) {
                    // Đợi addBlock hiện xong rồi mới fill sản phẩm
                    $(addBlockEl).one('shown.bs.modal', function () {
                        $('#blockProductDropdown').hide();
                        selectBlockProduct(product);
                    });
                }

                bootstrap.Modal.getOrCreateInstance(addBlockEl).show();
            });

            bootstrap.Modal.getInstance(qpEl).hide();
            toast('✅ ' + (d.message || 'Đã tạo sản phẩm!'));
        }, function () {
            $btn.prop('disabled', false).html('<i class="bx bx-check me-1"></i>Tạo sản phẩm & chọn ngay');
        });
    });

    /* =====================================================================
     * M. MOBILE SIDEBAR TOGGLE
     * ===================================================================== */
    $('#btnMobileSidebar').on('click', function () {
        $('.idtf-sidebar-col').toggleClass('open');
    });

    // Close sidebar when clicking outside on mobile
    $(document).on('click', function (e) {
        if ($(window).width() < 768 && !$(e.target).closest('.idtf-sidebar-col, #btnMobileSidebar').length) {
            $('.idtf-sidebar-col').removeClass('open');
        }
    });

    /* =====================================================================
     * N. KEYBOARD SHORTCUTS
     * ===================================================================== */
    // Enter to confirm in modals
    $('#modalNewLedger').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#btnConfirmNewLedger').trigger('click'); }
    });
    $('#modalQuickVariant').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#btnQvSave').trigger('click'); }
    });
    $('#modalQuickProduct').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#btnQpSave').trigger('click'); }
    });

    /* =====================================================================
     * INIT
     * ===================================================================== */
    loadSidebar();

})(jQuery);
