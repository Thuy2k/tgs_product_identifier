<?php
/**
 * Workspace Định danh sản phẩm — MÀN HÌNH CHÍNH
 * @package tgs_product_identifier
 */
if (!defined('ABSPATH')) exit;
?>
<div class="container-xxl flex-grow-1 container-p-y idtf-workspace">

    <!-- Thanh tìm mã nhanh -->
    <div class="card mb-3 idtf-quicksearch-card">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-6 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bx bx-qr-scan"></i></span>
                        <input type="text" id="quickSearchCode" class="form-control" placeholder="Quét hoặc nhập mã barcode để tra cứu nhanh..." autofocus />
                        <button class="btn btn-primary" id="btnQuickSearch" type="button">Tra cứu</button>
                    </div>
                </div>
                <div class="col-md-6 col-12 mt-2 mt-md-0">
                    <div id="quickSearchResult" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-3 col-12 idtf-sidebar-col">
            <div class="card idtf-sidebar-card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold" style="font-size:13px;">Phiếu định danh</span>
                    <button class="btn btn-sm btn-primary" id="btnNewLedger" title="Tạo phiếu mới">
                        <i class="bx bx-plus"></i>
                    </button>
                </div>
                <div class="card-body p-2">
                    <input type="text" class="form-control form-control-sm mb-2" id="sidebarSearch" placeholder="Tìm phiếu..." />
                    <div id="sidebarList" class="idtf-sidebar-list">
                        <div class="text-center text-muted py-3" style="font-size:12px;">Đang tải...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-9 col-12">
            <!-- Tab bar -->
            <div class="idtf-tabbar mb-3" id="tabBar">
                <div class="idtf-tabs-scroll" id="tabsContainer">
                    <!-- Tabs will be rendered by JS -->
                </div>
                <button class="btn btn-sm btn-outline-primary idtf-tab-add" id="btnAddTab" title="Tạo phiếu mới">
                    <i class="bx bx-plus"></i>
                </button>
            </div>

            <!-- Empty state -->
            <div id="emptyState" class="card">
                <div class="card-body text-center py-5">
                    <i class="bx bx-purchase-tag" style="font-size:48px; color:#d4d8dd;"></i>
                    <p class="text-muted mt-2 mb-3">Chọn hoặc tạo phiếu định danh để bắt đầu.</p>
                    <button class="btn btn-primary" id="btnEmptyNew"><i class="bx bx-plus me-1"></i>Tạo phiếu định danh</button>
                </div>
            </div>

            <!-- Ledger content (hidden until tab selected) -->
            <div id="ledgerContent" style="display:none;">
                <!-- Form phiếu -->
                <div class="card mb-3" id="ledgerFormCard">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-5 col-12 mb-2 mb-md-0">
                                <label class="form-label fw-semibold mb-1" style="font-size:12px;">Tên phiếu</label>
                                <input type="text" id="ledgerTitle" class="form-control form-control-sm" />
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label fw-semibold mb-1" style="font-size:12px;">Người tạo</label>
                                <input type="text" id="ledgerUser" class="form-control form-control-sm" disabled />
                            </div>
                            <div class="col-md-2 col-6">
                                <label class="form-label fw-semibold mb-1" style="font-size:12px;">Ngày tạo</label>
                                <input type="text" id="ledgerDate" class="form-control form-control-sm" disabled />
                            </div>
                            <div class="col-md-2 col-12 mt-2 mt-md-0">
                                <button class="btn btn-primary btn-sm w-100" id="btnSaveLedger">
                                    <i class="bx bx-save me-1"></i>Lưu
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Blocks container -->
                <div id="blocksContainer">
                    <!-- Product blocks rendered by JS -->
                </div>

                <!-- Add block button -->
                <div class="text-center mt-3" id="addBlockArea" style="display:none;">
                    <button class="btn btn-outline-primary" id="btnAddBlock">
                        <i class="bx bx-plus me-1"></i>Thêm khối sản phẩm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile sidebar toggle -->
    <button class="btn btn-primary idtf-mobile-sidebar-toggle d-md-none" id="btnMobileSidebar">
        <i class="bx bx-menu"></i>
    </button>
