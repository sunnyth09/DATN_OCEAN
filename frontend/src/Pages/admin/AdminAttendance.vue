<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import api from '../../axios';

// --- TOAST ---
const toastVisible = ref(false);
let toastTimer = null;
const toast = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  toastVisible.value = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastVisible.value = false; }, 4000);
};

// --- CLOCK ---
const currentTime = ref('');
const currentDate = ref('');
let clockInterval = null;
const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('vi-VN', { hour12: false });
    currentDate.value = now.toLocaleDateString('vi-VN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

// --- CAMERA ---
const videoElement = ref(null);
const canvasElement = ref(null);
let videoStream = null;

const startCamera = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        videoStream = stream;
        if (videoElement.value) videoElement.value.srcObject = stream;
    } catch {
        showToast("Không thể truy cập camera. Vui lòng cấp quyền!", "error");
    }
};

const stopCamera = () => {
    if (videoStream) videoStream.getTracks().forEach(track => track.stop());
};

const captureImage = () => {
    if (!videoElement.value || !canvasElement.value) return null;
    const canvas = canvasElement.value;
    const ctx = canvas.getContext('2d');
    const w = videoElement.value.videoWidth || 640;
    const h = videoElement.value.videoHeight || 480;
    if (w === 0 || h === 0) return null;
    canvas.width = w; canvas.height = h;
    ctx.drawImage(videoElement.value, 0, 0, w, h);
    return canvas.toDataURL('image/jpeg', 0.8);
};

// --- FACE DETECTION ---
const detectFace = async (imageBase64) => {
    // Dùng FaceDetector API (Chrome/Edge native)
    if (!('FaceDetector' in window)) return true; // Fallback: bỏ qua nếu browser không hỗ trợ

    try {
        const img = new Image();
        img.src = imageBase64;
        await new Promise((resolve) => { img.onload = resolve; });

        const detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
        const faces = await detector.detect(img);
        return faces.length > 0;
    } catch {
        return true; // Nếu lỗi, cho phép qua (graceful fallback)
    }
};

// --- ATTENDANCE STATE ---
const loading = ref(false);
const gpsLoading = ref(false);
const attendanceNote = ref('');
const todayData = ref(null);
const checkInResult = ref(null);

// Current shift info
const currentShift = computed(() => todayData.value?.current_shift || null);
const shiftsStatus = computed(() => todayData.value?.shifts || []);
const overallState = computed(() => todayData.value?.state || 'loading');

// Can check-in: ca hiện tại được phân + chưa check-in
const canCheckIn = computed(() => {
    if (!currentShift.value || !currentShift.value.is_assigned) return false;
    const shiftData = shiftsStatus.value.find(s => s.shift_id === currentShift.value.id);
    return shiftData && shiftData.state === 'not_checked_in';
});

// Can check-out: ca hiện tại đang checked_in
const canCheckOut = computed(() => {
    const shiftData = shiftsStatus.value.find(s => s.state === 'checked_in');
    return !!shiftData;
});

const currentCheckedInShift = computed(() => {
    return shiftsStatus.value.find(s => s.state === 'checked_in');
});

// --- FETCH TODAY ---
const fetchTodayStatus = async () => {
    try {
        const res = await api.get('/admin/attendance/today');
        if (res.data.status === 'success') {
            todayData.value = res.data.data;
        }
    } catch {
        todayData.value = { state: 'not_checked_in', current_shift: null, shifts: [] };
    }
};

// --- GPS ---
const getGeolocation = () => {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) { reject(new Error("Trình duyệt không hỗ trợ GPS.")); return; }
        gpsLoading.value = true;
        navigator.geolocation.getCurrentPosition(
            (pos) => { gpsLoading.value = false; resolve(pos); },
            (err) => {
                gpsLoading.value = false;
                const msgs = { 1: "Cần cấp quyền vị trí!", 2: "Không xác định được GPS.", 3: "Hết thời gian chờ GPS." };
                reject(new Error(msgs[err.code] || "Lỗi GPS."));
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    });
};

