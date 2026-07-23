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
.order-status-timeline { display: flex; flex-direction: column; gap: 16px; }
.history-item { display: flex; gap: 14px; align-items: flex-start; }
.history-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-top: 6px;
  flex-shrink: 0;
}
.history-content { flex: 1; }
.history-transition { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.history-note { margin: 6px 0 2px; font-size: 0.85rem; color: var(--text-muted); font-style: italic; }
.history-location { margin: 4px 0 2px; font-size: 0.82rem; color: var(--text-main); font-weight: 600; }
.history-time { font-size: 0.75rem; color: var(--text-light); }
.source-badge, .ghn-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
}
.source-badge { color: #0369a1; background: #e0f2fe; }
.ghn-badge { color: #c2410c; background: #ffedd5; }
.status-badge.sm {
  display: inline-flex;
  align-items: center;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
}
.badge-warning { background: #fff7ed; color: #ea580c; }
.badge-primary { background: #eff6ff; color: #2563eb; }
.badge-info { background: #ecfeff; color: #0891b2; }
.badge-success { background: #ecfdf5; color: #059669; }
.badge-danger { background: #fef2f2; color: #dc2626; }
.badge-secondary { background: #f1f5f9; color: #64748b; }
</style>
