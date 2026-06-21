import api from '@/axios';

export const walletService = {
  /**
   * Lấy tóm tắt số dư ví và tổng hoa hồng
   */
  getSummary() {
    return api.get('/wallet/summary');
  },

  /**
   * Lấy lịch sử giao dịch ví (phân trang)
   */
  getHistory(page = 1, perPage = 10) {
    return api.get(`/wallet/history?page=${page}&per_page=${perPage}`);
  },

  /**
   * Gửi yêu cầu rút tiền về ngân hàng hoặc VNPay
   * @param {Object} data { amount, withdrawal_method, bank_name, bank_account_name, bank_account_number }
   */
  requestWithdrawal(data) {
    return api.post('/wallet/withdraw', data);
  }
};
