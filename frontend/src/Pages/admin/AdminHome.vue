<template>
  <div class="dashboard">
    <!-- Welcome -->
    <!-- <div class="welcome-card ocean-card animate-in">
      <div>
        <h1 class="welcome-title">Chào mừng trở lại, <span class="highlight">Admin</span> 🌊</h1>
      </div>
    </div> -->

    <!-- Stat Cards -->
    <div class="stats-grid">
      <div class="stat-card ocean-card animate-in" v-for="(stat, i) in stats" :key="stat.title" :style="{ animationDelay: `${i * 0.08}s` }">
        <div class="stat-icon" :style="{ background: stat.iconBg }">
          <span v-html="stat.icon"></span>
        </div>
        <div class="stat-body">
          <span class="stat-label">{{ stat.title }}</span>
          <span class="stat-value">{{ stat.value }}</span>
        </div>
        <span class="stat-change" :class="stat.isUp ? 'up' : 'down'">
          {{ stat.isUp ? '↑' : '↓' }} {{ stat.change }}
        </span>
      </div>
    </div>

    <!-- Two Columns -->
    <div class="row-two">
      <!-- Revenue -->
      <div class="ocean-card chart-card animate-in" style="animation-delay: 0.3s">
        <div class="card-head">
          <h3 class="card-title">Doanh thu</h3>
          <div class="tab-group">
            <button class="tab" :class="{ active: currentTab === 'week' }" @click="currentTab = 'week'">Tuần</button>
            <button class="tab" :class="{ active: currentTab === 'month' }" @click="currentTab = 'month'">Tháng</button>
          </div>
        </div>
        <div class="bar-chart">
          <div class="bar-col" v-for="bar in displayedRevenue" :key="bar.label">
            <div class="bar-track">
              <div class="bar-fill" :style="{ height: bar.h + '%' }">
                <span class="bar-tip">{{ bar.val }}</span>
              </div>
            </div>
            <span class="bar-lbl">{{ bar.label }}</span>
          </div>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="ocean-card chart-card animate-in" style="animation-delay: 0.35s">
        <div class="card-head">
          <h3 class="card-title">Đơn hàng gần đây</h3>
          <router-link to="/admin/order" class="link-all">Xem tất cả →</router-link>
        </div>
        <div class="order-list">
          <div class="order-row" v-for="o in orders" :key="o.id">
            <div class="order-avatar" :style="{ background: o.bg }">{{ o.init }}</div>
            <div class="order-info">
              <span class="order-name">{{ o.name }}</span>
              <span class="order-prod">{{ o.product }}</span>
            </div>
            <div class="order-right">
              <span class="order-amt">{{ o.amount }}</span>
              <span class="order-status" :class="'s-' + o.status">{{ o.statusText }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="animate-in" style="animation-delay: 0.4s">
      <h3 class="section-title">Thao tác nhanh</h3>
      <div class="actions-grid">
        <router-link to="/admin/product" class="action-item ocean-card">
          <div class="action-icon" style="background: #E63B6F">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </div>
          <span>Thêm sản phẩm</span>
        </router-link>
        <router-link to="/admin/order" class="action-item ocean-card">
          <div class="action-icon" style="background: #4caf50">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
          </div>
          <span>Xem đơn hàng</span>
        </router-link>
        <router-link to="/admin/chat" class="action-item ocean-card">
          <div class="action-icon" style="background: #26a69a">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <span>Tin nhắn</span>
        </router-link>
        <router-link to="/admin/report" class="action-item ocean-card">
          <div class="action-icon" style="background: #ffb300">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <span>Báo cáo</span>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../axios'; // Use configured axios instance

const stats = ref([
  {
    title: 'Tổng doanh thu', value: '...', change: '', isUp: true,
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
    iconBg: '#E63B6F',
  },
  {
    title: 'Tổng đơn hàng', value: '...', change: '', isUp: true,
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>',
    iconBg: '#26a69a',
  },
  {
    title: 'Sản phẩm', value: '...', change: '', isUp: true,
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>',
    iconBg: '#ffa726',
  },
  {
    title: 'Khách hàng', value: '...', change: '', isUp: false,
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
    iconBg: '#7e57c2',
  },
]);

const currentTab = ref('week');
const revenueWeek = ref([]);
const revenueMonth = ref([]);

const displayedRevenue = computed(() => {
    return currentTab.value === 'week' ? revenueWeek.value : revenueMonth.value;
});

const orders = ref([]);

onMounted(async () => {
    try {
        const response = await api.get('/admin/dashboard');
        if (response.data && response.data.status === 'success') {
            const data = response.data.data;
            
            // Cập nhật stats
            stats.value[0].value = data.stats.revenue;
            stats.value[1].value = data.stats.orders;
            stats.value[2].value = data.stats.products;
            stats.value[3].value = data.stats.customers;

            // Cập nhật biểu đồ doanh thu
            revenueWeek.value = data.revenue_chart;
            revenueMonth.value = data.revenue_chart_month;

            // Cập nhật đơn hàng gần đây
            orders.value = data.recent_orders;
        }
    } catch (error) {
        console.error('Lỗi khi lấy dữ liệu dashboard:', error);
    }
});
</script>

<style scoped>
.dashboard { font-family: var(--font-inter); }

/* Welcome */
.welcome-card {
  padding: 28px 30px;
  margin-bottom: 24px;
  background: linear-gradient(135deg, rgba(230, 59, 111, 0.05) 0%, rgba(79, 195, 247, 0.08) 100%);
  border: 1px solid rgba(230, 59, 111, 0.1);
}
.welcome-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main);
  margin-bottom: 6px;
}
.highlight { color: #E63B6F; }
.welcome-sub {
  font-size: 0.85rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
.stat-card {
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  transition: all 0.2s;
}
.stat-card:hover { 
  border-color: rgba(230, 59, 111, 0.25); 
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.08);
}
.stat-icon {
  width: 44px; height: 44px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: white; flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.stat-body { flex: 1; display: flex; flex-direction: column; }
.stat-label {
  font-size: 0.72rem; font-weight: 700;
  color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;
  margin-bottom: 2px;
}
.stat-value { font-size: 1.4rem; font-weight: 800; color: var(--text-main); }
.stat-change { font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
.up { color: var(--seafoam); }
.down { color: var(--coral); }

/* Two columns */
.row-two {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 16px;
  margin-bottom: 24px;
}
.chart-card { padding: 22px; }
.card-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
}
.card-title { font-size: 1rem; font-weight: 800; color: var(--text-main); }
.link-all {
  font-size: 0.8rem; font-weight: 600;
  color: #E63B6F; text-decoration: none;
}
.link-all:hover { color: var(--ocean-bright); text-decoration: underline; }

/* Tabs */
.tab-group { display: flex; gap: 2px; background: var(--ocean-deepest); border: 1px solid var(--border-color); border-radius: 6px; padding: 2px; }
.tab {
  padding: 5px 12px; border-radius: 4px; border: none;
  background: none; color: var(--text-muted); 
  font-family: var(--font-inter); font-size: 0.75rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.tab.active { background: #E63B6F; color: white; }
.tab:hover:not(.active) { color: var(--text-main); }

/* Bar chart */
.bar-chart {
  display: flex; align-items: flex-end; justify-content: space-around;
  height: 180px;
}
.bar-col { display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 1; }
.bar-track { height: 160px; display: flex; align-items: flex-end; width: 100%; justify-content: center; }
.bar-fill {
  width: 32px; border-radius: 6px 6px 2px 2px;
  background: #E63B6F;
  position: relative; transition: all 0.2s; cursor: pointer;
  min-height: 12px;
}
.bar-fill:hover { background: var(--ocean-bright); }
.bar-tip {
  position: absolute; top: -28px; left: 50%; transform: translateX(-50%) scale(0);
  background: var(--text-main); border-radius: 6px; padding: 4px 8px;
  font-size: 0.7rem; font-weight: 700; color: white;
  white-space: nowrap; transition: transform 0.15s; pointer-events: none;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.bar-fill:hover .bar-tip { transform: translateX(-50%) scale(1); }
.bar-lbl { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); }

/* Orders */
.order-list { display: flex; flex-direction: column; gap: 8px; }
.order-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px; border-radius: 8px;
  transition: all 0.2s; border: 1px solid transparent;
}
.order-row:hover { background: var(--hover-bg); border-color: rgba(230, 59, 111, 0.1); }
.order-avatar {
  width: 38px; height: 38px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
}
.order-info { flex: 1; display: flex; flex-direction: column; }
.order-name { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
.order-prod { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
.order-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.order-amt { font-size: 0.85rem; font-weight: 800; color: var(--text-main); }
.order-status {
  font-size: 0.65rem; font-weight: 700; padding: 3px 8px;
  border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px;
}
.s-done { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.s-pending { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.s-shipped { background: rgba(3, 169, 244, 0.15); color: #E63B6F; }

/* Quick Actions */
.section-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 14px; }
.actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.action-item {
  display: flex; flex-direction: column; align-items: center; gap: 14px;
  padding: 24px 16px; text-decoration: none; cursor: pointer;
  transition: all 0.2s;
}
.action-item:hover { 
  border-color: rgba(230, 59, 111, 0.3); 
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.08); 
}
.action-item span { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
.action-icon {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

/* Responsive */
@media (max-width: 1100px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .row-two { grid-template-columns: 1fr; }
  .actions-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .stats-grid, .actions-grid { grid-template-columns: 1fr; }
}
</style>