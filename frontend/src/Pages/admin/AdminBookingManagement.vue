<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCourtBookingStore } from '@/stores/useCourtBookingStore';
import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import '@/assets/court-management.css';

const toast = {
    success: (msg) => Swal.fire({ icon: 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    error: (msg) => Swal.fire({ icon: 'error', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    warning: (msg) => Swal.fire({ icon: 'warning', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    info: (msg) => Swal.fire({ icon: 'info', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 })
};

const store = useCourtBookingStore();
const toLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const searchParams = ref({
    date: toLocalDateString(),
    status: '',
    court_id: '',
    search: ''
});

// For Modals
const selectedBooking = ref(null);
const extensionMinutes = ref(30);
const selectedServiceId = ref('');
const serviceQuantity = ref(1);
const calendarMode = ref('day');

// POS Booking Form
const posBookingForm = ref({
    court_id: '',
    user_id: '', // optional
    booking_date: toLocalDateString(),
    start_time: '07:00',
    end_time: '09:00',
    payment_method: 'cash',
    note: ''
});

onMounted(async () => {
    await store.fetchAdminCourts();
    await store.fetchServices();
    await fetchCalendar();
    await fetchBookings();
});

const fetchBookings = async () => {
    await store.fetchAdminBookings(searchParams.value);
    await fetchCalendar();
};

const fetchCalendar = async () => {
    await store.fetchCourtCalendar({
        mode: calendarMode.value,
        date: searchParams.value.date,
        court_id: searchParams.value.court_id || undefined
    });
};

// Summary stats
const bookingStats = computed(() => {
    const bookings = store.adminBookings || [];
    return {
        total: bookings.length,
        playing: bookings.filter(b => b.status === 'checked_in' || b.status === 'playing' || b.status === 'extended').length,
        pending: bookings.filter(b => b.status === 'pending').length,
        revenue: bookings.filter(b => b.status === 'completed').reduce((sum, b) => sum + Number(b.total_amount || 0), 0)
    };
});

const openModal = async (modalId, booking = null) => {
    if (booking) {
        if (modalId === 'detailModal') {
            await store.fetchAdminBookingDetail(booking.booking_id || booking.id);
            selectedBooking.value = store.currentBooking;
        } else {
            selectedBooking.value = booking;
        }
    } else {
        selectedBooking.value = null;
    }
    
    if (modalId === 'posBookingModal') {
        posBookingForm.value.booking_date = searchParams.value.date || toLocalDateString();
    }
    
    const modalEl = document.getElementById(modalId);
    if(modalEl) {
        const modal = new Modal(modalEl);
        modal.show();
    }
};

const closeModal = (modalId) => {
    const modalEl = document.getElementById(modalId);
    if(modalEl) {
        const modal = Modal.getInstance(modalEl);
        if(modal) modal.hide();
    }
};

const handleConfirm = async (id) => {
    const result = await Swal.fire({
        title: 'Xác nhận booking?',
        text: 'Chấp nhận đơn đặt sân này?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'Xác nhận',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.confirmBooking(id);
            toast.success('Đã xác nhận booking');
            fetchBookings();
        } catch (e) {}
    }
};

const handleCheckIn = async (id) => {
    const result = await Swal.fire({
        title: 'Xác nhận Check-in',
        text: 'Khách hàng đã có mặt và nhận sân?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Check-in',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.adminCheckIn(id);
            toast.success('Check-in thành công');
            fetchBookings();
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
    const booking = typeof bookingOrId === 'object' ? bookingOrId : null;
    const id = booking?.booking_id || booking?.id || bookingOrId;
    const payload = await buildCheckOutPayload(booking);
    if (payload === null) {
        return;
    }

    const result = await Swal.fire({
        title: 'Xác nhận Check-out',
        text: 'Khách hàng trả sân và hoàn tất thanh toán?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Check-out & Thanh toán',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.adminCheckOut(id, payload);
            toast.success('Check-out thành công');
            fetchBookings();
        } catch (e) {}
    }
};

const handleCancelBooking = async (id) => {
    const result = await Swal.fire({
        title: 'Hủy booking?',
        input: 'text',
        inputPlaceholder: 'Lý do hủy',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Hủy booking',
        cancelButtonText: 'Đóng'
    });
    if (!result.isConfirmed) return;

    try {
        await store.cancelAdminBooking(id, { reason: result.value || 'Admin cancelled booking' });
        toast.success('Đã hủy booking');
        fetchBookings();
        fetchCalendar();
    } catch (e) {}
};

const handleRecordPayment = async (booking) => {
    const id = booking.booking_id || booking.id;
    const result = await Swal.fire({
        title: 'Ghi nhận thanh toán',
        input: 'select',
        inputOptions: {
            cash: 'Tiền mặt',
            pos_transfer: 'Chuyển khoản / QR',
            pos_card: 'Quẹt thẻ'
        },
        inputValue: booking.payment_method || 'cash',
        showCancelButton: true,
        confirmButtonText: 'Ghi nhận',
        cancelButtonText: 'Đóng'
    });
    if (!result.isConfirmed) return;

    try {
        await store.recordAdminPayment(id, {
            payment_method: result.value,
            payment_type: 'full',
            amount: booking.total_amount,
            status: 'success',
            note: 'Admin recorded payment'
        });
        toast.success('Đã ghi nhận thanh toán');
        fetchBookings();
    } catch (e) {}
};

const handleQrCheckIn = async (booking) => {
    const id = booking.booking_id || booking.id;
    const result = await Swal.fire({
        title: 'QR check-in',
        input: 'text',
        inputPlaceholder: 'Dán mã QR token của khách',
        showCancelButton: true,
        confirmButtonText: 'Check-in',
        cancelButtonText: 'Đóng'
    });
    if (!result.isConfirmed || !result.value) return;

    try {
        await store.qrCheckInBooking(id, { qr_token: result.value });
        toast.success('QR check-in thành công');
        fetchBookings();
    } catch (e) {}
};

const handleExtend = async () => {
    if (extensionMinutes.value < 15) {
        toast.warning('Thời gian gia hạn tối thiểu 15 phút');
        return;
    }
    try {
        await store.extendBooking(selectedBooking.value.booking_id || selectedBooking.value.id, { extension_minutes: extensionMinutes.value });
        toast.success('Gia hạn thành công');
        closeModal('extendModal');
        fetchBookings();
    } catch (e) {}
};

const handleAddService = async () => {
    if(!selectedServiceId.value || serviceQuantity.value < 1) {
        toast.warning('Vui lòng chọn dịch vụ và số lượng');
        return;
    }
    try {
        await store.addServiceToBooking(selectedBooking.value.booking_id || selectedBooking.value.id, {
            service_id: selectedServiceId.value,
            quantity: serviceQuantity.value
        });
        toast.success('Thêm dịch vụ thành công');
        closeModal('serviceModal');
        fetchBookings();
    } catch (e) {}
};

const handleCreatePosBooking = async () => {
    if (!posBookingForm.value.court_id || !posBookingForm.value.start_time || !posBookingForm.value.end_time) {
        toast.warning('Vui lòng nhập đủ thông tin bắt buộc');
        return;
    }
    try {
        await store.createAdminBooking(posBookingForm.value);
        toast.success('Tạo booking thành công');
        closeModal('posBookingModal');
        fetchBookings();
    } catch (e) {}
};

const getStatusBadgeClass = (status) => 'status-badge--' + (status === 'playing' || status === 'extended' ? 'checked_in' : (status === 'expired' ? 'cancelled' : status));

const getStatusText = (status) => {
    const map = {
        'pending': 'Chờ duyệt',
        'confirmed': 'Đã xác nhận',
        'checked_in': 'Đang chơi',
        'playing': 'Đang chơi',
        'extended': 'Đã gia hạn',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy',
        'no_show': 'Không đến'
    };
    map.expired = 'Het han';
    return map[status] || status;
};

const formatCurrency = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
const formatTime = (timeStr) => timeStr ? timeStr.substring(0, 5) : '';
const formatDateTime = (dt) => {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const clearFilters = () => {
    searchParams.value = {
        date: toLocalDateString(),
        status: '',
        court_id: '',
        search: ''
    };
    fetchBookings();
};
</script>

<template>
    <div class="booking-management-page">
        <!-- Page Header -->
        <div class="court-section-header">
            <div>
                <h2 class="court-section-title">
                    <i class="bi bi-calendar-check"></i>
                    Quản Lý Lịch Đặt Sân
                </h2>
                <p class="court-section-subtitle">Theo dõi, check-in/out và quản lý tất cả đơn đặt sân</p>
            </div>
            <button class="court-action-btn court-action-btn--primary" @click="openModal('posBookingModal')">
                <i class="bi bi-plus-lg"></i> Đặt Sân Tại Quầy (POS)
            </button>
        </div>

        <!-- Stats Bar -->
        <div class="court-stats-bar">
            <div class="court-stat-card">
                <div class="court-stat-card__value" style="color: var(--court-primary);">{{ bookingStats.total }}</div>
                <div class="court-stat-card__label">Tổng booking</div>
            </div>
            <div class="court-stat-card">
                <div class="court-stat-card__value" style="color: var(--court-playing);">{{ bookingStats.playing }}</div>
                <div class="court-stat-card__label">Đang chơi</div>
            </div>
            <div class="court-stat-card">
                <div class="court-stat-card__value" style="color: #ffc107;">{{ bookingStats.pending }}</div>
                <div class="court-stat-card__label">Chờ duyệt</div>
            </div>
            <div class="court-stat-card">
                <div class="court-stat-card__value" style="color: var(--court-available);">{{ formatCurrency(bookingStats.revenue) }}</div>
                <div class="court-stat-card__label" style="font-size: 0.65rem;">Doanh thu ngày</div>
            </div>
        </div>

        <div class="court-filter-bar">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="fw-bold">Lịch vận hành</div>
                    <div class="text-muted" style="font-size: 0.82rem;">
                        {{ store.courtCalendar?.from_date }} - {{ store.courtCalendar?.to_date }} • {{ store.courtCalendar?.items?.length || 0 }} booking
                    </div>
                </div>
                <select class="form-select" v-model="calendarMode" @change="fetchCalendar" style="width: 160px; border-radius: 10px;">
                    <option value="day">Ngày</option>
                    <option value="week">Tuần</option>
                    <option value="month">Tháng</option>
                </select>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="court-filter-bar">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold" style="font-size: 0.8rem; color: var(--text-light, #6c757d);">
                        <i class="bi bi-search me-1"></i> Tìm kiếm
                    </label>
                    <input type="text" class="form-control" v-model="searchParams.search" placeholder="Mã BK, Tên..." @keyup.enter="fetchBookings"
                        style="border-radius: 10px; border-color: var(--border-color, rgba(0,0,0,0.1));">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold" style="font-size: 0.8rem; color: var(--text-light, #6c757d);">
                        <i class="bi bi-calendar3 me-1"></i> Ngày
                    </label>
                    <input type="date" class="form-control" v-model="searchParams.date" @change="fetchBookings"
                        style="border-radius: 10px; border-color: var(--border-color, rgba(0,0,0,0.1));">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size: 0.8rem; color: var(--text-light, #6c757d);">
                        <i class="bi bi-flag me-1"></i> Trạng thái
                    </label>
                    <select class="form-select" v-model="searchParams.status" @change="fetchBookings"
                        style="border-radius: 10px; border-color: var(--border-color, rgba(0,0,0,0.1));">
                        <option value="">Tất cả</option>
                        <option value="pending">Chờ duyệt</option>
                        <option value="confirmed">Đã xác nhận</option>
                        <option value="checked_in">Đang chơi</option>
                        <option value="extended">Đã gia hạn</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size: 0.8rem; color: var(--text-light, #6c757d);">
                        <i class="bi bi-building me-1"></i> Sân
                    </label>
                    <select class="form-select" v-model="searchParams.court_id" @change="fetchBookings"
                        style="border-radius: 10px; border-color: var(--border-color, rgba(0,0,0,0.1));">
                        <option value="">Tất cả sân</option>
                        <option v-for="c in store.courts" :key="c.court_id || c.id" :value="c.court_id || c.id">{{ c.court_name || c.name }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="court-action-btn court-action-btn--primary flex-fill" @click="fetchBookings">
                        <i class="bi bi-search"></i> Lọc
                    </button>
                    <button class="court-action-btn court-action-btn--outline" @click="clearFilters" title="Xóa bộ lọc">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="court-table-wrap">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Mã Booking</th>
                            <th>Khách Hàng</th>
                            <th>Sân</th>
                            <th>Thời Gian</th>
                            <th>Tổng Tiền</th>
                            <th>Trạng Thái</th>
                            <th class="text-end pe-4">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loading -->
                        <tr v-if="store.loading">
                            <td colspan="7" class="text-center py-5">
                                <div class="spinner-border spinner-border-sm me-2" style="color: var(--court-primary);" role="status"></div>
                                <span class="text-muted" style="font-size: 0.9rem;">Đang tải dữ liệu...</span>
                            </td>
                        </tr>
                        <!-- Empty -->
                        <tr v-else-if="!store.adminBookings || store.adminBookings.length === 0">
                            <td colspan="7">
                                <div class="court-empty-state" style="padding: 36px 24px;">
                                    <div class="court-empty-state__icon"><i class="bi bi-calendar-x"></i></div>
                                    <div class="court-empty-state__title">Không có lịch đặt nào</div>
                                    <div class="court-empty-state__text">Thử thay đổi bộ lọc hoặc chọn ngày khác</div>
                                </div>
                            </td>
                        </tr>
                        <!-- Data Rows -->
                        <tr v-else v-for="booking in store.adminBookings" :key="booking.booking_id || booking.id">
                            <td class="ps-4">
                                <span class="fw-bold" style="font-size: 0.9rem;">{{ booking.booking_code || `#${booking.booking_id || booking.id}` }}</span>
                                <div v-if="booking.source === 'pos'" class="badge bg-secondary" style="font-size: 0.65rem;">POS</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle" 
                                         style="width: 34px; height: 34px; background: var(--court-primary-soft); color: var(--court-primary); font-weight: 700; font-size: 0.8rem; flex-shrink: 0;">
                                        {{ (booking.user?.name || booking.user?.full_name || 'K')[0].toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="font-size: 0.88rem;">{{ booking.user?.name || booking.user?.full_name || 'Khách Vãng Lai' }}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">{{ booking.user?.phone || booking.user?.email || '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-2" style="background: var(--court-section-bg); font-size: 0.8rem; font-weight: 600;">
                                    <i class="bi bi-building" style="font-size: 0.7rem;"></i>
                                    {{ booking.court?.name || booking.court?.court_name || '—' }}
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold" style="font-size: 0.9rem;">
                                    <i class="bi bi-clock me-1 text-muted" style="font-size: 0.75rem;"></i>
                                    {{ formatTime(booking.start_time) }} – {{ formatTime(booking.end_time) }}
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-calendar3 me-1"></i>{{ booking.booking_date }}
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold" style="color: var(--court-primary); font-size: 0.95rem;">{{ formatCurrency(booking.total_amount) }}</span>
                            </td>
                            <td>
                                <span class="status-badge" :class="getStatusBadgeClass(booking.status)">
                                    <span v-if="['checked_in', 'playing', 'extended'].includes(booking.status)" class="pulse-dot pulse-dot--playing"></span>
                                    {{ getStatusText(booking.status) }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown court-dropdown d-inline-block me-1">
                                    <button class="court-action-btn court-action-btn--outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Hành Động
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="openModal('detailModal', booking)">
                                                <i class="bi bi-eye"></i> Chi tiết
                                            </a>
                                        </li>
                                        <li v-if="booking.status === 'pending'"><hr class="dropdown-divider"></li>
                                        <li v-if="booking.status === 'pending'">
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="handleConfirm(booking.booking_id || booking.id)" style="color: var(--court-primary);">
                                                <i class="bi bi-check-circle"></i> Xác nhận Booking
                                            </a>
                                        </li>
                                        <li v-if="booking.status === 'confirmed'">
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="handleCheckIn(booking.booking_id || booking.id)" style="color: var(--court-available);">
                                                <i class="bi bi-box-arrow-in-right"></i> Check-in Nhận Sân
                                            </a>
                                        </li>
                                        <li v-if="booking.status === 'confirmed'">
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="handleQrCheckIn(booking)" style="color: var(--court-playing);">
                                                <i class="bi bi-qr-code"></i> QR Check-in
                                            </a>
                                        </li>
                                        <li v-if="booking.payment_status !== 'paid' && booking.status !== 'cancelled'">
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="handleRecordPayment(booking)" style="color: var(--court-primary);">
                                                <i class="bi bi-credit-card"></i> Ghi nhận thanh toán
                                            </a>
                                        </li>
                                        <li v-if="['pending', 'confirmed'].includes(booking.status)">
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="handleCancelBooking(booking.booking_id || booking.id)" style="color: var(--court-closed);">
                                                <i class="bi bi-x-circle"></i> Hủy booking
                                            </a>
                                        </li>
                                        <li v-if="['checked_in', 'playing', 'extended'].includes(booking.status)">
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="openModal('serviceModal', booking)" style="color: var(--court-playing);">
                                                <i class="bi bi-cart-plus"></i> Thêm Dịch Vụ
                                            </a>
                                        </li>
                                        <li v-if="['checked_in', 'playing', 'extended'].includes(booking.status)">
                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" @click.prevent="openModal('extendModal', booking)" style="color: var(--court-maintenance);">
                                                <i class="bi bi-clock-history"></i> Gia Hạn Giờ
                                            </a>
                                        </li>
                                        <li v-if="['checked_in', 'playing', 'extended'].includes(booking.status)"><hr class="dropdown-divider"></li>
                                        <li v-if="['checked_in', 'playing', 'extended'].includes(booking.status)">
                                            <a class="dropdown-item d-flex align-items-center gap-2 fw-bold" href="#" @click.prevent="handleCheckOut(booking)" style="color: var(--court-closed);">
                                                <i class="bi bi-box-arrow-right"></i> Check-out Trả Sân
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination (if applicable) -->
            <div v-if="store.pagination && store.pagination.last_page > 1" class="p-3 d-flex justify-content-between align-items-center" style="border-top: 1px solid var(--border-color, rgba(0,0,0,0.05));">
                <div class="text-muted" style="font-size: 0.85rem;">
                    Hiển thị {{ store.pagination.total }} kết quả (Trang {{ store.pagination.current_page }}/{{ store.pagination.last_page }})
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div class="modal fade court-modal" id="detailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" v-if="selectedBooking">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Chi Tiết Booking {{ selectedBooking.booking_code }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal('detailModal')"></button>
                    </div>
                    <div class="modal-body p-0">
                        <!-- Summary -->
                        <div class="p-4" style="background: var(--court-section-bg); border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="text-muted mb-1" style="font-size: 0.8rem; text-transform: uppercase;">Khách hàng</p>
                                    <div class="fw-bold mb-3">{{ selectedBooking.user?.name || selectedBooking.user?.full_name || 'Khách vãng lai' }}</div>
                                    
                                    <p class="text-muted mb-1" style="font-size: 0.8rem; text-transform: uppercase;">Sân</p>
                                    <div class="fw-bold">{{ selectedBooking.court?.name || selectedBooking.court?.court_name }}</div>
                                </div>
                                <div class="col-md-6">
                                    <p class="text-muted mb-1" style="font-size: 0.8rem; text-transform: uppercase;">Thời gian</p>
                                    <div class="fw-bold mb-3">
                                        {{ formatTime(selectedBooking.start_time) }} - {{ formatTime(selectedBooking.end_time) }} <br>
                                        <small class="text-muted fw-normal">{{ formatDateTime(selectedBooking.booking_date) }}</small>
                                    </div>
                                    
                                    <p class="text-muted mb-1" style="font-size: 0.8rem; text-transform: uppercase;">Trạng thái</p>
                                    <div>
                                        <span class="status-badge" :class="getStatusBadgeClass(selectedBooking.status)">
                                            {{ getStatusText(selectedBooking.status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Financials & Services -->
                        <div class="p-4">
                            <h6 class="fw-bold mb-3">Chi tiết thanh toán</h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td>Tiền sân ({{ selectedBooking.duration_minutes }} phút)</td>
                                        <td class="text-end">{{ formatCurrency(selectedBooking.original_price) }}</td>
                                    </tr>
                                    <!-- Services -->
                                    <tr v-for="s in selectedBooking.services" :key="s.id">
                                        <td>Dịch vụ: {{ s.service?.name || s.service?.service_name }} x{{ s.quantity }}</td>
                                        <td class="text-end">{{ formatCurrency(s.subtotal) }}</td>
                                    </tr>
                                    <!-- Extensions -->
                                    <tr v-for="ext in selectedBooking.extensions" :key="ext.id">
                                        <td>Gia hạn: {{ ext.extension_minutes }} phút</td>
                                        <td class="text-end">{{ formatCurrency(ext.extra_amount) }}</td>
                                    </tr>
                                    <tr v-if="selectedBooking.discount_amount > 0">
                                        <td>Giảm giá</td>
                                        <td class="text-end text-danger">-{{ formatCurrency(selectedBooking.discount_amount) }}</td>
                                    </tr>
                                    <tr style="border-top: 2px solid var(--border-color, rgba(0,0,0,0.1));">
                                        <td class="fw-bold py-2">Tổng cộng</td>
                                        <td class="text-end fw-bold py-2" style="color: var(--court-primary); font-size: 1.1rem;">
                                            {{ formatCurrency(selectedBooking.total_amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="d-flex justify-content-between align-items-center mt-3 p-3 rounded" style="background: rgba(0,0,0,0.03);">
                                <div>
                                    <span class="text-muted me-2" style="font-size: 0.85rem;">Trạng thái TT:</span>
                                    <span class="badge" :class="selectedBooking.payment_status === 'paid' ? 'bg-success' : 'bg-warning text-dark'">
                                        {{ selectedBooking.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}
                                    </span>
                                </div>
                                <div class="fw-bold">
                                    Đã trả: <span class="text-success">{{ formatCurrency(selectedBooking.paid_amount || 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status Histories -->
                        <div class="p-4 border-top" v-if="selectedBooking.statusHistories?.length">
                            <h6 class="fw-bold mb-3">Lịch sử trạng thái</h6>
                            <div class="timeline" style="border-left: 2px solid var(--border-color, rgba(0,0,0,0.1)); padding-left: 15px; margin-left: 10px;">
                                <div v-for="history in selectedBooking.statusHistories" :key="history.id" class="mb-3 position-relative">
                                    <div class="position-absolute" style="width: 10px; height: 10px; background: var(--court-primary); border-radius: 50%; left: -21px; top: 5px;"></div>
                                    <div class="fw-bold" style="font-size: 0.9rem;">
                                        {{ getStatusText(history.new_status) }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.8rem;">
                                        {{ formatDateTime(history.created_at) }} - <span v-if="history.note">{{ history.note }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" @click="closeModal('detailModal')">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- POS Booking Modal -->
        <div class="modal fade court-modal" id="posBookingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-journal-plus me-2" style="color: var(--court-primary);"></i>
                            Đặt Sân Tại Quầy
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal('posBookingModal')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sân</label>
                            <select class="form-select" v-model="posBookingForm.court_id">
                                <option value="">-- Chọn sân --</option>
                                <option v-for="c in store.courts.filter(c => c.status === 'active')" :key="c.court_id || c.id" :value="c.court_id || c.id">
                                    {{ c.court_name || c.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ngày</label>
                            <input type="date" class="form-control" v-model="posBookingForm.booking_date">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Giờ bắt đầu</label>
                                <input type="time" class="form-control" v-model="posBookingForm.start_time">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Giờ kết thúc</label>
                                <input type="time" class="form-control" v-model="posBookingForm.end_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hình thức thanh toán dự kiến</label>
                            <select class="form-select" v-model="posBookingForm.payment_method">
                                <option value="cash">Tiền mặt</option>
                                <option value="pos_transfer">Chuyển khoản / QR</option>
                                <option value="pos_card">Quẹt thẻ</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ghi chú</label>
                            <textarea class="form-control" v-model="posBookingForm.note" rows="2" placeholder="Ghi chú thêm..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" @click="closeModal('posBookingModal')">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="handleCreatePosBooking">
                            <i class="bi bi-check-lg"></i> Đặt Sân
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Extend Modal -->
        <div class="modal fade court-modal" id="extendModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-clock-history me-2" style="color: var(--court-maintenance);"></i>
                            Gia Hạn Giờ Chơi
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal('extendModal')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3 p-3 rounded-3" style="background: var(--court-section-bg);">
                            <i class="bi bi-info-circle text-muted"></i>
                            <span style="font-size: 0.85rem;">Đang gia hạn cho booking <strong>{{ selectedBooking?.booking_code }}</strong></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Số phút muốn gia hạn thêm</label>
                            <input type="number" class="form-control" v-model="extensionMinutes" min="15" step="15">
                            <div class="form-text">Ví dụ: 15, 30, 60...</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" @click="closeModal('extendModal')">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="handleExtend">
                            <i class="bi bi-check-lg"></i> Xác Nhận Gia Hạn
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Service Modal -->
        <div class="modal fade court-modal" id="serviceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-cart-plus me-2" style="color: var(--court-playing);"></i>
                            Mua Thêm Dịch Vụ
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal('serviceModal')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3 p-3 rounded-3" style="background: var(--court-section-bg);">
                            <i class="bi bi-info-circle text-muted"></i>
                            <span style="font-size: 0.85rem;">Phục vụ cho booking <strong>{{ selectedBooking?.booking_code }}</strong></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Chọn Dịch Vụ</label>
                            <select class="form-select" v-model="selectedServiceId">
                                <option value="">-- Chọn --</option>
                                <option v-for="s in store.services.filter(s => s.is_active === true || s.status === 'active')" :key="s.service_id || s.id" :value="s.service_id || s.id">
                                    {{ s.name || s.service_name }} ({{ formatCurrency(s.price || s.unit_price) }})
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Số Lượng</label>
                            <input type="number" class="form-control" v-model="serviceQuantity" min="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" @click="closeModal('serviceModal')">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="handleAddService">
                            <i class="bi bi-check-lg"></i> Thêm Vào Hóa Đơn
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.booking-management-page {
    max-width: 100%;
}
</style>
