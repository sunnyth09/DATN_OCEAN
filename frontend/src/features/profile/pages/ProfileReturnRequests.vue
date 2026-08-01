<script setup>
import { onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import AppIcon from '@/icons/AppIcon.vue';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import {
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
  getReturnRefundStatusLabel,
} from '@/utils/orderStatus';

const store = useReturnRequestStore();
const { myRequests, myPagination, myLoading } = storeToRefs(store);

const formatDate = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatPrice = (value) => new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
}).format(Number(value || 0));

const fetchData = (page = 1) => {
  store.fetchMyReturnRequests({ page });
};

onMounted(() => {
  fetchData();
});
</script>

<template>
  <div class="return-requests-page">
    <div class="page-header">
      <h2 class="page-title">Yêu cầu hoàn hàng của tôi</h2>
      <p class="page-subtitle">Theo dõi tiến độ xử lý và hoàn tiền cho các đơn đã yêu cầu hoàn.</p>
    </div>


    <div v-if="myLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải yêu cầu hoàn hàng...</p>
    </div>

    <div v-else-if="myRequests.length === 0" class="empty-state">
      <div class="empty-icon-wrapper">
        <AppIcon name="archive" size="56" stroke-width="1.2" class="empty-icon" />
      </div>
      <h3 class="empty-title">Chưa có yêu cầu hoàn hàng nào</h3>
      <p class="empty-desc">Khi bạn gửi yêu cầu hoàn hàng, thông tin sẽ hiển thị ở đây.</p>
    </div>

    <div v-else class="request-list">
      <router-link
        v-for="item in myRequests"
        :key="item.id"
        :to="{ name: 'profile-return-request-detail', params: { id: item.id } }"
        class="request-card"
      >
        <div class="request-card__top">
          <div>
            <p class="request-code">{{ item.return_code || `#${item.order?.order_code || item.order_id}` }}</p>
            <h3 class="request-reason">{{ item.reason }}</h3>
            <small class="request-order-code">Đơn hàng #{{ item.order?.order_code || item.order_id }}</small>
          </div>
          <span class="status-badge" :class="getReturnRequestStatusTone(item.status)">
            {{ getReturnRequestStatusLabel(item.status) }}
          </span>
        </div>

        <div class="request-desc" v-html="item.description || 'Không có mô tả bổ sung.'"></div>

        <div class="request-meta">
          <span>Gửi lúc: {{ formatDate(item.requested_at || item.created_at) }}</span>
          <span>{{ item.items?.length || 0 }} sản phẩm</span>
          <span>Hoàn tiền: {{ getReturnRefundStatusLabel(item.refund_status) }}</span>
          <span v-if="Number(item.refund_amount || 0) > 0">Số tiền: {{ formatPrice(item.refund_amount) }}</span>
        </div>
      </router-link>
    </div>

    <div v-if="myPagination && myPagination.last_page > 1" class="pagination">
      <button
        class="page-btn"
        :disabled="myPagination.current_page === 1"
        @click="fetchData(myPagination.current_page - 1)"
      >
        «
      </button>
      <span>Trang {{ myPagination.current_page }} / {{ myPagination.last_page }}</span>
      <button
        class="page-btn"
        :disabled="myPagination.current_page === myPagination.last_page"
        @click="fetchData(myPagination.current_page + 1)"
      >
        »
      </button>
    </div>
  </div>
</template>

<style scoped>
.return-requests-page {
  background: var(--card-bg);
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  padding: 24px;
  min-height: 500px;
}

.page-header {
  margin-bottom: 24px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}
.page-title { margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main); }
.page-subtitle { margin: 8px 0 0; color: #64748b; font-size: 0.92rem; }

.request-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.request-card {
  display: block;
  text-decoration: none;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 18px 20px;
  color: inherit;
  transition: all 0.2s ease;
}

.request-card:hover {
  border-color: var(--primary);
  box-shadow: 0 10px 24px rgba(230, 59, 111, 0.08);
  transform: translateY(-1px);
}

.request-card__top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.request-code {
  margin: 0 0 6px;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--primary);
}

.request-reason {
  margin: 0;
  font-size: 1rem;
  color: var(--text-main);
}

.request-order-code {
  display: inline-block;
  margin-top: 4px;
  color: #64748b;
  font-weight: 600;
}

.request-desc {
  margin: 14px 0;
  color: #475569;
  line-height: 1.55;
}

.request-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 20px;
  color: #64748b;
  font-size: 0.85rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 0.85rem;
  font-weight: 700;
  background: var(--card-bg);
  border: 1px solid #e2e8f0;
}

.status-badge.status-info { color: #475569; background: #f8fafc; border-color: #cbd5e1; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border-color: #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce3; border-color: #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border-color: #fecaca; }

.loading-state,
.empty-state {
  text-align: center;
  padding: 56px 20px;
  color: #64748b;
}

.empty-icon-wrapper {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  width: 96px;
  height: 96px;
  background: rgba(230, 59, 111, 0.06);
  border-radius: 50%;
  margin-bottom: 20px;
}

.empty-icon {
  color: var(--primary);
}

.empty-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px;
}

.empty-desc {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0;
}

.spinner {
  width: 38px;
  height: 38px;
  border: 3px solid #f1f5f9;
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.9s linear infinite;
  margin: 0 auto 16px;
}

.pagination {
  margin-top: 24px;
  display: flex;
  justify-content: center;
  gap: 16px;
  align-items: center;
}

.page-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: var(--card-bg);
  cursor: pointer;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
