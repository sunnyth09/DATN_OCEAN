<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/axios.js';
import { getStorageUrl } from '@/utils/url';
import Swal from 'sweetalert2';
import { Toast, Modal } from 'bootstrap';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import AppIcon from '@/components/AppIcon.vue';

const route = useRoute();

// -- States --
const flashSales = ref([]);
const isModalOpen = ref(false);
const isEditing = ref(false);
const errors = ref({});
const isLoading = ref(false);

const toastObj = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
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

const STATUS_LABELS = {
    'draft': { text: 'Nháp', class: 'bg-secondary' },
    'active': { text: 'Đang chạy', class: 'bg-success' },
    'ended': { text: 'Đã kết thúc', class: 'bg-danger' },
};

// Form data
const defaultForm = () => ({
    id: null,
    name: '',
    start_time: '',
    end_time: '',
    status: 'draft',
    items: [],
});
const form = ref(defaultForm());

// Product Search Feature
const productSearchTerm = ref('');
const searchedProducts = ref([]);
let searchTimeout = null;

// Bulk Product Picker Modal State
const isPickerOpen = ref(false);
const pickerLoading = ref(false);
const pickerSearch = ref('');
const pickerCategory = ref('all');
const pickerCategories = ref([]);
const pickerProducts = ref([]);
const selectedPickerProductIds = ref([]);
const defaultPresetDiscount = ref(20);
const defaultPresetStock = ref(10);
let pickerSearchTimeout = null;

const loadPickerCategories = async () => {
    if (pickerCategories.value.length > 0) return;
    try {
        const res = await api.get('/categories');
        pickerCategories.value = res.data?.data || [];
    } catch (e) {
        pickerCategories.value = [];
    }
};

const fetchPickerProducts = async () => {
    pickerLoading.value = true;
    try {
        const params = {
            query: pickerSearch.value,
            category_id: pickerCategory.value !== 'all' ? pickerCategory.value : undefined,
            limit: 80,
        };
        const res = await api.get('/admin/flash-sale/search-products', { params });
        pickerProducts.value = Array.isArray(res.data.data) ? res.data.data : Object.values(res.data.data || {});
    } catch (e) {
        pickerProducts.value = [];
    } finally {
        pickerLoading.value = false;
    }
};

const onPickerSearchInput = () => {
    clearTimeout(pickerSearchTimeout);
    pickerSearchTimeout = setTimeout(() => {
        fetchPickerProducts();
    }, 300);
};

const openPickerModal = async () => {
    isPickerOpen.value = true;
    selectedPickerProductIds.value = [];
    pickerSearch.value = '';
    pickerCategory.value = 'all';
    await Promise.all([loadPickerCategories(), fetchPickerProducts()]);
};

const isProductAlreadyInCampaign = (prodId) => {
    return form.value.items.some(item => item.product_id === prodId);
};

const toggleSelectPickerProduct = (prodId) => {
    if (isProductAlreadyInCampaign(prodId)) return;
    const idx = selectedPickerProductIds.value.indexOf(prodId);
    if (idx > -1) {
        selectedPickerProductIds.value.splice(idx, 1);
    } else {
        selectedPickerProductIds.value.push(prodId);
    }
};

const isAllSelectableChecked = computed(() => {
    const selectable = pickerProducts.value.filter(p => !isProductAlreadyInCampaign(p.product_id));
    if (selectable.length === 0) return false;
    return selectable.every(p => selectedPickerProductIds.value.includes(p.product_id));
});

const toggleSelectAllPicker = () => {
    const selectable = pickerProducts.value.filter(p => !isProductAlreadyInCampaign(p.product_id));
    if (isAllSelectableChecked.value) {
        // Deselect all selectable on this page
        const selectableIds = selectable.map(p => p.product_id);
        selectedPickerProductIds.value = selectedPickerProductIds.value.filter(id => !selectableIds.includes(id));
    } else {
        // Select all selectable on this page
        selectable.forEach(p => {
            if (!selectedPickerProductIds.value.includes(p.product_id)) {
                selectedPickerProductIds.value.push(p.product_id);
            }
        });
    }
};

const confirmBulkAdd = () => {
    if (selectedPickerProductIds.value.length === 0) {
        showToast('Vui lòng chọn ít nhất 1 sản phẩm!', 'warning');
        return;
    }

    const discount = Math.min(100, Math.max(0, Number(defaultPresetDiscount.value) || 20));
    const stock = Math.max(1, Number(defaultPresetStock.value) || 10);
    let addedCount = 0;

    selectedPickerProductIds.value.forEach(prodId => {
        const prod = pickerProducts.value.find(p => p.product_id === prodId);
        if (prod && !isProductAlreadyInCampaign(prodId)) {
            const basePrice = Number(prod.base_price) || 0;
            const campaignPrice = Math.round(basePrice * (1 - discount / 100));
            form.value.items.push({
                product_id: prod.product_id,
                product: prod,
                campaign_price: campaignPrice,
                discount_percent: discount,
                campaign_stock: Math.min(stock, Math.max(1, prod.stock || stock)),
                sold: 0
            });
            addedCount++;
        }
    });

    isPickerOpen.value = false;
    showToast(`Đã thêm ${addedCount} sản phẩm vào Flash Sale!`, 'success');
};

