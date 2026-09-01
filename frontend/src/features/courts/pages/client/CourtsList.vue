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

const goToDetail = (court) => {
    router.push({ name: 'court-detail', params: { slug: court.slug || court.court_id } });
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
    <div class="container py-3 py-md-4">
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
                <div class="d-flex flex-wrap gap-2 mt-2 mt-md-3">
                    <span class="d-inline-flex align-items-center gap-1 px-2 px-md-3 py-1 rounded-pill"
                        style="background: rgba(255,255,255,0.15); font-size: 0.8rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #4ade80;"></span> Trống
                    </span>
                    <span class="d-inline-flex align-items-center gap-1 px-2 px-md-3 py-1 rounded-pill"
                        style="background: rgba(255,255,255,0.15); font-size: 0.8rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #f87171;"></span> Đang chơi
                    </span>
                    <span class="d-inline-flex align-items-center gap-1 px-2 px-md-3 py-1 rounded-pill"
                        style="background: rgba(255,255,255,0.15); font-size: 0.8rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #fbbf24;"></span> Bảo trì
                    </span>
                </div>
            </div>
        </div>

        <div class="row g-2 g-md-3 g-lg-4">
            <!-- Sidebar Filter -->
            <div class="col-lg-3 mb-2 mb-lg-4">
                <div class="filter-sidebar sticky-top">
                    <div class="filter-sidebar__inner">
                        <h5 class="filter-sidebar__title">
                            <i class="bi bi-funnel me-2"></i>Bộ Lọc Sân
                        </h5>

                        <div class="filter-controls-grid">
                            <div class="filter-group">
                                <label class="filter-sidebar__label">Ngày đặt</label>
                                <input type="date" class="form-control filter-sidebar__input" v-model="searchParams.date"
                                    @change="fetchCourts">
                            </div>

                            <div class="filter-group">
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
                        </div>

                        <div class="filter-actions-wrap">
                            <button class="btn filter-sidebar__apply" @click="fetchCourts">
                                <i class="bi bi-search me-2"></i>Áp Dụng Lọc
                            </button>
                            <button class="btn btn-link btn-clear-filter" @click="clearFilters">
                                <i class="bi bi-x-circle me-1"></i>Xóa bộ lọc
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Court Grid -->
            <div class="col-lg-9">
                <!-- Loading -->
                <div v-if="store.loading" class="row g-2 g-md-3 g-xl-4">
                    <div v-for="n in 6" :key="n" class="col-6 col-md-6 col-xl-4">
                        <div class="card border-0 rounded-4 overflow-hidden"
                            style="box-shadow: var(--court-ambient-shadow);">
                            <div class="court-skeleton" style="height: 120px;"></div>
                            <div class="p-3">
                                <div class="court-skeleton mb-2" style="height: 16px; width: 70%;"></div>
                                <div class="court-skeleton mb-3" style="height: 12px; width: 40%;"></div>
                                <div class="court-skeleton" style="height: 30px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="store.courts.length === 0" class="court-empty-state">
                    <div class="court-empty-state__icon"><i class="bi bi-calendar-x"></i></div>
                    <div class="court-empty-state__title">Không tìm thấy sân nào phù hợp</div>
                    <div class="court-empty-state__text">Vui lòng thử thay đổi điều kiện lọc hoặc chọn ngày khác.</div>
                    <button class="btn btn-outline-primary rounded-pill px-4 mt-3" @click="clearFilters">Xóa bộ lọc</button>
                </div>

                <!-- Court Cards -->
                <div v-else class="row g-2 g-md-3 g-xl-4">
                    <div v-for="court in store.courts" :key="court.court_id" class="col-6 col-md-6 col-xl-4">
                        <div class="client-court-card card h-100" @click="goToDetail(court)">
                            <!-- Image -->
                            <div class="client-court-card__img-wrap">
                                <img :src="court.image_url || DEFAULT_COURT_IMAGE"
                                    class="client-court-card__img" :alt="court.court_name"
                                    @error="handleImgError">
                                <!-- Status Badge Overlay -->
                                <div class="card-badges-overlay">
                                    <span v-if="court.status === 'active'" class="status-badge status-badge--active status-pill">
                                        <span class="pulse-dot pulse-dot--active"></span> Trống
                                    </span>
                                    <span v-else-if="court.status === 'maintenance'" class="status-badge status-badge--maintenance status-pill">
                                        Bảo trì
                                    </span>
                                    <span v-else class="status-badge status-badge--closed status-pill">
                                        Đóng cửa
                                    </span>

                                    <!-- Type Badge -->
                                    <span class="type-pill">
                                        <i :class="getTypeIcon(court.type)"></i>
                                        {{ getTypeLabel(court.type) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body client-court-card__body d-flex flex-column">
                                <h5 class="court-card-name">{{ court.court_name }}</h5>
                                <p v-if="court.description" class="court-card-desc flex-grow-1">
                                    {{ court.description }}
                                </p>

                                <div class="court-card-footer mt-auto">
                                    <div class="court-card-price">
                                        <span class="price-val">Linh hoạt</span>
                                        <span class="price-unit"> / giờ</span>
                                    </div>
                                    <button class="btn btn-court-book" @click.stop="goToDetail(court)">
                                        <span>Đặt Sân</span>
                                        <i class="bi bi-arrow-right"></i>
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
.filter-sidebar {
    top: 100px;
    z-index: 10;
}

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

.filter-group {
    margin-bottom: 16px;
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
    width: 100%;
    padding: 12px;
    font-weight: 700;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-align: center;
}

.filter-sidebar__apply:hover {
    box-shadow: 0 6px 20px rgba(230, 59, 111, 0.35);
    transform: translateY(-1px);
    color: #fff;
}

.btn-clear-filter {
    font-size: 0.85rem;
    color: #6c757d;
    text-decoration: none;
    width: 100%;
    margin-top: 8px;
}

.btn-clear-filter:hover {
    color: var(--court-primary);
}

/* Card Styling */
.client-court-card {
    border-radius: 16px;
    border: none;
    overflow: hidden;
    box-shadow: var(--court-ambient-shadow);
    transition: all 0.25s ease;
    cursor: pointer;
    background: var(--court-card-bg, #fff);
}

.client-court-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.client-court-card__img-wrap {
    overflow: hidden;
    position: relative;
}

.client-court-card__img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}

.client-court-card:hover .client-court-card__img {
    transform: scale(1.05);
}

.card-badges-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 4px;
}

.status-pill {
    background: rgba(25, 135, 84, 0.9) !important;
    color: #fff !important;
    border: none !important;
    font-size: 0.72rem;
    padding: 3px 8px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}

.status-badge--maintenance.status-pill {
    background: rgba(253, 126, 20, 0.9) !important;
}

.status-badge--closed.status-pill {
    background: rgba(220, 53, 69, 0.9) !important;
}

.status-pill .pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #fff;
}

.type-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 600;
    backdrop-filter: blur(4px);
}

