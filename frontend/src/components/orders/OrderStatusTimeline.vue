<script setup>
const props = defineProps({
  histories: { type: Array, default: () => [] },
  showGhnMeta: { type: Boolean, default: false },
  getStatusLabel: { type: Function, required: true },
  getStatusBadgeClass: { type: Function, required: true },
  formatDate: { type: Function, required: true },
});

const sourceLabels = {
  system: 'Hệ thống',
  manual: 'Admin',
  ghn_webhook: 'GHN',
  ghn_manual_sync: 'GHN',
  ghn_api: 'GHN',
};

const ghnStatusLabels = {
  ready_to_pick: 'Sẵn sàng lấy hàng',
  exception: 'GHN báo ngoại lệ',
  picking: 'Đang lấy hàng',
  money_collect_picking: 'Đang thu tiền khi lấy hàng',
  picked: 'Đã lấy hàng',
  storing: 'Đang lưu kho',
  transporting: 'Đang vận chuyển',
  sorting: 'Đang phân loại',
  delivering: 'Đang giao hàng',
  money_collect_delivering: 'Đang thu tiền khi giao',
  delivery_fail: 'Giao hàng thất bại',
  delivered: 'Đã giao hàng',
  cancel: 'Đã hủy trên GHN',
  damage: 'Hàng hư hỏng',
  lost: 'Thất lạc',
  waiting_to_return: 'Chờ hoàn hàng',
  return: 'Hoàn hàng',
  return_transporting: 'Đang chuyển hoàn',
  return_sorting: 'Đang phân loại hàng hoàn',
  returning: 'Đang hoàn hàng',
  return_fail: 'Hoàn hàng thất bại',
  returned: 'Đã hoàn hàng',
};

const sortedHistories = () => [...props.histories].sort((a, b) => {
  const aTime = new Date(a.happened_at || a.created_at || 0).getTime();
  const bTime = new Date(b.happened_at || b.created_at || 0).getTime();
  return bTime - aTime;
});

const readableFallback = (value) => String(value || '').replace(/_/g, ' ');
const getSourceLabel = (source) => sourceLabels[source] || readableFallback(source);
const getGhnStatusLabel = (status) => ghnStatusLabels[status] || readableFallback(status);
const isSameStatus = (h) => h.old_status && h.old_status === h.new_status;
const normalizeText = (value) => String(value || '').trim().toLowerCase();
const shouldShowDescription = (h) => {
  if (!props.showGhnMeta || !h.description) return false;
  const description = normalizeText(h.description);
  return description !== normalizeText(h.note)
    && description !== normalizeText(h.ghn_status)
    && description !== normalizeText(getGhnStatusLabel(h.ghn_status));
};
</script>

<template>
  <div class="order-status-timeline">
    <div v-for="(h, index) in sortedHistories()" :key="h.history_id || h.id || index" class="history-item">
      <div class="history-dot" :class="getStatusBadgeClass(h.new_status)"></div>
      <div class="history-content">
        <div class="history-transition">
          <span class="status-badge sm" :class="getStatusBadgeClass(h.old_status)" v-if="h.old_status && !isSameStatus(h)">{{ getStatusLabel(h.old_status) }}</span>
          <svg v-if="h.old_status && !isSameStatus(h)" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          <span class="status-badge sm" :class="getStatusBadgeClass(h.new_status)">{{ getStatusLabel(h.new_status) }}</span>
          <span v-if="showGhnMeta && h.source" class="source-badge">{{ getSourceLabel(h.source) }}</span>
          <span v-if="showGhnMeta && h.ghn_status" class="ghn-badge">GHN: {{ getGhnStatusLabel(h.ghn_status) }}</span>
        </div>
        <p class="history-note" v-if="h.note">{{ h.note }}</p>
        <p class="history-note" v-if="shouldShowDescription(h)">{{ h.description }}</p>
        <p class="history-location" v-if="showGhnMeta && h.location">{{ h.location }}</p>
        <span class="history-time">{{ formatDate(h.happened_at || h.created_at) }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.order-status-timeline { display: flex; flex-direction: column; gap: 20px; }
.history-item { display: flex; gap: 16px; align-items: flex-start; }
.history-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
  box-shadow: 0 0 0 2px white, 0 0 0 4px rgba(0,0,0,0.05);
}
.history-content { flex: 1; min-width: 0; }
.history-transition { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.history-note { margin: 6px 0 4px; font-size: 0.9rem; color: #475569; font-style: italic; word-break: break-word; }
.history-location { margin: 4px 0 2px; font-size: 0.85rem; color: #1e293b; font-weight: 600; }
.history-time { font-size: 0.8rem; color: #94a3b8; display: inline-block; margin-top: 4px; }
.source-badge, .ghn-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.source-badge { color: white; background: #0ea5e9; }
.ghn-badge { color: white; background: #f97316; }
.status-badge.sm {
  display: inline-flex;
  align-items: center;
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.badge-warning { background: #f59e0b; color: white; }
.badge-primary { background: #3b82f6; color: white; }
.badge-info { background: #06b6d4; color: white; }
.badge-success { background: #10b981; color: white; }
.badge-danger { background: #ef4444; color: white; }
.badge-secondary { background: #64748b; color: white; }

@media (max-width: 576px) {
  .order-status-timeline { gap: 16px; }
  .history-item { gap: 12px; }
  .history-dot { width: 12px; height: 12px; margin-top: 6px; }
  .status-badge.sm, .source-badge, .ghn-badge { font-size: 0.7rem; padding: 4px 10px; }
  .history-note { font-size: 0.85rem; }
}
</style>
