<template>
  <div class="chart-card ocean-card">
    <div class="card-header">
      <div class="header-left">
        <h3 class="card-title">Trạng thái đơn hàng</h3>
        <p class="card-subtitle">Phân bổ tỷ lệ các đơn hàng trong kỳ</p>
      </div>
      <div v-if="hasData" class="total-badge">
        <span>Tổng:</span>
        <strong>{{ totalOrders }}</strong>
        <span class="unit">đơn</span>
      </div>
    </div>

    <div v-if="hasData" class="chart-body">
      <!-- Donut Chart Container -->
      <div class="donut-section">
        <div class="donut-wrapper">
          <Doughnut :data="chartData" :options="chartOptions" />
          <div class="donut-center-info">
            <span class="donut-number">{{ totalOrders }}</span>
            <span class="donut-label">Đơn hàng</span>
          </div>
        </div>
      </div>

      <!-- Ultra-Clean Status Rows List (No heavy box borders) -->
      <div class="status-list-container">
        <div class="status-list">
          <div
            v-for="(item, idx) in legendItems"
            :key="idx"
            class="status-row"
          >
            <!-- Status Label with simple clean dot -->
            <div class="status-main">
              <span class="status-dot" :style="{ backgroundColor: item.color }"></span>
              <span class="status-name" :title="item.label">{{ item.label }}</span>
            </div>

            <!-- Progress bar in the middle -->
            <div class="status-bar-wrap">
              <div
                class="status-bar-fill"
                :style="{ width: item.percent + '%', backgroundColor: item.color }"
              ></div>
            </div>

            <!-- Numbers on the right -->
            <div class="status-stats">
              <span class="status-count">{{ item.count }}</span>
              <span class="status-pct">{{ item.percent }}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon-wrap">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </div>
      <div class="empty-title">Chưa có dữ liệu đơn hàng</div>
      <div class="empty-desc">Không có đơn hàng nào trong khoảng thời gian đã chọn</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import { useUiStore } from '@/stores/ui';

ChartJS.register(ArcElement, Tooltip);

const props = defineProps({
  data: {
    type: Object,
    default: null
  }
});

const uiStore = useUiStore();

// Client-side mapping & clean colors
const STATUS_DICT = {
  pending: { label: 'Chờ xác nhận', color: '#f59e0b' },
  confirmed: { label: 'Đã xác nhận', color: '#3b82f6' },
  processing: { label: 'Đang xử lý', color: '#06b6d4' },
  packing: { label: 'Đang đóng gói', color: '#0ea5e9' },
  awaiting_pickup: { label: 'Chờ lấy hàng', color: '#6366f1' },
  shipping: { label: 'Đang giao hàng', color: '#0284c7' },
  delivered: { label: 'Đã giao hàng', color: '#14b8a6' },
  completed: { label: 'Hoàn thành', color: '#10b981' },
  cancelled: { label: 'Đã hủy', color: '#ef4444' },
  return_requested: { label: 'Yêu cầu đổi/trả', color: '#ea580c' },
  return_approved: { label: 'Đã duyệt đổi/trả', color: '#4f46e5' },
  return_rejected: { label: 'Từ chối đổi/trả', color: '#e11d48' },
  returning: { label: 'Khách đang gửi hoàn', color: '#8b5cf6' },
  warehouse_received: { label: 'Kho đã nhận hoàn', color: '#7c3aed' },
  inspection_failed: { label: 'Hoàn không đạt QC', color: '#be123c' },
  inspected_ok: { label: 'Hoàn đạt QC', color: '#059669' },
  returned: { label: 'Đã nhận hàng hoàn', color: '#64748b' },
  refunded: { label: 'Đã hoàn tiền', color: '#475569' },
};

const normalizeLabel = (rawLabel) => {
  if (!rawLabel) return 'Khác';
  const lower = String(rawLabel).toLowerCase().trim();
  if (STATUS_DICT[lower]) return STATUS_DICT[lower].label;
  if (lower === 'awaiting_pickup' || lower === 'awaiting pickup') return 'Chờ lấy hàng';
  return rawLabel;
};

const normalizeColor = (rawLabel, fallbackColor) => {
  if (!rawLabel) return fallbackColor || '#94a3b8';
  const lower = String(rawLabel).toLowerCase().trim();
  if (STATUS_DICT[lower]) return STATUS_DICT[lower].color;
  for (const key in STATUS_DICT) {
    if (STATUS_DICT[key].label.toLowerCase() === lower) {
      return STATUS_DICT[key].color;
    }
  }
  return fallbackColor || '#94a3b8';
};

const hasData = computed(() => {
  return props.data && props.data.labels && props.data.labels.length > 0 && props.data.datasets && props.data.datasets[0]?.data?.length > 0;
});

const totalOrders = computed(() => {
  if (!hasData.value) return 0;
  const values = props.data.datasets[0].data || [];
  return values.reduce((sum, val) => sum + Number(val || 0), 0);
});

