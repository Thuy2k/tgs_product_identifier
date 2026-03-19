<?php
/**
 * TGS Product Identifier — AJAX Handler
 *
 * Nhóm A: Sinh mã trống (Blank Codes)
 * Nhóm B: Phiếu định danh (Identify Ledger)
 * Nhóm C: Khối sản phẩm (Product Blocks)
 * Nhóm D: Quét & gắn mã (Scan & Assign)
 * Nhóm E: Tìm mã nhanh (Quick Search)
 * Nhóm F: Biến thể + Sản phẩm
 *
 * @package tgs_product_identifier
 */

if (!defined('ABSPATH')) exit;

class TGS_Identifier_Ajax
{
    public static function register()
    {
        $actions = [
            // A: Blank codes
            'tgs_idtf_generate_blank_codes',
            'tgs_idtf_get_blank_list',
            'tgs_idtf_get_blank_detail',
            'tgs_idtf_get_blank_lots',
            'tgs_idtf_print_blank_codes',
            'tgs_idtf_prepare_print',
            // B: Identify ledger
            'tgs_idtf_create_identify_ledger',
            'tgs_idtf_get_identify_ledgers',
            'tgs_idtf_delete_identify_ledger',
            'tgs_idtf_get_identify_detail',
            // C: Product blocks
            'tgs_idtf_add_product_block',
            'tgs_idtf_remove_product_block',
            'tgs_idtf_update_block_variants',
            // D: Scan & assign
            'tgs_idtf_scan_code',
            'tgs_idtf_assign_codes_to_block',
            'tgs_idtf_unassign_code',
            'tgs_idtf_get_block_codes',
            // E: Quick search
            'tgs_idtf_search_code',
            // F: Variants + Products
            'tgs_idtf_get_variants',
            'tgs_idtf_save_variant',
            'tgs_idtf_delete_variant',
            'tgs_idtf_search_products',
            'tgs_idtf_quick_create_product',
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", [__CLASS__, $action]);
        }
    }

    /* =========================================================================
     * Helpers
     * ========================================================================= */

