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
  const map = { pending: 'Cho xu ly', processing: 'Dang xu ly', resolved: 'Da giai quyet', closed: 'Da dong' };
  return map[s] || s;
};

const statusClass = (s) => {
  const map = { pending: 'badge-warning', processing: 'badge-info', resolved: 'badge-success', closed: 'badge-secondary' };
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
    const res = await api.get('/tickets');
    tickets.value = res.data?.data || [];
  } catch (e) {
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
      <h2 class="page-title">Khieu nai cua toi</h2>
      <p class="page-subtitle">Theo doi trang thai xu ly khieu nai cua ban.</p>
    </div>

    <div v-if="loading" class="tickets-loading">
      <div v-for="i in 3" :key="i" class="skeleton-card">
        <div class="skeleton-line" style="width:40%;height:18px;margin-bottom:10px;"></div>
        <div class="skeleton-line" style="width:80%;height:14px;margin-bottom:8px;"></div>
        <div class="skeleton-line" style="width:60%;height:14px;"></div>
      </div>
    </div>

    <div v-else-if="tickets.length === 0" class="tickets-empty">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <p>Ban chua co khieu nai nao.</p>
    </div>

    <div v-else class="tickets-list">
      <div v-for="ticket in tickets" :key="ticket.ticket_id" class="ticket-card">
        <div class="ticket-card-header">
          <div class="ticket-id">#{{ ticket.ticket_id }}</div>
          <span :class="['ticket-badge', statusClass(ticket.status)]">{{ statusLabel(ticket.status) }}</span>
        </div>
        <div class="ticket-body">
          <p class="ticket-reason"><strong>Ly do:</strong> {{ ticket.reason }}</p>
          <p class="ticket-desc">{{ ticket.description }}</p>
          <div class="ticket-meta">
            <span v-if="ticket.order">Don hang: <strong>{{ ticket.order?.order_code || ('#' + ticket.order_id) }}</strong></span>
            <span>Ngay gui: {{ formatDate(ticket.created_at) }}</span>
          </div>
          <div v-if="ticket.image_url" class="ticket-evidence">
            <img :src="getImageUrl(ticket.image_url)" alt="Anh khieu nai" class="ticket-thumb" @click="lightboxSrc = getImageUrl(ticket.image_url)" />
          </div>
          <div v-if="ticket.admin_reply" class="ticket-reply">
            <div class="ticket-reply-label">Phan hoi tu Ocean Sport:</div>
            <p class="ticket-reply-text">{{ ticket.admin_reply }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox -->
    <div v-if="lightboxSrc" class="lightbox-overlay" @click="lightboxSrc = null">
      <img :src="lightboxSrc" class="lightbox-img" @click.stop />
    </div>
  </div>
</template>

<style scoped>
.tickets-page { max-width: 860px; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; margin: 0 0 6px; }
.page-subtitle { color: #666; font-size: 14px; margin: 0; }
.tickets-loading, .tickets-empty { padding: 40px 0; text-align: center; color: #aaa; }
.skeleton-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 14px; border: 1px solid #f0f0f0; }
.skeleton-line { background: linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 6px; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.tickets-list { display: flex; flex-direction: column; gap: 16px; }
.ticket-card { background: #fff; border-radius: 14px; border: 1px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,.04); overflow: hidden; transition: box-shadow .2s; }
.ticket-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }
.ticket-card-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px 10px; border-bottom: 1px solid #f7f7f7; }
.ticket-id { font-weight: 700; color: #E63B6F; font-size: 15px; }
.ticket-badge { padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-warning { background: #fff7e6; color: #d97706; }
.badge-info { background: #eff6ff; color: #2563eb; }
.badge-success { background: #f0fdf4; color: #16a34a; }
.badge-secondary { background: #f4f4f5; color: #71717a; }
.ticket-body { padding: 14px 20px 18px; }
.ticket-reason { margin: 0 0 6px; font-size: 14px; }
.ticket-desc { margin: 0 0 10px; font-size: 13px; color: #555; line-height: 1.5; }
.ticket-meta { display: flex; gap: 16px; font-size: 12px; color: #888; flex-wrap: wrap; margin-bottom: 12px; }
.ticket-evidence { margin-bottom: 12px; }
.ticket-thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; cursor: zoom-in; border: 2px solid #f0f0f0; transition: border-color .2s; }
.ticket-thumb:hover { border-color: #E63B6F; }
.ticket-reply { background: #fdf2f6; border-left: 3px solid #E63B6F; border-radius: 6px; padding: 12px 16px; }
.ticket-reply-label { font-size: 12px; font-weight: 600; color: #E63B6F; margin-bottom: 4px; }
.ticket-reply-text { margin: 0; font-size: 14px; color: #333; line-height: 1.55; }
.lightbox-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.75); z-index: 9999; display: flex; align-items: center; justify-content: center; cursor: zoom-out; }
.lightbox-img { max-width: 90vw; max-height: 85vh; border-radius: 10px; object-fit: contain; }
</style>
