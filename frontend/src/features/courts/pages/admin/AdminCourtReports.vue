<script setup>
import { ref, onMounted, computed } from 'vue';
import { useCourtBookingStore } from '@/features/courts/stores/useCourtBookingStore';
import '@/features/courts/assets/court-management.css';

const store = useCourtBookingStore();
const period = ref('month'); // day, week, month

onMounted(async () => {
    await fetchStats();
});

const fetchStats = async () => {
    await store.fetchCourtStats({ period: period.value });
};

const formatCurrency = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
const formatCurrencyShort = (val) => {
    const n = Number(val || 0);
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'tr';
    if (n >= 1_000) return (n / 1_000).toFixed(0) + 'k';
    return n.toString();
};

const kpis = computed(() => store.courtStats?.kpis || {});
const revenueByCourt = computed(() => store.courtStats?.revenue_by_court || []);
const topServices = computed(() => store.courtStats?.top_services || []);
const utilization = computed(() => store.courtStats?.utilization || []);
const revenueByDay = computed(() => store.courtStats?.revenue_by_day || []);

// SVG Bar Chart
const chartWidth = 700;
const chartHeight = 200;
const chartPaddingX = 40;
const chartPaddingY = 20;

const chartData = computed(() => {
    const days = revenueByDay.value;
    if (!days.length) return { bars: [], labels: [], maxVal: 0 };
    const maxVal = Math.max(...days.map(d => Number(d.revenue || 0)), 1);
    const innerWidth  = chartWidth - chartPaddingX * 2;
    const innerHeight = chartHeight - chartPaddingY * 2;
    const barWidth    = Math.max(6, Math.min(30, innerWidth / days.length - 4));

    const bars = days.map((d, i) => {
        const x = chartPaddingX + (i / days.length) * innerWidth + (innerWidth / days.length - barWidth) / 2;
        const h = (Number(d.revenue) / maxVal) * innerHeight;
        const y = chartPaddingY + innerHeight - h;
        return { x, y, w: barWidth, h, revenue: d.revenue, date: d.date, count: d.count };
    });

    const labels = days.filter((_, i) => days.length <= 10 || i % Math.ceil(days.length / 10) === 0).map((d, i) => {
        const idx = days.indexOf(d);
        const x = chartPaddingX + ((idx + 0.5) / days.length) * innerWidth;
        const label = d.date ? d.date.slice(5) : '';
        return { x, label };
    });

    return { bars, labels, maxVal };
});
</script>

