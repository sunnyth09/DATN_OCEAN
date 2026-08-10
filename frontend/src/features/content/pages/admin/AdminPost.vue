<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import api from '@/axios';
import { Toast, Modal } from 'bootstrap';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import Swal from 'sweetalert2';

const posts = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');

const toastObj = ref({ message: '', type: 'success' });
const deletingPostId = ref(null);

const showToast = (message, type = 'success') => {
  toastObj.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('postListToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const fetchPosts = async () => {
    try {
        isLoading.value = true;
        const response = await api.get('/posts');
        if(response.data && response.data.data) {
             posts.value = response.data.data;
        } else if (Array.isArray(response.data)) {
             posts.value = response.data;
        }
    } catch (error) {
        showToast('Lỗi tải danh sách bài viết!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

const filteredPosts = computed(() => {
    if (!searchQuery.value) return posts.value;
    const q = searchQuery.value.toLowerCase();
    return posts.value.filter(p => p.title && p.title.toLowerCase().includes(q));
});

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(10); 

watch(searchQuery, () => {
    currentPage.value = 1;
});

const totalPages = computed(() => Math.ceil(filteredPosts.value.length / itemsPerPage.value));

const paginatedPosts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredPosts.value.slice(start, end);
});

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

onMounted(fetchPosts);

const deletePost = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa bài viết?',
        text: 'Bài viết này sẽ bị xóa. Hành động này không thể hoàn tác.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/admin/posts/${id}`);
            showToast(res.data?.message || 'Đã xóa bài viết!', 'success');
            await fetchPosts();
        } catch (error) {
            showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
        }
    }
};

const getStatusLabel = (status) => {
    const map = {
        'published': { text: 'Xuất bản', class: 'active' },
        'draft': { text: 'Bản nháp', class: 'draft' },
        'hidden': { text: 'Đang ẩn', class: 'inactive' }
    };
    return map[status] || map['draft'];
};
</script>

<template>
    <AdminTableSkeleton v-if="isLoading" :columns="7" :rows="6" />
    <div v-else class="post-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Quản lý bài viết
                </h1>
                <p class="page-subtitle">Quản lý nội dung, blog, tin tức và khuyến mãi</p>
            </div>
            <router-link to="/admin/post/create" class="btn-primary" id="add-post-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Thêm bài viết
            </router-link>
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
                    placeholder="Tìm kiếm bài viết theo tiêu đề..."
                    class="search-input"
                />
            </div>
            <div class="filter-stats">
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                    {{ posts.length }} bài viết
                </span>
            </div>
        </div>

        <!-- Posts Table -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredPosts.length }}</strong> bài viết tìm thấy
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hình ảnh</th>
                            <th>Tiêu đề</th>
                            <th>Danh mục</th>
                            <th>Tác giả</th>
                            <th>Lượt xem</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="p in paginatedPosts" :key="p.post_id">
                            <tr>
                                <td>
                                    <div class="thumbnail-cell">
                                        <img v-if="p.thumbnail_url" :src="p.thumbnail_url" alt="thumbnail" />
                                        <div v-else class="img-placeholder">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="post-title-cell">
                                        <span class="post-title" :title="p.title">{{ p.title }}</span>
                                        <span class="badge-featured" v-if="p.is_featured">Hot</span>
                                    </div>
                                </td>
                                <td>{{ p.category ? p.category.name : 'Không có' }}</td>
                                <td>{{ p.author?.full_name || 'Không xác định' }}</td>
                                <td>{{ p.view_count || 0 }}</td>
                                <td>
                                    <span class="status-badge" :class="getStatusLabel(p.status).class">
                                        {{ getStatusLabel(p.status).text }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <router-link :to="`/admin/post/edit/${p.post_id}`" class="btn-action edit" title="Chỉnh sửa">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </router-link>
                                        <button @click="deletePost(p.post_id)" class="btn-action delete" title="Xóa">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div v-if="totalPages > 1 && filteredPosts.length > 0" class="pagination-controls">
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
            <div v-if="filteredPosts.length === 0" class="empty-state">
                <span class="empty-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg></span>
                <h3>Không tìm thấy bài viết</h3>
                <p>{{ searchQuery ? 'Thử từ khóa khác.' : 'Bắt đầu bằng cách thêm bài viết đầu tiên.' }}</p>
                <router-link to="/admin/post/create" class="btn-primary mt-3" style="display:inline-flex" v-if="!searchQuery">Thêm bài viết ngay</router-link>
            </div>
        </div>



        <!-- Bootstrap Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
            <div class="toast align-items-center border-0" :class="toastObj.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="postListToast" role="alert">
                <div class="d-flex">
                    <div class="toast-body">{{ toastObj.message }}</div>
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
    background: var(--primary); color: white; text-decoration: none;
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
    border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.data-table :deep(td) {
    padding: 14px 24px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s; vertical-align: middle;
}
.data-table :deep(tbody tr:hover td) { background: var(--hover-bg); }

/* Custom Row Styles */
.thumbnail-cell { width: 50px; height: 50px; border-radius: 6px; overflow: hidden; background: #f0f0f0; display: flex; align-items: center; justify-content: center; }
.thumbnail-cell img { width: 100%; height: 100%; object-fit: cover; }
.img-placeholder { color: #a0a0a0; }

.post-title-cell { display: flex; align-items: center; gap: 8px; }
.post-title { font-weight: 600; color: var(--text-main); font-size: 0.95rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
.badge-featured { background: #fee2e2; color: #ef4444; font-size: 0.7rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; }

.status-badge {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.status-badge.active { background: rgba(230, 59, 111, 0.08); color: #0284c7; }
.status-badge.inactive { background: var(--surface-container); color: var(--text-muted); }
.status-badge.draft { background: #fef3c7; color: #d97706; }

.action-buttons { display: flex; gap: 8px; }
.btn-action {
    background: none; border: none; padding: 6px; border-radius: 6px;
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.btn-action.edit { color: var(--primary); background: rgba(230, 59, 111, 0.08); }
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
</style>