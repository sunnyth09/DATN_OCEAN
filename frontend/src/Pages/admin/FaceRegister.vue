<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import api from '../../axios';
import { getAbsoluteUrl } from '@/utils/url';

// --- TOAST ---
const toastVisible = ref(false);
let toastTimer = null;
const toast = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  toastVisible.value = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastVisible.value = false; }, 5000);
};

// --- CAMERA ---
const videoElement = ref(null);
const canvasElement = ref(null);
let videoStream = null;

const startCamera = async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
    });
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
  return canvas.toDataURL('image/jpeg', 0.85);
};

// --- STATE ---
const loading = ref(false);
const faceStatus = ref(null); // { registered, encoding_count, encodings }
const capturedPhotos = ref([]); // [{ image: base64, label: 'front' }, ...]
const isRegistering = ref(false);

const labels = [
  { key: 'front', name: 'Mặt thẳng', icon: '😊', instruction: 'Nhìn thẳng vào camera' },
  { key: 'left', name: 'Nghiêng trái', icon: '😏', instruction: 'Xoay đầu nhẹ sang trái' },
  { key: 'right', name: 'Nghiêng phải', icon: '😌', instruction: 'Xoay đầu nhẹ sang phải' },
];

const currentStep = computed(() => capturedPhotos.value.length);
const currentLabel = computed(() => labels[currentStep.value] || null);
const allCaptured = computed(() => capturedPhotos.value.length >= labels.length);
const isRegistered = computed(() => faceStatus.value?.registered === true);

// --- FETCH STATUS ---
const fetchFaceStatus = async () => {
  try {
    const res = await api.get('/admin/face/status');
    if (res.data.status === 'success') {
      faceStatus.value = res.data.data;
    }
  } catch {
    faceStatus.value = null;
  }
};

// --- CAPTURE PHOTO ---
const handleCapture = () => {
  if (allCaptured.value) return;
  const image = captureImage();
  if (!image) {
    showToast('Không thể chụp ảnh. Hãy kiểm tra camera!', 'error');
    return;
  }
  capturedPhotos.value.push({
    image,
    label: currentLabel.value?.key || `photo_${currentStep.value + 1}`,
  });
  showToast(`Đã chụp "${currentLabel.value?.name}" ✓`, 'success');
};

// --- REMOVE PHOTO ---
const removePhoto = (index) => {
  capturedPhotos.value.splice(index);
};

