import { ref } from 'vue';

export function useCamera() {
  const videoElement = ref(null);
  const canvasElement = ref(null);
  let stream = null;

  const start = async () => {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
    if (videoElement.value) videoElement.value.srcObject = stream;
  };

  const stop = () => {
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    }
  };

  const capture = () => {
    if (!videoElement.value || !canvasElement.value) return null;

    const canvas = canvasElement.value;
    const width = videoElement.value.videoWidth || 640;
    const height = videoElement.value.videoHeight || 480;
    if (width === 0 || height === 0) return null;

    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(videoElement.value, 0, 0, width, height);
    return canvas.toDataURL('image/jpeg', 0.8);
  };

  // Dùng FaceDetector API (Chrome/Edge native). Trả về true nếu không hỗ trợ
  // hoặc gặp lỗi (graceful fallback — để server quyết định cuối cùng).
  const hasFace = async (imageBase64) => {
    if (!('FaceDetector' in window)) return true;

    try {
      const img = new Image();
      img.src = imageBase64;
      await new Promise((resolve) => { img.onload = resolve; });

      const detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
      const faces = await detector.detect(img);
      return faces.length > 0;
    } catch {
      return true;
    }
  };

  return { videoElement, canvasElement, start, stop, capture, hasFace };
}
