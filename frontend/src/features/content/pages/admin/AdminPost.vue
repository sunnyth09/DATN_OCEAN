<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/axios';
import { useToast } from '@/composables/useToast';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import Swal from 'sweetalert2';

const posts = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const selectedStatus = ref('all'); // 'all' | 'published' | 'draft' | 'hidden' | 'trashed'
const counts = ref({ all: 0, published: 0, draft: 0, hidden: 0, trashed: 0 });

// Bulk Selection
const selectedPostIds = ref([]);
const isBulkLoading = ref(false);

const { showToast } = useToast();

const statusTabs = [
    { value: 'all', label: 'Tất cả' },
    { value: 'published', label: 'Đã đăng' },
    { value: 'draft', label: 'Bản nháp' },
    { value: 'hidden', label: 'Đang ẩn' },
    { value: 'trashed', label: 'Thùng rác' },
];

const isAllSelected = computed(() => {
    return paginatedPosts.value.length > 0 && selectedPostIds.value.length === paginatedPosts.value.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedPostIds.value = [];
    } else {
        selectedPostIds.value = paginatedPosts.value.map(p => p.post_id);
    }
};

const clearSelection = () => {
    selectedPostIds.value = [];
};

const fetchCounts = async () => {
    try {
        const res = await api.get('/admin/posts/counts');
        if (res.data?.status === 'success') {
            counts.value = res.data.data;
        }
    } catch (e) {
        console.error('Lỗi tải post counts:', e);
    }
};

