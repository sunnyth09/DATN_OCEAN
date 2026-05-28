import api from '@/axios';

export const affiliateService = {
  /**
   * Đăng ký affiliate
   */
  register() {
    return api.post('/profile/affiliate/register');
  },

  /**
   * Lấy thông tin affiliate profile + thống kê tổng hợp
   */
  getProfile() {
    return api.get('/profile/affiliate/profile');
  },

  /**
   * Thống kê theo ngày/tháng/năm
   */
  getStatistics(type = 'month') {
    return api.get('/profile/affiliate/statistics', { params: { type } });
  },

  /**
   * Danh sách đơn hàng phát sinh hoa hồng
   */
  getConversions(params = {}) {
    return api.get('/profile/affiliate/conversions', { params });
  },

  /**
   * Gửi yêu cầu rút tiền
   */
  requestWithdrawal(data) {
    return api.post('/profile/affiliate/withdrawals', data);
  },

  /**
   * Lịch sử rút tiền
   */
  getWithdrawals() {
    return api.get('/profile/affiliate/withdrawals');
  },

  /**
   * Ghi nhận click referral link (Public API)
   */
  trackClick(data) {
    return api.post('/affiliate/track-click', data);
  },
};
