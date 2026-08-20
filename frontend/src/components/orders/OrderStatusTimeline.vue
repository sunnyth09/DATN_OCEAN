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
  admin: 'Admin',
  ghn_webhook: 'GHN',
  ghn_manual_sync: 'GHN',
  ghn_api: 'GHN',
  ocean_express: 'Ocean Express',
  oe_webhook: 'Ocean Express',
  ocean_express_webhook: 'Ocean Express',
  'carrier api': 'Vận chuyển',
  carrier_api: 'Vận chuyển',
};

const carrierStatusNames = {
  ready_to_pick: 'Chờ lấy hàng',
  exception: 'Báo ngoại lệ',
  picking: 'Đang lấy hàng',
  money_collect_picking: 'Thu tiền khi lấy hàng',
  picked: 'Đã lấy hàng',
  stored: 'Đã nhập kho',
  storing: 'Đang lưu kho',
  in_hub: 'Tại kho trung chuyển',
  hub_inbound: 'Nhập bưu cục',
  hub_outbound: 'Xuất bưu cục',
  transporting: 'Đang trung chuyển',
  sorting: 'Đang phân loại',
  delivering: 'Đang giao hàng',
  money_collect_delivering: 'Thu tiền khi giao',
  delivery_fail: 'Giao hàng thất bại',
  delivered: 'Đã giao hàng',
  completed: 'Hoàn tất',
  cancel: 'Đã hủy',
  cancelled: 'Đã hủy',
  damage: 'Hư hỏng',
  lost: 'Thất lạc',
  waiting_to_return: 'Chờ hoàn hàng',
  return_requested: 'Yêu cầu trả hàng',
  return: 'Hoàn hàng',
  return_transporting: 'Đang chuyển hoàn',
  return_sorting: 'Phân loại hàng hoàn',
  returning: 'Đang hoàn hàng',
  return_fail: 'Hoàn hàng thất bại',
  returned: 'Đã hoàn hàng',
};

const sortedHistories = () => [...props.histories].sort((a, b) => {
  const aTime = new Date(a.happened_at || a.created_at || 0).getTime();
  const bTime = new Date(b.happened_at || b.created_at || 0).getTime();
  if (bTime !== aTime) {
    return bTime - aTime;
  }
  return (b.history_id || b.id || 0) - (a.history_id || a.id || 0);
});

const readableFallback = (value) => String(value || '').replace(/_/g, ' ');
const getSourceLabel = (source) => sourceLabels[source] || readableFallback(source);
const getCarrierStatusLabel = (status) => carrierStatusNames[status] || readableFallback(status);
const isSameStatus = (h) => h.old_status && h.old_status === h.new_status;
const normalizeText = (value) => String(value || '').trim().toLowerCase();

const getCarrierBadge = (h) => {
  const src = normalizeText(h.source);
  const note = normalizeText(h.note);
  const desc = normalizeText(h.description);
  const rawStatus = normalizeText(h.ghn_status);

  const isOcean = src.includes('ocean') || src.includes('oe_') || note.includes('ocean') || desc.includes('ocean');
  const isGhn = src.includes('ghn') || note.includes('ghn') || desc.includes('ghn');

  if (isOcean) {
    const statusText = rawStatus ? getCarrierStatusLabel(rawStatus) : '';
    return {
      label: statusText ? `Ocean Express: ${statusText}` : 'Ocean Express',
      badgeClass: 'badge-carrier-oe',
    };
  }

  if (isGhn) {
    const statusText = rawStatus ? getCarrierStatusLabel(rawStatus) : '';
    return {
      label: statusText ? `GHN: ${statusText}` : 'GHN',
      badgeClass: 'badge-carrier-ghn',
    };
  }

  if (src === 'manual' || src === 'admin') {
    return {
      label: 'Admin',
      badgeClass: 'badge-carrier-admin',
    };
  }

  if (src === 'system') {
    return {
      label: 'Hệ thống',
      badgeClass: 'badge-carrier-system',
    };
  }

  if (h.source || h.ghn_status) {
    const statusText = rawStatus ? getCarrierStatusLabel(rawStatus) : '';
    return {
      label: statusText ? `Vận chuyển: ${statusText}` : getSourceLabel(h.source),
      badgeClass: 'badge-carrier-other',
    };
  }

  return null;
};

const shouldShowDescription = (h) => {
  if (!h.description) return false;
  const description = normalizeText(h.description);
  return description !== normalizeText(h.note)
    && description !== normalizeText(h.ghn_status)
    && description !== normalizeText(getCarrierStatusLabel(h.ghn_status));
};
</script>