const searchProducts = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        if (productSearchTerm.value.length < 2) {
            searchedProducts.value = [];
            return;
        }
        try {
            // Sử dụng endpoint search dành riêng cho FlashSale
            const res = await api.get('/admin/flash-sale/search-products', { params: { query: productSearchTerm.value } });
            // Đảm bảo ép kiểu về Array để .length luôn lấy được, bất kể API trả về object hay mảng
            searchedProducts.value = Array.isArray(res.data.data) ? res.data.data : Object.values(res.data.data || {});
        } catch (e) {
            console.error('Lỗi tìm sản phẩm', e);
        }
    }, 300);
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const resolveThumbnail = (url) => {
    if (!url) return '/images/no-image.png';
    if (url.startsWith('http')) return url;
    return getStorageUrl(url);
};

const addProductToItems = (product) => {
    // Tránh thêm trùng lặp
    if (form.value.items.some(item => item.product_id === product.product_id)) {
        showToast('Sản phẩm đã có trong danh sách!', 'warning');
        return;
    }
    
    form.value.items.push({
        product_id: product.product_id,
        product: product, // Giữ metadata để hiển thị trong table
        campaign_price: product.base_price,
        discount_percent: 0,
        campaign_stock: 1, // Default
        sold: 0
    });
    
    searchedProducts.value = [];
    productSearchTerm.value = '';
};

const removeItem = (index) => {
    form.value.items.splice(index, 1);
};

const onDiscountPercentChange = (item) => {
    const basePrice = item.product?.base_price || 0;
    if (basePrice > 0) {
        let dp = parseFloat(item.discount_percent) || 0;
        dp = Math.min(100, Math.max(0, dp));
        item.campaign_price = Math.max(0, basePrice - (basePrice * dp / 100));
    }
};

const onCampaignPriceChange = (item) => {
    const basePrice = item.product?.base_price || 0;
    if (basePrice > 0) {
        const cp = parseFloat(item.campaign_price) || 0;
        let dp = Math.round((1 - cp / basePrice) * 100);
        item.discount_percent = Math.max(0, dp);
    }
};

// -- CRUD Logic --
const fetchFlashSales = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/admin/flash-sale');
        flashSales.value = res.data.data;
    } finally {
        isLoading.value = false;
    }
};

const openCreate = () => {
    isEditing.value = false;
    form.value = defaultForm();
    errors.value = {};
    isModalOpen.value = true;
};

const formatDateTimeLocal = (dateString) => {
    if (!dateString) return '';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const getStatusMeta = (fs) => {
    if (fs.status === 'draft') {
        return { text: 'Bản nháp', class: 'status-draft' };
    }
    const now = new Date();
    const start = new Date(fs.start_time);
    const end = new Date(fs.end_time);
    if (now > end || fs.status === 'ended') {
        return { text: 'Đã kết thúc', class: 'status-ended' };
    }
    if (now < start) {
        return { text: 'Sắp diễn ra', class: 'status-upcoming' };
    }
    return { text: 'Đang diễn ra', class: 'status-active' };
};

const openEdit = (fs) => {
    isEditing.value = true;
    form.value = {
        id: fs.id,
        name: fs.name,
        start_time: fs.start_time_local || formatDateTimeLocal(fs.start_time),
        end_time: fs.end_time_local || formatDateTimeLocal(fs.end_time),
        status: fs.status,
        items: (fs.items || []).map(i => {
            const basePrice = i.product?.base_price || 0;
            let dp = i.discount_percent || 0;
            if (!dp && basePrice > 0) {
                dp = Math.max(0, Math.round((1 - i.campaign_price / basePrice) * 100));
            }
            return {
                product_id: i.product_id,
                product: i.product || { product_id: i.product_id, name: 'Sản phẩm #' + i.product_id, base_price: basePrice },
                campaign_price: i.campaign_price,
                discount_percent: dp,
                campaign_stock: i.campaign_stock,
                sold: i.sold || 0
            };
        })
    };
    errors.value = {};
    isModalOpen.value = true;
};

const applyBatchDiscount = (percent) => {
    form.value.items.forEach(item => {
        item.discount_percent = percent;
        onDiscountPercentChange(item);
    });
    showToast(`Đã áp dụng giảm ${percent}% cho tất cả sản phẩm!`, 'info');
};

const handleSubmit = async () => {
    errors.value = {};

    if (!form.value.name?.trim()) {
        showToast('Vui lòng nhập tên chiến dịch Flash Sale!', 'warning');
        return;
    }
    if (!form.value.start_time || !form.value.end_time) {
        showToast('Vui lòng chọn thời gian bắt đầu và kết thúc!', 'warning');
        return;
    }
    if (new Date(form.value.start_time) >= new Date(form.value.end_time)) {
        showToast('Thời gian bắt đầu phải trước thời gian kết thúc!', 'warning');
        return;
    }
    if (!form.value.items || form.value.items.length === 0) {
        showToast('Vui lòng thêm ít nhất 1 sản phẩm vào chiến dịch!', 'warning');
        return;
    }

    // Validate item prices and stock
    for (let i = 0; i < form.value.items.length; i++) {
        const item = form.value.items[i];
        if (!item.campaign_price || Number(item.campaign_price) <= 0) {
            showToast(`Giá sale của sản phẩm #${item.product_id} không hợp lệ!`, 'warning');
            return;
        }
        if (!item.campaign_stock || Number(item.campaign_stock) <= 0) {
            showToast(`Số lượng Flash Sale của sản phẩm #${item.product_id} phải lớn hơn 0!`, 'warning');
            return;
        }
    }

    try {
        if (isEditing.value) {
            await api.put(`/admin/flash-sale/${form.value.id}`, form.value);
        } else {
            await api.post('/admin/flash-sale', form.value);
        }
        showToast('Lưu chiến dịch Flash Sale thành công!', 'success');
        isModalOpen.value = false;
        fetchFlashSales();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors;
            showToast('Dữ liệu không hợp lệ. Vui lòng kiểm tra lại!', 'warning');
        } else {
            showToast(e.response?.data?.message || 'Lỗi lưu chiến dịch!', 'danger');
        }
    }
};

