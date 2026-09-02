<script setup>
import { onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import AppIcon from '@/components/AppIcon.vue';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import {
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
  getReturnRefundStatusLabel,
} from '@/utils/orderStatus';
import { getStorageUrl } from '@/utils/url';

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

const imageUrl = (path) => getStorageUrl(path);

const fetchData = (page = 1) => {
  store.fetchMyReturnRequests({ page });
};

onMounted(() => {
  fetchData();
});
</script>

<template>
  <div class="return-requests-page animate-in">
    <div class="page-header">
      <div>
        <h2 class="page-title">Yêu cầu hoàn hàng của tôi</h2>
        <p class="page-subtitle">Theo dõi tiến trình xét duyệt, vận chuyển thu hồi và hoàn tiền cho các sản phẩm đã yêu cầu trả.</p>
      </div>
    </div>

    <!-- Modern Skeleton Loading -->
    <div v-if="myLoading" class="profile-returns-skeleton">
      <div v-for="i in 3" :key="i" class="skeleton-return-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <div class="skeleton-box" style="width: 140px; height: 20px; border-radius: 4px;"></div>
          <div class="skeleton-box" style="width: 100px; height: 24px; border-radius: 20px;"></div>
        </div>
        <div class="skeleton-box" style="width: 60%; height: 16px; border-radius: 4px; margin-bottom: 8px;"></div>
        <div class="skeleton-box" style="width: 40%; height: 14px; border-radius: 4px;"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="myRequests.length === 0" class="empty-state">
      <div class="empty-icon-wrapper">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      </div>
      <h3 class="empty-title">Chưa có yêu cầu hoàn hàng nào</h3>
      <p class="empty-desc">Khi bạn gửi yêu cầu hoàn trả cho đơn hàng, thông tin và tiến độ hoàn tiền sẽ hiển thị tại đây.</p>
      <router-link :to="{ name: 'profile-orders' }" class="btn-goto-orders">
        Xem danh sách đơn hàng đã mua
      </router-link>
    </div>

    <!-- Requests List -->
    <div v-else class="request-list">
      <router-link
        v-for="item in myRequests"
        :key="item.id"
        :to="{ name: 'profile-return-request-detail', params: { id: item.id } }"
        class="request-card"
      >
        <div class="request-card__top">
          <div class="request-main-info">
            <div class="code-and-order">
              <span class="request-code">{{ item.return_code || `#${item.order?.order_code || item.order_id}` }}</span>
              <span class="dot-sep">•</span>
              <span class="request-order-code">Đơn hàng #{{ item.order?.order_code || item.order_id }}</span>
            </div>
            <h3 class="request-reason">{{ item.reason }}</h3>
          </div>

          <span class="status-badge" :class="getReturnRequestStatusTone(item.status)">
            <span class="pulse-dot"></span>
            {{ getReturnRequestStatusLabel(item.status) }}
          </span>
        </div>

        <!-- Product Thumbnails Row -->
        <div v-if="item.items?.length" class="card-products-preview">
          <div class="product-thumbs-strip">
            <div
              v-for="prod in item.items.slice(0, 4)"
              :key="prod.id"
              class="thumb-box"
              :title="prod.order_item?.product_name || prod.product?.name"
            >
              <img
                :src="imageUrl(prod.order_item?.product_image || prod.product?.thumbnail_url || prod.order_item?.variant_image || prod.variant?.image_url)"
                alt="Product"
                @error="(e) => e.target.src = 'https://placehold.co/44x44?text=SP'"
              />
              <span v-if="prod.requested_quantity > 1" class="qty-bubble">x{{ prod.requested_quantity }}</span>
            </div>
            <div v-if="item.items.length > 4" class="more-thumbs-bubble">
              +{{ item.items.length - 4 }}
            </div>
          </div>

          <div class="products-count-text">
            <span>{{ (item.items || []).reduce((sum, r) => sum + Number(r.requested_quantity || 0), 0) }} sản phẩm</span>
          </div>
        </div>

        <div v-if="item.description" class="request-desc" v-html="item.description"></div>

        <div class="request-footer">
          <div class="footer-meta">
            <span class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              {{ formatDate(item.requested_at || item.created_at) }}
            </span>
            <span v-if="item.return_tracking_code" class="tracking-pill">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              {{ item.return_carrier === 'dropoff_post_office' ? 'Bưu cục' : (item.return_carrier || 'Ocean Express') }}: {{ item.return_tracking_code }}
            </span>
          </div>

          <div class="footer-financial">
            <span class="refund-label">Tiền hoàn:</span>
            <strong class="refund-value">
              {{ formatPrice(item.refund_amount || item.items?.reduce((sum, i) => sum + Number(i.refundable_amount || 0), 0)) }}
            </strong>
          </div>
        </div>
      </router-link>
    </div>

    <!-- Pagination -->
    <div v-if="myPagination && myPagination.last_page > 1" class="pagination-wrapper">
      <button
        class="page-btn"
        :disabled="myPagination.current_page === 1"
        @click="fetchData(myPagination.current_page - 1)"
      >
        « Trước
      </button>
      <span class="page-info">Trang {{ myPagination.current_page }} / {{ myPagination.last_page }}</span>
      <button
        class="page-btn"
        :disabled="myPagination.current_page === myPagination.last_page"
        @click="fetchData(myPagination.current_page + 1)"
      >
        Sau »
      </button>
    </div>
  </div>
</template>

<style scoped>
.return-requests-page {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
  padding: 24px;
  min-height: 500px;
  border: 1px solid #e2e8f0;
}

.page-header {
  margin-bottom: 24px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}

.page-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
}

