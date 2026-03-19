/**
 * variant-manager.js — Quản lý biến thể sản phẩm
 * @package tgs_product_identifier
 */
(function ($) {
    'use strict';

    var selectedProductId = 0;
    var productSearchTimer = null;
    var editVariantId = 0;

    /* ── Presets ────────────────────────────────────────────────── */
    var presets = {
        size: { label: 'Kích cỡ', values: ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'] },
        color: { label: 'Màu sắc', values: ['Đỏ', 'Xanh', 'Vàng', 'Trắng', 'Đen', 'Hồng', 'Nâu', 'Xám'] },
        flavor: { label: 'Hương vị', values: ['Vanilla', 'Chocolate', 'Dâu', 'Cam', 'Nho', 'Bạc hà'] },
        weight: { label: 'Trọng lượng', values: ['100g', '250g', '500g', '1kg', '2kg', '5kg'] },
        age_range: { label: 'Độ tuổi', values: ['0-6 tháng', '6-12 tháng', '1-3 tuổi', '3-6 tuổi', '6+'] },
        expiry: { label: 'Hạn sử dụng', values: [] },
        custom: { label: 'Tùy chỉnh', values: [] }
    };

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
                else { toast(r.data && r.data.message ? r.data.message : 'Lỗi', 'error'); if (fail) fail(); }
            })
            .fail(function () { toast('Lỗi kết nối.', 'error'); if (fail) fail(); });
    }
    function esc(s) { return $('<span>').text(s || '').html(); }
    function toast(msg, type) {
        var $t = $('<div class="idtf-toast ' + (type === 'error' ? 'toast-error' : 'toast-success') + '">' + esc(msg) + '</div>').appendTo('body');
        setTimeout(function () { $t.addClass('show'); }, 30);
        setTimeout(function () { $t.removeClass('show'); setTimeout(function () { $t.remove(); }, 300); }, 3000);
    }

    /* =====================================================================
     * A. PRODUCT SEARCH
     * ===================================================================== */
    $('#varProductSearch').on('input', function () {
        clearTimeout(productSearchTimer);
        var val = $.trim($(this).val());
        if (val.length < 2) { $('#varProductDropdown').hide(); return; }

        productSearchTimer = setTimeout(function () {
            ajax('tgs_idtf_search_products', { keyword: val }, function (d) {
                var $dd = $('#varProductDropdown').empty();
                if (!d.products || !d.products.length) {
                    $dd.html('<div class="p-3 text-center text-muted" style="font-size:12px;">Không tìm thấy.</div>');
                    $dd.show();
                    return;
                }
                $.each(d.products, function (_, p) {
                    var $item = $('<div class="idtf-dd-item">'
                        + '<div class="idtf-dd-name">' + esc(p.local_product_name) + '</div>'
                        + '<div class="idtf-dd-meta"><span>SKU: ' + esc(p.local_product_sku) + '</span>'
                        + '<span>Barcode: ' + esc(p.local_product_barcode_main || '—') + '</span></div>'
                        + '</div>');
                    $item.on('click', function () { selectProduct(p); $dd.hide(); });
                    $dd.append($item);
                });
                $dd.show();
            });
        }, 300);
    });

    // Close dropdown on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.idtf-search-wrap').length) {
            $('#varProductDropdown').hide();
        }
    });

    function selectProduct(p) {
        selectedProductId = parseInt(p.local_product_name_id);
        $('#varProductId').val(selectedProductId);
        $('#varProductName').text(p.local_product_name);
        $('#varProductSku').text(p.local_product_sku || '—');
        $('#varProductSearch').hide();
        $('#varProductInfo').show();
        $('#variantSection').show();
        loadVariants();
    }

    $('#varClearProduct').on('click', function () {
        selectedProductId = 0;
        $('#varProductId').val('');
        $('#varProductSearch').val('').show();
        $('#varProductInfo').hide();
        $('#variantSection').hide();
    });

    /* =====================================================================
     * B. VARIANT LIST
     * ===================================================================== */
    function loadVariants() {
        if (!selectedProductId) return;
        ajax('tgs_idtf_get_variants', { product_id: selectedProductId }, function (d) {
            renderVariants(d.variants || []);
        });
    }

    function renderVariants(rows) {
        var $tb = $('#variantTableBody').empty();
        if (!rows.length) {
            $tb.html('<tr><td colspan="6" class="text-center text-muted py-3">Chưa có biến thể</td></tr>');
            return;
        }

        $.each(rows, function (i, v) {
            $tb.append(
                '<tr>'
                + '<td>' + esc(v.variant_type || 'custom') + '</td>'
                + '<td>' + esc(v.variant_label) + '</td>'
                + '<td><strong>' + esc(v.variant_value) + '</strong></td>'
                + '<td><code style="font-size:11px;">' + esc(v.local_product_sku || '—') + '</code></td>'
                + '<td>' + esc(v.variant_sku_suffix || '—') + '</td>'
                + '<td>'
                + '<button class="btn btn-sm btn-outline-primary me-1 btn-edit-var" data-v=\'' + JSON.stringify(v).replace(/'/g, '&#39;') + '\'><i class="bx bx-edit-alt"></i></button>'
                + '<button class="btn btn-sm btn-outline-danger btn-delete-var" data-id="' + v.variant_id + '"><i class="bx bx-trash"></i></button>'
                + '</td></tr>'
            );
        });
    }

    /* =====================================================================
     * C. VARIANT FORM — Create / Edit
     * ===================================================================== */
    // Type change → presets
    $('#varType').on('change', function () {
        var type = $(this).val();
        var data = presets[type] || presets.custom;
        var $area = $('#varPresetChips').empty();
        if (!data.values.length) { $('#varPresetsArea').hide(); return; }
        $('#varPresetsArea').show();
        $.each(data.values, function (_, v) {
            $area.append('<span class="idtf-preset-chip">' + v + '</span>');
        });
        if (!editVariantId) {
            $('#varLabel').val(data.label || '');
        }
    }).trigger('change');

    // Click preset chip → fill value
    $(document).on('click', '#varPresetChips .idtf-preset-chip', function () {
        $('#varValue').val($(this).text());
    });

    // Edit button
    $(document).on('click', '.btn-edit-var', function () {
        var v = JSON.parse($(this).attr('data-v'));
        editVariantId = parseInt(v.variant_id);
        $('#varEditId').val(editVariantId);
        $('#varType').val(v.variant_type || 'custom').trigger('change');
        $('#varLabel').val(v.variant_label || '');
        $('#varValue').val(v.variant_value || '');
        $('#varSkuSuffix').val(v.variant_sku_suffix || '');
        $('#varFormTitle').text('Sửa biến thể');
        $('#varCancelEdit').show();
    });

    // Cancel edit
    $('#varCancelEdit').on('click', function () {
        resetForm();
    });

    function resetForm() {
        editVariantId = 0;
        $('#varEditId').val('0');
        $('#varType').val('size').trigger('change');
        $('#varLabel, #varValue, #varSkuSuffix').val('');
        $('#varFormTitle').text('Thêm biến thể');
        $('#varCancelEdit').hide();
    }

    // Submit form
    $('#variantForm').on('submit', function (e) {
        e.preventDefault();
        if (!selectedProductId) { toast('Chưa chọn sản phẩm.', 'error'); return; }

        var label = $.trim($('#varLabel').val());
        var value = $.trim($('#varValue').val());
        if (!label || !value) { toast('Nhãn và giá trị không được trống.', 'error'); return; }

        ajax('tgs_idtf_save_variant', {
            variant_id: editVariantId,
            product_id: selectedProductId,
            variant_type: $('#varType').val(),
            variant_label: label,
            variant_value: value,
            variant_sku_suffix: $.trim($('#varSkuSuffix').val())
        }, function (d) {
            toast(d.message || 'Đã lưu biến thể.');
            resetForm();
            loadVariants();
        });
    });

    // Delete variant
    $(document).on('click', '.btn-delete-var', function () {
        var id = parseInt($(this).data('id'));
        if (!confirm('Xóa biến thể này?')) return;
        ajax('tgs_idtf_delete_variant', { variant_id: id }, function () {
            toast('Đã xóa biến thể.');
            loadVariants();
        });
    });

})(jQuery);
