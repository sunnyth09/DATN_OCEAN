<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import api from '@/axios.js';
import { getStorageUrl } from '@/utils/url';
import Swal from 'sweetalert2';
import { Toast, Modal } from 'bootstrap';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';

// -- States --
const flashSales = ref([]);
const isModalOpen = ref(false);
const isEditing = ref(false);
const errors = ref({});
const isLoading = ref(false);

const toastObj = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toastObj.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('flashSaleToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
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
    }, 400);
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

const openEdit = (fs) => {
    isEditing.value = true;
    form.value = {
        id: fs.id,
        name: fs.name,
        // Ép kiểu Date-Time Input phù hợp với HTML5
        start_time: fs.start_time.slice(0, 16),
        end_time: fs.end_time.slice(0, 16),
        status: fs.status,
        items: fs.items.map(i => {
            const basePrice = i.product?.base_price || 0;
            let dp = 0;
            if (basePrice > 0) {
                dp = Math.max(0, Math.round((1 - i.campaign_price / basePrice) * 100));
            }
            return { ...i, discount_percent: dp };
        })
    };
    errors.value = {};
    isModalOpen.value = true;
};

const handleSubmit = async () => {
    errors.value = {};
    try {
        if (isEditing.value) {
            await api.put(`/admin/flash-sale/${form.value.id}`, form.value);
        } else {
            await api.post('/admin/flash-sale', form.value);
        }
        showToast('Lưu thành công!', 'success');
        isModalOpen.value = false;
        fetchFlashSales();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors;
        } else {
            showToast('Lỗi máy chủ!', 'danger');
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

onMounted(fetchFlashSales);
</script>

<template>
  <div class="admin-fs-container animate-in py-4 px-2">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
          Quản lý Flash Sale Campaign
        </h2>
        <p class="section-desc">Thiết lập sự kiện Flash Sale và quản lý danh sách sản phẩm giảm giá.</p>
      </div>
      <div class="tab-header-actions">
        <button class="btn-create" @click="openCreate">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
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
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                </div>
                <span class="shift-name">{{ fs.name }}</span>
              </div>
            </td>
            <td><span class="time-badge time-start">{{ new Date(fs.start_time).toLocaleString('vi-VN') }}</span></td>
            <td><span class="time-badge time-end">{{ new Date(fs.end_time).toLocaleString('vi-VN') }}</span></td>
            <td class="text-center" style="color: var(--text-muted)">
                <div class="fw-bold text-dark" style="font-size: 0.9rem;">{{ fs.items?.length || 0 }} mẫu SP</div>
                <small style="font-size: 0.75rem;">(Tổng cấp: {{ fs.items?.reduce((sum, item) => sum + Number(item.campaign_stock), 0) || 0 }})</small>
            </td>
            <td class="status-cell">
              <span class="ws-status-badge" :class="(new Date(fs.end_time) < new Date()) || fs.status === 'ended' ? 'status-ended' : (fs.status === 'active' ? 'status-active' : 'status-inactive')">
                {{ (new Date(fs.end_time) < new Date()) ? 'Đã kết thúc' : (STATUS_LABELS[fs.status]?.text || fs.status) }}
              </span>
            </td>
            <td class="actions-cell">
              <button class="btn-action edit" @click="openEdit(fs)" title="Sửa">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="btn-action delete" @click="handleDelete(fs.id)" title="Xóa">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
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
            <h3>{{ isEditing ? 'Sửa Flash Sale' : 'Tạo mới Flash Sale' }}</h3>
            <button class="ws-btn-close" @click="isModalOpen = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="ws-modal-body" style="max-height: 80vh; overflow-y: auto; padding: 24px;">
            <!-- General Settings -->
            <div class="ws-form-grid mb-4">
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
            <hr style="border-color: var(--border-color, #d9e8f0); margin: 24px 0;" />
            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2" style="color: var(--text-main)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Danh sách sản phẩm Sale
            </h6>
            
            <div class="position-relative mb-3" style="z-index: 1055;">
                <input v-model="productSearchTerm" @input="searchProducts" type="text" 
                       class="ws-form-control w-100" placeholder="🔍 Gõ tên để thêm sản phẩm vào sự kiện..." style="padding-left: 14px; width: 100%;" />
                
                <ul v-if="searchedProducts && searchedProducts.length > 0" class="list-group position-absolute w-100 mt-1 shadow-lg border-0" style="z-index: 9999; max-height: 250px; overflow-y: auto; border-radius: 8px;">
                    <li v-for="prod in searchedProducts" :key="prod.product_id" 
                        class="list-group-item list-group-item-action cursor-pointer d-flex align-items-center border-bottom"
                        @click="addProductToItems(prod)" style="padding: 12px 16px;">
                        <img :src="resolveThumbnail(prod.thumbnail)" alt="" style="width: 36px; height: 36px; object-fit: cover; border-radius: 6px; margin-right: 12px; border: 1px solid #eee" />
                        <div>
                            <div class="fw-bold" style="font-size: 0.85rem; color: #102a43;">{{ prod.name }}</div>
                            <small style="font-size: 0.75rem; color: #627d98;">Giá gốc: <span class="text-decoration-line-through">{{ formatCurrency(prod.base_price) }}</span> | Kho hiện tại: {{ prod.stock }}</small>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color, #d9e8f0); border-radius: 8px;">
                <table class="ws-table align-middle mb-0" style="font-size: 0.85rem; width: 100%;">
                    <thead class="position-sticky top-0" style="background: var(--ocean-deepest, #f0f7fa); z-index: 10;">
                        <tr>
                            <th class="py-3 px-3 text-uppercase" style="font-size: 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Tên sản phẩm</th>
                            <th class="py-3 px-3 text-uppercase text-center" width="120" style="font-size: 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Giảm giá (%)</th>
                            <th class="py-3 px-3 text-uppercase text-center" width="160" style="font-size: 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Giá Sale (VNĐ)</th>
                            <th class="py-3 px-3 text-uppercase text-center" width="130" style="font-size: 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">SL cấp FS</th>
                            <th class="py-3 px-3 text-uppercase text-center" width="90" style="font-size: 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Đã bán</th>
                            <th class="py-3 px-3 text-uppercase text-center" width="70" style="font-size: 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border-color);">Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in form.items" :key="index" style="transition: background 0.15s;">
                            <td class="px-3">
                                <div class="d-flex align-items-center">
                                    <img v-if="item.product && item.product.thumbnail" :src="resolveThumbnail(item.product.thumbnail)" alt="" style="width: 32px; height: 32px; object-fit: cover; border-radius: 6px; margin-right: 12px; border: 1px solid #eee;" />
                                    <span class="fw-semibold" style="color: var(--text-main)">{{ item.product?.name || `Product ID: ${item.product_id}` }}</span>
                                </div>
                            </td>
                            <td class="px-3">
                                <div class="input-group input-group-sm" style="border-radius: 6px; overflow: hidden;">
                                    <input v-model="item.discount_percent" @input="onDiscountPercentChange(item)" type="number" min="0" max="100" class="form-control text-center" style="border-color: #d9e8f0; background: #fff;" />
                                    <span class="input-group-text bg-light border-light" style="border-color: #d9e8f0 !important;">%</span>
                                </div>
                            </td>
                            <td class="px-3">
                                <input v-model="item.campaign_price" @input="onCampaignPriceChange(item)" type="number" class="form-control form-control-sm" style="border-radius: 6px; border-color: #d9e8f0;" />
                                <small class="text-danger d-block mt-1" v-if="errors[`items.${index}.campaign_price`]">{{ errors[`items.${index}.campaign_price`][0] }}</small>
                            </td>
                            <td class="px-3">
                                <input v-model="item.campaign_stock" type="number" class="form-control form-control-sm text-center" style="border-radius: 6px; border-color: #d9e8f0;" />
                                <small class="text-danger d-block mt-1" v-if="errors[`items.${index}.campaign_stock`]">{{ errors[`items.${index}.campaign_stock`][0] }}</small>
                            </td>
                            <td class="px-3 text-center fw-bold" style="color: var(--text-muted);">{{ item.sold }}</td>
                            <td class="px-3 text-center">
                                <button class="btn btn-sm btn-outline-danger border-0 rounded-circle" @click="removeItem(index)" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; background: #ffebee;">✖</button>
                            </td>
                        </tr>
                        <tr v-if="form.items.length === 0">
                            <td colspan="6" class="text-center text-muted py-5">
                                <div class="mb-2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                                Chưa có sản phẩm nào. Hãy tìm kiếm và chọn sản phẩm ở trên.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <small class="text-danger mt-2 d-block">{{ errors.items?.[0] }}</small>

          </div>
          <div class="ws-modal-footer p-4 border-top d-flex justify-content-end gap-3" style="background: var(--card-bg, #f8fafc); border-top: 1px solid var(--border-color); border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" @click="isModalOpen = false" class="ws-btn-outline">Hủy bỏ</button>
            <button type="button" @click="handleSubmit" class="ws-btn-primary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Lưu Thay Đổi
            </button>
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
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.section-title { font-size: 1.4rem; font-weight: 700; color: var(--text-main, #102a43); display: flex; align-items: center; }
.section-desc { font-size: 0.85rem; color: var(--text-muted, #627d98); margin-top: 4px; }

.tab-header-actions { display: flex; justify-content: flex-end; }
.btn-create {
  display: flex; align-items: center; gap: 8px;
  background: var(--primary, #e63b6f); color: white; border: none;
  padding: 10px 20px; border-radius: 10px;
  font-weight: 600; font-size: 0.85rem; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.25);
  font-family: var(--font-inter, 'Inter', sans-serif);
}
.btn-create:hover { background: #d82f65; transform: translateY(-1px); }

/* ===== Table ===== */
.table-wrapper { overflow-x: auto; margin-bottom: 24px; }
.ws-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.ws-table th {
  text-align: left; padding: 14px 16px; font-weight: 700; font-size: 0.72rem;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--text-muted, #627d98); border-bottom: 1px solid var(--border-color, #d9e8f0); 
  background: var(--ocean-deepest, #f0f7fa);
}
.ws-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color, #d9e8f0); vertical-align: middle; }
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
    background: rgba(0, 0, 0, 0.5); z-index: 1050;
    display: flex; align-items: center; justify-content: center;
}
.ws-modal-box {
    background: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    display: flex; flex-direction: column; overflow: hidden;
}
.fs-modal-box { max-width: 900px !important; width: 95% !important; }
.ws-modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 24px; border-bottom: 1px solid var(--border-color);
}
.ws-modal-head h3 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-main); }
.ws-btn-close {
    background: none; border: none; cursor: pointer; color: var(--text-muted);
    transition: color 0.2s;
}
.ws-btn-close:hover { color: var(--text-main); }
.ws-form-group { display: flex; flex-direction: column; gap: 6px; }
.ws-form-group label { font-weight: 600; font-size: 0.9rem; color: var(--text-main); }
.ws-required { color: #dc2626; }
.ws-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}
@media (max-width: 768px) { .ws-form-grid { grid-template-columns: 1fr; } }
.ws-form-control {
    padding: 10px 14px; border: 1.5px solid var(--border-color);
    border-radius: 8px; font-size: 0.95rem; font-family: inherit;
    transition: border-color 0.2s, box-shadow 0.2s; outline: none;
}
.ws-form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.ws-btn-outline {
    padding: 10px 20px; border: 1.5px solid var(--border-color);
    border-radius: 8px; background: white; font-weight: 600; cursor: pointer;
    transition: all 0.2s;
}
.ws-btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
.ws-btn-primary {
    padding: 10px 20px; border: none; border-radius: 8px;
    background: var(--primary); color: white; font-weight: 600; cursor: pointer;
    display: inline-flex; align-items: center; gap: 8px;
    transition: all 0.2s;
}
.ws-btn-primary:hover { background: #d82f65; }

/* Modal Transitions */
.ws-modal-enter-active, .ws-modal-leave-active { transition: opacity 0.3s ease; }
.ws-modal-enter-from, .ws-modal-leave-to { opacity: 0; }
.ws-modal-enter-active .ws-modal-box { transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.ws-modal-enter-from .ws-modal-box, .ws-modal-leave-to .ws-modal-box { transform: scale(0.9) translateY(-30px); }

.cursor-pointer { cursor: pointer; }
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #f1f1f1; }
::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #888; }
</style>
<style>
/* ===== WS Modal Overlay ===== */
.ws-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.ws-modal-box {
  width: 100%; max-width: 520px; padding: 0;
  background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px; overflow: hidden;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.ws-modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px; border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.ws-modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #102a43); margin: 0; }
.ws-btn-close {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted, #627d98); display: flex; align-items: center; justify-content: center;
  padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.ws-btn-close:hover { background: var(--hover-bg, #e6f4fa); color: var(--primary, var(--primary)); }

.ws-modal-body { padding: 24px; }
.ws-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

/* Form */
.ws-form-group { margin-bottom: 16px; }
.ws-form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main, #102a43); margin-bottom: 8px; }
.ws-required { color: var(--primary, var(--primary)); }
.ws-form-control {
  width: 100%; padding: 10px 14px; border-radius: 8px;
  border: 1px solid var(--border-color, #d9e8f0); background: var(--ocean-deepest, #f0f7fa);
  color: var(--text-main, #102a43); font-family: var(--font-inter, 'Inter', sans-serif);
  font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.ws-form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.ws-form-control::placeholder { color: var(--text-light, #9fb3c8); }
.ws-form-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 14px center;
}

/* Buttons */
.ws-btn-outline {
  padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color, #d9e8f0);
  background: var(--card-bg); color: var(--text-main, #102a43); font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter, 'Inter', sans-serif);
}
.ws-btn-outline:hover { border-color: var(--ocean-mid, #b3e0f2); background: var(--ocean-deepest, #f0f7fa); }
.ws-btn-primary {
  padding: 10px 20px; border-radius: 8px; border: none;
  background: var(--primary); color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
  font-family: var(--font-inter, 'Inter', sans-serif);
}
.ws-btn-primary:hover { background: #d82f65; }
.ws-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

/* Transitions */
.ws-modal-enter-active, .ws-modal-leave-active { transition: all 0.25s ease; }
.ws-modal-enter-from, .ws-modal-leave-to { opacity: 0; }
.ws-modal-enter-from .ws-modal-box, .ws-modal-leave-to .ws-modal-box { transform: scale(0.95) translateY(10px); }

/* Đảm bảo toast hiển thị mượt mà */
.ws-toast-enter-active { transition: all 0.3s ease; }
.ws-toast-leave-active { transition: all 0.2s ease; }
.ws-toast-enter-from { opacity: 0; transform: translateX(40px); }
.ws-toast-leave-to { opacity: 0; transform: translateX(40px); }
.ws-toast-warning { background: #ff9800 !important; }
</style>
