import { defineStore } from 'pinia';
import { returnRequestService } from '@/services/returnRequestService';

export const useReturnRequestStore = defineStore('returnRequests', {
  state: () => ({
    myRequests: [],
    myPagination: null,
    myLoading: false,
    adminRequests: [],
    adminPagination: null,
    adminLoading: false,
    currentRequest: null,
    detailLoading: false,
  }),

  actions: {
    async createReturnRequest(orderId, payload) {
      const response = await returnRequestService.createReturnRequest(orderId, payload);
      return response.data;
    },

    async fetchMyReturnRequests(params = {}) {
      this.myLoading = true;
      try {
        const response = await returnRequestService.fetchMyReturnRequests(params);
        if (response.data.status === 'success') {
          this.myRequests = response.data.data?.data || [];
          this.myPagination = response.data.data || null;
        }
        return response.data;
      } finally {
        this.myLoading = false;
      }
    },

    async fetchMyReturnRequestDetail(id) {
      this.detailLoading = true;
      try {
        const response = await returnRequestService.fetchMyReturnRequestDetail(id);
        if (response.data.status === 'success') {
          this.currentRequest = response.data.data;
        }
        return response.data;
      } finally {
        this.detailLoading = false;
      }
    },

    async fetchAdminReturnRequests(params = {}) {
      this.adminLoading = true;
      try {
        const response = await returnRequestService.fetchAdminReturnRequests(params);
        if (response.data.status === 'success') {
          this.adminRequests = response.data.data?.data || [];
          this.adminPagination = response.data.data || null;
        }
        return response.data;
      } finally {
        this.adminLoading = false;
      }
    },

    async fetchAdminReturnRequestDetail(id) {
      this.detailLoading = true;
      try {
        const response = await returnRequestService.fetchAdminReturnRequestDetail(id);
        if (response.data.status === 'success') {
          this.currentRequest = response.data.data;
        }
        return response.data;
      } finally {
        this.detailLoading = false;
      }
    },

    async approveReturnRequest(id, payload = {}) {
      const response = await returnRequestService.approveReturnRequest(id, payload);
      return response.data;
    },

    async rejectReturnRequest(id, payload) {
      const response = await returnRequestService.rejectReturnRequest(id, payload);
      return response.data;
    },

    async markReturnReturning(id, payload = {}) {
      const response = await returnRequestService.markReturnReturning(id, payload);
      return response.data;
    },

    async markReturnReceived(id, payload = {}) {
      const response = await returnRequestService.markReturnReceived(id, payload);
      return response.data;
    },

    async inspectReturnRequest(id, payload) {
      const response = await returnRequestService.inspectReturnRequest(id, payload);
      return response.data;
    },

    async refundReturnRequest(id, payload) {
      const response = await returnRequestService.refundReturnRequest(id, payload);
      return response.data;
    },
  },
});
