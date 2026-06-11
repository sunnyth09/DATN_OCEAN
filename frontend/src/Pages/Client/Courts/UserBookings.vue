<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useCourtBookingStore } from '@/stores/useCourtBookingStore';
import { useAuthStore } from '@/stores/auth';
import Swal from 'sweetalert2';
import '@/assets/court-management.css';

import QRCode from 'qrcode';

const generateQrImgUrl = async (text, size = 220) => {
    try {
        return await QRCode.toDataURL(text, { width: size, margin: 2, color: { dark: '#1a1a2e', light: '#ffffff' } });
    } catch (err) {
        console.error("QR Code generation error", err);
        return '';
    }
};

const toast = {
    success: (msg) => Swal.fire({ icon: 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    error: (msg) => Swal.fire({ icon: 'error', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    warning: (msg) => Swal.fire({ icon: 'warning', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    info: (msg) => Swal.fire({ icon: 'info', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 })
};
import { useRouter } from 'vue-router';

const store = useCourtBookingStore();
const authStore = useAuthStore();
const router = useRouter();
let userChannel = null;

onMounted(async () => {
    await store.fetchUserBookings();
    subscribeUserChannel();
});

onUnmounted(() => {
    leaveUserChannel();
});

// Subscribe WebSocket channel - nhận thông báo realtime khi admin xác nhận/hủy
const subscribeUserChannel = () => {
    try {
        const userId = authStore.user?.user_id || authStore.user?.id;
        if (!userId || !window.Echo) return;

        userChannel = window.Echo.private(`user.${userId}`)
            .listen('.CourtBookingStatusChanged', (event) => {
                const statusLabels = {
                    confirmed: 'đã được xác nhận ✅',
                    cancelled: 'đã bị hủy ❌',
                    completed: 'đã hoàn thành ✅',
                    no_show: 'bị đánh dấu vắng mặt ⚠️',
                };
                const label = statusLabels[event.new_status] || `chuyển sang ${event.new_status}`;
                toast.info(`Lịch đặt sân #${event.booking_code} ${label}`);
                store.fetchUserBookings();
            })
            .listen('.CourtBookingCancelled', (event) => {
                toast.warning(`Lịch đặt sân #${event.booking_code} đã bị hủy`);
                store.fetchUserBookings();
            });
    } catch (e) {
        console.warn('UserBookings: WebSocket subscribe failed', e);
    }
};

const leaveUserChannel = () => {
    try {
        const userId = authStore.user?.user_id || authStore.user?.id;
        if (userId && window.Echo) {
            window.Echo.leave(`user.${userId}`);
        }
    } catch (e) {}
    userChannel = null;
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('vi-VN');
};

const formatTime = (timeStr) => {
    return timeStr ? timeStr.substring(0, 5) : '';
};

const getStatusBadge = (bookingOrStatus) => {
    const status = typeof bookingOrStatus === 'object' ? bookingOrStatus.status : bookingOrStatus;

    if (status === 'checked_in') {
        let isPlaying = false;
        if (typeof bookingOrStatus === 'object' && bookingOrStatus.booking_date && bookingOrStatus.start_time) {
            const now = new Date();
            const dateStr = String(bookingOrStatus.booking_date).split('T')[0];
            const startDateTime = new Date(`${dateStr}T${bookingOrStatus.start_time}`);
            if (now >= startDateTime) {
                isPlaying = true;
            }
        }
        return { 
            class: 'status-badge--checked_in', 
            text: isPlaying ? 'Đang chơi' : 'Đã check-in', 
            icon: isPlaying ? 'bi-play-circle' : 'bi-check2-all' 
        };
    }

    const badges = {
        'pending': { class: 'status-badge--pending', text: 'Chờ duyệt', icon: 'bi-hourglass-split' },
        'confirmed': { class: 'status-badge--confirmed', text: 'Đã xác nhận', icon: 'bi-check-circle' },
        'playing': { class: 'status-badge--checked_in', text: 'Đang chơi', icon: 'bi-play-circle' },
        'extended': { class: 'status-badge--extended', text: 'Đã gia hạn', icon: 'bi-clock-history' },
        'completed': { class: 'status-badge--completed', text: 'Hoàn thành', icon: 'bi-check-circle-fill' },
        'cancelled': { class: 'status-badge--cancelled', text: 'Đã hủy', icon: 'bi-x-circle' },
        'no_show': { class: 'status-badge--cancelled', text: 'Không đến', icon: 'bi-exclamation-circle' }
    };
    return badges[status] || { class: 'status-badge--pending', text: status, icon: 'bi-question-circle' };
};

const getStatusStep = (status) => {
    const steps = { pending: 1, confirmed: 2, checked_in: 3, completed: 4, cancelled: 0 };
    return steps[status] || 0;
};

const handleCancel = async (bookingId) => {
    const result = await Swal.fire({
        title: 'Hủy lịch đặt sân?',
        text: 'Bạn có chắc chắn muốn hủy lịch đặt sân này không?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Xác nhận hủy',
        cancelButtonText: 'Không, giữ lại'
    });
    
    if(result.isConfirmed) {
        try {
            await store.cancelBooking(bookingId, { reason: 'Khách hàng yêu cầu hủy' });
            toast.success('Hủy lịch thành công');
            await store.fetchUserBookings();
        } catch (e) {
            if(e.response?.data?.message) {
                toast.error(e.response.data.message);
            }
        }
    }
};

const handlePay = async (booking) => {
    const result = await Swal.fire({
        title: 'Ghi nhận thanh toán?',
        text: `Thanh toán ${formatCurrency(booking.total_amount || 0)} cho booking này`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Đã chuyển khoản',
        cancelButtonText: 'Để sau'
    });

    if (!result.isConfirmed) return;

    try {
        await store.payBooking(booking.booking_id || booking.id, {
            payment_method: 'bank_transfer',
            payment_type: 'full',
            amount: booking.total_amount,
            note: 'Customer bank transfer confirmation'
        });
        toast.success('Đã ghi nhận thanh toán');
        await store.fetchUserBookings();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Thanh toán thất bại');
    }
};

const showQr = async (booking) => {
    try {
        const res = await store.getBookingQr(booking.booking_id || booking.id);
        const qrToken = res?.data?.data?.qr_token || res?.data?.qr_token || '';
        const bookingCode = res?.data?.data?.booking_code || booking.booking_code || '';

        if (!qrToken) {
            toast.error('Không lấy được mã QR');
            return;
        }

        // Tạo ảnh QR
        const qrImgUrl = await generateQrImgUrl(qrToken, 220);

        await Swal.fire({
            title: '🏸 Mã QR Check-in',
            html: `
                <div style="text-align:center;padding:12px 0">
                    <img
                        src="${qrImgUrl}"
                        alt="QR Code check-in"
                        style="width:220px;height:220px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.15);border:2px solid #e9ecef"
                        onerror="this.onerror=null;this.src='';this.parentElement.innerHTML='<div style=\'color:#dc3545;font-size:13px\'>Kh\u00f4ng t\u1ea3i đ\u01b0\u1ee3c QR, vui l\u00f2ng th\u1eed l\u1ea1i</div>'"
                    />
                    <div style="margin-top:14px;font-family:monospace;font-size:13px;color:#6c757d;background:#f8f9fa;padding:8px 12px;border-radius:8px;word-break:break-all">
                        <strong>Mã đặt sân:</strong> ${bookingCode}
                    </div>
                    <p style="margin-top:10px;font-size:13px;color:#6c757d">Đưa mã này cho nhân viên quét khi đến sân</p>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: 'Đóng',
            confirmButtonColor: '#0d6efd',
            width: 360,
        });
    } catch (e) {
        console.error('QR error:', e);
        toast.error('Không lấy được mã QR');
    }
};

const goToDetail = (courtId) => {
    router.push({ name: 'court-detail', params: { id: courtId } });
};
</script>

<template>
    <div class="card border-0 rounded-4" style="box-shadow: var(--court-ambient-shadow);">
        <div class="card-body p-4 p-md-5">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-journal-bookmark me-2" style="color: var(--court-primary);"></i>
                        Lịch Sử Đặt Sân
                    </h4>
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Theo dõi tất cả đơn đặt sân của bạn</p>
                </div>
                <button class="btn px-3 py-2 rounded-pill fw-semibold" 
                        style="background: var(--court-primary); color: #fff; font-size: 0.85rem; border: none;"
                        @click="router.push({ name: 'courts-list' })">
                    <i class="bi bi-plus-lg me-1"></i> Đặt Sân Mới
                </button>
            </div>

            <!-- Loading -->
            <div v-if="store.loading" class="text-center py-5">
                <div class="spinner-border mb-2" style="color: var(--court-primary);" role="status"></div>
                <p class="text-muted" style="font-size: 0.9rem;">Đang tải lịch sử...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="store.userBookings.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-journal-x"></i></div>
                <div class="court-empty-state__title">Bạn chưa có lịch đặt sân nào</div>
                <div class="court-empty-state__text">Hãy đặt sân đầu tiên để bắt đầu trải nghiệm</div>
                <button class="btn mt-3 px-4 py-2 rounded-pill fw-bold" 
                        style="background: var(--court-primary); color: #fff; border: none;"
                        @click="router.push({ name: 'courts-list' })">
                    <i class="bi bi-trophy me-1"></i> Đặt Sân Ngay
                </button>
            </div>

            <!-- Bookings List -->
            <div v-else class="d-flex flex-column gap-4">
                <div v-for="booking in store.userBookings" :key="booking.booking_id || booking.id" 
                     class="booking-history-card" :class="'booking-history-card--' + booking.status">
                    
                    <!-- Card Header -->
                    <div class="d-flex justify-content-between align-items-center py-3 px-4" style="background: var(--court-section-bg, #f8f9fb); border-bottom: 1px solid rgba(0,0,0,0.04);">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold" style="font-size: 0.9rem;">Mã Booking: {{ booking.booking_code || `#${booking.booking_id || booking.id}` }}</span>
                        </div>
                        <span class="status-badge" :class="getStatusBadge(booking).class">
                            <i :class="getStatusBadge(booking).icon" style="font-size: 0.7rem;"></i>
                            {{ getStatusBadge(booking).text }}
                        </span>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="p-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <!-- Court icon -->
                                    <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" 
                                         style="width: 48px; height: 48px; background: var(--court-primary-soft, rgba(230,59,111,0.08));">
                                        <i class="bi bi-geo-alt-fill" style="color: var(--court-primary); font-size: 1.2rem;"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-1" style="font-size: 1.05rem;">{{ booking.court?.court_name || booking.court?.name || 'Sân Cầu Lông' }}</h5>
                                        <div class="d-flex flex-wrap gap-2 text-muted" style="font-size: 0.85rem;">
                                            <span class="d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-calendar-event"></i> {{ formatDate(booking.booking_date) }}
                                            </span>
                                            <span>•</span>
                                            <span class="d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-clock"></i> {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress Steps (only for non-cancelled) -->
                                <div v-if="booking.status !== 'cancelled'" class="d-flex align-items-center gap-1 ms-5 ps-3 mt-2" style="font-size: 0.7rem;">
                                    <template v-for="(step, i) in [{l: 'Đặt', s: 1}, {l: 'Xác nhận', s: 2}, {l: 'Chơi', s: 3}, {l: 'Xong', s: 4}]" :key="i">
                                        <span class="d-flex align-items-center justify-content-center rounded-circle" 
                                              :style="{
                                                  width: '18px', height: '18px', 
                                                  background: getStatusStep(booking.status) >= step.s ? 'var(--court-primary)' : '#dee2e6',
                                                  color: getStatusStep(booking.status) >= step.s ? '#fff' : '#adb5bd',
                                                  fontSize: '0.55rem', fontWeight: '700'
                                              }">
                                            {{ step.s }}
                                        </span>
                                        <span :style="{ color: getStatusStep(booking.status) >= step.s ? 'var(--court-primary)' : '#adb5bd', fontWeight: '600' }">
                                            {{ step.l }}
                                        </span>
                                        <span v-if="i < 3" style="width: 16px; height: 1px; display: block;" 
                                              :style="{ background: getStatusStep(booking.status) > step.s ? 'var(--court-primary)' : '#dee2e6' }"></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <div class="text-muted mb-1" style="font-size: 0.78rem;">Tổng tiền</div>
                                <h4 class="fw-bold mb-0" style="color: var(--court-primary);">{{ formatCurrency(booking.total_amount) }}</h4>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Footer -->
                    <div class="d-flex justify-content-end gap-2 p-3 px-4" style="border-top: 1px solid rgba(0,0,0,0.04);">
                        <button class="btn btn-sm px-4 py-2 rounded-pill fw-semibold" 
                                style="border: 1.5px solid var(--court-primary); color: var(--court-primary); background: transparent; font-size: 0.8rem;"
                                @click="goToDetail(booking.court_id)">
                            <i class="bi bi-arrow-repeat me-1"></i> Đặt lại sân này
                        </button>
                        <button
                            v-if="booking.payment_status !== 'paid' && booking.status !== 'cancelled'"
                            class="btn btn-sm px-4 py-2 rounded-pill fw-semibold"
                            style="border: 1.5px solid var(--court-available); color: var(--court-available); background: transparent; font-size: 0.8rem;"
                            @click="handlePay(booking)"
                        >
                            <i class="bi bi-credit-card me-1"></i> Thanh toán
                        </button>
                        <button
                            v-if="['pending', 'confirmed'].includes(booking.status)"
                            class="btn btn-sm px-4 py-2 rounded-pill fw-semibold"
                            style="border: 1.5px solid var(--court-playing); color: var(--court-playing); background: transparent; font-size: 0.8rem;"
                            @click="showQr(booking)"
                        >
                            <i class="bi bi-qr-code me-1"></i> QR check-in
                        </button>
                        <button 
                            v-if="booking.status === 'pending' || booking.status === 'confirmed'" 
                            class="btn btn-sm px-4 py-2 rounded-pill fw-semibold"
                            style="border: 1.5px solid var(--court-closed); color: var(--court-closed); background: transparent; font-size: 0.8rem;"
                            @click="handleCancel(booking.booking_id || booking.id)"
                        >
                            <i class="bi bi-x-lg me-1"></i> Hủy Đặt Sân
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 768px) {
    .col-md-4.text-md-end {
        border-left: 1px solid var(--border-color, rgba(0,0,0,0.06));
        padding-left: 20px;
    }
}
</style>
