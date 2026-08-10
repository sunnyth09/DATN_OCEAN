<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
  toastId: {
    type: String,
    default: 'appToast',
  },
});

const { toast } = useToast(props.toastId);

// Lấy màu và icon dựa trên loại thông báo
const toastState = computed(() => {
  switch (toast.value.type) {
    case 'error':
    case 'danger':
      return {
        color: '#ef4444',
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`,
        title: 'Lỗi'
      };
    case 'warning':
      return {
        color: '#f59e0b',
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`,
        title: 'Cảnh báo'
      };
    case 'info':
      return {
        color: '#3b82f6',
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`,
        title: 'Thông tin'
      };
    default: // success
      return {
        color: '#22c55e',
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`,
        title: 'Thành công'
      };
  }
});

// Xử lý logic progress bar
const progress = ref(100);
let animationFrameId = null;
let startTime = 0;
const DURATION = 3000; // Khớp với delay mặc định của bootstrap toast

const animateProgress = (timestamp) => {
  if (!startTime) startTime = timestamp;
  const elapsed = timestamp - startTime;
  
  progress.value = Math.max(0, 100 - (elapsed / DURATION) * 100);
  
  if (elapsed < DURATION) {
    animationFrameId = requestAnimationFrame(animateProgress);
  }
};

const startProgress = () => {
  progress.value = 100;
  startTime = 0;
  if (animationFrameId) cancelAnimationFrame(animationFrameId);
  animationFrameId = requestAnimationFrame(animateProgress);
};

// Theo dõi khi có message mới để chạy progress bar
watch(() => toast.value.message, (newMsg) => {
  if (newMsg) {
    startProgress();
  }
});

onUnmounted(() => {
  if (animationFrameId) cancelAnimationFrame(animationFrameId);
});

</script>

<template>
  <div class="toast-container position-fixed app-toast-host">
    <div
      :id="toastId"
      class="toast border-0 custom-toast"
      role="alert"
      aria-live="assertive"
      aria-atomic="true"
    >
      <div class="toast-content-wrapper">
        <!-- Icon Section -->
        <div class="toast-icon" :style="{ color: toastState.color }" v-html="toastState.icon"></div>
        
        <!-- Text Section -->
        <div class="toast-text">
          <span class="toast-title" :style="{ color: toastState.color }">{{ toastState.title }}</span>
          <span class="toast-message">{{ toast.message }}</span>
        </div>
        
        <!-- Close Button -->
        <button
          type="button"
          class="toast-close"
          data-bs-dismiss="toast"
          aria-label="Close"
        >
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      
      <!-- Progress Bar -->
      <div class="toast-progress-track">
        <div class="toast-progress-bar" :style="{ width: progress + '%', backgroundColor: toastState.color }"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.app-toast-host {
  top: 85px;
  right: 20px;
  left: auto;
  padding: 0;
  z-index: 1080;
}

/* Override Bootstrap Toast Default Styles */
.custom-toast {
  background: #ffffff !important;
  border-radius: 12px !important;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 10px rgba(0, 0, 0, 0.05) !important;
  width: auto;
  min-width: 300px;
  max-width: 400px;
  overflow: hidden; /* For progress bar border radius */
  position: relative;
  padding: 0 !important;
  font-family: inherit;
}

/* Cấu trúc phần tử con */
.toast-content-wrapper {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  gap: 14px;
}

.toast-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.toast-icon :deep(svg) {
  width: 100%;
  height: 100%;
}

.toast-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.toast-title {
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.2;
}

.toast-message {
  font-size: 0.9rem;
  color: #475569;
  line-height: 1.4;
}

.toast-close {
  flex-shrink: 0;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  margin-left: auto;
}

.toast-close:hover {
  background: #f1f5f9;
  color: #64748b;
}

/* Progress bar */
.toast-progress-track {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: #f1f5f9;
}

.toast-progress-bar {
  height: 100%;
  transition: width 0.1s linear;
}

/* Bootstrap Animation Overrides (Optional - Bootstrap handles fade in/out via opacity, we can add a slight slide effect) */
.toast.showing {
  animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
</style>
