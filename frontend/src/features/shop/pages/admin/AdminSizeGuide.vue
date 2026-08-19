<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import AppIcon from '@/components/AppIcon.vue';

const sizeGuides = ref([]);
const categories = ref([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const isEditing = ref(false);
onMounted(() => {
    fetchData();

const form = ref({
    id: null,
    name: '',
    description: '',
    table_headers: [],
    table_rows: [],
    tips: [],
    category_id: null
});

const formError = ref('');
const errors = ref({});

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

const fetchData = async () => {
    isLoading.value = true;
    try {
        const resSizes = await api.get('/size-guides');
        sizeGuides.value = resSizes.data?.data || resSizes.data || [];
    } catch (error) {
        console.error('Lỗi tải bảng size:', error);
        showToast('Lỗi tải dữ liệu bảng size!', 'danger');
    }

    try {
        const resCats = await api.get('/categories');
        categories.value = resCats.data?.data || resCats.data || [];
    } catch (error) {
        console.error('Lỗi tải danh mục:', error);
        showToast('Lỗi tải danh mục!', 'danger');
    }
    
    isLoading.value = false;
};

onMounted(fetchData);

const openCreateModal = () => {
    isEditing.value = false;
    formError.value = '';
    errors.value = {};
    form.value = {
        id: null,
        name: '',
        description: '',
        table_headers: ['Size', 'Chiều dài (cm)'],
        table_rows: [
            ['M', '65']
        ],
        tips: ['Vui lòng chọn size theo bảng'],
        category_id: null
    };
    isModalOpen.value = true;
};

const openEditModal = (guide) => {
    isEditing.value = true;
    formError.value = '';
    errors.value = {};
    form.value = {
        id: guide.id,
        name: guide.name || '',
        description: guide.description || '',
        table_headers: Array.isArray(guide.table_headers) ? [...guide.table_headers] : [],
        table_rows: Array.isArray(guide.table_rows) ? JSON.parse(JSON.stringify(guide.table_rows)) : [],
        tips: Array.isArray(guide.tips) ? [...guide.tips] : [],
        category_id: guide.categories && guide.categories.length > 0 ? guide.categories[0].category_id : null
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

// --- Bảng Data ---
const addColumn = () => {
    form.value.table_headers.push('Cột mới');
    form.value.table_rows.forEach(row => {
        row.push('');
    });
};
const removeColumn = (index) => {
    if (form.value.table_headers.length <= 1) return;
    form.value.table_headers.splice(index, 1);
    form.value.table_rows.forEach(row => {
        row.splice(index, 1);
    });
};

const addRow = () => {
    const newRow = Array(form.value.table_headers.length).fill('');
    form.value.table_rows.push(newRow);
};
const removeRow = (index) => {
    if (form.value.table_rows.length <= 1) return;
    form.value.table_rows.splice(index, 1);
};

const addTip = () => {
    form.value.tips.push('');
};
const removeTip = (index) => {
    form.value.tips.splice(index, 1);
};

const handleSubmit = async () => {
    formError.value = '';
    errors.value = {};

    if (!form.value.name.trim()) {
        errors.value.name = 'Vui lòng nhập tên bảng size.';
        return;
    }

    const categoryIds = form.value.category_id ? [form.value.category_id] : [];

    try {
        const payload = {
            name: form.value.name,
            description: form.value.description,
            table_headers: form.value.table_headers,
            table_rows: form.value.table_rows,
            tips: form.value.tips,
            category_ids: categoryIds
        };

        if (isEditing.value) {
            await api.put(`/size-guides/${form.value.id}`, payload);
            showToast('Cập nhật thành công!', 'success');
        } else {
            await api.post('/size-guides', payload);
            showToast('Tạo thành công!', 'success');
        }
        await fetchData();
        closeModal();
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            for (const key in backendErrors) {
                errors.value[key] = backendErrors[key][0];
            }
        } else {
            formError.value = error.response?.data?.message || 'Có lỗi xảy ra!';
        }
    } finally {
        isSubmitting.value = false;
    }
};

const deleteGuide = async (id) => {
    const result = await Swal.fire({
        title: 'Xác nhận xóa',
        text: 'Bạn có chắc chắn muốn xóa bảng size này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    try {
        await api.delete(`/size-guides/${id}`);
        showToast('Đã xóa bảng size!', 'success');
        await fetchData();
    } catch (error) {
        showToast('Xóa thất bại!', 'danger');
    }
};
</script>

<template>
    <div class="size-guide-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                    Quản lý Bảng Size
                </h1>
                <p class="page-subtitle">Quản lý linh hoạt bảng hướng dẫn chọn size cho các danh mục sản phẩm</p>
            </div>
            <button @click="openCreateModal" class="btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Thêm Bảng Size
            </button>
        </div>

        <AdminTableSkeleton v-if="isLoading" :columns="4" :rows="5" />

        <div v-else class="table-container ocean-card animate-in">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ sizeGuides.length }}</strong> bảng size
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên Bảng Size</th>
                            <th>Mô tả</th>
                            <th>Áp dụng cho Danh mục</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="guide in sizeGuides" :key="guide.id">
                            <td><strong>{{ guide.name }}</strong></td>
                            <td class="desc-cell">{{ guide.description }}</td>
                            <td>
                                <div class="cat-tags">
                                    <span v-for="cat in guide.categories" :key="cat.category_id" class="cat-tag">
                                        {{ cat.name }}
                                    </span>
                                    <span v-if="!guide.categories || guide.categories.length === 0" class="text-muted">Chưa áp dụng</span>
                                </div>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button @click="openEditModal(guide)" class="btn-action edit" title="Sửa">
                                        <AppIcon name="edit" size="16"/>
                                    </button>
                                    <button @click="deleteGuide(guide.id)" class="btn-action delete" title="Xóa">
                                        <AppIcon name="trash" size="16"/>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="sizeGuides.length === 0">
                            <td colspan="4" class="text-center py-4">Chưa có bảng size nào.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="modal">
            <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box ocean-card modal-xl">
                    <div class="modal-head">
                        <h3>{{ isEditing ? 'Chỉnh sửa Bảng Size' : 'Thêm Bảng Size Mới' }}</h3>
                        <button class="btn-close" @click="closeModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="handleSubmit" class="modal-body custom-scroll">
                        
                        <div class="form-row-2">
                            <div class="form-group">
                                <label>Tên bảng size <span class="required">*</span></label>
                                <input v-model="form.name" type="text" placeholder="VD: Bảng size Giày Thể Thao" class="form-control" :class="{'is-invalid': errors.name}" />
                                <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
                            </div>
                            <div class="form-group">
                                <label>Áp dụng cho Danh mục</label>
                                <select v-model="form.category_id" class="form-control form-select" :class="{'is-invalid': errors.category_ids}">
                                    <option :value="null">— Chọn 1 danh mục —</option>
                                    <AdminCategoryFormTree :categories="categories" />
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Đoạn mô tả chung</label>
                            <textarea v-model="form.description" rows="2" class="form-control" placeholder="VD: Bảng tính mặc định được thiết kế..."></textarea>
                        </div>

                        <!-- Data Table Builder -->
                        <div class="table-builder">
                            <div class="tb-head">
                                <h4>Dữ liệu Bảng Kích Cỡ</h4>
                                <button type="button" @click="addColumn" class="btn-outline-small">+ Thêm Cột</button>
                            </div>
                            
                            <div class="tb-wrapper">
                                <table class="builder-table">
                                    <thead>
                                        <tr>
                                            <th v-for="(col, colIndex) in form.table_headers" :key="colIndex">
                                                <div class="header-edit">
                                                    <input type="text" v-model="form.table_headers[colIndex]" class="form-control form-control-sm" placeholder="Tên cột" />
                                                    <button type="button" @click="removeColumn(colIndex)" class="btn-remove-col" v-if="form.table_headers.length > 1" title="Xóa cột">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </button>
                                                </div>
                                            </th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, rowIndex) in form.table_rows" :key="rowIndex">
                                            <td v-for="(cell, colIndex) in row" :key="colIndex">
                                                <input type="text" v-model="form.table_rows[rowIndex][colIndex]" class="form-control form-control-sm" />
                                            </td>
                                            <td>
                                                <button type="button" @click="removeRow(rowIndex)" class="btn-remove-row" v-if="form.table_rows.length > 1" title="Xóa hàng">
                                                    <AppIcon name="trash" size="14"/>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" @click="addRow" class="btn-outline-small mt-2">+ Thêm Hàng</button>
                        </div>

                        <!-- Tips -->
                        <div class="tips-builder mt-4">
                            <div class="tb-head">
                                <h4>Ghi chú / Lưu ý</h4>
                                <button type="button" @click="addTip" class="btn-outline-small">+ Thêm Lưu ý</button>
                            </div>
                            <div class="tip-list">
                                <div v-for="(tip, index) in form.tips" :key="index" class="tip-item">
                                    <div class="tip-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2">
                                          <circle cx="12" cy="12" r="10" />
                                          <line x1="12" y1="16" x2="12" y2="12" />
                                          <line x1="12" y1="8" x2="12.01" y2="8" />
                                        </svg>
                                    </div>
                                    <input type="text" v-model="form.tips[index]" class="form-control" placeholder="Nhập ghi chú..." />
                                    <button type="button" @click="removeTip(index)" class="btn-remove-row">
                                        <AppIcon name="trash" size="16"/>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Inline error -->
                        <div v-if="formError" class="form-error-box mt-3">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            {{ formError }}
                        </div>

                        <div class="modal-footer">
                            <button type="button" @click="closeModal" class="btn-outline">Hủy bỏ</button>
                            <button type="submit" class="btn-primary" :disabled="isSubmitting">
                                <span v-if="isSubmitting">Đang lưu...</span>
                                <span v-else>{{ isEditing ? 'Lưu thay đổi' : 'Tạo bảng size' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.size-guide-page { font-family: var(--font-inter); }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

.btn-primary {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 8px; border: none;
    background: var(--primary); color: white;
    font-weight: 700; cursor: pointer; transition: all 0.2s;
}
.btn-primary:hover { background: var(--ocean-bright); }

.table-container { margin-bottom: 24px; }
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th { padding: 14px 24px; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; background: var(--ocean-deepest); }
.data-table td { padding: 14px 24px; border-bottom: 1px solid var(--border-color); }
.desc-cell { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.cat-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.cat-tag { background: var(--hover-bg); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: var(--ocean-blue); }

.action-btns { display: flex; gap: 8px; }
.btn-action { background: none; border: none; border-radius: 6px; padding: 6px; cursor: pointer; transition: all 0.2s; color: var(--text-muted); }
.btn-action.edit:hover { background: #e0f2fe; color: #0284c7; }
.btn-action.delete:hover { background: #fee2e2; color: #dc2626; }

/* Modal */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
    overflow-y: auto; padding: 24px 0;
}
.modal-box.modal-xl { 
    width: 100%; max-width: 700px; padding: 0;
    border-radius: 16px; overflow: hidden;
    margin: auto; background: var(--card-bg, #fff);
}
.modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid var(--border-color);
}
.modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.btn-close {
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); display: flex; align-items: center; justify-content: center;
    padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.btn-close:hover { background: var(--hover-bg); color: var(--coral); }

.modal-body.custom-scroll { padding: 24px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
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
.field-error { display: block; color: var(--coral); font-size: 0.72rem; font-weight: 600; margin-top: 6px; }

.form-error-box {
    display: flex; align-items: center; gap: 8px; padding: 10px 14px; margin-bottom: 14px;
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
    color: #dc2626; font-size: 0.82rem; font-weight: 600;
}

.form-control-sm { padding: 8px 12px; font-size: 0.85rem;}

/* Builder */
.table-builder, .tips-builder { background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); }
.tb-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.tb-head h4 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-main); }
.btn-outline-small { background: white; border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; font-weight: 600;}
.btn-outline-small:hover { background: var(--hover-bg); border-color: var(--primary); color: var(--primary); }

.tb-wrapper { overflow-x: auto; background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px;}
.builder-table { width: 100%; border-collapse: collapse; }
.builder-table th, .builder-table td { padding: 6px; }
.header-edit { display: flex; align-items: center; gap: 4px; }
.btn-remove-col, .btn-remove-row { background: none; border: none; color: #dc2626; cursor: pointer; padding: 4px; border-radius: 4px; display: flex; align-items: center;}
.btn-remove-col:hover, .btn-remove-row:hover { background: #fee2e2; }

.tip-list { display: flex; flex-direction: column; gap: 10px; }
.tip-item { display: flex; align-items: center; gap: 10px; background: white; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);}
.tip-icon { font-size: 1.2rem; }
.btn-outline {
    padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.btn-outline:hover { border-color: var(--text-light); }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

.text-center { text-align: center; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1.5rem; }
.mt-3 { margin-top: 1rem; }

/* Modal Transition */
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(0.95) translateY(10px); }
</style>
