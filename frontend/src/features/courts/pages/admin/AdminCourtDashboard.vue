<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useCourtBookingStore } from '@/features/courts/stores/useCourtBookingStore';
import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import '@/features/courts/assets/court-management.css';

const store = useCourtBookingStore();
const toast = {
    success: (msg) => Swal.fire({ icon: 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    warning: (msg) => Swal.fire({ icon: 'warning', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    error: (msg) => Swal.fire({ icon: 'error', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
};

const filterDate = ref(new Date().toISOString().split('T')[0]);
const serverTime = ref('');
const schedulerScrollContainer = ref(null);
let pollingInterval = null;
let nowInterval = null;
let clockInterval = null;
let fetchInFlight = null;
let adminChannel = null;   // WebSocket channel
const nowMinutes = ref(0); // minutes since midnight

// Real-time clock
const liveHours = ref(0);
const liveMinutes = ref(0);
const liveSeconds = ref(0);

// POS Quick Booking
const posForm = ref({
    court_id: '',
    booking_date: '',
    start_time: '07:00',
    end_time: '09:00',
    payment_method: 'cash',
    note: ''
});

const openCreatePosModal = () => {
    posForm.value.booking_date = filterDate.value;
    const el = document.getElementById('posQuickModal');
    if (el) Modal.getOrCreateInstance(el).show();
};

// Clicked booking detail
const selectedBooking = ref(null);

// Schedule config: operating hours
const OPEN_HOUR = 5;   // 05:00
const CLOSE_HOUR = 23; // 23:00
const TOTAL_HOURS = CLOSE_HOUR - OPEN_HOUR;

// Generate hour labels for the timeline header
const hourLabels = computed(() => {
    const labels = [];
    for (let h = OPEN_HOUR; h <= CLOSE_HOUR; h++) {
        labels.push(`${h.toString().padStart(2, '0')}:00`);
    }
    return labels;
});

// Current time position as percentage of the timeline
const nowLinePercent = computed(() => {
    const mins = nowMinutes.value;
    const startMins = OPEN_HOUR * 60;
    const endMins = CLOSE_HOUR * 60;
    if (mins < startMins || mins > endMins) return -1; // hide
    return ((mins - startMins) / (endMins - startMins)) * 100;
});

// Live clock formatted
const liveClockDisplay = computed(() => {
    return `${String(liveHours.value).padStart(2, '0')}:${String(liveMinutes.value).padStart(2, '0')}:${String(liveSeconds.value).padStart(2, '0')}`;
});

// Day progress: percentage of operating hours elapsed
const dayProgressPercent = computed(() => {
    if (!isToday.value) return 0;
    const mins = liveHours.value * 60 + liveMinutes.value;
    const startMins = OPEN_HOUR * 60;
    const endMins = CLOSE_HOUR * 60;
    if (mins <= startMins) return 0;
    if (mins >= endMins) return 100;
    return Math.round(((mins - startMins) / (endMins - startMins)) * 1000) / 10; // 1 decimal
});

// Remaining time in operating day
const remainingTimeDisplay = computed(() => {
    if (!isToday.value) return '';
    const mins = liveHours.value * 60 + liveMinutes.value;
    const endMins = CLOSE_HOUR * 60;
    const remaining = endMins - mins;
    if (remaining <= 0) return 'Đã hết giờ';
    const h = Math.floor(remaining / 60);
    const m = remaining % 60;
    return `Còn ${h}h${m > 0 ? String(m).padStart(2, '0') + 'p' : ''}`;
});

// Is today?
const isToday = computed(() => filterDate.value === new Date().toISOString().split('T')[0]);

// Courts from dashboard data
const courtsData = computed(() => store.courtDashboard?.courts || []);
const dashboardStats = computed(() => store.courtDashboard?.stats || {});

// Calendar items grouped by court
const calendarItems = computed(() => store.courtCalendar?.items || []);
const bookingsByCourt = computed(() => {
    const map = {};
    calendarItems.value.forEach(b => {
        const courtId = b.court_id;
        if (!map[courtId]) map[courtId] = [];
        map[courtId].push(b);
    });
    return map;
});

onMounted(async () => {
    updateNow();
    updateClock();
    nowInterval  = setInterval(updateNow, 30000);  // update timeline every 30s
    clockInterval = setInterval(updateClock, 1000); // update clock every 1s
    await fetchAll();
    // Polling fallback (60s) - WebSocket là primary source
    pollingInterval = setInterval(fetchAll, 60000);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    // Scroll to current time
    nextTick(() => scrollToNow());
    // Subscribe WebSocket channel để nhận event thời gian thực
    subscribeAdminChannel();
});

onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
    if (nowInterval)     clearInterval(nowInterval);
    if (clockInterval)   clearInterval(clockInterval);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    leaveAdminChannel();
});

// ---- WebSocket: nhận realtime events từ admin-notifications channel ----
const subscribeAdminChannel = () => {
    try {
        if (!window.Echo) return;
        adminChannel = window.Echo.private('admin-notifications')
            .listen('.CourtBookingCreated', () => fetchAll())
            .listen('.CourtBookingStatusChanged', () => fetchAll())
            .listen('.CourtBookingCancelled', () => fetchAll())
            .listen('.CourtBookingPaymentUpdated', () => fetchAll())
            .listen('.CourtBookingServiceAdded', () => fetchAll())
            .listen('.CourtSlotLocked', () => fetchAll())
            .listen('.CourtSlotReleased', () => fetchAll());
    } catch (e) {
        console.warn('AdminDashboard: WebSocket subscribe failed, using polling fallback', e);
    }
};

const leaveAdminChannel = () => {
    try {
        if (window.Echo) {
            window.Echo.leave('admin-notifications');
        }
    } catch (e) {}
    adminChannel = null;
};

const updateNow = () => {
    const now = new Date();
    nowMinutes.value = now.getHours() * 60 + now.getMinutes();
};

const updateClock = () => {
    const now = new Date();
    liveHours.value = now.getHours();
    liveMinutes.value = now.getMinutes();
    liveSeconds.value = now.getSeconds();
};

const fetchAll = async () => {
    if (document.hidden) return;
    if (fetchInFlight) return fetchInFlight;

    fetchInFlight = Promise.all([
        store.fetchCourtDashboard({ date: filterDate.value }),
        store.fetchCourtCalendar({ date: filterDate.value, mode: 'day' }),
        store.fetchAdminCourts(),
    ]).then(() => {
        serverTime.value = store.courtDashboard?.server_time || '';
    }).finally(() => {
        fetchInFlight = null;
    });

    return fetchInFlight;
};

const handleVisibilityChange = () => {
    if (document.hidden) return;
    updateNow();
    updateClock();
    fetchAll();
};

const scrollToNow = () => {
    if (!schedulerScrollContainer.value || !isToday.value) return;
    const pct = nowLinePercent.value;
    if (pct < 0) return;
    const totalWidth = schedulerScrollContainer.value.scrollWidth;
    const scrollTarget = (pct / 100) * totalWidth - schedulerScrollContainer.value.clientWidth / 3;
    schedulerScrollContainer.value.scrollTo({ left: Math.max(0, scrollTarget), behavior: 'smooth' });
};

// Convert time string (HH:MM:SS or HH:MM) to position percentage on timeline
const timeToPercent = (timeStr) => {
    if (!timeStr) return 0;
    const parts = timeStr.split(':');
    const mins = parseInt(parts[0]) * 60 + parseInt(parts[1]);
    const startMins = OPEN_HOUR * 60;
    const endMins = CLOSE_HOUR * 60;
    return ((mins - startMins) / (endMins - startMins)) * 100;
};

// Width of a booking block as percentage
const bookingWidth = (startTime, endTime) => {
    return timeToPercent(endTime) - timeToPercent(startTime);
};

// Booking block color based on status
const bookingBlockColor = (status) => {
    const map = {
        'pending': { bg: 'rgba(255, 193, 7, 0.15)', border: '#ffc107', text: '#856404' },
        'confirmed': { bg: 'rgba(13, 110, 253, 0.12)', border: '#0d6efd', text: '#084298' },
        'checked_in': { bg: 'rgba(220, 53, 69, 0.12)', border: '#dc3545', text: '#842029' },
        'playing': { bg: 'rgba(220, 53, 69, 0.12)', border: '#dc3545', text: '#842029' },
        'extended': { bg: 'rgba(111, 66, 193, 0.12)', border: '#6f42c1', text: '#59359a' },
        'completed': { bg: 'rgba(25, 135, 84, 0.08)', border: '#198754', text: '#0f5132' },
        'cancelled': { bg: 'rgba(108, 117, 125, 0.08)', border: '#6c757d', text: '#495057' },
        'expired': { bg: 'rgba(108, 117, 125, 0.08)', border: '#6c757d', text: '#495057' },
    };
    return map[status] || map['confirmed'];
};

const formatTime = (t) => t ? t.substring(0, 5) : '';
const formatCurrency = (v) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0);

const getStatusText = (bookingOrStatus) => {
    if (!bookingOrStatus) return '';
    const status = typeof bookingOrStatus === 'object' ? bookingOrStatus.status : bookingOrStatus;
    
    if (status === 'checked_in') {
        if (typeof bookingOrStatus === 'object' && bookingOrStatus.booking_date && bookingOrStatus.start_time) {
            const now = new Date();
            const dateStr = String(bookingOrStatus.booking_date).split('T')[0];
            const startDateTime = new Date(`${dateStr}T${bookingOrStatus.start_time}`);
            if (now >= startDateTime) {
                return 'Đang chơi';
            }
        }
        return 'Đã check-in';
    }

    const map = {
        'pending': 'Chờ duyệt', 'confirmed': 'Đã xác nhận',
        'playing': 'Đang chơi', 'extended': 'Gia hạn', 'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy', 'no_show': 'Không đến'
    };
    map.expired = 'Hết hạn';
    return map[status] || status;
};

const formatDateDisplay = computed(() => {
    const d = new Date(filterDate.value + 'T00:00:00');
    const days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    const months = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
        'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
    return `${days[d.getDay()]}, ${months[d.getMonth()]} ${d.getDate()}`;
});

const changeDate = (delta) => {
    const d = new Date(filterDate.value);
    d.setDate(d.getDate() + delta);
    filterDate.value = d.toISOString().split('T')[0];
    fetchAll();
};

// Click on booking block
const handleBookingClick = async (booking) => {
    try {
        await store.fetchAdminBookingDetail(booking.booking_id || booking.id);
        selectedBooking.value = store.currentBooking;
        const el = document.getElementById('bookingDetailModal');
        if (el) Modal.getOrCreateInstance(el).show();
    } catch (e) {}
};

// Click on empty slot to create booking
const handleSlotClick = (court, hourOffset) => {
    const startHour = OPEN_HOUR + Math.floor(hourOffset);
    const startMins = (hourOffset % 1) === 0.5 ? 30 : 0;
    const startTimeStr = `${startHour.toString().padStart(2, '0')}:${startMins.toString().padStart(2, '0')}`;

    // Default duration: 1 hour (2 slots of 30m)
    let endHour = startHour + 1;
    let endMins = startMins;
    if (endHour > CLOSE_HOUR || (endHour === CLOSE_HOUR && endMins > 0)) {
        endHour = CLOSE_HOUR;
        endMins = 0;
    }
    const endTimeStr = `${endHour.toString().padStart(2, '0')}:${endMins.toString().padStart(2, '0')}`;

    posForm.value = {
        court_id: court.court_id,
        booking_date: filterDate.value,
        start_time: startTimeStr,
        end_time: endTimeStr,
        payment_method: 'cash',
        note: ''
    };
    const el = document.getElementById('posQuickModal');
    if (el) Modal.getOrCreateInstance(el).show();
};

const closeModal = (id) => {
    const el = document.getElementById(id);
    if (el) { const m = Modal.getInstance(el); if (m) m.hide(); }
};

// Actions
const handleCheckIn = async (bookingId) => {
    const result = await Swal.fire({
        title: 'Xác nhận Check-in', text: 'Khách hàng đã có mặt?', icon: 'question',
        showCancelButton: true, confirmButtonColor: '#198754', confirmButtonText: 'Check-in', cancelButtonText: 'Hủy'
    });
    if (result.isConfirmed) {
        try {
            await store.adminCheckIn(bookingId);
            toast.success('Check-in thành công');
            closeModal('bookingDetailModal');
            fetchAll();
        } catch (e) {}
    }
};

const buildCheckOutPayload = async (booking) => {
    const amountDue = Math.max(Number(booking?.total_amount || 0) - Number(booking?.paid_amount || 0), 0);
    if (amountDue <= 0) {
        return {};
    }

    const payment = await Swal.fire({
        title: 'Thu tien con lai',
        html: `Booking con <b>${formatCurrency(amountDue)}</b> chua thanh toan.`,
        input: 'select',
        inputOptions: {
            cash: 'Tien mat',
            bank_transfer: 'Chuyen khoan',
            pos_card: 'The POS',
            pos_transfer: 'POS transfer'
        },
        inputPlaceholder: 'Chon phuong thuc thanh toan',
        showCancelButton: true,
        confirmButtonText: 'Xac nhan thu tien',
        cancelButtonText: 'Huy',
        inputValidator: (value) => !value ? 'Vui long chon phuong thuc thanh toan' : undefined
    });

    if (!payment.isConfirmed) {
        return null;
    }

    return {
        payment_method: payment.value,
        note: 'Checkout payment'
    };
};

const handleCheckOut = async (bookingOrId) => {
    const booking = typeof bookingOrId === 'object' ? bookingOrId : selectedBooking.value;
    const bookingId = booking?.booking_id || booking?.id || bookingOrId;
    const payload = await buildCheckOutPayload(booking);
    if (payload === null) {
        return;
    }

    const result = await Swal.fire({
        title: 'Check-out & Thanh toán', text: 'Trả sân và hoàn tất?', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Check-out', cancelButtonText: 'Hủy'
    });
    if (result.isConfirmed) {
        try {
            await store.adminCheckOut(bookingId, payload);
            toast.success('Check-out thành công');
            closeModal('bookingDetailModal');
            fetchAll();
        } catch (e) {}
    }
};

const handleConfirm = async (bookingId) => {
    try {
        await store.confirmBooking(bookingId);
        toast.success('Đã xác nhận booking');
        closeModal('bookingDetailModal');
        fetchAll();
    } catch (e) {}
};

const handleCreatePosBooking = async () => {
    if (!posForm.value.court_id || !posForm.value.start_time || !posForm.value.end_time) {
        toast.warning('Vui lòng nhập đủ thông tin');
        return;
    }
    try {
        await store.createAdminBooking(posForm.value);
        toast.success('Tạo booking thành công');
        closeModal('posQuickModal');
        fetchAll();
    } catch (e) {}
};
</script>

<template>
    <div class="live-scheduler-page">
        <!-- ========== HEADER ========== -->
        <div class="scheduler-header">
            <div class="scheduler-header__left">
                <h2 class="scheduler-header__title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Live Scheduler
                </h2>
                <p class="scheduler-header__subtitle">{{ formatDateDisplay }} • {{ OPEN_HOUR }}:00 – {{ CLOSE_HOUR }}:00</p>
            </div>
            <div class="scheduler-header__right">
                <div class="scheduler-date-nav">
                    <button class="scheduler-nav-btn" @click="changeDate(-1)" title="Ngày trước">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="scheduler-date-input-wrap">
                        <input type="date" class="scheduler-date-input" v-model="filterDate" @change="fetchAll">
                    </div>
                    <button class="scheduler-nav-btn" @click="changeDate(1)" title="Ngày sau">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button v-if="!isToday" class="scheduler-today-btn" @click="filterDate = new Date().toISOString().split('T')[0]; fetchAll()">
                        Hôm nay
                    </button>
                </div>
                <button class="scheduler-create-btn" @click="openCreatePosModal">
                    <i class="bi bi-plus-lg"></i> Tạo Booking
                </button>
            </div>
        </div>

        <!-- ========== STATS BAR ========== -->
        <div class="scheduler-stats">
            <div class="scheduler-stat">
                <span class="scheduler-stat__value" style="color: #dc3545;">{{ dashboardStats.playing_now || 0 }}</span>
                <span class="scheduler-stat__label">Đang chơi</span>
            </div>
            <div class="scheduler-stat">
                <span class="scheduler-stat__value" style="color: #ffc107;">{{ dashboardStats.pending || 0 }}</span>
                <span class="scheduler-stat__label">Chờ duyệt</span>
            </div>
            <div class="scheduler-stat">
                <span class="scheduler-stat__value" style="color: #198754;">{{ dashboardStats.completed || 0 }}</span>
                <span class="scheduler-stat__label">Hoàn thành</span>
            </div>
            <div class="scheduler-stat">
                <span class="scheduler-stat__value scheduler-stat__value--revenue">{{ formatCurrency(dashboardStats.revenue_today || 0) }}</span>
                <span class="scheduler-stat__label">Doanh thu</span>
            </div>
            <!-- Live Clock + Day Progress -->
            <div class="scheduler-live-clock">
                <div class="scheduler-live-clock__top">
                    <div class="scheduler-live-clock__time">
                        <span class="scheduler-live-clock__digits">{{ liveClockDisplay }}</span>
                        <span class="scheduler-live-clock__pulse"></span>
                    </div>
                    <span class="scheduler-live-clock__remaining" v-if="isToday">{{ remainingTimeDisplay }}</span>
                </div>
                <div class="scheduler-live-clock__bar" v-if="isToday">
                    <div class="scheduler-live-clock__bar-track">
                        <div class="scheduler-live-clock__bar-fill" :style="{ width: dayProgressPercent + '%' }">
                            <div class="scheduler-live-clock__bar-glow"></div>
                        </div>
                    </div>
                    <div class="scheduler-live-clock__bar-labels">
                        <span>{{ OPEN_HOUR }}:00</span>
                        <span class="fw-bold" style="color: var(--court-primary, #6366f1);">{{ dayProgressPercent }}%</span>
                        <span>{{ CLOSE_HOUR }}:00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== LOADING ========== -->
        <div v-if="store.loading && !courtsData.length" class="scheduler-loading">
            <div class="spinner-border text-primary" role="status"></div>
            <span>Đang tải lịch sân...</span>
        </div>

        <!-- ========== SCHEDULER GRID ========== -->
        <div v-else class="scheduler-container">
            <!-- Court labels (fixed left column) -->
            <div class="scheduler-courts-col">
                <div class="scheduler-courts-col__header">Sân</div>
                <div v-for="court in courtsData" :key="court.court_id" class="scheduler-court-label"
                     :class="{ 'scheduler-court-label--playing': court.realtime_status === 'playing', 'scheduler-court-label--maintenance': court.realtime_status === 'maintenance' }">
                    <span class="scheduler-court-label__name">{{ court.court_name }}</span>
                    <span class="scheduler-court-label__status">
                        <span class="scheduler-status-dot" :class="'scheduler-status-dot--' + court.realtime_status"></span>
                    </span>
                </div>
            </div>

            <!-- Timeline area (scrollable) -->
            <div class="scheduler-timeline-wrapper" ref="schedulerScrollContainer">
                <!-- Time header -->
                <div class="scheduler-time-header" :style="{ width: TOTAL_HOURS * 120 + 'px' }">
                    <div v-for="(label, idx) in hourLabels" :key="idx" class="scheduler-time-header__cell"
                         :class="{ 'scheduler-time-header__cell--now': isToday && nowMinutes >= (OPEN_HOUR + idx) * 60 && nowMinutes < (OPEN_HOUR + idx + 1) * 60 }"
                         :style="{ width: '120px' }">
                        {{ label }}
                    </div>
                </div>

                <!-- Court rows -->
                <div class="scheduler-grid-body" :style="{ width: TOTAL_HOURS * 120 + 'px' }">
                    <!-- NOW line -->
                    <div v-if="isToday && nowLinePercent >= 0" class="scheduler-now-line" :style="{ left: nowLinePercent + '%' }">
                        <div class="scheduler-now-line__dot"></div>
                        <div class="scheduler-now-line__label">{{ Math.floor(nowMinutes / 60).toString().padStart(2, '0') }}:{{ (nowMinutes % 60).toString().padStart(2, '0') }} (Now)</div>
                    </div>

                    <div v-for="court in courtsData" :key="court.court_id" class="scheduler-row">
                        <!-- Grid lines (30-min) -->
                        <div v-for="h in (TOTAL_HOURS * 2)" :key="'grid-' + h" class="scheduler-row__gridline" :style="{ left: ((h - 1) / (TOTAL_HOURS * 2) * 100) + '%', width: (100 / (TOTAL_HOURS * 2)) + '%' }"
                             @click="handleSlotClick(court, (h - 1) / 2)">
                        </div>

                        <!-- Booking blocks -->
                        <div v-for="booking in (bookingsByCourt[court.court_id] || [])" :key="booking.booking_id || booking.id"
                             class="scheduler-booking-block"
                             :style="{
                                 left: timeToPercent(booking.start_time) + '%',
                                 width: bookingWidth(booking.start_time, booking.end_time) + '%',
                                 backgroundColor: bookingBlockColor(booking.status).bg,
                                 borderLeftColor: bookingBlockColor(booking.status).border,
                                 color: bookingBlockColor(booking.status).text
                             }"
                             @click.stop="handleBookingClick(booking)"
                             :title="`${booking.user?.full_name || 'Khách'} • ${formatTime(booking.start_time)} - ${formatTime(booking.end_time)}`">
                            <div class="scheduler-booking-block__name">{{ booking.user?.full_name || 'Khách vãng lai' }}</div>
                            <div class="scheduler-booking-block__time">{{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== BOOKING DETAIL MODAL ========== -->
        <div class="modal fade" id="bookingDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content scheduler-modal" v-if="selectedBooking">
                    <div class="modal-header scheduler-modal__header">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-ticket-detailed me-2"></i>{{ selectedBooking.booking_code }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal('bookingDetailModal')"></button>
                    </div>
                    <div class="modal-body p-0">
                        <!-- Booking info -->
                        <div class="p-4">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="scheduler-modal__field">
                                        <span class="scheduler-modal__field-label">Khách hàng</span>
                                        <span class="scheduler-modal__field-value">{{ selectedBooking.user?.full_name || selectedBooking.user?.name || 'Khách vãng lai' }}</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="scheduler-modal__field">
                                        <span class="scheduler-modal__field-label">Sân</span>
                                        <span class="scheduler-modal__field-value">{{ selectedBooking.court?.court_name || selectedBooking.court?.name }}</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="scheduler-modal__field">
                                        <span class="scheduler-modal__field-label">Thời gian</span>
                                        <span class="scheduler-modal__field-value fw-bold">{{ formatTime(selectedBooking.start_time) }} – {{ formatTime(selectedBooking.end_time) }}</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="scheduler-modal__field">
                                        <span class="scheduler-modal__field-label">Trạng thái</span>
                                        <span class="status-badge" :class="'status-badge--' + selectedBooking.status">{{ getStatusText(selectedBooking) }}</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="scheduler-modal__field">
                                        <span class="scheduler-modal__field-label">Tổng tiền</span>
                                        <span class="scheduler-modal__field-value fw-bold" style="color: var(--court-primary); font-size: 1.2rem;">{{ formatCurrency(selectedBooking.total_amount) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Services attached -->
                        <div v-if="selectedBooking.services?.length" class="px-4 pb-3">
                            <h6 class="fw-bold mb-2" style="font-size: 0.85rem;">Dịch vụ đi kèm</h6>
                            <div v-for="s in selectedBooking.services" :key="s.id" class="d-flex justify-content-between py-1" style="font-size: 0.85rem; border-bottom: 1px dashed rgba(0,0,0,0.06);">
                                <span>{{ s.service?.service_name || s.service?.name }} x{{ s.quantity }}</span>
                                <span class="fw-semibold">{{ formatCurrency(s.subtotal) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer scheduler-modal__footer">
                        <button v-if="selectedBooking.status === 'pending'" class="btn btn-primary btn-sm rounded-pill px-3" @click="handleConfirm(selectedBooking.booking_id || selectedBooking.id)">
                            <i class="bi bi-check-circle me-1"></i> Xác nhận
                        </button>
                        <button v-if="selectedBooking.status === 'confirmed'" class="btn btn-success btn-sm rounded-pill px-3" @click="handleCheckIn(selectedBooking.booking_id || selectedBooking.id)">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Check-in
                        </button>
                        <button v-if="['checked_in', 'playing', 'extended'].includes(selectedBooking.status)" class="btn btn-danger btn-sm rounded-pill px-3" @click="handleCheckOut(selectedBooking)">
                            <i class="bi bi-box-arrow-right me-1"></i> Check-out
                        </button>
                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" @click="closeModal('bookingDetailModal')">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== POS QUICK BOOKING MODAL ========== -->
        <div class="modal fade" id="posQuickModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content scheduler-modal">
                    <div class="modal-header scheduler-modal__header">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-journal-plus me-2" style="color: var(--court-primary);"></i> Đặt Sân Nhanh
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal('posQuickModal')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sân</label>
                            <select class="form-select" v-model="posForm.court_id">
                                <option value="">-- Chọn sân --</option>
                                <option v-for="c in courtsData.filter(c => c.status === 'active')" :key="c.court_id" :value="c.court_id">
                                    {{ c.court_name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ngày</label>
                            <input type="date" class="form-control" v-model="posForm.booking_date">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Giờ bắt đầu</label>
                                <input type="time" class="form-control" v-model="posForm.start_time">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Giờ kết thúc</label>
                                <input type="time" class="form-control" v-model="posForm.end_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Thanh toán</label>
                            <select class="form-select" v-model="posForm.payment_method">
                                <option value="cash">Tiền mặt</option>
                                <option value="pos_transfer">Chuyển khoản / QR</option>
                                <option value="pos_card">Quẹt thẻ</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ghi chú</label>
                            <textarea class="form-control" v-model="posForm.note" rows="2" placeholder="Ghi chú..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" @click="closeModal('posQuickModal')">Hủy</button>
                        <button class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold" @click="handleCreatePosBooking">
                            <i class="bi bi-check-lg me-1"></i> Đặt Sân
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ========================================
   LIVE SCHEDULER — Timeline View
   ======================================== */
.live-scheduler-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 100%;
    min-height: 0;
}

/* Header */
.scheduler-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
}
.scheduler-header__title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main, #1a1a2e);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}
.scheduler-header__subtitle {
    font-size: 0.9rem;
    color: var(--text-light, #6c757d);
    margin: 4px 0 0 34px;
}
.scheduler-header__right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

/* Date Navigation */
.scheduler-date-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e8e8e8);
    border-radius: 10px;
    padding: 4px 6px;
}
.scheduler-nav-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted, #6c757d);
    cursor: pointer;
    transition: all 0.15s;
}
.scheduler-nav-btn:hover {
    background: var(--hover-bg, #f3f4f6);
    color: var(--text-main, #1a1a2e);
}
.scheduler-date-input-wrap {
    position: relative;
}
.scheduler-date-input {
    border: none;
    background: transparent;
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--text-main, #1a1a2e);
    cursor: pointer;
    padding: 4px 8px;
}
.scheduler-date-input:focus {
    outline: none;
}
.scheduler-today-btn {
    border: 1px solid var(--border-color, #ddd);
    background: var(--card-bg, #fff);
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--court-primary, #6366f1);
    cursor: pointer;
    transition: all 0.15s;
}
.scheduler-today-btn:hover {
    background: var(--court-primary-soft, #eef2ff);
}

/* Create Button */
.scheduler-create-btn {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 700;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.25);
}
.scheduler-create-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.35);
}

/* Stats Bar */
.scheduler-stats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.scheduler-stat {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e8e8e8);
    border-radius: 10px;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.scheduler-stat__value {
    font-size: 1.2rem;
    font-weight: 800;
}
.scheduler-stat__value--revenue {
    font-size: 0.95rem;
    color: var(--court-primary, #6366f1);
}
.scheduler-stat__label {
    font-size: 0.75rem;
    color: var(--text-light, #6c757d);
    font-weight: 500;
}
.scheduler-stat--time {
    margin-left: auto;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted, #6c757d);
}

/* ========== LIVE CLOCK WIDGET ========== */
.scheduler-live-clock {
    margin-left: auto;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e8e8e8);
    border-radius: 12px;
    padding: 10px 18px;
    min-width: 220px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.scheduler-live-clock__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.scheduler-live-clock__time {
    display: flex;
    align-items: center;
    gap: 8px;
}
.scheduler-live-clock__digits {
    font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', 'Consolas', monospace;
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--text-main, #1a1a2e);
    line-height: 1;
}
.scheduler-live-clock__pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #dc3545;
    animation: clock-pulse 1s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes clock-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.7); }
}
.scheduler-live-clock__remaining {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--court-primary, #6366f1);
    background: var(--court-primary-soft, rgba(99, 102, 241, 0.08));
    padding: 3px 10px;
    border-radius: 20px;
    white-space: nowrap;
}

/* Progress Bar */
.scheduler-live-clock__bar {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.scheduler-live-clock__bar-track {
    height: 6px;
    background: var(--border-color, rgba(0, 0, 0, 0.06));
    border-radius: 6px;
    overflow: hidden;
    position: relative;
}
.scheduler-live-clock__bar-fill {
    height: 100%;
    border-radius: 6px;
    background: linear-gradient(90deg, #FF6B9D 0%, var(--primary) 100%);
    transition: width 1s linear;
    position: relative;
    overflow: hidden;
}
.scheduler-live-clock__bar-glow {
    position: absolute;
    right: 0;
    top: -2px;
    bottom: -2px;
    width: 12px;
    background: radial-gradient(circle, rgba(236, 72, 153, 0.8) 0%, transparent 70%);
    border-radius: 50%;
    animation: bar-glow-pulse 1.5s ease-in-out infinite;
}
@keyframes bar-glow-pulse {
    0%, 100% { opacity: 1; width: 12px; }
    50% { opacity: 0.5; width: 8px; }
}
.scheduler-live-clock__bar-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    color: var(--text-light, #6c757d);
    font-weight: 500;
}

/* Loading */
.scheduler-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 60px 0;
    color: var(--text-light, #6c757d);
}

/* ========== SCHEDULER CONTAINER (Split layout) ========== */
.scheduler-container {
    display: flex;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e8e8e8);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

/* Left column: Court Names */
.scheduler-courts-col {
    width: 110px;
    min-width: 110px;
    flex-shrink: 0;
    border-right: 2px solid var(--border-color, #e8e8e8);
    z-index: 2;
    background: var(--card-bg, #fff);
}
.scheduler-courts-col__header {
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-light, #6c757d);
    border-bottom: 1px solid var(--border-color, #e8e8e8);
    background: var(--card-bg, #fafbfc);
}
.scheduler-court-label {
    height: 56px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 14px;
    border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.04));
    transition: background 0.15s;
}
.scheduler-court-label:hover {
    background: var(--hover-bg, #f8f9fa);
}
.scheduler-court-label--playing {
    background: rgba(220, 53, 69, 0.03);
}
.scheduler-court-label--maintenance {
    background: rgba(253, 126, 20, 0.03);
}
.scheduler-court-label__name {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--text-main, #1a1a2e);
}

/* Status dots */
.scheduler-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.scheduler-status-dot--available { background: #198754; }
.scheduler-status-dot--playing {
    background: #dc3545;
    animation: pulse-glow 1.5s ease-in-out infinite;
}
.scheduler-status-dot--maintenance { background: #fd7e14; }
.scheduler-status-dot--closed { background: #6c757d; }

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.5); }
    50% { box-shadow: 0 0 0 5px rgba(220, 53, 69, 0); }
}

/* Timeline wrapper (horizontal scroll) */
.scheduler-timeline-wrapper {
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
    position: relative;
}
.scheduler-timeline-wrapper::-webkit-scrollbar {
    height: 8px;
}
.scheduler-timeline-wrapper::-webkit-scrollbar-track {
    background: transparent;
}
.scheduler-timeline-wrapper::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.12);
    border-radius: 4px;
}
.scheduler-timeline-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.2);
}

/* Time header */
.scheduler-time-header {
    display: flex;
    height: 42px;
    border-bottom: 1px solid var(--border-color, #e8e8e8);
    background: var(--card-bg, #fafbfc);
    position: sticky;
    top: 0;
    z-index: 1;
}
.scheduler-time-header__cell {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-light, #6c757d);
    border-right: 1px solid var(--border-color, rgba(0, 0, 0, 0.04));
    flex-shrink: 0;
}
.scheduler-time-header__cell--now {
    color: #dc3545;
    font-weight: 800;
    background: rgba(220, 53, 69, 0.04);
}

/* Grid body */
.scheduler-grid-body {
    position: relative;
}

/* NOW line */
.scheduler-now-line {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dc3545;
    z-index: 10;
    pointer-events: none;
}
.scheduler-now-line__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #dc3545;
    position: absolute;
    top: -5px;
    left: -4px;
}
.scheduler-now-line__label {
    position: absolute;
    top: -22px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.68rem;
    font-weight: 800;
    color: #dc3545;
    white-space: nowrap;
    background: rgba(255,255,255,0.9);
    padding: 1px 6px;
    border-radius: 4px;
}

/* Court row */
.scheduler-row {
    height: 56px;
    position: relative;
    border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.04));
}

/* Grid lines (hourly cells) */
.scheduler-row__gridline {
    position: absolute;
    top: 0;
    bottom: 0;
    border-right: 1px solid var(--border-color, rgba(0, 0, 0, 0.04));
    cursor: pointer;
    transition: background 0.15s;
}
.scheduler-row__gridline:hover {
    background: rgba(99, 102, 241, 0.04);
}

/* Booking block */
.scheduler-booking-block {
    position: absolute;
    top: 4px;
    bottom: 4px;
    border-radius: 6px;
    border-left: 3px solid;
    padding: 3px 8px;
    cursor: pointer;
    overflow: hidden;
    z-index: 5;
    transition: all 0.15s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
}
.scheduler-booking-block:hover {
    transform: scaleY(1.04);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    z-index: 6;
}
.scheduler-booking-block__name {
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.scheduler-booking-block__time {
    font-size: 0.68rem;
    font-weight: 500;
    opacity: 0.75;
    white-space: nowrap;
}

/* Modal */
.scheduler-modal {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}
.scheduler-modal__header {
    background: var(--card-bg, #fafbfc);
    border-bottom: 1px solid var(--border-color, #e8e8e8);
}
.scheduler-modal__footer {
    gap: 8px;
}
.scheduler-modal__field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.scheduler-modal__field-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-light, #6c757d);
    letter-spacing: 0.03em;
}
.scheduler-modal__field-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-main, #1a1a2e);
}

/* Responsive */
@media (max-width: 768px) {
    .scheduler-header {
        flex-direction: column;
    }
    .scheduler-stats {
        overflow-x: auto;
    }
    .scheduler-courts-col {
        width: 80px;
        min-width: 80px;
    }
    .scheduler-court-label__name {
        font-size: 0.78rem;
    }
}
</style>
