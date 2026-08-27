<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';

const tickets = ref([]);
const loading = ref(false);
const lightboxSrc = ref(null);

const formatDate = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const statusLabel = (s) => {
  const map = {
    pending: 'Chờ xử lý',
    processing: 'Đang xử lý',
    resolved: 'Đã giải quyết',
    closed: 'Đã đóng'
  };
  return map[s] || s;
};

const statusClass = (s) => {
  const map = {
    pending: 'badge-warning',
    processing: 'badge-info',
    resolved: 'badge-success',
    closed: 'badge-secondary'
  };
  return map[s] || 'badge-secondary';
};

const getImageUrl = (path) => {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  const base = (import.meta.env.VITE_API_BASE_URL || '').replace('/api', '');
  return base + '/storage/' + path;
};

const fetchTickets = async () => {
  loading.value = true;
  try {
    let res;
    try {
      res = await api.get('/profile/tickets');
    } catch (err) {
      res = await api.get('/tickets');
    }
    tickets.value = res.data?.data || [];
  } catch (e) {
    console.error('Lỗi lấy danh sách khiếu nại:', e);
    tickets.value = [];
  } finally {
    loading.value = false;
  }
};

onMounted(fetchTickets);
</script>

<template>
  <div class="tickets-page">
    <div class="page-header">
      <h2 class="page-title">Khiếu nại của tôi</h2>
      <p class="page-subtitle">Theo dõi tiến độ tiếp nhận và phản hồi từ ban quản trị Ocean Sport.</p>
    </div>

    <!-- Loading Skeletons -->
    <div v-if="loading" class="tickets-loading">
      <div v-for="i in 3" :key="i" class="skeleton-card">
        <div class="skeleton-line" style="width: 35%; height: 20px; margin-bottom: 12px;"></div>
        <div class="skeleton-line" style="width: 75%; height: 16px; margin-bottom: 8px;"></div>
        <div class="skeleton-line" style="width: 50%; height: 14px;"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="tickets.length === 0" class="tickets-empty">
      <div class="empty-icon-wrap">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
      </div>
      <p class="empty-text">Bạn chưa có khiếu nại nào.</p>
      <p class="empty-subtext">Nếu bạn gặp vấn đề với đơn hàng, hãy vào chi tiết đơn hàng để gửi khiếu nại.</p>
      <router-link to="/profile/orders" class="btn-goto-orders">Xem đơn hàng của tôi</router-link>
    </div>

    <!-- Tickets List -->
    <div v-else class="tickets-list">
      <div v-for="ticket in tickets" :key="ticket.ticket_id" class="ticket-card">
        <div class="ticket-card-header">
          <div class="ticket-id-tag">
            <span class="id-label">Khiếu nại #{{ ticket.ticket_id }}</span>
          </div>
          <span :class="['ticket-badge', statusClass(ticket.status)]">
            {{ statusLabel(ticket.status) }}
          </span>
        </div>

        <div class="ticket-body">
          <div class="ticket-reason-row">
            <span class="reason-label">Lý do:</span>
            <span class="reason-val">{{ ticket.reason }}</span>
          </div>

          <p class="ticket-desc">{{ ticket.description }}</p>

          <div class="ticket-meta">
            <span v-if="ticket.order" class="meta-item">
              Đơn hàng: 
              <router-link :to="`/profile/orders/${ticket.order_id}`" class="order-link">
                #{{ ticket.order?.order_code || ticket.order_id }}
              </router-link>
            </span>
            <span class="meta-item">Gửi lúc: {{ formatDate(ticket.created_at) }}</span>
          </div>

          <!-- Evidence Image -->
          <div v-if="ticket.image_url" class="ticket-evidence">
            <span class="evidence-title">Hình ảnh minh chứng:</span>
            <img :src="getImageUrl(ticket.image_url)" alt="Ảnh khiếu nại" class="ticket-thumb" @click="lightboxSrc = getImageUrl(ticket.image_url)" />
          </div>

          <!-- Admin Reply Box -->
          <div v-if="ticket.admin_reply" class="ticket-reply">
            <div class="ticket-reply-header">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              <span>Phản hồi từ Ban Quản Trị Ocean Sport:</span>
            </div>
            <p class="ticket-reply-text">{{ ticket.admin_reply }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox Preview -->
    <div v-if="lightboxSrc" class="lightbox-overlay" @click="lightboxSrc = null">
      <div class="lightbox-content" @click.stop>
        <button class="lightbox-close" @click="lightboxSrc = null">&times;</button>
        <img :src="lightboxSrc" class="lightbox-img" alt="Phóng to ảnh" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.tickets-page {
  max-width: 900px;
}

.page-header {
  margin-bottom: 24px;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0 0 6px;
}

.page-subtitle {
  color: #666;
  font-size: 14px;
  margin: 0;
}

.tickets-loading {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.skeleton-card {
  background: #fff;
  border-radius: 12px;
  padding: 22px;
  border: 1px solid #edf2f7;
}

.skeleton-line {
  background: linear-gradient(90deg, #f0f0f0 25%, #e2e8f0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 6px;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.tickets-empty {
  background: #fff;
  border-radius: 14px;
  border: 1px dashed #e2e8f0;
  padding: 60px 20px;
  text-align: center;
}

.empty-icon-wrap {
  color: #cbd5e1;
  margin-bottom: 16px;
}

.empty-text {
  font-size: 17px;
  font-weight: 600;
  color: #334155;
  margin: 0 0 6px;
}

.empty-subtext {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 20px;
}

.btn-goto-orders {
  display: inline-block;
  background: linear-gradient(135deg, #E63B6F 0%, #c0255a 100%);
  color: #fff;
  text-decoration: none;
  padding: 10px 24px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  transition: opacity 0.2s;
}

.btn-goto-orders:hover {
  opacity: 0.9;
}

.tickets-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ticket-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}

.ticket-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
}

.ticket-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 22px 12px;
  border-bottom: 1px solid #f8fafc;
  background: #fafbfd;
}

.ticket-id-tag .id-label {
  font-weight: 700;
  color: #E63B6F;
  font-size: 15px;
}

.ticket-badge {
  padding: 4px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.badge-warning { background: #fef3c7; color: #b45309; }
.badge-info { background: #e0f2fe; color: #0284c7; }
.badge-success { background: #dcfce7; color: #15803d; }
.badge-secondary { background: #f1f5f9; color: #64748b; }

.ticket-body {
  padding: 18px 22px 20px;
}

.ticket-reason-row {
  margin-bottom: 8px;
  font-size: 15px;
}

.reason-label {
  font-weight: 600;
  color: #475569;
  margin-right: 6px;
}

.reason-val {
  font-weight: 600;
  color: #1e293b;
}

.ticket-desc {
  margin: 0 0 14px;
  font-size: 14px;
  color: #475569;
  line-height: 1.6;
  background: #f8fafc;
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.ticket-meta {
  display: flex;
  gap: 20px;
  font-size: 13px;
  color: #64748b;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.order-link {
  color: #2563eb;
  font-weight: 600;
  text-decoration: none;
}

.order-link:hover {
  text-decoration: underline;
}

.ticket-evidence {
  margin-bottom: 14px;
}

.evidence-title {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 6px;
}

.ticket-thumb {
  width: 90px;
  height: 90px;
  object-fit: cover;
  border-radius: 8px;
  cursor: zoom-in;
  border: 2px solid #e2e8f0;
  transition: border-color 0.2s, transform 0.2s;
}

.ticket-thumb:hover {
  border-color: #E63B6F;
  transform: scale(1.03);
}

.ticket-reply {
  background: #fdf2f8;
  border-left: 4px solid #E63B6F;
  border-radius: 8px;
  padding: 14px 18px;
  margin-top: 10px;
}

.ticket-reply-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: #E63B6F;
  margin-bottom: 6px;
}

.ticket-reply-text {
  margin: 0;
  font-size: 14px;
  color: #334155;
  line-height: 1.6;
}

.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lightbox-content {
  position: relative;
  max-width: 90vw;
  max-height: 85vh;
}

.lightbox-img {
  max-width: 100%;
  max-height: 85vh;
  border-radius: 10px;
  object-fit: contain;
}

.lightbox-close {
  position: absolute;
  top: -40px;
  right: 0;
  background: none;
  border: none;
  color: #fff;
  font-size: 32px;
  cursor: pointer;
}
</style>

