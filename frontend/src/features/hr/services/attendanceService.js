import api from '@/axios';

export const attendanceService = {
  fetchToday() {
    return api.get('/admin/attendance/today');
  },

  fetchFaceStatus() {
    return api.get('/admin/face/status');
  },

  checkIn(payload) {
    return api.post('/admin/attendance/check-in', payload);
  },

  checkOut(payload) {
    return api.post('/admin/attendance/check-out', payload);
  },
};
