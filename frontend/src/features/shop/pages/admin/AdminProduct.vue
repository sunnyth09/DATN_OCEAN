<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import Swal from 'sweetalert2';
import AppIcon from '@/components/AppIcon.vue';
import { getApiBaseUrl, getAppBaseUrl } from '@/utils/url';
import QRCode from 'qrcode';
import { sanitizeHtml } from '@/utils/sanitize';
import BaseSelect from '@/components/base/BaseSelect.vue';

const showToastMsg = (message, type = 'success') => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    title: type === 'success' ? 'Thành công' : (type === 'error' || type === 'danger' ? 'Lỗi' : 'Thông báo'),
    text: message,
    icon: type === 'danger' ? 'error' : type,
    showConfirmButton: false,
    timer: 3000
  });
};

const route = useRoute();
const router = useRouter();

const products = ref([]);
const isLoading = ref(true);
const isInitialLoad = ref(true);

// Bulk Actions State
const selectedProducts = ref([]);
const selectAll = ref(false);

watch(selectAll, (val) => {
    if (val) {
        selectedProducts.value = products.value.map(p => p.product_id);
    } else if (selectedProducts.value.length === products.value.length) {
        // Only clear if all were selected by the checkbox, not if user manually unselected one
        selectedProducts.value = [];
    }
});

watch(selectedProducts, (val) => {
    if (val.length === 0) {
        selectAll.value = false;
    } else if (val.length === products.value.length && products.value.length > 0) {
        selectAll.value = true;
    } else {
        selectAll.value = false;
    }
});

const searchQuery = ref(route.query.search || '');
const statusFilter = ref(route.query.status || '');
const categoryFilter = ref(route.query.category_id || '');
const brandFilter = ref(route.query.brand_ids || '');
const sortByFilter = ref(route.query.sort_by || '');
const priceFilter = ref(route.query.price_range || '');

const categories = ref([]);
const brands = ref([]);

const fetchCategories = async () => {
    try {
        const res = await api.get('/categories');
        categories.value = res.data.data || res.data;
    } catch(e) {}
};

const fetchBrands = async () => {
    try {
        const res = await api.get('/brands');
        brands.value = res.data.data || res.data;
    } catch(e) {}
};

const categoryOptions = computed(() => {
    return categories.value.map(cat => ({ value: cat.category_id, label: cat.name }));
});

const brandOptions = computed(() => {
    return brands.value.map(b => ({ value: b.brand_id, label: b.name }));
});

const priceOptions = [
    { value: '0-500000', label: 'Dưới 500.000₫' },
    { value: '500000-1000000', label: '500.000₫ - 1.000.000₫' },
    { value: '1000000-3000000', label: '1.000.000₫ - 3.000.000₫' },
    { value: '3000000-5000000', label: '3.000.000₫ - 5.000.000₫' },
    { value: '5000000-', label: 'Trên 5.000.000₫' }
];

const sortOptions = [
    { value: 'latest', label: 'Mới nhất' },
    { value: 'oldest', label: 'Cũ nhất' },
    { value: 'price-asc', label: 'Giá tăng dần' },
    { value: 'price-desc', label: 'Giá giảm dần' }
];
const currentPage = ref(parseInt(route.query.page) || 1);
const totalProducts = ref(0);
const limit = 10;


const storageUrl = import.meta.env.VITE_API_STORAGE || `${getAppBaseUrl()}/storage`;

// Chuẩn hóa URL ảnh: DB có thể lưu path đã kèm '/storage/' (vd '/storage/products/x.webp'),
// path trần ('products/x.webp'), hoặc URL tuyệt đối. Tránh lặp '/storage//storage/'.
const buildMedia = (path) => {
    if (!path) return null;
    if (/^https?:\/\//i.test(path)) return path;
    const clean = String(path).replace(/^\/+/, '');
    if (clean.startsWith('storage/')) {
        return `${getAppBaseUrl()}/${clean}`;
    }
    return `${storageUrl}/${clean}`;
};

const productCache = new Map();

const fetchProducts = async () => {
    isLoading.value = true;
    try {
        selectedProducts.value = [];
        const params = new URLSearchParams({
            page: currentPage.value,
            limit: limit,
        });
        if (searchQuery.value) params.append('search', searchQuery.value);
        if (statusFilter.value) params.append('status', statusFilter.value);
        if (categoryFilter.value) params.append('category_id', categoryFilter.value);
        if (brandFilter.value) params.append('brand_ids', brandFilter.value);
        if (sortByFilter.value) params.append('sort_by', sortByFilter.value);
        if (priceFilter.value) params.append('price_range', priceFilter.value);
        
        const cacheKey = params.toString();
        
        // Fast Cache Check
        if (productCache.has(cacheKey)) {
            const cached = productCache.get(cacheKey);
            products.value = cached.products;
            totalProducts.value = cached.total;
            isLoading.value = false;
            isInitialLoad.value = false;
            return; // Exit early since we have cached data
        }

        const response = await api.get(`/products?${cacheKey}`);
        products.value = response.data.data || response.data;
        totalProducts.value = response.data.total || products.value.length;
        
        // Save to Cache
        productCache.set(cacheKey, {
            products: products.value,
            total: totalProducts.value
        });
    } catch (error) {
        console.error('Error fetching products:', error);
    } finally {
        isLoading.value = false;
        isInitialLoad.value = false;
    }
};

onMounted(async () => {
    fetchCategories();
    fetchBrands();
    await fetchProducts();
    if (route.query.edited_id) {
        nextTick(() => {
            setTimeout(() => {
                const el = document.getElementById(`product-${route.query.edited_id}`);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('highlight-edited');
                    setTimeout(() => el.classList.remove('highlight-edited'), 3000);
                }
            }, 300);
        });
    }
});

const totalPages = computed(() => Math.ceil(totalProducts.value / limit));

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    
    if (total <= 7) {
        const pages = [];
        for (let i = 1; i <= total; i++) pages.push(i);
        return pages;
    }
    
    if (current <= 4) {
        return [1, 2, 3, 4, 5, '...', total];
    }
    
    if (current >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }
    
    return [1, '...', current - 1, current, current + 1, '...', total];
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const getStatusLabel = (status) => {
    const map = {
        draft: 'Bản nháp',
        active: 'Đang bán',
        inactive: 'Tạm ẩn',
        out_of_stock: 'Hết hàng',
        deleted: 'Đã xóa',
    };
    return map[status] || status;
};

const getTypeLabel = (type) => {
    return type === 'simple' ? 'Đơn giản' : 'Biến thể';
};

const getImageUrl = (product) => {
    if (product.thumbnail_url) {
        return buildMedia(product.thumbnail_url);
    }
    if (product.main_image?.image_url) {
        return buildMedia(product.main_image.image_url);
    }
    return null;
};

let filterTimeout;
const updateRouteAndFetch = () => {
    const query = { ...route.query };
    
    if (currentPage.value > 1) query.page = currentPage.value;
    else delete query.page;
    
    if (searchQuery.value) query.search = searchQuery.value;
    else delete query.search;
    
    if (statusFilter.value) query.status = statusFilter.value;
    else delete query.status;
    
    if (categoryFilter.value) query.category_id = categoryFilter.value; else delete query.category_id;
    if (brandFilter.value) query.brand_ids = brandFilter.value; else delete query.brand_ids;
    if (sortByFilter.value) query.sort_by = sortByFilter.value; else delete query.sort_by;
    if (priceFilter.value) query.price_range = priceFilter.value; else delete query.price_range;
    
    router.replace({ query }).catch(() => {});
    
    // Build cache key to check if we can load instantly
    const params = new URLSearchParams({ page: currentPage.value, limit: limit });
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (statusFilter.value) params.append('status', statusFilter.value);
    if (categoryFilter.value) params.append('category_id', categoryFilter.value);
    if (brandFilter.value) params.append('brand_ids', brandFilter.value);
    if (sortByFilter.value) params.append('sort_by', sortByFilter.value);
    if (priceFilter.value) params.append('price_range', priceFilter.value);
    
    if (productCache.has(params.toString())) {
        clearTimeout(filterTimeout);
        fetchProducts(); // Instant hit
        return;
    }
    
    isInitialLoad.value = true;
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        fetchProducts();
    }, 300); // 300ms debounce
};

let searchTimeout;
const handleSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleSearch();
    }, 500);
};

const handleSearch = () => {
    currentPage.value = 1;
    updateRouteAndFetch();
};

const handleAdvancedFilter = () => {
    currentPage.value = 1;
    updateRouteAndFetch();
};

