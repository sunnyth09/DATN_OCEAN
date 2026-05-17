<template>
  <div class="chart-card ocean-card">
    <div class="card-header">
      <h3 class="card-title">Trạng thái đơn hàng</h3>
    </div>
    <div class="chart-container align-center">
      <Doughnut v-if="hasData" :data="chartData" :options="chartOptions" />
      <div v-else class="empty-state">Không có dữ liệu trong khoảng thời gian này</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'vue-chartjs';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
  data: {
    type: Object,
    default: null
  }
});

const hasData = computed(() => {
  return props.data && props.data.labels && props.data.labels.length > 0;
});

const chartData = computed(() => {
  if (!props.data) return { labels: [], datasets: [] };
  // add styling to dataset
  const dynamicData = { ...props.data };
  if (dynamicData.datasets && dynamicData.datasets.length > 0) {
    dynamicData.datasets[0].borderWidth = 0;
    dynamicData.datasets[0].hoverOffset = 8;
  }
  return dynamicData;
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '70%',
  plugins: {
    legend: {
      position: 'right',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          family: "'Inter', sans-serif",
          size: 13,
          weight: '500'
        },
        color: '#475569'
      }
    },
    tooltip: {
      backgroundColor: 'rgba(255, 255, 255, 0.95)',
      titleColor: '#1e293b',
      bodyColor: '#475569',
      borderColor: 'rgba(230, 59, 111, 0.2)',
      borderWidth: 1,
      padding: 12,
      displayColors: true,
      usePointStyle: true,
    }
  }
};
</script>

<style scoped>
.chart-card {
  background: white;
  padding: 24px;
  display: flex;
  flex-direction: column;
}

.card-header {
  margin-bottom: 20px;
}

.card-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text-main);
}

.chart-container {
  flex: 1;
  min-height: 280px;
  position: relative;
  display: flex;
  justify-content: center;
}

.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--text-muted);
  font-weight: 600;
  font-size: 0.95rem;
  background: var(--bg-body);
  border-radius: 8px;
  width: 100%;
}
</style>
