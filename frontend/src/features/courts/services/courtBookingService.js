import api from '@/axios';

export const courtBookingService = {
  // ==========================================
  // USER APIS
  // ==========================================
  getCourts(params) {
    return api.get('/courts', { params });
  },
  getCourtDetail(id) {
    return api.get(`/courts/${id}`);
  },
  checkAvailability(id, params) {
    return api.get(`/courts/${id}/availability`, { params });
  },
  lockSlot(payload) {
    return api.post('/court-bookings/lock', payload);
  },
  releaseLock(payload) {
    return api.post('/court-bookings/release-lock', payload);
  },
  createBooking(payload) {
    return api.post('/court-bookings', payload);
  },
  getUserBookings(params) {
    return api.get('/court-bookings', { params });
  },
  getUserBookingDetail(id) {
    return api.get(`/court-bookings/${id}`);
  },
  cancelBooking(id, payload) {
    return api.post(`/court-bookings/${id}/cancel`, payload);
  },
  payBooking(id, payload) {
    return api.post(`/court-bookings/${id}/payments`, payload);
  },
  getBookingQr(id) {
    return api.get(`/court-bookings/${id}/qr`);
  },
  getPublicServices(params) {
    return api.get('/court-services', { params });
  },

  // ==========================================
  // ADMIN APIS
  // ==========================================
  
  // -- Courts Management --
  getAdminCourts(params) {
    return api.get('/admin/courts', { params });
  },
  uploadCourtImage(formData) {
    return api.post('/admin/courts/upload-image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
  },
  createAdminCourt(payload) {
    return api.post('/admin/courts', payload);
  },
  updateAdminCourt(id, payload) {
    return api.put(`/admin/courts/${id}`, payload);
  },
  deleteAdminCourt(id) {
    return api.delete(`/admin/courts/${id}`);
  },

  // -- Bookings Management --
  getAdminBookings(params) {
    return api.get('/admin/court-bookings', { params });
  },
  getAdminBookingDetail(id) {
    return api.get(`/admin/court-bookings/${id}`);
  },
  createAdminBooking(payload) {
    return api.post('/admin/court-bookings', payload);
  },
  updateAdminBooking(id, payload) {
    return api.put(`/admin/court-bookings/${id}`, payload);
  },
  deleteAdminBooking(id) {
    return api.delete(`/admin/court-bookings/${id}`);
  },
  confirmBooking(id, payload = {}) {
    return api.post(`/admin/court-bookings/${id}/confirm`, payload);
  },
  cancelAdminBooking(id, payload = {}) {
    return api.post(`/admin/court-bookings/${id}/cancel`, payload);
  },
  recordAdminPayment(id, payload) {
    return api.post(`/admin/court-bookings/${id}/payments`, payload);
  },
  scanQrCheckIn(payload) {
    return api.post('/admin/court-bookings/scan-qr', payload);
  },
  qrCheckInBooking(id, payload) {
    return api.post(`/admin/court-bookings/${id}/qr-check-in`, payload);
  },
  checkInBooking(id, payload = {}) {
    return api.post(`/admin/court-bookings/${id}/check-in`, payload);
  },
  checkOutBooking(id, payload = {}) {
    return api.post(`/admin/court-bookings/${id}/check-out`, payload);
  },
  addServiceToBooking(id, payload) {
    return api.post(`/admin/court-bookings/${id}/services`, payload);
  },
  extendBooking(id, payload) {
    return api.post(`/admin/court-bookings/${id}/extend`, payload);
  },

  // -- Dashboard & Reports --
  getCourtDashboard(params) {
    return api.get('/admin/courts-dashboard', { params });
  },
  getCourtStats(params) {
    return api.get('/admin/courts-stats', { params });
  },
  getCourtCalendar(params) {
    return api.get('/admin/courts-calendar', { params });
  },

  // -- Schedules --
  getSchedules(params) { return api.get('/admin/court-schedules', { params }); },
  createSchedule(payload) { return api.post('/admin/court-schedules', payload); },
  updateSchedule(id, payload) { return api.put(`/admin/court-schedules/${id}`, payload); },
  deleteSchedule(id) { return api.delete(`/admin/court-schedules/${id}`); },

  // -- Prices --
  getPrices(params) { return api.get('/admin/court-prices', { params }); },
  createPrice(payload) { return api.post('/admin/court-prices', payload); },
  updatePrice(id, payload) { return api.put(`/admin/court-prices/${id}`, payload); },
  deletePrice(id) { return api.delete(`/admin/court-prices/${id}`); },

  // -- Services --
  getServices(params) { return api.get('/admin/court-services', { params }); },
  uploadCourtServiceImage(formData) {
    return api.post('/admin/court-services/upload-image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
  },
  createService(payload) { return api.post('/admin/court-services', payload); },
  updateService(id, payload) { return api.put(`/admin/court-services/${id}`, payload); },
  deleteService(id) { return api.delete(`/admin/court-services/${id}`); },

  // -- Maintenances --
  getMaintenances(params) { return api.get('/admin/court-maintenances', { params }); },
  createMaintenance(payload) { return api.post('/admin/court-maintenances', payload); },
  updateMaintenance(id, payload) { return api.put(`/admin/court-maintenances/${id}`, payload); },
  deleteMaintenance(id) { return api.delete(`/admin/court-maintenances/${id}`); },
};
