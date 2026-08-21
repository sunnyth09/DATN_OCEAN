import api from '@/axios';

export const walletService = {
  // Tổng quan ví (số dư, thống kê, giao dịch gần đây)
  getSummary() {
    return api.get('/wallet');
  },

  // Lịch sử giao dịch (phân trang)
  getHistory(params = {}) {
    return api.get('/wallet/history', { params });
  },

  // Preview giảm giá checkout
  previewDiscount(subtotal) {
    return api.get('/wallet/preview-discount', { params: { subtotal } });
  },

  // Khởi tạo nạp tiền
  initDeposit(amount, method) {
    return api.post('/wallet/deposit/init', { amount, method });
  },

  // Rút tiền từ deposit_balance
  withdraw(data) {
    return api.post('/wallet/withdraw', data);
  },

  // Lịch sử rút tiền
  getWithdrawals(params = {}) {
    return api.get('/wallet/withdrawals', { params });
  },

  // ── Tài khoản ngân hàng liên kết ──
  getBankAccounts() {
    return api.get('/wallet/bank-accounts');
  },
  addBankAccount(data) {
    return api.post('/wallet/bank-accounts', data);
  },
  updateBankAccount(id, data) {
    return api.put(`/wallet/bank-accounts/${id}`, data);
  },
  deleteBankAccount(id) {
    return api.delete(`/wallet/bank-accounts/${id}`);
  },
  setDefaultBankAccount(id) {
    return api.post(`/wallet/bank-accounts/${id}/default`);
  },
  verifyBankAccount(data) {
    return api.post('/wallet/bank-accounts/verify', data);
  },
};