// --- CHECK-IN ---
const handleCheckIn = async () => {
    loading.value = true;
    checkInResult.value = null;
    try {
        const imageBase64 = captureImage();
        if (!imageBase64) throw new Error("Không thể chụp ảnh, hãy đảm bảo camera hoạt động!");

        // Face detection
        const hasFace = await detectFace(imageBase64);
        if (!hasFace) {
            showToast("Không phát hiện khuôn mặt! Vui lòng đưa mặt vào camera rồi thử lại.", "error");
            loading.value = false;
            return;
        }

        const position = await getGeolocation();
        const payload = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            note: attendanceNote.value,
            image: imageBase64,
        };

        const res = await api.post('/admin/attendance/check-in', payload);
        showToast(res.data.message || 'Check-in thành công!', 'success');
        attendanceNote.value = '';
        checkInResult.value = res.data.data;
        fetchTodayStatus();
    } catch (error) {
        const msg = error.response?.data?.message || error.message || "Lỗi Check-in";
        showToast(msg, 'error');
        if (error.response?.data?.data) checkInResult.value = error.response.data.data;
    } finally { loading.value = false; }
};

// --- CHECK-OUT ---
const handleCheckOut = async () => {
    loading.value = true;
    try {
        const imageBase64 = captureImage();
        if (!imageBase64) throw new Error("Không thể chụp ảnh!");

        const hasFace = await detectFace(imageBase64);
        if (!hasFace) {
            showToast("Không phát hiện khuôn mặt! Vui lòng đưa mặt vào camera.", "error");
            loading.value = false;
            return;
        }

        const position = await getGeolocation();
        const res = await api.post('/admin/attendance/check-out', {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            image: imageBase64,
        });
        showToast(res.data.message || 'Check-out thành công!', 'success');
        checkInResult.value = null;
        fetchTodayStatus();
    } catch (error) {
        showToast(error.response?.data?.message || error.message || "Lỗi Check-out", 'error');
    } finally { loading.value = false; }
};

// --- FORMAT ---
const formatTime = (d) => d ? new Date(d).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
const getShiftStateLabel = (state) => {
    const m = { 'not_checked_in': 'Chưa vào', 'checked_in': 'Đang làm', 'checked_out': 'Hoàn tất', 'not_assigned': 'Không phân' };
    return m[state] || state;
};
const getShiftStateBadge = (state) => {
    const m = { 'not_checked_in': 'text-bg-warning', 'checked_in': 'text-bg-success', 'checked_out': 'text-bg-info', 'not_assigned': 'text-bg-light text-muted' };
    return m[state] || 'text-bg-secondary';
};

// --- LIFECYCLE ---
onMounted(() => { updateClock(); clockInterval = setInterval(updateClock, 1000); startCamera(); fetchTodayStatus(); });
onUnmounted(() => { clearInterval(clockInterval); stopCamera(); });
</script>

