import api from '@/axios';

export const orderService = {
  createProfileOrder(payload) {
    return api.post('/profile/orders', payload);
  },

  listProfileOrders(params = {}) {
    return api.get('/profile/orders', { params });
  },

  getProfileOrderDetail(orderId) {
    return api.get(`/profile/orders/${orderId}`);
  },

  getOrderTracking(orderId) {
    return api.get(`/profile/orders/${orderId}/tracking`);
  },

  trackOrderByToken(token) {
    return api.get(`/tracking/${token}`);
  },

  trackGuestOrder(payload) {
    return api.post('/orders/guest-tracking', payload);
  },

  cancelProfileOrder(orderId, cancelReason) {
    return api.put(`/profile/orders/${orderId}/cancel`, {
      cancel_reason: cancelReason,
    });
  },

  resolveOrderId(orderCode) {
    return api.get(`/profile/orders/${orderCode}/order-id`);
  },

  buyAgain(orderId) {
    return api.post(`/cart/buy-again/${orderId}`);
  },
};
