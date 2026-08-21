<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';

const brands = ref([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const isEditing = ref(false);
const searchQuery = ref('');

const defaultForm = () => ({
    brand_id: null,
    name: '',
    description: '',
    is_active: 1,
    current_image_url: null,
});

const form = ref(defaultForm());

const imageFile = ref(null);
const imagePreview = ref(null);
const imageInputRef = ref(null);
const isDeletingImage = ref(false);

const toast = ref({ message: '', type: 'success' });
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

const fetchBrands = async () => {
    try {
        isLoading.value = true;
        const response = await api.get('/brands');
        brands.value = response.data.data || response.data;
    } catch (error) {
        showToast('Lỗi tải thương hiệu!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

const filteredBrands = computed(() => {
    if (!searchQuery.value) return brands.value;
    const q = searchQuery.value.toLowerCase();
    return brands.value.filter(b => b.name.toLowerCase().includes(q));
});

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(10);

watch(searchQuery, () => {
    currentPage.value = 1; 
});

const totalPages = computed(() => Math.ceil(filteredBrands.value.length / itemsPerPage.value));

const paginatedBrands = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredBrands.value.slice(start, end);
});

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

onMounted(fetchBrands);

// ── Xử lý chọn ảnh ──
const onImageChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    imageFile.value = file;
    const reader = new FileReader();
    reader.onload = (ev) => { imagePreview.value = ev.target.result; };
    reader.readAsDataURL(file);
};

const clearImageSelection = () => {
    imageFile.value = null;
    imagePreview.value = null;
    if (imageInputRef.value) imageInputRef.value.value = '';
};

const removeCurrentImage = async () => {
    if (!form.value.brand_id || !form.value.current_image_url) {
        form.value.current_image_url = null;
        return;
    }
    isDeletingImage.value = true;
    try {
        await api.delete(`/brands/${form.value.brand_id}/image`);
        form.value.current_image_url = null;
        showToast('Đã xóa logo!', 'success');
        await fetchBrands();
    } catch {
        showToast('Xóa logo thất bại!', 'danger');
    } finally {
        isDeletingImage.value = false;
    }
};

const openCreateModal = () => {
    isEditing.value = false;
    formError.value = '';
    errors.value = {};
    form.value = defaultForm();
    clearImageSelection();
    isModalOpen.value = true;
};

const openEditModal = (brand) => {
    isEditing.value = true;
    formError.value = '';
    errors.value = {};
    form.value = {
        brand_id: brand.brand_id,
        name: brand.name,
        description: brand.description || '',
        is_active: brand.is_active,
        current_image_url: brand.image_url || null,
    };
    clearImageSelection();
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    clearImageSelection();
};

const formError = ref('');
const errors = ref({});

const handleSubmit = async () => {
    formError.value = '';
    errors.value = {};

    let hasError = false;
    if (!form.value.name.trim()) {
        errors.value.name = 'Vui lòng nhập tên thương hiệu.';
        hasError = true;
    }

    if (hasError) return;

    isSubmitting.value = true;

    const fd = new FormData();
    fd.append('name', form.value.name);
    fd.append('description', form.value.description || '');
    fd.append('is_active', form.value.is_active ?? 1);
    if (imageFile.value) {
        fd.append('image', imageFile.value);
    }

    try {
        if (isEditing.value) {
            fd.append('_method', 'PUT');
            const res = await api.post(`/brands/${form.value.brand_id}`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            showToast(res.data.message || 'Cập nhật thành công!', 'success');
        } else {
            const res = await api.post('/brands', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            showToast(res.data.message || 'Thêm thương hiệu thành công!', 'success');
        }
        await fetchBrands();
        closeModal();
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            for (const key in backendErrors) {
                errors.value[key] = backendErrors[key][0];
            }
        } else {
            formError.value = error.response?.data?.message || (isEditing.value ? 'Cập nhật thất bại!' : 'Thêm thương hiệu thất bại!');
        }
    } finally {
        isSubmitting.value = false;
    }
};

const deleteBrand = async (id) => {
    const result = await Swal.fire({
        title: 'Xác nhận xóa',
        text: 'Bạn có chắc chắn muốn xóa vĩnh viễn thương hiệu này? Sản phẩm thuộc thương hiệu này sẽ bị mất liên kết.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    try {
        const res = await api.delete(`/brands/${id}`);
        showToast(res.data.message || 'Đã xóa thương hiệu!', 'success');
        await fetchBrands();
    } catch (error) {
        showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
    }
};
</script>

<template>
    <div class="brand-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    Quản lý Thương hiệu
                </h1>
                <p class="page-subtitle">Quản lý các thương hiệu sản phẩm trong hệ thống</p>
            </div>
            <button @click="openCreateModal" class="btn-primary" id="add-brand-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Thêm thương hiệu
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
                    placeholder="Tìm kiếm thương hiệu..."
                    class="search-input"
                />
            </div>
            <div class="filter-stats">
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon></svg>
                    {{ brands.length }} thương hiệu
                </span>
            </div>
        </div>

        <!-- Loading State -->
        <AdminTableSkeleton v-if="isLoading" :columns="4" :rows="5" />

        <!-- Table -->
        <div v-else class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredBrands.length }}</strong> kết quả tìm thấy
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Tên thương hiệu</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="brand in paginatedBrands" :key="brand.brand_id">
                            <td>
                                <div class="brand-logo-container">
                                    <img v-if="brand.image_url" :src="brand.image_url" alt="logo" class="brand-img"/>
                                    <div v-else class="brand-img-placeholder">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="brand-name-info">
                                    <span class="fw-bold">{{ brand.name }}</span>
                                    <span class="brand-id">#{{ brand.brand_id }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge" :class="brand.is_active ? 'status-active' : 'status-inactive'">
                                    {{ brand.is_active ? 'Đang hoạt động' : 'Đang ẩn' }}
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-edit" @click="openEditModal(brand)" title="Chỉnh sửa">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </button>
                                    <button class="btn-action btn-delete" @click="deleteBrand(brand.brand_id)" title="Xóa">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1 && filteredBrands.length > 0" class="pagination-controls">
                <button :disabled="currentPage === 1" @click="changePage(currentPage - 1)" class="btn-page">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Trước
                </button>
                <div class="page-numbers">
                    <button 
                        v-for="page in totalPages" 
                        :key="page" 
                        @click="changePage(page)" 
                        class="btn-page-number" 
                        :class="{'active': currentPage === page}"
                    >
                        {{ page }}
                    </button>
                </div>
                <button :disabled="currentPage === totalPages" @click="changePage(currentPage + 1)" class="btn-page">
                    Sau
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
            
            <!-- Empty -->
            <div v-if="filteredBrands.length === 0" class="empty-state">
                <span class="empty-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon></svg></span>
                <h3>Không tìm thấy thương hiệu</h3>
                <p>{{ searchQuery ? 'Thử từ khóa khác.' : 'Bắt đầu bằng cách thêm thương hiệu đầu tiên.' }}</p>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="modal">
            <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box ocean-card">
                    <div class="modal-head">
                        <h3>{{ isEditing ? 'Chỉnh sửa thương hiệu' : 'Thêm thương hiệu mới' }}</h3>
                        <button class="btn-close" @click="closeModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="handleSubmit" novalidate class="modal-body">
                        <div class="form-group">
                            <label>Tên thương hiệu <span class="required">*</span></label>
                            <input v-model="form.name" type="text" placeholder="VD: Nike, Adidas..." class="form-control" :class="{'is-invalid': errors.name}" />
                            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
                        </div>
                        
                        <div class="form-group">
                            <label>Trạng thái</label>
                            <div class="toggle-wrap">
                                <label class="toggle-switch-wrapper">
                                    <div class="toggle-switch">
                                        <input type="checkbox" v-model="form.is_active" :true-value="1" :false-value="0" class="toggle-input" />
                                        <span class="toggle-slider"></span>
                                    </div>
                                    <span class="toggle-text">{{ form.is_active ? 'Đang hoạt động' : 'Đang ẩn' }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Mô tả</label>
                            <textarea v-model="form.description" rows="3" class="form-control" placeholder="Mô tả ngắn về thương hiệu..."></textarea>
                        </div>

                        <!-- Upload Ảnh -->
                        <div class="form-group">
                            <label>Logo thương hiệu</label>
                            <div v-if="imagePreview" class="image-preview-wrap">
                                <img :src="imagePreview" alt="Xem trước" class="image-preview" />
                                <button type="button" class="img-remove-btn" @click="clearImageSelection" title="Hủy chọn">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                                <span class="img-new-badge">Ảnh mới</span>
                            </div>
                            <div v-else-if="form.current_image_url" class="image-preview-wrap">
                                <img :src="form.current_image_url" alt="Logo hiện tại" class="image-preview" />
                                <button type="button" class="img-remove-btn" @click="removeCurrentImage" :disabled="isDeletingImage" title="Xóa logo">
                                    <svg v-if="!isDeletingImage" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                    <span v-else class="mini-spinner"></span>
                                </button>
                            </div>
                            <label v-if="!imagePreview" class="image-drop-zone" :class="{'has-image': form.current_image_url}">
                                <input ref="imageInputRef" type="file" accept="image/*" class="file-input-hidden" @change="onImageChange" />
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <span class="drop-zone-text">{{ form.current_image_url ? 'Thay logo mới' : 'Chọn hoặc kéo thả ảnh' }}</span>
                            </label>
                            <span v-if="errors.image" class="field-error">{{ errors.image }}</span>
                        </div>

                        <div v-if="formError" class="form-error-box">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            {{ formError }}
                        </div>

                        <div class="modal-footer">
                            <button type="button" @click="closeModal" class="btn-outline">Hủy bỏ</button>
                            <button type="submit" class="btn-primary" :disabled="isSubmitting">
                                <span v-if="isSubmitting">Đang lưu...</span>
                                <span v-else>{{ isEditing ? 'Lưu thay đổi' : 'Tạo thương hiệu' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.brand-page { font-family: var(--font-inter); }

/* Header */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-title { font-size: 1.5rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 12px; }
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

/* Buttons */
.btn-primary { display: flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 8px; border: none; background: var(--primary); color: white; font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2); }
.btn-primary:hover { background: var(--ocean-bright); transform: translateY(-2px); box-shadow: 0 6px 14px rgba(3, 169, 244, 0.3); }
.btn-primary:disabled { opacity: 0.7; transform: none; cursor: not-allowed; }
.btn-outline { padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--ocean-deepest); color: var(--text-main); font-family: var(--font-inter); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-outline:hover { border-color: var(--text-light); }

/* Filters */
.filters-bar { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; margin-bottom: 24px; gap: 16px; }
.search-box { display: flex; align-items: center; gap: 10px; background: var(--ocean-deepest); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 16px; flex: 1; max-width: 400px; transition: all 0.2s; }
.search-box:focus-within { border-color: var(--primary); background: var(--card-bg); box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.search-box svg { color: var(--text-light); flex-shrink: 0; }
.search-input { background: none; border: none; outline: none; color: var(--text-main); font-family: var(--font-inter); font-size: 0.9rem; width: 100%; }
.search-input::placeholder { color: var(--text-light); }
.filter-stats { display: flex; gap: 8px; flex-shrink: 0; }
.stat-pill { display: flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--ocean-deepest); color: var(--text-muted); font-size: 0.8rem; font-weight: 600; }
.stat-pill svg { color: var(--primary); }

/* Pagination */
.pagination-controls { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-top: 1px solid var(--border-color); }
.btn-page { display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--ocean-deepest); color: var(--text-main); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-page:hover:not(:disabled) { background: var(--hover-bg); border-color: var(--primary); color: var(--primary); }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--ocean-deepest); color: var(--text-main); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.btn-page-number:hover:not(.active) { background: var(--hover-bg); }
.btn-page-number.active { background: var(--primary); color: white; border-color: var(--primary); }

/* Table */
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); }
.table-count { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
.table-count strong { color: var(--text-main); font-weight: 800; }
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th { padding: 14px 24px; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest); }
.data-table td { padding: 14px 24px; border-bottom: 1px solid var(--border-color); transition: background 0.15s; }
.data-table tbody tr:hover td { background: var(--hover-bg); }

/* Table Content Styles */
.brand-logo-container { width: 44px; height: 44px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); background: var(--ocean-deepest); display: flex; align-items: center; justify-content: center; }
.brand-img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }
.brand-img-placeholder { color: var(--text-light); }
.brand-name-info { display: flex; flex-direction: column; gap: 4px; }
.brand-id { font-size: 0.75rem; color: var(--text-light); font-weight: 600; }
.status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
.status-active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-inactive { background: rgba(100, 116, 139, 0.1); color: #64748b; }
.action-buttons { display: flex; gap: 8px; }
.btn-action { width: 32px; height: 32px; border-radius: 6px; border: none; background: var(--ocean-deepest); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
.btn-action:hover { background: var(--hover-bg); }
.btn-edit:hover { color: var(--ocean-bright); }
.btn-delete:hover { color: var(--coral); }

/* Empty */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; color: var(--text-light); }
.empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.empty-state p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }

