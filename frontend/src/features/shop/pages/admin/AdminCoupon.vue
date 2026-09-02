<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';

const coupons = ref([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const isEditing = ref(false);
const searchQuery = ref('');
const currentTab = ref('all'); // 'all' | 'active' | 'expired' | 'inactive' | 'trashed'
const counts = ref({ all: 0, active: 0, expired: 0, inactive: 0, trashed: 0 });

// Bulk Selection
const selectedCouponIds = ref([]);
const isBulkLoading = ref(false);

// === Danh mục ===
const allCategories = ref([]);

// === Usages modal ===
const isUsagesModalOpen = ref(false);
const usagesData = ref(null);
const isLoadingUsages = ref(false);

const toLocalDatetimeInput = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const defaultForm = () => ({
    id: null,
    code: '',
    type: 'fixed',
    value: '',
    max_discount_value: '',
    min_order_value: '',
    usage_limit: '',
    user_usage_limit: 1,
    is_public: true,
    is_first_order: false,
    start_date: '',
    end_date: '',
    is_active: true,
    category_ids: [],
    send_email: false,
});

const form = ref(defaultForm());

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

const fetchCounts = async () => {
    try {
        const res = await api.get('/admin/coupons/counts');
        if (res.data?.status === 'success') {
            counts.value = res.data.data;
        }
    } catch (e) {
        console.error('Lỗi tải coupon counts:', e);
    }
};

const fetchCoupons = async () => {
    try {
        isLoading.value = true;
        selectedCouponIds.value = [];

        const params = {
            search: searchQuery.value || undefined,
            per_page: 50,
        };

        if (currentTab.value === 'trashed') {
            params.trashed = 'only';
        } else if (currentTab.value !== 'all') {
            params.status = currentTab.value;
        }

        const response = await api.get('/admin/coupons', { params });
        if (response.data.status === 'success') {
            coupons.value = response.data.data;
        }
    } catch (error) {
        showToast('Lỗi khi tải mã giảm giá!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

const switchTab = (tab) => {
    currentTab.value = tab;
    selectedCouponIds.value = [];
    fetchCoupons();
    fetchCounts();
};

const isAllSelected = computed(() => {
    return filteredCoupons.value.length > 0 && selectedCouponIds.value.length === filteredCoupons.value.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedCouponIds.value = [];
    } else {
        selectedCouponIds.value = filteredCoupons.value.map(c => c.id);
    }
};

const clearSelection = () => {
    selectedCouponIds.value = [];
};

const filteredCoupons = computed(() => {
    let list = coupons.value;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c => c.code && c.code.toLowerCase().includes(q));
    }
    return list;
});

const fetchCategories = async () => {
    try {
        const res = await api.get('/categories');
        allCategories.value = res.data.data || res.data;
    } catch (e) {
        console.error('Lỗi tải danh mục:', e);
    }
};

onMounted(() => {
    fetchCoupons();
    fetchCounts();
    fetchCategories();
});

const openCreateModal = () => {
    isEditing.value = false;
    formError.value = '';
    errors.value = {};
    form.value = defaultForm();
    isModalOpen.value = true;
};

const openEditModal = (coupon) => {
    isEditing.value = true;
    formError.value = '';
    errors.value = {};
    form.value = {
        id: coupon.id,
        code: coupon.code,
        type: coupon.type,
        value: coupon.value,
        max_discount_value: coupon.max_discount_value || '',
        min_order_value: coupon.min_order_value || '',
        usage_limit: coupon.usage_limit || '',
        user_usage_limit: coupon.user_usage_limit !== undefined && coupon.user_usage_limit !== null ? coupon.user_usage_limit : 1,
        is_public: Boolean(coupon.is_public),
        is_first_order: Boolean(coupon.is_first_order),
        start_date: toLocalDatetimeInput(coupon.start_date),
        end_date: toLocalDatetimeInput(coupon.end_date),
        is_active: Boolean(coupon.is_active),
        category_ids: Array.isArray(coupon.categories) ? coupon.categories.map(c => c.category_id) : (Array.isArray(coupon.category_ids) ? [...coupon.category_ids] : []),
        send_email: false,
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    isCatDropdownOpen.value = false;
};

const formError = ref('');
const errors = ref({});

const handleSubmit = async () => {
    formError.value = '';
    errors.value = {};

    let hasError = false;
    if (!form.value.code.trim()) { errors.value.code = 'Vui lòng nhập mã code.'; hasError = true; }
    if (!form.value.value || form.value.value <= 0) { errors.value.value = 'Mức giảm phải lớn hơn 0.'; hasError = true; }
    if (form.value.type === 'percent' && (!form.value.value || form.value.value > 100)) { errors.value.value = 'Phần trăm giảm không được vượt quá 100%'; hasError = true; }
    if (!form.value.start_date) { errors.value.start_date = 'Vui lòng chọn ngày bắt đầu.'; hasError = true; }
    if (!form.value.end_date) { errors.value.end_date = 'Vui lòng chọn ngày kết thúc.'; hasError = true; }

    if (hasError) return;

    isSubmitting.value = true;

    let payload = {
        ...form.value,
        is_active: Boolean(form.value.is_active),
        is_public: Boolean(form.value.is_public),
        is_first_order: Boolean(form.value.is_first_order),
        send_email: Boolean(form.value.send_email),
    };
    
    if (payload.max_discount_value === '') payload.max_discount_value = null;
    if (payload.min_order_value === '') payload.min_order_value = null;
    if (payload.usage_limit === '') payload.usage_limit = null;
    if (payload.start_date === '') payload.start_date = null;
    if (payload.end_date === '') payload.end_date = null;
    
    if (payload.type !== 'percent') {
        payload.max_discount_value = null;
    }

    try {
        if (isEditing.value) {
            const res = await api.put(`/admin/coupons/${payload.id}`, payload);
            showToast(res.data.message || 'Cập nhật thành công!', 'success');
        } else {
            const res = await api.post('/admin/coupons', payload);
            showToast(res.data.message || 'Tạo mã mới thành công!', 'success');
        }
        await fetchCoupons();
        await fetchCounts();
        closeModal();
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            for (const key in backendErrors) {
                errors.value[key] = backendErrors[key][0];
            }
        } else {
            formError.value = error.response?.data?.message || (isEditing.value ? 'Cập nhật thất bại!' : 'Tạo mã mới thất bại!');
        }
    } finally {
        isSubmitting.value = false;
    }
};

// Single Soft Delete
const confirmDeleteCouponPrompt = async (coupon) => {
    const result = await Swal.fire({
        title: 'Chuyển vào thùng rác?',
        html: `Bạn có chắc chắn muốn chuyển mã giảm giá <strong>${coupon.code}</strong> vào thùng rác?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/admin/coupons/${coupon.id}`);
            showToast(res.data.message || 'Đã chuyển mã giảm giá vào thùng rác!', 'success');
            await fetchCoupons();
            await fetchCounts();
        } catch (error) {
            showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
        }
    }
};

// Single Restore
const restoreCoupon = async (coupon) => {
    try {
        const res = await api.post(`/admin/coupons/${coupon.id}/restore`);
        showToast(res.data?.message || 'Khôi phục mã giảm giá thành công!', 'success');
        await fetchCoupons();
        await fetchCounts();
    } catch (error) {
        showToast(error.response?.data?.message || 'Khôi phục thất bại!', 'danger');
    }
};

// Single Force Delete
const forceDeleteCoupon = async (coupon) => {
    const result = await Swal.fire({
        title: 'XÓA VĨNH VIỄN?',
        html: `Hành động này sẽ <strong>xóa hoàn toàn</strong> mã <strong>${coupon.code}</strong> khỏi cơ sở dữ liệu và <strong class="text-danger">KHÔNG THỂ KHÔI PHỤC</strong>.`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#b91c1c',
        confirmButtonText: 'Xác nhận xóa vĩnh viễn',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/admin/coupons/${coupon.id}/force`);
            showToast(res.data?.message || 'Đã xóa vĩnh viễn mã giảm giá!', 'success');
            await fetchCoupons();
            await fetchCounts();
        } catch (error) {
            showToast(error.response?.data?.message || 'Lỗi xóa vĩnh viễn!', 'danger');
        }
    }
};

// Bulk Actions
const handleBulkTrash = async () => {
    const count = selectedCouponIds.value.length;
    const result = await Swal.fire({
        title: `Xóa ${count} mã giảm giá?`,
        text: `Chuyển ${count} mã đã chọn vào thùng rác?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy'
    });

    if (!result.isConfirmed) return;

    isBulkLoading.value = true;
    try {
        for (const id of selectedCouponIds.value) {
            await api.delete(`/admin/coupons/${id}`);
        }
        showToast(`Đã chuyển ${count} mã giảm giá vào thùng rác!`, 'success');
        selectedCouponIds.value = [];
        await fetchCoupons();
        await fetchCounts();
    } catch (e) {
        showToast(e.response?.data?.message || 'Lỗi thao tác hàng loạt!', 'danger');
    } finally {
        isBulkLoading.value = false;
    }
};

const handleBulkRestore = async () => {
    const count = selectedCouponIds.value.length;
    isBulkLoading.value = true;
    try {
        const res = await api.post('/admin/coupons/bulk-restore', { ids: selectedCouponIds.value });
        showToast(res.data?.message || `Đã khôi phục ${count} mã giảm giá!`, 'success');
        selectedCouponIds.value = [];
        await fetchCoupons();
        await fetchCounts();
    } catch (e) {
        showToast(e.response?.data?.message || 'Lỗi khôi phục hàng loạt!', 'danger');
    } finally {
        isBulkLoading.value = false;
    }
};

const handleBulkForceDelete = async () => {
    const count = selectedCouponIds.value.length;
    const result = await Swal.fire({
        title: `XÓA VĨNH VIỄN ${count} MÃ GIẢM GIÁ?`,
        text: `Hành động này sẽ xóa hoàn toàn ${count} mã đã chọn và KHÔNG THỂ HOÀN TÁC!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#b91c1c',
        confirmButtonText: 'Xóa vĩnh viễn',
        cancelButtonText: 'Hủy'
    });

    if (!result.isConfirmed) return;

    isBulkLoading.value = true;
    try {
        const res = await api.post('/admin/coupons/bulk-force-delete', { ids: selectedCouponIds.value });
        showToast(res.data?.message || `Đã xóa vĩnh viễn ${count} mã giảm giá!`, 'success');
        selectedCouponIds.value = [];
        await fetchCoupons();
        await fetchCounts();
    } catch (e) {
        showToast(e.response?.data?.message || 'Lỗi xóa vĩnh viễn hàng loạt!', 'danger');
    } finally {
        isBulkLoading.value = false;
    }
};

const formatValue = (coupon) => {
    if (coupon.type === 'percent') return `${coupon.value}%`;
    if (coupon.type === 'free_ship') return `Ship: ${formatCurrency(coupon.value)}`;
    return formatCurrency(coupon.value);
};

const formatCurrency = (val) => {
    if (!val) return '0đ';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const df = new Date(dateString);
    return df.toLocaleDateString('vi-VN') + ' ' + df.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const isExpired = (endDate) => {
    if (!endDate) return false;
    return new Date(endDate) < new Date();
};

// === Usages Modal ===
const openUsagesModal = async (couponId) => {
    isUsagesModalOpen.value = true;
    isLoadingUsages.value = true;
    usagesData.value = null;
    try {
        const res = await api.get(`/admin/coupons/${couponId}/usages`);
        if (res.data.status === 'success') {
            usagesData.value = res.data.data;
        }
    } catch (e) {
        showToast('Lỗi khi tải lượt dùng!', 'danger');
    } finally {
        isLoadingUsages.value = false;
    }
};

const closeUsagesModal = () => {
    isUsagesModalOpen.value = false;
    usagesData.value = null;
};

const isCatDropdownOpen = ref(false);

const toggleCategory = (catId) => {
    const idx = form.value.category_ids.indexOf(catId);
    if (idx > -1) {
        form.value.category_ids.splice(idx, 1);
    } else {
        form.value.category_ids.push(catId);
    }
};

const getCategoryName = (catId) => {
    for (const cat of allCategories.value) {
        if (cat.category_id === catId) return cat.name;
        if (cat.children) {
            const child = cat.children.find(c => c.category_id === catId);
            if (child) return child.name;
        }
    }
    return '';
};
</script>

<template>
    <div class="category-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
                    </svg>
                    Quản lý Mã giảm giá
                </h1>
                <p class="page-subtitle">Quản lý các loại mã giảm giá, Freeship, Flash Sale và khôi phục mã đã xóa</p>
            </div>
            <button @click="openCreateModal" class="btn-primary" id="add-coupon-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tạo mã mới
            </button>
        </div>

        <!-- Status Tabs Bar with Live Counts -->
        <div class="coupon-tabs-bar animate-in" style="animation-delay: 0.05s">
            <button
                class="tab-pill"
                :class="{ active: currentTab === 'all' }"
                @click="switchTab('all')"
            >
                <span>Tất cả</span>
                <span class="tab-badge">{{ counts.all }}</span>
            </button>

            <button
                class="tab-pill"
                :class="{ active: currentTab === 'active' }"
                @click="switchTab('active')"
            >
                <span class="status-dot dot-active"></span>
                <span>Đang áp dụng</span>
                <span class="tab-badge">{{ counts.active }}</span>
            </button>

            <button
                class="tab-pill"
                :class="{ active: currentTab === 'expired' }"
                @click="switchTab('expired')"
            >
                <span class="status-dot dot-expired"></span>
                <span>Đã hết hạn</span>
                <span class="tab-badge">{{ counts.expired }}</span>
            </button>

            <button
                class="tab-pill"
                :class="{ active: currentTab === 'inactive' }"
                @click="switchTab('inactive')"
            >
                <span class="status-dot dot-inactive"></span>
                <span>Tạm khóa</span>
                <span class="tab-badge">{{ counts.inactive }}</span>
            </button>

            <button
                class="tab-pill tab-trash"
                :class="{ active: currentTab === 'trashed' }"
                @click="switchTab('trashed')"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span>Thùng rác (Xóa mềm)</span>
                <span class="tab-badge badge-trash">{{ counts.trashed }}</span>
            </button>
        </div>

        <!-- Filters & Search  -->
        <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Tìm kiếm theo mã code (VD: SALE50K).."
                    class="search-input"
                />
            </div>
            <div class="filter-stats">
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                    {{ filteredCoupons.length }} mã {{ currentTab === 'trashed' ? 'trong thùng rác' : 'hiển thị' }}
                </span>
            </div>
        </div>

        <!-- Floating Bulk Action Bar -->
        <div v-if="selectedCouponIds.length > 0" class="bulk-action-bar animate-in">
            <div class="bulk-action-info">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Đã chọn <strong>{{ selectedCouponIds.length }}</strong> mã giảm giá</span>
            </div>
            <div class="bulk-action-btns">
                <template v-if="currentTab === 'trashed'">
                    <button class="btn-bulk-restore" @click="handleBulkRestore" :disabled="isBulkLoading">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10"></polyline>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                        </svg>
                        <span>Khôi phục đã chọn ({{ selectedCouponIds.length }})</span>
                    </button>
                    <button class="btn-bulk-force" @click="handleBulkForceDelete" :disabled="isBulkLoading">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        <span>Xóa vĩnh viễn đã chọn</span>
                    </button>
                </template>
                <template v-else>
                    <button class="btn-bulk-trash" @click="handleBulkTrash" :disabled="isBulkLoading">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        <span>Chuyển vào thùng rác</span>
                    </button>
                </template>
                <button class="btn-bulk-cancel" @click="clearSelection">Bỏ chọn</button>
            </div>
        </div>

        <!-- Loading State -->
        <AdminTableSkeleton v-if="isLoading" :columns="currentTab === 'trashed' ? 7 : 8" :rows="6" />

        <!-- Coupon Table -->
        <div v-else class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredCoupons.length }}</strong> mã giảm giá {{ currentTab === 'trashed' ? 'trong thùng rác' : 'được tìm thấy' }}
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input
                                    type="checkbox"
                                    class="form-check-input select-checkbox"
                                    :checked="isAllSelected"
                                    @change="toggleSelectAll"
                                    :disabled="filteredCoupons.length === 0"
                                />
                            </th>
                            <th>Mã code / Loại</th>
                            <th>Mức giảm</th>
                            <th>Điều kiện / Giới hạn</th>
                            <th>Lượt xài</th>
                            <th>{{ currentTab === 'trashed' ? 'Ngày xóa mềm' : 'Thời gian' }}</th>
                            <th>Trạng thái</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredCoupons.length === 0">
                            <td :colspan="currentTab === 'trashed' ? 7 : 8" class="empty-cell">
                                <span class="empty-emoji">{{ currentTab === 'trashed' ? '🗑️' : '🎟️' }}</span>
                                <h3>{{ currentTab === 'trashed' ? 'Thùng rác trống (Không có mã giảm giá bị xóa)' : 'Không tìm thấy mã giảm giá' }}</h3>
                                <p class="small text-muted mt-1">{{ currentTab === 'trashed' ? 'Các voucher bị xóa mềm sẽ được lưu trữ tại đây và có thể khôi phục bất cứ lúc nào.' : (searchQuery ? 'Thử tìm kiếm bằng từ khóa khác.' : 'Bắt đầu bằng cách bấm Tạo mã mới.') }}</p>
                            </td>
                        </tr>
                        <tr
                            v-for="coupon in filteredCoupons"
                            :key="coupon.id"
                            v-else
                            :class="{ 'row-selected': selectedCouponIds.includes(coupon.id), 'row-trashed': currentTab === 'trashed' }"
                        >
                            <td style="text-align: center;">
                                <input
                                    type="checkbox"
                                    class="form-check-input select-checkbox"
                                    :value="coupon.id"
                                    v-model="selectedCouponIds"
                                />
                            </td>
                            <td>
                                <span class="code-text" :class="!coupon.is_public ? 'private-code' : ''">
                                    {{ coupon.code }} 
                                    <svg v-if="!coupon.is_public" title="Ẩn nội bộ" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align:top;margin-left:2px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <div class="type-badge">{{ coupon.type === 'percent' ? 'Phần trăm' : (coupon.type === 'free_ship' ? 'Freeship' : 'Cố định') }}</div>
                                <div v-if="coupon.categories && coupon.categories.length" class="cat-badges">
                                    <span v-for="cat in coupon.categories" :key="cat.category_id" class="badge-category">{{ cat.name }}</span>
                                </div>
                                <div v-else class="cat-badges"><span class="badge-category all">Tất cả</span></div>
                            </td>
                            <td>
                                <strong class="value-text">{{ formatValue(coupon) }}</strong>
                                <div v-if="coupon.type === 'percent' && coupon.max_discount_value" class="max-discount-text">
                                    Tối đa: {{ formatCurrency(coupon.max_discount_value) }}
                                </div>
                            </td>
                            <td>
                                <div class="condition-info">
                                    <div v-if="coupon.min_order_value">Từ: <b>{{ formatCurrency(coupon.min_order_value) }}</b></div>
                                    <div v-else>Mọi đơn hàng</div>
                                    <span v-if="coupon.is_first_order" class="badge-first-order" title="Chỉ áp dụng đơn đầu tiên">Đơn đầu tiên</span>
                                    <span v-if="coupon.user_usage_limit" class="badge-user-limit" title="Số lượt dùng mỗi User">User: {{ coupon.user_usage_limit }} lần</span>
                                </div>
                            </td>
                            <td>
                                <div class="usage-info">
                                    <span>{{ coupon.used_count }}</span> / 
                                    <span v-if="coupon.usage_limit">{{ coupon.usage_limit }}</span>
                                    <span v-else>∞</span>
                                </div>
                                <button class="btn-usage" @click="openUsagesModal(coupon.id)" title="Xem chi tiết lượt dùng">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                    {{ coupon.total_users_used || 0 }} user
                                </button>
                            </td>
                            <td class="date-cell">
                                <template v-if="currentTab === 'trashed'">
                                    <div><small>Xóa lúc:</small> {{ formatDate(coupon.deleted_at) || '-' }}</div>
                                </template>
                                <template v-else>
                                    <div><small>Từ:</small> {{ formatDate(coupon.start_date) || '-' }}</div>
                                    <div><small>Đến:</small> <span :class="{'expired': isExpired(coupon.end_date)}">{{ formatDate(coupon.end_date) || '-' }}</span></div>
                                </template>
                            </td>
                            <td>
                                <template v-if="currentTab === 'trashed'">
                                    <span class="status-badge inactive">Đã xóa</span>
                                </template>
                                <template v-else>
                                    <span :class="['status-badge', (coupon.is_active && !isExpired(coupon.end_date)) ? 'active' : 'inactive']">
                                        {{ isExpired(coupon.end_date) ? 'Hết hạn' : (coupon.is_active ? 'Kích hoạt' : 'Tạm khóa') }}
                                    </span>
                                </template>
                            </td>
                            <td style="text-align: right;">
                                <!-- Trashed Actions -->
                                <div v-if="currentTab === 'trashed'" class="action-buttons justify-content-end">
                                    <button class="btn-action-restore" title="Khôi phục mã giảm giá" @click="restoreCoupon(coupon)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="1 4 1 10 7 10"></polyline>
                                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                        </svg>
                                        <span>Khôi phục</span>
                                    </button>
                                    <button class="btn-action delete" title="Xóa vĩnh viễn" @click="forceDeleteCoupon(coupon)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                                <!-- Normal Actions -->
                                <div v-else class="action-buttons justify-content-end">
                                    <button class="btn-action edit" title="Chỉnh sửa" @click="openEditModal(coupon)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="btn-action delete" title="Chuyển vào thùng rác" @click="confirmDeleteCouponPrompt(coupon)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vue Modal cho Tạo/Sửa Mã -->
        <Transition name="modal">
            <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box ocean-card">
                    <div class="modal-head">
                        <h3>{{ isEditing ? 'Chỉnh sửa mã giảm giá' : 'Thêm mã giảm giá mới' }}</h3>
                        <button class="btn-close" @click="closeModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="handleSubmit" novalidate class="modal-body">
                        <div v-if="formError" class="alert alert-danger mb-3 py-2 px-3 small">{{ formError }}</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Mã Voucher <span class="required">*</span></label>
                                <input
                                    type="text"
                                    v-model="form.code"
                                    class="form-control"
                                    :class="{'is-invalid': errors.code}"
                                    placeholder="VD: FREESHIP100"
                                    style="text-transform: uppercase;"
                                />
                                <div class="invalid-feedback" v-if="errors.code">{{ errors.code }}</div>
                            </div>
                            <div class="form-group">
                                <label>Loại giảm giá <span class="required">*</span></label>
                                <select v-model="form.type" class="form-control form-select">
                                    <option value="fixed">Số tiền cố định (VNĐ)</option>
                                    <option value="percent">Phần trăm (%)</option>
                                    <option value="free_ship">Miễn phí vận chuyển (VNĐ)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Mức giảm <span class="required">*</span></label>
                                <input
                                    type="number"
                                    v-model="form.value"
                                    class="form-control"
                                    :class="{'is-invalid': errors.value}"
                                    :placeholder="form.type === 'percent' ? 'VD: 15 (nghĩa là 15%)' : 'VD: 50000'"
                                />
                                <div class="invalid-feedback" v-if="errors.value">{{ errors.value }}</div>
                            </div>
                            <div class="form-group" v-if="form.type === 'percent'">
                                <label>Giảm tối đa (VNĐ)</label>
                                <input
                                    type="number"
                                    v-model="form.max_discount_value"
                                    class="form-control"
                                    placeholder="Bỏ trống nếu không giới hạn"
                                />
                            </div>
                            <div class="form-group" v-else>
                                <label>Đơn tối thiểu (VNĐ)</label>
                                <input
                                    type="number"
                                    v-model="form.min_order_value"
                                    class="form-control"
                                    placeholder="VD: 200000"
                                />
                            </div>
                        </div>

                        <div class="form-row" v-if="form.type === 'percent'">
                            <div class="form-group">
                                <label>Đơn tối thiểu (VNĐ)</label>
                                <input
                                    type="number"
                                    v-model="form.min_order_value"
                                    class="form-control"
                                    placeholder="VD: 200000"
                                />
                            </div>
                            <div class="form-group">
                                <label>Tổng lượt dùng tối đa</label>
                                <input
                                    type="number"
                                    v-model="form.usage_limit"
                                    class="form-control"
                                    placeholder="Bỏ trống = không giới hạn"
                                />
                            </div>
                        </div>
                        <div class="form-row" v-else>
                            <div class="form-group">
                                <label>Tổng lượt dùng tối đa</label>
                                <input
                                    type="number"
                                    v-model="form.usage_limit"
                                    class="form-control"
                                    placeholder="Bỏ trống = không giới hạn"
                                />
                            </div>
                            <div class="form-group">
                                <label>Mỗi User dùng tối đa (lần)</label>
                                <input
                                    type="number"
                                    v-model="form.user_usage_limit"
                                    class="form-control"
                                    placeholder="1"
                                    min="1"
                                />
                            </div>
                        </div>

                        <div class="form-row" v-if="form.type === 'percent'">
                            <div class="form-group">
                                <label>Mỗi User dùng tối đa (lần)</label>
                                <input
                                    type="number"
                                    v-model="form.user_usage_limit"
                                    class="form-control"
                                    placeholder="1"
                                    min="1"
                                />
                            </div>
                            <div class="form-group"></div>
                        </div>

                        <!-- Danh mục áp dụng -->
                        <div class="form-group position-relative">
                            <label>Danh mục áp dụng (bỏ trống = áp dụng tất cả)</label>
                            <div class="cat-dropdown-trigger" @click="isCatDropdownOpen = !isCatDropdownOpen">
                                <span class="cat-dropdown-text">
                                    {{ form.category_ids.length ? `Đã chọn ${form.category_ids.length} danh mục` : 'Tất cả danh mục' }}
                                </span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :style="{ transform: isCatDropdownOpen ? 'rotate(180deg)' : 'rotate(0)' }">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                            <div v-if="isCatDropdownOpen" class="cat-dropdown-menu">
                                <template v-for="cat in allCategories" :key="cat.category_id">
                                    <label class="cat-dropdown-item parent">
                                        <input
                                            type="checkbox"
                                            :checked="form.category_ids.includes(cat.category_id)"
                                            @change="toggleCategory(cat.category_id)"
                                        />
                                        <span>{{ cat.name }}</span>
                                    </label>
                                    <template v-if="cat.children && cat.children.length">
                                        <label v-for="child in cat.children" :key="child.category_id" class="cat-dropdown-item child">
                                            <input
                                                type="checkbox"
                                                :checked="form.category_ids.includes(child.category_id)"
                                                @change="toggleCategory(child.category_id)"
                                            />
                                            <span>{{ child.name }}</span>
                                        </label>
                                    </template>
                                </template>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Ngày bắt đầu <span class="required">*</span></label>
                                <input
                                    type="datetime-local"
                                    v-model="form.start_date"
                                    class="form-control"
                                    :class="{'is-invalid': errors.start_date}"
                                />
                                <div class="invalid-feedback" v-if="errors.start_date">{{ errors.start_date }}</div>
                            </div>
                            <div class="form-group">
                                <label>Ngày kết thúc <span class="required">*</span></label>
                                <input
                                    type="datetime-local"
                                    v-model="form.end_date"
                                    class="form-control"
                                    :class="{'is-invalid': errors.end_date}"
                                />
                                <div class="invalid-feedback" v-if="errors.end_date">{{ errors.end_date }}</div>
                            </div>
                        </div>

                        <!-- Checkbox options -->
                        <div class="option-section">
                            <label class="option-checkbox main-option">
                                <input type="checkbox" v-model="form.is_active" />
                                <span class="checkmark"></span>
                                <span class="option-label">Kích hoạt mã ngay</span>
                            </label>
                            <label class="option-checkbox">
                                <input type="checkbox" v-model="form.is_public" />
                                <span class="checkmark"></span>
                                <span class="option-label">Hiển thị công khai trong ví Voucher</span>
                            </label>
                            <label class="option-checkbox">
                                <input type="checkbox" v-model="form.is_first_order" />
                                <span class="checkmark"></span>
                                <span class="option-label">Chỉ áp dụng cho đơn hàng đầu tiên</span>
                            </label>
                            <label class="option-checkbox" v-if="!isEditing">
                                <input type="checkbox" v-model="form.send_email" />
                                <span class="checkmark"></span>
                                <span class="option-label">Gửi email thông báo cho tất cả khách hàng</span>
                            </label>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-outline" @click="closeModal">Hủy bỏ</button>
                            <button type="submit" class="btn-primary" :disabled="isSubmitting">
                                {{ isSubmitting ? 'Đang lưu...' : (isEditing ? 'Cập nhật' : 'Tạo mã ngay') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Usages Modal -->
        <Transition name="modal">
            <div v-if="isUsagesModalOpen" class="modal-overlay" @click.self="closeUsagesModal">
                <div class="modal-box ocean-card" style="max-width: 580px;">
                    <div class="modal-head">
                        <h3>Lượt sử dụng mã: {{ usagesData?.coupon?.code }}</h3>
                        <button class="btn-close" @click="closeUsagesModal">×</button>
                    </div>
                    <div class="modal-body">
                        <div v-if="isLoadingUsages" class="text-center py-4 text-muted">Đang tải...</div>
                        <div v-else-if="usagesData">
                            <div class="d-flex gap-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-num">{{ usagesData.total_saved }}</div>
                                    <div class="stat-label">Đã lưu mã</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-num text-success">{{ usagesData.total_used }}</div>
                                    <div class="stat-label">Đã sử dụng</div>
                                </div>
                            </div>
                            <div v-if="!usagesData.usages || usagesData.usages.length === 0" class="text-center py-3 text-muted">Chưa có ai lưu hoặc dùng mã này.</div>
                            <div v-else class="list-group">
                                <div v-for="u in usagesData.usages" :key="u.user_id" class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                    <div>
                                        <div class="fw-bold">{{ u.full_name }}</div>
                                        <small class="text-muted">{{ u.email }} | {{ u.phone || '—' }}</small>
                                    </div>
                                    <span class="usage-count-badge">{{ u.used_count }} lần</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.category-page { font-family: var(--font-inter); padding-bottom: 2rem; }

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px;
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
    background: linear-gradient(135deg, #e63b6f 0%, #ff6b8b 100%);
    color: white; font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(230, 59, 111, 0.3);
}

.btn-outline {
    padding: 10px 22px; border-radius: 8px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #ffffff); color: var(--text-muted, #64748b);
    font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
}
.btn-outline:hover { border-color: #e63b6f; color: #e63b6f; }

/* Coupon Tabs Bar */
.coupon-tabs-bar {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 16px; overflow-x: auto; padding-bottom: 4px;
}
.tab-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 9999px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #ffffff); color: var(--text-muted, #64748b);
    font-size: 0.84rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease;
    white-space: nowrap;
}
.tab-pill:hover {
    background: var(--hover-bg, #f8fafc); color: var(--text-main, #0f172a); border-color: #cbd5e1;
}
.tab-pill.active {
    background: #fff0f5; color: #e63b6f; border-color: #fbcfe8; font-weight: 700;
    box-shadow: 0 2px 6px rgba(230, 59, 111, 0.12);
}
.tab-pill.tab-trash.active {
    background: #fef2f2; color: #dc2626; border-color: #fecaca;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.12);
}

.tab-badge {
    font-size: 0.72rem; font-weight: 700; padding: 1px 7px;
    border-radius: 9999px; background: #f1f5f9; color: #475569;
}
.tab-pill.active .tab-badge { background: #e63b6f; color: #ffffff; }
.tab-pill.tab-trash.active .tab-badge { background: #dc2626; color: #ffffff; }

.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.dot-active { background: #10b981; }
.dot-expired { background: #ef4444; }
.dot-inactive { background: #94a3b8; }

/* Filters Bar */
.filters-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; margin-bottom: 16px; gap: 16px;
}
.search-box {
    display: flex; align-items: center; gap: 10px;
    background: var(--ocean-deepest, #f8fafc); border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 8px; padding: 9px 14px; flex: 1; max-width: 400px;
}
.search-box:focus-within {
    border-color: #e63b6f; background: #ffffff;
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}
.search-box svg { color: var(--text-light, #94a3b8); }
.search-input {
    background: none; border: none; outline: none;
    color: var(--text-main, #0f172a); font-size: 0.88rem; width: 100%;
}
.filter-stats { display: flex; gap: 8px; flex-shrink: 0; }
.stat-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-color, #e2e8f0);
    background: var(--ocean-deepest, #f8fafc); color: var(--text-muted, #64748b);
    font-size: 0.8rem; font-weight: 600;
}
.stat-pill svg { color: #e63b6f; }

/* Floating Bulk Action Bar */
.bulk-action-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px; background: #1e293b; color: #ffffff;
    border-radius: 12px; margin-bottom: 16px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    animation: slideDown 0.25s ease;
}
.bulk-action-info { display: flex; align-items: center; gap: 10px; font-size: 0.88rem; }
.bulk-action-btns { display: flex; align-items: center; gap: 8px; }

.btn-bulk-restore {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px; border: none;
    background: #10b981; color: #ffffff; font-size: 0.82rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s ease;
}
.btn-bulk-restore:hover { background: #059669; }

.btn-bulk-force {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px; border: none;
    background: #dc2626; color: #ffffff; font-size: 0.82rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s ease;
}
.btn-bulk-force:hover { background: #b91c1c; }

.btn-bulk-trash {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px; border: none;
    background: #e11d48; color: #ffffff; font-size: 0.82rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s ease;
}
.btn-bulk-trash:hover { background: #be123c; }

.btn-bulk-cancel {
    padding: 7px 14px; border-radius: 8px; border: 1px solid #475569;
    background: transparent; color: #cbd5e1; font-size: 0.82rem; cursor: pointer;
}
.btn-bulk-cancel:hover { background: #334155; color: #ffffff; }

.select-checkbox { width: 16px; height: 16px; cursor: pointer; }

/* Table */
.table-header { padding: 14px 20px; border-bottom: 1px solid var(--border-color, #e2e8f0); }
.table-count { font-size: 0.85rem; color: var(--text-muted, #64748b); font-weight: 500; }
.table-count strong { color: var(--text-main, #0f172a); font-weight: 800; }

.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 12px 16px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted, #64748b); text-transform: uppercase; letter-spacing: 0.8px;
    border-bottom: 1px solid var(--border-color, #e2e8f0); background: var(--ocean-deepest, #f8fafc);
    white-space: nowrap;
}
.data-table td {
    padding: 12px 16px; border-bottom: 1px solid var(--border-color, #e2e8f0);
    transition: background 0.15s; vertical-align: middle;
}
.data-table tbody tr:hover td { background: var(--hover-bg, #f8fafc); }
.row-selected td { background: #fff0f5 !important; }

.code-text { font-family: monospace; font-weight: 800; font-size: 0.95rem; color: #e63b6f; letter-spacing: 0.5px; }
.private-code { color: #7b1fa2; }
.type-badge { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }
.value-text { font-size: 0.95rem; color: var(--text-main); }
.max-discount-text { font-size: 0.75rem; color: var(--text-muted); }
.condition-info { font-size: 0.8rem; color: var(--text-muted); }
.badge-first-order { font-size: 0.65rem; background: #fff3e0; color: #e65100; padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 2px; }
.badge-user-limit { font-size: 0.65rem; background: #f3e5f5; color: #7b1fa2; padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 2px; margin-left: 2px; }

.usage-info { font-weight: 600; color: var(--text-main); }
.date-cell div { color: var(--text-muted); font-size: 0.8rem; white-space: nowrap; margin-bottom: 2px; }
.date-cell small { color: #9fb3c8; display: inline-block; width: 55px;}
.expired { color: #d32f2f; font-weight: 600;}

.status-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
.status-badge.active { background: #e8f5e9; color: #2e7d32; }
.status-badge.inactive { background: #ffebee; color: #c62828; }

.action-buttons { display: flex; gap: 6px; align-items: center; }
.btn-action {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0);
    background: var(--ocean-deepest, #f8fafc); color: var(--text-muted, #64748b);
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.btn-action.edit:hover { color: #0284c7; border-color: #0284c7; background: rgba(2, 132, 199, 0.08); }
.btn-action.delete:hover { color: #e11d48; border-color: #e11d48; background: rgba(225, 29, 72, 0.08); }

.btn-action-restore {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 6px; border: 1px solid #a7f3d0;
    background: #ecfdf5; color: #059669; font-size: 0.75rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s ease;
}
.btn-action-restore:hover { background: #10b981; color: #ffffff; border-color: #10b981; }

.empty-cell { text-align: center; padding: 60px 20px !important; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-cell h3 { font-size: 1rem; color: #64748b; margin: 0; }

/* Modal styles */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal-box {
    width: 100%; max-width: 650px; padding: 0;
    max-height: 90vh; overflow-y: auto;
    border-radius: 16px; background: var(--card-bg, #ffffff);
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}
.modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid var(--border-color, #e2e8f0);
}
.modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #0f172a); margin: 0; }
.btn-close {
    background: none; border: none; cursor: pointer; margin: 0;
    color: var(--text-muted, #64748b); display: flex; align-items: center; justify-content: center;
    padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.btn-close:hover { color: #dc2626; }
.modal-body { padding: 24px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--border-color, #e2e8f0); }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main, #0f172a); margin-bottom: 8px; }
.required { color: #dc2626; }
.form-control {
    width: 100%; padding: 10px 14px; border-radius: 8px;
    border: 1px solid var(--border-color, #e2e8f0); background: var(--ocean-deepest, #f8fafc);
    color: var(--text-main, #0f172a); font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.form-control:focus { border-color: #e63b6f; outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); background: #ffffff; }
.form-select {
    appearance: none; cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.cat-badges { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 4px; }
.badge-category {
    display: inline-block; font-size: 0.6rem; font-weight: 600;
    padding: 1px 6px; border-radius: 4px; background: #e3f2fd; color: #1565c0;
}
.badge-category.all { background: #f3e5f5; color: #7b1fa2; }

.cat-dropdown-trigger {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; border-radius: 8px;
    border: 1px solid var(--border-color, #e2e8f0); background: var(--ocean-deepest, #f8fafc);
    cursor: pointer; transition: all 0.2s; font-size: 0.85rem; color: var(--text-main, #0f172a);
}
.cat-dropdown-trigger:hover { border-color: #e63b6f; }
.cat-dropdown-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }

.cat-dropdown-menu {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 50;
    background: var(--card-bg, #ffffff); border: 1px solid #e0e6ed;
    border-radius: 10px; box-shadow: 0 12px 36px rgba(0,0,0,0.12);
    max-height: 280px; overflow-y: auto; margin-top: 6px; padding: 6px 0;
}
.cat-dropdown-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px; cursor: pointer; font-size: 0.84rem; font-weight: 500;
    color: #3d4f5f; transition: all 0.15s; margin: 2px 6px; border-radius: 6px;
}
.cat-dropdown-item:hover { background: #fff0f5; color: #e63b6f; }
.cat-dropdown-item.parent { font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; }
.cat-dropdown-item.child { padding-left: 28px; font-size: 0.8rem; color: #64748b; }

.btn-usage {
    display: inline-flex; align-items: center; gap: 4px;
    margin-top: 4px; padding: 2px 8px; border-radius: 12px;
    border: 1px solid #e3f2fd; background: #e3f2fd; color: #1565c0;
    font-size: 0.68rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
}
.btn-usage:hover { background: #bbdefb; border-color: #90caf9; }

.stat-card {
    flex: 1; text-align: center; padding: 12px; border-radius: 10px;
    background: var(--ocean-deepest, #f8fafc); border: 1px solid var(--border-color, #e2e8f0);
}
.stat-num { font-size: 1.3rem; font-weight: 800; color: var(--text-main, #0f172a); }
.stat-label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted, #64748b); margin-top: 2px; }

.usage-count-badge {
    display: inline-block; min-width: 24px; padding: 2px 8px;
    background: #fff3e0; color: #e65100; font-weight: 800; font-size: 0.8rem; border-radius: 12px;
}

.option-section { padding: 12px 0; border-bottom: 1px solid var(--border-color, #e2e8f0); margin-bottom: 12px; }
.option-checkbox {
    display: flex; align-items: center; gap: 10px;
    cursor: pointer; padding: 6px 0; margin: 0; font-size: 0.86rem; color: var(--text-main, #0f172a);
}
.option-checkbox input[type="checkbox"] { display: none; }
.option-checkbox .checkmark {
    width: 18px; height: 18px; border: 2px solid #cbd5e1; border-radius: 4px;
    flex-shrink: 0; position: relative; transition: all 0.2s; background: var(--ocean-deepest, #f8fafc);
}
.option-checkbox input:checked + .checkmark { background: #e63b6f; border-color: #e63b6f; }
.option-checkbox input:checked + .checkmark::after {
    content: ''; position: absolute; left: 5px; top: 1px;
    width: 5px; height: 10px; border: solid #fff;
    border-width: 0 2px 2px 0; transform: rotate(45deg);
}
.option-checkbox.main-option { font-weight: 700; font-size: 0.9rem; }

.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(0.95) translateY(10px); }

.animate-in { animation: fadeSlideUp 0.35s ease both; }
@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    .page-header button { width: 100%; justify-content: center; }
    .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
    .search-box { max-width: 100%; }
    .bulk-action-bar { flex-direction: column; gap: 10px; align-items: stretch; }
    .bulk-action-btns { justify-content: flex-end; }
    .modal-box { width: 96%; }
    .form-row { grid-template-columns: 1fr; gap: 8px; }
}
</style>
