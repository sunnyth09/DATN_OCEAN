<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCourtBookingStore } from '@/stores/useCourtBookingStore';
import { useAuthStore } from '@/stores/auth';
import Swal from 'sweetalert2';
import '@/assets/court-management.css';

const toast = {
    success: (msg) => Swal.fire({ icon: 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    error: (msg) => Swal.fire({ icon: 'error', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    warning: (msg) => Swal.fire({ icon: 'warning', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    info: (msg) => Swal.fire({ icon: 'info', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 })
};

const route = useRoute();
const router = useRouter();
const store = useCourtBookingStore();
const authStore = useAuthStore();

const toLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const courtId = route.params.id;
const selectedDate = ref(toLocalDateString());
const availableSlots = ref([]);
const services = ref([]);
const selectedSlots = ref([]);
const selectedServices = ref([]);
const loadingSlots = ref(false);
const paymentMethod = ref('cash');
const bookingInProgress = ref(false);
const activeLock = ref(null);
const lockCountdown = ref(0);
let lockTimer = null;
let bookingChannel = null;
const timelineWrapper = ref(null);

const scrollToCurrentTime = () => {
    if (!timelineWrapper.value || availableSlots.value.length === 0) return;
    
    // Chỉ cuộn nếu đang xem ngày hôm nay
    if (selectedDate.value !== toLocalDateString(new Date())) return;

    const now = new Date();
    const currentTimeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:00`;

    let targetIndex = availableSlots.value.findIndex(slot => slot.start_time >= currentTimeStr);
    
    if (targetIndex === -1) {
        targetIndex = availableSlots.value.length - 1;
    } else if (targetIndex > 0) {
        targetIndex = targetIndex - 1;
    }

    const slotElements = timelineWrapper.value.querySelectorAll('.client-timeline-slot');
    if (slotElements && slotElements[targetIndex]) {
        const wrapperWidth = timelineWrapper.value.clientWidth;
        const slotEl = slotElements[targetIndex];
        const scrollPosition = slotEl.offsetLeft - (wrapperWidth / 2) + (slotEl.clientWidth / 2);
        timelineWrapper.value.scrollTo({
            left: Math.max(0, scrollPosition),
            behavior: 'smooth'
        });
    }
};

// Quick date navigation
const quickDates = computed(() => {
    const dates = [];
    const today = new Date();
    for (let i = 0; i < 7; i++) {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        dates.push({
            value: toLocalDateString(d),
            label: i === 0 ? 'Hôm nay' : (i === 1 ? 'Ngày mai' : d.toLocaleDateString('vi-VN', { weekday: 'short', day: 'numeric', month: 'numeric' })),
            day: d.toLocaleDateString('vi-VN', { weekday: 'short' }),
            date: d.getDate()
        });
    }
    return dates;
});

// Computed: tóm tắt booking
const bookingSummary = computed(() => {
    let totalTime = selectedSlots.value.length; // mỗi slot 1 giờ
    let courtFee = 0;

    selectedSlots.value.forEach(slot => {
        courtFee += Number(slot.price) || 0;
    });

    let serviceFee = selectedServices.value.reduce((total, s) => total + (Number(s.unit_price) * s.quantity), 0);

    return {
        totalTime,
        courtFee,
        serviceFee,
        total: courtFee + serviceFee
    };
});

onMounted(async () => {
    await store.fetchCourtDetail(courtId);
    await fetchServices();
    await fetchAvailableSlots();
    subscribeRealtime();
});

onUnmounted(async () => {
    clearLockTimer();
    await releaseActiveLock();
    leaveRealtime();
});

let isRevertingDate = false;
watch(selectedDate, async (newVal, oldVal) => {
    if (isRevertingDate) {
        isRevertingDate = false;
        return;
    }
    
    if (oldVal && newVal !== oldVal && activeLock.value?.lock_token) {
        const result = await Swal.fire({
            title: 'Đổi ngày?',
            text: "Việc chọn ngày khác sẽ hủy các khung giờ bạn đang giữ. Bạn có chắc chắn?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e63b6f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Đồng ý đổi',
            cancelButtonText: 'Giữ lại'
        });
        if (!result.isConfirmed) {
            isRevertingDate = true;
            selectedDate.value = oldVal;
            return;
        }
        await releaseActiveLock();
    }
    selectedSlots.value = []; 
    await fetchAvailableSlots();
});

watch(selectedDate, () => {
    subscribeRealtime();
});

const fetchServices = async () => {
    try {
        const res = await store.fetchPublicServices({ status: 'active' });
        if (res && res.data) {
            services.value = res.data.map(s => ({ ...s, quantity: 0 }));
        }
    } catch (e) {
        console.error('Không thể tải dịch vụ:', e);
    }
};

const fetchAvailableSlots = async (silent = false) => {
    if (!silent) loadingSlots.value = true;
    try {
        const res = await store.checkAvailability(courtId, { date: selectedDate.value });
        if (res && res.data) {
            availableSlots.value = res.data;
            
            // Khôi phục trạng thái giữ chỗ nếu tải lại trang
            if (selectedSlots.value.length === 0 && !silent) {
                const myLockedSlots = res.data.filter(s => s.is_my_lock);
                if (myLockedSlots.length > 0) {
                    selectedSlots.value = myLockedSlots;
                    const firstLock = myLockedSlots[0];
                    activeLock.value = {
                        lock_token: firstLock.lock_token,
                        expires_at: firstLock.lock_expires_at
                    };
                    startLockTimer(firstLock.lock_expires_at);
                }
            }

            if (!silent) {
                nextTick(() => {
                    scrollToCurrentTime();
                });
            }
        } else {
            availableSlots.value = [];
        }
    } catch (e) {
        availableSlots.value = [];
    } finally {
        if (!silent) loadingSlots.value = false;
    }
};

const leaveRealtime = () => {
    if (!window.Echo || !bookingChannel) return;
    window.Echo.leave(`court-booking.court.${courtId}.${bookingChannel}`);
    bookingChannel = null;
};

const subscribeRealtime = () => {
    if (!window.Echo) return;
    leaveRealtime();
    bookingChannel = selectedDate.value;
    const refresh = () => fetchAvailableSlots(true);
    window.Echo.channel(`court-booking.court.${courtId}.${selectedDate.value}`)
        .listen('.CourtSlotLocked', refresh)
        .listen('.CourtSlotReleased', refresh)
        .listen('.CourtBookingCreated', refresh)
        .listen('.CourtBookingCancelled', refresh)
        .listen('.CourtBookingStatusChanged', refresh);
};

const clearLockTimer = () => {
    if (lockTimer) clearInterval(lockTimer);
    lockTimer = null;
    lockCountdown.value = 0;
};

const startLockTimer = (expiresAt) => {
    clearLockTimer();
    const tick = () => {
        const remain = Math.max(0, Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000));
        lockCountdown.value = remain;
        if (remain === 0) {
            clearLockTimer();
            activeLock.value = null;
            if (selectedSlots.value.length > 0) {
                selectedSlots.value = [];
                toast.warning('Thời gian giữ chỗ đã hết, vui lòng chọn lại!');
            }
            fetchAvailableSlots(true);
        }
    };
    tick();
    lockTimer = setInterval(tick, 1000);
};

const releaseActiveLock = async () => {
    if (!activeLock.value?.lock_token) return;
    const token = activeLock.value.lock_token;
    activeLock.value = null;
    clearLockTimer();
    try {
        await store.releaseLock({ lock_token: token });
    } catch (e) {}
};

const ensureAuthenticated = () => {
    if (!authStore.isHydrated) {
        authStore.hydrate();
    }

    if (authStore.isAuthenticated) {
        return true;
    }

    toast.info("Vui lòng đăng nhập để đặt sân");
    router.push({ name: 'login', query: { redirect: route.fullPath } });
    return false;
};

const lockSelectedSlots = async () => {
    if (!ensureAuthenticated()) return;
    await releaseActiveLock();
    if (selectedSlots.value.length === 0 || !hasContinuousSlots()) return;
    const startTime = formatTime(selectedSlots.value[0].start_time);
    const endTime = formatTime(selectedSlots.value[selectedSlots.value.length - 1].end_time);
    const res = await store.lockSlot({
        court_id: parseInt(courtId),
        booking_date: selectedDate.value,
        start_time: startTime,
        end_time: endTime
    });
    activeLock.value = res?.data || null;
    if (activeLock.value?.expires_at) startLockTimer(activeLock.value.expires_at);
};

const toggleSlot = async (slot) => {
    if (slot.status !== 'available' && !slot.is_my_lock) return;
    if (!ensureAuthenticated()) return;

    const index = selectedSlots.value.findIndex(s => s.start_time === slot.start_time);
    if (index === -1) {
        selectedSlots.value.push(slot);
    } else {
        selectedSlots.value.splice(index, 1);
    }

    // Sắp xếp lại theo thời gian
    selectedSlots.value.sort((a, b) => a.start_time.localeCompare(b.start_time));
    try {
        await lockSelectedSlots();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Khong the giu cho khung gio nay.');
        selectedSlots.value = selectedSlots.value.filter(s => s.start_time !== slot.start_time);
        await fetchAvailableSlots();
    }
};

const isSlotSelected = (slot) => {
    return selectedSlots.value.some(s => s.start_time === slot.start_time);
};

const getSlotStatusLabel = (status) => {
    const labels = {
        available: 'Trống',
        booked: 'Đã đặt',
        locked: 'Đang giữ',
        maintenance: 'Bảo trì',
        past: 'Đã qua',
    };
    return labels[status] || status;
};

const increaseService = (service) => {
    service.quantity++;
    updateSelectedServices(service);
};

const decreaseService = (service) => {
    if (service.quantity > 0) {
        service.quantity--;
        updateSelectedServices(service);
    }
};

const updateSelectedServices = (service) => {
    const sid = service.service_id || service.id;
    const index = selectedServices.value.findIndex(s => (s.service_id || s.id) === sid);
    if (service.quantity > 0) {
        if (index === -1) selectedServices.value.push(service);
    } else {
        if (index !== -1) selectedServices.value.splice(index, 1);
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

const formatTime = (timeStr) => {
    if (!timeStr) return '';
    return timeStr.substring(0, 5);
};

const courtName = computed(() => {
    const c = store.currentCourt;
    return c?.court_name || c?.name || 'Sân cầu lông';
});

const courtType = computed(() => {
    const t = store.currentCourt?.type;
    const map = { standard: 'Tiêu chuẩn', vip: 'VIP', outdoor: 'Ngoài trời', indoor: 'Trong nhà' };
    return map[t] || t || 'Tiêu chuẩn';
});

const hasContinuousSlots = () => {
    if (selectedSlots.value.length <= 1) return true;
    return selectedSlots.value.every((slot, index, slots) => {
        if (index === 0) return true;
        return formatTime(slots[index - 1].end_time) === formatTime(slot.start_time);
    });
};

const proceedBooking = async () => {
    if (!ensureAuthenticated()) return;

    if (!authStore.isAuthenticated) {
        toast.info("Vui lòng đăng nhập để đặt sân");
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }

    if (selectedSlots.value.length === 0) {
        toast.warning("Vui lòng chọn ít nhất 1 khung giờ");
        return;
    }

    // Ghép slots liền kề
    if (!hasContinuousSlots()) {
        toast.warning("Vui lòng chọn các khung giờ liền kề nhau");
        return;
    }

    const startTime = formatTime(selectedSlots.value[0].start_time);
    const endTime = formatTime(selectedSlots.value[selectedSlots.value.length - 1].end_time);

    const payload = {
        court_id: parseInt(courtId),
        booking_date: selectedDate.value,
        start_time: startTime,
        end_time: endTime,
        payment_method: paymentMethod.value,
        services: selectedServices.value
            .filter(s => s.quantity > 0)
            .map(s => ({
                service_id: s.service_id || s.id,
                quantity: s.quantity
            }))
    };

    bookingInProgress.value = true;
    try {
        if (!activeLock.value?.lock_token) {
            await lockSelectedSlots();
        }
        const lockToken = activeLock.value?.lock_token;
        if (!lockToken) {
            throw new Error('Khong the giu cho khung gio nay.');
        }
        payload.lock_token = lockToken;

        await store.bookCourt(payload);
        activeLock.value = null;
        clearLockTimer();
        await Swal.fire({
            icon: 'success',
            title: 'Đặt sân thành công!',
            text: `Sân ${courtName.value} • ${selectedDate.value} • ${startTime} - ${endTime}`,
            confirmButtonText: 'Xem đơn đặt sân',
            showCancelButton: true,
            cancelButtonText: 'Tiếp tục đặt',
            confirmButtonColor: '#e63b6f'
        }).then((result) => {
            if (result.isConfirmed) {
                router.push({ name: 'profile-court-bookings' });
            } else {
                // Reset state
                selectedSlots.value = [];
                selectedServices.value = [];
                services.value.forEach(s => s.quantity = 0);
                fetchAvailableSlots();
            }
        });
    } catch (e) {
        const msg = e?.response?.data?.message || store.error || 'Đặt sân thất bại. Vui lòng thử lại.';
        toast.error(msg);
        await fetchAvailableSlots();
    } finally {
        bookingInProgress.value = false;
    }
};

const confirmReleaseLock = async () => {
    const result = await Swal.fire({
        title: 'Hủy giữ chỗ?',
        text: "Bạn có chắc chắn muốn hủy khung giờ đã chọn không?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e63b6f',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Không'
    });
    if (result.isConfirmed) {
        await releaseActiveLock();
        selectedSlots.value = [];
        toast.info('Đã hủy giữ chỗ.');
        fetchAvailableSlots(true);
    }
};

const handleBeforeUnload = (e) => {
    if (activeLock.value?.lock_token) {
        // Send a synchronous request or fire-and-forget to release lock when closing tab
        store.releaseLock({ lock_token: activeLock.value.lock_token }).catch(() => {});
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    releaseActiveLock();
});
</script>

<template>
    <!-- Full Page Loading -->
    <div v-if="store.loading && !store.currentCourt" class="d-flex justify-content-center align-items-center py-5" style="min-height: 400px;">
        <div class="text-center">
            <div class="spinner-border mb-3" style="color: var(--court-primary); width: 2.5rem; height: 2.5rem;" role="status"></div>
            <p class="text-muted">Đang tải thông tin sân...</p>
        </div>
    </div>

    <div v-else-if="store.currentCourt" class="container py-4">
        <!-- Header -->
        <div class="d-flex align-items-center mb-4 flex-wrap gap-2">
            <button class="btn btn-light rounded-circle p-2 me-2" style="width: 40px; height: 40px; box-shadow: var(--court-ambient-shadow);" @click="router.back()">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div>
                <h2 class="fw-bold mb-0" style="color: var(--court-primary);">{{ courtName }}</h2>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="status-badge" :class="store.currentCourt.status === 'active' ? 'status-badge--active' : 'status-badge--maintenance'">
                        <span class="pulse-dot" :class="store.currentCourt.status === 'active' ? 'pulse-dot--active' : 'pulse-dot--maintenance'"></span>
                        {{ store.currentCourt.status === 'active' ? 'Đang hoạt động' : 'Bảo trì' }}
                    </span>
                    <span class="text-muted" style="font-size: 0.8rem;">•</span>
                    <span class="text-muted" style="font-size: 0.85rem;">{{ courtType }}</span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Content -->
            <div class="col-lg-8 mb-4">
                <!-- Court Image & Info -->
                <div class="card border-0 rounded-4 overflow-hidden mb-4" style="box-shadow: var(--court-ambient-shadow);">
                    <img :src="store.currentCourt.image_url || 'https://placehold.co/800x400/1a1a2e/e63b6f?text=' + courtName" class="card-img-top" style="height: 280px; object-fit: cover;" :alt="courtName">
                    <div class="card-body p-4" style="background: var(--court-section-bg, #f8f9fb);">
                        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2" style="color: var(--court-primary);"></i>Thông tin sân</h5>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="d-inline-flex align-items-center gap-1 px-3 py-2 rounded-pill" style="background: var(--court-card-bg, #fff); font-size: 0.8rem; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                                <i class="bi bi-layers" style="color: var(--court-primary);"></i> {{ courtType }}
                            </span>
                            <span v-if="store.currentCourt.surface" class="d-inline-flex align-items-center gap-1 px-3 py-2 rounded-pill" style="background: var(--court-card-bg, #fff); font-size: 0.8rem; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                                <i class="bi bi-grid-3x3" style="color: var(--court-playing);"></i> {{ store.currentCourt.surface }}
                            </span>
                            <span v-if="store.currentCourt.max_players" class="d-inline-flex align-items-center gap-1 px-3 py-2 rounded-pill" style="background: var(--court-card-bg, #fff); font-size: 0.8rem; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                                <i class="bi bi-people" style="color: var(--court-available);"></i> Tối đa {{ store.currentCourt.max_players }} người
                            </span>
                        </div>
                        <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.6;">
                            {{ store.currentCourt.description || 'Sân cầu lông đạt chuẩn thi đấu, bề mặt thảm chất lượng cao chống trơn trượt.' }}
                        </p>
                    </div>
                </div>

                <!-- Date & Time Selection -->
                <div class="card border-0 rounded-4 mb-4" style="box-shadow: var(--court-ambient-shadow);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-calendar-check me-2" style="color: var(--court-primary);"></i>Chọn Ngày & Giờ
                            </h5>
                            <input type="date" class="form-control w-auto fw-semibold" v-model="selectedDate" 
                                   :min="toLocalDateString()"
                                   style="border-radius: 10px; border-color: rgba(0,0,0,0.08); background: var(--court-section-bg, #f8f9fb);">
                        </div>

                        <!-- Quick Date Selector -->
                        <div class="d-flex gap-2 mb-4 overflow-auto pb-2" style="scrollbar-width: thin;">
                            <button v-for="qd in quickDates" :key="qd.value"
                                    class="btn flex-shrink-0 d-flex flex-column align-items-center px-3 py-2 rounded-3"
                                    :class="selectedDate === qd.value ? 'quick-date--selected' : 'quick-date'"
                                    @click="selectedDate = qd.value">
                                <span style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">{{ qd.day }}</span>
                                <span style="font-size: 1.1rem; font-weight: 800;">{{ qd.date }}</span>
                            </button>
                        </div>

                        <!-- Loading Slots -->
                        <div v-if="loadingSlots" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm me-2" style="color: var(--court-primary);" role="status"></div>
                            <span class="text-muted" style="font-size: 0.9rem;">Đang tải lịch...</span>
                        </div>

                        <!-- Empty Slots -->
                        <div v-else-if="availableSlots.length === 0" class="court-empty-state" style="padding: 32px 20px;">
                            <div class="court-empty-state__icon"><i class="bi bi-calendar-x"></i></div>
                            <div class="court-empty-state__title">Không có khung giờ nào</div>
                            <div class="court-empty-state__text">Vui lòng chọn ngày khác hoặc liên hệ quản trị viên để thiết lập lịch.</div>
                        </div>

                        <!-- Time Slots Grid -->
                        <div v-else>
                            <!-- Legend -->
                            <div class="d-flex gap-3 mb-3 flex-wrap">
                                <span class="d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                    <span style="width: 12px; height: 12px; border-radius: 4px; background: var(--court-available);"></span> Trống
                                </span>
                                <span class="d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                    <span style="width: 12px; height: 12px; border-radius: 4px; background: var(--court-primary);"></span> Đã chọn
                                </span>
                                <span class="d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                    <span style="width: 12px; height: 12px; border-radius: 4px; background: var(--court-closed);"></span> Đã đặt
                                </span>
                                <span class="d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                    <span style="width: 12px; height: 12px; border-radius: 4px; background: var(--court-pending);"></span> Đang giữ
                                </span>
                                <span class="d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                    <span style="width: 12px; height: 12px; border-radius: 4px; background: #adb5bd;"></span> Không khả dụng
                                </span>
                            </div>

                            <div class="client-timeline-wrapper" ref="timelineWrapper">
                                <div class="client-timeline">
                                    <div v-for="slot in availableSlots" :key="slot.start_time"
                                        class="client-timeline-slot"
                                        :class="{
                                            'slot--selected': isSlotSelected(slot),
                                            'slot--available': slot.status === 'available',
                                            'slot--booked': slot.status === 'booked',
                                            'slot--locked': slot.status === 'locked',
                                            'slot--unavailable': ['maintenance', 'past', 'closed'].includes(slot.status)
                                        }"
                                        @click="(slot.status === 'available' || slot.is_my_lock) && toggleSlot(slot)"
                                        :title="getSlotStatusLabel(slot.status) + (slot.price && slot.status === 'available' ? ' - ' + formatCurrency(slot.price) : '')"
                                    >
                                        <div class="slot-time-label">{{ formatTime(slot.start_time) }}</div>
                                        <div class="slot-bar">
                                            <i v-if="slot.status === 'booked'" class="bi bi-x"></i>
                                            <i v-else-if="slot.status === 'locked'" class="bi bi-lock-fill"></i>
                                            <i v-else-if="isSlotSelected(slot)" class="bi bi-check2"></i>
                                        </div>
                                    </div>
                                    <!-- End label for the last slot -->
                                    <div class="client-timeline-slot client-timeline-slot--end" v-if="availableSlots.length > 0">
                                        <div class="slot-time-label">{{ formatTime(availableSlots[availableSlots.length - 1].end_time) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Services -->
                <div v-if="services.length > 0" class="card border-0 rounded-4" style="box-shadow: var(--court-ambient-shadow);">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4"><i class="bi bi-bag-plus me-2" style="color: var(--court-primary);"></i>Dịch Vụ Mua Thêm</h5>
                        <div class="row g-3">
                            <div v-for="service in services" :key="service.service_id || service.id" class="col-md-6">
                                <div class="service-item">
                                    <div class="service-item__info">
                                        <div class="service-item__icon" style="color: var(--court-primary);">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1" style="font-size: 0.9rem;">{{ service.service_name }}</h6>
                                            <span style="color: var(--court-primary); font-weight: 600; font-size: 0.8rem;">
                                                {{ formatCurrency(service.unit_price) }} / {{ service.unit || 'lần' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="service-item__qty-control">
                                        <button class="btn btn-sm btn-link text-dark text-decoration-none px-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" @click="decreaseService(service)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                        </button>
                                        <span class="fw-bold px-2" style="min-width: 20px; text-align: center;">{{ service.quantity }}</span>
                                        <button class="btn btn-sm btn-link text-decoration-none px-2 d-flex align-items-center justify-content-center" style="color: var(--court-primary); width: 28px; height: 28px;" @click="increaseService(service)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Content: Summary -->
            <div class="col-lg-4">
                <div class="card border-0 rounded-4 booking-summary">
                    <div class="card-body p-4 d-flex flex-column gap-3">
                        <h5 class="fw-bold border-bottom pb-3 mb-0">
                            <i class="bi bi-receipt me-2" style="color: var(--court-primary);"></i>Tóm Tắt Đặt Sân
                        </h5>

                        <!-- Details -->
                        <div class="text-muted" style="font-size: 0.9rem;">
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-calendar3 me-1"></i> Ngày</span>
                                <strong class="text-dark">{{ selectedDate }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-clock me-1"></i> Thời gian</span>
                                <strong class="text-dark" v-if="selectedSlots.length > 0">
                                    {{ formatTime(selectedSlots[0].start_time) }} - {{ formatTime(selectedSlots[selectedSlots.length - 1].end_time) }}
                                </strong>
                                <span v-else style="color: var(--court-maintenance); font-size: 0.8rem;">Chưa chọn giờ</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="bi bi-building me-1"></i> Sân</span>
                                <strong class="text-dark">{{ courtName }}</strong>
                            </div>
                        </div>

                        <hr style="border-color: rgba(0,0,0,0.06); margin: 0;">

                        <!-- Pricing -->
                        <div class="text-muted" style="font-size: 0.9rem;">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tiền Sân ({{ bookingSummary.totalTime }}h)</span>
                                <strong class="text-dark">{{ formatCurrency(bookingSummary.courtFee) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between" v-if="selectedServices.length > 0">
                                <span>Dịch vụ thêm ({{ selectedServices.length }})</span>
                                <strong class="text-dark">{{ formatCurrency(bookingSummary.serviceFee) }}</strong>
                            </div>
                        </div>

                        <!-- Payment method -->
                        <div>
                            <label class="form-label fw-semibold" style="font-size: 0.8rem; color: #6c757d;">Phương thức thanh toán</label>
                            <select class="form-select" v-model="paymentMethod" style="border-radius: 10px; border-color: rgba(0,0,0,0.08);">
                                <option value="cash">Tiền mặt tại quầy</option>
                                <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                                <option value="vnpay">VNPay</option>
                                <option value="momo">MoMo</option>
                            </select>
                        </div>

                        <!-- Total & CTA -->
                        <div class="border-top pt-3 mt-auto" style="border-color: rgba(0,0,0,0.06) !important;">
                            <div class="d-flex justify-content-between align-items-end mb-4">
                                <span class="text-muted" style="font-size: 1rem;">Tổng cộng</span>
                                <span class="booking-summary__total">{{ formatCurrency(bookingSummary.total) }}</span>
                            </div>

                            <div v-if="activeLock && lockCountdown > 0" class="d-flex justify-content-between align-items-center mb-3 px-3 py-2 rounded-3" style="background: rgba(25,135,84,0.08); color: var(--court-available); font-size: 0.82rem; font-weight: 700;">
                                <span>Đang giữ chỗ: {{ Math.floor(lockCountdown / 60) }}:{{ String(lockCountdown % 60).padStart(2, '0') }}</span>
                                <button @click="confirmReleaseLock" class="btn btn-sm btn-link text-danger p-0 fw-semibold text-decoration-none" style="font-size: 0.8rem;">
                                    Hủy giữ chỗ
                                </button>
                            </div>

                            <button
                                class="booking-summary__cta"
                                :disabled="selectedSlots.length === 0 || bookingInProgress"
                                @click="proceedBooking"
                            >
                                <span v-if="bookingInProgress" class="spinner-border spinner-border-sm me-2"></span>
                                Đặt Sân Ngay <i class="bi bi-arrow-right ms-2"></i>
                            </button>

                            <p class="text-center text-muted mt-3 mb-0" style="font-size: 0.78rem;">
                                <i class="bi bi-shield-check me-1" style="color: var(--court-available);"></i>
                                Thanh toán an toàn và bảo mật
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Quick Date Selector */
.quick-date {
    background: var(--court-section-bg, #f8f9fb);
    border: 1.5px solid rgba(0,0,0,0.06);
    color: var(--text-main, #212529);
    min-width: 56px;
    transition: all 0.2s;
}

.quick-date:hover {
    border-color: var(--court-primary);
    color: var(--court-primary);
}

.quick-date--selected {
    background: var(--court-primary) !important;
    border-color: var(--court-primary) !important;
    color: #fff !important;
    box-shadow: 0 2px 8px rgba(230, 59, 111, 0.25);
}

/* Client Timeline */
.client-timeline-wrapper {
    width: 100%;
    overflow-x: auto;
    padding: 20px 0 10px 0;
    scrollbar-width: thin;
}
.client-timeline {
    display: flex;
    align-items: flex-start;
    min-width: max-content;
    padding: 0 10px;
}
.client-timeline-slot {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 60px; /* Thinner for 30 min slots */
    position: relative;
    cursor: pointer;
}
.client-timeline-slot--end {
    width: auto;
    cursor: default;
}
.slot-time-label {
    font-size: 0.75rem;
    color: var(--text-muted, #6c757d);
    font-weight: 600;
    margin-bottom: 8px;
    transform: translateX(-50%);
}
.client-timeline-slot--end .slot-time-label {
    transform: translateX(-50%);
}
.slot-bar {
    height: 40px;
    width: 100%;
    background: #e9ecef;
    border-right: 1px solid rgba(255,255,255,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 1.2rem;
    color: white;
}
.client-timeline-slot:first-child .slot-bar {
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
}
.client-timeline-slot:nth-last-child(2) .slot-bar {
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
    border-right: none;
}

.slot--available .slot-bar {
    background: rgba(25, 135, 84, 0.15); /* light green */
}
.slot--available:hover .slot-bar {
    background: rgba(25, 135, 84, 0.3);
}

.slot--selected .slot-bar {
    background: var(--court-primary) !important;
    box-shadow: 0 0 8px rgba(230, 59, 111, 0.4);
    z-index: 2;
}

.slot--booked .slot-bar {
    background: rgba(220, 53, 69, 0.15); /* light red */
    color: #dc3545;
    cursor: not-allowed;
}

.slot--locked .slot-bar {
    background: var(--court-pending-bg, rgba(255, 193, 7, 0.15));
    color: var(--court-pending, #ffc107);
    cursor: not-allowed;
}

.slot--unavailable .slot-bar {
    background: #e9ecef;
    cursor: not-allowed;
}

</style>
