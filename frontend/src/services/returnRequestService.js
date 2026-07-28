import api from '@/axios';

export const returnRequestService = {
  createReturnRequest(orderId, payload) {
    return api.post(`/orders/${orderId}/return-request`, payload, {
      headers: payload instanceof FormData
        ? { 'Content-Type': 'multipart/form-data' }
        : undefined,
    });
  },

  fetchMyReturnRequests(params = {}) {
    return api.get('/my/return-requests', { params });
  },

  fetchMyReturnRequestDetail(id) {
    return api.get(`/my/return-requests/${id}`);
  },

  fetchAdminReturnRequests(params = {}) {
    return api.get('/admin/return-requests', { params });
  },

  fetchAdminReturnRequestDetail(id) {
    return api.get(`/admin/return-requests/${id}`);
  },

  approveReturnRequest(id, payload = {}) {
    return api.patch(`/admin/return-requests/${id}/approve`, payload);
  },

  rejectReturnRequest(id, payload) {
    return api.patch(`/admin/return-requests/${id}/reject`, payload);
  },

  markReturnReturning(id, payload = {}) {
    return api.patch(`/admin/return-requests/${id}/returning`, payload);
  },

  markReturnReceived(id, payload = {}) {
    return api.patch(`/admin/return-requests/${id}/received`, payload);
  },

  inspectReturnRequest(id, payload) {
    return api.patch(`/admin/return-requests/${id}/inspect`, payload);
  },

  refundReturnRequest(id, payload) {
    return api.patch(`/admin/return-requests/${id}/refund`, payload);
  },
};