</div>

<!-- MODAL: Tạo phiếu mới -->
<div class="modal fade" id="modalNewLedger" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tạo phiếu định danh mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tên phiếu</label>
                    <input type="text" id="newLedgerTitle" class="form-control" placeholder="VD: Định danh quần áo trẻ em ngày 19/03" />
                    <small class="text-muted">Để trống sẽ tự điền tên mặc định</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Ghi chú</label>
                    <textarea id="newLedgerNote" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button class="btn btn-primary" id="btnConfirmNewLedger"><i class="bx bx-save me-1"></i>Tạo phiếu</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Thêm khối SP -->
<div class="modal fade" id="modalAddBlock" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm sản phẩm vào phiếu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Search product -->
                <div class="mb-3 position-relative">
                    <label class="form-label fw-semibold">Tìm sản phẩm</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                        <input type="text" id="blockProductSearch" class="form-control" placeholder="Tên SP, SKU, barcode..." />
                    </div>
                    <input type="hidden" id="blockProductId" value="" />
                    <div id="blockProductDropdown" class="idtf-dropdown" style="display:none;"></div>
                </div>

                <!-- Selected product info -->
                <div id="blockProductInfo" class="alert alert-success py-2 px-3" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span id="blockProductName" class="fw-semibold"></span>
                            <div class="mt-1" style="font-size:12px;">
                                <span class="badge bg-label-secondary">SKU: <b id="blockProductSku">—</b></span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="blockClearProduct"><i class="bx bx-x"></i></button>
                    </div>
                </div>

                <!-- Variant select -->
                <div id="blockVariantSection" style="display:none;">
                    <label class="form-label fw-semibold">Chọn biến thể (nhiều lựa chọn)</label>
                    <div id="blockVariantList" class="d-flex flex-wrap gap-2 mb-2"></div>
                    <button type="button" class="btn btn-sm btn-outline-info" id="btnBlockQuickVariant">
                        <i class="bx bx-plus me-1"></i>Thêm nhanh biến thể
                    </button>
                </div>

                <!-- HSD + Mã lô (tuỳ chọn) -->
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bx bx-calendar me-1 text-muted"></i>
                        <span class="fw-semibold" style="font-size:13px;">HSD &amp; Mã lô <small class="text-muted fw-normal ms-1">(tuỳ chọn)</small></span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label mb-1" style="font-size:12px;">Hạn sử dụng (HSD)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bx bx-calendar"></i></span>
                                <input type="text" id="blockExpDateDisplay" class="form-control" placeholder="dd/mm/yyyy" maxlength="10" />
                                <input type="date" id="blockExpDatePicker" class="form-control" style="max-width:42px; padding:0; opacity:0.01; cursor:pointer;" tabindex="-1" />
                            </div>
                            <input type="hidden" id="blockExpDate" value="" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1" style="font-size:12px;">Mã lô</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bx bx-purchase-tag"></i></span>
                                <input type="text" id="blockLotCode" class="form-control" placeholder="VD: LOT2025-A1..." />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button class="btn btn-primary" id="btnConfirmAddBlock" disabled><i class="bx bx-plus me-1"></i>Thêm khối</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Quét mã vào khối -->
