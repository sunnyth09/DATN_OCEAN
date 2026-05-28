<template>
  <div class="chart-card ocean-card">
    <div class="card-header">
      <h3 class="card-title">Khái quát doanh thu</h3>
    </div>
    <div class="chart-container">
      <Line v-if="hasData" :data="chartData" :options="chartOptions" />
      <div v-else class="empty-state">Không có dữ liệu trong khoảng thời gian này</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

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
  return props.data;
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: 'rgba(22, 24, 25, 0.95)',
      titleColor: '#f0f1f2',
      bodyColor: '#b7cbcf',
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1,
      padding: 12,
      displayColors: false,
      callbacks: {
        label: function(context) {
          let label = context.dataset.label || '';
          if (label) {
            label += ': ';
          }
          if (context.parsed.y !== null) {
            label += new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.parsed.y);
          }
          return label;
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(148, 163, 184, 0.1)',
        drawBorder: false,
      },
      ticks: {
        color: '#94a3b8',
        callback: function(value) {
          if (value === 0) return '0';
          return new Intl.NumberFormat('vi-VN', { notation: 'compact', compactDisplay: 'short' }).format(value);
        }
      }
    },
    x: {
      grid: {
        display: false,
        drawBorder: false,
      },
      ticks: {
        color: '#94a3b8',
        maxRotation: 45,
        minRotation: 0
      }
    }
  },
  elements: {
    line: {
      tension: 0.4
    },
    point: {
      radius: 0,
      hitRadius: 10,
      hoverRadius: 6,
      backgroundColor: '#E63B6F',
      borderWidth: 2,
      borderColor: '#fff'
    }
  }
};
</script>

<style scoped>
.chart-card {
  background: var(--card-bg);
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
  min-height: 320px;
  position: relative;
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
}
</style>
