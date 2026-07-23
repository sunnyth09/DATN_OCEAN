<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { Toast } from 'bootstrap';
import Swal from 'sweetalert2';
import AppIcon from '@/icons/AppIcon.vue';
import { getApiBaseUrl, getAppBaseUrl } from '@/utils/url';
import QRCode from 'qrcode';
import { sanitizeHtml } from '@/utils/sanitize';

const toastData = ref({ message: '', type: 'success' });
const showToastMsg = (message, type = 'success') => {
  toastData.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('productToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};

const route = useRoute();
const router = useRouter();

const products = ref([]);
const isLoading = ref(true);
const searchQuery = ref(route.query.search || '');
const statusFilter = ref(route.query.status || '');
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

const fetchProducts = async () => {
    isLoading.value = true;
    try {
        const params = new URLSearchParams({
            page: currentPage.value,
            limit: limit,
        });
        if (searchQuery.value) params.append('search', searchQuery.value);
        if (statusFilter.value) params.append('status', statusFilter.value);

        const response = await api.get(`/products?${params.toString()}`);
        products.value = response.data.data || response.data;
        totalProducts.value = response.data.total || products.value.length;
    } catch (error) {
        console.error('Error fetching products:', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(async () => {
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

const updateRouteAndFetch = () => {
    const query = { ...route.query };
    
    if (currentPage.value > 1) query.page = currentPage.value;
    else delete query.page;
    
    if (searchQuery.value) query.search = searchQuery.value;
    else delete query.search;
    
    if (statusFilter.value) query.status = statusFilter.value;
    else delete query.status;
    
    router.replace({ query }).catch(() => {});
    fetchProducts();
};

const handleSearch = () => {
    currentPage.value = 1;
    updateRouteAndFetch();
};

const handleFilterStatus = (status) => {
    statusFilter.value = statusFilter.value === status ? '' : status;
    currentPage.value = 1;

    if (status === 'deleted') {
        statusFilter.value = 'deleted';
    }
    updateRouteAndFetch();
};

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
        fetchProducts();
    } catch (error) {
        console.error('Error deleting product:', error);
        showToastMsg('Không thể xóa sản phẩm.', 'danger');
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

const openQuickView = async (slug) => {
    isLoadingQuickView.value = true;
    showQuickViewModal.value = true;
    try {
        const response = await api.get(`/products/${slug}`);
        const productData = response.data.data || response.data;
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
                <button class="btn-import" id="import-excel-btn" @click="openImportModal">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Nhập từ Excel
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
        <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input 
                    type="text" 
                    v-model="searchQuery"
                    @keyup.enter="handleSearch"
                    placeholder="Tìm kiếm sản phẩm theo tên..." 
                    class="search-input"
                />
            </div>
            <div class="filter-actions">
                <button class="filter-btn" :class="{ active: !statusFilter }" @click="handleFilterStatus('')">Tất cả</button>
                <button class="filter-btn" :class="{ active: statusFilter === 'active' }" @click="handleFilterStatus('active')">Đang bán</button>
                <button class="filter-btn" :class="{ active: statusFilter === 'draft' }" @click="handleFilterStatus('draft')">Nháp</button>
                <button class="filter-btn" :class="{ active: statusFilter === 'inactive' }" @click="handleFilterStatus('inactive')">Tạm ẩn</button>
                <button class="filter-btn" :class="{ active: statusFilter === 'out_of_stock' }" @click="handleFilterStatus('out_of_stock')">Hết hàng</button>
                <button class="filter-btn btn-danger" :class="{ active: statusFilter === 'deleted' }" @click="handleFilterStatus('deleted')">Đã xóa</button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="loading-state">
            <div class="spinner"></div>
            <p>Đang tải sản phẩm...</p>
        </div>

        <!-- Products Table -->
        <div v-else class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ totalProducts }}</strong> sản phẩm
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
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
                        <tr v-for="p in products" :key="p.product_id" :id="`product-${p.product_id}`">
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
                                    <div>
                                        <router-link :to="`/product/${p.slug}`" class="prod-name text-decoration-none">{{ p.name }}</router-link> <br class="m-0">
                                        <span class="prod-slug">{{ p.slug }}</span> <br class="m-0">
                                        <span class="prod-slug">Ngày tạo: {{ formatDate(p.created_at) }}</span>

                                    </div>
                                </div>
                            </td>
                            <td><span class="badge-type" :class="p.product_type">{{ getTypeLabel(p.product_type) }}</span></td>
                            <td>
                                <span class="val-price" v-if="p.min_price === p.max_price">{{ formatPrice(p.min_price || p.lowest_price_variant?.price) }}</span>
                                <span class="val-price" v-else>{{ formatPrice(p.min_price) }} - {{ formatPrice(p.max_price) }}</span>
                            </td>
                            <td>
                                <span class="badge-stock" :class="{ 
                                    'good': (p.variants_sum_stock || 0) > 20, 
                                    'low': (p.variants_sum_stock || 0) <= 20 && (p.variants_sum_stock || 0) > 0, 
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

                    <!-- Loading -->
                    <div v-if="isLoadingQuickView" class="qv-loading">
                        <div class="spinner"></div>
                        <p>Đang tải...</p>
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
                            <h4><AppIcon iconName="import" size="16" /> Hướng dẫn</h4>
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

        <!-- Bootstrap Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
            <div class="toast align-items-center border-0" :class="toastData.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="productToast" role="alert">
                <div class="d-flex">
                    <div class="toast-body">{{ toastData.message }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
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
    padding: 10px 22px; border-radius: 8px; border: none;
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
    padding: 10px 22px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--card-bg); color: var(--text-muted);
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; text-decoration: none;
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }

/* Filters */
.filters-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
}
.search-box {
    display: flex; align-items: center; gap: 10px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    border-radius: 8px; padding: 10px 16px; flex: 1; max-width: 350px;
    transition: all 0.2s;
}
.search-box:focus-within {
    border-color: var(--primary); background: var(--card-bg);
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}
.search-box svg { color: var(--text-light); }
.search-input {
    background: none; border: none; outline: none;
    color: var(--text-main); font-family: var(--font-inter);
    font-size: 0.9rem; width: 100%;
}
.search-input::placeholder { color: var(--text-light); }
.filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.filter-btn {
    padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    font-family: var(--font-inter); font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; gap: 6px;
}
.filter-btn:hover { border-color: var(--primary); color: var(--primary); }
.filter-btn.active {
    background: rgba(230, 59, 111, 0.1); border-color: rgba(230, 59, 111, 0.3);
    color: var(--primary);
}

/* Loading */
.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); font-weight: 600;}
.spinner {
    width: 30px; height: 30px; border: 3px solid var(--border-color);
    border-top-color: var(--primary); border-radius: 50%;
    animation: spin 1s linear infinite; margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Table */
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); }
.table-count { font-size: 0.85rem; color: var(--text-muted); font-weight: 500;}
.table-count strong { color: var(--text-main); font-weight: 800;}
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 14px 24px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color);
    background: var(--ocean-deepest);
}
.data-table td {
    padding: 16px 24px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s; vertical-align: middle;
}
.data-table tbody tr:hover td { background: var(--hover-bg); }

