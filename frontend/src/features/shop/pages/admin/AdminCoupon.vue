<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import api from '@/axios';
import { Toast, Modal } from 'bootstrap';

const coupons = ref([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const isEditing = ref(false);
const searchQuery = ref('');

// === Danh mục ===
const allCategories = ref([]);

// === Usages modal ===
const isUsagesModalOpen = ref(false);
const usagesData = ref(null);
const isLoadingUsages = ref(false);

const defaultForm = () => ({
    id: null,
    code: '',
    type: 'fixed',
    value: '',
    max_discount_value: '',
    min_order_value: '',
    usage_limit: '',
    user_usage_limit: 1,
    is_public: 1,
    is_first_order: 0,
    start_date: '',
    end_date: '',
    is_active: 1,
    category_ids: [],
    send_email: false,
});

const form = ref(defaultForm());

const toast = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('couponToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const fetchCoupons = async () => {
    try {
        isLoading.value = true;
        const response = await api.get('/admin/coupons');
        if (response.data.status === 'success') {
            coupons.value = response.data.data;
        }
    } catch (error) {
        showToast('Lỗi khi tải mã giảm giá!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

const filteredCoupons = computed(() => {
    let list = coupons.value;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c => c.code.toLowerCase().includes(q));
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
        user_usage_limit: coupon.user_usage_limit !== undefined ? coupon.user_usage_limit : 1,
        is_public: coupon.is_public !== undefined ? coupon.is_public : 1,
        is_first_order: coupon.is_first_order || 0,
        start_date: coupon.start_date ? new Date(coupon.start_date).toISOString().slice(0, 16) : '',
        end_date: coupon.end_date ? new Date(coupon.end_date).toISOString().slice(0, 16) : '',
        is_active: coupon.is_active,
        category_ids: coupon.category_ids || [],
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

    let payload = { ...form.value };
    
    // Ép kiểu các trường nullable nếu là chuỗi rỗng
    if (payload.max_discount_value === '') payload.max_discount_value = null;
    if (payload.min_order_value === '') payload.min_order_value = null;
    if (payload.usage_limit === '') payload.usage_limit = null;
    if (payload.start_date === '') payload.start_date = null;
    if (payload.end_date === '') payload.end_date = null;
    
    // Giảm tối đa chỉ có ý nghĩa nếu type = percent
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
        closeModal();
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            for (const key in backendErrors) {
                errors.value[key] = backendErrors[key][0];
            }
            // formError.value = error.response.data.message || 'Vui lòng kiểm tra lại các trường nhập liệu!';
        } else {
            formError.value = error.response?.data?.message || (isEditing.value ? 'Cập nhật thất bại!' : 'Tạo mã mới thất bại!');
        }
    } finally {
        isSubmitting.value = false;
    }
};

import Swal from 'sweetalert2';

const confirmDeleteCouponPrompt = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa mã giảm giá?',
        text: 'Bạn có chắc chắn muốn xóa (xóa mềm) mã giảm giá này không? Lịch sử giao dịch cũ sẽ không bị ảnh hưởng.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/admin/coupons/${id}`);
            showToast(res.data.message || 'Đã xóa mã giảm giá!', 'success');
            await fetchCoupons();
        } catch (error) {
            showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
        }
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

// Lấy tên category theo ID (duyệt cả children)
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

const selectedCategoryNames = computed(() => {
    if (!form.value.category_ids.length) return 'Tất cả danh mục';
    return form.value.category_ids.map(id => getCategoryName(id)).filter(Boolean).join(', ');
});
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
                <p class="page-subtitle">Quản lý các loại mã giảm giá, Freeship, Flash Sale</p>
            </div>
            <button @click="openCreateModal" class="btn-primary" id="add-coupon-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tạo mã mới
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
                    {{ coupons.length }} mã tổng
                </span>
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ coupons.filter(c => c.is_active && !isExpired(c.end_date)).length }} đang chạy
                </span>
            </div>
        </div>

        <!-- Coupon Table -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredCoupons.length }}</strong> mã giảm giá được tìm thấy
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã code / Loại</th>
                            <th>Mức giảm</th>
                            <th>Điều kiện / Giới hạn</th>
                            <th>Lượt xài</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="7" class="loading-state">
                                <div class="spinner"></div>
                                Đang tải dữ liệu...
                            </td>
                        </tr>
                        <tr v-else-if="filteredCoupons.length === 0">
                            <td colspan="7" class="empty-cell" style="text-align:center; padding: 40px; color:#9fb3c8;">Không có mã giảm giá nào.</td>
                        </tr>
                        <tr v-for="coupon in filteredCoupons" :key="coupon.id">
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
                                    <span v-if="coupon.is_first_order" class="badge-first-order" title="Chỉ áp dụng đơn đầu tiên">Chỉ mở bát</span>
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
                                <div><small>Từ:</small> {{ formatDate(coupon.start_date) || '-' }}</div>
                                <div><small>Đến:</small> <span :class="{'expired': isExpired(coupon.end_date)}">{{ formatDate(coupon.end_date) || '-' }}</span></div>
                            </td>
                            <td>
                                <span :class="['status-badge', (coupon.is_active && !isExpired(coupon.end_date)) ? 'active' : 'inactive']">
                                    {{ isExpired(coupon.end_date) ? 'Hết hạn' : (coupon.is_active ? 'Kích hoạt' : 'Tạm khóa') }}
                                </span>
                            </td>
                            <td>
                                <button class="btn-action edit" title="Chỉnh sửa" @click="openEditModal(coupon)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="btn-action delete" title="Xóa" @click="confirmDeleteCouponPrompt(coupon.id)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!isLoading && filteredCoupons.length === 0" class="empty-state">
                <span class="empty-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></span>
                <h3>Không tìm thấy mã giảm giá</h3>
                <p>{{ searchQuery ? 'Thử từ khóa khác.' : 'Bắt đầu bằng cách bấm Tạo mã mới.' }}</p>
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
                        
                        <!-- Cụm Code -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Mã Code <span class="required">*</span></label>
                                <input v-model="form.code" type="text" placeholder="VD: SALE50K" class="form-control" :class="{'is-invalid': errors.code}" style="text-transform: uppercase;" />
                                <span v-if="errors.code" class="field-error">{{ errors.code }}</span>
                            </div>
                        </div>

                        <!-- Cụm Loại & Giá trị -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Áp dụng loại giảm <span class="required">*</span></label>
                                <select v-model="form.type" class="form-control form-select">
                                    <option value="fixed">Giảm cố định (VNĐ)</option>
                                    <option value="percent">Giảm phần trăm (%)</option>
                                    <option value="free_ship">Miễn phí ship (VNĐ)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Mức giảm <span class="required">*</span></label>
                                <input v-model.number="form.value" type="number" min="0" class="form-control" :class="{'is-invalid': errors.value}" placeholder="0" />
                                <span v-if="errors.value" class="field-error">{{ errors.value }}</span>
                            </div>
                        </div>

                        <!-- Cặp giảm tối đa (nếu là percent) -->
                        <div class="form-row" v-if="form.type === 'percent'">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Mức GIẢM TỐI ĐA cho phép (VNĐ)</label>
                                <input v-model.number="form.max_discount_value" type="number" min="0" class="form-control" placeholder="Để trống nếu không giới hạn trần" />
                            </div>
                        </div>

                        <!-- Cụm Điều kiện giá & User -->
                        <div class="form-row" style="margin-top: 8px;">
                            <div class="form-group">
                                <label>Đơn tối thiểu áp dụng (VNĐ)</label>
                                <input v-model.number="form.min_order_value" type="number" min="0" class="form-control" placeholder="Không bắt buộc" />
                            </div>
                            <div class="form-group">
                                <label>Số lượt được Dùng / 1 User</label>
                                <input v-model.number="form.user_usage_limit" type="number" min="1" class="form-control" placeholder="Mặc định: 1" />
                            </div>
                        </div>

                        <!-- Cụm Kho -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tổng số mã phát ra (Kho)</label>
                                <input v-model.number="form.usage_limit" type="number" min="1" class="form-control" placeholder="Để trống = vô hạn" />
                            </div>
                        </div>

                        <!-- Cụm Ngày -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ngày bắt đầu <span class="required">*</span></label>
                                <input v-model="form.start_date" type="datetime-local" class="form-control" :class="{'is-invalid': errors.start_date}" />
                                <span v-if="errors.start_date" class="field-error">{{ errors.start_date }}</span>
                            </div>
                            <div class="form-group">
                                <label>Ngày kết thúc <span class="required">*</span></label>
                                <input v-model="form.end_date" type="datetime-local" class="form-control" :class="{'is-invalid': errors.end_date}" />
                                <span v-if="errors.end_date" class="field-error">{{ errors.end_date }}</span>
                            </div>
                        </div>

                        <!-- Cụm Danh mục áp dụng (Dropdown tree-view) -->
                        <div class="form-group" style="margin-bottom: 16px; position: relative;">
                            <label>Áp dụng cho Danh mục <span style="color:#9fb3c8;font-weight:400;font-size:0.75rem">(bỏ trống = tất cả)</span></label>
                            <div class="cat-dropdown-trigger" @click="isCatDropdownOpen = !isCatDropdownOpen">
                                <span class="cat-dropdown-text">{{ selectedCategoryNames }}</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" :style="{transform: isCatDropdownOpen ? 'rotate(180deg)' : '', transition: '0.2s'}"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                            <!-- Selected tags -->
                            <div v-if="form.category_ids.length" class="cat-selected-tags">
                                <span v-for="catId in form.category_ids" :key="catId" class="cat-tag">
                                    {{ getCategoryName(catId) }}
                                    <svg @click.stop="toggleCategory(catId)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="cursor:pointer;margin-left:4px;vertical-align:middle"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </span>
                            </div>
                            <!-- Dropdown menu -->
                            <div v-if="isCatDropdownOpen" class="cat-dropdown-menu">
                                <div v-if="allCategories.length === 0" style="padding:12px;color:#9fb3c8;font-size:0.8rem">Đang tải...</div>
                                <template v-for="parent in allCategories" :key="parent.category_id">
                                    <label class="cat-dropdown-item parent">
                                        <input type="checkbox" :checked="form.category_ids.includes(parent.category_id)" @change="toggleCategory(parent.category_id)" />
                                        <span>{{ parent.name }}</span>
                                    </label>
                                    <template v-if="parent.children && parent.children.length">
                                        <label v-for="child in parent.children" :key="child.category_id" class="cat-dropdown-item child">
                                            <input type="checkbox" :checked="form.category_ids.includes(child.category_id)" @change="toggleCategory(child.category_id)" />
                                            <span>{{ child.name }}</span>
                                        </label>
                                    </template>
                                </template>
                            </div>
                        </div>

                        <!-- ✅ Kích hoạt ngay -->
                        <div class="option-section">
                            <label class="option-checkbox main-option">
                                <input type="checkbox" v-model="form.is_active" :true-value="1" :false-value="0" />
                                <span class="checkmark"></span>
                                <span class="option-label">Kích hoạt ngay</span>
                            </label>
                        </div>

                        <!-- TÙY CHỌN NÂNG CAO -->
                        <div class="advanced-section">
                            <div class="advanced-title">TÙY CHỌN NÂNG CAO</div>

                            <label class="option-checkbox">
                                <input type="checkbox" v-model="form.is_public" :true-value="1" :false-value="0" />
                                <span class="checkmark"></span>
                                <svg class="option-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e65100" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                                <span class="option-label">Công khai trên Săn Voucher</span>
                            </label>

                            <!-- Gửi email (indented, highlighted) -->
                            <div v-if="!isEditing" class="email-option-wrap">
                                <label class="option-checkbox">
                                    <input type="checkbox" v-model="form.send_email" :true-value="true" :false-value="false" />
                                    <span class="checkmark"></span>
                                    <svg class="option-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1565c0" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <span class="option-label" style="color:#1565c0; font-weight:600;">Gửi email thông báo cho tất cả người dùng ngay lập tức</span>
                                </label>
                            </div>

                            <label class="option-checkbox">
                                <input type="checkbox" v-model="form.is_first_order" :true-value="1" :false-value="0" />
                                <span class="checkmark"></span>
                                <svg class="option-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d84315" stroke-width="2.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                <span class="option-label">Chỉ cho đơn đầu tiên</span>
                            </label>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" @click="closeModal" class="btn-outline">Hủy bỏ</button>
                            <button type="submit" class="btn-primary" :disabled="isSubmitting">
                                <span v-if="isSubmitting">Đang lưu...</span>
                                <span v-else>{{ isEditing ? 'Lưu cập nhật' : 'Tạo mã mới' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>



        <!-- Vue Modal: Xem lượt dùng coupon -->
        <Transition name="modal">
            <div v-if="isUsagesModalOpen" class="modal-overlay" @click.self="closeUsagesModal">
                <div class="modal-box ocean-card" style="max-width: 600px;">
                    <div class="modal-head">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" style="vertical-align:middle;margin-right:6px"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                            Lượt dùng: <strong style="color:#d84315">{{ usagesData?.coupon?.code }}</strong>
                        </h3>
                        <button class="btn-close" @click="closeUsagesModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div style="padding: 20px 24px;">
                        <!-- Stats -->
                        <div v-if="usagesData" style="display:flex; gap:12px; margin-bottom:16px;">
                            <div class="stat-card">
                                <div class="stat-num">{{ usagesData.total_saved }}</div>
                                <div class="stat-label">Đã lưu</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-num" style="color:#2e7d32">{{ usagesData.total_used }}</div>
                                <div class="stat-label">Đã dùng</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-num" style="color:#d84315">{{ usagesData.coupon?.used_count || 0 }} / {{ usagesData.coupon?.usage_limit || '∞' }}</div>
                                <div class="stat-label">Tổng lượt xài</div>
                            </div>
                        </div>

                        <!-- Loading -->
                        <div v-if="isLoadingUsages" class="loading-state" style="padding:40px"><div class="spinner"></div>Đang tải...</div>
                        
                        <!-- Table -->
                        <div v-else-if="usagesData && usagesData.usages.length" style="max-height:350px; overflow-y:auto;">
                            <table class="data-table" style="font-size:0.85rem">
                                <thead>
                                    <tr>
                                        <th>Khách hàng</th>
                                        <th>Email</th>
                                        <th>Số lần dùng</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="u in usagesData.usages" :key="u.user_id">
                                        <td><strong>{{ u.full_name }}</strong></td>
                                        <td style="color:var(--text-muted); font-size:0.8rem">{{ u.email }}</td>
                                        <td><span class="usage-count-badge">{{ u.used_count }}</span></td>
                                        <td>
                                            <span v-if="u.used_count > 0" class="status-badge active" style="font-size:0.6rem">Đã dùng</span>
                                            <span v-else class="status-badge inactive" style="font-size:0.6rem">Chỉ lưu</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty -->
                        <div v-else-if="usagesData" style="text-align:center; padding:40px; color:#9fb3c8">
                            <p>Chưa có ai lưu hoặc dùng mã này.</p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Bootstrap Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
            <div class="toast align-items-center border-0 border-start border-4" :class="toast.type === 'success' ? 'text-bg-white border-success' : 'text-bg-white border-danger'" id="couponToast" role="alert">
                <div class="d-flex align-items-center p-2">
                    <svg v-if="toast.type === 'success'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="2.5" stroke-linecap="round" class="mx-2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c62828" stroke-width="2.5" stroke-linecap="round" class="mx-2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    
                    <div class="toast-body fw-bold text-dark fs-6">{{ toast.message }}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Validation Styles */
.field-error {
    color: #e53935;
    font-size: 0.8rem;
    margin-top: 4px;
    display: block;
}
.is-invalid {
    border-color: #e53935 !important;
    background-color: #fff2f2 !important;
}
.form-error-box {
    background-color: #fff2f2;
    border: 1px solid #e53935;
    color: #c62828;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}
.category-page { font-family: var(--font-inter); }

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
    background: var(--primary); color: white;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover {
    background: var(--ocean-bright); transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(3, 169, 244, 0.3);
}
.btn-primary:disabled { opacity: 0.7; transform: none; cursor: not-allowed; }

.btn-outline {
    padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.btn-outline:hover { border-color: var(--text-light); }

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
    border-color: var(--primary); background: var(--card-bg);
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
.stat-pill svg { color: var(--primary); }

/* Loading & Empty */
.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); font-weight: 600; }
.spinner {
    width: 30px; height: 30px; border: 3px solid var(--border-color);
    border-top-color: var(--primary); border-radius: 50%;
    animation: spin 1s linear infinite; margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Table */
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); }
.table-count { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
.table-count strong { color: var(--text-main); font-weight: 800; }

.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 14px 24px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color);
    background: var(--ocean-deepest);
}
.data-table :deep(td) {
    padding: 14px 24px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s; vertical-align: middle;
}
.data-table :deep(tbody tr:hover td) { background: var(--hover-bg); }

