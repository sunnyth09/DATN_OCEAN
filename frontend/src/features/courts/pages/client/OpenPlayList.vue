<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useOpenPlayStore } from '@/features/courts/stores/useOpenPlayStore';
import { useAuthStore } from '@/stores/auth';
import '@/features/courts/assets/court-management.css';

const router = useRouter();
const openPlayStore = useOpenPlayStore();
const authStore = useAuthStore();

const toLocalDateString = (date = new Date()) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const searchParams = ref({
  date: toLocalDateString(),
  sport_type: 'all',
  skill_level: 'all',
  gender_rule: 'all',
  match_type: 'all',
  available_only: false,
  search: '',
  page: 1,
});

onMounted(async () => {
  await fetchMatches();
  setupRealtime();
});

onUnmounted(() => {
  if (window.Echo) {
    window.Echo.leave('open-plays');
  }
});

const setupRealtime = () => {
  if (!window.Echo) return;
  window.Echo.channel('open-plays')
    .listen('.OpenPlayCreated', () => {
      fetchMatches();
    })
    .listen('.ParticipantJoined', () => {
      fetchMatches();
    })
    .listen('.ParticipantLeft', () => {
      fetchMatches();
    })
    .listen('.OpenPlayCancelled', () => {
      fetchMatches();
    });
};

const fetchMatches = async (page = 1) => {
  searchParams.value.page = page;
  try {
    await openPlayStore.fetchMatches(searchParams.value);
  } catch (e) {
    console.warn('Lỗi tải danh sách kèo giao lưu:', e);
  }
};

const clearFilters = () => {
  searchParams.value = {
    date: toLocalDateString(),
    sport_type: 'all',
    skill_level: 'all',
    gender_rule: 'all',
    match_type: 'all',
    available_only: false,
    search: '',
    page: 1,
  };
  fetchMatches();
};

const isMobileFilterOpen = ref(false);

const setQuickDate = (type) => {
  const d = new Date();
  if (type === 'today') {
    searchParams.value.date = toLocalDateString(d);
  } else if (type === 'tomorrow') {
    d.setDate(d.getDate() + 1);
    searchParams.value.date = toLocalDateString(d);
  } else if (type === 'all') {
    searchParams.value.date = '';
  }
  fetchMatches(1);
};

const isQuickDateActive = (type) => {
  const d = new Date();
  if (type === 'today') {
    return searchParams.value.date === toLocalDateString(d);
  } else if (type === 'tomorrow') {
    d.setDate(d.getDate() + 1);
    return searchParams.value.date === toLocalDateString(d);
  } else if (type === 'all') {
    return !searchParams.value.date;
  }
  return false;
};

const setSkillLevel = (lvl) => {
  searchParams.value.skill_level = lvl;
  fetchMatches(1);
};

const setGenderRule = (rule) => {
  searchParams.value.gender_rule = rule;
  fetchMatches(1);
};

const goToDetail = (id) => {
  router.push({ name: 'open-play-detail', params: { id } });
};