.page-subtitle {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 0.88rem;
}

.request-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.request-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  text-decoration: none;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 18px 20px;
  color: inherit;
  background: #ffffff;
  transition: all 0.2s ease;
}

.request-card:hover {
  border-color: #2563eb;
  box-shadow: 0 8px 24px rgba(37, 99, 235, 0.08);
  transform: translateY(-2px);
}

.request-card__top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.request-main-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.code-and-order {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
}

.request-code {
  font-weight: 800;
  color: #2563eb;
  font-family: ui-monospace, monospace;
}

.dot-sep {
  color: #cbd5e1;
}

.request-order-code {
  color: #64748b;
  font-weight: 600;
}

.request-reason {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 20px;
  white-space: nowrap;
}

.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status-badge.warning { background: #fef3c7; color: #b45309; }
.status-badge.info { background: #e0f2fe; color: #0369a1; }
.status-badge.primary { background: #dbeafe; color: #1d4ed8; }
.status-badge.success { background: #dcfce7; color: #15803d; }
.status-badge.danger { background: #ffe4e6; color: #be123c; }
.status-badge.neutral { background: #f1f5f9; color: #475569; }

/* Products Preview Strip */
.card-products-preview {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 10px;
  padding: 8px 12px;
}

.product-thumbs-strip {
  display: flex;
  align-items: center;
  gap: 8px;
}

.thumb-box {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  background: #ffffff;
}

.thumb-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.qty-bubble {
  position: absolute;
  bottom: 0;
  right: 0;
  background: rgba(0, 0, 0, 0.75);
  color: #ffffff;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 4px;
  border-top-left-radius: 4px;
}

.more-thumbs-bubble {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: #e2e8f0;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.products-count-text {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 600;
}

.request-desc {
  font-size: 0.88rem;
  color: #475569;
  line-height: 1.5;
  background: #ffffff;
  border-left: 3px solid #e2e8f0;
  padding-left: 10px;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.request-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
  flex-wrap: wrap;
  gap: 10px;
}

.footer-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: #64748b;
}

.tracking-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #eff6ff;
  color: #1e40af;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.footer-financial {
  display: flex;
  align-items: center;
  gap: 6px;
}

.refund-label {
  font-size: 0.82rem;
  color: #64748b;
}

.refund-value {
  font-size: 1.05rem;
  font-weight: 800;
  color: #2563eb;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.empty-icon-wrapper {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}

.empty-title {
  margin: 0 0 8px;
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
}

.empty-desc {
  margin: 0 0 20px;
  color: #64748b;
  font-size: 0.9rem;
  max-width: 400px;
}

.btn-goto-orders {
  display: inline-flex;
  align-items: center;
  background: #2563eb;
  color: #ffffff;
  text-decoration: none;
  font-weight: 700;
  font-size: 0.88rem;
  padding: 10px 20px;
  border-radius: 10px;
  transition: all 0.2s ease;
}

.btn-goto-orders:hover {
  background: #1d4ed8;
}

/* Pagination */
.pagination-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 24px;
}

.page-btn {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.85rem;
  font-weight: 700;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.page-btn:hover:not(:disabled) {
  border-color: #2563eb;
  color: #2563eb;
  background: #eff6ff;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-size: 0.88rem;
  font-weight: 600;
  color: #64748b;
}

/* Skeleton */
.skeleton-return-card {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 18px 20px;
  margin-bottom: 16px;
}

.skeleton-box {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: skeletonPulse 1.5s infinite;
}

@keyframes skeletonPulse {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
