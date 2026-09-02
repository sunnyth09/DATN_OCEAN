<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useOpenPlayStore } from '@/features/courts/stores/useOpenPlayStore';
import { useAuthStore } from '@/stores/auth';
import '@/features/courts/assets/court-management.css';

const router = useRouter();
const openPlayStore = useOpenPlayStore();
const authStore = useAuthStore();

const activeTab = ref('joined'); // 'joined' or 'hosted'

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: '/profile/open-plays' } });
    return;
  }
  await openPlayStore.fetchMyOpenPlays();
});

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

const goToDetail = (id) => {
  router.push({ name: 'open-play-detail', params: { id } });
};

const goToCourts = () => {
  router.push({ name: 'courts-list' });
};

const goToCourtBookings = () => {
  router.push({ name: 'profile-court-bookings' });
};
</script>

<template>
  <div class="my-open-plays-page">
    <!-- Top Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
          <span class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle p-2" style="width: 38px; height: 38px;">
            <i class="bi bi-trophy-fill fs-5"></i>
          </span>
          <span>Trận Đấu Của Tôi</span>
        </h3>
        <p class="text-muted small mb-0">Quản lý các trận đấu bạn tham gia hoặc làm Host rủ bạn chơi.</p>
      </div>
      
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary rounded-pill px-3 py-2 fw-semibold d-flex align-items-center gap-2" @click="goToCourtBookings">
          <i class="bi bi-calendar-check text-primary"></i>
          <span>Lịch Đặt Sân</span>
        </button>
        <button class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2" @click="goToCourts">
          <i class="bi bi-calendar-plus"></i>
          <span>Đặt Sân Mới</span>
        </button>
      </div>
    </div>

    <!-- Segmented Tabs (Unified Pink Theme) -->
    <div class="mb-4">
      <div class="court-segmented-control shadow-sm">
        <button
          type="button"
          class="court-segment-btn"
          :class="{ active: activeTab === 'joined' }"
          @click="activeTab = 'joined'"
        >
          <i class="bi bi-person-check-fill"></i>
          <span>Trận đã tham gia ({{ openPlayStore.myOpenPlays.joined?.length || 0 }})</span>
        </button>
        <button
          type="button"
          class="court-segment-btn"
          :class="{ active: activeTab === 'hosted' }"
          @click="activeTab = 'hosted'"
        >
          <i class="bi bi-star-fill"></i>
          <span>Trận tôi làm Host ({{ openPlayStore.myOpenPlays.hosted?.length || 0 }})</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="openPlayStore.isLoading" class="text-center py-5">
      <div class="spinner-border text-danger" role="status"></div>
      <p class="mt-2 text-muted small">Đang tải danh sách trận đấu...</p>
    </div>

    <!-- Tab 1: Joined Matches -->
    <div v-else-if="activeTab === 'joined'">
      <div v-if="openPlayStore.myOpenPlays.joined?.length === 0" class="card border-0 shadow-sm rounded-4 p-5 text-center my-3 bg-white">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light p-3 mx-auto mb-3" style="width: 70px; height: 70px;">
          <i class="bi bi-people text-muted fs-2"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1">Bạn chưa tham gia trận đấu nào</h5>
        <p class="text-muted small mb-4" style="max-width: 460px; margin: 0 auto;">Khám phá ngay các kèo giao lưu đang mở công khai để giao lưu thể thao và kết nối cùng bạn chơi mới!</p>
        <div>
          <router-link to="/open-plays" class="btn btn-primary px-4 py-2.5 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center gap-2">
            <i class="bi bi-search"></i>
            <span>Khám Phá Kèo Giao Lưu</span>
          </router-link>
        </div>
      </div>

      <div v-else class="row g-3">
        <div v-for="match in openPlayStore.myOpenPlays.joined" :key="match.id" class="col-12">
          <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 hover-lift cursor-pointer bg-white" @click="goToDetail(match.id)">
            <div class="row align-items-center g-3">
              <div class="col-md-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <span class="badge bg-dark rounded-pill px-3 py-1">{{ match.open_play_code }}</span>
                  <span
                    class="badge rounded-pill px-3 py-1"
                    :class="{
                      'bg-success': match.status === 'open',
                      'bg-danger': match.status === 'full',
                      'bg-primary': match.status === 'ongoing',
                      'bg-secondary': match.status === 'completed',
                      'bg-dark': match.status === 'cancelled',
                    }"
                  >
                    {{ match.status.toUpperCase() }}
                  </span>
                </div>
                <h5 class="fw-bold text-dark mb-1">{{ match.title }}</h5>
                <div class="d-flex flex-wrap gap-3 text-secondary small mt-2">
                  <div><i class="bi bi-calendar3 text-danger me-1"></i>{{ formatDateDisplay(match.booking?.booking_date) }}</div>
                  <div><i class="bi bi-clock text-danger me-1"></i>{{ formatTime(match.booking?.start_time) }} - {{ formatTime(match.booking?.end_time) }}</div>
                  <div><i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ match.booking?.court?.court_name }}</div>
                  <div>Host: <strong>{{ match.host?.full_name }}</strong></div>
                </div>
              </div>
              <div class="col-md-4 text-md-end">
                <button class="btn btn-outline-danger rounded-pill px-4 py-2 fw-bold" @click.stop="goToDetail(match.id)">
                  Xem chi tiết & QR
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: Hosted Matches -->
    <div v-else-if="activeTab === 'hosted'">
      <div v-if="openPlayStore.myOpenPlays.hosted?.length === 0" class="card border-0 shadow-sm rounded-4 p-5 text-center my-3 bg-white">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light p-3 mx-auto mb-3" style="width: 70px; height: 70px;">
          <i class="bi bi-calendar2-check text-muted fs-2"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1">Bạn chưa mở trận đấu nào</h5>
        <p class="text-muted small mb-4" style="max-width: 480px; margin: 0 auto;">Bạn muốn rủ bạn bè hoặc người chơi cùng? Hãy vào <strong>Lịch Đặt Sân</strong> của bạn và bấm <strong>"Mời người chơi"</strong> tại bất kỳ lịch đặt nào!</p>
        <div>
          <button class="btn btn-primary px-4 py-2.5 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center gap-2" @click="goToCourtBookings">
            <i class="bi bi-calendar-check"></i>
            <span>Xem Lịch Đặt Sân Để Mời Người</span>
          </button>
        </div>
      </div>

      <div v-else class="row g-3">
        <div v-for="match in openPlayStore.myOpenPlays.hosted" :key="match.id" class="col-12">
          <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 hover-lift cursor-pointer bg-white" @click="goToDetail(match.id)">
            <div class="row align-items-center g-3">
              <div class="col-md-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <span class="badge bg-danger rounded-pill px-3 py-1">Tôi làm Host</span>
                  <span class="badge bg-dark rounded-pill px-3 py-1">{{ match.open_play_code }}</span>
                  <span
                    class="badge rounded-pill px-3 py-1"
                    :class="{
                      'bg-success': match.status === 'open',
                      'bg-danger': match.status === 'full',
                      'bg-primary': match.status === 'ongoing',
                      'bg-secondary': match.status === 'completed',
                      'bg-dark': match.status === 'cancelled',
                    }"
                  >
                    {{ match.status.toUpperCase() }}
                  </span>
                </div>
                <h5 class="fw-bold text-dark mb-1">{{ match.title }}</h5>
                <div class="d-flex flex-wrap gap-3 text-secondary small mt-2">
                  <div><i class="bi bi-calendar3 text-danger me-1"></i>{{ formatDateDisplay(match.booking?.booking_date) }}</div>
                  <div><i class="bi bi-clock text-danger me-1"></i>{{ formatTime(match.booking?.start_time) }} - {{ formatTime(match.booking?.end_time) }}</div>
                  <div><i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ match.booking?.court?.court_name }}</div>
                  <div>Số người: <strong>{{ match.current_players }} / {{ match.max_players }}</strong></div>
                </div>
              </div>
              <div class="col-md-4 text-md-end">
                <button class="btn btn-primary rounded-pill px-4 py-2 fw-bold" @click.stop="goToDetail(match.id)">
                  Quản lý trận đấu
                </button>
              </div>
            </div>
          </div>
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
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06) !important;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
