<?php
/**
 * Thống kê mã định danh theo sản phẩm + biến thể
 * @package tgs_product_identifier
 */
if (!defined('ABSPATH')) exit;
?>
<div class="container-xxl flex-grow-1 container-p-y idtf-prodcodes">

    <!-- Tiêu đề + tìm mã nhanh -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bx bx-package me-2"></i>Thống kê mã định danh</h4>
    </div>

    <!-- Quick barcode search -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bx bx-qr-scan"></i></span>
                        <input type="text" id="pcQuickBarcode" class="form-control" placeholder="Quét/nhập mã barcode để tra cứu nhanh..." />
                        <button class="btn btn-primary" id="btnPcQuickSearch" type="button">Tra cứu</button>
                    </div>
                </div>
                <div class="col-md-7 col-12 mt-2 mt-md-0">
                    <div id="pcQuickResult" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar: search + per_page -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group" style="max-width:350px;">
                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                    <input type="text" id="pcProductSearch" class="form-control form-control-sm" placeholder="Tìm sản phẩm (tên, SKU, barcode)..." />
                </div>
                <div class="input-group" style="max-width:160px;">
                    <span class="input-group-text" style="font-size:12px;">Hiển thị</span>
                    <select id="pcPerPage" class="form-select form-select-sm">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <span class="text-muted ms-auto" style="font-size:12px;" id="pcPageInfo"></span>
            </div>
        </div>
    </div>

    <!-- Product list -->
    <div id="pcProductList">
        <div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Đang tải...</div>
    </div>

    <!-- Pager -->
    <nav class="d-flex justify-content-center mt-3">
        <ul class="pagination pagination-sm mb-0" id="pcPager"></ul>
    </nav>
</div>

<!-- MODAL: Chi tiết mã theo combo -->
<div class="modal fade" id="modalProductCodes" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-list-check me-1"></i>Danh sách mã định danh</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Product info -->
                <div id="pcModalProductInfo" class="idtf-modal-product-info mb-3"></div>

                <!-- Active combo badge -->
                <div id="pcModalComboInfo" class="mb-3"></div>

                <!-- Filters row -->
                <div class="row g-2 mb-3">
                    <div class="col-md-3 col-6">
                        <select id="pcFilterExpDate" class="form-select form-select-sm">
                            <option value="">-- HSD --</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <select id="pcFilterLotCode" class="form-select form-select-sm">
                            <option value="">-- Mã lô --</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <select id="pcFilterPerPage" class="form-select form-select-sm">
                            <option value="50">50 / trang</option>
                            <option value="100">100 / trang</option>
                            <option value="200">200 / trang</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6 text-end">
                        <button class="btn btn-sm btn-outline-success" id="btnPcExport"><i class="bx bx-download me-1"></i>Xuất Excel</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Mã barcode</th>
                                <th>Trạng thái</th>
                                <th>Biến thể</th>
                                <th>HSD</th>
                                <th>Mã lô</th>
                                <th>Cập nhật</th>
                            </tr>
                        </thead>
                        <tbody id="pcModalCodesBody">
                            <tr><td colspan="7" class="text-center text-muted py-3">Chọn sản phẩm và bấm xem mã.</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Modal pager -->
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span id="pcModalCodesInfo" class="text-muted" style="font-size:12px;"></span>
                    <nav><ul class="pagination pagination-sm mb-0" id="pcModalPager"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>
