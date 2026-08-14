<template>
  <div class="table-card ocean-card">
    <div class="card-header">
      <h3 class="card-title">Báo cáo doanh thu nhân viên</h3>
      <button class="btn btn-outline-success btn-sm d-flex align-items-center" @click="handleExport" title="Xuất báo cáo nhân viên">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="8" y1="13" x2="16" y2="13"></line>
          <line x1="8" y1="17" x2="16" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        Xuất Excel
      </button>
    </div>
    <div class="table-responsive">
      <table class="ocean-table">
        <thead>
          <tr>
            <th>Nhân viên</th>
            <th>Vai trò</th>
            <th class="text-center sortable" @click="sortBy('total_orders')">
              Tổng số đơn
              <span class="sort-icon" v-if="sortKey === 'total_orders'">{{ sortAsc ? '↑' : '↓' }}</span>
            </th>
            <th class="text-center">Hoàn thành</th>
            <th class="text-center">Hủy / Hoàn</th>
            <th class="text-right sortable" @click="sortBy('total_revenue')">
              Doanh thu thực thu
              <span class="sort-icon" v-if="sortKey === 'total_revenue'">{{ sortAsc ? '↑' : '↓' }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-if="sortedSales && sortedSales.length > 0">
            <tr v-for="(staff, index) in sortedSales" :key="index">
              <td>
                <div class="staff-cell">
                  <div class="staff-avatar">
                    {{ getInitials(staff.staff_name) }}
                  </div>
                  <div class="staff-info">
                    <strong>{{ staff.staff_name }}</strong>
                    <span class="staff-email">{{ staff.staff_email }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge" :class="getRoleClass(staff.role)">
                  {{ formatRole(staff.role) }}
                </span>
              </td>
              <td class="text-center">
                <span class="order-count">{{ staff.total_orders }}</span>
              </td>
              <td class="text-center">
                <span class="badge bg-success">{{ staff.completed_orders || 0 }}</span>
              </td>
              <td class="text-center">
                <span class="badge" :class="(staff.cancelled_orders || 0) > 0 ? 'bg-danger' : 'bg-secondary'">{{ staff.cancelled_orders || 0 }}</span>
              </td>
              <td class="text-right">
                <strong class="revenue-val">{{ formatCurrency(staff.total_revenue) }}</strong>
              </td>
            </tr>
          </template>
          <tr v-else>
            <td colspan="6" class="text-center empty-cell">Không có dữ liệu</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  sales: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['export']);

const sortKey = ref('total_revenue');
const sortAsc = ref(false);

const sortedSales = computed(() => {
  if (!props.sales) return [];
  const data = [...props.sales];
  data.sort((a, b) => {
    let valA = a[sortKey.value];
    let valB = b[sortKey.value];
    if (valA < valB) return sortAsc.value ? -1 : 1;
    if (valA > valB) return sortAsc.value ? 1 : -1;
    return 0;
  });
  return data;
});

const sortBy = (key) => {
  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value;
  } else {
    sortKey.value = key;
    sortAsc.value = false;
  }
};

const handleExport = () => {
  emit('export');
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const getInitials = (name) => {
  if (!name) return 'U';
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

const formatRole = (role) => {
  const roles = {
    'admin': 'Quản trị viên',
    'staff': 'Nhân viên',
    'seller': 'Người bán'
  };
  return roles[role] || role;
};

const getRoleClass = (role) => {
  const classes = {
    'admin': 'bg-danger',
    'staff': 'bg-primary',
    'seller': 'bg-info text-dark'
  };
  return classes[role] || 'bg-secondary';
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
  height: 100%;
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
  margin: 0;
}

.table-responsive {
  overflow-x: auto;
  max-height: 420px;
  border-radius: 8px;
}

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

.ocean-table th.sortable {
  cursor: pointer;
  user-select: none;
}
.ocean-table th.sortable:hover {
  background: var(--border-color, #e2e8f0);
}

.sort-icon {
  margin-left: 4px;
  font-size: 1rem;
}

.ocean-table th.text-center { text-align: center; }
.ocean-table th.text-right { text-align: right; }

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

.staff-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.staff-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--primary, #0ea5e9);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
}

.staff-info {
  display: flex;
  flex-direction: column;
}
.staff-info strong {
  color: var(--text-main, #334155);
  font-size: 0.95rem;
}
.staff-email {
  color: var(--text-muted, #94a3b8);
  font-size: 0.8rem;
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