const handleDelete = async (id) => {
    const result = await Swal.fire({
         title: 'Xóa Flash Sale?',
         text: 'Bạn có chắc chắn muốn xóa chiến dịch Flash Sale này không?',
         icon: 'warning',
         showCancelButton: true,
         confirmButtonText: 'Đồng ý xóa',
         cancelButtonText: 'Hủy'
    });
    
    if (result.isConfirmed) {
        try {
            await api.delete(`/admin/flash-sale/${id}`);
            showToast('Đã xóa thành công!', 'success');
            fetchFlashSales();
        } catch (e) {
            showToast('Lỗi xóa chiến dịch!', 'danger');
        }
    }
};

const checkClearanceParam = () => {
    if (route.query.auto_create === '1') {
        const stored = sessionStorage.getItem('clearance_products');
        let clearanceItems = [];
        if (stored) {
            try {
                clearanceItems = JSON.parse(stored);
                sessionStorage.removeItem('clearance_products');
            } catch (e) {}
        }

        const now = new Date();
        const end = new Date();
        end.setDate(end.getDate() + 7); // 7 ngày xả kho

        const nowStr = now.toISOString().slice(0, 16);
        const endStr = end.toISOString().slice(0, 16);
        const monthYear = `${now.getMonth() + 1}/${now.getFullYear()}`;

        form.value = {
            id: null,
            name: `Xả Hàng Tồn Kho - Tháng ${monthYear}`,
            start_time: nowStr,
            end_time: endStr,
            status: 'draft',
            items: clearanceItems.map(p => {
                const basePrice = p.base_price || p.min_price || 0;
                // Đề xuất mức giảm giá xả hàng 40%
                const discountPercent = 40;
                const campaignPrice = Math.max(0, Math.round(basePrice * (1 - discountPercent / 100)));
                return {
                    product_id: p.id || p.product_id,
                    product: {
                        product_id: p.id || p.product_id,
                        name: p.name,
                        thumbnail: p.thumbnail_url || p.thumbnail,
                        base_price: basePrice,
                        stock: p.stock || 10
                    },
                    campaign_price: campaignPrice,
                    discount_percent: discountPercent,
                    campaign_stock: Math.max(1, p.stock || 5),
                    sold: 0
                };
            })
        };

        isEditing.value = false;
        errors.value = {};
        isModalOpen.value = true;
        showToast('Đã nạp danh sách sản phẩm tồn kho cần xả hàng với mức giảm đề xuất 40%!', 'success');
    }
};

onMounted(async () => {
    await fetchFlashSales();
    checkClearanceParam();
});
</script>

