<script setup>
import { ref, onMounted } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import api from '@/axios';
import { getStorageUrl } from '@/utils/url';

const tickets = ref([]);
const loading = ref(true);
const selectedTicket = ref(null);
const showDetailModal = ref(false);
const previewImage = ref(null);

const formatDate = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getStatusInfo = (status) => {
  const map = {
    pending: { label: 'Chờ xử lý', tone: 'warning', icon: 'clock' },
    processing: { label: 'Đang xử lý', tone: 'info', icon: 'refresh-cw' },
    resolved: { label: 'Đã giải quyết', tone: 'success', icon: 'check-circle' },
    closed: { label: 'Đã đóng', tone: 'muted', icon: 'lock' },
  };
  return map[status] || { label: status, tone: 'info', icon: 'help-circle' };
};

const fetchTickets = async () => {
  loading.value = true;
  try {
    const res = await api.get('/profile/tickets');
    if (res.data.status === 'success') {
      tickets.value = res.data.data || [];
    }
  } catch (error) {
    console.error('Lỗi lấy danh sách khiếu nại:', error);
  } finally {
    loading.value = false;
  }
};

const openDetail = (ticket) => {
  selectedTicket.value = ticket;
  showDetailModal.value = true;
};

const closeDetail = () => {
  showDetailModal.value = false;
  selectedTicket.value = null;
};

onMounted(() => {
  fetchTickets();
});
</script>

