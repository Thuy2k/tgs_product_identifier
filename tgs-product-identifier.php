<?php
/**
 * Plugin Name: TGS Product Identifier
 * Description: Sinh mã định danh trống + Định danh sản phẩm vào mã + Biến thể kết hợp
 * Version: 1.0.0
 * Author: TGS Dev Team
 * Requires Plugins: tgs_shop_management
 */

if (!defined('ABSPATH')) exit;

// ── Constants ────────────────────────────────────────────────────────────────
define('TGS_IDTF_VERSION', '1.0.0');
define('TGS_IDTF_DIR', plugin_dir_path(__FILE__));
define('TGS_IDTF_URL', plugin_dir_url(__FILE__));
define('TGS_IDTF_VIEWS', TGS_IDTF_DIR . 'admin-views/');

// Ledger types
if (!defined('TGS_LEDGER_TYPE_BLANK_CODES')) {
    define('TGS_LEDGER_TYPE_BLANK_CODES', 30);
}
if (!defined('TGS_LEDGER_TYPE_IDENTIFY')) {
    define('TGS_LEDGER_TYPE_IDENTIFY', 31);
}

// Tables
if (!defined('TGS_TABLE_GLOBAL_PRODUCT_VARIANTS')) {
    define('TGS_TABLE_GLOBAL_PRODUCT_VARIANTS', 'wp_global_product_variants');
}
if (!defined('TGS_TABLE_GLOBAL_LOT_VARIANT_MAP')) {
    define('TGS_TABLE_GLOBAL_LOT_VARIANT_MAP', 'wp_global_lot_variant_map');
}
if (!defined('TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS')) {
    define('TGS_TABLE_GLOBAL_IDENTIFY_BLOCKS', 'wp_global_identify_blocks');
}

// ── Init ─────────────────────────────────────────────────────────────────────
function tgs_idtf_init()
{
    if (!class_exists('TGS_Shop_Management') && !defined('TGS_SHOP_PLUGIN_DIR')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>TGS Product Identifier</strong> cần plugin <strong>TGS Shop Management</strong> được kích hoạt.</p></div>';
        });
        return;
    }

    require_once TGS_IDTF_DIR . 'includes/class-identifier-ajax.php';

    TGS_Identifier_Ajax::register();
}
add_action('plugins_loaded', 'tgs_idtf_init', 25);

// ── Routes ───────────────────────────────────────────────────────────────────
add_filter('tgs_shop_dashboard_routes', function ($routes) {
    $dir = TGS_IDTF_VIEWS;
    $routes['idtf-blank-create'] = ['Sinh mã trống',       $dir . 'blank-codes/create.php'];
    $routes['idtf-blank-list']   = ['DS phiếu sinh mã',    $dir . 'blank-codes/list.php'];
    $routes['idtf-blank-detail'] = ['Chi tiết phiếu mã',   $dir . 'blank-codes/detail.php'];
    $routes['idtf-workspace']    = ['Định danh sản phẩm',  $dir . 'identify/workspace.php'];
    $routes['idtf-product-codes'] = ['Thống kê mã SP',      $dir . 'identify/product-codes.php'];
    $routes['idtf-variants']     = ['Quản lý biến thể',    $dir . 'variants/list.php'];
    return $routes;
});

// ── Sidebar Menu ─────────────────────────────────────────────────────────────
add_action('tgs_shop_sidebar_menu', function ($current_view) {
    // Chỉ hiển thị 2 menu: Thống kê mã SP + Quản lý biến thể
    // Các menu ẩn tạm: idtf-blank-create, idtf-blank-list, idtf-blank-detail, idtf-workspace
    $views = ['idtf-product-codes', 'idtf-variants'];
    $is_active = in_array($current_view, $views);
    $open = $is_active ? ' active open' : '';
    $href = function_exists('tgs_url') ? function ($v) { return tgs_url($v); } : function ($v) {
        return admin_url('admin.php?page=tgs-shop-management&view=' . $v);
    };
    ?>
    <li class="menu-item<?php echo $open; ?>">
        <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-purchase-tag"></i>
            <div>Quản lý biến thể</div>
        </a>
        <ul class="menu-sub">
            <li class="menu-item<?php echo $current_view === 'idtf-product-codes' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url($href('idtf-product-codes')); ?>" class="menu-link"><div>Thống kê mã SP</div></a>
            </li>
            <li class="menu-item<?php echo $current_view === 'idtf-variants' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url($href('idtf-variants')); ?>" class="menu-link"><div>Quản lý biến thể</div></a>
            </li>
        </ul>
    </li>
    <?php
});

// ── Enqueue Assets ───────────────────────────────────────────────────────────
add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'tgs-shop-management') === false) return;

    $view = sanitize_text_field($_GET['view'] ?? '');
    if (strpos($view, 'idtf-') !== 0) return;

    wp_enqueue_style('tgs-idtf-css', TGS_IDTF_URL . 'assets/css/identifier.css', [], TGS_IDTF_VERSION);

    $localize = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('tgs_idtf_nonce'),
        'blogId'  => get_current_blog_id(),
        'userEmail' => wp_get_current_user()->user_email,
        'userName'  => wp_get_current_user()->display_name,
    ];

    if ($view === 'idtf-blank-create') {
        wp_enqueue_script('tgs-idtf-blank-create', TGS_IDTF_URL . 'assets/js/blank-codes-create.js', ['jquery'], TGS_IDTF_VERSION, true);
        wp_localize_script('tgs-idtf-blank-create', 'tgsIdtf', $localize);
    }
    if ($view === 'idtf-blank-list') {
        wp_enqueue_script('tgs-idtf-blank-list', TGS_IDTF_URL . 'assets/js/blank-codes-list.js', ['jquery'], TGS_IDTF_VERSION, true);
        wp_localize_script('tgs-idtf-blank-list', 'tgsIdtf', $localize);
    }
    if ($view === 'idtf-blank-detail') {
        wp_enqueue_script('tgs-idtf-blank-detail', TGS_IDTF_URL . 'assets/js/blank-codes-detail.js', ['jquery'], TGS_IDTF_VERSION, true);
        wp_localize_script('tgs-idtf-blank-detail', 'tgsIdtf', $localize);
    }
    if ($view === 'idtf-workspace') {
        wp_enqueue_script('tgs-idtf-workspace', TGS_IDTF_URL . 'assets/js/identify-workspace.js', ['jquery'], TGS_IDTF_VERSION, true);
        wp_localize_script('tgs-idtf-workspace', 'tgsIdtf', $localize);
    }
    if ($view === 'idtf-product-codes') {
        wp_enqueue_script('tgs-idtf-product-codes', TGS_IDTF_URL . 'assets/js/product-codes.js', ['jquery'], TGS_IDTF_VERSION, true);
        wp_localize_script('tgs-idtf-product-codes', 'tgsIdtf', $localize);
    }
    if ($view === 'idtf-variants') {
        wp_enqueue_script('tgs-idtf-variants', TGS_IDTF_URL . 'assets/js/variant-manager.js', ['jquery'], TGS_IDTF_VERSION, true);
        wp_localize_script('tgs-idtf-variants', 'tgsIdtf', $localize);
    }
});
