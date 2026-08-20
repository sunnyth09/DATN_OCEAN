<script setup>
import { ref, onMounted } from 'vue';
import { useCourtBookingStore } from '@/features/courts/stores/useCourtBookingStore';
import { useRouter } from 'vue-router';
import '@/features/courts/assets/court-management.css';

const store = useCourtBookingStore();
const router = useRouter();

const toLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const searchParams = ref({
    date: toLocalDateString(),
    type: ''
});

onMounted(async () => {
    await fetchCourts();
});

const fetchCourts = async () => {
    await store.fetchCourts(searchParams.value);
};

const goToDetail = (id) => {
    router.push({ name: 'court-detail', params: { id } });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

const getTypeLabel = (type) => {
    const map = { standard: 'Tiêu chuẩn', vip: 'VIP', outdoor: 'Ngoài trời', indoor: 'Trong nhà' };
    return map[type] || type || 'Tiêu chuẩn';
};

const getTypeIcon = (type) => {
    const map = { standard: 'bi-layers', vip: 'bi-star-fill', outdoor: 'bi-sun', indoor: 'bi-house-door' };
    return map[type] || 'bi-layers';
};

const DEFAULT_COURT_IMAGE = 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?q=80&w=800&auto=format&fit=crop';

const handleImgError = (event) => {
    event.target.src = DEFAULT_COURT_IMAGE;
};

const clearFilters = () => {
    searchParams.value = {
        date: toLocalDateString(),
        type: ''
    };
    fetchCourts();
};
</script>

<template>
    <div class="container py-4">
        <!-- Hero Banner -->
        <div class="court-hero">
            <div style="position: relative; z-index: 1;">
                <h1 class="court-hero__title">
                    <i class="bi bi-trophy me-2"></i>
                    Hệ Thống Sân Cầu Lông
                </h1>
                <p class="court-hero__subtitle">
                    Lựa chọn sân phù hợp và đặt lịch ngay hôm nay. Hệ thống 7 sân đạt chuẩn thi đấu.
                </p>
                <!-- Legend -->
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <span class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill"
                        style="background: rgba(255,255,255,0.15); font-size: 0.8rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #4ade80;"></span> Trống
                    </span>
                    <span class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill"
                        style="background: rgba(255,255,255,0.15); font-size: 0.8rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #f87171;"></span> Đang
                        chơi
                    </span>
                    <span class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill"
                        style="background: rgba(255,255,255,0.15); font-size: 0.8rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #fbbf24;"></span> Bảo trì
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar Filter -->
            <div class="col-lg-3 mb-4">
                <div class="filter-sidebar sticky-top" style="top: 100px; z-index: 10;">
                    <div class="filter-sidebar__inner">
                        <h5 class="filter-sidebar__title">
                            <i class="bi bi-funnel me-2"></i>Bộ Lọc Sân
                        </h5>

                        <div class="mb-4">
                            <label class="filter-sidebar__label">Ngày đặt</label>
                            <input type="date" class="form-control filter-sidebar__input" v-model="searchParams.date"
                                @change="fetchCourts">
                        </div>

                        <div class="mb-4">
                            <label class="filter-sidebar__label">Loại sân</label>
                            <select class="form-select filter-sidebar__input" v-model="searchParams.type"
                                @change="fetchCourts">
                                <option value="">Tất cả loại sân</option>
                                <option value="standard">Tiêu chuẩn</option>
                                <option value="vip">VIP</option>
                                <option value="outdoor">Ngoài trời</option>
                                <option value="indoor">Trong nhà</option>
                            </select>
                        </div>

                        <button class="btn w-100 py-3 fw-bold rounded-3 filter-sidebar__apply" @click="fetchCourts">
                            <i class="bi bi-search me-2"></i>Áp Dụng Lọc
                        </button>
                        <button class="btn btn-link w-100 mt-2 text-muted" style="font-size: 0.85rem;"
                            @click="clearFilters">
                            <i class="bi bi-x-circle me-1"></i>Xóa bộ lọc
                        </button>
                    </div>
                </div>
            </div>

            <!-- Court Grid -->
            <div class="col-lg-9">
                <!-- Loading -->
                <div v-if="store.loading" class="row g-4">
                    <div v-for="n in 6" :key="n" class="col-md-6 col-xl-4">
                        <div class="card border-0 rounded-4 overflow-hidden"
                            style="box-shadow: var(--court-ambient-shadow);">
                            <div class="court-skeleton" style="height: 180px;"></div>
                            <div class="p-4">
                                <div class="court-skeleton mb-2" style="height: 20px; width: 70%;"></div>
                                <div class="court-skeleton mb-3" style="height: 14px; width: 40%;"></div>
                                <div class="court-skeleton" style="height: 40px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="store.courts.length === 0" class="court-empty-state">
                    <div class="court-empty-state__icon"><i class="bi bi-calendar-x"></i></div>
                    <div class="court-empty-state__title">Không tìm thấy sân nào phù hợp</div>
                    <div class="court-empty-state__text">Vui lòng thử thay đổi điều kiện lọc hoặc chọn ngày khác.</div>
                    <button class="btn btn-outline-primary rounded-pill px-4 mt-3" @click="clearFilters">Xóa bộ
                        lọc</button>
                </div>

                <!-- Court Cards -->
                <div v-else class="row g-4">
                    <div v-for="court in store.courts" :key="court.court_id" class="col-md-6 col-xl-4">
                        <div class="client-court-card card h-100" @click="goToDetail(court.court_id)">
                            <!-- Image -->
                            <div class="client-court-card__img-wrap">
                                <img :src="court.image_url || DEFAULT_COURT_IMAGE"
                                    class="client-court-card__img" :alt="court.court_name"
                                    @error="handleImgError">
                                <!-- Status Badge Overlay -->
                                <div
                                    class="position-absolute top-0 start-0 w-100 p-3 d-flex justify-content-between align-items-start">
                                    <span v-if="court.status === 'active'" class="status-badge status-badge--active"
                                        style="background: rgba(25, 135, 84, 0.9); color: #fff; border: none;">
                                        <span class="pulse-dot pulse-dot--active" style="background: #fff;"></span> Đang
                                        trống
                                    </span>
                                    <span v-else-if="court.status === 'maintenance'"
                                        class="status-badge status-badge--maintenance"
                                        style="background: rgba(253, 126, 20, 0.9); color: #fff; border: none;">
                                        Bảo trì
                                    </span>
                                    <span v-else class="status-badge status-badge--closed"
                                        style="background: rgba(220, 53, 69, 0.9); color: #fff; border: none;">
                                        Đóng cửa
                                    </span>

                                    <!-- Type Badge -->
                                    <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill"
                                        style="background: rgba(0,0,0,0.5); color: #fff; font-size: 0.7rem; font-weight: 600; backdrop-filter: blur(4px);">
                                        <i :class="getTypeIcon(court.type)" style="font-size: 0.6rem;"></i>
                                        {{ getTypeLabel(court.type) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body p-4 d-flex flex-column">
                                <h5 class="fw-bold mb-2" style="font-size: 1.05rem;">{{ court.court_name }}</h5>
                                <p v-if="court.description" class="text-muted mb-0 flex-grow-1"
                                    style="font-size: 0.85rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    {{ court.description }}
                                </p>

                                <div class="mt-auto pt-3 d-flex justify-content-between align-items-center"
                                    style="border-top: 1px solid rgba(0,0,0,0.06);">
                                    <div>
                                        <span
                                            style="color: var(--court-primary); font-weight: 700; font-size: 1rem;">Linh
                                            hoạt</span>
                                        <span class="text-muted" style="font-size: 0.8rem;"> / giờ</span>
                                    </div>
                                    <button class="btn btn-sm px-3 py-2 fw-semibold rounded-pill"
                                        style="background: var(--court-primary); color: #fff; font-size: 0.8rem; border: none;"
                                        @click.stop="goToDetail(court.court_id)">
                                        Đặt Sân <i class="bi bi-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Filter Sidebar */
.filter-sidebar__inner {
    background: var(--court-card-bg, #fff);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--court-ambient-shadow);
    border: 1px solid rgba(0, 0, 0, 0.04);
}

.filter-sidebar__title {
    font-weight: 800;
    font-size: 1rem;
    margin-bottom: 20px;
}

.filter-sidebar__label {
    font-weight: 600;
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 6px;
    display: block;
}

.filter-sidebar__input {
    border-radius: 10px;
    padding: 10px 14px;
    border-color: rgba(0, 0, 0, 0.08);
    background: #f8f9fb;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.filter-sidebar__input:focus {
    border-color: var(--court-primary);
    box-shadow: 0 0 0 3px var(--court-primary-soft, rgba(230, 59, 111, 0.08));
    background: var(--card-bg);
}

.filter-sidebar__apply {
    background: linear-gradient(135deg, var(--court-primary, var(--primary)), #c02858);
    color: #fff;
    border: none;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
}

.filter-sidebar__apply:hover {
    box-shadow: 0 6px 20px rgba(230, 59, 111, 0.35);
    transform: translateY(-1px);
    color: #fff;
}
</style>

