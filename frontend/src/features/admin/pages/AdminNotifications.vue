<template>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0"><i class="bi bi-bell-fill me-2" style="color: var(--court-primary);"></i> Thông báo hệ thống</h4>
            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" @click="markAllAsRead" :disabled="!hasUnread">
                <i class="bi bi-check2-all me-1"></i> Đánh dấu tất cả đã đọc
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3">
                <ul class="nav nav-pills card-header-pills">
                    <li class="nav-item">
                        <a class="nav-link" :class="{ active: filter === 'all' }" href="#" @click.prevent="filter = 'all'">Tất cả</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" :class="{ active: filter === 'unread' }" href="#" @click.prevent="filter = 'unread'">Chưa đọc</a>
                    </li>
                </ul>
            </div>
            
            <div class="list-group list-group-flush" v-if="notifications.length > 0">
                <div v-for="noti in notifications" :key="noti.id" 
                    class="list-group-item list-group-item-action d-flex gap-3 py-3"
                    :class="{ 'bg-light': !noti.read_at }"
                    style="cursor: pointer;"
                    @click="handleNotificationClick(noti)">
                    
                    <div class="text-primary mt-1" style="font-size: 1.5rem;">
                        <i :class="noti.data.icon || 'bi bi-info-circle'"></i>
                    </div>
                    <div class="d-flex gap-2 w-100 justify-content-between">
                        <div>
                            <h6 class="mb-1 fw-bold d-flex align-items-center gap-2" :class="{'text-dark': !noti.read_at, 'text-muted': noti.read_at}">
                                {{ noti.data.title }}
                                <span v-if="noti.data.is_flash_sale || (noti.data.title && noti.data.title.includes('Flash Sale'))" class="badge bg-warning text-dark" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">⚡ Flash Sale</span>
                            </h6>
                            <p class="mb-1 text-secondary" style="font-size: 0.9rem;">{{ noti.data.message }}</p>
                            
                            <!-- Hiển thị trạng thái đơn hàng nếu có -->
                            <div v-if="noti.data.payment_status || noti.data.fulfillment_status" class="mt-2 mb-2 d-flex gap-2 flex-wrap">
                                <span v-if="noti.data.fulfillment_status" class="badge rounded-pill" :class="getFulfillmentBadgeClass(noti.data.fulfillment_status)">
                                    Vận chuyển: {{ fulfillmentLabels[noti.data.fulfillment_status] || noti.data.fulfillment_status }}
                                </span>
                                <span v-if="noti.data.payment_status" class="badge rounded-pill" :class="getPaymentBadgeClass(noti.data.payment_status)">
                                    Thanh toán: {{ paymentLabels[noti.data.payment_status] || noti.data.payment_status }}
                                </span>
                            </div>

                            <small class="text-muted"><i class="bi bi-clock me-1"></i> {{ formatTimeAgo(noti.created_at) }}</small>
                        </div>
                        <div class="text-end d-flex flex-column justify-content-between">
                            <span v-if="!noti.read_at" class="badge bg-danger rounded-pill align-self-end mb-2">Mới</span>
                            <button class="btn btn-sm text-danger border-0 bg-transparent p-1" @click.stop="deleteNotification(noti.id)" title="Xóa thông báo">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-5">
                <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">Không có thông báo nào.</p>
            </div>

            <!-- Pagination -->
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-center" v-if="totalPages >= 1">
                <div class="pagination">
                    <button class="page-btn" :disabled="currentPage === 1" @click="changePage(currentPage - 1)">‹</button>
                    <template v-for="(item, index) in visiblePages" :key="index">
                        <span v-if="item === '...'" class="page-dots">...</span>
                        <button
                            v-else
                            class="page-btn"
                            :class="{ active: item === currentPage }"
                            @click="changePage(item)"
                        >{{ item }}</button>
                    </template>
                    <button class="page-btn" :disabled="currentPage === totalPages || totalPages === 0" @click="changePage(currentPage + 1)">›</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

import { useUiStore } from '@/stores/ui';

const router = useRouter();
const uiStore = useUiStore();
const notifications = ref([]);
const filter = ref('all');
const currentPage = ref(1);
const totalPages = ref(1);

const hasUnread = computed(() => uiStore.adminUnreadNotificationCount > 0);

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    
    if (total <= 7) {
        const pages = [];
        for (let i = 1; i <= total; i++) pages.push(i);
        return pages;
    }
    
    if (current <= 4) {
        return [1, 2, 3, 4, 5, '...', total];
    }
    
    if (current >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }
    
    return [1, '...', current - 1, current, current + 1, '...', total];
});

const paymentLabels = {
  'unpaid': 'Chưa TT',
  'paid': 'Đã thanh toán',
  'pending': 'Đang xử lý',
  'failed': 'Thất bại',
  'refunded': 'Đã hoàn tiền',
  'partially_refunded': 'Hoàn 1 phần',
  'refund_pending': 'Chờ hoàn',
  'refund_failed': 'Hoàn lỗi'
};