/* Modal */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 24px 0; overflow-y: auto; }
.modal-box { width: 100%; max-width: 500px; padding: 0; border-radius: 16px; margin: auto; }
.modal-head { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border-color); }
.modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); }
.btn-close { background: none; border: none; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 6px; transition: all 0.2s; }
.btn-close:hover { background: var(--hover-bg); color: var(--coral); }
.modal-body { padding: 24px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

/* Form */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.required { color: var(--coral); }
.form-control { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--ocean-deepest); color: var(--text-main); font-family: var(--font-inter); font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box; }
.form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.form-control.is-invalid { border-color: var(--coral); background: #fef2f2; }
.field-error { display: block; color: var(--coral); font-size: 0.72rem; font-weight: 600; margin-top: 6px; }
.form-error-box { display: flex; align-items: center; gap: 8px; padding: 10px 14px; margin-bottom: 14px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.82rem; font-weight: 600; }

/* Toggle */
.toggle-wrap { margin-top: 4px; }
.toggle-switch-wrapper { display: flex; align-items: center; gap: 12px; cursor: pointer; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--text-light); transition: .3s; border-radius: 24px; }
.toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: var(--card-bg); transition: .3s; border-radius: 50%; }
.toggle-input:checked + .toggle-slider { background-color: var(--primary); }
.toggle-input:checked + .toggle-slider:before { transform: translateX(20px); }
.toggle-text { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

/* Image Upload */
.image-preview-wrap { position: relative; display: inline-block; margin-bottom: 10px; border-radius: 10px; }
.image-preview { width: 120px; height: 90px; object-fit: contain; background: white; border-radius: 10px; border: 2px solid var(--border-color); display: block; padding: 5px; }
.img-remove-btn { position: absolute; top: -8px; right: -8px; width: 24px; height: 24px; border-radius: 50%; background: var(--coral); color: white; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; z-index: 1; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
.img-remove-btn:hover { transform: scale(1.1); }
.img-new-badge { position: absolute; bottom: 6px; left: 6px; background: var(--primary); color: white; font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; }
.image-drop-zone { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 20px; border: 2px dashed var(--border-color); border-radius: 10px; cursor: pointer; transition: all 0.2s; color: var(--text-light); text-align: center; background: var(--ocean-deepest); }
.image-drop-zone:hover { border-color: var(--primary); color: var(--primary); background: rgba(230, 59, 111, 0.04); }
.file-input-hidden { display: none; }
.drop-zone-text { font-size: 0.82rem; font-weight: 600; }
.mini-spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid rgba(255,255,255,0.4); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal Transition */
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(0.95) translateY(10px); }

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
    .search-box { max-width: 100%; }
}
</style>
