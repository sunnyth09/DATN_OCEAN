<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useOpenPlayStore } from '@/features/courts/stores/useOpenPlayStore';
import { useAuthStore } from '@/stores/auth';
import Swal from 'sweetalert2';

const router = useRouter();
const openPlayStore = useOpenPlayStore();
const authStore = useAuthStore();

const selectedBookingId = ref(null);
const title = ref('');
const description = ref('');
const sportType = ref('badminton');
const skillLevel = ref('all_levels');
const genderRule = ref('any');
const matchType = ref('doubles');
const maxPlayers = ref(4);
const joinMode = ref('auto');
const paymentMode = ref('split_payment');
const rules = ref('');

const isSubmitting = ref(false);

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: '/open-plays/create' } });
    return;
  }
  await openPlayStore.fetchEligibleBookings();
  if (openPlayStore.eligibleBookings.length > 0) {
    selectedBookingId.value = openPlayStore.eligibleBookings[0].booking_id;
  }
});

const selectedBooking = computed(() => {
  return openPlayStore.eligibleBookings.find((b) => b.booking_id === selectedBookingId.value) || null;
});

const calculatedSlotPrice = computed(() => {
  if (!selectedBooking.value || paymentMode.value !== 'split_payment') return 0;
  const total = Number(selectedBooking.value.total_amount || 0);
  const max = Number(maxPlayers.value) || 4;
  return Math.floor(total / max);
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

const handleSubmit = async () => {
  if (!selectedBookingId.value) {
    Swal.fire('Lỗi', 'Vui lòng chọn một lịch đặt sân hợp lệ.', 'warning');
    return;
  }
  if (!title.value.trim()) {
    Swal.fire('Lỗi', 'Vui lòng nhập tên/tiêu đề kèo giao lưu.', 'warning');
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = {
      booking_id: selectedBookingId.value,
      title: title.value.trim(),
      description: description.value.trim() || null,
      sport_type: sportType.value,
      skill_level: skillLevel.value,
      gender_rule: genderRule.value,
      match_type: matchType.value,
      max_players: Number(maxPlayers.value),
      join_mode: joinMode.value,
      payment_mode: paymentMode.value,
      rules: rules.value.trim() || null,
    };

    const res = await openPlayStore.createMatch(payload);
    Swal.fire({
      icon: 'success',
      title: 'Tạo Kèo Thành Công!',
      text: 'Trận đấu mở của bạn đã được tạo và sẵn sàng nhận người chơi.',
    });
    router.push({ name: 'open-play-detail', params: { id: res.data.id } });
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Lỗi tạo kèo',
      text: err.response?.data?.message || 'Không thể tạo kèo giao lưu. Vui lòng thử lại.',
    });
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="container py-4">
    <nav class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><router-link to="/" class="text-decoration-none">Trang chủ</router-link></li>
        <li class="breadcrumb-item"><router-link to="/courts" class="text-decoration-none">Sân thể thao</router-link></li>
        <li class="breadcrumb-item active">Mở kèo giao lưu</li>
      </ol>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
          <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #fff0f5; color: #e63b6f;">
              <i class="bi bi-fire fs-4"></i>
            </div>
            <div>
              <h3 class="fw-bold text-dark mb-0">Mở Kèo Giao Lưu & Ghép Trận</h3>
              <p class="text-muted small mb-0">Chọn lịch đặt sân của bạn để tạo kèo rủ bạn chơi cùng trình độ.</p>
            </div>
          </div>

          <!-- Loading Eligible Bookings -->
          <div v-if="openPlayStore.isLoading" class="text-center py-5">
            <div class="spinner-border text-danger" role="status"></div>
            <p class="mt-2 text-muted">Đang kiểm tra các lịch đặt sân hợp lệ của bạn...</p>
          </div>

          <!-- No eligible bookings -->
          <div v-else-if="openPlayStore.eligibleBookings.length === 0" class="card border-0 rounded-4 p-5 text-center my-2" style="background: #fff9fa; border: 1px dashed rgba(230, 59, 111, 0.3) !important;">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle p-3 mx-auto mb-3" style="width: 65px; height: 65px; background: #fff0f5; color: #e63b6f;">
              <i class="bi bi-calendar-x fs-2"></i>
            </div>
            <h5 class="fw-bold text-dark mb-2">Bạn chưa có lịch đặt sân hợp lệ</h5>
            <p class="text-secondary small mb-4" style="max-width: 480px; margin: 0 auto;">
              Để mở kèo giao lưu tìm bạn chơi, trước tiên bạn cần có một lịch đặt sân đã được xác nhận và chưa diễn ra.
            </p>
            <div>
              <router-link to="/courts" class="btn btn-primary px-4 py-2.5 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                <i class="bi bi-calendar-plus"></i>
                <span>Đặt Sân Ngay</span>
              </router-link>
            </div>
          </div>

          <!-- Create Form -->
          <form v-else @submit.prevent="handleSubmit">
            <!-- Step 1: Select Booking -->
            <div class="mb-4">
              <label class="form-label fw-bold text-dark fs-6 mb-2">
                1. Chọn Lịch Đặt Sân Của Bạn <span class="text-danger">*</span>
              </label>
              <div class="row g-3">
                <div v-for="b in openPlayStore.eligibleBookings" :key="b.booking_id" class="col-md-6">
                  <label
                    class="card p-3 rounded-4 border-2 cursor-pointer h-100 transition-all"
                    :class="selectedBookingId === b.booking_id ? 'border-danger bg-danger-subtle bg-opacity-10' : 'border-light-subtle bg-light'"
                  >
                    <div class="d-flex align-items-start gap-3">
                      <input
                        type="radio"
                        name="selected_booking"
                        :value="b.booking_id"
                        v-model="selectedBookingId"
                        class="form-check-input mt-1"
                      />
                      <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <strong class="text-dark">{{ b.court?.court_name }}</strong>
                          <span class="badge bg-success small">Đã xác nhận</span>
                        </div>
                        <div class="small text-secondary mb-1">
                          <i class="bi bi-calendar3 me-1"></i>{{ formatDateDisplay(b.booking_date) }}
                        </div>
                        <div class="small text-secondary mb-1">
                          <i class="bi bi-clock me-1"></i>{{ formatTime(b.start_time) }} - {{ formatTime(b.end_time) }}
                        </div>
                        <div class="small fw-bold text-primary">
                          Tổng tiền: {{ formatCurrency(b.total_amount) }}
                        </div>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <!-- Step 2: Match Info -->
            <div class="mb-4 pt-3 border-top">
              <label class="form-label fw-bold text-dark fs-6 mb-2">
                2. Thông Tin Trận Đấu
              </label>

              <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Tên trận / Tiêu đề <span class="text-danger">*</span></label>
                <input
                  type="text"
                  class="form-control form-control-lg rounded-3 fs-6"
                  v-model="title"
                  placeholder="VD: Giao lưu cầu lông đôi nam nữ trình độ trung bình tối thứ 6"
                  required
                />
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Mô tả thêm về trận đấu</label>
                <textarea
                  class="form-control rounded-3"
                  rows="3"
                  v-model="description"
                  placeholder="VD: Nhóm vui vẻ, cầu Thành Công, chơi xong có giao lưu nước uống..."
                ></textarea>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label small fw-semibold text-secondary">Trình độ yêu cầu</label>
                  <select class="form-select" v-model="skillLevel">
                    <option value="all_levels">Mọi trình độ (All levels)</option>
                    <option value="beginner">Mới chơi (Beginner)</option>
                    <option value="intermediate">Trung bình (Intermediate)</option>
                    <option value="advanced">Nâng cao / Pro (Advanced)</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold text-secondary">Quy định giới tính</label>
                  <select class="form-select" v-model="genderRule">
                    <option value="any">Nam & Nữ (Không giới hạn)</option>
                    <option value="male_only">Chỉ Nam</option>
                    <option value="female_only">Chỉ Nữ</option>
                    <option value="mixed">Đôi Nam Nữ</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold text-secondary">Thể loại</label>
                  <select class="form-select" v-model="matchType">
                    <option value="doubles">Đánh Đôi (4 người)</option>
                    <option value="singles">Đánh Đơn (2 người)</option>
                    <option value="casual">Giao lưu tự do</option>
                    <option value="practice">Tập luyện</option>
                  </select>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label small fw-semibold text-secondary">Số người chơi tối đa</label>
                  <input type="number" class="form-control" v-model="maxPlayers" min="2" max="12" />
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold text-secondary">Chế độ tham gia</label>
                  <select class="form-select" v-model="joinMode">
                    <option value="auto">Tự động duyệt (Vào ngay)</option>
                    <option value="approval">Host duyệt thủ công</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold text-secondary">Hình thức chi phí</label>
                  <select class="form-select" v-model="paymentMode">
                    <option value="split_payment">Chia đều tiền sân (Split Payment)</option>
                    <option value="host_pays">Host bao sân (Miễn phí cho khách)</option>
                  </select>
                </div>
              </div>

              <!-- Price calculation preview -->
              <div v-if="paymentMode === 'split_payment'" class="p-3 bg-light rounded-4 mb-3 d-flex justify-content-between align-items-center">
                <div>
                  <div class="small text-muted">Dự tính tiền mỗi người chơi:</div>
                  <div class="small text-secondary">Tổng tiền sân ({{ formatCurrency(selectedBooking?.total_amount) }}) / {{ maxPlayers }} người</div>
                </div>
                <div class="fs-4 fw-bold text-danger">
                  {{ formatCurrency(calculatedSlotPrice) }} / người
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Quy định riêng của Host (Rules)</label>
                <textarea
                  class="form-control rounded-3"
                  rows="2"
                  v-model="rules"
                  placeholder="VD: Không hủy sát giờ dưới 2 tiếng, mang giày đế kếp chuyên dụng..."
                ></textarea>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="d-flex justify-content-end gap-3 pt-3 border-top">
              <router-link to="/open-plays" class="btn btn-light px-4 py-2 rounded-pill">
                Hủy
              </router-link>
              <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-bold shadow-sm" :disabled="isSubmitting">
                <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                Tạo & Đăng Kèo Ngay
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
.transition-all {
  transition: all 0.2s ease;
}
</style>