// --- REGISTER ---
const handleRegister = async () => {
  if (capturedPhotos.value.length < 1) {
    showToast('Vui lòng chụp ít nhất 1 ảnh!', 'error');
    return;
  }

  isRegistering.value = true;
  try {
    const res = await api.post('/admin/face/register', {
      images: capturedPhotos.value,
    });

    if (res.data.status === 'success') {
      showToast(res.data.message || 'Đăng ký khuôn mặt thành công!', 'success');
      capturedPhotos.value = [];
      await fetchFaceStatus();
    } else {
      showToast(res.data.message || 'Có lỗi xảy ra.', 'error');
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Lỗi đăng ký khuôn mặt.', 'error');
  } finally {
    isRegistering.value = false;
  }
};

// --- RESET ---
const handleReset = async () => {
  if (!confirm('Bạn có chắc muốn xóa toàn bộ ảnh đã đăng ký và đăng ký lại?')) return;

  loading.value = true;
  try {
    const res = await api.post('/admin/face/reset');
    if (res.data.status === 'success') {
      showToast('Đã xóa. Bạn có thể đăng ký lại.', 'success');
      capturedPhotos.value = [];
      await fetchFaceStatus();
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Lỗi xóa.', 'error');
  } finally {
    loading.value = false;
  }
};

// --- LIFECYCLE ---
onMounted(() => { startCamera(); fetchFaceStatus(); });
onUnmounted(() => { stopCamera(); });
</script>

<template>
  <div class="face-register-container p-4">
    <!-- Header -->
    <div class="fr-header">
      <div>
        <h2 class="h4 mb-1 fw-bold text-gray-800">
          <span class="text-primary me-1">#</span> Đăng Ký Khuôn Mặt
        </h2>
        <p class="text-muted small mb-0">Xác thực sinh trắc học cho chấm công</p>
      </div>
      <div v-if="isRegistered" class="status-badge status-badge--active">
        <i class="bi bi-shield-fill-check me-1"></i> Đã đăng ký ({{ faceStatus?.encoding_count }} ảnh)
      </div>
      <div v-else class="status-badge status-badge--inactive">
        <i class="bi bi-shield-fill-exclamation me-1"></i> Chưa đăng ký
      </div>
    </div>

    <!-- Đã đăng ký — Hiện ảnh đã lưu -->
    <div v-if="isRegistered" class="registered-section mt-4">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3">
            <i class="bi bi-person-check me-2 text-success"></i>Ảnh đã đăng ký
          </h6>
          <div class="registered-photos">
            <div v-for="enc in faceStatus.encodings" :key="enc.id" class="registered-photo-card">
              <img v-if="enc.image_path" :src="getAbsoluteUrl(enc.image_path)" alt="face" class="registered-photo-img" />
              <div class="registered-photo-label">{{ enc.label }}</div>
              <div class="registered-photo-date">{{ enc.created_at }}</div>
            </div>
          </div>

          <div class="mt-4 d-flex gap-3">
            <button @click="handleReset" class="btn btn-outline-danger btn-sm" :disabled="loading" id="btn-reset-face">
              <i class="bi bi-arrow-counterclockwise me-1"></i>
              Xóa và đăng ký lại
            </button>
            <router-link to="/admin/attendance" class="btn btn-primary btn-sm" id="btn-goto-attendance">
              <i class="bi bi-clock me-1"></i>
              Đi chấm công
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Chưa đăng ký — Form chụp ảnh -->
    <div v-else class="register-section mt-4">
      <div class="row g-4">
        <!-- Cột trái: Camera + Hướng dẫn -->
        <div class="col-lg-7">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4 d-flex flex-column align-items-center">
              <!-- Camera Preview -->
              <div class="camera-wrapper mb-3">
                <video ref="videoElement" autoplay playsinline class="camera-video"></video>
                <canvas ref="canvasElement" style="display: none;"></canvas>

                <!-- Face guide overlay -->
                <div class="face-guide-overlay">
                  <div class="face-circle"></div>
                </div>

                <!-- Current step instruction -->
                <div v-if="currentLabel" class="capture-instruction">
                  <span class="capture-emoji">{{ currentLabel.icon }}</span>
                  {{ currentLabel.instruction }}
                </div>
                <div v-else class="capture-instruction capture-done">
                  <i class="bi bi-check-circle me-1"></i> Đã chụp đủ ảnh!
                </div>
              </div>

              <!-- Progress steps -->
              <div class="step-indicators mb-3">
                <div v-for="(label, i) in labels" :key="label.key"
                     class="step-dot"
                     :class="{ 'step-done': i < currentStep, 'step-active': i === currentStep }">
                  <span v-if="i < currentStep" class="step-check">✓</span>
                  <span v-else>{{ i + 1 }}</span>
                </div>
              </div>

              <!-- Capture button -->
              <button v-if="!allCaptured" @click="handleCapture"
                class="btn btn-capture d-flex align-items-center justify-content-center"
                id="btn-capture-face">
                <i class="bi bi-camera-fill me-2 fs-5"></i>
                Chụp {{ currentLabel?.name }}
              </button>

              <!-- Register button -->
              <button v-else @click="handleRegister" :disabled="isRegistering"
                class="btn btn-register d-flex align-items-center justify-content-center"
                id="btn-register-face">
                <span v-if="isRegistering" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="bi bi-shield-fill-check me-2 fs-5"></i>
                {{ isRegistering ? 'Đang xử lý...' : 'Hoàn tất đăng ký' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Cột phải: Ảnh đã chụp + Hướng dẫn -->
        <div class="col-lg-5">
          <!-- Ảnh đã chụp -->
          <div class="card shadow-sm border-0 mb-3">
            <div class="card-body p-4">
              <h6 class="fw-bold mb-3">
                <i class="bi bi-images me-2 text-primary"></i>Ảnh đã chụp ({{ capturedPhotos.length }}/{{ labels.length }})
              </h6>
              <div v-if="capturedPhotos.length === 0" class="text-muted small text-center py-3">
                Chưa có ảnh nào. Hãy chụp ảnh theo hướng dẫn.
              </div>
              <div v-else class="captured-photos-grid">
                <div v-for="(photo, i) in capturedPhotos" :key="i" class="captured-photo-item">
                  <img :src="photo.image" alt="captured" class="captured-photo-img" />
                  <div class="captured-photo-overlay">
                    <span class="captured-photo-label">{{ labels[i]?.name }}</span>
                    <button @click="removePhoto(i)" class="captured-photo-remove" title="Xóa ảnh này và ảnh sau">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Hướng dẫn -->
          <div class="card shadow-sm border-0">
            <div class="card-body p-4">
              <h6 class="fw-bold mb-3">
                <i class="bi bi-info-circle me-2 text-info"></i>Hướng dẫn
              </h6>
              <ul class="guide-list">
                <li><i class="bi bi-check2 text-success me-2"></i>Đảm bảo ánh sáng đủ, không bị tối</li>
                <li><i class="bi bi-check2 text-success me-2"></i>Không đeo kính râm, khẩu trang</li>
                <li><i class="bi bi-check2 text-success me-2"></i>Đưa mặt vào vòng tròn hướng dẫn</li>
                <li><i class="bi bi-check2 text-success me-2"></i>Chụp 3 ảnh: mặt thẳng, nghiêng trái, nghiêng phải</li>
                <li><i class="bi bi-shield-fill-check text-primary me-2"></i>Ảnh được mã hóa và lưu an toàn</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="fr-toast">
      <div v-if="toastVisible" class="fr-toast" :class="'fr-toast-' + toast.type">{{ toast.message }}</div>
    </Transition>
  </div>
</template>

<style scoped>
.face-register-container { max-width: 1100px; margin: 0 auto; }
.fr-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }

.status-badge {
  padding: 8px 18px;
  border-radius: 50px;
  font-weight: 600;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
}
.status-badge--active { background: #dcfce7; color: #166534; }
.status-badge--inactive { background: #fef3c7; color: #92400e; }

/* Camera */
.camera-wrapper {
  position: relative;
  width: 100%;
  max-width: 480px;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.camera-video {
  width: 100%;
  aspect-ratio: 4/3;
  background: #000;
  object-fit: cover;
  display: block;
}
.face-guide-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.face-circle {
  width: 200px;
  height: 260px;
  border: 3px dashed rgba(255,255,255,0.5);
  border-radius: 50%;
  animation: pulse-ring 2s ease-in-out infinite;
}
@keyframes pulse-ring {
  0%, 100% { border-color: rgba(255,255,255,0.3); transform: scale(1); }
  50% { border-color: rgba(255,255,255,0.7); transform: scale(1.02); }
}
.capture-instruction {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0,0,0,0.7);
  color: white;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
}
.capture-emoji { font-size: 1.1rem; }
.capture-done { background: rgba(16,185,129,0.8); }

/* Step indicators */
.step-indicators {
  display: flex;
  gap: 12px;
}
.step-dot {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--surface-container);
  color: var(--text-light);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.85rem;
  transition: all 0.3s;
}
.step-done { background: #10b981; color: white; }
.step-active { background: #3b82f6; color: white; box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
.step-check { font-size: 1rem; }

/* Buttons */
.btn-capture {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  border: none;
  padding: 14px 36px;
  border-radius: 50px;
  font-weight: 600;
  font-size: 1rem;
  transition: all 0.3s;
  box-shadow: 0 4px 15px rgba(59,130,246,0.3);
}
.btn-capture:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(59,130,246,0.4);
  color: white;
}
.btn-register {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border: none;
  padding: 14px 36px;
  border-radius: 50px;
  font-weight: 600;
  font-size: 1rem;
  transition: all 0.3s;
  box-shadow: 0 4px 15px rgba(16,185,129,0.3);
}
.btn-register:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(16,185,129,0.4);
  color: white;
}

/* Captured photos grid */
.captured-photos-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}
.captured-photo-item {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.captured-photo-img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  display: block;
}
.captured-photo-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(transparent, rgba(0,0,0,0.7));
  padding: 24px 8px 6px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}
.captured-photo-label {
  color: white;
  font-size: 0.7rem;
  font-weight: 600;
}
.captured-photo-remove {
  background: rgba(239,68,68,0.8);
  border: none;
  color: white;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  cursor: pointer;
  transition: background 0.2s;
}
.captured-photo-remove:hover { background: #ef4444; }

/* Registered photos */
.registered-photos {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.registered-photo-card {
  text-align: center;
  width: 120px;
}
.registered-photo-img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
}
.registered-photo-label {
  font-weight: 600;
  font-size: 0.8rem;
  color: #334155;
  margin-top: 6px;
}
.registered-photo-date {
  font-size: 0.7rem;
  color: var(--text-light);
}

/* Guide */
.guide-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.guide-list li {
  padding: 6px 0;
  font-size: 0.875rem;
  color: #475569;
  display: flex;
  align-items: center;
}

/* Toast */
.fr-toast {
  position: fixed;
  bottom: 30px;
  right: 30px;
  padding: 14px 28px;
  border-radius: 12px;
  color: white;
  font-weight: 500;
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  z-index: 9999;
  max-width: 450px;
}
.fr-toast-success { background: linear-gradient(135deg, #10b981, #059669); }
.fr-toast-error { background: linear-gradient(135deg, #ef4444, #dc2626); }
.fr-toast-enter-active { transition: all 0.3s ease; }
.fr-toast-leave-active { transition: all 0.2s ease; }
.fr-toast-enter-from, .fr-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
