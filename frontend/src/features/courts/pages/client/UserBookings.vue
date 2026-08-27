<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useCourtBookingStore } from '@/features/courts/stores/useCourtBookingStore';
import { useAuthStore } from '@/stores/auth';
import Swal from 'sweetalert2';
import '@/features/courts/assets/court-management.css';

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

import { playNotificationSound } from '@/utils/sound';

// Subscribe WebSocket channel - nhận thông báo realtime khi admin xác nhận/hủy
const subscribeUserChannel = () => {
    try {
        const userId = authStore.user?.user_id || authStore.user?.id;
        if (!userId || !window.Echo) return;

        userChannel = window.Echo.private(`user.${userId}`)
            .listen('.CourtBookingStatusChanged', (event) => {
                playNotificationSound();
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
                playNotificationSound();
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
        <div class="card-body p-3 p-md-5">
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

            <!-- Modern Skeleton Loading -->
            <div v-if="store.loading" class="d-flex flex-column gap-4">
                <div v-for="i in 3" :key="i" class="skeleton-booking-card">
                    <div class="skeleton-booking-header">
                        <div class="skeleton-box" style="width: 160px; height: 18px; border-radius: 4px;"></div>
                        <div class="skeleton-box" style="width: 100px; height: 24px; border-radius: 20px;"></div>
                    </div>
                    <div class="skeleton-booking-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="skeleton-box" style="width: 50%; height: 22px; border-radius: 4px; margin-bottom: 10px;"></div>
                                <div class="skeleton-box" style="width: 70%; height: 14px; border-radius: 4px; margin-bottom: 8px;"></div>
                                <div class="skeleton-box" style="width: 40%; height: 14px; border-radius: 4px;"></div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="skeleton-box" style="width: 90px; height: 24px; border-radius: 4px; margin-bottom: 8px; margin-left: auto;"></div>
                                <div class="skeleton-box" style="width: 120px; height: 14px; border-radius: 4px; margin-left: auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>
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
            <div v-else class="d-flex flex-column gap-3 gap-md-4">
                <div v-for="booking in store.userBookings" :key="booking.booking_id || booking.id" 
                     class="booking-history-card" :class="'booking-history-card--' + booking.status">
                    
                    <!-- Card Header -->
                    <div class="booking-card-header">
                        <div class="booking-code-wrap">
                            <span class="booking-code-label">Mã Booking:</span>
                            <span class="booking-code-val">{{ booking.booking_code || `#${booking.booking_id || booking.id}` }}</span>
                        </div>
                        <span class="status-badge" :class="getStatusBadge(booking).class">
                            <i :class="getStatusBadge(booking).icon"></i>
                            {{ getStatusBadge(booking).text }}
                        </span>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="booking-card-body">
                        <div class="row align-items-center g-3">
                            <div class="col-md-8">
                                <div class="booking-court-info">
                                    <!-- Court icon -->
                                    <div class="court-avatar">
                                        <i class="bi bi-geo-alt-fill"></i>
                                    </div>
                                    <div class="court-meta">
                                        <h5 class="court-name">{{ booking.court?.court_name || booking.court?.name || 'Sân Cầu Lông' }}</h5>
                                        <div class="court-datetime">
                                            <span class="dt-tag">
                                                <i class="bi bi-calendar-event"></i> {{ formatDate(booking.booking_date) }}
                                            </span>
                                            <span class="dt-dot">•</span>
                                            <span class="dt-tag">
                                                <i class="bi bi-clock"></i> {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress Steps (only for non-cancelled) -->
                                <div v-if="booking.status !== 'cancelled'" class="booking-stepper">
                                    <template v-for="(step, i) in [{l: 'Đặt', s: 1}, {l: 'Xác nhận', s: 2}, {l: 'Chơi', s: 3}, {l: 'Xong', s: 4}]" :key="i">
                                        <div class="stepper-node" :class="{ active: getStatusStep(booking.status) >= step.s }">
                                            <span class="step-num">{{ step.s }}</span>
                                            <span class="step-text">{{ step.l }}</span>
                                        </div>
                                        <div v-if="i < 3" class="stepper-line" :class="{ active: getStatusStep(booking.status) > step.s }"></div>
                                    </template>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-4 text-md-end">
                                <div class="booking-amount-box">
                                    <div class="amount-label">Tổng tiền</div>
                                    <div class="amount-value">{{ formatCurrency(booking.total_amount) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Footer Actions -->
                    <div class="booking-card-actions">
                        <button class="booking-btn booking-btn--repeat" @click="goToDetail(booking.court_id)">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Đặt lại sân</span>
                        </button>
                        <button
                            v-if="booking.payment_status !== 'paid' && booking.status !== 'cancelled'"
                            class="booking-btn booking-btn--pay"
                            @click="handlePay(booking)"
                        >
                            <i class="bi bi-credit-card"></i>
                            <span>Thanh toán</span>
                        </button>
                        <button
                            v-if="['pending', 'confirmed', 'checked_in'].includes(booking.status)"
                            class="booking-btn booking-btn--qr"
                            @click="showQr(booking)"
                        >
                            <i class="bi bi-qr-code"></i>
                            <span>QR check-in</span>
                        </button>
                        <button 
                            v-if="booking.status === 'pending' || booking.status === 'confirmed'" 
                            class="booking-btn booking-btn--cancel"
                            @click="handleCancel(booking.booking_id || booking.id)"
                        >
                            <i class="bi bi-x-lg"></i>
                            <span>Hủy Đặt Sân</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.card {
    font-family: var(--font-inter, 'Inter', sans-serif);
}

.booking-history-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #eef2f6;
    border-left: 4.5px solid transparent;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}
.booking-history-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.booking-history-card--pending   { border-left-color: var(--court-pending, #f59e0b); }
.booking-history-card--confirmed { border-left-color: var(--court-booked, #3b82f6); }
.booking-history-card--checked_in { border-left-color: var(--court-playing, #10b981); }
.booking-history-card--completed { border-left-color: var(--court-completed, #6366f1); }
.booking-history-card--cancelled { border-left-color: var(--court-closed, #ef4444); }

/* Header */
.booking-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    gap: 10px;
    flex-wrap: wrap;
}
.booking-code-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.88rem;
    flex-wrap: wrap;
}
.booking-code-label {
    color: #64748b;
    font-weight: 500;
}
.booking-code-val {
    color: #0f172a;
    font-weight: 700;
    word-break: break-all;
}

/* Body */
.booking-card-body {
    padding: 18px;
}

.booking-court-info {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.court-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(230, 59, 111, 0.08);
    color: var(--court-primary, #e63b6f);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.court-meta {
    flex: 1;
    min-width: 0;
}
.court-name {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px;
}
.court-datetime {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 0.84rem;
    color: #64748b;
}
.dt-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
}
.dt-dot {
    color: #cbd5e1;
}

/* Stepper */
.booking-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 14px;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
}
.stepper-node {
    display: flex;
    align-items: center;
    gap: 5px;
}
.step-num {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #94a3b8;
    font-size: 0.68rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.step-text {
    font-size: 0.76rem;
    font-weight: 600;
    color: #94a3b8;
    transition: all 0.2s;
}
.stepper-node.active .step-num {
    background: var(--court-primary, #e63b6f);
    color: #fff;
}
.stepper-node.active .step-text {
    color: var(--court-primary, #e63b6f);
    font-weight: 700;
}
.stepper-line {
    flex: 1;
    height: 2px;
    background: #e2e8f0;
    margin: 0 8px;
    border-radius: 999px;
    transition: all 0.2s;
}
.stepper-line.active {
    background: var(--court-primary, #e63b6f);
}

/* Amount Row */
.booking-amount-box {
    display: flex;
    flex-direction: column;
}
.amount-label {
    font-size: 0.78rem;
    color: #64748b;
    margin-bottom: 2px;
}
.amount-value {
    font-size: 1.28rem;
    font-weight: 800;
    color: var(--court-primary, #e63b6f);
    letter-spacing: -0.3px;
}

@media (min-width: 768px) {
    .col-md-4.text-md-end {
        border-left: 1px solid var(--border-color, rgba(0,0,0,0.06));
        padding-left: 20px;
    }
}

/* Actions Footer */
.booking-card-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    background: #fbfcfd;
    border-top: 1px solid #f1f5f9;
    flex-wrap: wrap;
}

.booking-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1.5px solid transparent;
    outline: none;
    font-family: inherit;
    white-space: nowrap;
    text-decoration: none;
    box-sizing: border-box;
}
.booking-btn:active {
    transform: scale(0.97);
}

.booking-btn--repeat {
    border-color: #e2e8f0;
    color: #475569;
    background: #ffffff;
}
.booking-btn--repeat:hover {
    border-color: var(--court-primary, #e63b6f);
    color: var(--court-primary, #e63b6f);
    background: rgba(230, 59, 111, 0.04);
}

.booking-btn--pay {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
}
.booking-btn--pay:hover {
    background: #059669;
    border-color: #059669;
}

.booking-btn--qr {
    background: rgba(14, 165, 233, 0.08);
    border-color: #38bdf8;
    color: #0284c7;
    font-weight: 700;
}
.booking-btn--qr:hover {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
}

.booking-btn--cancel {
    border-color: #fecdd3;
    color: #e11d48;
    background: #fff1f2;
    font-weight: 600;
}
.booking-btn--cancel:hover {
    background: #ffe4e6;
    border-color: #fda4af;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .booking-card-header {
        padding: 10px 14px;
    }
    .booking-card-body {
        padding: 14px;
    }
    .booking-court-info {
        gap: 10px;
    }
    .court-name {
        font-size: 0.98rem;
    }
    .court-datetime {
        font-size: 0.78rem;
    }
    .booking-stepper {
        padding: 8px 10px;
        margin-top: 10px;
    }
    .step-text {
        font-size: 0.7rem;
    }
    .stepper-line {
        margin: 0 4px;
    }
    .booking-amount-box {
        flex-direction: row;
        justify-content: space-between;
        align-items: baseline;
        padding-top: 10px;
        border-top: 1px dashed #e2e8f0;
    }
    .booking-card-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 12px 14px;
    }
    .booking-btn {
        width: 100%;
        min-height: 42px;
        padding: 8px 6px;
        font-size: 0.8rem;
        border-radius: 8px;
    }
    .booking-card-actions > .booking-btn:only-child {
        grid-column: span 2;
    }
}

/* ===== Modern Skeleton Loading Styles ===== */
.skeleton-booking-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #eef2f6;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    pointer-events: none;
}

.skeleton-booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: #f8f9fb;
    border-bottom: 1px solid rgba(0,0,0,0.04);
}

.skeleton-booking-body {
    padding: 20px;
}

.skeleton-box {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-box::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0,
    rgba(255, 255, 255, 0.4) 30%,
    rgba(255, 255, 255, 0.75) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
