<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { attendanceService } from '@/features/hr/services/attendanceService';
import { useCamera } from '@/features/hr/composables/useCamera';
import { useGeolocation } from '@/features/hr/composables/useGeolocation';

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

// --- CAMERA + GPS (composables) ---
const {
    videoElement,
    canvasElement,
    start: startCamera,
    stop: stopCamera,
    capture: captureImage,
    hasFace: detectFace,
} = useCamera();
const { gpsLoading, getPosition } = useGeolocation();

// --- ATTENDANCE STATE ---
const loading = ref(false);
const attendanceNote = ref('');
const todayData = ref(null);
const checkInResult = ref(null);

// Face verification status
const faceRegistered = ref(null); // null = loading, true/false
const faceEncodingCount = ref(0);

// Face scanning UI state
const scanningPhase = ref(''); // '' | 'capture' | 'gps' | 'face_scan' | 'done' | 'error'
const scanResult = ref(null); // { match, confidence, message }

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
        const res = await attendanceService.fetchToday();
        if (res.data.status === 'success') {
            todayData.value = res.data.data;
        }
    } catch {
        todayData.value = { state: 'not_checked_in', current_shift: null, shifts: [] };
    }
};

// --- FACE STATUS ---
const fetchFaceStatus = async () => {
    try {
        const res = await attendanceService.fetchFaceStatus();
        if (res.data.status === 'success') {
            faceRegistered.value = res.data.data.registered;
            faceEncodingCount.value = res.data.data.encoding_count;
        }
    } catch {
        faceRegistered.value = null;
    }
};

// --- CHECK-IN ---
const handleCheckIn = async () => {
    if (loading.value) return; // Chốt chặn double-click (phòng khi disabled bị bỏ qua)
    loading.value = true;
    checkInResult.value = null;
    scanResult.value = null;
    scanningPhase.value = 'capture';
    try {
        // Phase 1: Chụp ảnh
        await sleep(400);
        const imageBase64 = captureImage();
        if (!imageBase64) throw new Error("Không thể chụp ảnh, hãy đảm bảo camera hoạt động!");

        const hasFace = await detectFace(imageBase64);
        if (!hasFace) {
            scanningPhase.value = 'error';
            scanResult.value = { match: false, confidence: 0, message: 'Không phát hiện khuôn mặt!' };
            showToast("Không phát hiện khuôn mặt! Vui lòng đưa mặt vào camera rồi thử lại.", "error");
            loading.value = false;
            setTimeout(() => { scanningPhase.value = ''; scanResult.value = null; }, 3000);
            return;
        }

        // Phase 2: GPS
        scanningPhase.value = 'gps';
        const position = await getPosition();

        // Phase 3: Face scanning (gửi lên server xác thực)
        scanningPhase.value = 'face_scan';

        const payload = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            note: attendanceNote.value,
            image: imageBase64,
        };

        const res = await attendanceService.checkIn(payload);

        // Phase 4: Done
        scanningPhase.value = 'done';
        scanResult.value = {
            match: res.data.data?.face_verified ?? true,
            confidence: res.data.data?.face_confidence ?? 100,
            message: 'Xác thực thành công!',
        };
        showToast(res.data.message || 'Check-in thành công!', 'success');
        attendanceNote.value = '';
        checkInResult.value = res.data.data;
        fetchTodayStatus();
        setTimeout(() => { scanningPhase.value = ''; }, 4000);
    } catch (error) {
        scanningPhase.value = 'error';
        const msg = error.response?.data?.message || error.message || "Lỗi Check-in";
        const isFaceFail = error.response?.status === 403;
        scanResult.value = {
            match: false,
            confidence: error.response?.data?.data?.face_confidence ?? 0,
            message: isFaceFail ? 'Khuôn mặt không khớp!' : msg,
        };
        showToast(msg, 'error');
        if (error.response?.data?.data) checkInResult.value = error.response.data.data;
        setTimeout(() => { scanningPhase.value = ''; scanResult.value = null; }, 4000);
    } finally { loading.value = false; }
};

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

