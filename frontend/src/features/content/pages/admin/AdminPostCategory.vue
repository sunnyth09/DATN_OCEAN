<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import api from '@/axios';
import { Toast, Modal } from 'bootstrap';
import AdminCategoryFormTree from '@/components/AdminCategoryFormTree.vue';
import AdminCategoryRow from '@/components/AdminCategoryRow.vue'; // We can safely use this component if the props match (e.g., category_id vs post_category_id)

const categories = ref([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const isEditing = ref(false);
const searchQuery = ref('');

const defaultForm = () => ({
    post_category_id: null,
    name: '',
    parent_id: null,
    description: '',
    sort_order: 0,
    is_active: 1,
});

const form = ref(defaultForm());

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
        const response = await api.get('/post-categories'); // Use admin prefix as discussed
        categories.value = response.data.data;
    } catch (error) {
        showToast('Lỗi tải danh mục bài viết!', 'danger');
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

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(5); // Số danh mục gốc trên mỗi trang

watch(searchQuery, () => {
    currentPage.value = 1; // Reset về trang 1 khi tìm kiếm
});

const totalPages = computed(() => Math.ceil(filteredCategories.value.length / itemsPerPage.value));

const paginatedCategories = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredCategories.value.slice(start, end);
});

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

onMounted(fetchCategories);

const openCreateModal = () => {
    isEditing.value = false;
    form.value = defaultForm();
    isModalOpen.value = true;
};

const openEditModal = (category) => {
    isEditing.value = true;
    form.value = {
        post_category_id: category.post_category_id,
        name: category.name,
        parent_id: category.parent_id == 0 ? null : category.parent_id,
        description: category.description || '',
        sort_order: category.sort_order || 0,
        is_active: category.is_active !== undefined ? category.is_active : 1,
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const handleSubmit = async () => {
    if (!form.value.name.trim()) {
        showToast('Vui lòng nhập tên danh mục!', 'danger');
        return;
    }

    const data = { ...form.value, parent_id: form.value.parent_id || null };
    isSubmitting.value = true;

    try {
        if (isEditing.value) {
            const res = await api.put(`/post-categories/${data.post_category_id}`, data);
            Swal.fire('Thành công', res.data?.message || 'Cập nhật thành công!', 'success');
        } else {
            const res = await api.post('/post-categories', data);
            Swal.fire('Thành công', res.data?.message || 'Thêm danh mục thành công!', 'success');
        }
        await fetchCategories();
        closeModal();
    } catch (error) {
        const msg = error.response?.data?.message || (isEditing.value ? 'Cập nhật thất bại!' : 'Thêm danh mục thất bại!');
        showToast(msg, 'danger');
    } finally {
        isSubmitting.value = false;
    }
};

import Swal from 'sweetalert2';

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

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/post-categories/${id}`);
            showToast(res.data?.message || 'Đã xóa danh mục!', 'success');
            await fetchCategories();
        } catch (error) {
            showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
        }
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
                    Danh mục bài viết
                </h1>
                <p class="page-subtitle">Quản lý và tổ chức bài viết theo danh mục</p>
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
                        <template v-for="cat in paginatedCategories" :key="cat.post_category_id">
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

            <!-- Pagination Controls -->
            <div v-if="totalPages > 1 && filteredCategories.length > 0" class="pagination-controls">
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
                        <h3>{{ isEditing ? 'Chỉnh sửa danh mục' : 'Thêm danh mục bài viết' }}</h3>
                        <button class="btn-close" @click="closeModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="handleSubmit" class="modal-body">
                        <div class="form-group">
                            <label>Tên danh mục <span class="required">*</span></label>
                            <input v-model="form.name" type="text" placeholder="VD: Tin tức nổi bật" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label>Danh mục cha</label>
                            <select v-model="form.parent_id" class="form-control form-select">
                                <option :value="null">— Không có (Danh mục gốc)</option>
                                <AdminCategoryFormTree :categories="categories" :currentParentId="form.post_category_id" />
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
.category-page { font-family: var(--font-inter); padding-bottom: 2rem;}

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

/* Pagination */
.pagination-controls {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid var(--border-color);
}
.btn-page {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
.btn-page:hover:not(:disabled) { background: var(--hover-bg); border-color: var(--primary); color: var(--primary); }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.btn-page-number:hover:not(.active) { background: var(--hover-bg); }
.btn-page-number.active {
    background: var(--primary); color: white; border-color: var(--primary);
}

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
    vertical-align: middle;
}
.data-table :deep(tbody tr:hover td) { background: var(--hover-bg); }

/* Custom Row Styles */
.category-name-cell { display: flex; align-items: center; gap: 10px; }
.tree-line { width: 12px; height: 1px; background: var(--border-color); display: none; }
.folder-icon { color: var(--primary); opacity: 0.8; }
.category-name { font-weight: 600; color: var(--text-main); font-size: 0.9rem; }
.status-badge {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.status-badge.active { background: rgba(230, 59, 111, 0.08); color: #0284c7; }
.status-badge.inactive { background: var(--surface-container); color: var(--text-muted); }
.action-buttons { display: flex; gap: 8px; }
.btn-action {
    background: none; border: none; padding: 6px; border-radius: 6px;
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.btn-action.view { color: var(--primary); background: rgba(230, 59, 111, 0.1); }
.btn-action.edit { color: var(--primary); background: rgba(230, 59, 111, 0.08); }
.btn-action.delete { color: var(--coral); background: #fee2e2; }
.btn-action:hover { transform: scale(1.1); }

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
}
.modal-box {
    width: 100%; max-width: 520px; padding: 0;
    border-radius: 16px; overflow: hidden;
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