<template>
  <div class="attendance-container p-4">
    <!-- Header -->
    <div class="attendance-header">
      <div>
        <h2 class="h4 mb-1 fw-bold text-gray-800">
           <span class="text-primary me-1">#</span> Chấm Công GPS
        </h2>
        <p class="text-muted small mb-0">{{ currentDate }}</p>
      </div>
      <div class="d-flex align-items-center gap-3">
        <!-- Ca hiện tại -->
        <span v-if="currentShift" class="shift-badge">
          <i class="bi bi-clock-history me-1"></i>{{ currentShift.name }}
          <span class="ms-1 opacity-75">({{ currentShift.start_time?.substring(0,5) }} - {{ currentShift.end_time?.substring(0,5) }})</span>
        </span>
        <span v-else class="shift-badge shift-badge-off">Ngoài giờ ca</span>
        <div class="clock-badge">{{ currentTime }}</div>
      </div>
    </div>

    <div class="row g-4 mt-1">
      <!-- Cột Trái -->
      <div class="col-lg-5">
        <!-- Trạng thái từng ca -->
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-list-check me-2 text-primary"></i>Trạng thái các ca hôm nay</h6>
            <div v-if="shiftsStatus.length === 0" class="text-muted small">Đang tải...</div>
            <div v-else class="d-flex flex-column gap-2">
              <div v-for="s in shiftsStatus" :key="s.shift_id"
                   class="shift-status-row d-flex align-items-center justify-content-between p-2 rounded"
                   :class="{ 'shift-current': s.is_current }">
                <div>
                  <span class="fw-bold small">{{ s.shift_name }}</span>
                  <span class="text-muted small ms-2">{{ s.start_time?.substring(0,5) }} - {{ s.end_time?.substring(0,5) }}</span>
                </div>
                <span class="badge" :class="getShiftStateBadge(s.state)">{{ getShiftStateLabel(s.state) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Kết quả check-in -->
        <div v-if="checkInResult" class="card shadow-sm border-0">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Kết quả</h6>
            <div class="result-grid">
              <div v-if="checkInResult.shift_name" class="result-item">
                <span class="result-label">Ca:</span>
                <span class="result-value fw-medium">{{ checkInResult.shift_name }}</span>
              </div>
              <div v-if="checkInResult.location_name" class="result-item">
                <span class="result-label">Vị trí:</span>
                <span class="result-value fw-medium">{{ checkInResult.location_name }}</span>
              </div>
              <div v-if="checkInResult.nearest_location" class="result-item">
                <span class="result-label">Gần nhất:</span>
                <span class="result-value fw-medium">{{ checkInResult.nearest_location }}</span>
              </div>
              <div v-if="checkInResult.distance_meters !== undefined" class="result-item">
                <span class="result-label">Khoảng cách:</span>
                <span class="result-value fw-medium" :class="checkInResult.distance_meters > (checkInResult.allowed_radius_meters || 200) ? 'text-danger' : 'text-success'">
                  {{ Math.round(checkInResult.distance_meters) }}m
                </span>
              </div>
              <div v-if="checkInResult.accuracy" class="result-item">
                <span class="result-label">GPS accuracy:</span>
                <span class="result-value" :class="checkInResult.accuracy > 100 ? 'text-warning' : 'text-success'">±{{ Math.round(checkInResult.accuracy) }}m</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cột Phải: Camera + Action -->
      <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center">
            <!-- Camera -->
            <div class="mb-4 w-100 px-3 position-relative">
              <video ref="videoElement" autoplay playsinline class="w-100 rounded shadow-sm border" style="max-height: 280px; background: #000; object-fit: cover;"></video>
              <canvas ref="canvasElement" style="display: none;"></canvas>
              <div class="face-hint">
                <i class="bi bi-person-bounding-box me-1"></i> Đưa mặt vào camera
              </div>
            </div>

            <!-- Note -->
            <div v-if="canCheckIn" class="w-100 mb-4" style="max-width: 420px;">
              <label class="form-label text-start w-100 fw-medium small">Ghi chú (không bắt buộc)</label>
              <textarea v-model="attendanceNote" class="form-control" rows="2" placeholder="Ví dụ: Ca sáng, đã nhận hàng..."></textarea>
            </div>

            <!-- Loading -->
            <div v-if="loading || gpsLoading" class="mb-3">
              <div class="spinner-border text-primary" role="status"></div>
              <p class="text-muted small mt-2 mb-0">{{ gpsLoading ? 'Đang lấy vị trí GPS...' : 'Đang xử lý...' }}</p>
            </div>

            <!-- Buttons -->
            <div v-else class="w-100 d-flex flex-column align-items-center gap-3">
              <!-- CHECK-IN -->
              <button v-if="canCheckIn" @click="handleCheckIn" id="btn-check-in"
                class="btn btn-checkin-action d-flex align-items-center justify-content-center py-3 px-5 rounded-pill fw-bold shadow"
                style="min-width: 240px; font-size: 1.1rem;">
                <i class="bi bi-box-arrow-in-right me-2 fs-4"></i>
                CHECK-IN {{ currentShift?.name?.toUpperCase() }}
              </button>

              <!-- CHECK-OUT -->
              <div v-if="canCheckOut" class="text-center">
                <p class="text-muted small mb-2">
                  Đang làm <strong>{{ currentCheckedInShift?.shift_name }}</strong>
                  — check-in lúc <strong>{{ formatTime(currentCheckedInShift?.attendance?.check_in_at) }}</strong>
                </p>
                <button @click="handleCheckOut" id="btn-check-out"
                  class="btn btn-checkout-action d-flex align-items-center justify-content-center py-3 px-5 rounded-pill fw-bold shadow"
                  style="min-width: 240px; font-size: 1.1rem;">
                  <i class="bi bi-box-arrow-left me-2 fs-4"></i>
                  CHECK-OUT
                </button>
              </div>

              <!-- Không có ca / Không được phân -->
              <div v-if="!canCheckIn && !canCheckOut && !loading" class="text-center">
                <div v-if="!currentShift" class="text-muted">
                  <i class="bi bi-moon-stars fs-3 d-block mb-2"></i>
                  <p class="fw-medium">Hiện không có ca nào đang hoạt động.</p>
                </div>
                <div v-else-if="!currentShift.is_assigned" class="text-warning">
                  <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
                  <p class="fw-medium">Bạn không được phân <strong>{{ currentShift.name }}</strong> hôm nay.</p>
                </div>
                <div v-else class="text-success">
                  <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                  <p class="fw-medium">Đã hoàn tất tất cả ca hôm nay.</p>
                </div>
              </div>
            </div>

            <div class="mt-3 text-muted small">
              <i class="bi bi-shield-lock me-1"></i> GPS + ảnh selfie được thu thập tự động.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="att-toast">
      <div v-if="toastVisible" class="att-toast" :class="'att-toast-' + toast.type">{{ toast.message }}</div>
    </Transition>
  </div>
</template>

<style scoped>
.attendance-container { max-width: 1100px; margin: 0 auto; }
.attendance-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }

.clock-badge { background: #eef2ff; color: #4f46e5; padding: 8px 16px; border-radius: 50px; font-weight: bold; font-size: 1.1rem; }
.shift-badge { background: #dcfce3; color: #065f46; padding: 6px 14px; border-radius: 50px; font-weight: 600; font-size: 0.85rem; }
.shift-badge-off { background: #f3f4f6; color: #6b7280; }

.shift-status-row { background: #f9fafb; transition: all 0.2s; }
.shift-status-row:hover { background: #f0f2f5; }
.shift-current { background: #eef2ff !important; border: 1px solid #c7d2fe; }

.result-grid { display: flex; flex-direction: column; gap: 8px; }
.result-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6; }
.result-item:last-child { border-bottom: none; }
.result-label { color: #6b7280; font-size: 0.9rem; }
.result-value { font-size: 0.9rem; }

.face-hint { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; }

.btn-checkin-action { background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; transition: all 0.3s; }
.btn-checkin-action:hover { background: linear-gradient(135deg, #059669, #047857); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4) !important; color: white; }
.btn-checkout-action { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; transition: all 0.3s; }
.btn-checkout-action:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(239,68,68,0.4) !important; color: white; }

.att-toast { position: fixed; bottom: 30px; right: 30px; padding: 14px 28px; border-radius: 12px; color: white; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.2); z-index: 9999; max-width: 450px; }
.att-toast-success { background: linear-gradient(135deg, #10b981, #059669); }
.att-toast-error { background: linear-gradient(135deg, #ef4444, #dc2626); }
.att-toast-enter-active { transition: all 0.3s ease; }
.att-toast-leave-active { transition: all 0.2s ease; }
.att-toast-enter-from, .att-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