<template>
  <div class="admin-fs-container animate-in">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title">
          <AppIcon name="zap" size="22" style="vertical-align:middle;margin-right:6px;color:#e63b6f;" />
          Quản lý Flash Sale Campaign
        </h2>
        <p class="section-desc">Thiết lập sự kiện Flash Sale và quản lý danh sách sản phẩm giảm giá.</p>
      </div>
      <div class="tab-header-actions">
        <button class="btn-create" @click="openCreate">
          <AppIcon name="plus" size="18" />
          Tạo Campaign
        </button>
      </div>
    </div>

    <!-- LIST VIEW -->
    <AdminTableSkeleton v-if="isLoading" :columns="6" :rows="5" />
    <div v-else class="ocean-card table-wrapper">
      <table class="ws-table">
        <thead>
          <tr>
            <th>Tên Campaign</th>
            <th>Thời gian bắt đầu</th>
            <th>Thời gian kết thúc</th>
            <th class="text-center">Sản phẩm / Số lượng</th>
            <th class="status-th">Trạng thái</th>
            <th class="actions-th">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="flashSales.length === 0">
            <td colspan="6" class="empty-cell">Chưa có chiến dịch Flash Sale nào.</td>
          </tr>
          <tr v-else v-for="fs in flashSales" :key="fs.id" class="ws-row">
            <td>
              <div class="shift-info-cell">
                <div class="shift-icon" style="background:#fff3e0; color:#e65100;">
                  <AppIcon name="zap" size="16" />
                </div>
                <span class="shift-name">{{ fs.name ? fs.name.replace(/^⚡\s*/, '') : '' }}</span>
              </div>
            </td>
            <td><span class="time-badge time-start">{{ fs.start_time_formatted || new Date(fs.start_time).toLocaleString('vi-VN') }}</span></td>
            <td><span class="time-badge time-end">{{ fs.end_time_formatted || new Date(fs.end_time).toLocaleString('vi-VN') }}</span></td>
            <td class="text-center" style="color: var(--text-muted)">
                <div class="fw-bold text-dark" style="font-size: 0.9rem;">{{ fs.items?.length || 0 }} mẫu SP</div>
                <small style="font-size: 0.75rem;">(Tổng cấp: {{ fs.items?.reduce((sum, item) => sum + Number(item.campaign_stock), 0) || 0 }})</small>
            </td>
            <td class="status-cell">
              <span class="ws-status-badge" :class="getStatusMeta(fs).class">
                {{ getStatusMeta(fs).text }}
              </span>
            </td>
            <td class="actions-cell">
              <button class="btn-action edit" @click="openEdit(fs)" title="Sửa">
                <AppIcon name="edit" size="15" />
              </button>
              <button class="btn-action delete" @click="handleDelete(fs.id)" title="Xóa">
                <AppIcon name="trash" size="15" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- FORM MODAL -->
    <Teleport to="body">
    <Transition name="ws-modal">
      <div v-if="isModalOpen" class="ws-modal-overlay" @click.self="isModalOpen = false">
        <div class="ws-modal-box fs-modal-box">
          <div class="ws-modal-head">
            <h3 style="font-size: 0.98rem; font-weight: 700; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 6px;"><AppIcon name="zap" size="16" v-if="!isEditing" /><AppIcon name="edit" size="16" v-else /> {{ isEditing ? 'Chỉnh sửa Chiến Dịch Flash Sale' : 'Tạo Mới Chiến Dịch Flash Sale' }}</h3>
            <button class="ws-btn-close" @click="isModalOpen = false">
              <AppIcon name="x" size="20" />
            </button>
          </div>
          <div class="ws-modal-body" style="overflow-y: auto; padding: 16px 20px;">
            <!-- General Settings -->
            <div class="ws-form-grid mb-3">
                <div class="ws-form-group">
                    <label>Tên Campaign <span class="ws-required">*</span></label>
                    <input v-model="form.name" type="text" class="ws-form-control" placeholder="Ví dụ: Siêu Sale Giữa Tháng" />
                    <small class="text-danger mt-1 d-block">{{ errors.name?.[0] }}</small>
                </div>
                <div class="ws-form-group">
                    <label>Trạng thái</label>
                    <select v-model="form.status" class="ws-form-control ws-form-select">
                        <option value="draft">Bản nháp</option>
                        <option value="active">Đang diễn ra</option>
                        <option value="ended">Đã kết thúc</option>
                    </select>
                    <small class="text-danger mt-1 d-block">{{ errors.status?.[0] }}</small>
                </div>
                <div class="ws-form-group">
                    <label>Thời gian bắt đầu <span class="ws-required">*</span></label>
                    <input v-model="form.start_time" type="datetime-local" class="ws-form-control" />
                    <small class="text-danger mt-1 d-block">{{ errors.start_time?.[0] }}</small>
                </div>
                <div class="ws-form-group">
                    <label>Thời gian kết thúc <span class="ws-required">*</span></label>
                    <input v-model="form.end_time" type="datetime-local" class="ws-form-control" />
                    <small class="text-danger mt-1 d-block">{{ errors.end_time?.[0] }}</small>
                </div>
            </div>

            <!-- Dynamic Items Settings -->
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2 pt-2 border-top">
                <h6 class="fw-bold mb-0 d-flex align-items-center gap-2" style="font-size: 0.9rem; color: var(--text-main)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    Danh sách sản phẩm Sale ({{ form.items.length }})
                </h6>
                <div v-if="form.items.length > 0" class="d-flex align-items-center gap-1">
                    <span style="font-size: 0.75rem; color: #64748b;">Giảm nhanh:</span>
                    <button type="button" @click="applyBatchDiscount(10)" class="btn btn-sm btn-outline-secondary" style="font-size: 0.72rem; padding: 1px 7px; border-radius: 5px;">10%</button>
                    <button type="button" @click="applyBatchDiscount(20)" class="btn btn-sm btn-outline-secondary" style="font-size: 0.72rem; padding: 1px 7px; border-radius: 5px;">20%</button>
                    <button type="button" @click="applyBatchDiscount(30)" class="btn btn-sm btn-outline-secondary" style="font-size: 0.72rem; padding: 1px 7px; border-radius: 5px;">30%</button>
                    <button type="button" @click="applyBatchDiscount(50)" class="btn btn-sm btn-outline-primary" style="font-size: 0.72rem; padding: 1px 7px; border-radius: 5px;">50%</button>
                </div>
            </div>
            
            <div class="d-flex gap-2 mb-2">
                <div class="position-relative flex-grow-1" style="z-index: 1055;">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-white border-end-0" style="border-color: var(--border-color); color: #94a3b8;"><AppIcon name="search" size="14" /></span>
                      <input v-model="productSearchTerm" @input="searchProducts" type="text" 
                             class="ws-form-control form-control-sm w-100 border-start-0" placeholder="Gõ tên hoặc SKU để thêm nhanh..." style="font-size: 0.85rem;" />
                    </div>
                    
                    <ul v-if="searchedProducts && searchedProducts.length > 0" class="list-group position-absolute w-100 mt-1 shadow-lg border-0" style="z-index: 9999; max-height: 200px; overflow-y: auto; border-radius: 8px;">
                        <li v-for="prod in searchedProducts" :key="prod.product_id" 
                            class="list-group-item list-group-item-action cursor-pointer d-flex align-items-center border-bottom"
                            @click="addProductToItems(prod)" style="padding: 10px 14px;">
                            <img :src="resolveThumbnail(prod.thumbnail)" alt="" style="width: 32px; height: 32px; object-fit: cover; border-radius: 6px; margin-right: 10px; border: 1px solid #eee" />
                            <div>
                                <div class="fw-bold" style="font-size: 0.82rem; color: #102a43;">{{ prod.name }}</div>
                                <small style="font-size: 0.72rem; color: #627d98;">Giá gốc: <span class="text-decoration-line-through">{{ formatCurrency(prod.base_price) }}</span> | Kho: {{ prod.stock }}</small>
                            </div>
                        </li>
                    </ul>
                </div>
                <button type="button" @click="openPickerModal" class="btn btn-sm d-inline-flex align-items-center gap-1" style="white-space: nowrap; border-radius: 8px; font-weight: 600; font-size: 0.8rem; padding: 0 12px; border: 1.5px solid #E63B6F; color: #E63B6F; background: #fff5f8;">
                    <AppIcon name="package" size="15" />
                    Chọn Hàng Loạt
                </button>
            </div>

            <div class="table-responsive" style="max-height: 220px; overflow-y: auto; border: 1px solid var(--border-color, #d9e8f0); border-radius: 8px;">
                <table class="ws-table align-middle mb-0" style="font-size: 0.82rem; width: 100%;">
                    <thead class="position-sticky top-0" style="background: var(--ocean-deepest, #f0f7fa); z-index: 10;">
                        <tr>
                            <th class="py-2 px-3 text-uppercase" style="font-size: 0.72rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Tên sản phẩm</th>
                            <th class="py-2 px-3 text-uppercase text-center" width="110" style="font-size: 0.72rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Giảm giá (%)</th>
                            <th class="py-2 px-3 text-uppercase text-center" width="150" style="font-size: 0.72rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Giá Sale (VNĐ)</th>
                            <th class="py-2 px-3 text-uppercase text-center" width="110" style="font-size: 0.72rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">SL cấp FS</th>
                            <th class="py-2 px-3 text-uppercase text-center" width="80" style="font-size: 0.72rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Đã bán</th>
                            <th class="py-2 px-3 text-uppercase text-center" width="60" style="font-size: 0.72rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in form.items" :key="index" style="transition: background 0.15s;">
                            <td class="px-3 py-2">
                                <div class="d-flex align-items-center">
                                    <img v-if="item.product && item.product.thumbnail" :src="resolveThumbnail(item.product.thumbnail)" alt="" style="width: 28px; height: 28px; object-fit: cover; border-radius: 5px; margin-right: 10px; border: 1px solid #eee;" />
                                    <span class="fw-semibold text-truncate" style="max-width: 200px; color: var(--text-main)">{{ item.product?.name || `Product ID: ${item.product_id}` }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="input-group input-group-sm" style="border-radius: 6px; overflow: hidden;">
                                    <input v-model="item.discount_percent" @input="onDiscountPercentChange(item)" type="number" min="0" max="100" class="form-control text-center" style="border-color: #d9e8f0; background: #fff;" />
                                    <span class="input-group-text bg-light border-light" style="border-color: #d9e8f0 !important;">%</span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model="item.campaign_price" @input="onCampaignPriceChange(item)" type="number" class="form-control form-control-sm" style="border-radius: 6px; border-color: #d9e8f0;" />
                                <small class="text-danger d-block mt-1" v-if="errors[`items.${index}.campaign_price`]">{{ errors[`items.${index}.campaign_price`][0] }}</small>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model="item.campaign_stock" type="number" class="form-control form-control-sm text-center" style="border-radius: 6px; border-color: #d9e8f0;" />
                                <small class="text-danger d-block mt-1" v-if="errors[`items.${index}.campaign_stock`]">{{ errors[`items.${index}.campaign_stock`][0] }}</small>
                            </td>
                            <td class="px-3 py-2 text-center fw-bold" style="color: var(--text-muted);">{{ item.sold }}</td>
                            <td class="px-3 py-2 text-center">
                                <button class="btn btn-sm btn-outline-danger border-0 rounded-circle" @click="removeItem(index)" style="width: 24px; height: 24px; padding: 0; display: inline-flex; align-items: center; justify-content: center; background: #ffebee;" title="Xóa">
                                  <AppIcon name="x" size="13" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="form.items.length === 0">
                            <td colspan="6" class="text-center text-muted py-4">
                                <div class="mb-1"><AppIcon name="alert-circle" size="20" /></div>
                                <span style="font-size: 0.8rem;">Chưa có sản phẩm nào. Tìm kiếm hoặc bấm <strong>"Chọn Hàng Loạt"</strong> ở trên.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <small class="text-danger mt-1 d-block">{{ errors.items?.[0] }}</small>

          </div>
          <div class="ws-modal-footer px-4 py-3 border-top d-flex justify-content-end gap-2" style="background: var(--card-bg, #f8fafc); border-top: 1px solid var(--border-color);">
            <button type="button" @click="isModalOpen = false" class="ws-btn-outline" style="padding: 8px 16px; font-size: 0.85rem;">Hủy bỏ</button>
            <button type="button" @click="handleSubmit" class="ws-btn-primary" style="padding: 8px 18px; font-size: 0.85rem;">
              <AppIcon name="check" size="15" />
              Lưu Thay Đổi
            </button>
          </div>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- Bulk Product Picker Modal (Rose Pink Brand Theme) -->
    <Teleport to="body">
    <Transition name="ws-modal">
      <div v-if="isPickerOpen" class="ws-modal-overlay" style="z-index: 100000;" @click.self="isPickerOpen = false">
        <div class="ws-modal-box" style="max-width: 840px; width: 92%; max-height: 85vh;">
          <div class="ws-modal-head" style="background: linear-gradient(135deg, #E63B6F 0%, #BE123C 100%); color: #fff; padding: 12px 18px;">
            <div>
              <h3 style="color: #fff; margin: 0; font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                <AppIcon name="package" size="18" />
                Chọn Sản Phẩm Thêm Hàng Loạt Vào Flash Sale
              </h3>
              <p style="margin: 2px 0 0; font-size: 0.72rem; color: #ffe4e6;">Lựa chọn nhanh nhiều sản phẩm theo danh mục và áp dụng thiết lập giảm giá tự động</p>
            </div>
            <button class="ws-btn-close" @click="isPickerOpen = false" style="color: #fff; background: rgba(255,255,255,0.2); width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
              <AppIcon name="x" size="16" />
            </button>
          </div>

          <div class="ws-modal-body" style="overflow-y: auto; padding: 14px 18px;">
            <!-- Fast Settings Bar (Pink Theme) -->
            <div class="p-2 px-3 mb-2 rounded-3" style="background: #fff1f2; border: 1px solid #fecdd3; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
              <div class="d-flex align-items-center gap-1">
                <span style="font-size: 0.76rem; font-weight: 700; color: #be123c; display: inline-flex; align-items: center; gap: 4px;"><AppIcon name="zap" size="14" /> Thiết lập sẵn cho SP chọn:</span>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="d-flex align-items-center gap-1">
                  <span style="font-size: 0.72rem; color: #475569;">Giảm giá:</span>
                  <input v-model="defaultPresetDiscount" type="number" min="0" max="99" class="form-control form-control-sm text-center" style="width: 55px; height: 26px; font-size: 0.75rem; border-radius: 5px; border-color: #fbcfe8;" />
                  <span style="font-size: 0.72rem; font-weight: 600; color: #be123c;">%</span>
                </div>
                <div class="d-flex align-items-center gap-1">
                  <span style="font-size: 0.72rem; color: #475569;">SL Flash Sale:</span>
                  <input v-model="defaultPresetStock" type="number" min="1" class="form-control form-control-sm text-center" style="width: 55px; height: 26px; font-size: 0.75rem; border-radius: 5px; border-color: #fbcfe8;" />
                  <span style="font-size: 0.72rem; font-weight: 600; color: #be123c;">cái</span>
                </div>
              </div>
            </div>

            <!-- Filters Bar -->
            <div class="row g-2 mb-2">
              <div class="col-md-7">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0" style="border-color: #e2e8f0; color: #94a3b8;"><AppIcon name="search" size="14" /></span>
                  <input v-model="pickerSearch" @input="onPickerSearchInput" type="text" class="form-control border-start-0" placeholder="Tìm theo tên sản phẩm, mã SKU, ID..." style="border-color: #e2e8f0; font-size: 0.78rem; height: 32px;" />
                </div>
              </div>
              <div class="col-md-5">
                <select v-model="pickerCategory" @change="fetchPickerProducts" class="form-select form-select-sm" style="border-color: #e2e8f0; font-size: 0.78rem; height: 32px;">
                  <option value="all">Tất cả danh mục</option>
                  <option v-for="cat in pickerCategories" :key="cat.category_id || cat.id" :value="cat.category_id || cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Product Table -->
            <div class="table-responsive border rounded-3" style="max-height: 280px; overflow-y: auto;">
              <table class="table table-hover align-middle mb-0" style="font-size: 0.76rem;">
                <thead class="position-sticky top-0" style="z-index: 5; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                  <tr>
                    <th width="36" class="text-center py-2">
                      <input type="checkbox" :checked="isAllSelectableChecked" @change="toggleSelectAllPicker" class="form-check-input cursor-pointer" style="accent-color: #E63B6F;" />
                    </th>
                    <th class="py-2" style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Sản phẩm</th>
                    <th class="py-2" style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Danh mục</th>
                    <th class="text-end py-2" style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Giá niêm yết</th>
                    <th class="text-center py-2" style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Tồn kho</th>
                    <th width="100" class="text-center py-2" style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="pickerLoading">
                    <td colspan="6" class="text-center py-4 text-muted">
                      <div class="spinner-border spinner-border-sm text-danger me-2"></div> Đang tải danh sách sản phẩm...
                    </td>
                  </tr>
                  <tr v-else-if="pickerProducts.length === 0">
                    <td colspan="6" class="text-center py-4 text-muted" style="font-size: 0.78rem;">
                      Không tìm thấy sản phẩm nào phù hợp bộ lọc.
                    </td>
                  </tr>
                  <tr 
                    v-else 
                    v-for="prod in pickerProducts" 
                    :key="prod.product_id"
                    :class="{ 'picker-row-selected': selectedPickerProductIds.includes(prod.product_id), 'opacity-50': isProductAlreadyInCampaign(prod.product_id) }"
                    @click="toggleSelectPickerProduct(prod.product_id)"
                    style="cursor: pointer;"
                  >
                    <td class="text-center py-2" @click.stop>
                      <input 
                        type="checkbox" 
                        :checked="selectedPickerProductIds.includes(prod.product_id)" 
                        :disabled="isProductAlreadyInCampaign(prod.product_id)"
                        @change="toggleSelectPickerProduct(prod.product_id)" 
                        class="form-check-input cursor-pointer" 
                        style="accent-color: #E63B6F;"
                      />
                    </td>
                    <td class="py-2">
                      <div class="d-flex align-items-center gap-2">
                        <img :src="resolveThumbnail(prod.thumbnail)" alt="" style="width: 32px; height: 32px; object-fit: cover; border-radius: 5px; border: 1px solid #eee;" />
                        <div>
                          <div class="fw-semibold text-dark text-truncate" style="max-width: 280px; font-size: 0.76rem; line-height: 1.3;">{{ prod.name }}</div>
                          <small class="text-muted" style="font-size: 0.68rem;" v-if="prod.sku">SKU: {{ prod.sku }}</small>
                        </div>
                      </div>
                    </td>
                    <td class="py-2"><span class="badge bg-light text-secondary border" style="font-size: 0.68rem; padding: 2px 6px;">{{ prod.category_name || 'Mặc định' }}</span></td>
                    <td class="text-end fw-bold py-2" style="color: #E63B6F; font-size: 0.78rem;">{{ formatCurrency(prod.base_price) }}</td>
                    <td class="text-center font-monospace py-2" style="font-size: 0.75rem; color: #475569;">{{ prod.stock }}</td>
                    <td class="text-center py-2">
                      <span v-if="isProductAlreadyInCampaign(prod.product_id)" class="badge bg-secondary" style="font-size: 0.68rem;">Đã có trong sale</span>
                      <span v-else-if="selectedPickerProductIds.includes(prod.product_id)" class="badge" style="background: #E63B6F; color: #fff; font-size: 0.68rem;">Đã chọn</span>
                      <span v-else class="badge bg-light text-muted border" style="font-size: 0.68rem;">Chưa chọn</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>

          <div class="ws-modal-footer px-3 py-2 border-top d-flex justify-content-between align-items-center" style="background: #f8fafc;">
            <div class="fw-bold" style="font-size: 0.8rem; color: #be123c;">
              Đã chọn: <span class="badge" style="background: #E63B6F; font-size: 0.75rem;">{{ selectedPickerProductIds.length }}</span> sản phẩm
            </div>
            <div class="d-flex gap-2">
              <button type="button" @click="isPickerOpen = false" class="btn btn-sm btn-light border px-3" style="font-size: 0.78rem;">Hủy</button>
              <button type="button" @click="confirmBulkAdd" class="btn btn-sm text-white px-3 fw-bold d-inline-flex align-items-center gap-1" :disabled="selectedPickerProductIds.length === 0" style="background: #E63B6F; border-color: #E63B6F; font-size: 0.78rem;">
                <AppIcon name="plus" size="14" />
                Thêm {{ selectedPickerProductIds.length }} Sản Phẩm Vào Flash Sale
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
    <Transition name="ws-toast">
      <div v-if="toastObj.message" class="ws-toast" :class="toastObj.type === 'success' ? 'ws-toast-success' : (toastObj.type === 'warning' ? 'ws-toast-warning' : 'ws-toast-error')" style="display: block;">
        {{ toastObj.message }}
      </div>
    </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ===== Page Header ===== */
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.section-title { font-size: 1.15rem; font-weight: 700; color: var(--text-main, #102a43); display: flex; align-items: center; margin: 0; }
.section-desc { font-size: 0.78rem; color: var(--text-muted, #627d98); margin: 2px 0 0; }

.tab-header-actions { display: flex; justify-content: flex-end; }
.btn-create {
  display: flex; align-items: center; gap: 6px;
  background: var(--primary, #e63b6f); color: white; border: none;
  padding: 8px 16px; border-radius: 8px;
  font-weight: 600; font-size: 0.82rem; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 2px 8px rgba(230, 59, 111, 0.2);
  font-family: var(--font-inter, 'Inter', sans-serif);
}
.btn-create:hover { background: #d82f65; transform: translateY(-1px); }

/* ===== Table ===== */
.table-wrapper { overflow-x: auto; margin-bottom: 20px; }
.ws-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.ws-table th {
  text-align: left; padding: 12px 14px; font-weight: 700; font-size: 0.7rem;
  text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--text-muted, #627d98); border-bottom: 1px solid var(--border-color, #d9e8f0); 
  background: var(--ocean-deepest, #f0f7fa);
}
.ws-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-color, #d9e8f0); vertical-align: middle; }
.ws-row { transition: background 0.15s; }
.ws-row:hover { background: var(--hover-bg, #e6f4fa); }

.shift-info-cell { display: flex; align-items: center; gap: 12px; }
.shift-name { font-weight: 600; color: var(--text-main, #102a43); }
.shift-icon {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.time-badge {
  display: inline-flex; padding: 4px 10px; border-radius: 6px;
  font-size: 0.8rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;
}
.time-start { background: #e8f5e9; color: #2e7d32; }
.time-end { background: #ffebee; color: #c62828; }

.ws-status-badge {
  display: inline-flex; align-items: center; padding: 5px 12px;
  border-radius: 20px; font-size: 0.75rem; font-weight: 700;
}
.status-active { background: #e8f5e9; color: #2e7d32; }
.status-upcoming { background: #e0f2fe; color: #0284c7; }
.status-draft { background: #f5f5f5; color: #757575; }
.status-ended { background: #ffebee; color: #c62828; }
.status-inactive { background: #f5f5f5; color: #757575; }

.status-th, .actions-th { text-align: center !important; }
.status-cell, .actions-cell { text-align: center; }
.actions-cell { display: flex; gap: 6px; justify-content: center; }

.btn-action {
  width: 34px; height: 34px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s; border: 1.5px solid;
}
.btn-action.edit { background: #e3f2fd; color: #1565c0; border-color: #bbdefb; }
.btn-action.edit:hover { background: #bbdefb; color: #0d47a1; }
.btn-action.delete { background: #ffebee; color: var(--primary, #e63b6f); border-color: #ffcdd2; }
.btn-action.delete:hover { background: #ffcdd2; color: #c62828; }

/* Empty state */
.empty-cell { text-align: center; padding: 40px !important; color: var(--text-light, #9fb3c8); }

/* Custom Modal & Form Sizing */
.ws-modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(3px); z-index: 1050;
    display: flex; align-items: center; justify-content: center;
    padding: 24px 16px;
}
.ws-modal-box {
    background: #ffffff; border-radius: 14px; box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.25);
    display: flex; flex-direction: column; overflow: hidden;
    max-height: 85vh; width: 100%; margin: auto;
}
.fs-modal-box { max-width: 800px !important; width: 92% !important; }
.ws-modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.ws-modal-head h3 { margin: 0; font-size: 0.98rem; font-weight: 700; color: var(--text-main); }
.ws-btn-close {
    background: none; border: none; cursor: pointer; color: var(--text-muted);
    transition: color 0.2s;
}
.ws-btn-close:hover { color: var(--text-main); }
.ws-form-group { display: flex; flex-direction: column; gap: 4px; }
.ws-form-group label { font-weight: 600; font-size: 0.82rem; color: var(--text-main); }
.ws-required { color: #dc2626; }
.ws-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (max-width: 768px) { .ws-form-grid { grid-template-columns: 1fr; } }
.ws-form-control {
    padding: 8px 12px; border: 1.5px solid var(--border-color);
    border-radius: 8px; font-size: 0.85rem; font-family: inherit;
    transition: border-color 0.2s, box-shadow 0.2s; outline: none;
}
.ws-form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.ws-btn-outline {
    padding: 8px 16px; border: 1.5px solid var(--border-color);
    border-radius: 8px; background: white; font-weight: 600; cursor: pointer;
    transition: all 0.2s;
}
.ws-btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
.ws-btn-primary {
    padding: 8px 18px; border: none; border-radius: 8px;
    background: var(--primary); color: white; font-weight: 600; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    transition: all 0.2s;
}
.ws-btn-primary:hover { background: #d82f65; }

/* Selected row in bulk picker */
.picker-row-selected {
  background: #fff1f2 !important;
}

/* Modal Transitions */
.ws-modal-enter-active, .ws-modal-leave-active { transition: opacity 0.25s ease; }
.ws-modal-enter-from, .ws-modal-leave-to { opacity: 0; }
.ws-modal-enter-active .ws-modal-box { transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.ws-modal-enter-from .ws-modal-box, .ws-modal-leave-to .ws-modal-box { transform: scale(0.95) translateY(-15px); }

.cursor-pointer { cursor: pointer; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f5f9; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