const fetchPosts = async () => {
    try {
        isLoading.value = true;
        selectedPostIds.value = [];

        const params = {
            search: searchQuery.value || undefined,
        };

        if (selectedStatus.value === 'trashed') {
            params.trashed = 'only';
        } else if (selectedStatus.value !== 'all') {
            params.status = selectedStatus.value;
        }

        const response = await api.get('/admin/posts', { params });
        if (response.data && response.data.data) {
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

const switchTab = (tabValue) => {
    selectedStatus.value = tabValue;
    currentPage.value = 1;
    selectedPostIds.value = [];
    fetchPosts();
    fetchCounts();
};

const filteredPosts = computed(() => {
    let result = posts.value;

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(p => (p.title && p.title.toLowerCase().includes(q)) || (p.slug && p.slug.toLowerCase().includes(q)));
    }

    return result;
});

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(10); 

watch([searchQuery], () => {
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

// Single Soft Delete
const deletePost = async (post) => {
    const result = await Swal.fire({
        title: 'Chuyển vào thùng rác?',
        html: `Bạn có chắc muốn chuyển bài viết <strong>${post.title}</strong> vào thùng rác?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/admin/posts/${post.post_id}`);
            showToast(res.data?.message || 'Đã chuyển bài viết vào thùng rác!', 'success');
            await fetchPosts();
            await fetchCounts();
        } catch (error) {
            showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
        }
    }
};

// Single Restore
const restorePost = async (post) => {
    try {
        const res = await api.post(`/admin/posts/${post.post_id}/restore`);
        showToast(res.data?.message || 'Khôi phục bài viết thành công!', 'success');
        await fetchPosts();
        await fetchCounts();
    } catch (error) {
        showToast(error.response?.data?.message || 'Khôi phục thất bại!', 'danger');
    }
};

// Single Force Delete
const forceDeletePost = async (post) => {
    const result = await Swal.fire({
        title: 'XÓA VĨNH VIỄN?',
        html: `Hành động này sẽ <strong>xóa hoàn toàn</strong> bài viết <strong>${post.title}</strong> khỏi cơ sở dữ liệu và <strong class="text-danger">KHÔNG THỂ KHÔI PHỤC</strong>.`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#b91c1c',
        confirmButtonText: 'Xác nhận xóa vĩnh viễn',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            const res = await api.delete(`/admin/posts/${post.post_id}/force`);
            showToast(res.data?.message || 'Đã xóa vĩnh viễn bài viết!', 'success');
            await fetchPosts();
            await fetchCounts();
        } catch (error) {
            showToast(error.response?.data?.message || 'Lỗi xóa vĩnh viễn!', 'danger');
        }
    }
};

// Bulk Actions
const handleBulkTrash = async () => {
    const count = selectedPostIds.value.length;
    const result = await Swal.fire({
        title: `Xóa ${count} bài viết?`,
        text: `Chuyển ${count} bài viết đã chọn vào thùng rác?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy'
    });

    if (!result.isConfirmed) return;

    isBulkLoading.value = true;
    try {
        for (const id of selectedPostIds.value) {
            await api.delete(`/admin/posts/${id}`);
        }
        showToast(`Đã chuyển ${count} bài viết vào thùng rác!`, 'success');
        selectedPostIds.value = [];
        await fetchPosts();
        await fetchCounts();
    } catch (e) {
        showToast(e.response?.data?.message || 'Lỗi thao tác hàng loạt!', 'danger');
    } finally {
        isBulkLoading.value = false;
    }
};

const handleBulkRestore = async () => {
    const count = selectedPostIds.value.length;
    isBulkLoading.value = true;
    try {
        const res = await api.post('/admin/posts/bulk-restore', { ids: selectedPostIds.value });
        showToast(res.data?.message || `Đã khôi phục ${count} bài viết!`, 'success');
        selectedPostIds.value = [];
        await fetchPosts();
        await fetchCounts();
    } catch (e) {
        showToast(e.response?.data?.message || 'Lỗi khôi phục hàng loạt!', 'danger');
    } finally {
        isBulkLoading.value = false;
    }
};

const handleBulkForceDelete = async () => {
    const count = selectedPostIds.value.length;
    const result = await Swal.fire({
        title: `XÓA VĨNH VIỄN ${count} BÀI VIẾT?`,
        text: `Hành động này sẽ xóa hoàn toàn ${count} bài viết đã chọn và KHÔNG THỂ HOÀN TÁC!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#b91c1c',
        confirmButtonText: 'Xóa vĩnh viễn',
        cancelButtonText: 'Hủy'
    });

    if (!result.isConfirmed) return;

    isBulkLoading.value = true;
    try {
        const res = await api.post('/admin/posts/bulk-force-delete', { ids: selectedPostIds.value });
        showToast(res.data?.message || `Đã xóa vĩnh viễn ${count} bài viết!`, 'success');
        selectedPostIds.value = [];
        await fetchPosts();
        await fetchCounts();
    } catch (e) {
        showToast(e.response?.data?.message || 'Lỗi xóa vĩnh viễn hàng loạt!', 'danger');
    } finally {
        isBulkLoading.value = false;
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

const formatDateTime = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    fetchPosts();
    fetchCounts();
});
</script>

<template>
    <AdminTableSkeleton v-if="isLoading" :columns="selectedStatus === 'trashed' ? 7 : 8" :rows="6" />
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
                <p class="page-subtitle">Quản lý nội dung, blog, tin tức, khuyến mãi và khôi phục bài viết đã xóa</p>
            </div>
            <router-link to="/admin/post/create" class="btn-primary" id="add-post-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Thêm bài viết
            </router-link>
        </div>

        <!-- Status Tabs Bar with Live Counts -->
        <div class="status-tabs ocean-card animate-in" style="animation-delay: 0.1s">
            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                class="status-tab"
                :class="{ active: selectedStatus === tab.value, 'tab-trash': tab.value === 'trashed' }"
                @click="switchTab(tab.value)"
            >
                <svg v-if="tab.value === 'trashed'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span>{{ tab.label }}</span>
                <strong :class="{ 'badge-trash': tab.value === 'trashed' }">{{ counts[tab.value] ?? 0 }}</strong>
            </button>
        </div>

        <!-- Filters & Search -->
        <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.15s">
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
                    {{ filteredPosts.length }} bài viết
                </span>
            </div>
        </div>

        <!-- Floating Bulk Action Bar -->
        <div v-if="selectedPostIds.length > 0" class="bulk-action-bar animate-in">
            <div class="bulk-action-info">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Đã chọn <strong>{{ selectedPostIds.length }}</strong> bài viết</span>
            </div>
            <div class="bulk-action-btns">
                <template v-if="selectedStatus === 'trashed'">
                    <button class="btn-bulk-restore" @click="handleBulkRestore" :disabled="isBulkLoading">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10"></polyline>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                        </svg>
                        <span>Khôi phục đã chọn ({{ selectedPostIds.length }})</span>
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

        <!-- Posts Table -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredPosts.length }}</strong> bài viết {{ selectedStatus === 'trashed' ? 'trong thùng rác' : 'tìm thấy' }}
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
                                    :disabled="paginatedPosts.length === 0"
                                />
                            </th>
                            <th>Hình ảnh</th>
                            <th>Tiêu đề</th>
                            <th>Danh mục</th>
                            <th>Tác giả</th>
                            <th v-if="selectedStatus !== 'trashed'">Lượt xem</th>
                            <th>{{ selectedStatus === 'trashed' ? 'Ngày xóa mềm' : 'Trạng thái' }}</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="paginatedPosts.length === 0">
                            <td :colspan="selectedStatus === 'trashed' ? 7 : 8" class="empty-cell">
                                <span class="empty-emoji">{{ selectedStatus === 'trashed' ? '🗑️' : '📝' }}</span>
                                <h3>{{ selectedStatus === 'trashed' ? 'Thùng rác trống (Không có bài viết bị xóa)' : 'Không tìm thấy bài viết' }}</h3>
                                <p class="small text-muted mt-1">{{ selectedStatus === 'trashed' ? 'Các bài viết bị xóa mềm sẽ được lưu trữ tại đây và có thể khôi phục bất cứ lúc nào.' : (searchQuery ? 'Thử tìm kiếm bằng từ khóa khác.' : 'Bắt đầu bằng cách thêm bài viết đầu tiên.') }}</p>
                            </td>
                        </tr>
                        <template v-for="p in paginatedPosts" :key="p.post_id" v-else>
                            <tr :class="{ 'row-selected': selectedPostIds.includes(p.post_id), 'row-trashed': selectedStatus === 'trashed' }">
                                <td style="text-align: center;">
                                    <input
                                        type="checkbox"
                                        class="form-check-input select-checkbox"
                                        :value="p.post_id"
                                        v-model="selectedPostIds"
                                    />
                                </td>
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
                                        <router-link v-if="selectedStatus !== 'trashed'" :to="`/admin/post/edit/${p.post_id}`" class="post-title" :title="p.title">
                                            {{ p.title }}
                                        </router-link>
                                        <span v-else class="post-title text-muted" :title="p.title">
                                            {{ p.title }}
                                        </span>
                                        <span class="badge-featured" v-if="p.is_featured">Hot</span>
                                    </div>
                                </td>
                                <td>{{ p.category ? p.category.name : 'Không có' }}</td>
                                <td>{{ p.author?.full_name || 'Không xác định' }}</td>
                                <td v-if="selectedStatus !== 'trashed'">{{ p.view_count || 0 }}</td>
                                <td>
                                    <template v-if="selectedStatus === 'trashed'">
                                        <span style="color:var(--text-muted); font-size:0.8rem">
                                            {{ formatDateTime(p.deleted_at) }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="status-badge" :class="getStatusLabel(p.status).class">
                                            {{ getStatusLabel(p.status).text }}
                                        </span>
                                    </template>
                                </td>
                                <td style="text-align: right;">
                                    <!-- Trashed Actions -->
                                    <div v-if="selectedStatus === 'trashed'" class="action-buttons justify-content-end">
                                        <button @click="restorePost(p)" class="btn-action-restore" title="Khôi phục bài viết">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="1 4 1 10 7 10"></polyline>
                                                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                            </svg>
                                            <span>Khôi phục</span>
                                        </button>
                                        <button @click="forceDeletePost(p)" class="btn-action delete" title="Xóa vĩnh viễn">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
                                    <!-- Normal Actions -->
                                    <div v-else class="action-buttons justify-content-end">
                                        <router-link :to="`/admin/post/edit/${p.post_id}`" class="btn-action edit" title="Chỉnh sửa">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </router-link>
                                        <button @click="deletePost(p)" class="btn-action delete" title="Chuyển vào thùng rác">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
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
        </div>
    </div>
</template>

<style scoped>
.post-page { font-family: var(--font-inter); padding-bottom: 2rem;}

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
    color: white; text-decoration: none;
    font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(230, 59, 111, 0.3);
    color: white;
}

/* Status Tabs Bar */
.status-tabs {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px; margin-bottom: 16px; overflow-x: auto;
}
.status-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 999px; background: var(--card-bg, #ffffff);
    color: var(--text-muted, #64748b); font-size: 0.84rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.status-tab strong {
    min-width: 20px; padding: 1px 7px; border-radius: 999px;
    background: #f1f5f9; color: #475569; font-size: 0.72rem; font-weight: 700;
}
.status-tab:hover {
    background: var(--hover-bg, #f8fafc); color: var(--text-main, #0f172a); border-color: #cbd5e1;
}
.status-tab.active {
    background: #fff0f5; color: #e63b6f; border-color: #fbcfe8; font-weight: 700;
    box-shadow: 0 2px 6px rgba(230, 59, 111, 0.12);
}
.status-tab.active strong { background: #e63b6f; color: white; }

.status-tab.tab-trash.active {
    background: #fef2f2; color: #dc2626; border-color: #fecaca;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.12);
}
.status-tab.tab-trash.active strong { background: #dc2626; color: white; }

/* Filters */
.filters-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; margin-bottom: 16px; gap: 16px;
}
.search-box {
    display: flex; align-items: center; gap: 10px;
    background: var(--ocean-deepest, #f8fafc); border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 8px; padding: 9px 14px; flex: 1; max-width: 400px;
    transition: all 0.2s;
}
.search-box:focus-within {
    border-color: #e63b6f; background: #ffffff;
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}
.search-box svg { color: var(--text-light, #94a3b8); flex-shrink: 0; }
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

/* Custom Row Styles */
.thumbnail-cell { width: 44px; height: 44px; border-radius: 8px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.thumbnail-cell img { width: 100%; height: 100%; object-fit: cover; }
.img-placeholder { color: #94a3b8; }

.post-title-cell { display: flex; align-items: flex-start; gap: 8px; max-width: 380px; }
.post-title {
    font-weight: 700; color: var(--text-main, #0f172a); font-size: 0.88rem; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; word-break: break-word; text-decoration: none;
}
.post-title:hover { color: #e63b6f; }
.badge-featured { background: #fee2e2; color: #ef4444; font-size: 0.65rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; }

.status-badge {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 9999px; font-size: 0.72rem; font-weight: 700;
}
.status-badge.active { background: #e8f5e9; color: #2e7d32; }
.status-badge.inactive { background: #f5f5f5; color: #757575; }
.status-badge.draft { background: #fef3c7; color: #d97706; }

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

/* Pagination */
.pagination-controls {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid var(--border-color, #e2e8f0);
}
.btn-page {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #ffffff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
.btn-page:hover:not(:disabled) { background: var(--hover-bg, #f4f6f8); border-color: #e63b6f; color: #e63b6f; }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #ffffff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.btn-page-number:hover:not(.active) { background: var(--hover-bg, #f4f6f8); }
.btn-page-number.active { background: #e63b6f; color: white; border-color: #e63b6f; }

.animate-in { animation: fadeSlideUp 0.35s ease both; }
@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
    .search-box { max-width: 100%; }
    .bulk-action-bar { flex-direction: column; gap: 10px; align-items: stretch; }
    .bulk-action-btns { justify-content: flex-end; }
}
</style>