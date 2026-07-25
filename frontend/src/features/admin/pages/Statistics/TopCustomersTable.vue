<template>
  <div class="table-card ocean-card">
    <div class="card-header">
      <h3 class="card-title">Top khách hàng mua nhiều</h3>
    </div>
    <div class="table-responsive">
      <table class="ocean-table">
        <thead>
          <tr>
            <th>Khách hàng</th>
            <th class="text-center">Số đơn</th>
            <th class="text-right">Tổng chi tiêu</th>
            <th class="text-right">Ngày mua gần nhất</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="customers && customers.length > 0">
            <tr v-for="(customer, index) in customers" :key="customer.id || index">
              <td>
                <div class="customer-cell">
                  <div class="customer-avatar" :style="{ background: getRandomColor(customer.name) }">
                    {{ getInitials(customer.name) }}
                  </div>
                  <div class="customer-info">
                    <span class="customer-name">{{ customer.name }}</span>
                    <span class="customer-email">{{ customer.email }}</span>
                  </div>
                </div>
              </td>
              <td class="text-center"><strong>{{ customer.total_orders }}</strong></td>
              <td class="text-right text-ocean"><strong>{{ formatCurrency(customer.total_spent) }}</strong></td>
              <td class="text-right color-muted">{{ customer.last_order }}</td>
            </tr>
          </template>
          <tr v-else>
            <td colspan="4" class="text-center empty-cell">Không có dữ liệu</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  customers: {
    type: Array,
    default: () => []
  }
});

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const getInitials = (name) => {
  if (!name) return 'KH';
  const parts = name.trim().split(' ');
  let initials = parts[0].charAt(0).toUpperCase();
  if (parts.length > 1) {
    initials += parts[parts.length - 1].charAt(0).toUpperCase();
  }
  return initials;
};

const getRandomColor = (string) => {
  if (!string) return '#E63B6F';
  const colors = ['#E63B6F', '#26a69a', '#ffa726', '#7e57c2', '#ef5350', '#66bb6a', '#ec407a'];
  let sum = 0;
  for(let i=0; i<string.length; i++) {
    sum += string.charCodeAt(i);
  }
  return colors[sum % colors.length];
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
  margin-bottom: 20px;
}

.card-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text-main);
}

.table-responsive {
  overflow-x: auto;
}

.ocean-table {
  width: 100%;
  min-width: 550px;
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
  white-space: nowrap;
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

.customer-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.customer-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.customer-info {
  display: flex;
  flex-direction: column;
}

.customer-name {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-main);
}

.customer-email {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.text-right { text-align: right; }
.text-center { text-align: center; }
.text-ocean { color: var(--primary); }
.color-muted { color: var(--text-muted); font-size: 0.85rem; }

.empty-cell {
  padding: 40px !important;
  color: var(--text-muted);
  font-weight: 500;
}
</style>
