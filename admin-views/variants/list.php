<?php
/**
 * Quản lý biến thể sản phẩm
 * @package tgs_product_identifier
 */
if (!defined('ABSPATH')) exit;
?>
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bx bx-category me-2"></i>Quản lý biến thể sản phẩm</h4>
            <p class="text-muted mb-0" style="font-size:13px;">Tạo và quản lý biến thể (size, màu, hương vị, độ tuổi...) cho từng sản phẩm.</p>
        </div>
    </div>

    <!-- Search product -->
    <div class="card mb-3 idtf-search-card">
        <div class="card-body">
            <label class="form-label fw-semibold">Chọn sản phẩm để xem biến thể</label>
            <div class="position-relative idtf-search-wrap">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                    <input type="text" id="varProductSearch" class="form-control" placeholder="Tên SP, SKU hoặc barcode..." />
                </div>
                <input type="hidden" id="varProductId" value="" />
                <div id="varProductDropdown" class="idtf-dropdown" style="display:none;"></div>
            </div>
            <div id="varProductInfo" class="mt-2" style="display:none;">
                <div class="alert alert-success py-2 px-3 mb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span id="varProductName" class="fw-semibold"></span>
                            <span class="badge bg-label-secondary ms-2">SKU: <b id="varProductSku">—</b></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="varClearProduct"><i class="bx bx-x me-1"></i>Đổi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Variant list + form -->
    <div id="variantSection" style="display:none;">
        <div class="row">
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Danh sách biến thể</h5></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Loại</th>
                                    <th>Nhãn</th>
                                    <th>Giá trị</th>
                                    <th>SKU sản phẩm</th>
                                    <th>SKU suffix</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="variantTableBody">
                                <tr><td colspan="6" class="text-center text-muted py-3">Chưa có biến thể</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0" id="varFormTitle">Thêm biến thể</h5></div>
                    <div class="card-body">
                        <form id="variantForm" autocomplete="off">
                            <input type="hidden" id="varEditId" value="0" />
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Loại</label>
                                <select id="varType" class="form-select">
                                    <option value="size">Kích cỡ</option>
                                    <option value="color">Màu sắc</option>
                                    <option value="expiry">Hạn sử dụng</option>
                                    <option value="flavor">Hương vị</option>
                                    <option value="weight">Trọng lượng</option>
                                    <option value="age_range">Độ tuổi</option>
                                    <option value="custom">Tùy chỉnh</option>
                                </select>
                            </div>
                            <div class="mb-2" id="varPresetsArea">
                                <div id="varPresetChips" class="d-flex flex-wrap gap-1 mb-2"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nhãn</label>
                                <input type="text" id="varLabel" class="form-control" placeholder="VD: Kích cỡ" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Giá trị</label>
                                <input type="text" id="varValue" class="form-control" placeholder="VD: M" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">SKU suffix</label>
                                <input type="text" id="varSkuSuffix" class="form-control" placeholder="VD: -M" />
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bx bx-save me-1"></i>Lưu biến thể</button>
                            <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="varCancelEdit" style="display:none;">Hủy sửa</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
