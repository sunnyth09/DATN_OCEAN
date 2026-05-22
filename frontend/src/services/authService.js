import api from '@/axios';

export const authService = {
  login(payload) {
    return api.post('/login', payload);
  },

  register(payload) {
    return api.post('/register', payload);
  },

  logout() {
    return api.post('/logout');
  },

  sendForgotPasswordOtp(email) {
    return api.post('/forgot-password/send-otp', { email });
  },

  verifyForgotPasswordOtp(payload) {
    return api.post('/forgot-password/verify-otp', payload);
  },

  resetForgotPassword(payload) {
    return api.post('/forgot-password/reset', payload);
  },

  exchangeGoogleCode(code) {
    return api.post('/auth/google/callback', { code });
  },

  exchangeFacebookCode(code) {
    return api.post('/auth/facebook/callback', { code });
  },

  fetchProfileNotifications(params = {}) {
    return api.get('/profile/notifications', { params });
  },
};