<template>
  <div class="profile-tickets-page">
    <div class="page-header">
      <div>
        <h2 class="page-title">Khiếu nại & Hỗ trợ của tôi</h2>
        <p class="page-subtitle">Theo dõi tiến độ xử lý khiếu nại và phản hồi trực tiếp từ đội ngũ Chăm sóc khách hàng.</p>
      </div>
      <div v-if="tickets.length > 0" class="ticket-count-badge">
        {{ tickets.length }} khiếu nại
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="tickets-skeleton">
      <div v-for="i in 3" :key="i" class="skeleton-ticket-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
          <div class="skeleton-box" style="width: 160px; height: 22px; border-radius: 6px;"></div>
          <div class="skeleton-box" style="width: 110px; height: 28px; border-radius: 20px;"></div>
        </div>
        <div class="skeleton-box" style="width: 75%; height: 18px; border-radius: 4px; margin-bottom: 10px;"></div>
        <div class="skeleton-box" style="width: 45%; height: 14px; border-radius: 4px;"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="tickets.length === 0" class="empty-state">
      <div class="empty-icon-wrapper">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="empty-icon">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
      </div>
      <h3 class="empty-title">Bạn chưa có khiếu nại nào</h3>
      <p class="empty-desc">Nếu đơn hàng có bất kỳ vấn đề gì về sản phẩm hoặc giao nhận, bạn có thể gửi khiếu nại trong trang chi tiết đơn hàng.</p>
      <router-link to="/profile/orders" class="btn-goto-orders">
        Xem đơn hàng của tôi ➔
      </router-link>
    </div>

    <!-- Tickets List -->
    <div v-else class="tickets-list">
      <div
        v-for="ticket in tickets"
        :key="ticket.ticket_id"
        class="ticket-card"
        :class="`ticket-card--${ticket.status}`"
      >
        <!-- Card Header -->
        <div class="ticket-card__header">
          <div class="ticket-identity">
            <span class="ticket-id-tag">#KN-{{ ticket.ticket_id }}</span>
            <router-link
              v-if="ticket.order"
              :to="`/profile/orders/${ticket.order.order_id}`"
              class="ticket-order-link"
              title="Xem đơn hàng này"
            >
              📦 Đơn hàng #{{ ticket.order.order_code }}
            </router-link>
          </div>
          <span class="status-badge" :class="`status-badge--${getStatusInfo(ticket.status).tone}`">
            <span class="status-dot"></span>
            {{ getStatusInfo(ticket.status).label }}
          </span>
        </div>

        <!-- Product Preview if any -->
        <div v-if="ticket.product" class="ticket-product-chip">
          <img
            v-if="ticket.product.thumbnail_url"
            :src="getStorageUrl(ticket.product.thumbnail_url)"
            alt="SP"
            class="product-chip-img"
          />
          <span class="product-chip-name">{{ ticket.product.name }}</span>
        </div>

        <!-- Reason & Description -->
        <div class="ticket-body">
          <h4 class="ticket-reason">{{ ticket.reason }}</h4>
          <p class="ticket-desc">{{ ticket.description }}</p>

          <!-- Evidence image -->
          <div v-if="ticket.image_url" class="ticket-evidence">
            <span class="evidence-label">📷 Ảnh minh chứng đính kèm:</span>
            <img
              :src="getStorageUrl(ticket.image_url)"
              alt="Ảnh minh chứng"
              class="evidence-thumbnail"
              @click="previewImage = getStorageUrl(ticket.image_url)"
            />
          </div>
        </div>

        <!-- Admin Reply Section -->
        <div v-if="ticket.admin_reply" class="admin-reply-box">
          <div class="admin-reply-header">
            <div class="admin-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
              <span>Phản hồi từ Admin Ocean Sport</span>
            </div>
            <span v-if="ticket.updated_at" class="reply-time">{{ formatDate(ticket.updated_at) }}</span>
          </div>
          <p class="admin-reply-content">{{ ticket.admin_reply }}</p>
        </div>

        <div v-else class="pending-reply-note">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span>Đang chờ nhân viên hỗ trợ tiếp nhận & phản hồi...</span>
        </div>

        <!-- Card Footer -->
        <div class="ticket-card__footer">
          <span class="ticket-date">Gửi lúc: {{ formatDate(ticket.created_at) }}</span>
          <div class="footer-actions">
            <button class="btn-view-detail" @click="openDetail(ticket)">
              Xem chi tiết
            </button>
            <router-link
              v-if="ticket.order"
              :to="`/profile/orders/${ticket.order.order_id}`"
              class="btn-view-order"
            >
              Chi tiết đơn hàng ➔
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <Transition name="modal-fade">
      <div v-if="showDetailModal && selectedTicket" class="modal-overlay" @click.self="closeDetail">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Chi tiết khiếu nại #KN-{{ selectedTicket.ticket_id }}</h3>
            <button class="btn-close" @click="closeDetail">&times;</button>
          </div>

          <div class="modal-body">
            <div class="info-row">
              <span class="info-label">Trạng thái:</span>
              <span class="status-badge" :class="`status-badge--${getStatusInfo(selectedTicket.status).tone}`">
                {{ getStatusInfo(selectedTicket.status).label }}
              </span>
            </div>

            <div v-if="selectedTicket.order" class="info-row">
              <span class="info-label">Mã đơn hàng:</span>
              <router-link :to="`/profile/orders/${selectedTicket.order.order_id}`" class="order-link-highlight">
                #{{ selectedTicket.order.order_code }}
              </router-link>
            </div>

            <div class="info-row">
              <span class="info-label">Thời gian gửi:</span>
              <span class="info-val">{{ formatDate(selectedTicket.created_at) }}</span>
            </div>

            <div class="detail-section">
              <h4>Lý do khiếu nại:</h4>
              <p class="reason-highlight">{{ selectedTicket.reason }}</p>
            </div>

            <div class="detail-section">
              <h4>Nội dung mô tả:</h4>
              <div class="desc-content">{{ selectedTicket.description }}</div>
            </div>

            <div v-if="selectedTicket.image_url" class="detail-section">
              <h4>Ảnh minh chứng:</h4>
              <img
                :src="getStorageUrl(selectedTicket.image_url)"
                alt="Minh chứng"
                class="modal-evidence-img"
                @click="previewImage = getStorageUrl(selectedTicket.image_url)"
              />
            </div>

            <div v-if="selectedTicket.admin_reply" class="detail-section admin-detail-reply">
              <h4 style="color: #b50c4d; display: flex; align-items: center; gap: 6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Phản hồi từ Ban Quản Trị:
              </h4>
              <div class="reply-detail-content">{{ selectedTicket.admin_reply }}</div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-close-footer" @click="closeDetail">Đóng</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Image Lightbox Modal -->
    <Transition name="modal-fade">
      <div v-if="previewImage" class="lightbox-overlay" @click="previewImage = null">
        <div class="lightbox-wrap">
          <img :src="previewImage" class="lightbox-img" alt="Ảnh minh chứng to" />
          <button class="lightbox-close" @click="previewImage = null">&times;</button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.profile-tickets-page {
  background: var(--card-bg, #ffffff);
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  padding: 24px;
  min-height: 500px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 18px;
}

.page-title {
  margin: 0;
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
}

.page-subtitle {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.ticket-count-badge {
  background: #FFF0F3;
  color: var(--primary, #E63B6F);
  font-weight: 700;
  font-size: 0.82rem;
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid #fecdd3;
  white-space: nowrap;
}

/* Tickets List */
.tickets-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ticket-card {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 20px;
  background: #ffffff;
  transition: all 0.2s ease;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.ticket-card:hover {
  border-color: #f472b6;
  box-shadow: 0 8px 20px rgba(230, 59, 111, 0.06);
  transform: translateY(-1px);
}

.ticket-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.ticket-identity {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.ticket-id-tag {
  font-weight: 800;
  font-size: 0.9rem;
  color: var(--primary, #E63B6F);
}

.ticket-order-link {
  font-size: 0.82rem;
  color: #0284c7;
  background: #e0f2fe;
  padding: 3px 10px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
}

.ticket-order-link:hover {
  background: #bae6fd;
  text-decoration: underline;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 700;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status-badge--warning { background: #fef3c7; color: #d97706; }
.status-badge--info { background: #e0f2fe; color: #0284c7; }
.status-badge--success { background: #dcfce7; color: #16a34a; }
.status-badge--muted { background: #f1f5f9; color: #64748b; }

/* Product chip */
.ticket-product-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 4px 10px;
  border-radius: 8px;
  margin-bottom: 12px;
  max-width: 100%;
}

.product-chip-img {
  width: 24px;
  height: 24px;
  border-radius: 4px;
  object-fit: cover;
}

.product-chip-name {
  font-size: 0.82rem;
  color: #334155;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ticket-body {
  margin-bottom: 14px;
}

.ticket-reason {
  margin: 0 0 6px;
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
}

.ticket-desc {
  margin: 0;
  font-size: 0.9rem;
  color: #475569;
  line-height: 1.5;
  white-space: pre-wrap;
}

.ticket-evidence {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.evidence-label {
  font-size: 0.82rem;
  color: #64748b;
  font-weight: 500;
}

.evidence-thumbnail {
  width: 48px;
  height: 48px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  object-fit: cover;
  cursor: zoom-in;
  transition: transform 0.2s, box-shadow 0.2s;
}

.evidence-thumbnail:hover {
  transform: scale(1.08);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Admin Reply Box */
.admin-reply-box {
  background: #FFF0F3;
  border-left: 4px solid var(--primary, #E63B6F);
  border-radius: 0 10px 10px 0;
  padding: 14px 18px;
  margin-bottom: 14px;
}

.admin-reply-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.admin-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #b50c4d;
  font-size: 0.82rem;
  font-weight: 700;
}

.reply-time {
  font-size: 0.76rem;
  color: #94a3b8;
}

.admin-reply-content {
  margin: 0;
  color: #1e293b;
  font-size: 0.9rem;
  line-height: 1.55;
  white-space: pre-wrap;
}

.pending-reply-note {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #94a3b8;
  font-style: italic;
  margin-bottom: 14px;
}

/* Footer */
.ticket-card__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #f1f5f9;
  padding-top: 12px;
  gap: 12px;
  flex-wrap: wrap;
}

.ticket-date {
  font-size: 0.8rem;
  color: #94a3b8;
}

.footer-actions {
  display: flex;
  gap: 10px;
}

.btn-view-detail {
  padding: 6px 14px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-view-detail:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.btn-view-order {
  padding: 6px 14px;
  background: #fff;
  border: 1px solid #e0f2fe;
  color: #0284c7;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-view-order:hover {
  background: #e0f2fe;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 56px 20px;
}

.empty-icon-wrapper {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  width: 88px;
  height: 88px;
  background: #FFF0F3;
  border-radius: 50%;
  margin-bottom: 18px;
}

.empty-icon {
  color: var(--primary, #E63B6F);
}

.empty-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px;
}

.empty-desc {
  font-size: 0.92rem;
  color: #64748b;
  max-width: 440px;
  margin: 0 auto 20px;
  line-height: 1.5;
}

.btn-goto-orders {
  display: inline-block;
  background: var(--primary, #E63B6F);
  color: #fff;
  padding: 10px 24px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  transition: opacity 0.2s;
}

.btn-goto-orders:hover {
  opacity: 0.9;
}

/* Skeleton */
.tickets-skeleton {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skeleton-ticket-card {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 20px;
  background: #fff;
}

.skeleton-box {
  background: #f1f5f9;
  position: relative;
  overflow: hidden;
}

.skeleton-box::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(90deg, rgba(255,255,255,0) 0, rgba(255,255,255,0.6) 50%, rgba(255,255,255,0) 100%);
  animation: shimmer 1.4s infinite;
}

@keyframes shimmer {
  100% { transform: translateX(100%); }
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 560px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}

.modal-header {
  padding: 18px 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #94a3b8;
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.info-label {
  font-size: 0.85rem;
  color: #64748b;
  width: 120px;
}

.order-link-highlight {
  font-weight: 700;
  color: #0284c7;
  text-decoration: underline;
}

.detail-section {
  margin-top: 18px;
}

.detail-section h4 {
  margin: 0 0 6px;
  font-size: 0.92rem;
  color: #334155;
}

.reason-highlight {
  margin: 0;
  font-weight: 600;
  color: #0f172a;
}

.desc-content {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 16px;
  color: #334155;
  font-size: 0.9rem;
  line-height: 1.6;
  white-space: pre-wrap;
}

.modal-evidence-img {
  max-width: 100%;
  max-height: 220px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  cursor: zoom-in;
}

.admin-detail-reply {
  background: #FFF0F3;
  border-radius: 10px;
  padding: 16px;
  border: 1px solid #ffd9de;
}

.reply-detail-content {
  color: #1e293b;
  font-size: 0.92rem;
  line-height: 1.6;
  white-space: pre-wrap;
}

.modal-footer {
  padding: 14px 24px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
}

.btn-close-footer {
  padding: 8px 20px;
  background: #f1f5f9;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
}

/* Lightbox */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(8px);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.lightbox-wrap {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
}

.lightbox-img {
  max-width: 100%;
  max-height: 85vh;
  object-fit: contain;
  border-radius: 10px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

.lightbox-close {
  position: absolute;
  top: -40px;
  right: 0;
  background: none;
  border: none;
  color: #fff;
  font-size: 2rem;
  cursor: pointer;
}

/* Modal animation */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
