import api from '@/axios';

export const openPlayService = {
  // ==========================================
  // PUBLIC & GUEST APIS
  // ==========================================
  getOpenPlays(params) {
    return api.get('/open-plays', { params });
  },

  getOpenPlayDetail(id) {
    return api.get(`/open-plays/${id}`);
  },

  sendGuestOtp(phone) {
    return api.post('/open-plays/guest/send-otp', { phone });
  },

  verifyGuestOtp(payload) {
    return api.post('/open-plays/guest/verify-otp', payload);
  },

  // ==========================================
  // USER / PARTICIPANT APIS
  // ==========================================
  getByBooking(bookingId) {
    return api.get(`/open-plays/by-booking/${bookingId}`);
  },

  initForBooking(bookingId, payload = {}) {
    return api.post(`/open-plays/init-for-booking/${bookingId}`, payload);
  },

  inviteUsers(id, userIds) {
    return api.post(`/open-plays/${id}/invite-users`, { user_ids: userIds });
  },

  searchInvitees(query, openPlayId = null) {
    return api.get('/open-plays/search-invitees', { params: { query, open_play_id: openPlayId } });
  },

  getEligibleBookings() {
    return api.get('/open-plays/eligible-bookings');
  },

  createOpenPlay(payload) {
    return api.post('/open-plays', payload);
  },

  updateOpenPlay(id, payload) {
    return api.put(`/open-plays/${id}`, payload);
  },

  joinOpenPlay(id, payload = {}) {
    return api.post(`/open-plays/${id}/join`, payload);
  },

  leaveOpenPlay(id, payload = {}) {
    return api.post(`/open-plays/${id}/leave`, payload);
  },

  joinWaitlist(id) {
    return api.post(`/open-plays/${id}/waitlist`);
  },

  leaveWaitlist(id) {
    return api.post(`/open-plays/${id}/waitlist/leave`);
  },

  paySlot(id, payload) {
    return api.post(`/open-plays/${id}/pay`, payload);
  },

  getCheckInQr(id) {
    return api.get(`/open-plays/${id}/qr`);
  },

  checkIn(id, payload) {
    return api.post(`/open-plays/${id}/check-in`, payload);
  },

  getMyOpenPlays() {
    return api.get('/my-open-plays');
  },

  // ==========================================
  // HOST MANAGEMENT APIS
  // ==========================================
  approveParticipant(id, participantId) {
    return api.post(`/open-plays/${id}/approve`, { participant_id: participantId });
  },

  rejectParticipant(id, participantId, reason = null) {
    return api.post(`/open-plays/${id}/reject`, { participant_id: participantId, reason });
  },

  removeParticipant(id, participantId, reason = null) {
    return api.post(`/open-plays/${id}/remove-participant`, { participant_id: participantId, reason });
  },

  closeRegistration(id) {
    return api.post(`/open-plays/${id}/close`);
  },

  cancelOpenPlay(id, reason = null) {
    return api.post(`/open-plays/${id}/cancel`, { reason });
  },

  // ==========================================
  // ADMIN / STAFF APIS
  // ==========================================
  getAdminOpenPlays(params) {
    return api.get('/admin/open-plays', { params });
  },

  getAdminOpenPlayDetail(id) {
    return api.get(`/admin/open-plays/${id}`);
  },

  scanAdminQr(qrData) {
    return api.post('/admin/open-plays/scan-qr', { qr_data: qrData });
  },
};
