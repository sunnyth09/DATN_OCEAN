<script setup>
import { ref, onMounted, nextTick, computed } from 'vue';
import api from '@/axios.js';
import Swal from 'sweetalert2';
import { Toast } from 'bootstrap';

// -- States --
const flashSales = ref([]);
const searchQuery = ref('');
const filteredFlashSales = computed(() => {
    if (!searchQuery.value) return flashSales.value;
    const q = searchQuery.value.toLowerCase();
    return flashSales.value.filter(fs => fs.name && fs.name.toLowerCase().includes(q));
});
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

const getStatusLabel = (status) => {
    const map = {
        'active': { text: 'Đang chạy', class: 'active' },
        'draft': { text: 'Bản nháp', class: 'draft' },
        'ended': { text: 'Đã kết thúc', class: 'inactive' }
    };
    return map[status] || map['draft'];
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
    const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8383/api';
    return `${apiUrl.replace('/api', '')}/storage/${url}`;
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
    <div class="post-page px-4 pt-4">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    Quản lý Flash Sale Campaign
                </h1>
                <p class="page-subtitle">Quản lý các chương trình Flash Sale, chiến dịch giảm giá</p>
            </div>
            <button class="btn-primary" @click="openCreate">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tạo Campaign
            </button>
        </div>

        <!-- Filters & Search -->
        <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Tìm kiếm chiến dịch theo tên..."
                    class="search-input"
                />
            </div>
            <div class="filter-stats">
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                    {{ flashSales.length }} chiến dịch
                </span>
            </div>
        </div>

        <!-- LIST VIEW -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredFlashSales.length }}</strong> chiến dịch tìm thấy
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên Campaign</th>
                            <th>Thời gian bắt đầu</th>
                            <th>Thời gian kết thúc</th>
                            <th>Số lượng SP</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading"><td colspan="6" class="text-center" style="padding: 24px;">Đang tải...</td></tr>
                        <template v-else-if="filteredFlashSales.length > 0">
                            <tr v-for="fs in filteredFlashSales" :key="fs.id">
                                <td>
                                    <div class="post-title-cell">
                                        <span class="post-title" :title="fs.name">{{ fs.name }}</span>
                                    </div>
                                </td>
                                <td>{{ new Date(fs.start_time).toLocaleString('vi-VN') }}</td>
                                <td>{{ new Date(fs.end_time).toLocaleString('vi-VN') }}</td>
                                <td>{{ fs.items?.length || 0 }} Sản phẩm</td>
                                <td>
                                    <span class="status-badge" :class="getStatusLabel(fs.status).class">
                                        {{ getStatusLabel(fs.status).text }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button @click="openEdit(fs)" class="btn-action edit" title="Chỉnh sửa">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <button @click="handleDelete(fs.id)" class="btn-action delete" title="Xóa">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            
            <!-- Empty State -->
            <div v-if="!isLoading && filteredFlashSales.length === 0" class="empty-state">
                <span class="empty-emoji">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                         <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </span>
                <h3>Không tìm thấy chiến dịch</h3>
                <p>{{ searchQuery ? 'Thử từ khóa khác.' : 'Bắt đầu bằng cách thêm chiến dịch Flash Sale đầu tiên.' }}</p>
                <button v-if="!searchQuery" @click="openCreate" class="btn-primary mt-3" style="display:inline-flex; margin: 0 auto;">Tạo chiến dịch ngay</button>
            </div>
        </div>

        <!-- FORM MODAL (Full Width/Overlay) -->
        <div v-if="isModalOpen" class="fs-modal-overlay">
            <div class="card fs-modal-content">
                <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                    <h5 class="m-0">{{ isEditing ? 'Sửa Flash Sale' : 'Tạo mới Flash Sale' }}</h5>
                    <button class="btn-close" @click="isModalOpen = false"></button>
                </div>
                
                <div class="card-body" style="overflow: visible;">
                    <!-- General Settings -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Tên Campaign <span class="text-danger">*</span></label>
                            <input v-model="form.name" type="text" class="form-control" />
                            <small class="text-danger">{{ errors.name?.[0] }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select v-model="form.status" class="form-select">
                                <option value="draft">Bản nháp</option>
                                <option value="active">Active (Set lên Redis)</option>
                                <option value="ended">Ended (Thu hồi Redis)</option>
                            </select>
                            <small class="text-danger">{{ errors.status?.[0] }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian bắt đầu <span class="text-danger">*</span></label>
                            <input v-model="form.start_time" type="datetime-local" class="form-control" />
                            <small class="text-danger">{{ errors.start_time?.[0] }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian kết thúc <span class="text-danger">*</span></label>
                            <input v-model="form.end_time" type="datetime-local" class="form-control" />
                            <small class="text-danger">{{ errors.end_time?.[0] }}</small>
                        </div>
                    </div>

                    <!-- Dynamic Items Settings -->
                    <hr/>
                    <h6 class="fw-bold mb-3">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                     Danh sách sản phẩm Sale</h6>
                    <div class="position-relative mb-3" style="z-index: 1055;">
                        <input v-model="productSearchTerm" @input="searchProducts" type="text" 
                               class="form-control shadow-sm" placeholder="🔍 Gõ tên để thêm sản phẩm vào sự kiện..." />
                        
                        <ul v-if="searchedProducts && searchedProducts.length > 0" class="list-group position-absolute w-100 mt-1 shadow-lg" style="z-index: 9999; max-height: 250px; overflow-y: auto;">
                            <li v-for="prod in searchedProducts" :key="prod.product_id" 
                                class="list-group-item list-group-item-action cursor-pointer d-flex align-items-center"
                                @click="addProductToItems(prod)">
                                <img :src="resolveThumbnail(prod.thumbnail)" alt="" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; margin-right: 10px;" />
                                <div>
                                    <div class="fw-bold">{{ prod.name }}</div>
                                    <small>Giá gốc: <span class="text-decoration-line-through text-muted">{{ formatCurrency(prod.base_price) }}</span> | Kho hiện tại: {{ prod.stock }}</small>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light position-sticky top-0 shadow-sm">
                                <tr>
                                    <th>Tên sản phẩm</th>
                                <th width="120">Giảm giá (%)</th>
                                <th width="180">Giá Sale (VNĐ)</th>
                                <th width="150">Số lượng cấp FB</th>
                                <th width="100">Đã bán</th>
                                <th width="80">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img v-if="item.product && item.product.thumbnail" :src="resolveThumbnail(item.product.thumbnail)" alt="" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; margin-right: 10px;" />
                                        <span>{{ item.product?.name || `Product ID: ${item.product_id}` }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input v-model="item.discount_percent" @input="onDiscountPercentChange(item)" type="number" min="0" max="100" class="form-control" />
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>
                                <td>
                                    <input v-model="item.campaign_price" @input="onCampaignPriceChange(item)" type="number" class="form-control form-control-sm" />
                                    <small class="text-danger" v-if="errors[`items.${index}.campaign_price`]">{{ errors[`items.${index}.campaign_price`][0] }}</small>
                                </td>
                                <td>
                                    <input v-model="item.campaign_stock" type="number" class="form-control form-control-sm" />
                                    <small class="text-danger" v-if="errors[`items.${index}.campaign_stock`]">{{ errors[`items.${index}.campaign_stock`][0] }}</small>
                                </td>
                                <td>{{ item.sold }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger" @click="removeItem(index)">✖</button>
                                </td>
                            </tr>
                            <tr v-if="form.items.length === 0">
                                <td colspan="5" class="text-center text-muted">Chưa có sản phẩm nào. Hãy tìm kiếm và chọn sản phẩm ở trên.</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <small class="text-danger">{{ errors.items?.[0] }}</small>

                </div>
                <!-- Action Bottom -->
                <div class="card-footer bg-white text-end">
                    <button class="btn btn-secondary me-2" @click="isModalOpen = false">Hủy</button>
                    <button class="btn btn-primary" @click="handleSubmit">Lưu Thay Đổi</button>
                </div>
            </div>
        </div>

        <!-- Bootstrap Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
            <div class="toast align-items-center border-0" :class="toastObj.type === 'success' ? 'text-bg-success' : (toastObj.type === 'warning' ? 'text-bg-warning' : 'text-bg-danger')" id="flashSaleToast" role="alert">
                <div class="d-flex">
                    <div class="toast-body fw-bold text-white fs-6">{{ toastObj.message }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.post-page { font-family: var(--font-inter); padding-bottom: 2rem;}

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

/* Buttons */
.btn-primary {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 8px; border: none;
    background: #E63B6F; color: white; text-decoration: none;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover {
    background: var(--ocean-bright); transform: translateY(-2px); color: white;
    box-shadow: 0 6px 14px rgba(3, 169, 244, 0.3);
}

/* Filters */
.filters-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; margin-bottom: 24px; gap: 16px;
}
.search-box {
    display: flex; align-items: center; gap: 10px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    border-radius: 8px; padding: 10px 16px; flex: 1; max-width: 400px;
    transition: all 0.2s;
}
.search-box:focus-within {
    border-color: #E63B6F; background: white;
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}
.search-box svg { color: var(--text-light); flex-shrink: 0; }
.search-input {
    background: none; border: none; outline: none;
    color: var(--text-main); font-family: var(--font-inter);
    font-size: 0.9rem; width: 100%;
}
.search-input::placeholder { color: var(--text-light); }

.filter-stats { display: flex; gap: 8px; flex-shrink: 0; }
.stat-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    font-size: 0.8rem; font-weight: 600;
}
.stat-pill svg { color: #E63B6F; }

/* Table */
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); }
.table-count { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
.table-count strong { color: var(--text-main); font-weight: 800; }

.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 14px 24px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.data-table :deep(td) {
    padding: 14px 24px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s; vertical-align: middle;
}
.data-table :deep(tbody tr:hover td) { background: var(--hover-bg); }

/* Custom Row Styles */
.post-title-cell { display: flex; align-items: center; gap: 8px; }
.post-title { font-weight: 600; color: var(--text-main); font-size: 0.95rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}

.status-badge {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.status-badge.active { background: rgba(230, 59, 111, 0.08); color: #0284c7; }
.status-badge.inactive { background: #f1f5f9; color: #64748b; }
.status-badge.draft { background: #fef3c7; color: #d97706; }

.action-buttons { display: flex; gap: 8px; }
.btn-action {
    background: none; border: none; padding: 6px; border-radius: 6px;
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.btn-action.edit { color: #E63B6F; background: rgba(230, 59, 111, 0.08); }
.btn-action.delete { color: var(--coral); background: #fee2e2; }
.btn-action:hover { transform: scale(1.1); }

/* Empty */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.empty-state p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }

/* Responsive */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
    .search-box { max-width: 100%; }
    .filter-stats { justify-content: flex-start; }
}

.fs-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.5);
    z-index: 1050;
    display: flex;
    justify-content: center;
    align-items: center;
}
.fs-modal-content {
    width: 900px;
    max-width: 95vw;
    max-height: 90vh;
}
.cursor-pointer { cursor: pointer; }
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #f1f1f1; }
::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #888; }
</style>
