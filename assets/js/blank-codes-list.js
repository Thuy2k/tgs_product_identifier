/**
 * blank-codes-list.js — DS phiếu sinh mã trống
 * @package tgs_product_identifier
 */
(function ($) {
    'use strict';

    var currentPage = 1;
    var searchTimer = null;

    /* ── Init ──────────────────────────────────────────────────── */
    loadList(1);

    $('#blankListSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            currentPage = 1;
            loadList(1);
        }, 400);
    });

    /* ── Load list ─────────────────────────────────────────────── */
    function loadList(page) {
        $.post(tgsIdtf.ajaxUrl, {
            action: 'tgs_idtf_get_blank_list',
            nonce: tgsIdtf.nonce,
            page: page,
            per_page: 20,
            search: $.trim($('#blankListSearch').val())
        })
        .done(function (res) {
            if (!res.success) return;
            var d = res.data;
            currentPage = d.page;
            renderTable(d.ledgers);
            renderPager(d.page, d.pages, d.total);
        });
    }

    /* ── Render table ──────────────────────────────────────────── */
    function renderTable(rows) {
        var $tb = $('#blankListBody').empty();
        if (!rows || !rows.length) {
            $tb.html('<tr><td colspan="7" class="text-center py-4 text-muted">Không có phiếu nào.</td></tr>');
            return;
        }

        $.each(rows, function (i, r) {
            var dateStr = r.created_at ? r.created_at.substring(0, 16) : '—';
            var detailUrl = buildUrl('idtf-blank-detail') + '&ledger_id=' + r.local_ledger_id;
            $tb.append(
                '<tr class="lot-row" data-href="' + detailUrl + '">'
                + '<td>' + ((currentPage - 1) * 20 + i + 1) + '</td>'
                + '<td><code>' + esc(r.local_ledger_code || '—') + '</code></td>'
                + '<td>' + esc(r.local_ledger_title || '—') + '</td>'
                + '<td><span class="badge bg-label-primary">' + (r.lots_count || 0) + '</span></td>'
                + '<td>' + esc(r.user_name || '—') + '</td>'
                + '<td>' + dateStr + '</td>'
                + '<td><a href="' + detailUrl + '" class="btn btn-sm btn-outline-primary"><i class="bx bx-right-arrow-alt"></i></a></td>'
                + '</tr>'
            );
        });

        $tb.find('.lot-row').on('click', function (e) {
            if ($(e.target).closest('a, button').length) return;
            window.location.href = $(this).data('href');
        });
    }

    /* ── Pager ─────────────────────────────────────────────────── */
    function renderPager(page, pages, total) {
        $('#blankListInfo').text('Tổng ' + total + ' phiếu');
        var $pager = $('#blankListPager').empty();
        if (pages <= 1) return;

        for (var p = 1; p <= pages; p++) {
            $pager.append(
                '<li class="page-item' + (p === page ? ' active' : '') + '">'
                + '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>'
            );
        }

        $pager.on('click', '.page-link', function (e) {
            e.preventDefault();
            loadList(parseInt($(this).data('page'), 10));
        });
    }

    /* ── Helpers ──────────────────────────────────────────────── */
    function buildUrl(view) {
        return window.location.pathname + '?page=tgs-shop-management&view=' + view;
    }
    function esc(s) {
        return $('<span>').text(s || '').html();
    }

})(jQuery);