<template>
    <div class="booking-management-page">
        <!-- Page Header -->
        <div class="court-section-header">
            <div>
                <h2 class="court-section-title">
                    <i class="bi bi-graph-up-arrow"></i>
                    Tổng Quan &amp; Báo Cáo
                </h2>
                <p class="court-section-subtitle">Thống kê doanh thu, hiệu suất sử dụng sân và dịch vụ</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select border-0 bg-light" style="width: 150px; border-radius: 8px; font-weight: 600;" v-model="period" @change="fetchStats">
                    <option value="day">Hôm nay</option>
                    <option value="week">Tuần này</option>
                    <option value="month">Tháng này</option>
                </select>
                <button class="court-action-btn court-action-btn--primary" @click="fetchStats" :disabled="store.loading">
                    <i :class="store.loading ? 'bi bi-arrow-repeat spin' : 'bi bi-arrow-repeat'"></i>
                </button>
            </div>
        </div>

        <div v-if="store.loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Đang tải dữ liệu thống kê...</p>
        </div>

        <template v-else-if="store.courtStats">
            <!-- KPI Cards -->
            <div class="court-stats-bar mb-4">
                <div class="court-stat-card" style="border-bottom: 4px solid var(--court-primary);">
                    <div class="court-stat-card__value" style="color: var(--court-primary);">{{ formatCurrency(kpis.total_revenue) }}</div>
                    <div class="court-stat-card__label">Tổng Doanh Thu</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--text-color);">{{ kpis.total_bookings }}</div>
                    <div class="court-stat-card__label">Tổng Lượt Đặt</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--court-available);">{{ kpis.completed_bookings }}</div>
                    <div class="court-stat-card__label">Hoàn Thành</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--court-closed);">{{ kpis.cancelled_bookings }}</div>
                    <div class="court-stat-card__label">Đã Hủy</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--court-playing);">{{ kpis.completion_rate }}%</div>
                    <div class="court-stat-card__label">Tỉ lệ hoàn thành</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: #8b5cf6;">{{ formatCurrency(kpis.avg_revenue_per_booking) }}</div>
                    <div class="court-stat-card__label">TB/Booking</div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="court-card mb-4" v-if="revenueByDay.length > 0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-bar-chart-fill me-2" style="color: var(--court-primary);"></i>
                        Doanh Thu Theo Ngày
                    </h5>
                    <span class="badge bg-light text-dark border">{{ revenueByDay.length }} ngày</span>
                </div>

                <div style="overflow-x: auto;">
                    <svg :width="chartWidth" :height="chartHeight + 30" style="display: block; margin: 0 auto; min-width: 320px;">
                        <!-- Grid lines -->
                        <line v-for="n in 4" :key="n"
                            :x1="chartPaddingX" :y1="chartPaddingY + ((chartHeight - chartPaddingY*2) * (1 - n/4))"
                            :x2="chartWidth - chartPaddingX" :y2="chartPaddingY + ((chartHeight - chartPaddingY*2) * (1 - n/4))"
                            stroke="#e5e7eb" stroke-dasharray="4"/>
                        <!-- Y axis labels -->
                        <text v-for="n in 4" :key="'y'+n"
                            :x="chartPaddingX - 4"
                            :y="chartPaddingY + ((chartHeight - chartPaddingY*2) * (1 - n/4)) + 4"
                            text-anchor="end" font-size="10" fill="#9ca3af">
                            {{ formatCurrencyShort(chartData.maxVal * n / 4) }}
                        </text>
                        <!-- Bars -->
                        <g v-for="bar in chartData.bars" :key="bar.date">
                            <rect
                                :x="bar.x" :y="bar.y"
                                :width="bar.w" :height="bar.h"
                                rx="4"
                                fill="url(#barGrad)"
                                style="transition: opacity 0.2s;"
                                opacity="0.85">
                                <title>{{ bar.date }}: {{ formatCurrency(bar.revenue) }} ({{ bar.count }} đặt)</title>
                            </rect>
                        </g>
                        <!-- X axis labels -->
                        <text v-for="lbl in chartData.labels" :key="lbl.label"
                            :x="lbl.x"
                            :y="chartHeight + 14"
                            text-anchor="middle" font-size="10" fill="#9ca3af">
                            {{ lbl.label }}
                        </text>
                        <!-- Gradient -->
                        <defs>
                            <linearGradient id="barGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1"/>
                                <stop offset="100%" stop-color="#a78bfa"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            </div>

            <div class="row g-4">
                <!-- Hiệu suất sử dụng sân -->
                <div class="col-lg-8">
                    <div class="court-card h-100">
                        <h5 class="fw-bold mb-4">
                            <i class="bi bi-speedometer2 me-2" style="color: #f59e0b;"></i>
                            Hiệu suất sử dụng sân
                        </h5>
                        <div v-if="utilization.length === 0" class="text-center text-muted py-4">Chưa có dữ liệu</div>
                        <div v-for="u in utilization" :key="u.court_id" class="mb-4">
                            <div class="d-flex justify-content-between align-items-end mb-1">
                                <span class="fw-semibold">{{ u.court_name }}</span>
                                <span class="text-muted" style="font-size: 0.85rem;">
                                    <span class="fw-bold" style="color: var(--court-primary);">{{ u.booked_hours }}h</span>
                                    / {{ u.total_hours }}h
                                    <span :class="u.utilization_rate >= 70 ? 'text-success' : u.utilization_rate >= 40 ? 'text-warning' : 'text-danger'"
                                          class="ms-1 fw-bold">({{ u.utilization_rate }}%)</span>
                                </span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 10px; background-color: var(--court-section-bg);">
                                <div class="progress-bar" role="progressbar"
                                     :style="{
                                         width: u.utilization_rate + '%',
                                         backgroundColor: u.utilization_rate >= 70 ? '#10b981' : u.utilization_rate >= 40 ? '#f59e0b' : '#ef4444',
                                         borderRadius: '10px',
                                         transition: 'width 0.8s ease'
                                     }">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Dịch Vụ -->
                <div class="col-lg-4">
                    <div class="court-card h-100">
                        <h5 class="fw-bold mb-4">
                            <i class="bi bi-trophy-fill me-2" style="color: #f59e0b;"></i>
                            Dịch vụ bán chạy
                        </h5>
                        <div v-if="topServices.length === 0" class="text-center text-muted py-4">Chưa có dữ liệu</div>
                        <ul class="list-unstyled mb-0">
                            <li v-for="(ts, index) in topServices" :key="ts.service_id"
                                class="d-flex align-items-center mb-3 pb-3"
                                :class="index < topServices.length - 1 ? 'border-bottom' : ''">
                                <div class="fw-bold me-3 rounded-circle d-flex align-items-center justify-content-center"
                                     :style="{
                                         width: '32px', height: '32px', fontSize: '0.85rem',
                                         background: index === 0 ? '#fbbf24' : index === 1 ? '#9ca3af' : index === 2 ? '#b45309' : '#e5e7eb',
                                         color: index < 3 ? '#fff' : '#6b7280'
                                     }">
                                    #{{ index + 1 }}
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold" style="font-size: 0.9rem;">{{ ts.service?.service_name || ts.service?.name || 'Dịch vụ' }}</div>
                                    <small class="text-muted">Đã bán: {{ ts.total_quantity }}</small>
                                </div>
                                <div class="fw-bold text-end" style="color: var(--court-primary); font-size: 0.85rem;">
                                    {{ formatCurrency(ts.total_revenue) }}
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Doanh thu theo sân -->
                <div class="col-12">
                    <div class="court-card">
                        <h5 class="fw-bold mb-4">
                            <i class="bi bi-building me-2" style="color: var(--court-primary);"></i>
                            Doanh thu theo sân
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Sân</th>
                                        <th class="text-center">Số Lượt Thuê</th>
                                        <th class="text-center">Doanh Thu</th>
                                        <th class="text-end pe-4">Tỉ Trọng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="revenueByCourt.length === 0">
                                        <td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                                    </tr>
                                    <tr v-for="rev in revenueByCourt" :key="rev.court_id">
                                        <td class="ps-3 fw-semibold">
                                            <i class="bi bi-building me-2 text-muted"></i>
                                            {{ rev.court?.court_name || rev.court?.name || `Sân #${rev.court_id}` }}
                                        </td>
                                        <td class="text-center fw-bold">{{ rev.booking_count }}</td>
                                        <td class="text-center fw-bold" style="color: var(--court-primary);">
                                            {{ formatCurrency(rev.revenue) }}
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px; max-width: 80px;">
                                                    <div class="progress-bar"
                                                         :style="{ width: kpis.total_revenue > 0 ? ((rev.revenue / kpis.total_revenue) * 100).toFixed(0) + '%' : '0%', backgroundColor: 'var(--court-primary)' }">
                                                    </div>
                                                </div>
                                                <span class="text-muted" style="font-size: 0.82rem; min-width: 38px;">
                                                    {{ kpis.total_revenue > 0 ? ((rev.revenue / kpis.total_revenue) * 100).toFixed(1) : 0 }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="text-center py-5 text-muted">
            <i class="bi bi-graph-up-arrow" style="font-size: 3rem; opacity: 0.3;"></i>
            <p class="mt-3">Không có dữ liệu thống kê</p>
        </div>
    </div>
</template>

<style scoped>
.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