<template>
  <div class="order-status-timeline">
    <div v-for="(h, index) in sortedHistories()" :key="h.history_id || h.id || index" class="history-item" :class="{ 'latest-entry': index === 0 }">
      <div class="history-dot-wrap">
        <div class="history-dot" :class="getStatusBadgeClass(h.new_status)">
          <div v-if="index === 0" class="history-dot-pulse"></div>
        </div>
      </div>
      <div class="history-content">
        <div class="history-transition">
          <span class="status-badge sm" :class="getStatusBadgeClass(h.old_status)" v-if="h.old_status && !isSameStatus(h)">
            {{ getStatusLabel(h.old_status) }}
          </span>
          <svg v-if="h.old_status && !isSameStatus(h)" class="transition-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.5">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
          <span class="status-badge sm" :class="getStatusBadgeClass(h.new_status)">
            {{ getStatusLabel(h.new_status) }}
          </span>
          <span v-if="showGhnMeta && getCarrierBadge(h)" class="carrier-badge" :class="getCarrierBadge(h).badgeClass">
            {{ getCarrierBadge(h).label }}
          </span>
        </div>
        <p class="history-note" v-if="h.note">{{ h.note }}</p>
        <p class="history-desc" v-if="shouldShowDescription(h)">{{ h.description }}</p>
        <div class="history-meta-row">
          <span class="history-location" v-if="showGhnMeta && h.location">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {{ h.location }}
          </span>
          <span class="history-time">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            {{ formatDate(h.happened_at || h.created_at) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.order-status-timeline {
  display: flex;
  flex-direction: column;
  position: relative;
  padding-left: 6px;
}

.history-item {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  position: relative;
  padding-bottom: 22px;
}

.history-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 6px;
  top: 18px;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}

.history-dot-wrap {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  margin-top: 4px;
  flex-shrink: 0;
}

.history-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #94a3b8;
  border: 2.5px solid #ffffff;
  box-shadow: 0 0 0 1.5px #cbd5e1;
  position: relative;
}

.history-item.latest-entry .history-dot {
  box-shadow: 0 0 0 2px #3b82f6, 0 0 8px rgba(59, 130, 246, 0.4);
}

.history-dot-pulse {
  position: absolute;
  top: -4px;
  left: -4px;
  right: -4px;
  bottom: -4px;
  border-radius: 50%;
  background: rgba(59, 130, 246, 0.2);
  animation: dotPulse 2s infinite cubic-bezier(0.45, 0, 0.55, 1);
}

@keyframes dotPulse {
  0% { transform: scale(0.9); opacity: 0.8; }
  50% { transform: scale(1.6); opacity: 0; }
  100% { transform: scale(0.9); opacity: 0; }
}

.history-content {
  flex: 1;
  min-width: 0;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 14px;
  transition: all 0.2s ease;
}

.history-item.latest-entry .history-content {
  background: #ffffff;
  border-color: #cbd5e1;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.history-transition {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 6px;
}

.transition-arrow {
  color: #94a3b8;
  flex-shrink: 0;
}

.history-note {
  margin: 4px 0 2px;
  font-size: 0.86rem;
  color: #334155;
  font-weight: 500;
  line-height: 1.45;
  word-break: break-word;
}

.history-desc {
  margin: 2px 0 4px;
  font-size: 0.82rem;
  color: #64748b;
  line-height: 1.4;
  word-break: break-word;
}

.history-meta-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px dashed #f1f5f9;
}

.history-location {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.78rem;
  color: #0369a1;
  font-weight: 600;
}

.history-time {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.76rem;
  color: #64748b;
}

/* Badges */
.status-badge.sm {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.carrier-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.badge-carrier-oe {
  background: #0284c7;
  color: #ffffff;
}

.badge-carrier-ghn {
  background: #ea580c;
  color: #ffffff;
}

.badge-carrier-admin {
  background: #6366f1;
  color: #ffffff;
}

.badge-carrier-system {
  background: #475569;
  color: #ffffff;
}

.badge-carrier-other {
  background: #0891b2;
  color: #ffffff;
}

.badge-warning { background: #f59e0b; color: white; }
.badge-primary { background: #3b82f6; color: white; }
.badge-info { background: #06b6d4; color: white; }
.badge-success { background: #10b981; color: white; }
.badge-danger { background: #ef4444; color: white; }
.badge-secondary { background: #64748b; color: white; }

@media (max-width: 576px) {
  .history-item { gap: 10px; padding-bottom: 16px; }
  .history-content { padding: 8px 10px; }
  .status-badge.sm, .carrier-badge { font-size: 0.68rem; padding: 2px 8px; }
  .history-note { font-size: 0.8rem; }
  .history-meta-row { gap: 8px; }
}
</style>

