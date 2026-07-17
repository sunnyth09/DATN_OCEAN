<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import api from '../../axios.js';
import { Toast } from 'bootstrap';
import Swal from 'sweetalert2';
import AdminCategoryFormTree from '../../components/AdminCategoryFormTree.vue';
import AdminCategoryRow from '../../components/AdminCategoryRow.vue';

const categories = ref([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const isEditing = ref(false);
const searchQuery = ref('');

const defaultForm = () => ({
    category_id: null,
    name: '',
    parent_id: null,
    description: '',
    sort_order: 0,
    is_active: 1,
    // Ảnh hiện tại (URL để preview khi edit)
    current_image_url: null,
});

const form = ref(defaultForm());

// File ảnh mới được chọn
const imageFile = ref(null);
const imagePreview = ref(null);
const imageInputRef = ref(null);
const isDeletingImage = ref(false);

const toast = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('categoryToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const fetchCategories = async () => {
    try {
        isLoading.value = true;
        const response = await api.get('/categories');
        categories.value = response.data.data;
    } catch (error) {
        showToast('Lỗi tải danh mục!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

const filterTree = (nodes, query) => {
    if (!query) return nodes;
    const q = query.toLowerCase();
    return nodes.reduce((acc, node) => {
        const children = node.children ? filterTree(node.children, query) : [];
        if (node.name.toLowerCase().includes(q) || children.length) {
            acc.push({ ...node, children });
        }
        return acc;
    }, []);
};

const filteredCategories = computed(() => filterTree(categories.value, searchQuery.value));

const countAll = (nodes) => nodes.reduce((sum, n) => sum + 1 + countAll(n.children || []), 0);
const totalCount = computed(() => countAll(categories.value));

onMounted(fetchCategories);

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

// ── Xóa ảnh hiện tại (đã lưu trên server) ──
const removeCurrentImage = async () => {
    if (!form.value.category_id || !form.value.current_image_url) {
        form.value.current_image_url = null;
        return;
    }
    isDeletingImage.value = true;
    try {
        await api.delete(`/categories/${form.value.category_id}/image`);
        form.value.current_image_url = null;
        showToast('Đã xóa ảnh danh mục!', 'success');
        await fetchCategories();
    } catch {
        showToast('Xóa ảnh thất bại!', 'danger');
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

const openEditModal = (category) => {
    isEditing.value = true;
    formError.value = '';
    errors.value = {};
    form.value = {
        category_id: category.category_id,
        name: category.name,
        parent_id: category.parent_id == 0 ? null : category.parent_id,
        description: category.description || '',
        sort_order: category.sort_order || 0,
        is_active: category.is_active,
        current_image_url: category.image_url || null,
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
        errors.value.name = 'Vui lòng nhập tên danh mục.';
        hasError = true;
    }

    if (hasError) return;

    isSubmitting.value = true;

    // Luôn dùng FormData để hỗ trợ upload file
    const fd = new FormData();
    fd.append('name', form.value.name);
    fd.append('parent_id', form.value.parent_id ?? '');
    fd.append('description', form.value.description || '');
    fd.append('sort_order', form.value.sort_order ?? 0);
    fd.append('is_active', form.value.is_active ?? 1);
    if (imageFile.value) {
        fd.append('image', imageFile.value);
    }

    try {
        if (isEditing.value) {
            // Dùng POST + _method=PUT hoặc route POST categories/{id}
            fd.append('_method', 'PUT');
            const res = await api.post(`/categories/${form.value.category_id}`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            Swal.fire('Thành công', res.data.message || 'Cập nhật thành công!', 'success');
        } else {
            const res = await api.post('/categories', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            Swal.fire('Thành công', res.data.message || 'Thêm danh mục thành công!', 'success');
        }
        await fetchCategories();
        closeModal();
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            for (const key in backendErrors) {
                errors.value[key] = backendErrors[key][0];
            }
        } else {
            formError.value = error.response?.data?.message || (isEditing.value ? 'Cập nhật thất bại!' : 'Thêm danh mục thất bại!');
        }
    } finally {
        isSubmitting.value = false;
    }
};

const deleteCategory = async (id) => {
    const result = await Swal.fire({
        title: 'Xác nhận xóa',
        text: 'Bạn có chắc chắn muốn xóa vĩnh viễn danh mục này? Thao tác này không thể hoàn tác!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    try {
        const res = await api.delete(`/categories/${id}`);
        showToast(res.data.message || 'Đã xóa danh mục!', 'success');
        await fetchCategories();
    } catch (error) {
        showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
    }
};
</script>

<template>
    <div class="category-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                    </svg>
                    Quản lý danh mục
                </h1>
                <p class="page-subtitle">Quản lý và tổ chức sản phẩm theo danh mục phân cấp</p>
            </div>
            <button @click="openCreateModal" class="btn-primary" id="add-category-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Thêm danh mục
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
                    placeholder="Tìm kiếm danh mục theo tên..."
                    class="search-input"
                />
            </div>
            <div class="filter-stats">
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                    {{ totalCount }} danh mục
                </span>
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    {{ categories.length }} gốc
                </span>
            </div>
        </div>



        <!-- Category Table -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredCategories.length }}</strong> danh mục gốc tìm thấy
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên danh mục</th>
                            <th>ID</th>
                            <th>Trạng thái</th>
                            <th>Thứ tự</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="cat in filteredCategories" :key="cat.category_id">
                            <AdminCategoryRow
                                :category="cat"
                                :level="0"
                                @edit="openEditModal"
                                @delete="deleteCategory"
                            />
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="filteredCategories.length === 0" class="empty-state">
                <span class="empty-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg></span>
                <h3>Không tìm thấy danh mục</h3>
                <p>{{ searchQuery ? 'Thử từ khóa khác.' : 'Bắt đầu bằng cách thêm danh mục đầu tiên.' }}</p>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="modal">
            <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box ocean-card">
                    <div class="modal-head">
                        <h3>{{ isEditing ? 'Chỉnh sửa danh mục' : 'Thêm danh mục mới' }}</h3>
                        <button class="btn-close" @click="closeModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="handleSubmit" novalidate class="modal-body">
                        <div class="form-group">
                            <label>Tên danh mục <span class="required">*</span></label>
                            <input v-model="form.name" type="text" placeholder="VD: Quần áo thể thao" class="form-control" :class="{'is-invalid': errors.name}" />
                            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
                        </div>
                        <div class="form-group">
                            <label>Danh mục cha</label>
                            <select v-model="form.parent_id" class="form-control form-select">
                                <option :value="null">— Không có (Danh mục gốc)</option>
                                <AdminCategoryFormTree :categories="categories" :currentParentId="form.category_id" />
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Thứ tự hiển thị</label>
                                <input v-model.number="form.sort_order" type="number" min="0" class="form-control" placeholder="0" />
                            </div>
                            <div class="form-group">
                                <label>Trạng thái</label>
                                <div class="toggle-wrap">
                                    <label class="toggle-switch-wrapper">
                                        <div class="toggle-switch">
                                            <input type="checkbox" v-model="form.is_active" :true-value="1" :false-value="0" class="toggle-input" />
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <span class="toggle-text">{{ form.is_active ? 'Đang hiển thị' : 'Đang ẩn' }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mô tả</label>
                            <textarea v-model="form.description" rows="3" class="form-control" placeholder="Mô tả ngắn về danh mục (không bắt buộc)..."></textarea>
                        </div>

                        <!-- ── Phần Upload Ảnh ── -->
                        <div class="form-group">
                            <label>Hình ảnh danh mục</label>

                            <!-- Preview ảnh đang chọn mới -->
                            <div v-if="imagePreview" class="image-preview-wrap">
                                <img :src="imagePreview" alt="Xem trước ảnh mới" class="image-preview" />
                                <button type="button" class="img-remove-btn" @click="clearImageSelection" title="Hủy chọn ảnh">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                                <span class="img-new-badge">Ảnh mới</span>
                            </div>

                            <!-- Preview ảnh hiện tại (server) -->
                            <div v-else-if="form.current_image_url" class="image-preview-wrap">
                                <img :src="form.current_image_url" alt="Ảnh hiện tại" class="image-preview" />
                                <button
                                    type="button"
                                    class="img-remove-btn"
                                    @click="removeCurrentImage"
                                    :disabled="isDeletingImage"
                                    title="Xóa ảnh"
                                >
                                    <svg v-if="!isDeletingImage" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                    <span v-else class="mini-spinner"></span>
                                </button>
                            </div>

                            <!-- Drop zone / Chọn file -->
                            <label v-if="!imagePreview" class="image-drop-zone" :class="{'has-image': form.current_image_url}">
                                <input
                                    ref="imageInputRef"
                                    type="file"
                                    accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
                                    class="file-input-hidden"
                                    @change="onImageChange"
                                />
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <span class="drop-zone-text">
                                    {{ form.current_image_url ? 'Thay ảnh mới' : 'Chọn hoặc kéo thả ảnh' }}
                                </span>
                                <span class="drop-zone-hint">JPG, PNG, WebP, GIF — tối đa 2MB</span>
                            </label>
                            <span v-if="errors.image" class="field-error">{{ errors.image }}</span>
                        </div>

                        <!-- Inline error -->
                        <div v-if="formError" class="form-error-box">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            {{ formError }}
                        </div>

                        <div class="modal-footer">
                            <button type="button" @click="closeModal" class="btn-outline">Hủy bỏ</button>
                            <button type="submit" class="btn-primary" :disabled="isSubmitting">
                                <span v-if="isSubmitting">Đang lưu...</span>
                                <span v-else>{{ isEditing ? 'Lưu thay đổi' : 'Tạo danh mục' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>


        <!-- Bootstrap Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
            <div class="toast align-items-center border-0" :class="toast.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="categoryToast" role="alert">
                <div class="d-flex">
                    <div class="toast-body">{{ toast.message }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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

/* Loading */
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
    transition: background 0.15s;
}
.data-table :deep(tbody tr:hover td) { background: var(--hover-bg); }

/* Empty */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.empty-state p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }

/* Modal */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
    overflow-y: auto; padding: 24px 0;
}
.modal-box {
    width: 100%; max-width: 560px; padding: 0;
    border-radius: 16px; overflow: hidden;
    margin: auto;
}
.modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid var(--border-color);
}
.modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); }
.btn-close {
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); display: flex; align-items: center; justify-content: center;
    padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.btn-close:hover { background: var(--hover-bg); color: var(--coral); }

.modal-body { padding: 24px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

/* Form */
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
.form-control.is-invalid { border-color: var(--coral); background: #fef2f2; }
.form-control.is-invalid:focus { box-shadow: 0 0 0 3px rgba(239,83,80,0.1); }
.field-error { display: block; color: var(--coral); font-size: 0.72rem; font-weight: 600; margin-top: 6px; animation: fadeSlideUp 0.2s ease; }

.form-error-box {
    display: flex; align-items: center; gap: 8px; padding: 10px 14px; margin-bottom: 14px;
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
    color: #dc2626; font-size: 0.82rem; font-weight: 600;
    animation: shakeError 0.35s ease;
}
@keyframes shakeError { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }

.form-control::placeholder { color: var(--text-light); }
.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Toggle */
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

/* ── Image Upload ── */
.image-preview-wrap {
    position: relative; display: inline-block; margin-bottom: 10px;
    border-radius: 10px; overflow: visible;
}
.image-preview {
    width: 120px; height: 90px; object-fit: cover;
    border-radius: 10px; border: 2px solid var(--border-color);
    display: block;
}
.img-remove-btn {
    position: absolute; top: -8px; right: -8px;
    width: 24px; height: 24px; border-radius: 50%;
    background: var(--coral); color: white; border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.2s; z-index: 1;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
.img-remove-btn:hover { transform: scale(1.1); }
.img-remove-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.img-new-badge {
    position: absolute; bottom: 6px; left: 6px;
    background: var(--primary); color: white;
    font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 4px;
    letter-spacing: 0.5px;
}

.image-drop-zone {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px; padding: 20px; border: 2px dashed var(--border-color);
    border-radius: 10px; cursor: pointer; transition: all 0.2s;
    color: var(--text-light); text-align: center;
    background: var(--ocean-deepest);
}
.image-drop-zone:hover {
    border-color: var(--primary); color: var(--primary);
    background: rgba(230, 59, 111, 0.04);
}
.image-drop-zone.has-image { padding: 12px; border-style: solid; }
.file-input-hidden { display: none; }
.drop-zone-text { font-size: 0.82rem; font-weight: 600; }
.drop-zone-hint { font-size: 0.72rem; color: var(--text-light); }

/* Mini spinner */
.mini-spinner {
    display: inline-block; width: 12px; height: 12px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: white;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}

/* Modal Transition */
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(0.95) translateY(10px); }

/* Responsive */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
    .search-box { max-width: 100%; }
    .filter-stats { justify-content: flex-start; }
}
</style>
