<?php
/**
 * Chi tiết phiếu sinh mã trống
 * @package tgs_product_identifier
 */
if (!defined('ABSPATH')) exit;
$ledger_id = intval($_GET['ledger_id'] ?? 0);
?>
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bx bx-detail me-2"></i>Chi tiết phiếu sinh mã trống</h4>
        <div>
            <a href="<?php echo function_exists('tgs_url') ? tgs_url('idtf-blank-list') : '#'; ?>" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i>Quay lại
            </a>
            <a href="<?php echo function_exists('tgs_url') ? tgs_url('idtf-blank-create') : '#'; ?>" class="btn btn-primary ms-1">
                <i class="bx bx-plus me-1"></i>Sinh thêm mã
            </a>
        </div>
    </div>

    <input type="hidden" id="detailLedgerId" value="<?php echo esc_attr($ledger_id); ?>" />

    <!-- Ledger info -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bx bx-file me-1"></i>Thông tin phiếu</h5>
            <span class="badge bg-label-primary" id="ticketCode">—</span>
        </div>
        <div class="card-body">
            <div class="row" id="ledgerInfo">
                <div class="col-md-3"><strong>Tiêu đề:</strong> <span id="infoTitle">—</span></div>
                <div class="col-md-3"><strong>Người tạo:</strong> <span id="infoUser">—</span></div>
                <div class="col-md-3"><strong>Ngày tạo:</strong> <span id="infoDate">—</span></div>
                <div class="col-md-3"><strong>Ghi chú:</strong> <span id="infoNote">—</span></div>
            </div>
            <div class="row mt-3" id="statsRow">
                <div class="col-6 col-md-3 mb-2">
                    <div class="idtf-stat-card">
                        <div class="idtf-stat-num" id="statTotal">0</div>
                        <div class="idtf-stat-label">Tổng mã</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="idtf-stat-card stat-warning">
                        <div class="idtf-stat-num" id="statBlank">0</div>
                        <div class="idtf-stat-label">Mã trống</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="idtf-stat-card stat-success">
                        <div class="idtf-stat-num" id="statIdentified">0</div>
                        <div class="idtf-stat-label">Đã định danh</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="idtf-stat-card stat-info">
                        <div class="idtf-stat-num" id="statSold">0</div>
                        <div class="idtf-stat-label">Đã bán/xuất</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group" style="max-width:300px;">
                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                    <input type="text" id="lotSearch" class="form-control form-control-sm" placeholder="Tìm mã barcode..." />
                </div>
                <div class="input-group" style="max-width:160px;">
                    <span class="input-group-text" style="font-size:12px;">Hiển thị</span>
                    <select id="lotPerPage" class="form-select form-select-sm">
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="200">200</option>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnSelectAll">Chọn tất cả</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAll">Bỏ chọn</button>
                <span class="badge bg-label-primary" id="selectedBadge" style="display:none;">Đã chọn: <b id="selectedCount">0</b></span>
                <div class="vr"></div>
                <button type="button" class="btn btn-sm btn-info" id="btnPrintSelected" disabled>
                    <i class="bx bx-printer me-1"></i>In mã (<span id="printCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Lots table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="checkAll" /></th>
                        <th>#</th>
                        <th>Mã barcode</th>
                        <th>Trạng thái</th>
                        <th>Sản phẩm</th>
                        <th>Biến thể</th>
                        <th>HSD</th>
                        <th>Mã lô</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody id="lotsTableBody">
                    <tr><td colspan="9" class="text-center py-4 text-muted">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span id="lotsInfo" class="text-muted" style="font-size:13px;"></span>
            <nav><ul class="pagination pagination-sm mb-0" id="lotsPager"></ul></nav>
        </div>
    </div>
</div>
