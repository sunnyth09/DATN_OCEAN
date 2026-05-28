<template>
  <div class="dashboard container-fluid px-4 py-4">
    <!-- Header -->
    <div class="dashboard-header d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="page-title m-0">Thống kê kinh doanh</h1>
        <p class="text-muted m-0">Báo cáo chi tiết hoạt động của hệ thống</p>
      </div>
      <div class="actions">
        <!-- Optional Actions like PDF, Excel -->
        <button class="btn btn-outline-success me-2" @click="handleExportExcel" title="Xuất Doanh Thu Tháng Vừa Rồi">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          Xuất Excel (Tháng trước)
        </button>
        <button class="btn btn-outline-ocean me-2" @click="handlePrintPdf">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          Xuất PDF
        </button>
        <button class="btn btn-ocean" @click="fetchData">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" :class="{ 'spin': loading }"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          Làm mới
        </button>
      </div>
    </div>

    <!-- Filter -->
    <StatisticsFilter v-model="filters" @apply="fetchData" />

    <div v-if="loading" class="loading-state text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-3 text-muted">Đang tải dữ liệu báo cáo...</p>
    </div>

    <div v-else class="dashboard-content" id="printable-dashboard">
      <!-- 8 Cards Overview -->
      <StatisticsOverviewCards :data="overviewData" />

      <!-- Charts Section -->
      <div class="row g-4 mb-4">
        <div class="col-lg-8">
          <RevenueChart :data="revenueChartData" />
        </div>
        <div class="col-lg-4">
          <OrderStatusChart :data="orderStatusChartData" />
        </div>
      </div>

      <!-- Detail Tables Section -->
      <div class="row g-4">
        <div class="col-lg-6">
          <TopProductsTable :products="topProducts" />
        </div>
        <div class="col-lg-6">
          <TopCustomersTable :customers="topCustomers" />
        </div>
      </div>

      <!-- Additional Report Table -->
      <div class="row mt-4">
        <div class="col-12">
          <RevenueReportTable :report="revenueReport" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../axios';
import StatisticsFilter from './Statistics/StatisticsFilter.vue';
import StatisticsOverviewCards from './Statistics/StatisticsOverviewCards.vue';
import RevenueChart from './Statistics/RevenueChart.vue';
import OrderStatusChart from './Statistics/OrderStatusChart.vue';
import TopProductsTable from './Statistics/TopProductsTable.vue';
import TopCustomersTable from './Statistics/TopCustomersTable.vue';
import RevenueReportTable from './Statistics/RevenueReportTable.vue';

const filters = ref({
  preset: '30days',
  start_date: '',
  end_date: ''
});

const loading = ref(false);

const overviewData = ref({});
const revenueChartData = ref({});
const orderStatusChartData = ref({});
const topProducts = ref([]);
const topCustomers = ref([]);
const revenueReport = ref([]);

const fetchData = async () => {
  loading.value = true;
  const params = { ...filters.value };
  
  try {
    const urls = [
      api.get('/admin/statistics/overview', { params }),
      api.get('/admin/statistics/revenue', { params }),
      api.get('/admin/statistics/orders-status', { params }),
      api.get('/admin/statistics/top-products', { params }),
      api.get('/admin/statistics/top-customers', { params }),
      api.get('/admin/statistics/report', { params })
    ];

    const [
      overviewRes,
      revenueChartRes,
      orderStatusChartRes,
      topProductsRes,
      topCustomersRes,
      reportRes
    ] = await Promise.all(urls);

    overviewData.value = overviewRes.data.data;
    revenueChartData.value = revenueChartRes.data.data;
    orderStatusChartData.value = orderStatusChartRes.data.data;
    topProducts.value = topProductsRes.data.data;
    topCustomers.value = topCustomersRes.data.data;
    revenueReport.value = reportRes.data.data;
    
  } catch (error) {
    console.error('Lỗi tải dữ liệu thống kê:', error);
  } finally {
    loading.value = false;
  }
};

const handlePrintPdf = () => {
  window.print();
};

const handleExportExcel = async () => {
  try {
    const response = await api.get('/admin/statistics/export-revenue-last-month', { responseType: 'blob' });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    
    // Tạo tên file an toàn với định dạng tháng trước
    const lastMonth = new Date();
    lastMonth.setMonth(lastMonth.getMonth() - 1);
    const mm = String(lastMonth.getMonth() + 1).padStart(2, '0');
    const yyyy = lastMonth.getFullYear();
    
    link.setAttribute('download', `Doanh_Thu_Thang_${mm}_${yyyy}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.parentNode.removeChild(link);
  } catch (error) {
    console.error("Lỗi xuất Excel:", error);
    alert("Không thể xuất file Excel. Vui lòng kiểm tra lại quyền hoặc dữ liệu.");
  }
};

onMounted(() => {
  fetchData();
});
</script>

<style scoped>
.dashboard {
  font-family: var(--font-inter);
  background-color: var(--bg-body);
  min-height: 100vh;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--ocean-deepest);
}

.btn-ocean {
  background: #E63B6F;
  color: white;
  border: none;
}
.btn-ocean:hover {
  background: var(--ocean-bright);
}

.btn-outline-ocean {
  border: 1px solid #E63B6F;
  color: #E63B6F;
  background: transparent;
}
.btn-outline-ocean:hover {
  background: rgba(230, 59, 111, 0.1);
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

@media print {
  @page { margin: 10mm; size: landscape; }
  body * {
    visibility: hidden;
  }
  #printable-dashboard, #printable-dashboard * {
    visibility: visible;
  }
  #printable-dashboard {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>
<style scoped>
/* Scoped variables for dashboard to prevent bleeding into other admin pages */
.dashboard {
  --ocean-blue: #E63B6F;
  --ocean-bright: #4fc3f7;
  --ocean-deepest: #01579b;
  --seafoam: #26a69a;
  --coral: #ef5350;
  --amber: #ffa726;
  --text-main: #1e293b;
  --text-muted: #64748b;
  --border-color: #e2e8f0;
  --bg-body: transparent;
  --hover-bg: #f1f5f9;
}

:global(html.dark) .dashboard {
  --text-main: #f0f1f2;
  --text-muted: #b7cbcf;
  --border-color: #2e3132;
  --hover-bg: #2a1520;
}

:deep(.ocean-card) {
  border-radius: 16px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
  background: var(--card-bg);
}
</style>
