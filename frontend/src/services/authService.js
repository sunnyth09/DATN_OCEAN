import api from '../axios';

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

  exchangeGoogleCode(code, redirectUri = null) {
    const payload = { code };
    if (redirectUri) payload.redirect_uri = redirectUri;
    return api.post('/auth/google/callback', payload);
  },

  exchangeFacebookCode(code, redirectUri = null) {
    const payload = { code };
    if (redirectUri) payload.redirect_uri = redirectUri;
    return api.post('/auth/facebook/callback', payload);
  },

  fetchProfileNotifications(params = {}) {
    return api.get('/profile/notifications', { params });
  },
};
