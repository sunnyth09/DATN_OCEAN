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
                            <h6 class="mb-1 fw-bold" :class="{'text-dark': !noti.read_at, 'text-muted': noti.read_at}">{{ noti.data.title }}</h6>
                            <p class="mb-1 text-secondary" style="font-size: 0.9rem;">{{ noti.data.message }}</p>
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
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-center" v-if="totalPages > 1">
                <ul class="pagination pagination-sm m-0">
                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                        <a class="page-link" href="#" @click.prevent="changePage(currentPage - 1)">Trang trước</a>
                    </li>
                    <li class="page-item" v-for="p in totalPages" :key="p" :class="{ active: p === currentPage }">
                        <a class="page-link" href="#" @click.prevent="changePage(p)">{{ p }}</a>
                    </li>
                    <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                        <a class="page-link" href="#" @click.prevent="changePage(currentPage + 1)">Trang sau</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
<<<<<<< HEAD
import axios from '@/axios.js';
=======
import api from '@/axios';
>>>>>>> origin/Dev
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const router = useRouter();
const notifications = ref([]);
const filter = ref('all');
const currentPage = ref(1);
const totalPages = ref(1);
const unreadCount = ref(0);

const hasUnread = computed(() => unreadCount.value > 0);

const fetchNotifications = async () => {
    try {
        const unreadOnly = filter.value === 'unread' ? 'true' : 'false';
<<<<<<< HEAD
        const res = await axios.get(`/admin/notifications?unread_only=${unreadOnly}&page=${currentPage.value}`);
=======
        const res = await api.get('/admin/notifications', {
            params: {
                unread_only: unreadOnly,
                page: currentPage.value,
            },
        });
>>>>>>> origin/Dev
        if (res.data.success) {
            notifications.value = res.data.notifications;
            totalPages.value = res.data.last_page;
            unreadCount.value = res.data.unread_count;
        }
    } catch (error) {
        console.error('Lỗi khi tải thông báo', error);
    }
};

const markAllAsRead = async () => {
    try {
<<<<<<< HEAD
        await axios.post('/admin/notifications/read-all');
=======
        await api.post('/admin/notifications/read-all');
>>>>>>> origin/Dev
        unreadCount.value = 0;
        fetchNotifications();
    } catch (error) {
        console.error(error);
    }
};

const handleNotificationClick = async (noti) => {
    if (!noti.read_at) {
        try {
<<<<<<< HEAD
            await axios.post(`/admin/notifications/${noti.id}/read`);
=======
            await api.post(`/admin/notifications/${noti.id}/read`);
>>>>>>> origin/Dev
            noti.read_at = new Date().toISOString();
            unreadCount.value--;
        } catch (error) {
            console.error(error);
        }
    }

    if (noti.data.booking_id) {
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
<<<<<<< HEAD
            await axios.delete(`/admin/notifications/${id}`);
=======
            await api.delete(`/admin/notifications/${id}`);
>>>>>>> origin/Dev
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
});
</script>

<style scoped>
.nav-pills .nav-link.active {
    background-color: var(--court-primary);
}
.nav-pills .nav-link {
    color: var(--text-muted);
}
</style>
