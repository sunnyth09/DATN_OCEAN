/**
 * useFaceApi — Vue 3 Composable cho face-api.js
 *
 * Quản lý load model, detect khuôn mặt, extract 128-dim descriptor.
 * Dùng cho cả trang đăng ký và chấm công.
 *
 * Models được tải từ /models/ (public directory) và cached bởi browser.
 */
import { ref } from 'vue';
import * as faceapi from '@vladmandic/face-api';

// Singleton state — models chỉ cần load 1 lần cho toàn app
const modelsLoaded = ref(false);
const modelsLoading = ref(false);
const modelsError = ref(null);

// Model path (served from public/)
const MODEL_URL = '/models';

/**
 * Load 3 models cần thiết cho face recognition:
 * 1. SSD MobileNet v1 — Detect vị trí khuôn mặt
 * 2. Face Landmark 68 — Xác định 68 điểm đặc trưng (mắt, mũi, miệng...)
 * 3. Face Recognition — Tạo 128-dim descriptor vector
 */
async function loadModels() {
  if (modelsLoaded.value || modelsLoading.value) return modelsLoaded.value;

  modelsLoading.value = true;
  modelsError.value = null;

  try {
    await Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]);

    modelsLoaded.value = true;
    return true;
  } catch (err) {
    modelsError.value = 'Không thể tải AI models. Vui lòng tải lại trang.';
    return false;
  } finally {
    modelsLoading.value = false;
  }
}

/**
 * Detect khuôn mặt trong ảnh/video element.
 * Trả về detection result hoặc null nếu không tìm thấy.
 *
 * @param {HTMLVideoElement|HTMLCanvasElement|HTMLImageElement} input
 * @returns {Promise<faceapi.FaceDetection|null>}
 */
async function detectFace(input) {
  if (!modelsLoaded.value) return null;

  const detection = await faceapi
    .detectSingleFace(input, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }));

  return detection || null;
}

/**
 * Detect khuôn mặt + extract 128-dim descriptor.
 * Đây là hàm chính dùng cho cả đăng ký và xác thực.
 *
 * @param {HTMLVideoElement|HTMLCanvasElement|HTMLImageElement} input
 * @returns {Promise<{descriptor: number[], detection: object}|null>}
 *   - descriptor: Array 128 số float — "dấu vân tay" khuôn mặt
 *   - detection: Thông tin vị trí khuôn mặt (bounding box, score)
 *   - null nếu không phát hiện khuôn mặt
 */
async function getFaceDescriptor(input) {
  if (!modelsLoaded.value) return null;

  const result = await faceapi
    .detectSingleFace(input, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
    .withFaceLandmarks()
    .withFaceDescriptor();

  if (!result) return null;

  return {
    descriptor: Array.from(result.descriptor), // Float32Array → regular Array
    detection: {
      score: result.detection.score,
      box: result.detection.box,
    },
  };
}

/**
 * So sánh 2 descriptors bằng euclidean distance.
 * Dùng cho client-side preview (quyết định cuối cùng vẫn ở backend).
 *
 * @param {number[]} d1 — Descriptor 1 (128-dim)
 * @param {number[]} d2 — Descriptor 2 (128-dim)
 * @returns {number} Euclidean distance (0 = giống hoàn toàn, >0.45 = khác người)
 */
function computeDistance(d1, d2) {
  if (!d1 || !d2 || d1.length !== 128 || d2.length !== 128) return 1.0;
  return faceapi.euclideanDistance(d1, d2);
}

/**
 * Vue 3 Composable — dùng trong component
 */
export function useFaceApi() {
  return {
    // State (reactive)
    modelsLoaded,
    modelsLoading,
    modelsError,

    // Actions
    loadModels,
    detectFace,
    getFaceDescriptor,
    computeDistance,
  };
}
