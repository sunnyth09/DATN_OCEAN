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
              <td><strong>{{ day.date }}</strong></td>
              <td class="text-center">{{ day.orders }}</td>
              <td class="text-right text-ocean"><strong>{{ formatCurrency(day.revenue) }}</strong></td>
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
  background: var(--card-bg);
  padding: 24px;
}

.card-header {
  margin-bottom: 20px;
}

.card-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text-main);
}

.table-responsive {
  overflow-x: auto;
  max-height: 400px;
}

.ocean-table {
  width: 100%;
  border-collapse: collapse;
}

.ocean-table th {
  text-align: left;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-color);
  background: var(--surface-container);
  position: sticky;
  top: 0;
  z-index: 10;
}

.ocean-table td {
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
  transition: background 0.2s;
}

.ocean-table tr:hover td {
  background: var(--hover-bg);
}

.text-right { text-align: right; }
.text-center { text-align: center; }
.text-ocean { color: var(--primary); }

.empty-cell {
  padding: 40px !important;
  color: var(--text-muted);
  font-weight: 500;
}
</style>
