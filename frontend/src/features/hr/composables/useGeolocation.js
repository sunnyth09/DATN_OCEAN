import { ref } from 'vue';

const GPS_ERROR_MESSAGES = {
  1: 'Cần cấp quyền vị trí!',
  2: 'Không xác định được GPS.',
  3: 'Hết thời gian chờ GPS.',
};

export function useGeolocation() {
  const gpsLoading = ref(false);

  const getPosition = (options = {}) =>
    new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Trình duyệt không hỗ trợ GPS.'));
        return;
      }

      gpsLoading.value = true;
      navigator.geolocation.getCurrentPosition(
        (position) => {
          gpsLoading.value = false;
          resolve(position);
        },
        (error) => {
          gpsLoading.value = false;
          reject(new Error(GPS_ERROR_MESSAGES[error.code] || 'Lỗi GPS.'));
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0, ...options },
      );
    });

  return { gpsLoading, getPosition };
}
