import api from '@/axios';

export const loyaltyService = {
  /**
   * Lấy tóm tắt điểm thưởng: số dư, tổng earn, burn, sắp hết hạn
   */
  getSummary() {
    return api.get('/loyalty/summary');
  },

  /**
   * Lịch sử giao dịch điểm (paginated)
   * @param {Object} params - { type: 'earn'|'burn'|'expire', per_page: 20, page: 1 }
   */
  getHistory(params = {}) {
    return api.get('/loyalty/history', { params });
  },

  /**
   * Lấy danh sách quy tắc earn/burn (public)
   */
  getRules() {
    return api.get('/loyalty/rules');
  },

  /**
   * Preview: tính số tiền giảm nếu dùng X điểm khi checkout
   * @param {Object} data - { points_to_use: int, order_subtotal: float }
   */
  previewBurn(data) {
    return api.post('/loyalty/preview-burn', data);
  },

  /**
   * Earn +30 điểm khi chia sẻ sản phẩm lên mạng xã hội
   * @param {number} productId
   */
  socialShare(productId) {
    return api.post('/loyalty/social-share', { product_id: productId });
  },

  /**
   * Lấy danh sách quà tặng (Rewards)
   */
  getRewards() {
    return api.get('/loyalty/rewards');
  },

  /**
   * Đổi quà
   * @param {number} rewardId 
   */
  redeem(rewardId) {
    return api.post('/loyalty/redeem', { reward_id: rewardId });
  },
};
