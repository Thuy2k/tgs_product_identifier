<?php
/**
 * Sinh mã định danh trống — Trang tạo mới
 * @package tgs_product_identifier
 */
if (!defined('ABSPATH')) exit;
?>
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bx bx-barcode me-2"></i>Sinh mã định danh trống</h4>
            <p class="text-muted mb-0" style="font-size:13px;">Tạo mã barcode trống (chưa gắn sản phẩm). In ra dán trước, định danh sau.</p>
        </div>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('idtf-blank-list') : '#'; ?>" class="btn btn-outline-secondary">
            <i class="bx bx-list-ul me-1"></i>DS Phiếu
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="blankCodeForm" autocomplete="off">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Tên phiếu</label>
                        <input type="text" id="blankTitle" class="form-control" placeholder="VD: Phiếu sinh mã ngày 19/03/2026" />
                        <small class="text-muted">Để trống sẽ tự điền tên mặc định</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Số lượng mã <span class="text-danger">*</span></label>
                        <input type="number" id="blankQty" class="form-control" min="1" max="10000" value="100" required />
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <span class="idtf-qty-chip" data-qty="50">50</span>
                            <span class="idtf-qty-chip active" data-qty="100">100</span>
                            <span class="idtf-qty-chip" data-qty="200">200</span>
                            <span class="idtf-qty-chip" data-qty="500">500</span>
                            <span class="idtf-qty-chip" data-qty="1000">1.000</span>
                            <span class="idtf-qty-chip" data-qty="5000">5.000</span>
                        </div>
                        <small class="text-muted" id="paperEstimate">~ 1 trang giấy (2 mã/hàng, 70×22mm)</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Người tạo</label>
                        <input type="text" class="form-control" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" disabled />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="text" class="form-control" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" disabled />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Ngày tạo</label>
                        <input type="text" class="form-control" value="<?php echo wp_date('d/m/Y H:i'); ?>" disabled />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Ghi chú</label>
                    <textarea id="blankNote" class="form-control" rows="2" placeholder="Ghi chú..."></textarea>
                </div>

                <button type="submit" id="btnGenBlank" class="btn btn-primary btn-lg">
                    <i class="bx bx-barcode me-1"></i>Sinh mã trống
                </button>
            </form>
        </div>
    </div>

    <!-- Progress overlay -->
    <div id="genOverlay" class="idtf-overlay" style="display:none;">
        <div class="idtf-overlay-box">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <div id="genProgressText" class="fw-semibold mb-2">Đang sinh mã...</div>
            <div class="progress" style="width:300px; height:8px;">
                <div id="genProgressBar" class="progress-bar" role="progressbar" style="width:0%"></div>
            </div>
            <div id="genProgressCount" class="text-muted mt-1" style="font-size:12px;">0 / 0</div>
        </div>
    </div>

    <!-- Error display -->
    <div id="errorBanner" class="idtf-error-banner" style="display:none;"></div>
</div>