const legendItems = computed(() => {
  if (!hasData.value) return [];
  const rawLabels = props.data.labels || [];
  const dataset = props.data.datasets[0] || {};
  const values = dataset.data || [];
  const colors = dataset.backgroundColor || [];
  const total = totalOrders.value || 1;

  return rawLabels.map((rawLabel, idx) => {
    const count = Number(values[idx] || 0);
    const percent = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
    const label = normalizeLabel(rawLabel);
    const color = normalizeColor(rawLabel, colors[idx]);

    return {
      label,
      count,
      percent: percent.endsWith('.0') ? percent.replace('.0', '') : percent,
      color,
    };
  }).filter(item => item.count > 0);
});

const chartData = computed(() => {
  if (!hasData.value) return { labels: [], datasets: [] };
  const items = legendItems.value;
  const isDark = uiStore.isBackofficeDarkMode;

  return {
    labels: items.map(i => i.label),
    datasets: [{
      data: items.map(i => i.count),
      backgroundColor: items.map(i => i.color),
      borderWidth: 2,
      borderColor: isDark ? '#1e293b' : '#ffffff',
      hoverOffset: 6,
    }]
  };
});

const chartOptions = computed(() => {
  const isDark = uiStore.isBackofficeDarkMode;
  return {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '74%',
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.96)' : 'rgba(255, 255, 255, 0.96)',
        titleColor: isDark ? '#f8fafc' : '#0f172a',
        bodyColor: isDark ? '#94a3b8' : '#475569',
        borderColor: isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)',
        borderWidth: 1,
        padding: 10,
        cornerRadius: 8,
        boxPadding: 4,
        usePointStyle: true,
        titleFont: { family: "'Inter', sans-serif", weight: '700', size: 12 },
        bodyFont: { family: "'Inter', sans-serif", weight: '600', size: 12 },
        callbacks: {
          label: function(context) {
            const label = context.label || '';
            const value = context.raw || 0;
            const total = totalOrders.value || 1;
            const pct = ((value / total) * 100).toFixed(1);
            return ` ${label}: ${value} đơn (${pct}%)`;
          }
        }
      }
    }
  };
});
</script>

<style scoped>
.chart-card {
  background: var(--card-bg, #ffffff);
  padding: 24px;
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  border: 1px solid var(--border-color, #e2e8f0);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  height: 100%;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.card-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
  margin: 0;
  line-height: 1.2;
}

.card-subtitle {
  font-size: 0.78rem;
  color: var(--text-muted, #64748b);
  margin: 3px 0 0 0;
  font-weight: 500;
}

.total-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 0.76rem;
  color: #64748b;
  flex-shrink: 0;
}
.total-badge strong {
  color: #e63b6f;
  font-size: 0.85rem;
  font-weight: 800;
}
.total-badge .unit {
  font-size: 0.72rem;
}

.chart-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1;
}

.donut-section {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px 0;
}

.donut-wrapper {
  position: relative;
  width: 180px;
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.donut-center-info {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  pointer-events: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.donut-number {
  font-size: 1.55rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
  line-height: 1;
  letter-spacing: -0.5px;
}

.donut-label {
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--text-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.6px;
  margin-top: 3px;
}

/* Status list */
.status-list-container {
  flex: 1;
  max-height: 250px;
  overflow-y: auto;
  padding: 2px 4px 2px 0;
}
.status-list-container::-webkit-scrollbar {
  width: 4px;
}
.status-list-container::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.status-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

/* Clean Row Layout without bulky borders */
.status-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 10px;
  border-radius: 8px;
  background: transparent;
  transition: background 0.15s ease;
}
.status-row:hover {
  background: #f8fafc;
}

.status-main {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 125px;
  max-width: 145px;
  overflow: hidden;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.status-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-main, #1e293b);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-bar-wrap {
  flex: 1;
  height: 4px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
  min-width: 40px;
}

.status-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.status-stats {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
  gap: 6px;
  min-width: 65px;
  flex-shrink: 0;
}

.status-count {
  font-size: 0.84rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
}

.status-pct {
  font-size: 0.72rem;
  color: var(--text-muted, #64748b);
  font-weight: 500;
}

/* Dark Mode Overrides */
:global(html.dark) .status-row:hover {
  background: #1e293b;
}
:global(html.dark) .status-name {
  color: #f1f5f9;
}
:global(html.dark) .status-count {
  color: #f8fafc;
}
:global(html.dark) .status-bar-wrap {
  background: #334155;
}
:global(html.dark) .total-badge {
  background: #1e293b;
  border-color: #334155;
  color: #94a3b8;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 240px;
  padding: 24px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px dashed #cbd5e1;
  text-align: center;
}

.empty-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 10px;
}

.empty-title {
  font-size: 0.88rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 3px;
}

.empty-desc {
  font-size: 0.78rem;
  color: #64748b;
}
</style>