<div class="modal fade" id="modalScanCodes" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-qr-scan me-1"></i>Quét mã định danh</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scanBlockId" value="" />

                <!-- Product info banner -->
                <div id="scanProductInfo" class="idtf-modal-product-info mb-3" style="display:none;"></div>

                <!-- Big scan counter -->
                <div class="idtf-scan-counter-wrap text-center mb-3">
                    <div class="idtf-scan-counter" id="scanBigCount">0</div>
                    <div class="text-muted" style="font-size:12px;">mã đã quét</div>
                </div>

                <div class="mb-3">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bx bx-barcode"></i></span>
                        <input type="text" id="scanInput" class="form-control" placeholder="Quét hoặc nhập mã barcode..." autofocus />
                    </div>
                    <small class="text-muted"><i class="bx bx-info-circle me-1"></i>Quét liên tục — mã tự động thêm vào danh sách</small>
                </div>

                <!-- Scan result alert -->
                <div id="scanAlert" class="mb-3" style="display:none;"></div>

                <!-- Pending codes list -->
                <div id="scanPendingSection" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold" style="font-size:13px;">Mã đã quét (<span id="scanPendingCount">0</span>)</span>
                        <button class="btn btn-sm btn-outline-danger" id="btnScanClearAll">Xóa tất cả</button>
                    </div>
                    <div id="scanPendingList" class="idtf-scan-list"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button class="btn btn-success btn-lg" id="btnScanConfirm" disabled>
                    <i class="bx bx-check me-1"></i>Xác nhận gắn mã (<span id="scanConfirmCount">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Xem mã trong khối -->
<div class="modal fade" id="modalViewCodes" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-list-check me-1"></i>Danh sách mã đã gắn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="viewBlockId" value="" />

                <!-- Product info banner -->
                <div id="viewProductInfo" class="idtf-modal-product-info mb-3" style="display:none;"></div>

                <!-- Quick search in codes -->
                <div class="mb-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" id="viewCodesSearch" class="form-control" placeholder="Tìm mã barcode..." />
                        <button class="btn btn-outline-secondary" type="button" id="btnViewCodesSearchClear" style="display:none;"><i class="bx bx-x"></i></button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Mã barcode</th>
                                <th>Trạng thái</th>
                                <th>Ngày gắn</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="viewCodesBody">
                            <tr><td colspan="5" class="text-center text-muted py-3">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span id="viewCodesInfo" class="text-muted" style="font-size:12px;"></span>
                    <nav><ul class="pagination pagination-sm mb-0" id="viewCodesPager"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Thêm nhanh biến thể -->
<div class="modal fade" id="modalQuickVariant" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content idtf-modal-elevated">
            <div class="modal-header">
                <h5 class="modal-title">Thêm nhanh biến thể</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Loại biến thể</label>
                    <select id="qvType" class="form-select">
                        <option value="size">Kích cỡ</option>
                        <option value="color">Màu sắc</option>
                        <option value="expiry">Hạn sử dụng</option>
                        <option value="flavor">Hương vị</option>
                        <option value="weight">Trọng lượng</option>
                        <option value="age_range">Độ tuổi</option>
                        <option value="custom">Tùy chỉnh</option>
                    </select>
                </div>
                <div class="mb-2" id="qvPresetsArea">
                    <label class="form-label fw-semibold mb-1">Chọn nhanh:</label>
                    <div id="qvPresetChips" class="d-flex flex-wrap gap-1"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nhãn (Label)</label>
                    <input type="text" id="qvLabel" class="form-control" placeholder="VD: Kích cỡ" />
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Giá trị</label>
                    <input type="text" id="qvValue" class="form-control" placeholder="VD: M, L, XL..." />
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">SKU suffix</label>
                    <input type="text" id="qvSkuSuffix" class="form-control" placeholder="VD: -M, -XL..." />
                    <small class="text-muted">Hậu tố ghép vào SKU sản phẩm gốc</small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button class="btn btn-primary" id="btnQvSave"><i class="bx bx-save me-1"></i>Lưu biến thể</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Thêm nhanh SP -->
<div class="modal fade" id="modalQuickProduct" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content idtf-modal-elevated">
            <div class="modal-header">
                <h5 class="modal-title">Thêm nhanh sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tên sản phẩm <span class="text-danger">*</span></label>
                    <input type="text" id="qpName" class="form-control" />
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">SKU <span class="text-danger">*</span></label>
                        <input type="text" id="qpSku" class="form-control" />
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Đơn vị</label>
                        <input type="text" id="qpUnit" class="form-control" value="Cái" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Barcode</label>
                        <input type="text" id="qpBarcode" class="form-control" />
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Giá bán</label>
                        <input type="number" id="qpPrice" class="form-control" value="0" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button class="btn btn-primary" id="btnQpSave"><i class="bx bx-save me-1"></i>Thêm SP</button>
            </div>
        </div>
    </div>
</div>
