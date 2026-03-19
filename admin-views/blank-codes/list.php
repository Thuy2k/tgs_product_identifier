<?php
/**
 * DS phiếu sinh mã trống
 * @package tgs_product_identifier
 */
if (!defined('ABSPATH')) exit;
?>
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bx bx-list-ul me-2"></i>Danh sách phiếu sinh mã trống</h4>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('idtf-blank-create') : '#'; ?>" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i>Sinh mã trống
        </a>
    </div>

    <!-- Search -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" id="blankListSearch" class="form-control" placeholder="Tìm theo mã phiếu hoặc tiêu đề..." />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Mã phiếu</th>
                        <th>Tiêu đề</th>
                        <th>Số mã</th>
                        <th>Người tạo</th>
                        <th>Ngày tạo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="blankListBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span id="blankListInfo" class="text-muted" style="font-size:13px;"></span>
            <nav><ul class="pagination pagination-sm mb-0" id="blankListPager"></ul></nav>
        </div>
    </div>
</div>