const fulfillmentLabels = {
  'pending': 'Chờ duyệt',
  'confirmed': 'Đã duyệt',
  'processing': 'Đang xử lý',
  'packing': 'Đóng gói',
  'shipping': 'Đang giao',
  'delivered': 'Đã giao',
  'completed': 'Hoàn thành',
  'cancelled': 'Đã hủy',
  'return_requested': 'Yêu cầu hoàn',
  'return_approved': 'Đã duyệt hoàn',
  'return_rejected': 'Từ chối hoàn',
  'returned': 'Đã nhận hàng hoàn',
  'refunded': 'Đã hoàn tiền'
};

const getPaymentBadgeClass = (status) => {
    switch (status) {
        case 'paid': return 'text-success bg-success-subtle border border-success-subtle';
        case 'unpaid': return 'text-secondary bg-secondary-subtle border border-secondary-subtle';
        case 'failed': case 'refund_failed': return 'text-danger bg-danger-subtle border border-danger-subtle';
        default: return 'text-warning bg-warning-subtle border border-warning-subtle';
    }
};

const getFulfillmentBadgeClass = (status) => {
    switch (status) {
        case 'delivered': case 'completed': case 'returned': case 'refunded': return 'text-success bg-success-subtle border border-success-subtle';
        case 'cancelled': case 'return_rejected': return 'text-danger bg-danger-subtle border border-danger-subtle';
        case 'pending': return 'text-secondary bg-secondary-subtle border border-secondary-subtle';
        default: return 'text-primary bg-primary-subtle border border-primary-subtle';
    }
};

const fetchNotifications = async () => {
    try {
        const unreadOnly = filter.value === 'unread' ? 'true' : 'false';
        const res = await api.get('/admin/notifications', {
            params: {
                unread_only: unreadOnly,
                page: currentPage.value,
            },
        });
        if (res.data.success) {
            notifications.value = res.data.notifications;
            totalPages.value = res.data.last_page;
            uiStore.setAdminUnreadNotificationCount(res.data.unread_count);
        }
    } catch (error) {
        console.error('Lỗi khi tải thông báo', error);
    }
};

const markAllAsRead = async () => {
    try {
        await api.post('/admin/notifications/read-all');
        uiStore.setAdminUnreadNotificationCount(0);
        fetchNotifications();
    } catch (error) {
        console.error(error);
    }
};

const handleNotificationClick = async (noti) => {
    if (!noti.read_at) {
        try {
            noti.read_at = new Date().toISOString();
            uiStore.decrementAdminUnreadNotificationCount();
            await api.post(`/admin/notifications/${noti.id}/read`);
        } catch (error) {
            console.error(error);
        }
    }

    if (noti.data.url_redirect) {
        router.push(noti.data.url_redirect);
    } else if (noti.data.booking_id) {
        router.push({
            path: '/admin/court-bookings',
            query: { search: noti.data.booking_code }
        });
    }
};

const deleteNotification = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa thông báo?',
        text: 'Bạn có chắc muốn xóa thông báo này không?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/admin/notifications/${id}`);
            fetchNotifications();
        } catch (error) {
            Swal.fire('Lỗi', 'Không thể xóa thông báo', 'error');
        }
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        fetchNotifications();
    }
};

const formatTimeAgo = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) return 'Vừa xong';
    if (diffMin < 60) return `${diffMin} phút trước`;
    if (diffHour < 24) return `${diffHour} giờ trước`;
    if (diffDay < 7) return `${diffDay} ngày trước`;
    
    return date.toLocaleDateString('vi-VN');
};

watch(filter, () => {
    currentPage.value = 1;
    fetchNotifications();
});

onMounted(() => {
    fetchNotifications();
    window.addEventListener('admin-notification-received', fetchNotifications);
});

import { onUnmounted } from 'vue';
onUnmounted(() => {
    window.removeEventListener('admin-notification-received', fetchNotifications);
});
</script>

<style scoped>
.nav-pills .nav-link.active {
    background-color: var(--court-primary);
}
.nav-pills .nav-link {
    color: var(--text-muted);
}

.pagination {
    display: flex; justify-content: center; align-items: center; gap: 8px; padding: 10px 0; margin: 0;
}
.page-btn {
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
    border-radius: 8px; border: 1px solid #e2e8f0; background: #fff;
    font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; font-family: inherit;
}
.page-btn:hover:not(:disabled) { border-color: var(--primary, #E63B6F); color: var(--primary, #E63B6F); }
.page-btn.active { background: var(--primary, #E63B6F); color: white; border-color: var(--primary, #E63B6F); }
.page-btn:disabled { opacity: 0.5; cursor: not-allowed; background: #f8fafc; }
.page-dots { font-weight: 700; color: #64748b; padding: 0 4px; }
</style>
