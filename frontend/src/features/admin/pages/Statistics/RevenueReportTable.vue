<template>
  <div class="table-card ocean-card">
    <div class="card-header">
      <h3 class="card-title">Báo cáo doanh thu theo ngày</h3>
    </div>
    <div class="table-responsive">
      <table class="ocean-table">
        <thead>
          <tr>
            <th>Ngày</th>
            <th class="text-center">Số đơn hàng</th>
            <th class="text-right">Doanh thu</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="report && report.length > 0">
            <tr v-for="(day, index) in report" :key="index">
              <td>
                <div class="date-cell">
                  <div class="date-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                  </div>
                  <strong>{{ day.date }}</strong>
                </div>
              </td>
              <td class="text-center">
                <span class="order-count">{{ day.orders }}</span>
              </td>
              <td class="text-right">
                <strong class="revenue-val">{{ formatCurrency(day.revenue) }}</strong>
              </td>
            </tr>
          </template>
          <tr v-else>
            <td colspan="3" class="text-center empty-cell">Không có dữ liệu</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  report: {
    type: Array,
    default: () => []
  }
});

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};
</script>

<style scoped>
.table-card {
  background: var(--card-bg, #ffffff);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04);
  border: 1px solid var(--border-color, #f1f5f9);
  transition: box-shadow 0.3s ease;
}

.table-card:hover {
  box-shadow: 0 10px 32px rgba(15, 23, 42, 0.08);
}

.card-header {
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-main, #1e293b);
  letter-spacing: -0.01em;
}

.table-responsive {
  overflow-x: auto;
  max-height: 420px;
  border-radius: 8px;
}

/* Custom Scrollbar cho table responsive */
.table-responsive::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.table-responsive::-webkit-scrollbar-track {
  background: transparent;
}
.table-responsive::-webkit-scrollbar-thumb {
  background: var(--border-color, #cbd5e1);
  border-radius: 10px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.ocean-table {
  width: 100%;
  border-collapse: collapse;
}

.ocean-table th {
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  background: var(--surface-container, #f8fafc);
  position: sticky;
  top: 0;
  z-index: 10;
  white-space: nowrap;
}

/* Fix CSS specificity cho TH */
.ocean-table th.text-center {
  text-align: center;
}
.ocean-table th.text-right {
  text-align: right;
}

.ocean-table td {
  padding: 16px;
  border-bottom: 1px solid var(--border-color, #f1f5f9);
  vertical-align: middle;
  transition: all 0.2s ease;
}

.ocean-table tr:last-child td {
  border-bottom: none;
}

.ocean-table tr:hover td {
  background: var(--hover-bg, #f8fafc);
}

.date-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--text-main, #334155);
}

.date-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(14, 165, 233, 0.1);
  color: var(--primary, #0ea5e9);
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.ocean-table tr:hover .date-icon {
  transform: scale(1.1) rotate(-5deg);
}

.order-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 32px;
  padding: 0 8px;
  border-radius: 16px;
  background: var(--surface-container, #f1f5f9);
  font-weight: 700;
  color: var(--text-main, #475569);
  font-size: 0.95rem;
}

.revenue-val {
  color: var(--primary, #0ea5e9);
  font-size: 1rem;
}

.text-right { text-align: right; }
.text-center { text-align: center; }

.empty-cell {
  padding: 60px 20px !important;
  color: var(--text-muted, #94a3b8);
  font-weight: 500;
  font-size: 0.95rem;
}
</style>
