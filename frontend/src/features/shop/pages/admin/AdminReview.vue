<script setup>
import { ref, nextTick, onMounted, onUnmounted, watch } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import AppIcon from '@/components/AppIcon.vue';
import { getAbsoluteUrl, getStorageUrl } from '@/utils/url';
import { sanitizeHtml } from '@/utils/sanitize';

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
const toast = {
  success: (msg) => showToast(msg, 'success'),
  error: (msg) => showToast(msg, 'danger'),
};

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');

// ─── Mode State ───────────────────────────────────────────────────────────────────
const viewMode = ref('reviews'); // 'reviews' | 'tickets'

// ─── Shared Helpers ───────────────────────────────────────────────────────────────
const formatDate = (d) => d ? new Date(d).toLocaleString('vi-VN', {
  day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
}) : '—';
const avatarUrl = (path) => {
  if (!path) return 'https://ui-avatars.com/api/?name=U&background=e63b6f&color=fff&size=40';
  return getAbsoluteUrl(path);
};
const thumbUrl = (path) => {
  if (!path) return 'https://placehold.co/48x48/f8f9fa/a1a1aa?text=SP';
  return getStorageUrl(path);
};

const parseReviewImages = (images) => {
  if (!images) return [];
  
  let parsed = images;
  
  // Keep parsing if it's a string that looks like JSON
  while (typeof parsed === 'string') {
    try {
      let temp = JSON.parse(parsed);
      if (temp === parsed) break;
      parsed = temp;
    } catch (e) {
      parsed = parsed.replace(/[\[\]"]/g, '').split(',').map(s => s.trim());
      break;
    }
  }

  // At this point, parsed should be an array
  if (Array.isArray(parsed)) {
    return parsed.map(s => {
      let cleaned = String(s).trim();
      
      // Replace all backslashes with forward slashes FIRST! (Important for Windows paths)
      cleaned = cleaned.replace(/\\/g, '/');
      
      // Remove any leftover double quotes or single quotes from double-encoding artifacts
      cleaned = cleaned.replace(/["']/g, '');
      
      // Deduplicate forward slashes
      cleaned = cleaned.replace(/\/+/g, '/');
      
      return cleaned;
    }).filter(s => s);
  }
  
  return [];
};

// ─── Review State & Logic ─────────────────────────────────────────────────────────
const reviews    = ref([]);
const reviewLoading = ref(true);
const reviewSearchQuery = ref('');
const reviewFilterStatus = ref('all');   // 'all' | 'approved' | 'pending'
const filterRating = ref('');      // '' | 1-5
const reviewPagination = ref({ current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null, total: 0 });

const fetchReviews = async (page = 1) => {
  reviewLoading.value = true;
  try {
    const res = await api.get('/admin/reviews', {
      params: {
        page,
        status: reviewFilterStatus.value,
        rating: filterRating.value || undefined,
        search: reviewSearchQuery.value || undefined,
      }
    });
    if (res.data.status === 'success') {
      reviews.value = res.data.data.data;
      const d = res.data.data;
      reviewPagination.value = {
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
    reviewLoading.value = false;
  }
};

const applyReviewFilter = () => fetchReviews(1);
const changeReviewPage = (p) => {
  if (p >= 1 && p <= reviewPagination.value.last_page) fetchReviews(p);
};

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
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
    toast.success(`Đã ${label.toLowerCase()} đánh giá thành công!`);
    window.dispatchEvent(new Event('admin-notification-received'));
  } catch (e) {
    toast.error(e.response?.data?.message || 'Thao tác thất bại');
  }
};

const deleteReview = async (review) => {
  const result = await Swal.fire({
      title: 'Khuyến cáo',
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
    reviewPagination.value.total = Math.max(0, reviewPagination.value.total - 1);
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
    toast.success('Đã xóa đánh giá!');
    window.dispatchEvent(new Event('admin-notification-received'));
  } catch (e) {
    toast.error(e.response?.data?.message || 'Xóa thất bại');
  }
};

const showReviewModal = ref(false);
const selectedReview = ref(null);
const openReviewModal = (review) => {
  selectedReview.value = review;
  showReviewModal.value = true;
};
const closeReviewModal = () => {
  showReviewModal.value = false;
  selectedReview.value = null;
};

// ─── Ticket State & Logic ─────────────────────────────────────────────────────────
const tickets = ref([]);
const ticketLoading = ref(false);
const actionLoading = ref(false);
const ticketSearchQuery = ref('');
const ticketFilterStatus = ref('all');

const showTicketModal = ref(false);
const selectedTicket = ref(null);
const replyContent = ref('');
const updateStatus = ref('pending');

let hasFetchedTickets = false;

const fetchTickets = async () => {
  if (ticketLoading.value) return;
  ticketLoading.value = true;
  hasFetchedTickets = true;
  try {
    const res = await api.get('/admin/tickets', {
      params: {
        search: ticketSearchQuery.value,
        status: ticketFilterStatus.value
      }
    });
    if (res.data.status === 'success') {
      tickets.value = res.data.data.data || [];
    }
  } catch (error) {
    console.error("Lỗi lấy danh sách khiếu nại:", error);
    toast.error('Không thể lấy danh sách khiếu nại');
  } finally {
    ticketLoading.value = false;
  }
};

const applyTicketFilter = () => fetchTickets();

const getStatusText = (status) => {
  const map = { 'pending': 'Chờ xử lý', 'processing': 'Đang xử lý', 'resolved': 'Đã giải quyết', 'closed': 'Đã đóng' };
  return map[status] || status;
};

const openTicketModal = (ticket) => {
  selectedTicket.value = ticket;
  replyContent.value = ticket.admin_reply || '';
  updateStatus.value = ticket.status;
  showTicketModal.value = true;
};

const closeTicketModal = () => {
  showTicketModal.value = false;
  selectedTicket.value = null;
};

const submitReply = async () => {
  if (!selectedTicket.value) return;
  actionLoading.value = true;
  try {
    const res = await api.put(`/admin/tickets/${selectedTicket.value.ticket_id}`, {
      status: updateStatus.value,
      admin_reply: replyContent.value
    });

    if (res.data.status === 'success') {
      toast.success('Đã cập nhật khiếu nại');
      window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
      window.dispatchEvent(new Event('admin-notification-received'));
      closeTicketModal();
      fetchTickets();
    }
  } catch (error) {
    console.error("Lỗi cập nhật:", error);
    toast.error('Có lỗi xảy ra khi cập nhật');
  } finally {
    actionLoading.value = false;
  }
};

// ─── Image Lightbox (Phóng to ảnh) ────────────────────────────────────────────────
const previewImageUrl = ref('');
const showImageLightbox = ref(false);

const openImageLightbox = (url) => {
  if (!url) return;
  previewImageUrl.value = url;
  showImageLightbox.value = true;
};

const closeImageLightbox = () => {
  showImageLightbox.value = false;
  previewImageUrl.value = '';
};

const openImage = (path) => {
  openImageLightbox(thumbUrl(path));
};

const onKeydown = (e) => {
  if (e.key === 'Escape') {
    if (showImageLightbox.value) {
      closeImageLightbox();
    } else if (showReviewModal.value) {
      closeReviewModal();
    } else if (showTicketModal.value) {
      closeTicketModal();
    }
  }
};

watch(viewMode, (newVal) => {
  if (newVal === 'reviews' && reviews.value.length === 0) fetchReviews();
  else if (newVal === 'tickets' && !hasFetchedTickets) fetchTickets();
});

onMounted(() => {
  fetchReviews();
  window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div class="admin-reviews">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Đánh giá & Khiếu nại</h1>
        <p class="page-subtitle">Kiểm duyệt đánh giá sản phẩm và xử lý khiếu nại từ khách hàng</p>
      </div>
      <div class="header-badge">
        <span v-if="viewMode === 'reviews'">{{ reviewPagination.total }} đánh giá</span>
        <span v-else>{{ tickets.length }} khiếu nại</span>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
      <!-- Mode tabs (Review/Ticket) -->
      <div class="filter-tabs mode-tabs" style="margin-right: auto; padding-right: 12px; border-right: 1px solid #e9ecef;">
        <button class="tab-btn" :class="{ 'tab-btn--active': viewMode === 'reviews' }" @click="viewMode = 'reviews'">
           Đánh giá
        </button>
        <button class="tab-btn" :class="{ 'tab-btn--active': viewMode === 'tickets' }" @click="viewMode = 'tickets'">
           Khiếu nại
        </button>
      </div>

      <!-- Search -->
      <div class="search-wrap">
        <AppIcon name="search" size="16" class="search-icon" />
        <input
          v-show="viewMode === 'reviews'"
          v-model="reviewSearchQuery"
          type="text"
          placeholder="Tìm sản phẩm hoặc khách hàng..."
          class="search-input"
          @keyup.enter="applyReviewFilter"
        />
        <input
          v-show="viewMode === 'tickets'"
          v-model="ticketSearchQuery"
          type="text"
          placeholder="Tìm mã đơn, tên, lý do..."
          class="search-input"
          @keyup.enter="applyTicketFilter"
        />
      </div>

      <!-- Status tabs for Reviews -->
      <div v-show="viewMode === 'reviews'" class="filter-tabs">
        <button v-for="tab in [{ v:'all', l:'Tất cả' }, { v:'approved', l:'Đã duyệt' }, { v:'pending', l:'Chờ duyệt' }]"
          :key="tab.v"
          class="tab-btn"
          :class="{ 'tab-btn--active': reviewFilterStatus === tab.v }"
          @click="reviewFilterStatus = tab.v; applyReviewFilter()"
        >{{ tab.l }}</button>
      </div>

      <!-- Status tabs for Tickets -->
      <div v-show="viewMode === 'tickets'" class="filter-tabs">
        <button v-for="tab in [{ v:'all', l:'Tất cả' }, { v:'pending', l:'Chờ xử lý' }, { v:'processing', l:'Đang xử lý' }, { v:'resolved', l:'Đã giải quyết' }, { v:'closed', l:'Đã đóng' }]"
          :key="tab.v"
          class="tab-btn"
          :class="{ 'tab-btn--active': ticketFilterStatus === tab.v }"
          @click="ticketFilterStatus = tab.v; applyTicketFilter()"
        >{{ tab.l }}</button>
      </div>

      <!-- Star filter (Only Reviews) -->
      <div v-show="viewMode === 'reviews'" class="star-filter">
        <button
          v-for="s in [0, 5, 4, 3, 2, 1]"
          :key="s"
          class="star-btn"
          :class="{ 'star-btn--active': filterRating == s && s > 0 || (s === 0 && !filterRating) }"
          @click="filterRating = s > 0 ? s : ''; applyReviewFilter()"
        >
          <template v-if="s > 0">{{ s }} ⭐</template>
          <template v-else>Tất cả ⭐</template>
        </button>
      </div>
    </div>

    <!-- ========================================== -->
    <!--                 REVIEW VIEW                -->
    <!-- ========================================== -->
    <div v-show="viewMode === 'reviews'">
      <AdminTableSkeleton v-if="reviewLoading" :columns="6" :rows="5" />

      <div v-else class="table-wrap">
        <table class="review-table">
          <thead>
            <tr>
              <th>Sản phẩm</th>
              <th>Khách hàng</th>
              <th>Đánh giá</th>
              <th>Có ảnh</th>
              <th>Nội dung</th>
              <th>Ngày gửi</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="reviews.length === 0">
              <td colspan="8" class="empty-cell">
                <div class="empty-state">
                  <p>Không có đánh giá nào phù hợp</p>
                </div>
              </td>
            </tr>

            <tr v-for="r in reviews" :key="r.comment_id" class="review-row" :class="{ 'row--pending': !r.is_approved }">
              <!-- Sản phẩm -->
              <td>
                <div class="product-cell">
                  <img :src="thumbUrl(r.product?.thumbnail_url)" @error="$event.target.src='https://placehold.co/48x48/f8f9fa/a1a1aa?text=SP'" class="product-thumb" alt="" />
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
              <!-- Có ảnh -->
              <td class="text-center">
                <div v-if="parseReviewImages(r.images).length > 0" class="table-images-wrap">
                  <img
                    v-for="(img, idx) in parseReviewImages(r.images).slice(0, 3)"
                    :key="idx"
                    :src="getStorageUrl(img)"
                    class="table-review-img"
                    alt="img"
                    title="Nhấn để xem ảnh to"
                    @click.stop="openImageLightbox(getStorageUrl(img))"
                  />
                  <span v-if="parseReviewImages(r.images).length > 3" class="more-images-count">+{{ parseReviewImages(r.images).length - 3 }}</span>
                </div>
                <span v-else class="text-muted">❌</span>
              </td>
              <!-- Nội dung -->
              <td>
                <div class="review-content" v-html="sanitizeHtml(r.content || '(Không có nội dung)')"></div>
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
                  <button class="btn-action btn-view" title="Xem chi tiết" @click="openReviewModal(r)">
                    Xem
                  </button>
                  <button class="btn-action" :class="r.is_approved ? 'btn-danger' : 'btn-success'" :title="r.is_approved ? 'Ẩn đánh giá' : 'Duyệt đánh giá'" @click="toggleApprove(r)">
                    {{ r.is_approved ? 'Ẩn' : 'Duyệt' }}
                  </button>
                  <button class="btn-action btn-warn" title="Xóa" @click="deleteReview(r)">
                    Xóa
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="reviewPagination.last_page > 1">
        <button class="page-btn" :disabled="!reviewPagination.prev_page_url" @click="changeReviewPage(reviewPagination.current_page - 1)">← Trước</button>
        <span class="page-info">Trang {{ reviewPagination.current_page }} / {{ reviewPagination.last_page }}</span>
        <button class="page-btn" :disabled="!reviewPagination.next_page_url" @click="changeReviewPage(reviewPagination.current_page + 1)">Tiếp →</button>
      </div>
    </div>


    <!-- ========================================== -->
    <!--                 TICKET VIEW                -->
    <!-- ========================================== -->
    <div v-show="viewMode === 'tickets'">
      <div v-if="ticketLoading" class="loading-state">
        <div class="spinner"></div>
        <span>Đang tải khiếu nại...</span>
      </div>

      <div v-else class="table-wrap">
        <table class="review-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Khách hàng</th>
              <th>Mã đơn</th>
              <th>Lý do</th>
              <th>Ngày gửi</th>
              <th>Trạng thái</th>
              <th class="text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="tickets.length === 0">
              <td colspan="7" class="empty-cell">
                <div class="empty-state">
                  <p>Không có khiếu nại nào phù hợp</p>
                </div>
              </td>
            </tr>

            <tr v-for="ticket in tickets" :key="ticket.ticket_id" class="review-row">
              <td>#{{ ticket.ticket_id }}</td>
              <td>
                <div class="user-cell">
                  <div>
                    <div class="user-name">{{ ticket.user?.full_name || 'Khách' }}</div>
                    <div class="user-email">{{ ticket.user?.email }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span v-if="ticket.order" class="order-code">#{{ ticket.order.order_code }}</span>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <div class="reason-text" :title="ticket.reason">{{ ticket.reason }}</div>
              </td>
              <td class="date-cell">{{ formatDate(ticket.created_at) }}</td>
              <td>
                <span class="status-badge-ticket" :class="ticket.status">
                  {{ getStatusText(ticket.status) }}
                </span>
              </td>
              <td class="text-right">
                <div class="action-btns" style="align-items: flex-end;">
                  <button
                    class="btn-action"
                    :class="['pending', 'processing'].includes(ticket.status) ? 'btn-primary-action' : 'btn-view'"
                    @click="openTicketModal(ticket)"
                  >
                    {{ ['pending', 'processing'].includes(ticket.status) ? 'Xử lý' : 'Xem' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Review Detail Modal -->
    <Transition name="modal">
      <div v-if="showReviewModal && selectedReview" class="modal-overlay" @click.self="closeReviewModal">
        <div class="modal-box" style="max-width: 600px;">
          <div class="modal-header">
            <h3>Chi tiết Đánh giá #{{ selectedReview.comment_id }}</h3>
            <button class="btn-close-modal" @click="closeReviewModal">&times;</button>
          </div>

          <div class="modal-body">
            <div class="info-grid">
              <div class="info-group">
                <label>Khách hàng:</label>
                <p>{{ (selectedReview.commenter_info || selectedReview.user)?.full_name }}</p>
              </div>
              <div class="info-group">
                <label>Sản phẩm:</label>
                <p>{{ selectedReview.product?.name }}</p>
              </div>
              <div class="info-group">
                <label>Đánh giá:</label>
                <p>
                  <span v-for="s in 5" :key="s" style="color: #F59E0B">{{ s <= selectedReview.rating ? '★' : '☆' }}</span>
                </p>
              </div>
              <div class="info-group">
                <label>Ngày gửi:</label>
                <p>{{ formatDate(selectedReview.created_at) }}</p>
              </div>
            </div>

            <div class="ticket-content" style="margin-top: 16px;">
              <h4>Nội dung đánh giá:</h4>
              <div class="description-box" v-html="sanitizeHtml(selectedReview.content || '(Không có nội dung)')"></div>
              <div class="image-box" v-if="parseReviewImages(selectedReview.images).length > 0" style="margin-top: 16px;">
                 <p class="image-box-title"><strong>Ảnh đính kèm (nhấn vào ảnh để xem to):</strong></p>
                 <div class="review-images">
                   <div
                     v-for="(img, idx) in parseReviewImages(selectedReview.images)"
                     :key="idx"
                     class="modal-img-container"
                     title="Nhấn để xem ảnh phóng to"
                     @click="openImageLightbox(getStorageUrl(img))"
                   >
                     <img :src="getStorageUrl(img)" class="admin-review-img modal-review-img" alt="Review image" />
                     <div class="zoom-overlay">
                       <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                     </div>
                   </div>
                 </div>
              </div>
            </div>
          </div>
          
          <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 8px;">
            <button class="btn-action" :class="selectedReview.is_approved ? 'btn-warn' : 'btn-success'" @click="toggleApprove(selectedReview); closeReviewModal()">
              {{ selectedReview.is_approved ? 'Ẩn đánh giá' : 'Duyệt đánh giá' }}
            </button>
            <button class="btn-action" style="background: #e2e8f0; color: #475569;" @click="closeReviewModal">Đóng</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Ticket Detail/Reply Modal -->
    <Transition name="modal">
      <div v-if="showTicketModal && selectedTicket" class="modal-overlay" @click.self="closeTicketModal">
        <div class="modal-box">
          <div class="modal-header">
            <h3>Chi tiết Khiếu nại #{{ selectedTicket.ticket_id }}</h3>
            <button class="btn-close-modal" @click="closeTicketModal">&times;</button>
          </div>

          <div class="modal-body">
            <div class="info-grid">
              <div class="info-group">
                <label>Khách hàng:</label>
                <p>{{ selectedTicket.user?.full_name }} ({{ selectedTicket.user?.email }})</p>
              </div>
              <div class="info-group">
                <label>Đơn hàng:</label>
                <p>
                  <span v-if="selectedTicket.order" class="order-code">#{{ selectedTicket.order.order_code }}</span>
                  <span v-else>Không xác định</span>
                </p>
              </div>
              <div class="info-group">
                <label>Ngày gửi:</label>
                <p>{{ formatDate(selectedTicket.created_at) }}</p>
              </div>
              <div class="info-group">
                <label>Trạng thái hiện tại:</label>
                <p>
                   <span class="status-badge-ticket" :class="selectedTicket.status">
                    {{ getStatusText(selectedTicket.status) }}
                  </span>
                </p>
              </div>
            </div>

            <div class="ticket-content">
              <h4>Lý do: {{ selectedTicket.reason }}</h4>
              <div class="description-box">
                {{ selectedTicket.description }}
              </div>
              <div class="image-box" v-if="selectedTicket.image_url">
                 <p class="image-box-title"><strong>Ảnh minh chứng (nhấn vào ảnh để xem to):</strong></p>
                 <div
                   class="modal-img-container evidence-container"
                   title="Nhấn để xem ảnh phóng to"
                   @click="openImageLightbox(thumbUrl(selectedTicket.image_url))"
                 >
                   <img :src="thumbUrl(selectedTicket.image_url)" alt="Minh chứng" class="evidence-img" />
                   <div class="zoom-overlay">
                     <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                   </div>
                 </div>
              </div>
            </div>

            <div class="reply-section">
              <h4>Phản hồi từ Admin</h4>
              <textarea
                v-model="replyContent"
                placeholder="Nhập nội dung phản hồi cho khách hàng..."
                class="reply-input"
                rows="4"
              ></textarea>

              <div class="status-update mt-3">
                <label>Cập nhật trạng thái:</label>
                <select v-model="updateStatus" class="filter-select w-100">
                  <option value="pending">Chờ xử lý</option>
                  <option value="processing">Đang xử lý</option>
                  <option value="resolved">Đã giải quyết</option>
                  <option value="closed">Đã đóng</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeTicketModal">Đóng</button>
            <button class="btn btn-primary" @click="submitReply" :disabled="actionLoading">
              <span v-if="actionLoading" class="spinner-small"></span>
              <span v-else>Cập nhật & Phản hồi</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Image Lightbox Modal -->
    <Teleport to="body">
      <Transition name="lightbox-fade">
        <div
          v-if="showImageLightbox && previewImageUrl"
          class="image-lightbox-overlay"
          @click.self="closeImageLightbox"
          role="dialog"
          aria-modal="true"
        >
          <button class="lightbox-close-btn" @click="closeImageLightbox" title="Đóng (ESC)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
          <div class="lightbox-content" @click.self="closeImageLightbox">
            <img :src="previewImageUrl" class="lightbox-img" alt="Xem ảnh phóng to" />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.admin-reviews {
  padding: 24px 20px;
  min-height: calc(100vh - 70px);
  box-sizing: border-box;
  max-width: 100%;
}

.review-images {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 8px;
}
.admin-review-img {
  width: 50px;
  height: 50px;
  border-radius: 6px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
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
  background: var(--primary);
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
  box-sizing: border-box;
}
.search-input:focus { border-color: var(--primary); }

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
.tab-btn:hover { background: #fdf2f8; border-color: var(--primary); color: var(--primary); }
.tab-btn--active { background: var(--primary) !important; color: #fff !important; border-color: var(--primary) !important; }

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
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Table responsive wrapper */
.table-wrap {
  background: var(--card-bg);
  border-radius: 12px;
  border: 1px solid #e9ecef;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.table-wrap::-webkit-scrollbar {
  height: 8px;
}
.table-wrap::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}
.table-wrap::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.table-wrap::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.review-table {
  width: 100%;
  min-width: 960px;
  border-collapse: collapse;
  font-size: 0.88rem;
}
.review-table thead tr {
  background: var(--surface-container);
}
.review-table th {
  padding: 13px 14px;
  text-align: left;
  font-weight: 600;
  color: #374151;
  font-size: 0.82rem;
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
.review-row.row--pending { background: #fff0f6; }
.review-table td {
  padding: 12px 14px;
  vertical-align: middle;
  color: #374151;
}

/* Product cell */
.product-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 140px;
  max-width: 200px;
}
.product-thumb {
  width: 44px; height: 44px;
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
  min-width: 130px;
}
.user-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.user-name { font-weight: 500; font-size: 0.86rem; }
.user-email { font-size: 0.76rem; color: var(--text-light); }

/* Stars */
.stars-row { display: flex; gap: 1px; }
.star { font-size: 0.95rem; color: #d1d5db; }
.star--filled { color: #f59e0b; }
.rating-num { font-size: 0.76rem; color: var(--text-muted); margin-top: 2px; display: block; }

/* Table images */
.table-images-wrap {
  display: inline-flex;
  gap: 4px;
  justify-content: center;
  align-items: center;
}
.table-review-img {
  width: 36px;
  height: 36px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  cursor: zoom-in;
  transition: transform 0.15s, box-shadow 0.15s;
}
.table-review-img:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  border-color: var(--primary);
}
.more-images-count {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  margin-left: 2px;
}

/* Content */
.review-content {
  margin: 0;
  max-width: 200px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.85rem;
  color: #475569;
  line-height: 1.4;
}

.date-cell {
  font-size: 0.8rem;
  color: var(--text-muted);
  white-space: nowrap;
}

/* Status badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.76rem;
  font-weight: 600;
  white-space: nowrap;
}
.badge--approved { background: #d1fae5; color: #065f46; }
.badge--pending  { background: #fef3c7; color: #92400e; }

/* Action buttons */
.action-btns {
  display: flex;
  flex-direction: column;
  gap: 5px;
  align-items: stretch;
  min-width: 65px;
  max-width: 80px;
}
.btn-action {
  padding: 4px 10px;
  border-radius: 6px;
  border: none;
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.1s;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.btn-action:active { transform: scale(0.96); }
.btn-success { background: #d1fae5; color: #065f46; }
.btn-success:hover { background: #a7f3d0; }
.btn-warn    { background: #fef3c7; color: #92400e; }
.btn-warn:hover    { background: #fde68a; }
.btn-danger  { background: #fee2e2; color: #991b1b; }
.btn-danger:hover  { background: #fecaca; }

.btn-primary-action { background: #fdf2f8; color: var(--primary); border: 1px solid #fbcfe8; }
.btn-primary-action:hover { background: #fce7f3; }
.btn-view { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.btn-view:hover { background: #e2e8f0; }

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
.page-btn:hover:not(:disabled) { background: var(--primary); color: #fff; border-color: var(--primary); }
.page-btn:disabled { opacity: 0.4; cursor: default; }
.page-info { font-size: 0.88rem; color: #6c757d; }

/* ──────────────────────────────────────────────────────────
   TICKET STYLES
────────────────────────────────────────────────────────── */
.order-code {
  background: #e0f2fe;
  color: #0284c7;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.85rem;
}
.reason-text {
  max-width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
  color: #334155;
}
.status-badge-ticket {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  display: inline-block;
}
.status-badge-ticket.pending { background: #fef3c7; color: #d97706; }
.status-badge-ticket.processing { background: #e0f2fe; color: #0284c7; }
.status-badge-ticket.resolved { background: #dcfce3; color: #16a34a; }
.status-badge-ticket.closed { background: #f1f5f9; color: #64748b; }
.text-right { text-align: right !important; }
.text-muted { color: #94a3b8; }
.btn-icon {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.2s;
}
.btn-icon:hover {
  background: #f1f5f9;
  color: var(--primary);
}

/* Modal styles for tickets & reviews */
.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}
.modal-box {
  background: white;
  width: 100%; max-width: 650px;
  border-radius: 16px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
  display: flex; flex-direction: column;
  max-height: 90vh;
}
.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex; justify-content: space-between; align-items: center;
}
.modal-header h3 { margin: 0; font-size: 1.25rem; font-weight: 700; }
.btn-close-modal {
  background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;
}
.modal-body {
  padding: 24px;
  overflow-y: auto;
}
.info-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;
}
.info-group label { display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 4px; }
.info-group p { margin: 0; font-weight: 600; color: #1e293b; }

.ticket-content {
  background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #e2e8f0;
}
.ticket-content h4 { margin: 0 0 12px; font-size: 1.1rem; color: #dc2626; }
.description-box { color: #334155; line-height: 1.6; white-space: pre-wrap; }

.image-box { margin-top: 16px; }
.image-box-title {
  font-size: 0.88rem;
  color: #334155;
  margin-bottom: 8px;
}
.modal-img-container {
  position: relative;
  display: inline-block;
  cursor: zoom-in;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.modal-img-container:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
  border-color: var(--primary);
}
.modal-img-container .admin-review-img {
  width: 90px;
  height: 90px;
  border-radius: 8px;
  object-fit: cover;
  display: block;
}
.zoom-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  opacity: 0;
  transition: opacity 0.2s ease;
}
.modal-img-container:hover .zoom-overlay {
  opacity: 1;
}

.evidence-container {
  max-width: 220px;
}
.evidence-container .evidence-img {
  max-width: 100%;
  max-height: 180px;
  display: block;
}

.reply-section h4 { margin: 0 0 12px; font-size: 1.05rem; }
.reply-input {
  width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;
  font-family: inherit; font-size: 0.95rem; resize: vertical; box-sizing: border-box;
}
.reply-input:focus { border-color: var(--primary); outline: none; }

.modal-footer {
  padding: 16px 24px; border-top: 1px solid #e2e8f0;
  display: flex; justify-content: flex-end; gap: 12px;
}
.btn {
  padding: 8px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s;
}
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-primary { background: var(--primary); color: white; display: flex; align-items: center; gap: 8px; }
.btn-primary:hover:not(:disabled) { background: #d82f65; }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

.mt-3 { margin-top: 16px; }
.w-100 { width: 100%; box-sizing: border-box; }
.filter-select {
  padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px;
  font-size: 0.95rem; outline: none; background: white;
}

/* ──────────────────────────────────────────────────────────
   IMAGE LIGHTBOX MODAL
────────────────────────────────────────────────────────── */
.image-lightbox-overlay {
  position: fixed;
  inset: 0;
  z-index: 99999;
  background: rgba(10, 15, 30, 0.88);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.lightbox-close-btn {
  position: absolute;
  top: 20px;
  right: 24px;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s, transform 0.1s;
  z-index: 100000;
}
.lightbox-close-btn:hover {
  background: #ef4444;
  transform: scale(1.08);
}
.lightbox-content {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 92vw;
  max-height: 90vh;
}
.lightbox-img {
  max-width: 90vw;
  max-height: 86vh;
  object-fit: contain;
  border-radius: 12px;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  animation: zoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes zoomIn {
  from {
    transform: scale(0.9);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.lightbox-fade-enter-active,
.lightbox-fade-leave-active {
  transition: opacity 0.2s ease;
}
.lightbox-fade-enter-from,
.lightbox-fade-leave-to {
  opacity: 0;
}

/* Transitions */
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
