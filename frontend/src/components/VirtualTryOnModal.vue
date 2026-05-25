<script setup>
import { ref, watch, onBeforeUnmount, shallowRef, nextTick } from 'vue';
import { FilesetResolver, PoseLandmarker } from '@mediapipe/tasks-vision';

const props = defineProps({
  imageUrl: { type: String, required: true },
  show: { type: Boolean, default: false }
});

const emit = defineEmits(['close']);

// Refs
const videoRef = ref(null);
const canvasRef = ref(null);
const imageElRef = ref(null);

// State
const stream = ref(null);
const hasCameraError = ref(false);
const errorMessage = ref('');
const isUsingFile = ref(false);
const uploadedImage = ref(null);
const isTracking = ref(false);
const isModelLoading = ref(true);

// MediaPipe
const poseLandmarker = shallowRef(null);
let animationFrameId = null;
let lastVideoTime = -1;

// Image dimensions for overlay
const productImg = new Image();

// Manual Fallback State (when AI fails to detect)
const isManualMode = ref(false);
const positionX = ref(0);
const positionY = ref(0);
const scale = ref(1.5);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);

// Watch for product image changes
watch(() => props.imageUrl, (newUrl) => {
  if (newUrl) {
    productImg.onload = () => {
      if (isUsingFile.value) processStaticImage();
    };
    productImg.onerror = (err) => {
      console.error("Không thể tải ảnh sản phẩm:", err);
    };
    productImg.src = newUrl;
  }
}, { immediate: true });

// Initialize MediaPipe Model
const initModel = async () => {
  try {
    isModelLoading.value = true;
    const vision = await FilesetResolver.forVisionTasks(
      "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/wasm"
    );
    poseLandmarker.value = await PoseLandmarker.createFromOptions(vision, {
      baseOptions: {
        modelAssetPath: `https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task`,
        delegate: "GPU"
      },
      runningMode: "VIDEO",
      numPoses: 1
    });
    isModelLoading.value = false;
  } catch (error) {
    console.error("Lỗi tải AI Model:", error);
    errorMessage.value = "Không thể tải AI Model. Chuyển sang chế độ thủ công.";
    isModelLoading.value = false;
    isManualMode.value = true;
  }
};

// Start Camera
const startCamera = async () => {
  try {
    hasCameraError.value = false;
    errorMessage.value = '';
    isUsingFile.value = false;
    uploadedImage.value = null;
    isManualMode.value = false;
    isTracking.value = true;
    
    if (!poseLandmarker.value) await initModel();

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error("Trình duyệt không hỗ trợ camera.");
    }

    stream.value = await navigator.mediaDevices.getUserMedia({ 
      video: { facingMode: 'user', width: 640, height: 480 } 
    });
    
    if (videoRef.value) {
      videoRef.value.srcObject = stream.value;
      videoRef.value.addEventListener('loadeddata', predictWebcam);
    }
  } catch (err) {
    console.error("Camera error:", err);
    hasCameraError.value = true;
    errorMessage.value = "Không thể truy cập camera: " + err.message;
    isTracking.value = false;
  }
};

const stopCamera = () => {
  isTracking.value = false;
  if (animationFrameId) cancelAnimationFrame(animationFrameId);
  if (stream.value) {
    stream.value.getTracks().forEach(track => track.stop());
    stream.value = null;
  }
};

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = async (e) => {
    stopCamera();
    isUsingFile.value = true;
    uploadedImage.value = e.target.result;
    hasCameraError.value = false;
    isManualMode.value = false;
    isTracking.value = true;
    
    if (!poseLandmarker.value) await initModel();
    
    // Process single image
    nextTick(() => {
      if (imageElRef.value && canvasRef.value) {
        imageElRef.value.onload = () => {
          processStaticImage();
        };
      }
    });
  };
  reader.readAsDataURL(file);
};

// Process AI for Video
const predictWebcam = async () => {
  if (!isTracking.value || !videoRef.value || !canvasRef.value) return;
  const canvas = canvasRef.value;
  const ctx = canvas.getContext("2d");
  const video = videoRef.value;

  if (canvas.width !== video.videoWidth) {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
  }

  let startTimeMs = performance.now();
  let results = null;

  if (lastVideoTime !== video.currentTime) {
    lastVideoTime = video.currentTime;
    // Set running mode to VIDEO before detecting
    await poseLandmarker.value.setOptions({ runningMode: "VIDEO" });
    results = poseLandmarker.value.detectForVideo(video, startTimeMs);
  }

  renderCanvas(ctx, canvas, video, results, true);

  if (isTracking.value && !isUsingFile.value) {
    animationFrameId = requestAnimationFrame(predictWebcam);
  }
};

