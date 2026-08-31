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

  // Preview giảm giá checkout (tính toán cục bộ dựa trên số dư từ API /wallet để tránh lỗi route cache 404)
  async previewDiscount(subtotal, useDeposit = true, useCommission = true) {
    try {
      const res = await api.get('/wallet');
      if (res.data?.status === 'success') {
        const summary = res.data.data;
        const deposit_balance = parseFloat(summary.deposit_balance || 0);
        const commission_balance = parseFloat(summary.commission_balance || 0);
        
        const maxDeposit = useDeposit ? deposit_balance : 0;
        const maxCommission = useCommission ? commission_balance : 0;
        const maxTotal = Math.min(maxDeposit + maxCommission, subtotal);

        const depositUsed = Math.min(subtotal, maxDeposit);
        const remaining = subtotal - depositUsed;
        const commissionUsed = Math.min(remaining, maxCommission);

        return {
          data: {
            status: 'success',
            data: {
              deposit_balance: deposit_balance,
              commission_balance: commission_balance,
              max_from_deposit: maxDeposit,
              max_from_commission: maxCommission,
              max_total_discount: maxTotal,
              total_available: maxTotal,
              deposit_used: depositUsed,
              commission_used: commissionUsed,
              remaining_payment: Math.max(0, subtotal - maxTotal)
            }
          }
        };
      }
      return res;
    } catch (e) {
      console.error(e);
      throw e;
    }
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
};