/* Badges */
.badge-id {
    padding: 4px 8px; border-radius: 6px; font-size: 0.8rem;
    font-weight: 700; background: rgba(230, 59, 111, 0.1); color: var(--primary);
}
.prod-cell { display: flex; flex-direction: column; gap: 2px; }
.prod-thumb {
    width: 48px; height: 100%; border-radius: 8px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; color: var(--text-light);
}
.prod-thumb img { width: 100%; height: 100%; object-fit: cover; }
.prod-name { font-size: 0.9rem; font-weight: 700; color: var(--text-main); }
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
.actions-cell { display: flex; gap: 6px; }
.btn-icon {
    width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; text-decoration: none;
}
.btn-icon:hover { border-color: currentColor; background: var(--card-bg);}
.edit:hover { color: var(--seafoam); border-color: var(--seafoam); background: rgba(38, 166, 154, 0.05); }
.del:hover { color: var(--coral); border-color: var(--coral); background: rgba(239, 83, 80, 0.05); }
.view:hover { color: #8e24aa; border-color: #8e24aa; background: rgba(142, 36, 170, 0.05); }

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
    padding: 10px 20px; border-radius: 8px; border: 1.5px solid #2e7d32;
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
    .header-btns { flex-direction: column; width: 100%; }
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
</style>