/* Elements Table Specific */
.code-text { display: block; font-weight: 800; color: #d84315; font-size: 0.95rem; font-family: monospace; letter-spacing: 0.5px; margin-bottom: 4px;}
.private-code { color: #546e7a; }
.type-badge { display: inline-block; font-size: 0.65rem; background: #eceff1; color: #546e7a; padding: 2px 6px; border-radius: 4px; font-weight: 600; }
.value-text { font-size: 1rem; color: var(--seafoam, #128176); }
.max-discount-text { font-size: 0.75rem; color: #d84315; font-weight: 600; margin-top: 2px; }

.condition-info { font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; }
.badge-first-order { display: inline-block; background: #fff3e0; color: #e65100; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-top: 4px; }
.badge-user-limit { display: inline-block; background: #e3f2fd; color: #0d47a1; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-top: 4px; margin-left: 4px;}

.usage-info { font-weight: 600; color: var(--text-main); }
.date-cell div { color: var(--text-muted); font-size: 0.8rem; white-space: nowrap; margin-bottom: 2px; }
.date-cell small { color: #9fb3c8; display: inline-block; width: 55px;}
.expired { color: #d32f2f; font-weight: 600;}

.status-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
.status-badge.active { background: #e8f5e9; color: #2e7d32; }
.status-badge.inactive { background: #ffebee; color: #c62828; }

.btn-action {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 6px; border: none;
    background: transparent; color: var(--text-muted);
    cursor: pointer; transition: all 0.2s; margin-right: 4px;
}
.btn-action.edit:hover { background: #e3f2fd; color: #1565c0; }
.btn-action.delete:hover { background: #ffebee; color: #c62828; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.empty-state p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }

/* Vue Modal for Form */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal-box {
    width: 100%; max-width: 650px; padding: 0;
    max-height: 90vh; overflow-y: auto;
    border-radius: 16px; background: var(--card-bg);
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}
.modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid var(--border-color);
}
.modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.btn-close {
    background: none; border: none; cursor: pointer; margin: 0;
    color: var(--text-muted); display: flex; align-items: center; justify-content: center;
    padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.btn-close:hover { background: var(--hover-bg); color: var(--coral); }
.modal-body { padding: 24px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--border-color); }

/* Form Control */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.required { color: var(--coral); }
.form-control {
    width: 100%; padding: 10px 14px; border-radius: 8px;
    border: 1px solid var(--border-color); background: var(--ocean-deepest);
    color: var(--text-main); font-family: var(--font-inter);
    font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.form-select {
    appearance: none; cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Toggle Slider */
.toggle-wrap { margin-top: 4px; }
.toggle-switch-wrapper { display: flex; align-items: center; gap: 12px; cursor: pointer; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--text-light); transition: .3s; border-radius: 24px;
}
.toggle-slider:before {
    position: absolute; content: ""; height: 18px; width: 18px;
    left: 3px; bottom: 3px; background-color: var(--card-bg); transition: .3s; border-radius: 50%;
}
.toggle-input:checked + .toggle-slider { background-color: var(--primary); }
.toggle-input:checked + .toggle-slider:before { transform: translateX(20px); }
.toggle-text { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

/* Modal Transition */
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(0.95) translateY(10px); }

/* Custom Bootstap Toast style */
.text-bg-white { background-color: var(--card-bg) !important; color: #333 !important; }

/* Category Badges in table */
.cat-badges { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 4px; }
.badge-category {
    display: inline-block; font-size: 0.6rem; font-weight: 600;
    padding: 1px 6px; border-radius: 4px;
    background: #e3f2fd; color: #1565c0;
}
.badge-category.all { background: #f3e5f5; color: #7b1fa2; }

/* Category Dropdown selector */
.cat-dropdown-trigger {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; border-radius: 8px;
    border: 1.5px solid var(--border-color); background: var(--ocean-deepest);
    cursor: pointer; transition: all 0.2s; font-size: 0.85rem;
    color: var(--text-main); font-family: var(--font-inter);
}
.cat-dropdown-trigger:hover { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111,0.08); }
.cat-dropdown-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }

.cat-selected-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
.cat-tag {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 10px; border-radius: 14px; font-size: 0.72rem; font-weight: 600;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb); color: #0d47a1;
    border: 1px solid #90caf9; transition: all 0.2s;
}
.cat-tag:hover { background: linear-gradient(135deg, #bbdefb, #90caf9); }

.cat-dropdown-menu {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 50;
    background: var(--card-bg); border: 1px solid #e0e6ed;
    border-radius: 10px; box-shadow: 0 12px 36px rgba(0,0,0,0.12), 0 4px 12px rgba(0,0,0,0.06);
    max-height: 280px; overflow-y: auto; margin-top: 6px; padding: 6px 0;
}
.cat-dropdown-menu::-webkit-scrollbar { width: 5px; }
.cat-dropdown-menu::-webkit-scrollbar-track { background: transparent; }
.cat-dropdown-menu::-webkit-scrollbar-thumb { background: #c7d2e0; border-radius: 10px; }

.cat-dropdown-item {
    display: flex; align-items: center; gap: 10px;
    padding: 0; cursor: pointer; font-size: 0.84rem;
    font-weight: 500; color: #3d4f5f; transition: all 0.15s;
    font-family: var(--font-inter); margin: 2px 6px; border-radius: 6px;
    padding: 9px 12px; user-select: none;
}
.cat-dropdown-item:hover { background: var(--hover-bg); color: var(--primary); }
.cat-dropdown-item.parent {
    font-weight: 700; font-size: 0.85rem; color: var(--text-main);
    margin-top: 2px; border-bottom: 1px solid #f1f5f9;
    border-radius: 6px 6px 0 0; padding-bottom: 9px;
}
.cat-dropdown-item.parent:first-child { margin-top: 0; }
.cat-dropdown-item.child {
    padding-left: 36px; font-size: 0.8rem; color: var(--text-muted);
    position: relative; margin-top: 0; border-radius: 0;
}
.cat-dropdown-item.child::before {
    content: ''; position: absolute; left: 20px; top: 50%; width: 8px; height: 1px;
    background: #cbd5e1;
}
/* Custom checkbox inside dropdown */
.cat-dropdown-item input[type="checkbox"] {
    appearance: none; -webkit-appearance: none;
    width: 17px; height: 17px; border: 2px solid #c0c8d4; border-radius: 4px;
    cursor: pointer; flex-shrink: 0; position: relative;
    background: var(--card-bg); transition: all 0.15s;
}
.cat-dropdown-item input[type="checkbox"]:hover { border-color: var(--primary); }
.cat-dropdown-item input[type="checkbox"]:checked {
    background: var(--primary); border-color: var(--primary);
}
.cat-dropdown-item input[type="checkbox"]:checked::after {
    content: ''; position: absolute; left: 4px; top: 1px;
    width: 5px; height: 9px; border: solid #fff;
    border-width: 0 2px 2px 0; transform: rotate(45deg);
}

/* Usage button in table */
.btn-usage {
    display: inline-flex; align-items: center; gap: 4px;
    margin-top: 4px; padding: 2px 8px; border-radius: 12px;
    border: 1px solid #e3f2fd; background: #e3f2fd; color: #1565c0;
    font-size: 0.68rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
    font-family: var(--font-inter);
}
.btn-usage:hover { background: #bbdefb; border-color: #90caf9; }

/* Stat cards in usages modal */
.stat-card {
    flex: 1; text-align: center; padding: 12px; border-radius: 10px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
}
.stat-num { font-size: 1.3rem; font-weight: 800; color: var(--text-main); }
.stat-label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); margin-top: 2px; }

/* Usage count badge */
.usage-count-badge {
    display: inline-block; min-width: 24px; padding: 2px 8px;
    background: #fff3e0; color: #e65100; font-weight: 800;
    font-size: 0.8rem; border-radius: 12px; text-align: center;
}

/* ======= Option Checkbox (Redesigned form section) ======= */
.option-section {
    padding: 14px 0; border-bottom: 1px solid var(--border-color); margin-bottom: 12px;
}

.option-checkbox {
    display: flex; align-items: center; gap: 10px;
    cursor: pointer; padding: 8px 0; margin: 0; font-family: var(--font-inter);
    user-select: none; font-size: 0.88rem; color: var(--text-main);
}
.option-checkbox input[type="checkbox"] { display: none; }
.option-checkbox .checkmark {
    width: 20px; height: 20px; border: 2px solid #c0c8d4; border-radius: 4px;
    flex-shrink: 0; position: relative; transition: all 0.2s;
    background: var(--ocean-deepest);
}
.option-checkbox input:checked + .checkmark {
    background: var(--primary); border-color: var(--primary);
}
.option-checkbox input:checked + .checkmark::after {
    content: ''; position: absolute; left: 5px; top: 1px;
    width: 6px; height: 11px; border: solid #fff;
    border-width: 0 2.5px 2.5px 0; transform: rotate(45deg);
}
.option-checkbox.main-option { font-weight: 700; font-size: 0.92rem; }
.option-icon { flex-shrink: 0; }
.option-label { flex: 1; }

.advanced-section {
    border-top: 1px solid var(--border-color); padding-top: 12px; margin-bottom: 4px;
}
.advanced-title {
    font-size: 0.7rem; font-weight: 800; letter-spacing: 1.5px;
    color: var(--text-muted); margin-bottom: 6px;
}

.email-option-wrap {
    padding: 8px 12px; margin: 4px 0 4px 28px;
    background: #eef2ff; border-radius: 8px;
}
</style>
