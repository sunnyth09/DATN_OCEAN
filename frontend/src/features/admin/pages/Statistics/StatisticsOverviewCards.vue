<template>
  <div class="overview-grid">
    <!-- Revenue -->
    <div class="stat-card ocean-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #E63B6F, #4fc3f7)">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      </div>
      <div class="stat-content">
        <p class="stat-title">Doanh thu</p>
        <h3 class="stat-value">{{ formatCurrency(data.total_revenue?.value) }}</h3>
        <span class="stat-badge" :class="data.total_revenue?.isUp ? 'up' : 'down'">
          {{ data.total_revenue?.isUp ? '↑' : '↓' }} {{ data.total_revenue?.change }}
        </span>
      </div>
    </div>

    <!-- Orders -->
    <div class="stat-card ocean-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #26a69a, #80cbc4)">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      </div>
      <div class="stat-content">
        <p class="stat-title">Đơn hàng</p>
        <h3 class="stat-value">{{ data.total_orders?.value || 0 }}</h3>
        <span class="stat-badge" :class="data.total_orders?.isUp ? 'up' : 'down'">
          {{ data.total_orders?.isUp ? '↑' : '↓' }} {{ data.total_orders?.change }}
        </span>
      </div>
    </div>

    <!-- AOV (Average Order Value) -->
    <div class="stat-card ocean-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #00897b, #4db6ac)">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><line x1="12" y1="6" x2="12" y2="8"/><line x1="12" y1="16" x2="12" y2="18"/></svg>
      </div>
      <div class="stat-content">
        <p class="stat-title">Giá trị TB / Đơn (AOV)</p>
        <h3 class="stat-value">{{ formatCurrency(data.aov?.value) }}</h3>
        <span class="stat-badge" :class="data.aov?.isUp ? 'up' : 'down'">
          {{ data.aov?.isUp ? '↑' : '↓' }} {{ data.aov?.change }}
        </span>
      </div>
    </div>

    <!-- Customers -->
    <div class="stat-card ocean-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #7e57c2, #b39ddb)">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <div class="stat-content">
        <p class="stat-title">Khách hàng mới</p>
        <h3 class="stat-value">{{ data.total_customers?.value || 0 }}</h3>
        <span class="stat-badge" :class="data.total_customers?.isUp ? 'up' : 'down'">
          {{ data.total_customers?.isUp ? '↑' : '↓' }} {{ data.total_customers?.change }}
        </span>
      </div>
    </div>

    <!-- Today's Special Cards -->
    <div class="stat-card-small ocean-card">
      <div class="sc-left">
        <p>Hôm nay (Thực thu)</p>
        <h4>{{ formatCurrency(data.today_revenue) }}</h4>
      </div>
      <div class="sc-right blue-text">
        <span>Đơn</span>
        <strong>{{ data.today_orders || 0 }}</strong>
      </div>
    </div>

    <div class="stat-card-small ocean-card">
      <div class="sc-left">
        <p>Chờ xử lý</p>
        <h4 class="orange-text">{{ data.pending_orders || 0 }}</h4>
      </div>
    </div>

    <div class="stat-card-small ocean-card">
      <div class="sc-left">
        <p>Đã Hủy</p>
        <h4 class="red-text">{{ data.cancelled_orders || 0 }}</h4>
      </div>
    </div>

    <div class="stat-card-small ocean-card">
      <div class="sc-left">
        <p>Vốn đọng (>60 ngày)</p>
        <h4 class="amber-text">{{ formatCurrency(data.tied_up_capital) }}</h4>
      </div>
      <div class="sc-right text-muted">
        <span>Sản phẩm</span>
        <strong>{{ data.slow_moving_count || 0 }}</strong>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: {
    type: Object,
    default: () => ({})
  }
});

const formatCurrency = (val) => {
  if (!val) return '0 đ';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};
</script>

<style scoped>
.overview-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  padding: 24px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  background: var(--card-bg);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  grid-column: span 1;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(230, 59, 111, 0.1);
  border-color: rgba(230, 59, 111, 0.3);
}

.stat-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-content {
  flex: 1;
}

.stat-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.stat-value {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--text-main);
  margin-bottom: 8px;
  letter-spacing: -0.5px;
}

.stat-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
}

.stat-badge.up {
  background: rgba(38, 166, 154, 0.15);
  color: #167a70;
}

.stat-badge.down {
  background: rgba(239, 83, 80, 0.15);
  color: #c62828;
}

.stat-card-small {
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--card-bg);
}

.sc-left p {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-muted);
  margin-bottom: 4px;
  text-transform: uppercase;
}
.sc-left h4 {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-main);
}
.sc-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.sc-right span {
  font-size: 0.75rem;
  font-weight: 600;
}
.sc-right strong {
  font-size: 1.25rem;
  font-weight: 800;
}

.blue-text { color: var(--primary); }
.orange-text { color: #f57c00; }
.red-text { color: #d32f2f; }
.amber-text { color: #d97706; }

@media (max-width: 1400px) {
  .overview-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 1024px) {
  .overview-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