const goToCourts = () => {
  router.push({ name: 'courts-list' });
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const formatTime = (timeStr) => {
  if (!timeStr) return '';
  return timeStr.substring(0, 5);
};

const formatDateDisplay = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getSkillBadge = (level) => {
  const map = {
    all: { text: 'Mọi trình độ', class: 'badge-skill-all' },
    all_levels: { text: 'Mọi trình độ', class: 'badge-skill-all' },
    beginner: { text: 'Mới chơi', class: 'badge-skill-beginner' },
    intermediate: { text: 'Trung bình', class: 'badge-skill-intermediate' },
    advanced: { text: 'Nâng cao', class: 'badge-skill-advanced' }
  };
  return map[level] || { text: level, class: 'badge-skill-all' };
};

const getGenderBadge = (gender) => {
  const map = {
    all: 'Nam & Nữ',
    any: 'Nam & Nữ',
    male_only: 'Chỉ Nam',
    male: 'Chỉ Nam',
    female_only: 'Chỉ Nữ',
    female: 'Chỉ Nữ',
    mixed: 'Đôi Nam Nữ'
  };
  return map[gender] || 'Không giới hạn';
};

const getMatchTypeBadge = (type) => {
  const map = {
    single: 'Đánh đơn',
    singles: 'Đánh đơn',
    double: 'Đánh đôi',
    doubles: 'Đánh đôi',
    mixed: 'Đôi nam nữ',
    any: 'Tự do'
  };
  return map[type] || 'Giao lưu';
};

const getGenderLabel = (gender) => {
  const map = {
    any: 'Không giới hạn',
    all: 'Không giới hạn',
    male: 'Nam',
    male_only: 'Nam',
    female: 'Nữ',
    female_only: 'Nữ',
    mixed: 'Nam/Nữ'
  };
  return map[gender] || gender;
};

const getCourtTypeLabel = (type) => {
  const map = {
    standard: 'Tiêu chuẩn',
    vip: 'VIP',
    outdoor: 'Ngoài trời',
    indoor: 'Trong nhà'
  };
  return map[type] || type;
};
</script>

<template>
  <div class="court-container container py-3 py-md-4">
    <!-- Sleek Segmented Sub-Nav Bar -->
    <div class="court-subnav-bar">
      <div class="court-segmented-control">
        <router-link to="/courts" class="court-segment-btn">
          <i class="bi bi-geo-alt-fill"></i>
          <span>Đặt Sân Thể Thao</span>
        </router-link>
        <router-link to="/open-plays" class="court-segment-btn active">
          <i class="bi bi-people-fill"></i>
          <span>Kèo Giao Lưu & Ghép Trận</span>
        </router-link>
      </div>

      <router-link to="/profile/court-bookings" class="court-subnav-right-btn">
        <i class="bi bi-calendar-check" style="color: #e63b6f;"></i>
        <span>Lịch Đặt Của Tôi</span>
      </router-link>
    </div>

    <!-- Ocean Sport Unified Pink Hero Banner -->
    <div class="court-hero-pink">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
          <div class="hero-pill">
            <i class="bi bi-fire me-1 text-warning"></i> OCEAN SPORT OPEN PLAY
          </div>
          <h1>Kèo Giao Lưu &amp; Ghép Trận</h1>
          <p class="hero-sub mb-0">
            Khám phá và tham gia các trận đấu đang mở công khai từ cộng đồng Ocean Sport. Bạn muốn mở trận mới? Hãy đặt sân trước và chọn "Mời người chơi"!
          </p>
        </div>

        <!-- Action Buttons on Pink Banner -->
        <div class="d-flex flex-wrap gap-2 align-self-start align-self-md-center flex-shrink-0">
          <router-link to="/courts" class="court-hero-btn-white">
            <i class="bi bi-calendar-plus"></i>
            <span>Đặt Sân Để Mở Trận</span>
          </router-link>
          <router-link to="/profile/open-plays" v-if="authStore.isAuthenticated" class="court-hero-btn-glass">
            <i class="bi bi-trophy"></i>
            <span>Trận Đấu Của Tôi</span>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Main Content: Sidebar Filter + Match List -->
    <div class="row g-3 g-lg-4">
      <!-- Modern Sports Filter Sidebar -->
      <div class="col-lg-3">
        <!-- Mobile Filter Toggle Button -->
        <div class="d-lg-none mb-3">
          <button
            class="btn btn-outline-secondary w-100 rounded-3 py-2 d-flex align-items-center justify-content-between fw-semibold"
            @click="isMobileFilterOpen = !isMobileFilterOpen"
          >
            <span><i class="bi bi-funnel-fill text-danger me-2"></i>Bộ Lọc Kèo Giao Lưu</span>
            <i class="bi" :class="isMobileFilterOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
          </button>
        </div>

        <div class="sports-filter-card sticky-top" :class="{ 'd-none d-lg-block': !isMobileFilterOpen }">
          <!-- Filter Header -->
          <div class="filter-header-modern">
            <h5 class="filter-header-title">
              <span class="filter-header-icon"><i class="bi bi-funnel-fill"></i></span>
              <span>Bộ Lọc Kèo</span>
            </h5>
            <button class="filter-btn-reset" @click="clearFilters">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Đặt lại
            </button>
          </div>

          <!-- Search Input -->
          <div class="filter-group-modern">
            <label class="filter-label-modern">Tìm kiếm</label>
            <div class="modern-input-box">
              <i class="bi bi-search modern-input-icon"></i>
              <input
                type="text"
                class="modern-input-field"
                v-model="searchParams.search"
                @keyup.enter="fetchMatches(1)"
                placeholder="Tên kèo, sân, host..."
              />
            </div>
          </div>

          <!-- Date Filter with Quick Chips -->
          <div class="filter-group-modern">
            <label class="filter-label-modern">Ngày thi đấu</label>
            <div class="quick-chips-group mb-2">
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: isQuickDateActive('today') }"
                @click="setQuickDate('today')"
              >
                Hôm nay
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: isQuickDateActive('tomorrow') }"
                @click="setQuickDate('tomorrow')"
              >
                Ngày mai
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: isQuickDateActive('all') }"
                @click="setQuickDate('all')"
              >
                Tất cả
              </button>
            </div>
            <div class="modern-input-box">
              <i class="bi bi-calendar3 modern-input-icon"></i>
              <input
                type="date"
                class="modern-input-field"
                v-model="searchParams.date"
                @change="fetchMatches(1)"
              />
            </div>
          </div>

          <!-- Skill Level Chips -->
          <div class="filter-group-modern">
            <label class="filter-label-modern">Trình độ</label>
            <div class="quick-chips-group">
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.skill_level === 'all' }"
                @click="setSkillLevel('all')"
              >
                Tất cả
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.skill_level === 'beginner' }"
                @click="setSkillLevel('beginner')"
              >
                🟢 Mới chơi
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.skill_level === 'intermediate' }"
                @click="setSkillLevel('intermediate')"
              >
                🟡 Trung bình
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.skill_level === 'advanced' }"
                @click="setSkillLevel('advanced')"
              >
                🔴 Nâng cao
              </button>
            </div>
          </div>

          <!-- Gender Rule Chips -->
          <div class="filter-group-modern">
            <label class="filter-label-modern">Quy định giới tính</label>
            <div class="quick-chips-group">
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.gender_rule === 'all' }"
                @click="setGenderRule('all')"
              >
                Tất cả
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.gender_rule === 'any' }"
                @click="setGenderRule('any')"
              >
                Nam & Nữ
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.gender_rule === 'male_only' }"
                @click="setGenderRule('male_only')"
              >
                Chỉ Nam
              </button>
              <button
                type="button"
                class="quick-filter-chip"
                :class="{ active: searchParams.gender_rule === 'female_only' }"
                @click="setGenderRule('female_only')"
              >
                Chỉ Nữ
              </button>
            </div>
          </div>

          <!-- Available only switch -->
          <div class="d-flex align-items-center justify-content-between my-3 pt-2 border-top">
            <label class="form-check-label text-dark fw-semibold" style="font-size: 0.82rem;" for="availableOnlySwitch">
              Chỉ hiện slot còn trống
            </label>
            <div class="form-check form-switch m-0">
              <input
                class="form-check-input"
                type="checkbox"
                id="availableOnlySwitch"
                v-model="searchParams.available_only"
                @change="fetchMatches(1)"
              />
            </div>
          </div>

          <!-- Apply Filter Button -->
          <button class="filter-apply-btn-modern" @click="fetchMatches(1)">
            <i class="bi bi-search"></i>
            <span>Áp Dụng Bộ Lọc</span>
          </button>
        </div>
      </div>

      <!-- Match Cards Feed -->
      <div class="col-lg-9">
        <!-- Loading state -->
        <div v-if="openPlayStore.isLoading" class="row g-3">
          <div v-for="i in 4" :key="i" class="col-12">
            <div class="card p-3 border-0 shadow-sm rounded-4 animate-pulse" style="background: #f8fafc; height: 160px;"></div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="openPlayStore.matches.length === 0" class="card border-0 shadow-sm rounded-4 p-5 text-center my-3">
          <div class="mb-3">
            <i class="bi bi-calendar-x text-muted" style="font-size: 3.5rem;"></i>
          </div>
          <h4 class="fw-bold text-dark">Chưa có trận giao lưu nào phù hợp</h4>
          <p class="text-muted">Không tìm thấy trận đấu nào theo bộ lọc của bạn. Bạn muốn mở một trận mới? Hãy đặt sân trước và chọn "Mời người chơi" nhé!</p>
          <div class="mt-2">
            <router-link to="/courts" class="btn btn-primary px-4 py-2 rounded-pill fw-bold shadow-sm">
              <i class="bi bi-calendar-plus me-2"></i>Đặt Sân Mới Ngay
            </router-link>
          </div>
        </div>

        <!-- Match Cards List -->
        <div v-else class="row g-3">
          <div v-for="match in openPlayStore.matches" :key="match.id" class="col-12">
            <div
              class="open-play-match-card"
              @click="goToDetail(match.id)"
            >
              <div class="row align-items-center g-3">
                <!-- Left Details -->
                <div class="col-md-7">
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span :class="getSkillBadge(match.skill_level).class">
                      {{ getSkillBadge(match.skill_level).text }}
                    </span>
                    <span class="badge-gender-pill">
                      <i class="bi bi-gender-ambiguous me-1 text-ocean-primary"></i>{{ getGenderBadge(match.gender_rule) }}
                    </span>
                    <span class="badge-match-pill">
                      <i class="bi bi-controller me-1 text-ocean-primary"></i>{{ getMatchTypeBadge(match.match_type) }}
                    </span>
                    <span v-if="match.payment_mode === 'split_payment'" class="badge-split-payment">
                      <i class="bi bi-cash-coin me-1"></i>Chia tiền sân
                    </span>
                    <span v-else class="badge-host-pays">
                      <i class="bi bi-gift me-1"></i>Host bao sân
                    </span>
                  </div>

                  <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <span>{{ match.title }}</span>
                    <span v-if="match.status === 'full'" class="badge bg-danger text-white small">FULL</span>
                  </h5>

                  <div class="d-flex flex-wrap gap-3 text-secondary small mt-2">
                    <div class="d-flex align-items-center gap-1">
                      <i class="bi bi-calendar3 text-ocean-primary"></i>
                      <span>{{ formatDateDisplay(match.booking?.booking_date) }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                      <i class="bi bi-clock text-ocean-primary"></i>
                      <span class="fw-semibold">{{ formatTime(match.booking?.start_time) }} - {{ formatTime(match.booking?.end_time) }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                      <i class="bi bi-geo-alt-fill text-danger"></i>
                      <span class="fw-semibold text-dark">{{ match.booking?.court?.court_name || 'Sân cầu lông' }}</span>
                    </div>
                  </div>

                  <!-- Host info -->
                  <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                    <div class="host-avatar-capsule">
                      {{ (match.host?.full_name || 'H')[0].toUpperCase() }}
                    </div>
                    <span class="small text-secondary">
                      Host: <strong class="text-dark">{{ match.host?.full_name || 'Host' }}</strong>
                    </span>
                    <span class="small text-muted ms-2">
                      <i class="bi bi-shield-check text-success me-1"></i>Booking đã xác nhận
                    </span>
                  </div>
                </div>

                <!-- Right: Capacity & Actions -->
                <div class="col-md-5">
                  <div class="p-3 bg-light rounded-4 text-center">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <span class="small fw-semibold text-secondary">Số lượng người chơi</span>
                      <span class="fw-bold fs-6 text-dark">
                        <strong class="text-ocean-primary">{{ match.current_players }}</strong> / {{ match.max_players }}
                      </span>
                    </div>

                    <!-- Progress bar -->
                    <div class="progress mb-2" style="height: 8px;">
                      <div
                        class="progress-bar ocean-progress-bar"
                        :class="match.current_players >= match.max_players ? 'bg-danger' : 'bg-primary'"
                        role="progressbar"
                        :style="{ width: ((match.current_players / match.max_players) * 100) + '%' }"
                      ></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small mb-3">
                      <span v-if="match.available_slots > 0" class="text-success fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i>Còn {{ match.available_slots }} slot trống
                      </span>
                      <span v-else class="text-danger fw-bold">
                        <i class="bi bi-x-circle-fill me-1"></i>Đã đủ người (Full)
                      </span>
                      <span v-if="match.payment_mode === 'split_payment'" class="fw-bold text-dark">
                        {{ formatCurrency(match.slot_price) }} / người
                      </span>
                      <span v-else class="badge-split-payment">
                        Miễn phí
                      </span>
                    </div>

                    <button
                      class="btn btn-sm w-100 fw-bold py-2 rounded-pill shadow-sm"
                      :class="match.available_slots > 0 ? 'btn-primary' : 'btn-outline-secondary'"
                      @click.stop="goToDetail(match.id)"
                    >
                      <template v-if="match.available_slots > 0">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Xem & Tham Gia
                      </template>
                      <template v-else>
                        <i class="bi bi-hourglass-split me-1"></i> Xem Danh Sách Chờ
                      </template>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="openPlayStore.pagination.lastPage > 1" class="d-flex justify-content-center mt-4">
          <nav>
            <ul class="pagination pagination-sm shadow-sm rounded-pill overflow-hidden">
              <li class="page-item" :class="{ disabled: openPlayStore.pagination.currentPage === 1 }">
                <button class="page-link" @click="fetchMatches(openPlayStore.pagination.currentPage - 1)">
                  <i class="bi bi-chevron-left"></i>
                </button>
              </li>
              <li
                v-for="page in openPlayStore.pagination.lastPage"
                :key="page"
                class="page-item"
                :class="{ active: page === openPlayStore.pagination.currentPage }"
              >
                <button class="page-link" @click="fetchMatches(page)">{{ page }}</button>
              </li>
              <li class="page-item" :class="{ disabled: openPlayStore.pagination.currentPage === openPlayStore.pagination.lastPage }">
                <button class="page-link" @click="fetchMatches(openPlayStore.pagination.currentPage + 1)">
                  <i class="bi bi-chevron-right"></i>
                </button>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hover-lift {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08) !important;
}
.cursor-pointer {
  cursor: pointer;
}
.animate-pulse {
  animation: pulse 1.5s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>