    private static function verify()
    {
        if (!check_ajax_referer('tgs_idtf_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce không hợp lệ.'], 403);
        }
    }

    private static function json_ok($data = [], $msg = 'OK')
    {
        wp_send_json_success(array_merge(['message' => $msg], $data));
    }

    private static function json_err($msg = 'Lỗi', $code = 400)
    {
        wp_send_json_error(['message' => $msg], $code);
    }

    private static function lots_table()
    {
        return defined('TGS_TABLE_GLOBAL_PRODUCT_LOTS') ? TGS_TABLE_GLOBAL_PRODUCT_LOTS : 'wp_global_product_lots';
    }

    private static function ledger_table()
    {
        global $wpdb;
        return defined('TGS_TABLE_LOCAL_LEDGER') ? TGS_TABLE_LOCAL_LEDGER : $wpdb->prefix . 'local_ledger';
    }

    private static function product_table()
    {
        global $wpdb;
        return defined('TGS_TABLE_LOCAL_PRODUCT_NAME') ? TGS_TABLE_LOCAL_PRODUCT_NAME : $wpdb->prefix . 'local_product_name';
    }

    /**
     * Lookup phiếu + khối chứa lot (nếu đã được gắn vào phiếu định danh)
     * Trả về array ['ledger_code','ledger_title','block_id','block_position','block_product_name'] hoặc null
     */
    private static function lookup_lot_assignment($lot)
    {
        $ledger_id = intval($lot['identifier_ledger_id'] ?? 0);
        if ($ledger_id <= 0) return null;

        global $wpdb;

        // Lấy thông tin phiếu
        $ledger = $wpdb->get_row($wpdb->prepare(
            "SELECT local_ledger_id, local_ledger_code, local_ledger_title
             FROM " . self::ledger_table() . "
             WHERE local_ledger_id = %d AND is_deleted = 0",
            $ledger_id
        ), ARRAY_A);

        if (!$ledger) return null;

        // Tìm khối chứa lot (match product_id + combo_hash + ledger_id)
        $combo_hash = $lot['variant_combo_hash'] ?? null;
        $product_id = intval($lot['local_product_name_id'] ?? 0);
        $where_combo = $combo_hash !== null
            ? $wpdb->prepare(" AND variant_combo_hash = %s", $combo_hash)
            : " AND variant_combo_hash IS NULL";

        $blocks_in_ledger = $wpdb->get_results($wpdb->prepare(
            "SELECT block_id, block_sort_order, local_product_name_id
             FROM " . TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS . "
             WHERE ledger_id = %d AND is_deleted = 0
             ORDER BY block_sort_order ASC, block_id ASC",
            $ledger_id
        ), ARRAY_A);

        $block_position = null;
        $block_id = null;
        foreach ($blocks_in_ledger as $idx => $b) {
            if (intval($b['local_product_name_id']) === $product_id) {
                // Check combo_hash match nếu cần
                $b_hash = $wpdb->get_var($wpdb->prepare(
                    "SELECT variant_combo_hash FROM " . TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS . " WHERE block_id = %d",
                    $b['block_id']
                ));
                if (($combo_hash === null && $b_hash === null) || $combo_hash === $b_hash) {
                    $block_position = $idx + 1; // 1-indexed
                    $block_id = intval($b['block_id']);
                    break;
                }
            }
        }

        return [
            'ledger_id'    => $ledger['local_ledger_id'],
            'ledger_code'  => $ledger['local_ledger_code'],
            'ledger_title' => $ledger['local_ledger_title'],
            'block_id'     => $block_id,
            'block_position' => $block_position,
            'total_blocks' => count($blocks_in_ledger),
        ];
    }

    /**
     * LIKE pattern cho product_lot_meta chứa blank_ledger_id.
     * JSON format: {"blank_ledger_id":NNN,"generated_by":"..."}
     * Dùng pattern kết thúc bằng [,}] để không match NNN123
     */
    private static function blank_ledger_like($ledger_id)
    {
        // Pattern: %"blank_ledger_id":NNN,% hoặc %"blank_ledger_id":NNN}%
        // Vì PHP json_encode giữ thứ tự key, blank_ledger_id luôn đứng trước generated_by
        // nên luôn có dấu , sau value. Nhưng để an toàn, cũng match } cho trường hợp 1 key.
        return '%"blank_ledger_id":' . intval($ledger_id) . ',%';
    }

    /**
     * Sinh barcode EAN-13 unique (retry tối đa 5 lần nếu trùng)
     */
    private static function generate_ean13_raw()
    {
        $first = mt_rand(1, 9);
        $remaining = str_pad(mt_rand(0, 99999999999), 11, '0', STR_PAD_LEFT);
        $code12 = $first . $remaining;
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$code12[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $code12 . $check;
    }

    private static function generate_ean13()
    {
        global $wpdb;
        $lots_table = self::lots_table();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $barcode = self::generate_ean13_raw();
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$lots_table} WHERE global_product_lot_barcode = %s LIMIT 1",
                $barcode
            ));
            if (!$exists) return $barcode;
        }
        return $barcode; // fallback — xác suất trùng cực thấp sau 5 lần
    }

    /**
     * Tính variant combo hash
     */
    private static function combo_hash($variant_ids)
    {
        if (empty($variant_ids)) return null;
        $ids = array_map('intval', $variant_ids);
        sort($ids);
        return md5(implode(',', $ids));
    }

    /* =========================================================================
     * A. SINH MÃ TRỐNG (Blank Codes)
     * ========================================================================= */

    /**
     * Sinh batch mã trống
     * Hỗ trợ batch_size để client gọi nhiều lần (progress bar)
     */
    public static function tgs_idtf_generate_blank_codes()
    {
        self::verify();
        global $wpdb;

        $title      = sanitize_text_field($_POST['title'] ?? '');
        $total_qty  = intval($_POST['quantity'] ?? 0);
        $note       = sanitize_text_field($_POST['note'] ?? '');
        $batch_size = intval($_POST['batch_size'] ?? 200);
        $offset     = intval($_POST['offset'] ?? 0);
        $ledger_id  = intval($_POST['ledger_id'] ?? 0);
        $blog_id    = intval($_POST['blog_id'] ?? get_current_blog_id());

        if ($total_qty <= 0 || $total_qty > 10000) {
            self::json_err('Số lượng phải từ 1 đến 10.000.');
        }

        $now = current_time('mysql');
        $user_id = get_current_user_id();

        // Lần đầu (offset=0): tạo phiếu ledger
        if ($offset === 0 && $ledger_id === 0) {
            if (empty($title)) {
                $title = 'Phiếu sinh mã #' . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT) . ' — ' . wp_date('d/m/Y H:i');
            }

            $ticket_code = function_exists('tgs_shop_generate_ticket_code')
                ? tgs_shop_generate_ticket_code('SMT')
                : 'SMT-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

            $wpdb->insert(self::ledger_table(), [
                'local_ledger_code'   => $ticket_code,
                'local_ledger_title'  => $title,
                'local_ledger_type'   => TGS_LEDGER_TYPE_BLANK_CODES,
                'local_ledger_note'   => $note,
                'local_ledger_status' => 1,
                'user_id'             => $user_id,
                'is_deleted'          => 0,
                'created_at'          => $now,
                'updated_at'          => $now,
                'local_ledger_person_meta' => wp_json_encode([
                    'plugin'     => 'tgs_product_identifier',
                    'quantity'   => $total_qty,
                    'user_email' => wp_get_current_user()->user_email,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $ledger_id = $wpdb->insert_id;
            if (!$ledger_id) {
                self::json_err('Không thể tạo phiếu. DB: ' . $wpdb->last_error);
            }
        }

        // Sinh batch mã
        $limit = min($batch_size, $total_qty - $offset);
        $created_ids = [];
        $lots_table = self::lots_table();

        for ($i = 0; $i < $limit; $i++) {
            $barcode = self::generate_ean13();

            $wpdb->insert($lots_table, [
                'global_product_lot_barcode'   => $barcode,
                'local_product_name_id'        => null,
                'global_product_lot_is_active' => 100,
                'local_product_lot_is_active'  => 100,
                'source_blog_id'               => $blog_id,
                'to_blog_id'                   => $blog_id,
                'variant_id'                   => null,
                'variant_combo_hash'           => null,
                'identifier_ledger_id'         => null,
                'user_id'                      => $user_id,
                'is_deleted'                   => 0,
                'created_at'                   => $now,
                'updated_at'                   => $now,
                'product_lot_meta'             => wp_json_encode([
                    'blank_ledger_id'   => $ledger_id,
                    'generated_by'      => 'tgs_product_identifier',
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $lot_id = $wpdb->insert_id;
            if ($lot_id) {
                $created_ids[] = $lot_id;
            }
        }

        // Chỉ ghi lot_ids vào ledger khi batch cuối (tránh O(n²) read-merge-write mỗi batch)
        $new_offset = $offset + count($created_ids);
        $done = $new_offset >= $total_qty;

        if ($done) {
            $all_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT global_product_lot_id FROM {$lots_table}
                 WHERE product_lot_meta LIKE %s AND is_deleted = 0
                 ORDER BY global_product_lot_id ASC",
                self::blank_ledger_like($ledger_id)
            ));
            $wpdb->update(self::ledger_table(), [
                'local_ledger_item_id' => wp_json_encode(array_map('intval', $all_ids)),
                'updated_at' => $now,
            ], ['local_ledger_id' => $ledger_id]);
        }

        self::json_ok([
            'ledger_id'    => $ledger_id,
            'created'      => count($created_ids),
            'offset'       => $new_offset,
            'total'        => $total_qty,
            'done'         => $done,
        ], $done ? "Đã sinh {$new_offset}/{$total_qty} mã." : "Đang sinh... {$new_offset}/{$total_qty}");
    }

    /**
     * DS phiếu sinh mã trống
     */
    public static function tgs_idtf_get_blank_list()
    {
        self::verify();
        global $wpdb;

        $page     = max(1, intval($_POST['page'] ?? 1));
        $per_page = min(100, max(10, intval($_POST['per_page'] ?? 20)));
        $offset   = ($page - 1) * $per_page;
        $search   = sanitize_text_field($_POST['search'] ?? '');

        $lt = self::ledger_table();
        $where = "l.local_ledger_type = %d AND l.is_deleted = 0";
        $params = [TGS_LEDGER_TYPE_BLANK_CODES];

        if ($search !== '') {
            $where .= " AND (l.local_ledger_code LIKE %s OR l.local_ledger_title LIKE %s)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lt} l WHERE {$where}", ...$params));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.local_ledger_id, l.local_ledger_code, l.local_ledger_title, l.local_ledger_note,
                    l.local_ledger_item_id, l.local_ledger_person_meta,
                    l.user_id, l.created_at, u.display_name as user_name
             FROM {$lt} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE {$where} ORDER BY l.created_at DESC LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        ), ARRAY_A);

        foreach ($rows as &$r) {
            $ids = json_decode($r['local_ledger_item_id'] ?? '[]', true);
            $r['lots_count'] = is_array($ids) ? count($ids) : 0;
        }
        unset($r);

        self::json_ok([
            'ledgers' => $rows ?: [],
            'total'   => intval($total),
            'page'    => $page,
            'pages'   => $per_page > 0 ? ceil($total / $per_page) : 1,
        ]);
    }

    /**
     * Chi tiết phiếu sinh mã trống
     */
    public static function tgs_idtf_get_blank_detail()
    {
        self::verify();
        global $wpdb;

        $ledger_id = intval($_POST['ledger_id'] ?? 0);
        if ($ledger_id <= 0) self::json_err('ledger_id không hợp lệ.');

        $lt = self::ledger_table();
        $ledger = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name
             FROM {$lt} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.local_ledger_id = %d AND l.local_ledger_type = %d AND l.is_deleted = 0",
            $ledger_id, TGS_LEDGER_TYPE_BLANK_CODES
        ), ARRAY_A);

        if (!$ledger) self::json_err('Không tìm thấy phiếu.');

        $lot_ids = json_decode($ledger['local_ledger_item_id'] ?? '[]', true) ?: [];

        // Stats — hỗ trợ fallback khi JSON chưa ghi (đang sinh batch)
        $stats = ['total' => 0, 'blank' => 0, 'identified' => 0, 'sold' => 0];

        if (!empty($lot_ids)) {
            $ph = implode(',', array_fill(0, count($lot_ids), '%d'));
            $where = "global_product_lot_id IN ({$ph}) AND is_deleted = 0";
            $params = $lot_ids;
        } else {
            // Fallback: lấy qua product_lot_meta
            $meta_pattern = self::blank_ledger_like($ledger_id);
            $where = "product_lot_meta LIKE %s AND is_deleted = 0";
            $params = [$meta_pattern];
        }

        $status_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT local_product_lot_is_active AS s, COUNT(*) AS c
             FROM " . self::lots_table() . " WHERE {$where}
             GROUP BY local_product_lot_is_active",
            ...$params
        ), ARRAY_A);
        foreach ($status_rows as $sr) {
            $s = intval($sr['s']);
            $c = intval($sr['c']);
            $stats['total'] += $c;
            if ($s === 100) $stats['blank'] = $c;
            elseif ($s === 1) $stats['identified'] = $c;
            elseif ($s === 0) $stats['sold'] = $c;
        }

        self::json_ok([
            'ledger' => $ledger,
            'stats'  => $stats,
        ]);
    }

    /**
     * Lấy lots phân trang cho detail page
     */
    public static function tgs_idtf_get_blank_lots()
    {
        self::verify();
        global $wpdb;

        $ledger_id = intval($_POST['ledger_id'] ?? 0);
        $page      = max(1, intval($_POST['page'] ?? 1));
        $per_page  = min(200, max(20, intval($_POST['per_page'] ?? 100)));
        $search    = sanitize_text_field($_POST['search'] ?? '');

        $lots_tbl = self::lots_table();
        $prod_tbl = self::product_table();

        // Thử lấy từ JSON trước (nhanh với IN clause), fallback về product_lot_meta
        $lt = self::ledger_table();
        $ledger_item_ids = $wpdb->get_var($wpdb->prepare(
            "SELECT local_ledger_item_id FROM {$lt} WHERE local_ledger_id = %d AND is_deleted = 0",
            $ledger_id
        ));
        $lot_ids = json_decode($ledger_item_ids ?? '[]', true) ?: [];

        if (!empty($lot_ids)) {
            $ph = implode(',', array_fill(0, count($lot_ids), '%d'));
            $where = "l.global_product_lot_id IN ({$ph}) AND l.is_deleted = 0";
            $params = $lot_ids;
        } else {
            // Fallback: query qua product_lot_meta (đang sinh hoặc JSON chưa ghi)
            $where = "l.product_lot_meta LIKE %s AND l.is_deleted = 0";
            $params = [self::blank_ledger_like($ledger_id)];
            // Kiểm tra có lot nào không
            $check = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$lots_tbl} l WHERE {$where} LIMIT 1", ...$params
            ));
            if (!$check) {
                self::json_ok(['lots' => [], 'total' => 0, 'page' => 1, 'pages' => 1]);
                return;
            }
        }

        if ($search !== '') {
            $where .= " AND l.global_product_lot_barcode LIKE %s";
            $params[] = "%{$search}%";
        }

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$lots_tbl} l WHERE {$where}",
            ...$params
        ));

        $offset = ($page - 1) * $per_page;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.global_product_lot_id, l.global_product_lot_barcode,
                    l.local_product_name_id, l.local_product_lot_is_active,
                    l.variant_combo_hash, l.identifier_ledger_id, l.created_at,
                    p.local_product_name
             FROM {$lots_tbl} l
             LEFT JOIN {$prod_tbl} p
                ON l.local_product_sku IS NOT NULL
               AND p.local_product_sku = l.local_product_sku
               AND (p.is_deleted IS NULL OR p.is_deleted = 0)
             WHERE {$where}
             ORDER BY l.global_product_lot_id ASC
             LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        ), ARRAY_A);

        self::json_ok([
            'lots'  => $rows ?: [],
            'total' => intval($total),
            'page'  => $page,
            'pages' => $per_page > 0 ? ceil($total / $per_page) : 1,
        ]);
    }

    /**
     * Lưu danh sách barcodes vào transient, trả về print_key
     * Dùng cho trường hợp in nhiều mã (> 200) tránh URL quá dài
     */
    public static function tgs_idtf_prepare_print()
    {
        self::verify();

        $raw = sanitize_text_field($_POST['barcodes'] ?? '');
        $barcodes = json_decode(stripslashes($raw), true);
        if (!is_array($barcodes) || empty($barcodes)) {
            self::json_err('Không có barcode để in.');
        }

        // Sanitize từng barcode
        $barcodes = array_values(array_filter(array_map('sanitize_text_field', $barcodes)));
        if (empty($barcodes)) {
            self::json_err('Danh sách barcode rỗng sau khi lọc.');
        }

        $print_key = wp_generate_password(32, false);
        set_transient('tgs_idtf_print_' . $print_key, $barcodes, 300); // 5 phút

        self::json_ok(['print_key' => $print_key]);
    }

    /**
     * In mã barcode trống (standalone HTML)
     * Hỗ trợ 2 mode:
     *   GET  ?barcodes=...  (cho ít mã, < 200)
     *   GET  ?print_key=... (cho nhiều mã, qua transient)
     */
    public static function tgs_idtf_print_blank_codes()
    {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'tgs_idtf_nonce')) {
            wp_die('Nonce không hợp lệ.');
        }

        global $wpdb;

        // Mode 1: transient key (cho nhiều mã)
        $print_key = sanitize_text_field($_GET['print_key'] ?? '');
        if ($print_key !== '') {
            $barcodes = get_transient('tgs_idtf_print_' . $print_key);
            if (!is_array($barcodes) || empty($barcodes)) {
                wp_die('Phiên in đã hết hạn hoặc không hợp lệ. Vui lòng thử lại.');
            }
            delete_transient('tgs_idtf_print_' . $print_key);
        } else {
            // Mode 2: GET barcodes (cho ít mã)
            $barcodes_str = sanitize_text_field($_GET['barcodes'] ?? '');
            if (empty($barcodes_str)) wp_die('Không có barcode.');
            $barcodes = array_filter(array_map('trim', explode(',', $barcodes_str)));
        }

        if (empty($barcodes)) wp_die('Danh sách barcode rỗng.');

        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <title>In Mã Định Danh Trống</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
            <style>
                @page { size: 70mm 22mm; margin: 0; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                html, body { width: 70mm; }
                .barcode-container { display: flex; flex-wrap: wrap; width: 70mm; }
                .barcode-item {
                    width: 35mm; height: 22mm;
                    padding: 1mm;
                    text-align: center;
                    display: flex; flex-direction: column; justify-content: center; align-items: center;
                    overflow: hidden; background: #fff; border: 1px dashed #ccc;
                }
                .barcode-item svg { width: 32mm !important; height: 14mm !important; }
                .print-btn {
                    position: fixed; top: 10px; right: 10px;
                    padding: 10px 20px; background: #4CAF50; color: #fff;
                    border: none; cursor: pointer; border-radius: 4px; z-index: 100;
                }
                @media print { .print-btn { display: none; } .barcode-item { border: none; } }
            </style>
        </head>
        <body>
            <button class="print-btn" onclick="window.print()">In mã</button>
            <div class="barcode-container">
                <?php foreach ($barcodes as $i => $bc): ?>
                <div class="barcode-item">
                    <svg id="bc-<?php echo $i; ?>"></svg>
                </div>
                <?php endforeach; ?>
            </div>
            <script>
            <?php foreach ($barcodes as $i => $bc): ?>
                JsBarcode("#bc-<?php echo $i; ?>", "<?php echo esc_js($bc); ?>", {
                    format: "EAN13", width: 2, height: 50, displayValue: true,
                    fontSize: 14, font: "Arial", margin: 0, textMargin: 1
                });
            <?php endforeach; ?>
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /* =========================================================================
     * B. PHIẾU ĐỊNH DANH (Identify Ledger)
     * ========================================================================= */

    /**
     * Tạo phiếu định danh SP (type=31)
     */
    public static function tgs_idtf_create_identify_ledger()
    {
        self::verify();
        global $wpdb;

        $title = sanitize_text_field($_POST['title'] ?? '');
        $note  = sanitize_text_field($_POST['note'] ?? '');

        if (empty($title)) {
            $title = 'Phiếu định danh — ' . wp_date('d/m/Y H:i');
        }

        $now = current_time('mysql');
        $ticket_code = function_exists('tgs_shop_generate_ticket_code')
            ? tgs_shop_generate_ticket_code('DDSP')
            : 'DDSP-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

        $wpdb->insert(self::ledger_table(), [
            'local_ledger_code'   => $ticket_code,
            'local_ledger_title'  => $title,
            'local_ledger_type'   => TGS_LEDGER_TYPE_IDENTIFY,
            'local_ledger_note'   => $note,
            'local_ledger_status' => 1,
            'user_id'             => get_current_user_id(),
            'is_deleted'          => 0,
            'created_at'          => $now,
            'updated_at'          => $now,
            'local_ledger_person_meta' => wp_json_encode([
                'plugin'     => 'tgs_product_identifier',
                'user_email' => wp_get_current_user()->user_email,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $id = $wpdb->insert_id;
        if (!$id) self::json_err('Không thể tạo phiếu. DB: ' . $wpdb->last_error);

        self::json_ok([
            'ledger_id'   => $id,
            'ticket_code' => $ticket_code,
            'title'       => $title,
        ], 'Đã tạo phiếu định danh.');
    }

    /**
     * DS phiếu định danh
     */
    public static function tgs_idtf_get_identify_ledgers()
    {
        self::verify();
        global $wpdb;

        $search = sanitize_text_field($_POST['search'] ?? '');
        $lt = self::ledger_table();

        $where = "l.local_ledger_type = %d AND l.is_deleted = 0";
        $params = [TGS_LEDGER_TYPE_IDENTIFY];

        if ($search !== '') {
            $where .= " AND (l.local_ledger_code LIKE %s OR l.local_ledger_title LIKE %s)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.local_ledger_id, l.local_ledger_code, l.local_ledger_title, l.local_ledger_note,
                    l.user_id, l.created_at, u.display_name as user_name
             FROM {$lt} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE {$where} ORDER BY l.created_at DESC LIMIT 50",
            ...$params
        ), ARRAY_A);

        // Count blocks per ledger + total codes
        $bt = TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS;
        foreach ($rows as &$r) {
            $lid = intval($r['local_ledger_id']);
            $r['blocks_count'] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$bt} WHERE ledger_id = %d AND is_deleted = 0",
                $lid
            )));
            $r['total_codes'] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(codes_count), 0) FROM {$bt} WHERE ledger_id = %d AND is_deleted = 0",
                $lid
            )));
        }
        unset($r);

        self::json_ok(['ledgers' => $rows ?: []]);
    }

    /**
     * Xóa phiếu định danh (soft delete)
     */
    public static function tgs_idtf_delete_identify_ledger()
    {
        self::verify();
        global $wpdb;

        $ledger_id = intval($_POST['ledger_id'] ?? 0);
        if ($ledger_id <= 0) self::json_err('ledger_id không hợp lệ.');

        $now = current_time('mysql');

        $wpdb->update(self::ledger_table(), [
            'is_deleted' => 1,
            'deleted_at' => $now,
        ], [
            'local_ledger_id' => $ledger_id,
            'local_ledger_type' => TGS_LEDGER_TYPE_IDENTIFY,
        ]);

        self::json_ok([], 'Đã xóa phiếu.');
    }

    /**
     * Chi tiết phiếu định danh (+ blocks)
     */
    public static function tgs_idtf_get_identify_detail()
    {
        self::verify();
        global $wpdb;

        $ledger_id = intval($_POST['ledger_id'] ?? 0);
        if ($ledger_id <= 0) self::json_err('ledger_id không hợp lệ.');

        $lt = self::ledger_table();
        $ledger = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name
             FROM {$lt} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.local_ledger_id = %d AND l.local_ledger_type = %d AND l.is_deleted = 0",
            $ledger_id, TGS_LEDGER_TYPE_IDENTIFY
        ), ARRAY_A);

        if (!$ledger) self::json_err('Không tìm thấy phiếu.');

        // Get blocks — JOIN bằng SKU để cross-site vẫn nhận đúng sản phẩm
        $blocks = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, p.local_product_name,
                    COALESCE(p.local_product_sku, b.local_product_sku) AS local_product_sku,
                    COALESCE(p.local_product_barcode_main, b.local_product_barcode_main) AS local_product_barcode_main,
                    p.local_product_thumbnail, p.local_product_price_after_tax
             FROM " . TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS . " b
             LEFT JOIN " . self::product_table() . " p
                ON b.local_product_sku IS NOT NULL
               AND p.local_product_sku = b.local_product_sku
               AND (p.is_deleted IS NULL OR p.is_deleted = 0)
             WHERE b.ledger_id = %d AND b.is_deleted = 0
             ORDER BY b.block_sort_order ASC, b.block_id ASC",
            $ledger_id
        ), ARRAY_A);

        // Enrich blocks with variant info
        foreach ($blocks as &$block) {
            $v_ids = json_decode($block['variant_ids'] ?? '[]', true) ?: [];
            $block['variants'] = [];
            if (!empty($v_ids)) {
                $vph = implode(',', array_fill(0, count($v_ids), '%d'));
                $block['variants'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT variant_id, variant_type, variant_label, variant_value
                     FROM " . TGS_TABLE_GLOBAL_PRODUCT_VARIANTS . " WHERE variant_id IN ({$vph}) AND is_deleted = 0",
                    ...$v_ids
                ), ARRAY_A);
            }
        }
        unset($block);

        self::json_ok([
            'ledger' => $ledger,
            'blocks' => $blocks,
        ]);
    }

    /* =========================================================================
     * C. KHỐI SẢN PHẨM (Product Blocks)
     * ========================================================================= */

    /**
     * Thêm khối SP vào phiếu
     */
    public static function tgs_idtf_add_product_block()
    {
        self::verify();
        global $wpdb;

        $ledger_id  = intval($_POST['ledger_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        $blog_id    = intval($_POST['blog_id'] ?? get_current_blog_id());
        $variant_ids = json_decode(stripslashes($_POST['variant_ids'] ?? '[]'), true) ?: [];

        if ($ledger_id <= 0) self::json_err('ledger_id không hợp lệ.');
        if ($product_id <= 0) self::json_err('Chưa chọn sản phẩm.');

        $now = current_time('mysql');
        $combo_hash = self::combo_hash($variant_ids);

        // Lookup SKU + barcode from product table for cross-site identification
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT local_product_sku, local_product_barcode_main FROM " . self::product_table() . " WHERE local_product_name_id = %d LIMIT 1",
            $product_id
        ), ARRAY_A);

        $wpdb->insert(TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS, [
            'ledger_id'                  => $ledger_id,
            'ledger_blog_id'             => $blog_id,
            'local_product_name_id'      => $product_id,
            'source_blog_id'             => $blog_id,
            'local_product_sku'          => $product['local_product_sku'] ?? null,
            'local_product_barcode_main' => $product['local_product_barcode_main'] ?? null,
            'variant_ids'                => wp_json_encode(array_map('intval', $variant_ids)),
            'variant_combo_hash'         => $combo_hash,
            'codes_count'                => 0,
            'block_sort_order'           => 0,
            'user_id'                    => get_current_user_id(),
            'is_deleted'                 => 0,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ]);

        $block_id = $wpdb->insert_id;
        if (!$block_id) self::json_err('Không thể tạo khối. DB: ' . $wpdb->last_error);

        self::json_ok(['block_id' => $block_id], 'Đã thêm khối sản phẩm.');
    }

    /**
     * Xóa khối SP (gỡ tất cả mã bên trong trước)
     */
    public static function tgs_idtf_remove_product_block()
    {
        self::verify();
        global $wpdb;

        $block_id = intval($_POST['block_id'] ?? 0);
        if ($block_id <= 0) self::json_err('block_id không hợp lệ.');

        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS . " WHERE block_id = %d AND is_deleted = 0",
            $block_id
        ), ARRAY_A);

        if (!$block) self::json_err('Không tìm thấy khối.');

        $now = current_time('mysql');

        // Gỡ tất cả mã đã gắn trong khối
        $combo_hash = $block['variant_combo_hash'];
        $ledger_id  = $block['ledger_id'];
        $product_id = $block['local_product_name_id'];

        // Tìm các lot đã gắn vào khối này (cùng product + combo + identifier_ledger_id)
        $where_combo = $combo_hash !== null
            ? $wpdb->prepare(" AND variant_combo_hash = %s", $combo_hash)
            : " AND variant_combo_hash IS NULL";

        // Kiểm tra trước: có lot nào đã thay đổi trạng thái (≠ 1) không?
        $changed_lots = $wpdb->get_results($wpdb->prepare(
            "SELECT global_product_lot_id, global_product_lot_barcode, local_product_lot_is_active
             FROM " . self::lots_table() . "
             WHERE local_product_name_id = %d
               AND identifier_ledger_id = %d
               AND local_product_lot_is_active != 1
               AND is_deleted = 0" . $where_combo,
            $product_id, $ledger_id
        ), ARRAY_A);

        if (!empty($changed_lots)) {
            $barcodes = array_column($changed_lots, 'global_product_lot_barcode');
            $preview  = array_slice($barcodes, 0, 5);
            $msg = 'Không thể xóa khối. ' . count($barcodes) . ' mã đã thay đổi trạng thái (có thể đã bán/hoàn/hủy): '
                 . implode(', ', $preview);
            if (count($barcodes) > 5) $msg .= '… và ' . (count($barcodes) - 5) . ' mã khác';
            self::json_err($msg);
        }

        $lots = $wpdb->get_col($wpdb->prepare(
            "SELECT global_product_lot_id FROM " . self::lots_table() . "
             WHERE local_product_name_id = %d
               AND identifier_ledger_id = %d
               AND is_deleted = 0" . $where_combo,
            $product_id, $ledger_id
        ));

        if (!empty($lots)) {
            $ph = implode(',', array_fill(0, count($lots), '%d'));

            // Reset lots
            $wpdb->query($wpdb->prepare(
                "UPDATE " . self::lots_table() . "
                 SET local_product_lot_is_active = 100,
                     local_product_name_id = NULL,
                     local_product_sku = NULL,
                     variant_id = NULL,
                     variant_combo_hash = NULL,
                     identifier_ledger_id = NULL,
                     updated_at = %s
                 WHERE global_product_lot_id IN ({$ph})",
                ...array_merge([$now], $lots)
            ));

            // Delete variant map
            $wpdb->query($wpdb->prepare(
                "DELETE FROM " . TGS_TABLE_GLOBAL_LOT_VARIANT_MAP . " WHERE global_product_lot_id IN ({$ph})",
                ...$lots
            ));
        }

        // Soft delete block
        $wpdb->update(TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS, [
            'is_deleted' => 1,
            'deleted_at' => $now,
        ], ['block_id' => $block_id]);

        self::json_ok([], 'Đã xóa khối sản phẩm.');
    }

    /**
     * Cập nhật biến thể cho khối
     */
    public static function tgs_idtf_update_block_variants()
    {
        self::verify();
        global $wpdb;

        $block_id    = intval($_POST['block_id'] ?? 0);
        $variant_ids = json_decode(stripslashes($_POST['variant_ids'] ?? '[]'), true) ?: [];

        if ($block_id <= 0) self::json_err('block_id không hợp lệ.');

        $combo_hash = self::combo_hash($variant_ids);

        $wpdb->update(TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS, [
            'variant_ids'        => wp_json_encode(array_map('intval', $variant_ids)),
            'variant_combo_hash' => $combo_hash,
            'updated_at'         => current_time('mysql'),
        ], ['block_id' => $block_id]);

        self::json_ok(['combo_hash' => $combo_hash], 'Đã cập nhật biến thể.');
    }

    /* =========================================================================
     * D. QUÉT & GẮN MÃ (Scan & Assign)
     * ========================================================================= */

    /**
     * Quét 1 mã barcode → validate
     */
    public static function tgs_idtf_scan_code()
    {
        self::verify();
        global $wpdb;

        $barcode = sanitize_text_field($_POST['barcode'] ?? '');
        if (empty($barcode)) self::json_err('Barcode rỗng.');

        $lot = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, p.local_product_name
             FROM " . self::lots_table() . " l
             LEFT JOIN " . self::product_table() . " p
                ON l.local_product_sku IS NOT NULL
               AND p.local_product_sku = l.local_product_sku
               AND (p.is_deleted IS NULL OR p.is_deleted = 0)
             WHERE l.global_product_lot_barcode = %s AND l.is_deleted = 0",
            $barcode
        ), ARRAY_A);

        if (!$lot) {
            self::json_ok([
                'found'  => false,
                'status' => 'not_found',
            ], 'Mã không tồn tại trong hệ thống.');
            return;
        }

        $status = intval($lot['local_product_lot_is_active']);
        $result = [
            'found'      => true,
            'lot_id'     => $lot['global_product_lot_id'],
            'barcode'    => $lot['global_product_lot_barcode'],
            'status'     => $status,
            'can_assign' => $status === 100,
        ];

        if ($status === 100) {
            $result['status_label'] = 'Mã trống — sẵn sàng định danh';
        } elseif ($status === 1) {
            $result['status_label'] = 'Đã định danh: ' . ($lot['local_product_name'] ?? 'N/A');
            $result['product_name'] = $lot['local_product_name'];
            $result['identifier_ledger_id'] = $lot['identifier_ledger_id'];
        } elseif ($status === 0) {
            $result['status_label'] = 'Đã bán / đã xuất';
            $result['warning'] = true;
            $result['warning_msg'] = 'Mã ' . $barcode . ' đã bán/xuất — không thể gán hoặc gỡ.';
        } else {
            $result['status_label'] = 'Trạng thái: ' . $status;
            $result['warning'] = true;
            $result['warning_msg'] = 'Mã ' . $barcode . ' có trạng thái bất thường (' . $status . ').';
        }

        // Lookup phiếu + khối chứa mã
        $assignment = self::lookup_lot_assignment($lot);
        if ($assignment) $result['assignment'] = $assignment;

        self::json_ok($result);
    }

    /**
     * Gắn N mã vào khối (batch update)
     */
    public static function tgs_idtf_assign_codes_to_block()
    {
        self::verify();
        global $wpdb;

        $block_id  = intval($_POST['block_id'] ?? 0);
        $lot_ids   = json_decode(stripslashes($_POST['lot_ids'] ?? '[]'), true) ?: [];

        if ($block_id <= 0) self::json_err('block_id không hợp lệ.');
        if (empty($lot_ids)) self::json_err('Chưa có mã nào.');

        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS . " WHERE block_id = %d AND is_deleted = 0",
            $block_id
        ), ARRAY_A);

        if (!$block) self::json_err('Không tìm thấy khối.');

        $product_id  = $block['local_product_name_id'];
        $product_sku = $block['local_product_sku'];
        $ledger_id   = $block['ledger_id'];
        $variant_ids = json_decode($block['variant_ids'] ?? '[]', true) ?: [];
        $combo_hash  = $block['variant_combo_hash'];
        $now         = current_time('mysql');
        $assigned    = 0;

        $assigned_lot_ids = [];

        foreach ($lot_ids as $lot_id) {
            $lot_id = intval($lot_id);

            // Chỉ gắn mã trống (status=100)
            $result = $wpdb->update(self::lots_table(), [
                'local_product_name_id'        => $product_id,
                'local_product_sku'            => $product_sku,
                'local_product_lot_is_active'  => 1,
                'global_product_lot_is_active' => 1,
                'variant_combo_hash'           => $combo_hash,
                'identifier_ledger_id'         => $ledger_id,
                'to_blog_id'                   => $block['source_blog_id'],
                'updated_at'                   => $now,
            ], [
                'global_product_lot_id'        => $lot_id,
                'local_product_lot_is_active'  => 100,
                'is_deleted'                   => 0,
            ]);

            if ($result) {
                $assigned++;
                $assigned_lot_ids[] = $lot_id;
            }
        }

        // Batch INSERT variant map cho tất cả lot đã gắn thành công
        if (!empty($assigned_lot_ids) && !empty($variant_ids)) {
            $map_table = TGS_TABLE_GLOBAL_LOT_VARIANT_MAP;
            $values = [];
            $placeholders = [];
            foreach ($assigned_lot_ids as $a_lot_id) {
                foreach ($variant_ids as $vid) {
                    $vid = intval($vid);
                    $placeholders[] = '(%d, %d, %s)';
                    $values[] = $a_lot_id;
                    $values[] = $vid;
                    $values[] = $now;
                }
            }
            // INSERT IGNORE để bỏ qua nếu đã có (UNIQUE KEY)
            $sql = "INSERT IGNORE INTO {$map_table} (global_product_lot_id, variant_id, created_at) VALUES "
                 . implode(', ', $placeholders);
            $wpdb->query($wpdb->prepare($sql, ...$values));
        }

        // Update codes_count cache
        $total_codes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::lots_table() . "
             WHERE local_product_name_id = %d
               AND identifier_ledger_id = %d
               AND local_product_lot_is_active = 1
               AND is_deleted = 0"
             . ($combo_hash ? " AND variant_combo_hash = %s" : " AND variant_combo_hash IS NULL"),
            ...array_merge([$product_id, $ledger_id], $combo_hash ? [$combo_hash] : [])
        ));

        $wpdb->update(TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS, [
            'codes_count' => intval($total_codes),
            'updated_at'  => $now,
        ], ['block_id' => $block_id]);

        self::json_ok([
            'assigned'    => $assigned,
            'codes_count' => intval($total_codes),
        ], "Đã gắn {$assigned} mã vào khối.");
    }

    /**
     * Gỡ 1 mã khỏi khối — chỉ cho gỡ nếu trạng thái vẫn = 1 (đã định danh)
     */
    public static function tgs_idtf_unassign_code()
    {
        self::verify();
        global $wpdb;

        $lot_id   = intval($_POST['lot_id'] ?? 0);
        $block_id = intval($_POST['block_id'] ?? 0);

        if ($lot_id <= 0) self::json_err('lot_id không hợp lệ.');

        // Kiểm tra lot hiện tại
        $lot = $wpdb->get_row($wpdb->prepare(
            "SELECT global_product_lot_id, global_product_lot_barcode, local_product_lot_is_active
             FROM " . self::lots_table() . " WHERE global_product_lot_id = %d AND is_deleted = 0",
            $lot_id
        ), ARRAY_A);

        if (!$lot) self::json_err('Mã không tồn tại.');

        $status = intval($lot['local_product_lot_is_active']);
        if ($status !== 1) {
            $label = $status === 0 ? 'đã bán/xuất' : ($status === 100 ? 'mã trống' : 'trạng thái ' . $status);
            self::json_err('Không thể gỡ mã ' . $lot['global_product_lot_barcode'] . ' — ' . $label . '. Mã này đã thay đổi trạng thái sau khi định danh.');
        }

        $now = current_time('mysql');

        // Reset lot
        $wpdb->update(self::lots_table(), [
            'local_product_lot_is_active'  => 100,
            'global_product_lot_is_active' => 100,
            'local_product_name_id'        => null,
            'local_product_sku'            => null,
            'variant_combo_hash'           => null,
            'identifier_ledger_id'         => null,
            'updated_at'                   => $now,
        ], [
            'global_product_lot_id' => $lot_id,
            'is_deleted'            => 0,
        ]);

        // Delete variant map
        $wpdb->delete(TGS_TABLE_GLOBAL_LOT_VARIANT_MAP, ['global_product_lot_id' => $lot_id]);

        // Update block codes_count if block_id provided
        if ($block_id > 0) {
            $block = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS . " WHERE block_id = %d",
                $block_id
            ), ARRAY_A);

            if ($block) {
                $combo_hash = $block['variant_combo_hash'];
                $cnt = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . self::lots_table() . "
                     WHERE local_product_name_id = %d
                       AND identifier_ledger_id = %d
                       AND local_product_lot_is_active = 1
                       AND is_deleted = 0"
                     . ($combo_hash ? " AND variant_combo_hash = %s" : " AND variant_combo_hash IS NULL"),
                    ...array_merge([$block['local_product_name_id'], $block['ledger_id']], $combo_hash ? [$combo_hash] : [])
                ));

                $wpdb->update(TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS, [
                    'codes_count' => intval($cnt),
                    'updated_at'  => $now,
                ], ['block_id' => $block_id]);
            }
        }

        self::json_ok([], 'Đã gỡ mã khỏi sản phẩm.');
    }

    /**
     * Lấy mã đã gắn trong khối (phân trang)
     */
    public static function tgs_idtf_get_block_codes()
    {
        self::verify();
        global $wpdb;

        $block_id = intval($_POST['block_id'] ?? 0);
        $page     = max(1, intval($_POST['page'] ?? 1));
        $per_page = 50;

        if ($block_id <= 0) self::json_err('block_id không hợp lệ.');

        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS . " WHERE block_id = %d AND is_deleted = 0",
            $block_id
        ), ARRAY_A);

        if (!$block) self::json_err('Không tìm thấy khối.');

        $combo_hash = $block['variant_combo_hash'];
        $where_combo = $combo_hash ? " AND l.variant_combo_hash = %s" : " AND l.variant_combo_hash IS NULL";
        $base_params = [$block['local_product_name_id'], $block['ledger_id']];
        if ($combo_hash) $base_params[] = $combo_hash;

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::lots_table() . " l
             WHERE l.local_product_name_id = %d AND l.identifier_ledger_id = %d
               AND l.is_deleted = 0" . $where_combo,
            ...$base_params
        ));

        $offset = ($page - 1) * $per_page;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.global_product_lot_id, l.global_product_lot_barcode,
                    l.local_product_lot_is_active, l.created_at, l.updated_at
             FROM " . self::lots_table() . " l
             WHERE l.local_product_name_id = %d AND l.identifier_ledger_id = %d
               AND l.is_deleted = 0" . $where_combo . "
             ORDER BY l.local_product_lot_is_active DESC, l.updated_at DESC LIMIT %d OFFSET %d",
            ...array_merge($base_params, [$per_page, $offset])
        ), ARRAY_A);

        self::json_ok([
            'lots'  => $rows ?: [],
            'total' => intval($total),
            'page'  => $page,
            'pages' => $per_page > 0 ? ceil($total / $per_page) : 1,
        ]);
    }

    /* =========================================================================
     * E. TÌM MÃ NHANH
     * ========================================================================= */

    /**
     * Tìm mã barcode → trả về thông tin đầy đủ
     */
    public static function tgs_idtf_search_code()
    {
        self::verify();
        global $wpdb;

        $barcode = sanitize_text_field($_POST['barcode'] ?? '');
        if (empty($barcode)) self::json_err('Barcode rỗng.');

        $lot = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, p.local_product_name
             FROM " . self::lots_table() . " l
             LEFT JOIN " . self::product_table() . " p
                ON l.local_product_sku IS NOT NULL
               AND p.local_product_sku = l.local_product_sku
               AND (p.is_deleted IS NULL OR p.is_deleted = 0)
             WHERE l.global_product_lot_barcode = %s AND l.is_deleted = 0",
            $barcode
        ), ARRAY_A);

        if (!$lot) {
            self::json_ok(['found' => false], 'Mã không tồn tại.');
            return;
        }

        $status = intval($lot['local_product_lot_is_active']);
        $result = [
            'found'                => true,
            'lot_id'               => $lot['global_product_lot_id'],
            'barcode'              => $lot['global_product_lot_barcode'],
            'status'               => $status,
            'product_name'         => $lot['local_product_name'],
            'identifier_ledger_id' => $lot['identifier_ledger_id'],
        ];

        if ($status === 100) {
            $result['status_label'] = 'Mã trống';
        } elseif ($status === 1) {
            $result['status_label'] = 'Đã định danh: ' . ($lot['local_product_name'] ?? 'N/A');
        } elseif ($status === 0) {
            $result['status_label'] = 'Đã bán / đã xuất';
            $result['warning'] = true;
            $result['warning_msg'] = 'Mã này đã bán/xuất — không thể gỡ hoặc xóa khối chứa nó.';
        } else {
            $result['status_label'] = 'Trạng thái: ' . $status;
            $result['warning'] = true;
            $result['warning_msg'] = 'Mã này có trạng thái bất thường (' . $status . ').';
        }

        // Lookup phiếu + khối chứa mã
        $assignment = self::lookup_lot_assignment($lot);
        if ($assignment) $result['assignment'] = $assignment;

        // Lấy biến thể
        $variants = $wpdb->get_results($wpdb->prepare(
            "SELECT v.variant_id, v.variant_type, v.variant_label, v.variant_value
             FROM " . TGS_TABLE_GLOBAL_LOT_VARIANT_MAP . " m
             JOIN " . TGS_TABLE_GLOBAL_PRODUCT_VARIANTS . " v ON m.variant_id = v.variant_id
             WHERE m.global_product_lot_id = %d",
            $lot['global_product_lot_id']
        ), ARRAY_A);

        $result['variants'] = $variants;

        self::json_ok($result);
    }

    /* =========================================================================
     * F. BIẾN THỂ + SẢN PHẨM
     * ========================================================================= */

    public static function tgs_idtf_get_variants()
    {
        self::verify();
        global $wpdb;

        $product_id = intval($_POST['product_id'] ?? 0);
        $blog_id    = intval($_POST['blog_id'] ?? get_current_blog_id());

        $table = TGS_TABLE_GLOBAL_PRODUCT_VARIANTS;
        $where = "is_deleted = 0";
        $params = [];

        if ($product_id > 0) {
            $where .= " AND local_product_name_id = %d AND source_blog_id = %d";
            $params[] = $product_id;
            $params[] = $blog_id;
        } else {
            $where .= " AND source_blog_id = %d";
            $params[] = $blog_id;
        }

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY variant_sort_order ASC, variant_id DESC";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        self::json_ok(['variants' => $rows ?: []]);
    }

    public static function tgs_idtf_save_variant()
    {
        self::verify();
        global $wpdb;

        $table = TGS_TABLE_GLOBAL_PRODUCT_VARIANTS;
        $now   = current_time('mysql');

        $variant_id = intval($_POST['variant_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        $blog_id    = intval($_POST['blog_id'] ?? get_current_blog_id());

        if ($product_id <= 0) self::json_err('Chưa chọn sản phẩm.');

        // Lookup product SKU & barcode
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT local_product_sku, local_product_barcode_main FROM " . self::product_table() . " WHERE local_product_name_id = %d AND (is_deleted IS NULL OR is_deleted = 0)",
            $product_id
        ), ARRAY_A);

        $data = [
            'local_product_name_id'      => $product_id,
            'source_blog_id'             => $blog_id,
            'local_product_sku'          => $product ? $product['local_product_sku'] : null,
            'local_product_barcode_main' => $product ? $product['local_product_barcode_main'] : null,
            'variant_type'               => sanitize_text_field($_POST['variant_type'] ?? 'custom'),
            'variant_label'            => sanitize_text_field($_POST['variant_label'] ?? ''),
            'variant_value'            => sanitize_text_field($_POST['variant_value'] ?? ''),
            'variant_sku_suffix'       => sanitize_text_field($_POST['variant_sku_suffix'] ?? ''),
            'variant_barcode_main'     => sanitize_text_field($_POST['variant_barcode_main'] ?? ''),
            'variant_price_adjustment' => floatval($_POST['variant_price_adjustment'] ?? 0),
            'variant_sort_order'       => intval($_POST['variant_sort_order'] ?? 0),
            'is_active'                => intval($_POST['is_active'] ?? 1),
            'updated_at'               => $now,
        ];

        if (!empty($_POST['variant_meta'])) {
            $data['variant_meta'] = wp_json_encode(json_decode(stripslashes($_POST['variant_meta']), true));
        }

        // Duplicate check
        $dup_sql = $wpdb->prepare(
            "SELECT variant_id FROM {$table}
             WHERE local_product_name_id = %d AND source_blog_id = %d
               AND variant_label = %s AND variant_value = %s AND is_deleted = 0",
            $product_id, $blog_id, $data['variant_label'], $data['variant_value']
        );
        if ($variant_id > 0) {
            $dup_sql .= $wpdb->prepare(" AND variant_id != %d", $variant_id);
        }
        if ($wpdb->get_var($dup_sql)) {
            self::json_err('Biến thể này đã tồn tại.');
        }

        if ($variant_id > 0) {
            $wpdb->update($table, $data, ['variant_id' => $variant_id]);
            self::json_ok(['variant_id' => $variant_id], 'Đã cập nhật biến thể.');
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
            $new_id = $wpdb->insert_id;
            if (!$new_id) self::json_err('Không thể tạo biến thể. DB: ' . $wpdb->last_error);
            self::json_ok(['variant_id' => $new_id], 'Đã thêm biến thể.');
        }
    }

    public static function tgs_idtf_delete_variant()
    {
        self::verify();
        global $wpdb;

        $variant_id = intval($_POST['variant_id'] ?? 0);
        if ($variant_id <= 0) self::json_err('variant_id không hợp lệ.');

        $wpdb->update(TGS_TABLE_GLOBAL_PRODUCT_VARIANTS, [
            'is_deleted' => 1,
            'deleted_at' => current_time('mysql'),
        ], ['variant_id' => $variant_id]);

        self::json_ok([], 'Đã xóa biến thể.');
    }

    public static function tgs_idtf_search_products()
    {
        self::verify();
        global $wpdb;

        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $table   = self::product_table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT local_product_name_id, local_product_name, local_product_barcode_main,
                    local_product_sku, local_product_unit, local_product_price_after_tax,
                    local_product_thumbnail, local_product_is_tracking
             FROM {$table}
             WHERE is_deleted = 0
               AND (local_product_name LIKE %s OR local_product_barcode_main LIKE %s OR local_product_sku LIKE %s)
             ORDER BY local_product_name ASC LIMIT 30",
            "%{$keyword}%", "%{$keyword}%", "%{$keyword}%"
        ), ARRAY_A);

        self::json_ok(['products' => $rows ?: []]);
    }

    public static function tgs_idtf_quick_create_product()
    {
        self::verify();
        global $wpdb;

        $name    = sanitize_text_field($_POST['product_name'] ?? '');
        $sku     = sanitize_text_field($_POST['product_sku'] ?? '');
        $barcode = sanitize_text_field($_POST['product_barcode'] ?? '');
        $price   = floatval($_POST['product_price_after_tax'] ?? 0);
        $unit    = sanitize_text_field($_POST['product_unit'] ?? 'Cái');

        if (empty($name)) self::json_err('Tên sản phẩm không được trống.');
        if (empty($sku))  self::json_err('Mã SKU không được trống.');

        $table = self::product_table();

        // Unique SKU check
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE local_product_sku = %s AND (is_deleted IS NULL OR is_deleted = 0)",
            $sku
        ));
        if ($exists > 0) self::json_err('Mã SKU đã tồn tại.');

        $now = current_time('mysql');
        $wpdb->insert($table, [
            'local_product_name'            => $name,
            'local_product_sku'             => $sku,
            'local_product_barcode_main'    => $barcode,
            'local_product_price_after_tax' => $price,
            'local_product_unit'            => $unit,
            'local_product_status'          => 'active',
            'local_product_is_tracking'     => 1,
            'user_id'                       => get_current_user_id(),
            'is_deleted'                    => 0,
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ]);

        $new_id = $wpdb->insert_id;
        if (!$new_id) self::json_err('Không thể tạo SP. DB: ' . $wpdb->last_error);

        self::json_ok([
            'product' => [
                'local_product_name_id'         => $new_id,
                'local_product_name'            => $name,
                'local_product_sku'             => $sku,
                'local_product_barcode_main'    => $barcode,
                'local_product_price_after_tax' => $price,
                'local_product_unit'            => $unit,
                'local_product_is_tracking'     => 1,
            ]
        ], 'Đã thêm sản phẩm.');
    }
}
