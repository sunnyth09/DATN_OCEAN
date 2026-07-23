<script setup>
import { ref, nextTick, onMounted } from 'vue';
import api from '@/axios';
import { Toast } from 'bootstrap';
import Swal from 'sweetalert2';
import { getAbsoluteUrl, getStorageUrl } from '@/utils/url';

const toastData = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toastData.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('reviewToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};
const toast = {
  success: (msg) => showToast(msg, 'success'),
  error: (msg) => showToast(msg, 'danger'),
};

// ─── State ───────────────────────────────────────────────────────────────────
const reviews    = ref([]);
const loading    = ref(true);
const searchQuery = ref('');
const filterStatus = ref('all');   // 'all' | 'approved' | 'pending'
const filterRating = ref('');      // '' | 1-5

const pagination = ref({ current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null, total: 0 });


// ─── Helpers ─────────────────────────────────────────────────────────────────
const formatDate = (d) => d ? new Date(d).toLocaleString('vi-VN', {
  day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
}) : '—';

const avatarUrl = (path) => {
  if (!path) return 'https://ui-avatars.com/api/?name=U&background=1d4ed8&color=fff&size=40';
  return getAbsoluteUrl(path);
};

const thumbUrl = (path) => {
  if (!path) return 'https://via.placeholder.com/48x48?text=SP';
  return getStorageUrl(path);
};

// ─── Fetch ────────────────────────────────────────────────────────────────────
const fetchReviews = async (page = 1) => {
  loading.value = true;
  try {
    const res = await api.get('/admin/reviews', {
      params: {
        page,
        status: filterStatus.value,
        rating: filterRating.value || undefined,
        search: searchQuery.value || undefined,
      }
    });
    if (res.data.status === 'success') {
      reviews.value = res.data.data.data;
      const d = res.data.data;
      pagination.value = {
        current_page: d.current_page,
        last_page:    d.last_page,
        prev_page_url: d.prev_page_url,
        next_page_url: d.next_page_url,
        total: d.total,
      };
    }
  } catch (e) {
    console.error(e);
    toast.error('Không thể tải danh sách đánh giá');
  } finally {
    loading.value = false;
  }
};

const changePage = (p) => {
  if (p >= 1 && p <= pagination.value.last_page) fetchReviews(p);
};

const applyFilter = () => fetchReviews(1);

// ─── Actions ──────────────────────────────────────────────────────────────────
const toggleApprove = async (review) => {
  const endpoint = review.is_approved ? 'reject' : 'approve';
  const label    = review.is_approved ? 'Ẩn'    : 'Duyệt';
  
  const result = await Swal.fire({
      title: 'Xác nhận',
      text: `${label} đánh giá này?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Đồng ý',
      cancelButtonText: 'Hủy'
  });
  if (!result.isConfirmed) return;

  try {
    await api.put(`/admin/reviews/${review.comment_id}/${endpoint}`);
    review.is_approved = review.is_approved ? 0 : 1;
    toast.success(`Đã ${label.toLowerCase()} đánh giá thành công!`);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Thao tác thất bại');
  }
};

const deleteReview = async (review) => {
  const result = await Swal.fire({
      title: 'Khuyên cáo',
      text: 'Xóa đánh giá này? Hành động này không thể hoàn tác!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Xóa',
      cancelButtonText: 'Hủy'
  });
  if (!result.isConfirmed) return;

  try {
    await api.delete(`/admin/reviews/${review.comment_id}`);
    reviews.value = reviews.value.filter(r => r.comment_id !== review.comment_id);
    pagination.value.total = Math.max(0, pagination.value.total - 1);
    toast.success('Đã xóa đánh giá!');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Xóa thất bại');
  }
};

onMounted(() => fetchReviews());
</script>

<template>
  <div class="admin-reviews">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Đánh giá</h1>
        <p class="page-subtitle">Kiểm duyệt và quản lý đánh giá sản phẩm từ khách hàng</p>
      </div>
      <div class="header-badge">
        {{ pagination.total }} đánh giá
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
      <!-- Search -->
      <div class="search-wrap">
        <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Tìm sản phẩm hoặc khách hàng..."
          class="search-input"
          @keyup.enter="applyFilter"
        />
      </div>

      <!-- Status tabs -->
      <div class="filter-tabs">
        <button v-for="tab in [{ v:'all', l:'Tất cả' }, { v:'approved', l:'Đã duyệt' }, { v:'pending', l:'Chờ duyệt' }]"
          :key="tab.v"
          class="tab-btn"
          :class="{ 'tab-btn--active': filterStatus === tab.v }"
          @click="filterStatus = tab.v; applyFilter()"
        >{{ tab.l }}</button>
      </div>

      <!-- Star filter -->
      <div class="star-filter">
        <button
          v-for="s in [0, 5, 4, 3, 2, 1]"
          :key="s"
          class="star-btn"
          :class="{ 'star-btn--active': filterRating == s && s > 0 || (s === 0 && !filterRating) }"
          @click="filterRating = s > 0 ? s : ''; applyFilter()"
        >
          <template v-if="s > 0">{{ s }} ⭐</template>
          <template v-else>Tất cả ⭐</template>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Đang tải đánh giá...</span>
    </div>

    <!-- Table -->
    <div v-else class="table-wrap">
      <table class="review-table">
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th>Khách hàng</th>
            <th>Đánh giá</th>
            <th>Nội dung</th>
            <th>Ngày gửi</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="reviews.length === 0">
            <td colspan="7" class="empty-cell">
              <div class="empty-state">
                <p>Không có đánh giá nào phù hợp</p>
              </div>
            </td>
          </tr>

          <tr v-for="r in reviews" :key="r.comment_id" class="review-row" :class="{ 'row--pending': !r.is_approved }">
            <!-- Sản phẩm -->
            <td>
              <div class="product-cell">
                <img :src="thumbUrl(r.product?.thumbnail_url)" class="product-thumb" alt="" />
                <span class="product-name">{{ r.product?.name || '—' }}</span>
              </div>
            </td>

            <!-- Khách hàng -->
            <td>
              <div class="user-cell">
                <img :src="avatarUrl((r.commenter_info || r.user)?.avatar_url)" class="user-avatar" alt="" />
                <div>
                  <div class="user-name">{{ (r.commenter_info || r.user)?.full_name || 'Ẩn danh' }}</div>
                  <div class="user-email">{{ (r.commenter_info || r.user)?.email || '' }}</div>
                </div>
              </div>
            </td>

            <!-- Stars -->
            <td>
              <div class="stars-row">
                <span v-for="s in 5" :key="s" class="star" :class="{ 'star--filled': s <= r.rating }">★</span>
              </div>
              <span class="rating-num">{{ r.rating }}/5</span>
            </td>

            <!-- Nội dung -->
            <td>
              <p class="review-content">{{ r.content || '(Không có nội dung)' }}</p>
            </td>

            <!-- Ngày -->
            <td class="date-cell">{{ formatDate(r.created_at) }}</td>

            <!-- Trạng thái -->
            <td>
              <span class="status-badge" :class="r.is_approved ? 'badge--approved' : 'badge--pending'">
                {{ r.is_approved ? 'Đã duyệt' : 'Chờ duyệt' }}
              </span>
            </td>

            <!-- Thao tác -->
            <td>
              <div class="action-btns">
                <button
                  class="btn-action"
                  :class="r.is_approved ? 'btn-warn' : 'btn-success'"
                  :title="r.is_approved ? 'Ẩn đánh giá' : 'Duyệt đánh giá'"
                  @click="toggleApprove(r)"
                >
                  {{ r.is_approved ? 'Ẩn' : 'Duyệt' }}
                </button>
                <button class="btn-action btn-danger" title="Xóa" @click="deleteReview(r)">
                  Xóa
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button class="page-btn" :disabled="!pagination.prev_page_url" @click="changePage(pagination.current_page - 1)">← Trước</button>
      <span class="page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button class="page-btn" :disabled="!pagination.next_page_url" @click="changePage(pagination.current_page + 1)">Tiếp →</button>
    </div>

    <!-- Bootstrap Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
      <div class="toast align-items-center border-0" :class="toastData.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="reviewToast" role="alert">
        <div class="d-flex">
          <div class="toast-body">{{ toastData.message }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-reviews {
  padding: 28px;
  min-height: calc(100vh - 70px);
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 4px;
}
.page-subtitle {
  color: #6c757d;
  font-size: 0.92rem;
  margin: 0;
}
.header-badge {
  background: #1d4ed8;
  color: #fff;
  padding: 6px 18px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.88rem;
  white-space: nowrap;
}

.filters-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  background: var(--card-bg);
  border: 1px solid #e9ecef;
  border-radius: 12px;
  padding: 14px 18px;
  margin-bottom: 20px;
}

.search-wrap {
  position: relative;
  flex: 1 1 220px;
}
.search-icon {
  position: absolute;
  top: 50%; left: 12px;
  transform: translateY(-50%);
  color: #aaa;
}
.search-input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  transition: border 0.2s;
}
.search-input:focus { border-color: #1d4ed8; }

.filter-tabs {
  display: flex;
  gap: 6px;
}
.tab-btn {
  padding: 7px 14px;
  border-radius: 8px;
  border: 1px solid #dee2e6;
  background: var(--card-bg);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  color: #495057;
}
.tab-btn:hover { background: #f0f4ff; border-color: #1d4ed8; color: #1d4ed8; }
.tab-btn--active { background: #1d4ed8 !important; color: #fff !important; border-color: #1d4ed8 !important; }

.star-filter {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.star-btn {
  padding: 5px 11px;
  border-radius: 7px;
  border: 1px solid #dee2e6;
  background: var(--card-bg);
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.2s;
  color: #555;
}
.star-btn:hover { border-color: #f59e0b; color: #f59e0b; }
.star-btn--active { background: #fef9c3 !important; border-color: #f59e0b !important; color: #b45309 !important; font-weight: 600; }

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 60px;
  color: #6c757d;
}
.spinner {
  width: 28px; height: 28px;
  border: 3px solid #dee2e6;
  border-top-color: #1d4ed8;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

 
.table-wrap {
  background: var(--card-bg);
  border-radius: 12px;
  border: 1px solid #e9ecef;
  overflow: hidden;
}
.review-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.review-table thead tr {
  background: var(--surface-container);
}
.review-table th {
  padding: 13px 16px;
  text-align: left;
  font-weight: 600;
  color: #374151;
  font-size: 0.83rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid #e9ecef;
  white-space: nowrap;
}
.review-row {
  border-bottom: 1px solid #f0f2f5;
  transition: background 0.15s;
}
.review-row:hover { background: #fafbff; }
.review-row.row--pending { background: #fffbeb; }
.review-table td {
  padding: 13px 16px;
  vertical-align: middle;
  color: #374151;
}

/* Product cell */
.product-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 160px;
  max-width: 210px;
}
.product-thumb {
  width: 46px; height: 46px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
  flex-shrink: 0;
}
.product-name {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-main);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 140px;
}
.user-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.user-name { font-weight: 500; font-size: 0.88rem; }
.user-email { font-size: 0.78rem; color: var(--text-light); }

/* Stars */
.stars-row { display: flex; gap: 1px; }
.star { font-size: 1rem; color: #d1d5db; }
.star--filled { color: #f59e0b; }
.rating-num { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; display: block; }

/* Content */
.review-content {
  margin: 0;
  max-width: 240px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.875rem;
  color: #475569;
  line-height: 1.5;
}

.date-cell {
  font-size: 0.82rem;
  color: var(--text-muted);
  white-space: nowrap;
}

/* Status badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 600;
  white-space: nowrap;
}
.badge--approved { background: #d1fae5; color: #065f46; }
.badge--pending  { background: #fef3c7; color: #92400e; }

/* Action buttons */
.action-btns { display: flex; flex-direction: column; gap: 6px; }
.btn-action {
  padding: 5px 12px;
  border-radius: 7px;
  border: none;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.1s;
  white-space: nowrap;
}
.btn-action:active { transform: scale(0.96); }
.btn-success { background: #d1fae5; color: #065f46; }
.btn-success:hover { background: #a7f3d0; }
.btn-warn    { background: #fef3c7; color: #92400e; }
.btn-warn:hover    { background: #fde68a; }
.btn-danger  { background: #fee2e2; color: #991b1b; }
.btn-danger:hover  { background: #fecaca; }

/* Empty */
.empty-cell { padding: 0 !important; }
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 60px;
  color: var(--text-light);
  font-size: 0.95rem;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 24px;
}
.page-btn {
  padding: 8px 20px;
  border-radius: 8px;
  border: 1px solid #dee2e6;
  background: var(--card-bg);
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.page-btn:hover:not(:disabled) { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
.page-btn:disabled { opacity: 0.4; cursor: default; }
.page-info { font-size: 0.88rem; color: #6c757d; }
</style>