const handleFilterStatus = (status) => {
    statusFilter.value = statusFilter.value === status ? '' : status;
    currentPage.value = 1;
    updateRouteAndFetch();
};

const resetFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    categoryFilter.value = '';
    brandFilter.value = '';
    sortByFilter.value = '';
    priceFilter.value = '';
    currentPage.value = 1;
    updateRouteAndFetch();
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || statusFilter.value || categoryFilter.value || brandFilter.value || sortByFilter.value || priceFilter.value;
});

const handleDelete = async (productId, isDeleted) => {
    const confirmMsg = isDeleted 
        ? 'Bạn có chắc chắn muốn xóa vĩnh viễn sản phẩm này? Thao tác này không thể hoàn tác!' 
        : 'Bạn có chắc chắn muốn xóa tạm sản phẩm này?';
    
    const result = await Swal.fire({
        title: 'Xác nhận xóa',
        text: confirmMsg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    try {
        await api.delete(`/products/${productId}`);
        showToastMsg(isDeleted ? 'Xóa vĩnh viễn thành công.' : 'Xóa sản phẩm thành công.', 'success');
        productCache.clear();
        fetchProducts();
    } catch (error) {
        console.error('Error deleting product:', error);
        showToastMsg('Không thể xóa sản phẩm.', 'danger');
    }
};

const handleBulkDelete = async () => {
    if (selectedProducts.value.length === 0) return;
    
    const result = await Swal.fire({
        title: 'Xác nhận xóa hàng loạt',
        text: `Bạn có chắc chắn muốn xóa ${selectedProducts.value.length} sản phẩm đã chọn?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    
    if (!result.isConfirmed) return;
    
    try {
        // Implement bulk delete - calling delete sequentially for now if no bulk API exists
        // Ideally there should be a bulk API like api.post('/products/bulk-delete', { ids: selectedProducts.value })
        const promises = selectedProducts.value.map(id => api.delete(`/products/${id}`));
        await Promise.all(promises);
        
        showToastMsg(`Đã xóa thành công ${selectedProducts.value.length} sản phẩm.`, 'success');
        selectedProducts.value = [];
        productCache.clear();
        fetchProducts();
    } catch (error) {
        console.error('Error bulk deleting products:', error);
        showToastMsg('Có lỗi xảy ra khi xóa hàng loạt.', 'danger');
    }
};

const handleBulkStatusChange = async (newStatus) => {
    if (selectedProducts.value.length === 0) return;
    
    const statusText = newStatus === 'active' ? 'Đang bán' : (newStatus === 'inactive' ? 'Tạm ẩn' : newStatus);
    
    const result = await Swal.fire({
        title: 'Cập nhật trạng thái',
        text: `Chuyển ${selectedProducts.value.length} sản phẩm sang trạng thái "${statusText}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'Cập nhật',
        cancelButtonText: 'Hủy'
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const promises = selectedProducts.value.map(id => api.put(`/products/${id}`, { status: newStatus }));
        await Promise.all(promises);
        
        showToastMsg(`Đã cập nhật trạng thái cho ${selectedProducts.value.length} sản phẩm.`, 'success');
        selectedProducts.value = [];
        productCache.clear();
        fetchProducts();
    } catch (error) {
        console.error('Error bulk updating status:', error);
        showToastMsg('Có lỗi xảy ra khi cập nhật trạng thái.', 'danger');
    }
};

const printPriceLabel = async (product) => {
    // Generate default date string for current month
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    const defaultDateStr = `${String(firstDay.getDate()).padStart(2, '0')}/${String(firstDay.getMonth() + 1).padStart(2, '0')} - ${String(lastDay.getDate()).padStart(2, '0')}/${String(lastDay.getMonth() + 1).padStart(2, '0')}/${lastDay.getFullYear()}`;

    // Prompt admin for shelf/position and valid date
    const { value: formValues } = await Swal.fire({
        title: 'Thông tin bổ sung tem giá',
        html: `
            <div style="text-align: left; padding: 10px;">
                <label style="font-size: 14px; font-weight: bold; margin-bottom: 5px; display: block; color: #333;">Mã kệ / Vị trí (VD: K35 M3 VT6)</label>
                <input id="swal-shelf" class="swal2-input" placeholder="Nhập vị trí kệ..." style="width: 100%; margin: 0 0 15px 0; max-width: 100%;" value="K01">
                
                <label style="font-size: 14px; font-weight: bold; margin-bottom: 5px; display: block; color: #333;">Thời gian áp dụng</label>
                <input id="swal-date" class="swal2-input" placeholder="VD: 01/07 - 04/08/2026" style="width: 100%; margin: 0 0 15px 0; max-width: 100%;" value="${defaultDateStr}">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'In Tem',
        cancelButtonText: 'Hủy',
        preConfirm: () => {
            return {
                shelf: document.getElementById('swal-shelf').value,
                date: document.getElementById('swal-date').value
            }
        }
    });

    if (!formValues) return;

    try {
        const productUrl = `${window.location.origin}/product/${product.slug}`;
        const qrDataUrl = await QRCode.toDataURL(productUrl, { width: 150, margin: 1 });
        
        const priceStr = formatPrice(product.min_price || product.lowest_price_variant?.price).replace('₫', '').trim();
        const skuText = product.variants && product.variants.length > 0 ? product.variants[0].sku : (product.sku || 'N/A');
        
        const printWindow = window.open('', '_blank', 'width=400,height=300');
        printWindow.document.write(`
            <html>
            <head>
                <title>In Tem Giá - ${product.name}</title>
                <style>
                    @page { margin: 0; size: auto; }
                    body { 
                        margin: 0; 
                        font-family: Arial, sans-serif; 
                        width: 180px; /* Tương đương khổ giấy nhỏ 50mm */
                        padding: 10px 5px;
                        box-sizing: border-box;
                        background: #fff;
                        color: #000;
                    }
                    .price-row {
                        text-align: center;
                        font-size: 26px;
                        font-weight: 900;
                        letter-spacing: -0.5px;
                        border-bottom: 1px dashed #000;
                        padding-bottom: 5px;
                        margin-bottom: 5px;
                    }
                    .price-row span {
                        font-size: 12px;
                        font-weight: normal;
                    }
                    .content-row {
                        display: flex;
                        align-items: flex-start;
                    }
                    .qr-col {
                        margin-right: 6px;
                    }
                    .qr-col img {
                        width: 48px;
                        height: 48px;
                        display: block;
                    }
                    .info-col {
                        flex: 1;
                        font-size: 10px;
                        line-height: 1.25;
                    }
                    .product-name {
                        font-weight: bold;
                        font-size: 11px;
                        text-transform: uppercase;
                        margin-bottom: 3px;
                    }
                    .product-meta {
                        font-size: 9px;
                        line-height: 1.3;
                    }
                </style>
            </head>
            <body>
                <div class="price-row">
                    ${priceStr} <span>đ</span>
                </div>
                <div class="content-row">
                    <div class="qr-col">
                        <img src="${qrDataUrl}" />
                    </div>
                    <div class="info-col">
                        <div class="product-name">${product.name}</div>
                        <div class="product-meta">
                            ${formValues.shelf ? formValues.shelf + ' - ' : ''}SKU: ${skuText} <br/>
                            Áp dụng: ${formValues.date}
                        </div>
                    </div>
                </div>
                <script>
                    window.onload = () => {
                        window.print();
                        setTimeout(() => window.close(), 500);
                    };
                <\/script>
            <\/body>
            <\/html>
        `);
        printWindow.document.close();
    } catch (error) {
        console.error('Lỗi in tem giá:', error);
        showToastMsg('Không thể in tem giá.', 'danger');
    }
};

const handleRestore = async (productId) => {
    const result = await Swal.fire({
        title: 'Xác nhận khôi phục',
        text: 'Bạn có chắc chắn muốn khôi phục sản phẩm này?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Khôi phục',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;
    try {
        await api.put(`/products/${productId}/restore`);
        showToastMsg('Khôi phục sản phẩm thành công.', 'success');
        productCache.clear();
        fetchProducts();
    } catch (error) {
        console.error('Error restoring product:', error);
        showToastMsg('Không thể khôi phục sản phẩm.', 'danger');
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        updateRouteAndFetch();
    }
};

// ===== Export Excel =====
const showExportModal = ref(false);
const exportDatePreset = ref('today'); // 'today', 'this_week', 'this_month', 'custom'
const exportFromDate = ref('');
const exportToDate = ref('');
const exportStatus = ref('all');
const exportCategoryId = ref('');
const exportBrandId = ref('');
const exportType = ref('variant');
const isExporting = ref(false);

const openExportModal = () => {
    showExportModal.value = true;
};

const closeExportModal = () => {
    showExportModal.value = false;
};

const handleExportExcel = async () => {
    isExporting.value = true;
    try {
        const params = new URLSearchParams({
            date_preset: exportDatePreset.value,
            export_type: exportType.value,
            status: exportStatus.value,
        });

        if (exportDatePreset.value === 'custom') {
            if (exportFromDate.value) params.append('from_date', exportFromDate.value);
            if (exportToDate.value) params.append('to_date', exportToDate.value);
        }
        if (exportCategoryId.value) params.append('category_id', exportCategoryId.value);
        if (exportBrandId.value) params.append('brand_id', exportBrandId.value);

        const response = await api.get(`/products/export?${params.toString()}`, {
            responseType: 'blob',
        });

        const blob = new Blob([response.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const nowStr = new Date().toISOString().slice(0, 10);
        link.setAttribute('download', `danh_sach_san_pham_${nowStr}.xlsx`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);

        showToastMsg('Xuất file Excel thành công!', 'success');
        closeExportModal();
    } catch (error) {
        console.error('Export error:', error);
        showToastMsg('Có lỗi xảy ra khi xuất file Excel.', 'danger');
    } finally {
        isExporting.value = false;
    }
};

// ===== Import Excel =====
const showImportModal = ref(false);
const importFile = ref(null);
const importFileName = ref('');
const isImporting = ref(false);

const apiBaseUrl = getApiBaseUrl();

const openImportModal = () => {
    showImportModal.value = true;
    importFile.value = null;
    importFileName.value = '';
};

const closeImportModal = () => {
    showImportModal.value = false;
    importFile.value = null;
    importFileName.value = '';
};

const handleImportFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        importFile.value = file;
        importFileName.value = file.name;
    }
};

/**
 * Xử lý Import Excel
 * FLOW:
 * 1. Hiển thị loading SweetAlert
 * 2. Gửi file lên API POST /products/import
 * 3. Nhận kết quả: success_count, error_count, errors[]
 * 4. Hiển thị kết quả chi tiết (số thành công, chi tiết lỗi)
 * 5. Tải lại danh sách sản phẩm
 */
const importResult = ref(null);
const showImportResult = ref(false);

const handleImportExcel = async () => {
    if (!importFile.value) {
        showToastMsg('Vui lòng chọn file Excel (.xlsx) để import.', 'danger');
        return;
    }

    isImporting.value = true;

    const formData = new FormData();
    formData.append('excel_file', importFile.value);

    try {
        // Bước 1: Upload file → server lưu vào disk và trả về session_id + total_chunks
        const parseRes = await api.post('/products/import', formData, {
            timeout: 120000, // 2 phút cho upload và đếm dòng
        });

        if (!parseRes.data.success) {
            throw new Error(parseRes.data.message || 'Lỗi khi upload file.');
        }

        const { session_id, total_chunks } = parseRes.data;
        let cumulativeSuccess = 0;
        let cumulativeErrors = [];

        // Bước 2: Gọi từng chunk tuần tự — KHÔNG đặt timeout (mỗi chunk có thể mất vài phút nếu download ảnh)
        for (let i = 0; i < total_chunks; i++) {
            try {
                const chunkRes = await api.post('/products/import/process-chunk', {
                    session_id,
                    chunk_index: i
                }, { 
                    timeout: 0  // Vô hiệu hóa timeout — Nginx đã được cấu hình 300s
                });

                if (chunkRes.data.success) {
                    cumulativeSuccess += chunkRes.data.success_count || 0;
                    if (chunkRes.data.errors && chunkRes.data.errors.length > 0) {
                        cumulativeErrors = cumulativeErrors.concat(chunkRes.data.errors);
                    }
                } else {
                    cumulativeErrors.push(`Lỗi chunk ${i}: ` + (chunkRes.data.error || 'Unknown error'));
                }
            } catch (chunkErr) {
                console.error(`Chunk ${i} error:`, chunkErr);
                // Không dừng — tiếp tục chunk tiếp theo
                cumulativeErrors.push(`Lỗi kết nối chunk ${i}: ` + (chunkErr.message || 'Network error'));
            }
        }

        importResult.value = { 
            success_count: cumulativeSuccess, 
            error_count: cumulativeErrors.length, 
            errors: cumulativeErrors 
        };
        closeImportModal();
        showImportResult.value = true;
        productCache.clear();
        fetchProducts();

    } catch (error) {
        console.error('Import error:', error);
        const msg = error.response?.data?.message || error.message || 'Có lỗi xảy ra khi import file.';
        showToastMsg(msg, 'danger');
    } finally {
        isImporting.value = false;
    }
};

/**
 * Tải file Excel mẫu
 * FLOW: Gọi GET /products/import-template qua axios (kèm auth token) → tạo blob download
 */
const downloadTemplate = async () => {
    try {
        const response = await api.get('/products/import-template', { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'mau_import_san_pham.xlsx');
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Download template error:', error);
        showToastMsg('Không thể tải file mẫu.', 'danger');
    }
};

// ===== Quick View =====
const showQuickViewModal = ref(false);
const quickViewProduct = ref(null);
const isLoadingQuickView = ref(false);
const qvSelectedImage = ref('');
const qvSafeShortDescription = computed(() => sanitizeHtml(quickViewProduct.value?.short_description));
const qvSafeDescription = computed(() => sanitizeHtml(quickViewProduct.value?.description));

const quickViewCache = ref({});

const openQuickView = async (slug) => {
    showQuickViewModal.value = true;
    
    // Kiểm tra cache
    if (quickViewCache.value[slug]) {
        quickViewProduct.value = quickViewCache.value[slug];
        if (quickViewProduct.value.thumbnail_url) {
            qvSelectedImage.value = buildMedia(quickViewProduct.value.thumbnail_url);
        } else if (quickViewProduct.value.images && quickViewProduct.value.images.length > 0) {
            qvSelectedImage.value = buildMedia(quickViewProduct.value.images[0].image_url);
        } else {
            qvSelectedImage.value = '';
        }
        return;
    }

    isLoadingQuickView.value = true;
    try {
        const response = await api.get(`/products/${slug}`);
        const productData = response.data.data || response.data;
        quickViewCache.value[slug] = productData; // Lưu vào cache
        quickViewProduct.value = productData;
        // Set preview ảnh ban đầu
        if (productData.thumbnail_url) {
            qvSelectedImage.value = buildMedia(productData.thumbnail_url);
        } else if (productData.images && productData.images.length > 0) {
            qvSelectedImage.value = buildMedia(productData.images[0].image_url);
        } else {
            qvSelectedImage.value = '';
        }
    } catch (error) {
        console.error("Error loading quick view:", error);
        showQuickViewModal.value = false;
    } finally {
        isLoadingQuickView.value = false;
    }
};

const closeQuickView = () => {
    showQuickViewModal.value = false;
    quickViewProduct.value = null;
    qvSelectedImage.value = '';
};

const selectQvImage = (url) => {
    qvSelectedImage.value = buildMedia(url);
};

const qvTotalStock = computed(() => {
    if (!quickViewProduct.value?.variants) return 0;
    return quickViewProduct.value.variants.reduce((sum, v) => sum + (v.stock || 0), 0);
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('vi-VN');
};
</script>

<template>
    <div class="products-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    Quản Lý Sản Phẩm
                </h1>
                <p class="page-subtitle">Quản lý kho hàng cửa hàng Ocean</p>
            </div>
            <div class="header-btns">
                <button class="btn-import" id="export-excel-btn" @click="openExportModal">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Xuất
                </button>
                <button class="btn-import" id="import-excel-btn" @click="openImportModal">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    Nhập
                </button>
                <router-link to="/admin/product/create" class="btn-primary" id="add-product-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Thêm Sản Phẩm
                </router-link>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s; padding: 0;">
            <!-- Status Tabs -->
            <div class="status-tabs-container">
                <div class="status-pills">
                    <button class="status-pill" :class="{ active: !statusFilter }" @click="handleFilterStatus('')">Tất cả</button>
                    <button class="status-pill" :class="{ active: statusFilter === 'active' }" @click="handleFilterStatus('active')">Đang bán</button>
                    <button class="status-pill" :class="{ active: statusFilter === 'draft' }" @click="handleFilterStatus('draft')">Nháp</button>
                    <button class="status-pill" :class="{ active: statusFilter === 'inactive' }" @click="handleFilterStatus('inactive')">Tạm ẩn</button>
                    <button class="status-pill" :class="{ active: statusFilter === 'out_of_stock' }" @click="handleFilterStatus('out_of_stock')">Hết hàng</button>
                    <button class="status-pill danger" :class="{ active: statusFilter === 'deleted' }" @click="handleFilterStatus('deleted')">Đã xóa</button>
                </div>
            </div>

            <div class="filters-inner" style="padding: 16px 20px;">
                <div class="filters-top" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; width: 100%;">
                    <div class="search-box" style="flex: 2; min-width: 280px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input 
                            type="text" 
                            v-model="searchQuery"
                            @input="handleSearchInput"
                            @keyup.enter="handleSearch"
                            placeholder="Tìm kiếm theo mã, tên sản phẩm..." 
                            class="search-input"
                        />
                    </div>
                    
                    <div class="advanced-filters" style="display: flex; gap: 12px; flex: 2; flex-wrap: wrap; justify-content: flex-end; align-items: center;">
                        <BaseSelect 
                            v-model="categoryFilter" 
                            @change="handleAdvancedFilter" 
                            :options="categoryOptions"
                            placeholder="Danh mục"
                            style="min-width: 140px;"
                        />
                        <BaseSelect 
                            v-model="brandFilter" 
                            @change="handleAdvancedFilter" 
                            :options="brandOptions"
                            placeholder="Thương hiệu"
                            style="min-width: 140px;"
                        />
                        <BaseSelect 
                            v-model="priceFilter" 
                            @change="handleAdvancedFilter" 
                            :options="priceOptions"
                            placeholder="Mức giá"
                            style="min-width: 120px;"
                        />
                        <BaseSelect 
                            v-model="sortByFilter" 
                            @change="handleAdvancedFilter" 
                            :options="sortOptions"
                            placeholder="Sắp xếp"
                            style="min-width: 120px;"
                        />
                    </div>
                </div>

                <div v-if="hasActiveFilters" style="margin-top: 12px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 0.85rem; color: var(--text-light);">Đang lọc dữ liệu</span>
                    <button class="btn-clear-filters" @click="resetFilters">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        Xóa bộ lọc
                    </button>
                </div>
            </div>
        </div>

        <!-- Modern Skeleton Loading State -->
        <div v-if="isInitialLoad" class="table-container ocean-card" style="position: relative;">
            <div class="table-header">
                <div class="skeleton-text" style="width: 150px; height: 20px; margin-top: 4px;"></div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px"></th>
                        <th>Sản phẩm</th>
                        <th>Phân loại</th>
                        <th>Giá</th>
                        <th>Kho</th>
                        <th>Trạng thái</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in 5" :key="i">
                        <td><div class="skeleton-box" style="width: 20px; height: 20px; border-radius: 4px;"></div></td>
                        <td>
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <div class="skeleton-box" style="width: 48px; height: 48px; border-radius: 8px;"></div>
                                <div>
                                    <div class="skeleton-text" style="width: 220px; height: 16px; margin-bottom: 8px;"></div>
                                    <div class="skeleton-text" style="width: 120px; height: 12px;"></div>
                                </div>
                            </div>
                        </td>
                        <td><div class="skeleton-text" style="width: 80px; height: 22px; border-radius: 12px;"></div></td>
                        <td><div class="skeleton-text" style="width: 90px; height: 16px;"></div></td>
                        <td><div class="skeleton-text" style="width: 60px; height: 22px; border-radius: 6px;"></div></td>
                        <td><div class="skeleton-text" style="width: 70px; height: 22px; border-radius: 6px;"></div></td>
                        <td>
                            <div class="actions-cell" style="justify-content: flex-end;">
                                <div class="skeleton-box" style="width: 36px; height: 36px; border-radius: 10px;"></div>
                                <div class="skeleton-box" style="width: 36px; height: 36px; border-radius: 10px;"></div>
                                <div class="skeleton-box" style="width: 36px; height: 36px; border-radius: 10px;"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Products Table -->
        <div v-else class="table-container ocean-card animate-in" :class="{ 'table-loading': isLoading && !isInitialLoad }" style="animation-delay: 0.2s; position: relative;">
            
            <!-- Bulk Actions Bar -->
            <div v-if="selectedProducts.length > 0" class="bulk-actions-bar animate-in">
                <div class="bulk-info">
                    <span class="bulk-count">{{ selectedProducts.length }}</span> sản phẩm được chọn
                </div>
                <div class="bulk-buttons">
                    <button class="btn-bulk btn-bulk-success" @click="handleBulkStatusChange('active')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        Đăng bán
                    </button>
                    <button class="btn-bulk btn-bulk-warning" @click="handleBulkStatusChange('inactive')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Tạm ẩn
                    </button>
                    <button class="btn-bulk btn-bulk-danger" @click="handleBulkDelete">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Xóa
                    </button>
                </div>
            </div>

            <div class="table-header">
                <span class="table-count">
                    <strong>{{ totalProducts }}</strong> sản phẩm
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" v-model="selectAll" class="custom-checkbox" />
                            </th>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Loại</th>
                            <th>Giá</th>
                            <th>Kho</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in products" :key="p.product_id" :id="`product-${p.product_id}`" :class="{ 'row-selected': selectedProducts.includes(p.product_id) }">
                            <td style="text-align: center;">
                                <input type="checkbox" :value="p.product_id" v-model="selectedProducts" class="custom-checkbox" />
                            </td>
                            <td><span class="badge-id">#{{ p.product_id }}</span></td>
                            <td>
                                <div class="prod-thumb">
                                    <img v-if="getImageUrl(p)" :src="getImageUrl(p)" :alt="p.name" />
                                    <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                </div>
                            </td>
                            <td>
                                <div class="prod-cell">
                                    <router-link :to="`/product/${p.slug}`" class="prod-name text-decoration-none">{{ p.name }}</router-link>
                                </div>
                            </td>
                            <td><span class="badge-type" :class="p.product_type">{{ getTypeLabel(p.product_type) }}</span></td>
                            <td>
                                <span class="val-price" v-if="p.min_price === p.max_price">{{ formatPrice(p.min_price || p.lowest_price_variant?.price) }}</span>
                                <span class="val-price" v-else>{{ formatPrice(p.min_price) }} - {{ formatPrice(p.max_price) }}</span>
                            </td>
                            <td>
                                <span class="badge-stock" :class="{ 
                                    'good': (p.variants_sum_stock || 0) > 5, 
                                    'low': (p.variants_sum_stock || 0) <= 5 && (p.variants_sum_stock || 0) > 0, 
                                    'out': (p.variants_sum_stock || 0) === 0 
                                }">
                                    {{ p.variants_sum_stock ?? 0 }}
                                </span>
                            </td>
                            <td><span class="badge-status" :class="p.status">{{ getStatusLabel(p.status) }}</span></td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-icon view" title="Xem Nhanh" @click="openQuickView(p.slug)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button class="btn-icon" title="In Tem Giá" @click="printPriceLabel(p)" style="color: #6c757d; background: #f8f9fa;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                            <rect x="6" y="14" width="12" height="8"></rect>
                                        </svg>
                                    </button>
                                    <router-link :to="{ path: `/admin/product/edit/${p.product_id}`, query: route.query }" class="btn-icon edit" title="Sửa">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </router-link>
                                    <button v-if="p.deleted_at" class="btn-icon restore" title="Khôi phục" @click="handleRestore(p.product_id)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                            <path d="M3 3v5h5"/>
                                        </svg>
                                    </button>
                                    <button class="btn-icon del" :title="p.deleted_at ? 'Xóa vĩnh viễn' : 'Xóa'" @click="handleDelete(p.product_id, p.deleted_at)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 6h18"/>
                                            <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                            <path d="M10 11v6"/>
                                            <path d="M14 11v6"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty -->
            <div v-if="products.length === 0" class="empty-state">
                <span class="empty-emoji mb-4">
                    <!-- Search icon -->
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <h3>Không tìm thấy sản phẩm</h3>
                <p>Thử tìm kiếm với từ khóa khác hoặc thêm sản phẩm mới.</p>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="pagination">
                <button class="page-btn" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>
                <template v-for="(item, index) in visiblePages" :key="index">
                    <span v-if="item === '...'" class="page-dots">...</span>
                    <button
                        v-else
                        class="page-btn"
                        :class="{ active: item === currentPage }"
                        @click="goToPage(item)"
                    >{{ item }}</button>
                </template>
                <button class="page-btn" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">›</button>
            </div>
        </div>

        <!-- ===== Quick View Modal ===== -->
        <Teleport to="body">
            <div class="qv-backdrop" v-if="showQuickViewModal" @click.self="closeQuickView">
                <div class="qv-modal animate-in">
                    <!-- Modal Header -->
                    <div class="qv-header">
                        <h2>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            Xem Nhanh Sản Phẩm
                        </h2>
                        <button class="qv-close" @click="closeQuickView">×</button>
                    </div>

                    <!-- Loading Skeleton -->
                    <div v-if="isLoadingQuickView" class="qv-body p-4">
                        <div class="qv-top">
                            <div class="qv-gallery" style="flex: 0 0 320px;">
                                <div class="skeleton-box" style="width: 100%; height: 320px; border-radius: 12px;"></div>
                                <div class="d-flex gap-2 mt-3">
                                    <div class="skeleton-box" style="width: 60px; height: 60px; border-radius: 8px;"></div>
                                    <div class="skeleton-box" style="width: 60px; height: 60px; border-radius: 8px;"></div>
                                    <div class="skeleton-box" style="width: 60px; height: 60px; border-radius: 8px;"></div>
                                </div>
                            </div>
                            <div class="qv-details" style="flex: 1;">
                                <div class="skeleton-text mb-3" style="width: 80%; height: 32px;"></div>
                                <div class="skeleton-text mb-4" style="width: 40%; height: 24px;"></div>
                                <div class="skeleton-text mb-2" style="width: 100%; height: 16px;"></div>
                                <div class="skeleton-text mb-2" style="width: 100%; height: 16px;"></div>
                                <div class="skeleton-text mb-4" style="width: 60%; height: 16px;"></div>
                                
                                <div class="skeleton-box mt-4" style="width: 100%; height: 160px; border-radius: 12px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="qv-body" v-if="quickViewProduct && !isLoadingQuickView">
                        <div class="qv-top">
                            <!-- Image Gallery -->
                            <div class="qv-gallery">
                                <div class="qv-main-img">
                                    <img v-if="qvSelectedImage" :src="qvSelectedImage" alt="Product Image" />
                                    <div v-else class="qv-no-img">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        <span>Chưa có ảnh</span>
                                    </div>
                                </div>
                                <!-- Thumbnails -->
                                <div class="qv-thumbs" v-if="quickViewProduct.images && quickViewProduct.images.length > 0">
                                    <div 
                                        class="qv-thumb-item" 
                                        :class="{ active: qvSelectedImage === buildMedia(quickViewProduct.thumbnail_url) }"
                                        v-if="quickViewProduct.thumbnail_url"
                                        @click="qvSelectedImage = buildMedia(quickViewProduct.thumbnail_url)"
                                    >
                                        <img :src="buildMedia(quickViewProduct.thumbnail_url)" alt="Main" />
                                    </div>
                                    <div 
                                        v-for="img in quickViewProduct.images.filter(i => !i.is_main)"
                                        :key="img.image_id"
                                        class="qv-thumb-item"
                                        :class="{ active: qvSelectedImage === buildMedia(img.image_url) }"
                                        @click="selectQvImage(img.image_url)"
                                    >
                                        <img :src="buildMedia(img.image_url)" alt="Gallery" />
                                    </div>
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div class="qv-info">
                                <h3 class="qv-name">{{ quickViewProduct.name }}</h3>
                                <p class="qv-slug">/{{ quickViewProduct.slug }}</p>

                                <div class="qv-price-block">
                                    <span class="qv-price" v-if="quickViewProduct.min_price === quickViewProduct.max_price">
                                        {{ formatPrice(quickViewProduct.min_price) }}
                                    </span>
                                    <span class="qv-price" v-else>
                                        {{ formatPrice(quickViewProduct.min_price) }} — {{ formatPrice(quickViewProduct.max_price) }}
                                    </span>
                                </div>

                                <div class="qv-meta">
                                    <div class="qv-meta-item">
                                        <span class="qv-meta-label">Loại</span>
                                        <span class="badge-type" :class="quickViewProduct.product_type">{{ getTypeLabel(quickViewProduct.product_type) }}</span>
                                    </div>
                                    <div class="qv-meta-item">
                                        <span class="qv-meta-label">Trạng thái</span>
                                        <span class="badge-status" :class="quickViewProduct.status">{{ getStatusLabel(quickViewProduct.status) }}</span>
                                    </div>
                                    <div class="qv-meta-item">
                                        <span class="qv-meta-label">Tổng kho</span>
                                        <span class="badge-stock" :class="{ good: qvTotalStock > 20, low: qvTotalStock > 0 && qvTotalStock <= 20, out: qvTotalStock === 0 }">{{ qvTotalStock }}</span>
                                    </div>
                                    <div class="qv-meta-item" v-if="quickViewProduct.category">
                                        <span class="qv-meta-label">Danh mục</span>
                                        <span class="qv-meta-value">{{ quickViewProduct.category.name }}</span>
                                    </div>
                                    <div class="qv-meta-item" v-if="quickViewProduct.brand">
                                        <span class="qv-meta-label">Thương hiệu</span>
                                        <span class="qv-meta-value">{{ quickViewProduct.brand.name }}</span>
                                    </div>
                                    <div class="qv-meta-item" v-if="quickViewProduct.is_featured">
                                        <span class="qv-meta-label">Nổi bật</span>
                                        <span class="qv-featured-badge">⭐ Sản phẩm nổi bật</span>
                                    </div>
                                </div>

                                <!-- Mô tả ngắn -->
                                <div class="qv-desc-section" v-if="quickViewProduct.short_description">
                                    <h4>Mô tả ngắn</h4>
                                    <div class="qv-desc-content" v-html="qvSafeShortDescription"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Variants Table -->
                        <div class="qv-section" v-if="quickViewProduct.variants && quickViewProduct.variants.length > 0">
                            <h4 class="qv-section-title">
                                Biến thể ({{ quickViewProduct.variants.length }})
                            </h4>
                            <div class="qv-variants-table-wrap">
                                <table class="qv-variants-table">
                                    <thead>
                                        <tr>
                                            <th>Ảnh</th>
                                            <th>SKU</th>
                                            <th>Màu</th>
                                            <th>Size</th>
                                            <th>Giá</th>
                                            <th>Kho</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="v in quickViewProduct.variants" :key="v.variant_id">
                                            <td>
                                                <div class="qv-variant-thumb" v-if="v.image_url" @click="selectQvImage(v.image_url)">
                                                    <img :src="buildMedia(v.image_url)" alt="Variant" />
                                                </div>
                                                <div class="qv-variant-thumb empty" v-else>—</div>
                                            </td>
                                            <td><code>{{ v.sku }}</code></td>
                                            <td>{{ v.color || '—' }}</td>
                                            <td>{{ v.size || '—' }}</td>
                                            <td class="qv-v-price">{{ formatPrice(v.price) }}</td>
                                            <td>
                                                <span class="badge-stock" :class="{ good: v.stock > 20, low: v.stock > 0 && v.stock <= 20, out: v.stock === 0 }">{{ v.stock }}</span>
                                            </td>
                                            <td><span class="badge-status" :class="v.status">{{ getStatusLabel(v.status) }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mô tả chi tiết -->
                        <div class="qv-section" v-if="quickViewProduct.description">
                            <h4 class="qv-section-title">Mô tả chi tiết</h4>
                            <div class="qv-desc-full" v-html="qvSafeDescription"></div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="qv-footer">
                            <router-link :to="{ path: `/admin/product/edit/${quickViewProduct.product_id}`, query: route.query }" class="btn-primary" @click="closeQuickView">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Chỉnh Sửa Sản Phẩm
                            </router-link>
                            <button class="btn-outline" @click="closeQuickView">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ===== Import Excel Modal ===== -->
        <Teleport to="body">
            <div class="import-backdrop" v-if="showImportModal" @click.self="closeImportModal">
                <div class="import-modal animate-in">
                    <div class="import-header">
                        <h2>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                            Nhập Sản Phẩm từ Excel
                        </h2>
                        <button class="import-close" @click="closeImportModal">×</button>
                    </div>

                    <div class="import-body">
                        <!-- Hướng dẫn -->
                        <div class="import-guide">
                            <h4><AppIcon name="import" size="16" /> Hướng dẫn</h4>
                            <ol>
                                <li>Tải file Excel mẫu bên dưới</li>
                                <li>Điền thông tin sản phẩm vào file (mỗi dòng = 1 sản phẩm đơn)</li>
                                <li>Chọn file đã điền và nhấn <strong>Bắt đầu Import</strong></li>
                            </ol>
                            <div class="import-cols-info">
                                <span class="col-tag required">ten_san_pham *</span>
                                <span class="col-tag required">danh_muc_id *</span>
                                <span class="col-tag required">gia_ban *</span>
                                <span class="col-tag required">so_luong_kho *</span>
                                <span class="col-tag">thuong_hieu_id</span>
                                <span class="col-tag">gia_goc</span>
                                <span class="col-tag">mo_ta_ngan</span>
                                <span class="col-tag">mo_ta_chi_tiet</span>
                                <span class="col-tag">trang_thai</span>
                                <span class="col-tag">noi_bat</span>
                                <span class="col-tag">ma_sku</span>
                                <span class="col-tag">chat_lieu</span>
                                <span class="col-tag">xuat_xu</span>
                                <span class="col-tag">kieu_dang</span>
                            </div>
                        </div>

                        <!-- Tải mẫu -->
                        <button class="btn-download-template" @click="downloadTemplate">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Tải File Excel Mẫu
                        </button>

                        <!-- Chọn file -->
                        <div class="import-dropzone" :class="{ 'has-file': importFileName }">
                            <input type="file" class="import-file-input" accept=".xlsx,.xls" @change="handleImportFileChange" />
                            <div v-if="!importFileName" class="dropzone-placeholder">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                <span>Nhấn để chọn file hoặc kéo thả vào đây</span>
                                <small>Chỉ chấp nhận file .xlsx, .xls (tối đa 10MB)</small>
                            </div>
                            <div v-else class="dropzone-selected">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#167a70" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <span>{{ importFileName }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="import-footer">
                        <button class="btn-outline" @click="closeImportModal">Hủy</button>
                        <button class="btn-primary" :disabled="!importFile || isImporting" @click="handleImportExcel">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="16 16 12 12 8 16"/>
                                <line x1="12" y1="12" x2="12" y2="21"/>
                                <path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
                            </svg>
                            {{ isImporting ? 'Đang xử lý...' : 'Bắt đầu Import' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Import Result Modal -->
        <Teleport to="body">
            <div class="import-backdrop" v-if="showImportResult" @click.self="showImportResult = false">
                <div class="import-modal animate-in" style="max-width: 480px;">
                    <div class="import-header">
                        <h2>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" :stroke="importResult?.error_count > 0 ? '#e65100' : '#167a70'" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Kết quả Import
                        </h2>
                        <button class="import-close" @click="showImportResult = false">×</button>
                    </div>
                    <div class="import-body" v-if="importResult">
                        <p style="font-size:1rem;margin-bottom:8px;"><strong>{{ importResult.success_count }}</strong> sản phẩm đã được thêm thành công.</p>
                        <div v-if="importResult.error_count > 0" style="margin-top:12px;">
                            <p style="color:#e65100;margin-bottom:8px;"><strong>{{ importResult.error_count }}</strong> dòng bị lỗi:</p>
                            <div style="max-height:180px;overflow-y:auto;font-size:0.85rem;background:#fef3cd;padding:10px;border-radius:8px;">
                                <div v-for="(err, i) in importResult.errors" :key="i" style="margin-bottom:4px;">⚠️ {{ err }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="import-footer">
                        <button class="btn-primary" @click="showImportResult = false">Đóng</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ===== Export Excel Modal ===== -->
        <Teleport to="body">
            <div class="import-backdrop" v-if="showExportModal" @click.self="closeExportModal">
                <div class="import-modal animate-in" style="max-width: 540px;">
                    <div class="import-header">
                        <h2>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#167a70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Xuất Dữ Liệu Sản Phẩm Excel
                        </h2>
                        <button class="import-close" @click="closeExportModal">×</button>
                    </div>

                    <div class="import-body">
                        <!-- Chọn khoảng thời gian -->
                        <div class="export-group">
                            <label class="export-label">Khoảng thời gian tạo sản phẩm</label>
                            <div class="export-presets">
                                <button type="button" class="preset-btn" :class="{ active: exportDatePreset === 'today' }" @click="exportDatePreset = 'today'">Hôm nay</button>
                                <button type="button" class="preset-btn" :class="{ active: exportDatePreset === 'this_week' }" @click="exportDatePreset = 'this_week'">Tuần này</button>
                                <button type="button" class="preset-btn" :class="{ active: exportDatePreset === 'this_month' }" @click="exportDatePreset = 'this_month'">Tháng này</button>
                                <button type="button" class="preset-btn" :class="{ active: exportDatePreset === 'custom' }" @click="exportDatePreset = 'custom'">Tùy chọn</button>
                            </div>
                            <div v-if="exportDatePreset === 'custom'" class="export-custom-dates">
                                <div class="date-input-group">
                                    <span>Từ ngày:</span>
                                    <input type="date" v-model="exportFromDate" class="export-date-input" />
                                </div>
                                <div class="date-input-group">
                                    <span>Đến ngày:</span>
                                    <input type="date" v-model="exportToDate" class="export-date-input" />
                                </div>
                            </div>
                        </div>

                        <!-- Chế độ xuất -->
                        <div class="export-group">
                            <label class="export-label">Chế độ xuất dữ liệu</label>
                            <div class="export-radio-group">
                                <label class="export-radio-item">
                                    <input type="radio" v-model="exportType" value="variant" />
                                    <span>Chi tiết từng biến thể (Mã SKU, Màu, Size, Giá, Tồn kho)</span>
                                </label>
                                <label class="export-radio-item">
                                    <input type="radio" v-model="exportType" value="summary" />
                                    <span>Tổng quan sản phẩm (ID, Tên SP, Loại, Giá min/max, Tổng kho)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Bộ lọc khác (Trạng thái & Danh mục) -->
                        <div class="export-group-row">
                            <div class="export-group" style="flex: 1;">
                                <label class="export-label">Trạng thái</label>
                                <select v-model="exportStatus" class="export-select">
                                    <option value="all">Tất cả trạng thái</option>
                                    <option value="active">Đang bán</option>
                                    <option value="draft">Bản nháp</option>
                                    <option value="inactive">Tạm ẩn</option>
                                    <option value="out_of_stock">Hết hàng</option>
                                    <option value="deleted">Đã xóa</option>
                                </select>
                            </div>
                            <div class="export-group" style="flex: 1;">
                                <label class="export-label">Danh mục</label>
                                <select v-model="exportCategoryId" class="export-select">
                                    <option value="">Tất cả danh mục</option>
                                    <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="import-footer">
                        <button class="btn-outline" @click="closeExportModal">Hủy</button>
                        <button class="btn-primary" :disabled="isExporting" @click="handleExportExcel">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            {{ isExporting ? 'Đang xuất Excel...' : 'Tải File Excel' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.products-page { font-family: var(--font-inter); }

/* Restore Button */
.btn-icon.restore { color: #16a34a; }
.btn-icon.restore:hover { background: rgba(22, 163, 74, 0.15); color: #15803d; border-color: rgba(22, 163, 74, 0.3); }

/* Header buttons group */
.header-btns { display: flex; gap: 10px; align-items: center; }

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500;}

/* Buttons */
.btn-primary {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 18px; border-radius: 8px; border: none;
    background: var(--primary); color: white;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; text-decoration: none;
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover {
    background: var(--primary-dark); color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(230, 59, 111, 0.28);
}
.btn-outline {
    padding: 8px 18px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--card-bg); color: var(--text-muted);
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; text-decoration: none;
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }

/* Filters & Search */
.filters-bar {
    display: flex; flex-direction: column;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03); border-radius: 16px;
    overflow: hidden;
}

/* Premium Status Tabs (Pill Toggles) */
.status-tabs-container {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border-color);
    background: white;
}
.status-pills {
    display: inline-flex; gap: 8px; padding: 6px;
    background: #f1f5f9; border-radius: 12px;
}
.status-pill {
    background: transparent !important; border: none !important; outline: none !important;
    padding: 6px 14px !important; margin: 0 !important;
    font-size: 0.85rem !important; font-weight: 600 !important; color: #64748b !important;
    border-radius: 8px !important; cursor: pointer !important; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    line-height: 1.5 !important; font-family: var(--font-inter) !important;
}
.status-pill:hover { color: var(--text-main) !important; }
.status-pill.active {
    background: white !important; color: var(--primary) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important;
}
.status-pill.danger.active { color: #dc3545 !important; }

.filters-inner {
    background: white;
}
.search-box { position: relative; max-width: 380px; width: 100%; }
.search-box svg {
    position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
    color: #94a3b8;
}
.search-input {
    width: 100%; padding: 10px 14px 10px 40px; border-radius: 30px;
    border: 1px solid var(--border-color); background: #f8fafc;
    font-family: inherit; font-size: 0.85rem; transition: all 0.25s;
    color: var(--text-main);
}
.search-input:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }

/* Override BaseSelect inside advanced filters for alignment */
.advanced-filters :deep(.base-select) {
    margin-bottom: 0 !important;
}
.advanced-filters :deep(.base-select__field) {
    padding: 10px 30px 10px 14px !important;
    font-size: 0.85rem !important;
    border-radius: 8px !important;
    height: 40.5px !important; /* Force exact height to match search input */
    line-height: 1.5 !important;
}

.btn-clear-filters {
    background: none; border: none; padding: 4px 8px; color: var(--primary);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px;
    border-radius: 6px; transition: background 0.2s;
}
.btn-clear-filters:hover { background: rgba(230, 59, 111, 0.1); }

/* Table */
.table-header {
    padding: 20px 24px; border-bottom: 1px solid var(--border-color);
}
.table-count { font-size: 0.95rem; color: var(--text-light); }
.table-count strong { color: var(--text-main); font-weight: 800; font-size: 1.1rem; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    padding: 16px 24px; text-align: left; font-size: 0.75rem; font-weight: 700;
    color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;
    background: #f8fafc; border-bottom: 1px solid var(--border-color);
}
.data-table td {
    padding: 18px 24px; border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s; vertical-align: middle;
}
.data-table tbody tr { transition: background 0.2s; }
.data-table tbody tr:hover { background: #f8fafc; }
.data-table tbody tr.row-selected { background: rgba(230, 59, 111, 0.04); }

/* Bulk Actions Bar */
.bulk-actions-bar {
    position: absolute; top: 0; left: 0; width: 100%; height: 60px;
    background: white; z-index: 10; border-bottom: 1px solid var(--border-color);
    border-radius: 16px 16px 0 0; display: flex; align-items: center; justify-content: space-between;
    padding: 0 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.bulk-info { font-size: 0.95rem; font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 8px;}
.bulk-count { background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.85rem;}
.bulk-buttons { display: flex; gap: 10px; }
.btn-bulk {
    display: flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px;
    font-size: 0.85rem; font-weight: 700; cursor: pointer; border: 1px solid transparent; transition: 0.2s;
}
.btn-bulk-success { background: rgba(38, 166, 154, 0.1); color: #167a70; }
.btn-bulk-success:hover { background: #167a70; color: white; }
.btn-bulk-warning { background: rgba(255, 167, 38, 0.1); color: #e65100; }
.btn-bulk-warning:hover { background: #e65100; color: white; }
.btn-bulk-danger { background: rgba(239, 83, 80, 0.1); color: #c62828; }
.btn-bulk-danger:hover { background: #c62828; color: white; }

/* Custom Checkbox */
.custom-checkbox {
    width: 16px; height: 16px; cursor: pointer;
    accent-color: var(--primary); outline: none; border: none;
    margin: 0; padding: 0; vertical-align: middle;
}

/* Badges */
.badge-id {
    padding: 6px 10px; border-radius: 8px; font-size: 0.85rem;
    font-weight: 700; background: #f1f5f9; color: #475569;
}
.prod-cell { display: flex; flex-direction: column; gap: 4px; justify-content: center; height: 100%;}
.prod-thumb {
    width: 48px; height: 100%; border-radius: 8px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; color: var(--text-light);
}
.prod-thumb img { width: 100%; height: 100%; object-fit: cover; }
.prod-name { 
    font-size: 0.9rem; font-weight: 700; color: var(--text-main); 
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.prod-slug { font-size: 0.75rem; color: var(--text-light); }
.val-price { font-size: 0.85rem; font-weight: 800; color: var(--seafoam); }

.badge-type {
    padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;
}
.badge-type.simple { background: rgba(156, 39, 176, 0.1); color: #7b1fa2; }
.badge-type.variant { background: rgba(3, 169, 244, 0.1); color: var(--primary); }

.badge-stock {
    padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;
}
.good { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.low { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.out { background: rgba(239, 83, 80, 0.15); color: #c62828; }

.badge-status {
    padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;
    display: inline-block;
}
.badge-status.active { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.badge-status.draft { background: rgba(158, 158, 158, 0.15); color: #616161; }
.badge-status.inactive { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.badge-status.out_of_stock { background: rgba(239, 83, 80, 0.15); color: #c62828; }
.badge-status.deleted { background: rgba(239, 83, 80, 0.15); color: #c62828; }

/* Actions */
.actions-cell { display: flex; gap: 8px; }
.btn-icon {
    width: 36px; height: 36px; min-height: unset; aspect-ratio: 1 / 1; border-radius: 10px; border: none !important; outline: none !important;
    background: transparent !important; color: #94a3b8 !important;
    cursor: pointer !important; display: flex; align-items: center; justify-content: center;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important; text-decoration: none;
}
.btn-icon:hover { transform: translateY(-2px); }
.edit:hover { color: var(--primary) !important; background: rgba(230, 59, 111, 0.1) !important; }
.del:hover { color: #dc3545 !important; background: rgba(220, 53, 69, 0.1) !important; }
.view:hover { color: #8e24aa !important; background: rgba(142, 36, 170, 0.1) !important; }
.btn-icon[title="In Tem Giá"]:hover { color: #f59e0b !important; background: rgba(245, 158, 11, 0.1) !important; }

/* ===== Quick View Modal ===== */
.qv-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.55); display: flex; align-items: center; justify-content: center;
    z-index: 1000; backdrop-filter: blur(2px);
}
.qv-modal {
    background: var(--card-bg); border-radius: 16px; width: 94%; max-width: 900px;
    max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.qv-header {
    padding: 18px 24px; border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
    position: sticky; top: 0; background: var(--card-bg); z-index: 10; border-radius: 16px 16px 0 0;
}
.qv-header h2 {
    font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);
    display: flex; align-items: center; gap: 10px;
}
.qv-close {
    background: none; border: none; font-size: 1.6rem; line-height: 1;
    color: var(--text-muted); cursor: pointer; transition: 0.2s; padding: 0; width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center; border-radius: 8px;
}
.qv-close:hover { color: var(--coral); background: rgba(239,83,80,0.08); }
.qv-loading { padding: 60px 20px; text-align: center; color: var(--text-muted); }
.qv-body { padding: 24px; }

/* Top Layout: Gallery + Info */
.qv-top { display: flex; gap: 28px; margin-bottom: 24px; }
.qv-gallery { flex: 0 0 300px; display: flex; flex-direction: column; gap: 10px; }
.qv-main-img {
    width: 100%; aspect-ratio: 1; border-radius: 12px; overflow: hidden;
    border: 1px solid var(--border-color); background: var(--surface-container);
    display: flex; align-items: center; justify-content: center;
}
.qv-main-img img { width: 100%; height: 100%; object-fit: contain; }
.qv-no-img { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #cbd5e1; font-size: 0.85rem; }
.qv-thumbs { display: flex; gap: 8px; flex-wrap: wrap; }
.qv-thumb-item {
    width: 52px; height: 52px; border-radius: 8px; overflow: hidden; cursor: pointer;
    border: 2px solid transparent; transition: border-color 0.2s;
}
.qv-thumb-item:hover { border-color: var(--primary); }
.qv-thumb-item.active { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(230, 59, 111,0.2); }
.qv-thumb-item img { width: 100%; height: 100%; object-fit: cover; }

/* Product Info */
.qv-info { flex: 1; display: flex; flex-direction: column; gap: 12px; }
.qv-name { font-size: 1.35rem; font-weight: 800; color: var(--text-main); line-height: 1.35; margin: 0; }
.qv-slug { font-size: 0.8rem; color: var(--text-light); margin: -4px 0 0 0; }
.qv-price-block { padding: 12px 16px; background: rgba(38,166,154,0.06); border-radius: 10px; }
.qv-price { font-size: 1.35rem; font-weight: 800; color: var(--seafoam); }
.qv-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.qv-meta-item {
    display: flex; flex-direction: column; gap: 3px;
    padding: 8px 12px; background: var(--ocean-deepest, #f8fafc); border-radius: 8px;
}
.qv-meta-label { font-size: 0.7rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }
.qv-meta-value { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }
.qv-featured-badge { font-size: 0.82rem; font-weight: 600; color: #e65100; }
.qv-desc-section h4 { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.5px; }
.qv-desc-content { font-size: 0.88rem; color: var(--text-main); line-height: 1.6; padding: 10px 14px; background: var(--ocean-deepest); border-radius: 8px; }

/* Variants Table */
.qv-section { margin-bottom: 20px; }
.qv-section-title {
    font-size: 0.9rem; font-weight: 800; color: var(--text-main);
    padding-bottom: 10px; border-bottom: 1px solid var(--border-color); margin-bottom: 12px;
}
.qv-variants-table-wrap { overflow-x: auto; }
.qv-variants-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
.qv-variants-table th {
    padding: 10px 12px; text-align: left; font-size: 0.7rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest, #f8fafc);
}
.qv-variants-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.qv-variants-table tbody tr:hover td { background: rgba(230, 59, 111,0.03); }
.qv-variants-table code { font-size: 0.78rem; background: var(--surface-container); padding: 2px 6px; border-radius: 4px; }
.qv-v-price { font-weight: 700; color: var(--seafoam); }
.qv-variant-thumb {
    width: 36px; height: 36px; border-radius: 6px; overflow: hidden;
    border: 1px solid var(--border-color); cursor: pointer; transition: border-color 0.2s;
}
.qv-variant-thumb:hover { border-color: var(--primary); }
.qv-variant-thumb img { width: 100%; height: 100%; object-fit: cover; }
.qv-variant-thumb.empty {
    display: flex; align-items: center; justify-content: center;
    color: var(--text-light); font-size: 0.75rem; background: var(--surface-container); cursor: default;
}

.qv-desc-full {
    font-size: 0.88rem; color: var(--text-main); line-height: 1.7;
    padding: 14px 18px; background: var(--ocean-deepest); border-radius: 10px;
    max-height: 200px; overflow-y: auto;
}

/* Footer */
.qv-footer {
    display: flex; gap: 12px; justify-content: flex-end;
    padding-top: 16px; border-top: 1px solid var(--border-color);
}

/* Pagination */
.pagination {
    display: flex; justify-content: center; gap: 6px; padding: 20px;
    border-top: 1px solid var(--border-color);
}
.page-btn {
    width: 36px; height: 36px; border-radius: 8px;
    border: 1px solid var(--border-color); background: var(--card-bg);
    color: var(--text-muted); font-weight: 700; font-size: 0.85rem;
    cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.page-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
.page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-dots { display: flex; align-items: center; justify-content: center; padding: 0 4px; color: var(--text-muted); font-weight: 700; }

/* Empty */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.empty-state p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500;}

/* ===== Import Excel Button ===== */
.btn-import {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 18px; border-radius: 8px; border: 1.5px solid #2e7d32;
    background: rgba(46, 125, 50, 0.08); color: #2e7d32;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
}
.btn-import:hover {
    background: #2e7d32; color: white;
    box-shadow: 0 4px 12px rgba(46, 125, 50, 0.25); transform: translateY(-2px);
}

/* ===== Import Excel Modal ===== */
.import-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.55); display: flex; align-items: center; justify-content: center;
    z-index: 1000; backdrop-filter: blur(2px);
}
.import-modal {
    background: var(--card-bg); border-radius: 16px; width: 94%; max-width: 560px;
    display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.import-header {
    padding: 18px 24px; border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
}
.import-header h2 {
    font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);
    display: flex; align-items: center; gap: 10px;
}
.import-close {
    background: none; border: none; font-size: 1.6rem; line-height: 1;
    color: var(--text-muted); cursor: pointer; transition: 0.2s; padding: 0; width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center; border-radius: 8px;
}
.import-close:hover { color: var(--coral); background: rgba(239,83,80,0.08); }

.import-body { padding: 24px; display: flex; flex-direction: column; gap: 18px; }

.import-guide {
    background: var(--ocean-deepest, #f0f7fa); padding: 16px 18px; border-radius: 10px;
    border: 1px solid rgba(230, 59, 111, 0.15);
}
.import-guide h4 { font-size: 0.95rem; font-weight: 700; margin: 0 0 8px 0; color: var(--text-main); }
.import-guide ol {
    margin: 0; padding-left: 20px; font-size: 0.85rem; color: var(--text-muted);
    line-height: 1.8;
}
.import-guide ol strong { color: var(--primary); }
.import-cols-info {
    display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px;
}
.col-tag {
    padding: 3px 10px; border-radius: 5px; font-size: 0.72rem; font-weight: 700;
    background: rgba(158, 158, 158, 0.12); color: var(--text-muted);
}
.col-tag.required { background: rgba(230, 59, 111, 0.1); color: var(--primary); }

.btn-download-template {
    display: flex; align-items: center; gap: 8px; justify-content: center;
    padding: 10px 20px; border-radius: 8px; border: 1.5px dashed #2e7d32;
    background: rgba(46, 125, 50, 0.04); color: #2e7d32;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; width: 100%;
}
.btn-download-template:hover {
    background: rgba(46, 125, 50, 0.1); border-style: solid;
}

.import-dropzone {
    position: relative; border: 2px dashed var(--border-color); border-radius: 12px;
    padding: 30px 20px; text-align: center; cursor: pointer; transition: all 0.25s;
    background: var(--ocean-deepest, #fafcfe);
}
.import-dropzone:hover { border-color: var(--primary); background: rgba(230, 59, 111, 0.03); }
.import-dropzone.has-file { border-color: #26a69a; border-style: solid; background: rgba(38, 166, 154, 0.04); }
.import-file-input {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    opacity: 0; cursor: pointer;
}
.dropzone-placeholder {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    color: var(--text-light);
}
.dropzone-placeholder span { font-size: 0.9rem; font-weight: 600; }
.dropzone-placeholder small { font-size: 0.78rem; color: var(--text-light); }
.dropzone-selected {
    display: flex; align-items: center; gap: 10px; justify-content: center;
}
.dropzone-selected span { font-size: 0.9rem; font-weight: 700; color: #167a70; }

.import-footer {
    padding: 16px 24px; border-top: 1px solid var(--border-color);
    display: flex; gap: 12px; justify-content: flex-end;
}
.import-footer .btn-primary:disabled {
    opacity: 0.5; cursor: not-allowed; transform: none;
    box-shadow: none;
}

/* Animation */
.animate-in { animation: fadeSlideUp 0.35s ease both; }
@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

/* Responsive */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    .header-btns { flex-direction: row; flex-wrap: wrap; width: 100%; gap: 8px; }
    .header-btns .btn-import, .header-btns .btn-primary { flex: 1 1 auto; justify-content: center; }
    .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
    .search-box { max-width: 100%; }
    .qv-top { flex-direction: column; }
    .qv-gallery { flex: none; max-width: 300px; margin: 0 auto; }
    .qv-meta { grid-template-columns: 1fr; }
    .qv-footer { flex-direction: column; }
    .import-modal { width: 96%; }
}

.highlight-edited {
    background-color: rgba(38, 166, 154, 0.15) !important;
    transition: background-color 2s ease-out;
}

.badge-type, .badge-status, .badge-stock, .val-price {
    white-space: nowrap;
}

.data-table th, .data-table td {
    padding: 12px 16px;
    vertical-align: middle;
}

/* ===== Premium Skeleton Loader ===== */
.skeleton-box, .skeleton-text {
    background: #e2e8f0;
    position: relative;
    overflow: hidden;
}
.skeleton-text { border-radius: 4px; }
.skeleton-box::after, .skeleton-text::after {
    content: ''; position: absolute;
    top: 0; right: 0; bottom: 0; left: 0;
    transform: translateX(-100%);
    background-image: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0,
        rgba(255, 255, 255, 0.4) 20%,
        rgba(255, 255, 255, 0.7) 60%,
        rgba(255, 255, 255, 0)
    );
    animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 100% { transform: translateX(100%); } }

.table-loading {
    opacity: 0.6;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

/* ===== Export Modal Styles ===== */
.export-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
.export-group-row { display: flex; gap: 16px; margin-bottom: 8px; }
.export-label { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
.export-presets { display: flex; gap: 8px; flex-wrap: wrap; }
.preset-btn {
    padding: 6px 14px; border-radius: 8px; border: 1px solid var(--border-color);
    background: #f8fafc; color: var(--text-muted); font-size: 0.82rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.preset-btn:hover { border-color: var(--primary); color: var(--primary); }
.preset-btn.active { background: #167a70; color: white; border-color: #167a70; }
.export-custom-dates { display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap; }
.date-input-group { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--text-muted); }
.export-date-input {
    padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color);
    font-size: 0.82rem; color: var(--text-main); outline: none;
}
.export-radio-group { display: flex; flex-direction: column; gap: 8px; background: #f8fafc; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--border-color); }
.export-radio-item { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; cursor: pointer; color: var(--text-main); }
.export-radio-item input[type="radio"] { accent-color: #167a70; width: 16px; height: 16px; cursor: pointer; }
.export-select {
    width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color);
    background: white; font-size: 0.85rem; color: var(--text-main); outline: none;
}
</style>