// Process AI for Static Image
const processStaticImage = async () => {
  if (!imageElRef.value || !canvasRef.value) return;
  const canvas = canvasRef.value;
  const ctx = canvas.getContext("2d");
  const img = imageElRef.value;

  canvas.width = img.naturalWidth;
  canvas.height = img.naturalHeight;

  // Set running mode to IMAGE for static files
  await poseLandmarker.value.setOptions({ runningMode: "IMAGE" });
  const results = poseLandmarker.value.detect(img);
  
  renderCanvas(ctx, canvas, img, results, false);
};

// Main Render Function
const renderCanvas = (ctx, canvas, source, results, isMirrored) => {
  ctx.save();
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  
  if (isMirrored) {
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
  }
  
  // Draw original video/image
  ctx.drawImage(source, 0, 0, canvas.width, canvas.height);

  let hasBody = false;

  if (results && results.landmarks && results.landmarks.length > 0 && !isManualMode.value) {
    const landmarks = results.landmarks[0];
    
    // Check if shoulders are visible
    const leftShoulder = landmarks[11]; // MediaPipe considers 11 as Left, 12 as Right from the person's perspective
    const rightShoulder = landmarks[12];
    const leftHip = landmarks[23];
    const rightHip = landmarks[24];

    if (leftShoulder && rightShoulder && leftShoulder.visibility > 0.5 && rightShoulder.visibility > 0.5) {
      hasBody = true;
      
      const pLeftShoulder = { x: leftShoulder.x * canvas.width, y: leftShoulder.y * canvas.height };
      const pRightShoulder = { x: rightShoulder.x * canvas.width, y: rightShoulder.y * canvas.height };
      
      // Calculate Shoulder Width
      // Chú ý: Điểm 11 (Left Shoulder của người) nằm bên PHẢI bức ảnh (X lớn)
      // Điểm 12 (Right Shoulder của người) nằm bên TRÁI bức ảnh (X nhỏ)
      // Vector từ Trái sang Phải màn hình phải là: 11 - 12
      const dx = pLeftShoulder.x - pRightShoulder.x;
      const dy = pLeftShoulder.y - pRightShoulder.y;
      const shoulderWidth = Math.sqrt(dx * dx + dy * dy);
      
      // Calculate Midpoint
      const midX = (pLeftShoulder.x + pRightShoulder.x) / 2;
      let midY = (pLeftShoulder.y + pRightShoulder.y) / 2;
      
      // If hips are visible, use them to adjust Y position lower and calculate height better
      if (leftHip && rightHip && leftHip.visibility > 0.5 && rightHip.visibility > 0.5) {
        const pHipMidY = ((leftHip.y + rightHip.y) / 2) * canvas.height;
        midY = midY + (pHipMidY - midY) * 0.4; // Anchor around the chest
      } else {
         // Fallback if no hips: just shift down based on shoulder width
         midY += shoulderWidth * 0.5;
      }

      const angle = Math.atan2(dy, dx);
      
      // Apply product scale factor relative to shoulder width
      // Typically a shirt is about 1.8x to 2x wider than the distance between shoulder joints
      const productScale = 1.8 * scale.value; 
      const drawWidth = shoulderWidth * productScale;
      
      // Preserve aspect ratio of product image
      const imgAspect = productImg.width / productImg.height || 1;
      const drawHeight = drawWidth / imgAspect;

      ctx.save();
      ctx.translate(midX, midY);
      
      // In mirror mode, the angle needs to be inverted for correct tilting
      if (isMirrored) {
         ctx.rotate(-angle);
      } else {
         ctx.rotate(angle);
      }
      
      ctx.globalCompositeOperation = "multiply"; // Better blending for white backgrounds
      ctx.drawImage(productImg, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
      ctx.restore();
    }
  }

  // Draw manual fallback if no body detected
  if (!hasBody && productImg.src && productImg.complete) {
    if (!isManualMode.value && !isUsingFile.value) {
       // Only show warning if not intentionally in manual mode
       ctx.font = "16px Arial";
       ctx.fillStyle = "white";
       ctx.textAlign = "center";
       ctx.save();
       if(isMirrored) { ctx.scale(-1, 1); ctx.fillText("Đứng xa khung hình để AI nhận diện 2 vai", -canvas.width/2, 30); }
       else { ctx.fillText("Đứng xa khung hình để AI nhận diện 2 vai", canvas.width/2, 30); }
       ctx.restore();
    }
    
    // Draw product manually
    const drawWidth = 200 * scale.value;
    const imgAspect = productImg.width / productImg.height || 1;
    const drawHeight = drawWidth / imgAspect;
    
    ctx.save();
    // Revert mirror for manual drawing so dragging isn't inverted
    if (isMirrored) {
      ctx.scale(-1, 1);
      ctx.translate(-canvas.width/2 + positionX.value, canvas.height/2 + positionY.value);
    } else {
      ctx.translate(canvas.width/2 + positionX.value, canvas.height/2 + positionY.value);
    }
    
    ctx.globalCompositeOperation = "multiply";
    ctx.drawImage(productImg, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
    ctx.restore();
  }

  ctx.restore();
};

// Manual Drag controls (for fallback)
const startDrag = (e) => {
  e.preventDefault();
  isDragging.value = true;
  const clientX = e.touches ? e.touches[0].clientX : e.clientX;
  const clientY = e.touches ? e.touches[0].clientY : e.clientY;
  
  startX.value = clientX - positionX.value;
  startY.value = clientY - positionY.value;
  
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', stopDrag);
  document.addEventListener('touchmove', onDrag, { passive: false });
  document.addEventListener('touchend', stopDrag);
};

const onDrag = (e) => {
  if (!isDragging.value) return;
  e.preventDefault();
  const clientX = e.touches ? e.touches[0].clientX : e.clientX;
  const clientY = e.touches ? e.touches[0].clientY : e.clientY;
  
  positionX.value = clientX - startX.value;
  positionY.value = clientY - startY.value;
  
  if (isUsingFile.value && isManualMode.value) {
     processStaticImage(); // Re-render for static image
  }
};

const stopDrag = () => {
  isDragging.value = false;
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', stopDrag);
  document.removeEventListener('touchmove', onDrag);
  document.removeEventListener('touchend', stopDrag);
};

const zoomIn = () => { scale.value += 0.1; if(isUsingFile.value) processStaticImage(); };
const zoomOut = () => { if (scale.value > 0.2) scale.value -= 0.1; if(isUsingFile.value) processStaticImage(); };

const toggleManualMode = () => {
  isManualMode.value = !isManualMode.value;
  positionX.value = 0;
  positionY.value = 0;
  if(isUsingFile.value) processStaticImage();
};

const closeModal = () => {
  stopCamera();
  emit('close');
};

watch(() => props.show, (newVal) => {
  if (newVal) {
    scale.value = 1.5;
    positionX.value = 0;
    positionY.value = 0;
    setTimeout(() => startCamera(), 100);
  } else {
    stopCamera();
  }
});

onBeforeUnmount(() => {
  stopCamera();
  stopDrag();
  if (poseLandmarker.value) {
    poseLandmarker.value.close();
  }
});
</script>

<template>
  <teleport to="body">
    <transition name="modal-fade">
      <div v-if="show" class="tryon-overlay" @click.self="closeModal">
        <div class="tryon-modal">
          
          <div class="tryon-header">
            <h3 class="tryon-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path><circle cx="12" cy="13" r="3"></circle></svg>
              AI Virtual Try-On
              <span v-if="isModelLoading" class="badge loading">Đang tải AI...</span>
              <span v-else-if="!isManualMode" class="badge active">AI Tracking ON</span>
              <span v-else class="badge manual">Manual Mode</span>
            </h3>
            <button class="tryon-close" @click="closeModal" title="Đóng">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="tryon-body">
            
            <div v-if="hasCameraError" class="tryon-error">
              <p>{{ errorMessage }}</p>
              <label class="tryon-btn-upload">
                Tải ảnh lên
                <input type="file" accept="image/*" @change="handleFileUpload" hidden />
              </label>
              <button class="tryon-btn-retry" @click="startCamera">Thử lại Camera</button>
            </div>

            <!-- Viewport -->
            <div class="tryon-viewport" v-show="!hasCameraError">
              <!-- Hidden Video for AI Processing -->
              <video ref="videoRef" playsinline class="hidden"></video>
              <!-- Hidden Image for Static AI Processing -->
              <img ref="imageElRef" v-if="isUsingFile" :src="uploadedImage" class="hidden" />
              
              <!-- Main Canvas Display -->
              <canvas 
                ref="canvasRef" 
                class="tryon-canvas"
                @mousedown="startDrag"
                @touchstart="startDrag"
              ></canvas>

              <div v-if="isModelLoading" class="loading-overlay">
                <div class="spinner"></div>
                <p>Đang tải mô hình AI (khoảng 5MB lần đầu)...</p>
              </div>
            </div>

          </div>

          <!-- Controls -->
          <div class="tryon-controls" v-if="!hasCameraError">
            <div class="tryon-controls-group">
              <button class="tryon-ctrl-btn" @click="zoomOut" title="Thu nhỏ (Scale áo)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
              </button>
              <span class="tryon-scale-label">{{ Math.round(scale * 100) }}%</span>
              <button class="tryon-ctrl-btn" @click="zoomIn" title="Phóng to (Scale áo)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
              </button>
            </div>

            <button class="tryon-ctrl-btn text-btn" :class="{ 'active-mode': isManualMode }" @click="toggleManualMode">
              {{ isManualMode ? 'Bật lại AI' : 'Kéo thả thủ công' }}
            </button>

            <label class="tryon-ctrl-btn text-btn upload-icon" title="Đổi ảnh nền">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
              <input type="file" accept="image/*" @change="handleFileUpload" hidden />
            </label>
            
            <button v-if="isUsingFile" class="tryon-ctrl-btn text-btn" @click="startCamera">
               Bật Camera
            </button>
          </div>
          
          <div class="tryon-tip" v-if="!hasCameraError">
            <span v-if="!isManualMode">💡 Lùi ra xa để camera thấy rõ 2 vai của bạn. Áo sẽ tự động ghép vào người.</span>
            <span v-else>👉 Kéo thả trực tiếp lên ảnh sản phẩm để di chuyển.</span>
          </div>

        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.tryon-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(5px); display: flex; justify-content: center; align-items: center; z-index: 10000; padding: 16px;
}
.tryon-modal {
  background: #ffffff; border-radius: 20px; width: 100%; max-width: 650px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); display: flex; flex-direction: column;
}
.tryon-header {
  display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #F8F9FA; border-bottom: 1px solid #E9ECEF;
}
.tryon-title {
  display: flex; align-items: center; gap: 10px; font-size: 1.1rem; font-weight: 800; color: #2D3436; margin: 0;
}
.badge { font-size: 0.7rem; padding: 4px 8px; border-radius: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.badge.loading { background: #FFF0F3; color: #E63B6F; }
.badge.active { background: #ECFDF5; color: #059669; }
.badge.manual { background: #F3F4F6; color: #4B5563; }

.tryon-close { background: none; border: none; cursor: pointer; color: #636E72; border-radius: 8px; padding: 4px; display: flex; transition: all 0.2s; }
.tryon-close:hover { background: #E9ECEF; color: #E63B6F; }

.tryon-body { position: relative; width: 100%; background: #000; min-height: 400px; display: flex; justify-content: center; align-items: center; overflow: hidden; }
.tryon-error { padding: 40px 20px; text-align: center; color: #fff; }
.tryon-btn-upload, .tryon-btn-retry { display: inline-block; padding: 10px 20px; background: #E63B6F; color: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; margin: 5px; }
.tryon-btn-retry { background: #4B5563; }

.hidden { display: none !important; }
.tryon-viewport { position: relative; width: 100%; height: 60vh; max-height: 600px; display: flex; justify-content: center; align-items: center; }
.tryon-canvas {
  width: 100%; height: 100%; object-fit: contain; cursor: grab;
}
.tryon-canvas:active { cursor: grabbing; }

.loading-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.6); display: flex; flex-direction: column; justify-content: center; align-items: center; color: white; gap: 16px;
}
.spinner { width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.3); border-top-color: #E63B6F; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.tryon-controls { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 16px; padding: 16px 20px; background: #fff; border-top: 1px solid #E9ECEF; }
.tryon-controls-group { display: flex; align-items: center; background: #F8F9FA; border-radius: 8px; padding: 4px; }
.tryon-ctrl-btn { background: #fff; border: 1px solid #E9ECEF; border-radius: 6px; width: 36px; height: 36px; display: flex; justify-content: center; align-items: center; cursor: pointer; color: #2D3436; transition: all 0.2s; }
.tryon-ctrl-btn:hover { border-color: #B2BEC3; color: #E63B6F; }
.tryon-ctrl-btn.text-btn { width: auto; padding: 0 16px; font-weight: 600; font-size: 0.9rem; }
.tryon-ctrl-btn.active-mode { background: #E63B6F; color: #fff; border-color: #E63B6F; }
.tryon-scale-label { min-width: 50px; text-align: center; font-size: 0.9rem; font-weight: 600; color: #636E72; }
.tryon-tip { padding: 12px 20px; background: #FFF0F3; color: #E63B6F; font-size: 0.85rem; text-align: center; font-weight: 500; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; transform: scale(0.95); }
</style>
