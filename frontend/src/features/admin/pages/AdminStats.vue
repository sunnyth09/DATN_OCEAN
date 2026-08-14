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
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" class="me-1">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="8" y1="13" x2="16" y2="13"></line>
            <line x1="8" y1="17" x2="16" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          Xuất Excel (Tháng trước)
        </button>
        <button class="btn btn-outline-ocean me-2" @click="handlePrintPdf">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" class="me-1">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          Xuất PDF
        </button>
        <button class="btn btn-ocean" @click="fetchData">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" class="me-1" :class="{ 'spin': loading }">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
          Làm mới
        </button>
      </div>
    </div>

    <!-- Filter -->
    <StatisticsFilter v-model="filters" @apply="fetchData" />

    <AdminTableSkeleton v-if="loading" :columns="6" :rows="5" />

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
          <RevenueReportTable :report="revenueReport" />
        </div>
        <div class="col-lg-6">
          <TopCustomersTable :customers="topCustomers" />
        </div>
      </div>

      <!-- Additional Report Table -->
      <div class="row mt-4">
        <div class="col-12">
          <TopProductsTable :products="topProducts" />
        </div>
      </div>

      <!-- Slow Moving / Dead Stock Alert Table -->
      <div class="row mt-4">
        <div class="col-12">
          <SlowMovingProductsTable 
            :products="slowMovingData.products" 
            :summary="slowMovingData.summary"
            @threshold-change="fetchSlowMovingData"
          />
        </div>
      </div>

      <!-- Staff Sales Table -->
      <div class="row mt-4 mb-5">
        <div class="col-12">
          <StaffSalesTable :sales="staffSales" @export="handleExportStaffSales" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import StatisticsFilter from './Statistics/StatisticsFilter.vue';
import StatisticsOverviewCards from './Statistics/StatisticsOverviewCards.vue';
import RevenueChart from './Statistics/RevenueChart.vue';
import OrderStatusChart from './Statistics/OrderStatusChart.vue';
import TopProductsTable from './Statistics/TopProductsTable.vue';
import TopCustomersTable from './Statistics/TopCustomersTable.vue';
import RevenueReportTable from './Statistics/RevenueReportTable.vue';
import StaffSalesTable from './Statistics/StaffSalesTable.vue';
import SlowMovingProductsTable from './Statistics/SlowMovingProductsTable.vue';

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
const staffSales = ref([]);
const slowMovingData = ref({ summary: {}, products: [] });
const slowMovingDays = ref(60);

const fetchSlowMovingData = async (days = 60) => {
  slowMovingDays.value = days;
  try {
    const res = await api.get('/admin/statistics/slow-moving-products', {
      params: { days_threshold: days }
    });
    slowMovingData.value = res.data.data || { summary: {}, products: [] };
  } catch (error) {
    console.error('Lỗi tải dữ liệu hàng tồn lâu:', error);
  }
};

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
      api.get('/admin/statistics/report', { params }),
      api.get('/admin/statistics/staff-sales', { params }),
      api.get('/admin/statistics/slow-moving-products', { params: { days_threshold: slowMovingDays.value } })
    ];

    const [
      overviewRes,
      revenueChartRes,
      orderStatusChartRes,
      topProductsRes,
      topCustomersRes,
      reportRes,
      staffSalesRes,
      slowMovingRes
    ] = await Promise.all(urls);

    overviewData.value = overviewRes.data.data;
    revenueChartData.value = revenueChartRes.data.data;
    orderStatusChartData.value = orderStatusChartRes.data.data;
    topProducts.value = topProductsRes.data.data;
    topCustomers.value = topCustomersRes.data.data;
    revenueReport.value = reportRes.data.data;
    staffSales.value = staffSalesRes.data.data;
    slowMovingData.value = slowMovingRes.data.data || { summary: {}, products: [] };

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
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: 'Lỗi',
      text: 'Không thể xuất file Excel. Vui lòng kiểm tra lại quyền hoặc dữ liệu.',
      showConfirmButton: false,
      timer: 3000
    });
  }
};

const handleExportStaffSales = async () => {
  try {
    const params = { ...filters.value };
    const response = await api.get('/admin/statistics/export-staff-sales', { params, responseType: 'blob' });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;

    const now = new Date();
    const dateStr = `${now.getFullYear()}_${String(now.getMonth() + 1).padStart(2, '0')}_${String(now.getDate()).padStart(2, '0')}`;

    link.setAttribute('download', `Doanh_Thu_Nhan_Vien_${dateStr}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.parentNode.removeChild(link);
  } catch (error) {
    console.error("Lỗi xuất Excel:", error);
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: 'Lỗi',
      text: 'Không thể xuất file Excel báo cáo nhân viên.',
      showConfirmButton: false,
      timer: 3000
    });
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
  color: var(--text-main);
}

.btn-ocean {
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 700;
  padding: 8px 18px;
  letter-spacing: 0.2px;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-ocean:hover {
  background: #d82f65;
  transform: translateY(-1.5px);
  box-shadow: 0 6px 15px rgba(230, 59, 111, 0.3);
}

.btn-outline-ocean {
  border: 1.5px solid var(--primary);
  color: var(--primary);
  background: transparent;
  border-radius: 10px;
  font-weight: 700;
  padding: 8px 18px;
  letter-spacing: 0.2px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-outline-ocean:hover {
  background: rgba(230, 59, 111, 0.08);
  border-color: var(--primary);
  color: var(--primary);
  transform: translateY(-1.5px);
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}
</style>

<style>
@media print {
  @page {
    margin: 10mm;
    size: landscape;
  }

  body * {
    visibility: hidden;
  }

  #printable-dashboard,
  #printable-dashboard * {
    visibility: visible !important;
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
  --ocean-blue: var(--primary);
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
