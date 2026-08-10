<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import api from '@/axios';
import { Toast } from 'bootstrap';
import Swal from 'sweetalert2';

const comments = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const statusFilter = ref('all'); // all, approved, pending
const page = ref(1);
const totalPages = ref(1);

const toastObj = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toastObj.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('commentListToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const fetchComments = async (pageNum = 1) => {
    try {
        isLoading.value = true;
        let url = `/admin/post-comments?page=${pageNum}`;
        if (statusFilter.value !== 'all') {
            url += `&status=${statusFilter.value}`;
        }
        if (searchQuery.value.trim()) {
            url += `&search=${encodeURIComponent(searchQuery.value.trim())}`;
        }
        const response = await api.get(url);
        if (response.data && response.data.status === 'success') {
            comments.value = response.data.data.data || [];
            page.value = response.data.data.current_page || 1;
            totalPages.value = response.data.data.last_page || 1;
        }
    } catch (error) {
        showToast('Lỗi tải danh sách bình luận!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

const approveComment = async (id) => {
    try {
        const res = await api.put(`/admin/post-comments/${id}/approve`);
        if (res.data.status === 'success') {
            showToast('Đã duyệt bình luận!', 'success');
            await fetchComments(page.value);
        }
    } catch (error) {
        showToast('Duyệt bình luận thất bại!', 'danger');
    }
};

const deleteComment = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa bình luận?',
        text: 'Bình luận này sẽ bị xóa khỏi cơ sở dữ liệu.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/admin/post-comments/${id}`);
            if (res.data.status === 'success') {
                showToast(res.data?.message || 'Đã xóa bình luận!', 'success');
                await fetchComments(page.value);
            }
        } catch (error) {
            showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
        }
    }
};

const handleFilterChange = () => {
    fetchComments(1);
};

const handleSearch = () => {
    fetchComments(1);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
};

onMounted(() => {
    fetchComments(1);
});
</script>

<template>
  <div class="comment-admin-page">
    <!-- Page Header -->
    <div class="page-header animate-in">
        <div class="header-info">
            <h1 class="page-title">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Kiểm duyệt bình luận bài viết
            </h1>
            <p class="page-subtitle">Xem, phê duyệt, hoặc gỡ bỏ các bình luận của người dùng trên bài viết</p>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
        <div class="search-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input
                type="text"
                v-model="searchQuery"
                @input="handleSearch"
                placeholder="Tìm bình luận, bài viết, người dùng..."
                class="search-input"
            />
        </div>

        <div class="filter-controls">
            <select v-model="statusFilter" @change="handleFilterChange" class="status-select">
                <option value="all">Tất cả trạng thái</option>
                <option value="pending">Chờ kiểm duyệt</option>
                <option value="approved">Đã phê duyệt</option>
            </select>
        </div>
    </div>

    <!-- Comments Table -->
    <AdminTableSkeleton v-if="isLoading" :columns="6" :rows="5" />

    <div v-else class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Người dùng</th>
                        <th>Bài viết</th>
                        <th>Nội dung bình luận</th>
                        <th>Ngày đăng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in comments" :key="c.comment_id">
                        <td>
                            <div class="user-cell">
                                <img :src="c.user?.avatar_url || 'https://placehold.co/40x40?text=U'" alt="avatar" class="user-avatar" />
                                <div class="user-info">
                                    <span class="user-name">{{ c.user?.full_name || 'Ẩn danh' }}</span>
                                    <span class="user-email">{{ c.user?.email || 'N/A' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <router-link :to="`/posts/${c.post?.slug}`" class="post-cell">
                                <span class="post-title" :title="c.post?.title">{{ c.post?.title || 'Bài viết đã bị xóa' }}</span>
                            </router-link>
                        </td>
                        <td>
                            <div class="comment-content-cell">
                                <p class="comment-text" :title="c.content">{{ c.content }}</p>
                            </div>
                        </td>
                        <td>{{ formatDate(c.created_at) }}</td>
                        <td>
                            <span class="status-badge" :class="c.is_approved ? 'approved' : 'pending'">
                                {{ c.is_approved ? 'Đã duyệt' : 'Chờ duyệt' }}
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button 
                                    v-if="!c.is_approved" 
                                    @click="approveComment(c.comment_id)" 
                                    class="btn-action approve" 
                                    title="Phê duyệt bình luận"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </button>
                                <button @click="deleteComment(c.comment_id)" class="btn-action delete" title="Xóa bình luận">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 12-2h4a2 2 0 0 12 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="comments.length === 0">
                        <td colspan="6" class="text-center py-5 text-muted">
                            Không tìm thấy bình luận nào phù hợp.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-container" v-if="totalPages > 1">
            <button class="btn-page" :disabled="page === 1" @click="fetchComments(page - 1)">
                &lt; Trước
            </button>
            <span class="page-info">Trang {{ page }} / {{ totalPages }}</span>
            <button class="btn-page" :disabled="page === totalPages" @click="fetchComments(page + 1)">
                Sau &gt;
            </button>
        </div>
    </div>

    <!-- Bootstrap Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
        <div class="toast align-items-center border-0" :class="toastObj.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="commentListToast" role="alert">
            <div class="d-flex">
                <div class="toast-body">{{ toastObj.message }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
.comment-admin-page { font-family: var(--font-inter); padding-bottom: 2rem; }

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

.filter-controls {
    display: flex; gap: 8px;
}
.status-select {
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: white;
    color: var(--text-main);
    font-size: 0.88rem;
    font-weight: 600;
    outline: none;
}
.status-select:focus {
    border-color: #E63B6F;
}

/* Table */
.table-container {
    padding: 0;
    border-radius: 12px;
    overflow: hidden;
}
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

/* Custom cells */
.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-color);
}
.user-info {
    display: flex;
    flex-direction: column;
}
.user-name {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-main);
}
.user-email {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.post-cell {
    display: block;
    max-width: 420px;
    color: var(--text-main);
    text-decoration: none;
}
.post-title {
    font-weight: 700;
    font-size: 0.9rem;
    line-height: 1.45;
    color: var(--text-main);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
.post-cell:hover .post-title {
    color: var(--primary);
}

.comment-content-cell {
    max-width: 300px;
}
.comment-text {
    font-size: 0.88rem;
    color: var(--text-main);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-all;
}

.status-badge {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.status-badge.approved { background: rgba(16, 185, 129, 0.08); color: #10b981; }
.status-badge.pending { background: #fef3c7; color: #d97706; }

.action-buttons { display: flex; gap: 8px; }
.btn-action {
    background: none; border: none; padding: 6px; border-radius: 6px;
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.btn-action.approve { color: #10b981; background: rgba(16, 185, 129, 0.08); }
.btn-action.delete { color: var(--coral); background: #fee2e2; }
.btn-action:hover { transform: scale(1.1); }

.loading-state {
    text-align: center;
    padding: 60px;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    padding: 16px 24px;
    background: var(--ocean-deepest);
    border-top: 1px solid var(--border-color);
}
.btn-page {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s;
}
.btn-page:hover:not(:disabled) {
    border-color: #E63B6F;
    color: #E63B6F;
}
.btn-page:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-info {
    font-size: 0.85rem;
    color: var(--text-muted);
}
</style>
