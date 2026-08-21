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

const getEffectiveDate = (h) => {
  if (!h) return '';
  return h.happened_at || h.created_at || '';
};

const getEffectiveTime = (h) => {
  const dt = getEffectiveDate(h);
  if (!dt) return 0;
  const time = new Date(dt).getTime();
  return isNaN(time) ? 0 : time;
};

const sortedHistories = () => {
  const list = Array.isArray(props.histories) ? [...props.histories] : [];

  // Sắp xếp thời gian mới nhất lên trên
  list.sort((a, b) => {
    const idA = Number(a.history_id || a.id || 0);
    const idB = Number(b.history_id || b.id || 0);
    if (idA > 0 && idB > 0 && idA !== idB) {
      return idB - idA;
    }
    const aTime = getEffectiveTime(a);
    const bTime = getEffectiveTime(b);
    return bTime - aTime;
  });

  // Lọc bỏ các bản ghi webhook retry trùng lặp liên tiếp
  const result = [];
  for (let i = 0; i < list.length; i++) {
    const curr = list[i];
    const prev = result[result.length - 1];

    if (prev) {
      const sameOld = (prev.old_status || '') === (curr.old_status || '');
      const sameNew = (prev.new_status || '') === (curr.new_status || '');
      const sameGhn = (prev.ghn_status || '') === (curr.ghn_status || '');
      const sameNote = (prev.note || '') === (curr.note || '');
      const sameDesc = (prev.description || '') === (curr.description || '');

      // Nếu 2 bản ghi liên tiếp hoàn toàn giống nhau về trạng thái và nội dung mô tả, coi là trùng lặp và bỏ qua
      if (sameOld && sameNew && sameGhn && sameNote && sameDesc) {
        continue;
      }
    }
    result.push(curr);
  }

  return result;
};

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
            {{ formatDate(getEffectiveDate(h)) }}
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
  width: 2.5px;
  background: linear-gradient(180deg, #3b82f6 0%, #8b5cf6 50%, #cbd5e1 100%);
  border-radius: 2px;
}

.history-dot-wrap {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  margin-top: 4px;
  flex-shrink: 0;
}

.history-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #94a3b8;
  border: 2.5px solid #ffffff;
  box-shadow: 0 0 0 2px #cbd5e1;
  position: relative;
  transition: all 0.2s ease;
}

.history-item.latest-entry .history-dot {
  box-shadow: 0 0 0 3px #3b82f6, 0 0 10px rgba(59, 130, 246, 0.45);
}

.history-dot-pulse {
  position: absolute;
  top: -5px;
  left: -5px;
  right: -5px;
  bottom: -5px;
  border-radius: 50%;
  background: rgba(59, 130, 246, 0.25);
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
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 11px 16px;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.history-item:hover .history-content {
  transform: translateX(3px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.history-item.latest-entry .history-content {
  background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
  border-color: #bfdbfe;
  border-left: 4px solid #3b82f6;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.06);
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
  font-weight: 600;
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
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #e2e8f0;
}

.history-location {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.78rem;
  color: #0284c7;
  font-weight: 700;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  padding: 2px 8px;
  border-radius: 6px;
}

.history-time {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.76rem;
  color: #64748b;
  font-weight: 600;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  padding: 2px 8px;
  border-radius: 6px;
}

/* Badges with vivid modern gradients */
.status-badge.sm {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.carrier-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.badge-carrier-oe {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
}

.badge-carrier-ghn {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(234, 88, 12, 0.25);
}

.badge-carrier-admin {
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(99, 102, 241, 0.25);
}

.badge-carrier-system {
  background: linear-gradient(135deg, #64748b 0%, #475569 100%);
  color: #ffffff;
}

.badge-carrier-other {
  background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
  color: #ffffff;
}

.badge-warning {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
}
.badge-primary {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
  box-shadow: 0 2px 6px rgba(59, 130, 246, 0.25);
}
.badge-info {
  background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
  color: white;
  box-shadow: 0 2px 6px rgba(6, 182, 212, 0.25);
}
.badge-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
}
.badge-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25);
}
.badge-secondary {
  background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
  color: white;
}

@media (max-width: 576px) {
  .history-item { gap: 12px; padding-bottom: 18px; }
  .history-content { padding: 9px 12px; }
  .status-badge.sm, .carrier-badge { font-size: 0.7rem; padding: 3px 9px; }
  .history-note { font-size: 0.82rem; }
  .history-meta-row { gap: 8px; }
}
</style>

