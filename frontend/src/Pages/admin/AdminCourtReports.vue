<script setup>
import { ref, onMounted, computed } from 'vue';
import { useCourtBookingStore } from '@/stores/useCourtBookingStore';
import '@/assets/court-management.css';

const store = useCourtBookingStore();
const period = ref('month'); // day, week, month

onMounted(async () => {
    await fetchStats();
});

const fetchStats = async () => {
    await store.fetchCourtStats({ period: period.value });
};

const formatCurrency = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);

const kpis = computed(() => store.courtStats?.kpis || {});
const revenueByCourt = computed(() => store.courtStats?.revenue_by_court || []);
const topServices = computed(() => store.courtStats?.top_services || []);
const utilization = computed(() => store.courtStats?.utilization || []);
</script>

<template>
    <div class="booking-management-page">
        <!-- Page Header -->
        <div class="court-section-header">
            <div>
                <h2 class="court-section-title">
                    <i class="bi bi-graph-up-arrow"></i>
                    Tổng Quan & Báo Cáo
                </h2>
                <p class="court-section-subtitle">Thống kê doanh thu, hiệu suất sử dụng sân và dịch vụ</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select border-0 bg-light" style="width: 150px; border-radius: 8px; font-weight: 600;" v-model="period" @change="fetchStats">
                    <option value="day">Hôm nay</option>
                    <option value="week">Tuần này</option>
                    <option value="month">Tháng này</option>
                </select>
                <button class="court-action-btn court-action-btn--primary" @click="fetchStats">
                    <i class="bi bi-arrow-repeat"></i>
                </button>
            </div>
        </div>

        <div v-if="store.loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
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
            </div>

            <div class="row g-4">
                <!-- Hiệu suất sử dụng sân -->
                <div class="col-lg-8">
                    <div class="court-card h-100">
                        <h5 class="fw-bold mb-4">Hiệu suất sử dụng sân</h5>
                        <div v-if="utilization.length === 0" class="text-center text-muted py-4">Chưa có dữ liệu</div>
                        <div v-for="u in utilization" :key="u.court_id" class="mb-4">
                            <div class="d-flex justify-content-between align-items-end mb-1">
                                <span class="fw-semibold">{{ u.court_name }}</span>
                                <span class="text-muted" style="font-size: 0.85rem;">
                                    <span class="fw-bold" style="color: var(--court-primary);">{{ u.booked_hours }}h</span> / {{ u.total_hours }}h ({{ u.utilization_rate }}%)
                                </span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 10px; background-color: var(--court-section-bg);">
                                <div class="progress-bar" role="progressbar" 
                                     :style="{ width: u.utilization_rate + '%', backgroundColor: u.utilization_rate > 50 ? 'var(--court-primary)' : 'var(--court-playing)' }">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Dịch Vụ -->
                <div class="col-lg-4">
                    <div class="court-card h-100">
                        <h5 class="fw-bold mb-4">Dịch vụ bán chạy</h5>
                        <div v-if="topServices.length === 0" class="text-center text-muted py-4">Chưa có dữ liệu</div>
                        <ul class="list-unstyled mb-0">
                            <li v-for="(ts, index) in topServices" :key="ts.service_id" class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="fw-bold text-muted me-3" style="font-size: 1.2rem;">#{{ index + 1 }}</div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ ts.service?.name || ts.service?.service_name || 'Dịch vụ' }}</div>
                                    <small class="text-muted">Đã bán: {{ ts.total_quantity }}</small>
                                </div>
                                <div class="fw-bold text-end" style="color: var(--court-primary);">
                                    {{ formatCurrency(ts.total_revenue) }}
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Doanh thu theo sân -->
                <div class="col-12">
                    <div class="court-card">
                        <h5 class="fw-bold mb-4">Doanh thu theo sân</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Sân</th>
                                        <th class="text-center">Số Lượt Thuê</th>
                                        <th class="text-end pe-4">Tổng Doanh Thu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="revenueByCourt.length === 0">
                                        <td colspan="3" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                                    </tr>
                                    <tr v-for="rev in revenueByCourt" :key="rev.court_id">
                                        <td class="ps-3 fw-semibold">
                                            <i class="bi bi-building me-2 text-muted"></i>
                                            {{ rev.court?.name || rev.court?.court_name }}
                                        </td>
                                        <td class="text-center fw-bold">{{ rev.booking_count }}</td>
                                        <td class="text-end pe-4 fw-bold" style="color: var(--court-primary);">{{ formatCurrency(rev.revenue) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