// --- CHECK-OUT ---
const handleCheckOut = async () => {
    if (loading.value) return; // Chốt chặn double-click
    loading.value = true;
    scanResult.value = null;
    scanningPhase.value = 'capture';
    try {
        await sleep(400);
        const imageBase64 = captureImage();
        if (!imageBase64) throw new Error("Không thể chụp ảnh!");

        const hasFace = await detectFace(imageBase64);
        if (!hasFace) {
            scanningPhase.value = 'error';
            scanResult.value = { match: false, confidence: 0, message: 'Không phát hiện khuôn mặt!' };
            showToast("Không phát hiện khuôn mặt! Vui lòng đưa mặt vào camera.", "error");
            loading.value = false;
            setTimeout(() => { scanningPhase.value = ''; scanResult.value = null; }, 3000);
            return;
        }

        scanningPhase.value = 'gps';
        const position = await getPosition();

        scanningPhase.value = 'face_scan';
        const res = await attendanceService.checkOut({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            image: imageBase64,
        });

        scanningPhase.value = 'done';
        scanResult.value = { match: true, confidence: 100, message: 'Check-out thành công!' };
        showToast(res.data.message || 'Check-out thành công!', 'success');
        checkInResult.value = null;
        fetchTodayStatus();
        setTimeout(() => { scanningPhase.value = ''; }, 4000);
    } catch (error) {
        scanningPhase.value = 'error';
        scanResult.value = { match: false, confidence: 0, message: error.response?.data?.message || 'Lỗi Check-out' };
        showToast(error.response?.data?.message || error.message || "Lỗi Check-out", 'error');
        setTimeout(() => { scanningPhase.value = ''; scanResult.value = null; }, 4000);
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
const initCamera = async () => {
    try {
        await startCamera();
    } catch {
        showToast("Không thể truy cập camera. Vui lòng cấp quyền!", "error");
    }
};

onMounted(() => { updateClock(); clockInterval = setInterval(updateClock, 1000); initCamera(); fetchTodayStatus(); fetchFaceStatus(); });
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
        <!-- Face Warning -->
        <div v-if="faceRegistered === false" class="card shadow-sm border-0 mb-3 face-warning-card">
          <div class="card-body p-3 d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
            <div>
              <p class="fw-bold mb-1 small">Chưa đăng ký khuôn mặt</p>
              <p class="text-muted mb-2" style="font-size: 0.8rem;">Hệ thống yêu cầu xác thực khuôn mặt khi chấm công.</p>
              <router-link to="/admin/face-register" class="btn btn-warning btn-sm" id="btn-go-face-register">
                <i class="bi bi-person-bounding-box me-1"></i> Đăng ký ngay
              </router-link>
            </div>
          </div>
        </div>

        <div v-if="faceRegistered === true" class="d-flex align-items-center gap-2 mb-3">
          <span class="badge" style="background: #dcfce7; color: #166534; font-size: 0.75rem;">
            <i class="bi bi-shield-fill-check me-1"></i>Face ID: {{ faceEncodingCount }} ảnh
          </span>
        </div>

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
              <div v-if="checkInResult.face_verified !== null && checkInResult.face_verified !== undefined" class="result-item">
                <span class="result-label">Face ID:</span>
                <span class="result-value" :class="checkInResult.face_verified ? 'text-success' : 'text-danger'">
                  {{ checkInResult.face_verified ? '✓ Xác thực' : '✗ Không khớp' }}
                  <span v-if="checkInResult.face_confidence" class="ms-1 opacity-75">({{ checkInResult.face_confidence }}%)</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cột Phải: Camera + Action -->
      <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center">
            <!-- Camera + Face Scan Overlay -->
            <div class="mb-4 w-100 px-3 position-relative camera-box">
              <video ref="videoElement" autoplay playsinline class="w-100 rounded shadow-sm border" style="max-height: 280px; background: #000; object-fit: cover;"></video>
              <canvas ref="canvasElement" style="display: none;"></canvas>

              <!-- Face scan overlay -->
              <Transition name="scan-fade">
                <div v-if="scanningPhase" class="face-scan-overlay">
                  <!-- Scanning animation -->
                  <div v-if="scanningPhase === 'face_scan'" class="scan-animation">
                    <div class="scan-circle">
                      <div class="scan-line"></div>
                    </div>
                    <div class="scan-corners">
                      <span class="corner tl"></span><span class="corner tr"></span>
                      <span class="corner bl"></span><span class="corner br"></span>
                    </div>
                  </div>

                  <!-- Success -->
                  <div v-if="scanningPhase === 'done'" class="scan-result scan-success">
                    <i class="bi bi-shield-fill-check"></i>
                    <span>{{ scanResult?.message || 'Xác thực thành công!' }}</span>
                    <small v-if="scanResult?.confidence">{{ scanResult.confidence }}%</small>
                  </div>

                  <!-- Error -->
                  <div v-if="scanningPhase === 'error'" class="scan-result scan-error">
                    <i class="bi bi-shield-fill-x"></i>
                    <span>{{ scanResult?.message || 'Xác thực thất bại' }}</span>
                  </div>

                  <!-- Phase label -->
                  <div v-if="['capture','gps','face_scan'].includes(scanningPhase)" class="scan-phase-label">
                    <span v-if="scanningPhase === 'capture'"><i class="bi bi-camera-fill me-1"></i>Đang chụp ảnh...</span>
                    <span v-if="scanningPhase === 'gps'"><i class="bi bi-geo-alt-fill me-1"></i>Đang lấy GPS...</span>
                    <span v-if="scanningPhase === 'face_scan'"><i class="bi bi-person-bounding-box me-1"></i>Đang quét khuôn mặt...</span>
                  </div>
                </div>
              </Transition>

              <!-- Default hint -->
              <div v-if="!scanningPhase" class="face-hint">
                <i class="bi bi-person-bounding-box me-1"></i> Đưa mặt vào camera
              </div>
            </div>

            <!-- Note -->
            <div v-if="canCheckIn" class="w-100 mb-4" style="max-width: 420px;">
              <label class="form-label text-start w-100 fw-medium small">Ghi chú (không bắt buộc)</label>
              <textarea v-model="attendanceNote" class="form-control" rows="2" placeholder="Ví dụ: Ca sáng, đã nhận hàng..."></textarea>
            </div>

            <!-- Loading -->
            <div v-if="(loading || gpsLoading) && !scanningPhase" class="mb-3">
              <div class="spinner-border text-primary" role="status"></div>
              <p class="text-muted small mt-2 mb-0">{{ gpsLoading ? 'Đang lấy vị trí GPS...' : 'Đang xử lý...' }}</p>
            </div>

            <!-- Scanning progress steps -->
            <div v-if="scanningPhase && scanningPhase !== 'done' && scanningPhase !== 'error'" class="scan-steps mb-3">
              <div class="scan-step" :class="{ active: scanningPhase === 'capture', done: ['gps','face_scan'].includes(scanningPhase) }">
                <i class="bi" :class="['gps','face_scan'].includes(scanningPhase) ? 'bi-check-circle-fill' : 'bi-camera-fill'"></i>
                <span>Chụp ảnh</span>
              </div>
              <div class="scan-step-line" :class="{ done: ['gps','face_scan'].includes(scanningPhase) }"></div>
              <div class="scan-step" :class="{ active: scanningPhase === 'gps', done: scanningPhase === 'face_scan' }">
                <i class="bi" :class="scanningPhase === 'face_scan' ? 'bi-check-circle-fill' : 'bi-geo-alt-fill'"></i>
                <span>GPS</span>
              </div>
              <div class="scan-step-line" :class="{ done: scanningPhase === 'face_scan' }"></div>
              <div class="scan-step" :class="{ active: scanningPhase === 'face_scan' }">
                <i class="bi bi-person-bounding-box"></i>
                <span>Face ID</span>
              </div>
            </div>

            <!-- Buttons -->
            <div v-else class="w-100 d-flex flex-column align-items-center gap-3">
              <!-- CHECK-IN -->
              <button v-if="canCheckIn" @click="handleCheckIn" id="btn-check-in"
                :disabled="loading"
                class="btn btn-checkin-action d-flex align-items-center justify-content-center py-3 px-5 rounded-pill fw-bold shadow"
                style="min-width: 240px; font-size: 1.1rem;">
                <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                <i v-else class="bi bi-box-arrow-in-right me-2 fs-4"></i>
                {{ loading ? 'ĐANG XỬ LÝ...' : `CHECK-IN ${currentShift?.name?.toUpperCase() || ''}` }}
              </button>

              <!-- CHECK-OUT -->
              <div v-if="canCheckOut" class="text-center">
                <p class="text-muted small mb-2">
                  Đang làm <strong>{{ currentCheckedInShift?.shift_name }}</strong>
                  — check-in lúc <strong>{{ formatTime(currentCheckedInShift?.attendance?.check_in_at) }}</strong>
                </p>
                <button @click="handleCheckOut" id="btn-check-out"
                  :disabled="loading"
                  class="btn btn-checkout-action d-flex align-items-center justify-content-center py-3 px-5 rounded-pill fw-bold shadow"
                  style="min-width: 240px; font-size: 1.1rem;">
                  <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                  <i v-else class="bi bi-box-arrow-left me-2 fs-4"></i>
                  {{ loading ? 'ĐANG XỬ LÝ...' : 'CHECK-OUT' }}
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
              <i class="bi bi-shield-lock me-1"></i> GPS + ảnh selfie + Face ID được thu thập tự động.
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
.shift-badge-off { background: #f3f4f6; color: var(--text-muted); }

.shift-status-row { background: var(--surface-container); transition: all 0.2s; }
.shift-status-row:hover { background: #f0f2f5; }
.shift-current { background: #eef2ff !important; border: 1px solid #c7d2fe; }

.result-grid { display: flex; flex-direction: column; gap: 8px; }
.result-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6; }
.result-item:last-child { border-bottom: none; }
.result-label { color: var(--text-muted); font-size: 0.9rem; }
.result-value { font-size: 0.9rem; }

.camera-box { border-radius: 16px; overflow: hidden; }
.face-hint { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; z-index: 2; }

/* Face scan overlay */
.face-scan-overlay {
  position: absolute; inset: 0; z-index: 10;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  background: rgba(0,0,0,0.45);
  border-radius: 8px;
}
.scan-fade-enter-active { transition: opacity 0.3s; }
.scan-fade-leave-active { transition: opacity 0.2s; }
.scan-fade-enter-from, .scan-fade-leave-to { opacity: 0; }

/* Scanning circle + line */
.scan-animation { position: relative; width: 180px; height: 220px; }
.scan-circle {
  width: 100%; height: 100%;
  border: 3px solid rgba(59,130,246,0.6);
  border-radius: 50%;
  position: relative; overflow: hidden;
  animation: scan-pulse 1.5s ease-in-out infinite;
}
@keyframes scan-pulse {
  0%, 100% { border-color: rgba(59,130,246,0.4); box-shadow: 0 0 0 0 rgba(59,130,246,0); }
  50% { border-color: rgba(59,130,246,0.9); box-shadow: 0 0 20px 4px rgba(59,130,246,0.3); }
}
.scan-line {
  position: absolute; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, transparent, #3b82f6, transparent);
  animation: scan-sweep 1.8s ease-in-out infinite;
}
@keyframes scan-sweep {
  0% { top: 10%; opacity: 0; }
  10% { opacity: 1; }
  90% { opacity: 1; }
  100% { top: 90%; opacity: 0; }
}
.scan-corners { position: absolute; inset: -6px; }
.corner { position: absolute; width: 24px; height: 24px; border-color: #3b82f6; border-style: solid; border-width: 0; }
.corner.tl { top: 0; left: 0; border-top-width: 3px; border-left-width: 3px; border-top-left-radius: 8px; }
.corner.tr { top: 0; right: 0; border-top-width: 3px; border-right-width: 3px; border-top-right-radius: 8px; }
.corner.bl { bottom: 0; left: 0; border-bottom-width: 3px; border-left-width: 3px; border-bottom-left-radius: 8px; }
.corner.br { bottom: 0; right: 0; border-bottom-width: 3px; border-right-width: 3px; border-bottom-right-radius: 8px; }

/* Scan results */
.scan-result {
  display: flex; flex-direction: column; align-items: center; gap: 6px; color: white;
  animation: result-pop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes result-pop {
  0% { transform: scale(0.5); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
.scan-result i { font-size: 3rem; }
.scan-result span { font-size: 1rem; font-weight: 600; }
.scan-result small { font-size: 0.85rem; opacity: 0.8; }
.scan-success i { color: #34d399; }
.scan-error i { color: #f87171; }

.scan-phase-label {
  position: absolute; bottom: 16px;
  background: rgba(0,0,0,0.7); color: white;
  padding: 6px 18px; border-radius: 20px;
  font-size: 0.8rem; font-weight: 500;
}

/* Scan steps progress */
.scan-steps {
  display: flex; align-items: center; justify-content: center; gap: 0;
}
.scan-step {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  color: var(--text-light); font-size: 0.7rem; font-weight: 600;
  transition: all 0.3s; min-width: 60px;
}
.scan-step i { font-size: 1.2rem; }
.scan-step.active { color: #3b82f6; }
.scan-step.active i { animation: step-bounce 0.6s ease; }
@keyframes step-bounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.3); } }
.scan-step.done { color: #10b981; }
.scan-step-line { width: 30px; height: 2px; background: #e2e8f0; margin: 0 4px; margin-bottom: 18px; transition: background 0.3s; }
.scan-step-line.done { background: #10b981; }

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

.face-warning-card { border: 1px solid #fbbf24 !important; background: #fffbeb; animation: face-warn-pulse 3s ease-in-out infinite; }
@keyframes face-warn-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(251,191,36,0); } 50% { box-shadow: 0 0 0 4px rgba(251,191,36,0.2); } }
</style>