.client-court-card__body {
    padding: 16px;
}

.court-card-name {
    font-weight: 700;
    font-size: 1rem;
    color: #102a43;
    margin-bottom: 6px;
}

.court-card-desc {
    font-size: 0.82rem;
    color: #627d98;
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.35;
}

/* Desktop Footer Layout: Price on Left, Pill Button on Right */
.court-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    gap: 6px;
}

.court-card-price .price-val {
    color: #0f172a;
    font-weight: 800;
    font-size: 0.92rem;
}

.court-card-price .price-unit {
    color: #829ab1;
    font-size: 0.75rem;
}

.btn-court-book {
    background: linear-gradient(135deg, var(--court-primary, #E63B6F), #c02858);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 999px;
    padding: 6px 14px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    box-shadow: 0 3px 10px rgba(230, 59, 111, 0.25);
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-court-book:hover {
    box-shadow: 0 5px 15px rgba(230, 59, 111, 0.4);
    transform: translateY(-1px);
    color: #fff;
}

.btn-court-book:active {
    transform: scale(0.96);
}

/* ─── Mobile Optimizations (Max 768px ONLY) ─── */
@media (max-width: 768px) {
    .container {
        padding-top: 14px !important;
        padding-bottom: 85px !important;
    }

    /* Hero Banner Mobile */
    .court-hero {
        padding: 16px 18px !important;
        border-radius: 14px !important;
        margin-bottom: 14px !important;
    }

    .court-hero__title {
        font-size: 1.2rem !important;
        margin-bottom: 4px !important;
    }

    .court-hero__subtitle {
        font-size: 0.82rem !important;
        margin-bottom: 8px !important;
        line-height: 1.35;
    }

    .court-hero .gap-2 {
        gap: 6px !important;
        margin-top: 6px !important;
    }

    .court-hero .rounded-pill {
        font-size: 0.72rem !important;
        padding: 3px 10px !important;
    }

    /* Filter Sidebar Mobile */
    .filter-sidebar {
        position: static !important;
        top: auto !important;
    }

    .filter-sidebar__inner {
        padding: 14px 16px !important;
        border-radius: 14px !important;
        margin-bottom: 14px !important;
    }

    .filter-sidebar__title {
        font-size: 0.95rem !important;
        margin-bottom: 10px !important;
    }

    .filter-controls-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 10px !important;
    }

    .filter-group {
        margin-bottom: 8px !important;
    }

    .filter-sidebar__label {
        font-size: 0.76rem !important;
        margin-bottom: 4px !important;
    }

    .filter-sidebar__input {
        padding: 6px 10px !important;
        font-size: 0.82rem !important;
        height: 36px !important;
        border-radius: 8px !important;
    }

    .filter-actions-wrap {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        margin-top: 8px !important;
    }

    .filter-sidebar__apply {
        padding: 0 18px !important;
        font-size: 0.82rem !important;
        height: 36px !important;
        border-radius: 8px !important;
        flex: 0 0 auto !important;
        width: auto !important;
        box-shadow: 0 2px 8px rgba(230, 59, 111, 0.22) !important;
    }

    .btn-clear-filter {
        font-size: 0.76rem !important;
        width: auto !important;
        margin-top: 0 !important;
        padding: 4px 8px !important;
        white-space: nowrap !important;
    }

    /* 2 Columns Court Cards Mobile */
    .client-court-card {
        border-radius: 14px !important;
    }

    .client-court-card__img {
        height: 130px !important;
    }

    .card-badges-overlay {
        padding: 8px 8px !important;
    }

    .status-pill {
        font-size: 0.65rem !important;
        padding: 3px 8px !important;
    }

    .status-pill .pulse-dot {
        width: 5px !important;
        height: 5px !important;
    }

    .type-pill {
        font-size: 0.62rem !important;
        padding: 3px 7px !important;
    }

    .type-pill i {
        font-size: 0.56rem !important;
    }

    .client-court-card__body {
        padding: 12px 14px 12px !important;
    }

    .court-card-name {
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        margin-bottom: 4px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 1.3 !important;
    }

    .court-card-desc {
        font-size: 0.78rem !important;
        -webkit-line-clamp: 1 !important;
        line-clamp: 1 !important;
        margin-bottom: 8px !important;
        line-height: 1.35 !important;
    }

    /* Mobile Footer: Nút full-width bên dưới giá với chiều cao 32px to rõ & chắc chắn */
    .court-card-footer {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 6px !important;
        padding-top: 8px !important;
        border-top: 1px solid rgba(0, 0, 0, 0.06) !important;
    }

    .court-card-price {
        display: flex !important;
        align-items: baseline !important;
        justify-content: flex-start !important;
        gap: 3px !important;
    }

    .court-card-price .price-val {
        font-size: 0.92rem !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        line-height: 1.1 !important;
    }

    .court-card-price .price-unit {
        font-size: 0.72rem !important;
        color: #829ab1 !important;
    }

    /* Nút Đặt Sân Full-Width Chuẩn Đẹp (Chiều cao 32px to rõ & tiện bấm) */
    .btn-court-book {
        width: 100% !important;
        height: 32px !important;
        min-height: 32px !important;
        max-height: 32px !important;
        padding: 0 !important;
        font-size: 0.8rem !important;
        font-weight: 700 !important;
        border-radius: 8px !important;
        box-shadow: 0 2px 8px rgba(230, 59, 111, 0.25) !important;
        background: linear-gradient(135deg, var(--court-primary, #E63B6F), #c02858) !important;
        color: #fff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        line-height: 1 !important;
        gap: 5px !important;
    }

    .btn-court-book span {
        display: inline-flex !important;
        align-items: center !important;
        line-height: 1 !important;
    }

    .btn-court-book i {
        font-size: 0.74rem !important;
        display: inline-flex !important;
        align-items: center !important;
        line-height: 1 !important;
        margin: 0 !important;
    }
}
</style>
