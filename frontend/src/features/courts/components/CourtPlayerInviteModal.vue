<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { openPlayService } from '@/features/courts/services/openPlayService';
import { useAuthStore } from '@/stores/auth';
import Swal from 'sweetalert2';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    booking: {
        type: Object,
        required: true
    },
    isInviteeView: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'updated']);

const authStore = useAuthStore();
const currentUserId = computed(() => authStore.user?.user_id || authStore.user?.id || 0);

const loading = ref(false);
const actionLoading = ref(false);
const openPlayData = ref(null);
const activeTab = ref('invite'); // 'invite' | 'players'

// Invite capacity configuration
const additionalSlots = ref(3);
const maxCapacity = computed(() => additionalSlots.value + 1);
const joinMode = ref('auto'); // 'auto' | 'approval'

// User search & invite
const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);
const selectedUserIds = ref([]);
let searchTimeout = null;

// Copy link state
const copied = ref(false);

const isHost = computed(() => {
    if (!props.booking) return false;
    const hostId = openPlayData.value?.host_user_id || props.booking.user_id;
    return hostId === currentUserId.value;
});

const isParticipant = computed(() => {
    if (!openPlayData.value?.participants) return false;
    return openPlayData.value.participants.some(
        p => p.user_id === currentUserId.value && ['confirmed', 'approved', 'host'].includes(p.status)
    );
});

const isPending = computed(() => {
    if (!openPlayData.value?.participants) return false;
    return openPlayData.value.participants.some(
        p => p.user_id === currentUserId.value && p.status === 'pending'
    );
});

const isInWaitlist = computed(() => {
    if (!openPlayData.value?.waitlists) return false;
    return openPlayData.value.waitlists.some(
        w => w.user_id === currentUserId.value && w.status === 'waiting'
    );
});

const confirmedParticipants = computed(() => {
    if (!openPlayData.value?.participants) return [];
    return openPlayData.value.participants.filter(
        p => ['confirmed', 'approved', 'host', 'checked_in', 'completed'].includes(p.status)
    );
});

const remainingSlots = computed(() => {
    if (!openPlayData.value) return additionalSlots.value;
    return Math.max(0, (openPlayData.value.max_players || maxCapacity.value) - (openPlayData.value.current_players || confirmedParticipants.value.length || 1));
});

const isFull = computed(() => {
    if (!openPlayData.value) return false;
    return openPlayData.value.status === 'full' || remainingSlots.value <= 0;
});

const shareLink = computed(() => {
    if (!props.booking) return '';
    const bookingId = props.booking.booking_id || props.booking.id;
    const opId = openPlayData.value?.id || '';
    return `${window.location.origin}/profile/court-bookings?booking_id=${bookingId}&open_play_id=${opId}`;
});

let realtimeChannel = null;

const subscribeRealtime = () => {
    if (!window.Echo || !openPlayData.value?.id) return;
    leaveRealtime();
    const opId = openPlayData.value.id;
    realtimeChannel = window.Echo.channel(`open-play.${opId}`)
        .listen('.OpenPlayParticipantJoined', () => fetchCollaborationData(true))
        .listen('.OpenPlayParticipantLeft', () => fetchCollaborationData(true))
        .listen('.OpenPlayParticipantRemoved', () => fetchCollaborationData(true))
        .listen('.OpenPlayStatusUpdated', () => fetchCollaborationData(true));
};

const leaveRealtime = () => {
    if (window.Echo && openPlayData.value?.id) {
        window.Echo.leave(`open-play.${openPlayData.value.id}`);
    }
    realtimeChannel = null;
};

const fetchCollaborationData = async (silent = false) => {
    if (!props.booking) return;
    const bookingId = props.booking.booking_id || props.booking.id;
    if (!silent) loading.value = true;
    try {
        const res = await openPlayService.getByBooking(bookingId);
        if (res && res.data?.data) {
            openPlayData.value = res.data.data;
            if (openPlayData.value.max_players) {
                additionalSlots.value = Math.max(1, openPlayData.value.max_players - 1);
            }
            if (openPlayData.value.join_mode) {
                joinMode.value = openPlayData.value.join_mode;
            }
            subscribeRealtime();
        }
    } catch (e) {
        // If not created yet and user is host, we will init when user clicks save or invites
    } finally {
        if (!silent) loading.value = false;
    }
};

const initOrUpdateCapacity = async () => {
    if (!props.booking || !isHost.value) return;
    const bookingId = props.booking.booking_id || props.booking.id;
    actionLoading.value = true;
    try {
        const res = await openPlayService.initForBooking(bookingId, {
            additional_slots: additionalSlots.value,
            max_players: maxCapacity.value,
            join_mode: joinMode.value
        });
        if (res && res.data?.data) {
            openPlayData.value = res.data.data;
            subscribeRealtime();
            emit('updated', openPlayData.value);
            Swal.fire({
                icon: 'success',
                title: 'Đã lưu thiết lập người chơi!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
        }
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: e?.response?.data?.message || 'Không thể cập nhật cấu hình người chơi.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    } finally {
        actionLoading.value = false;
    }
};

const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        return;
    }
    searchTimeout = setTimeout(async () => {
        searching.value = true;
        try {
            const opId = openPlayData.value?.id || null;
            const res = await openPlayService.searchInvitees(searchQuery.value.trim(), opId);
            searchResults.value = res?.data?.data || [];
        } catch (e) {
            searchResults.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
};

const toggleUserSelection = (uid) => {
    const idx = selectedUserIds.value.indexOf(uid);
    if (idx === -1) {
        if (selectedUserIds.value.length >= remainingSlots.value) {
            Swal.fire({
                icon: 'warning',
                title: `Chỉ còn ${remainingSlots.value} slot trống`,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500
            });
            return;
        }
        selectedUserIds.value.push(uid);
    } else {
        selectedUserIds.value.splice(idx, 1);
    }
};

const sendUserInvites = async () => {
    if (!selectedUserIds.value.length) return;
    actionLoading.value = true;
    try {
        // Ensure open play record exists first
        if (!openPlayData.value?.id) {
            await initOrUpdateCapacity();
        }
        const opId = openPlayData.value?.id;
        if (!opId) throw new Error('Không thể khởi tạo trận chơi');

        const res = await openPlayService.inviteUsers(opId, selectedUserIds.value);
        Swal.fire({
            icon: 'success',
            title: res.data?.message || 'Đã gửi lời mời thành công!',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        selectedUserIds.value = [];
        searchQuery.value = '';
        searchResults.value = [];
        await fetchCollaborationData(true);
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: e?.response?.data?.message || 'Không thể gửi lời mời.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    } finally {
        actionLoading.value = false;
    }
};

const copyShareLink = async () => {
    try {
        // Ensure initialized
        if (!openPlayData.value?.id && isHost.value) {
            await initOrUpdateCapacity();
        }
        await navigator.clipboard.writeText(shareLink.value);
        copied.value = true;
        setTimeout(() => copied.value = false, 3000);
        Swal.fire({
            icon: 'success',
            title: 'Đã sao chép liên kết mời chơi!',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Không thể sao chép liên kết' });
    }
};

// Invitee Join Action
const handleJoinMatch = async () => {
    if (!openPlayData.value?.id) return;
    actionLoading.value = true;
    try {
        const res = await openPlayService.joinOpenPlay(openPlayData.value.id);
        Swal.fire({
            icon: 'success',
            title: res.data?.message || 'Tham gia trận chơi thành công! 🏸',
            text: `Bạn đã tham gia vào lịch đặt sân #${props.booking.booking_code || props.booking.booking_id}`,
            confirmButtonColor: '#e63b6f'
        });
        await fetchCollaborationData();
        emit('updated', openPlayData.value);
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Không thể tham gia',
            text: e?.response?.data?.message || 'Trận chơi đã đủ người hoặc lời mời không còn khả dụng.',
            confirmButtonColor: '#e63b6f'
        });
    } finally {
        actionLoading.value = false;
    }
};

// Leave Action
const handleLeaveMatch = async () => {
    if (!openPlayData.value?.id) return;
    const result = await Swal.fire({
        title: 'Rời khỏi trận?',
        text: 'Bạn có chắc chắn muốn hủy tham gia trận đấu này không?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Rời trận',
        cancelButtonText: 'Ở lại'
    });
    if (!result.isConfirmed) return;

    actionLoading.value = true;
    try {
        await openPlayService.leaveOpenPlay(openPlayData.value.id);
        Swal.fire({
            icon: 'success',
            title: 'Đã rời khỏi trận chơi',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500
        });
        await fetchCollaborationData();
        emit('updated', openPlayData.value);
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: e?.response?.data?.message || 'Không thể rời trận.',
            confirmButtonColor: '#e63b6f'
        });
    } finally {
        actionLoading.value = false;
    }
};

// Remove Participant by Host
const handleRemoveParticipant = async (pId, pName) => {
    if (!openPlayData.value?.id || !isHost.value) return;
    const result = await Swal.fire({
        title: `Xóa người chơi?`,
        text: `Bạn có chắc muốn xóa ${pName} khỏi trận không?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    actionLoading.value = true;
    try {
        await openPlayService.removeParticipant(openPlayData.value.id, pId);
        Swal.fire({
            icon: 'success',
            title: `Đã xóa ${pName} khỏi trận`,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500
        });
        await fetchCollaborationData();
        emit('updated', openPlayData.value);
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: e?.response?.data?.message || 'Không thể xóa người chơi.',
            confirmButtonColor: '#e63b6f'
        });
    } finally {
        actionLoading.value = false;
    }
};

// Join Waitlist if Full
const handleJoinWaitlist = async () => {
    if (!openPlayData.value?.id) return;
    actionLoading.value = true;
    try {
        await openPlayService.joinWaitlist(openPlayData.value.id);
        Swal.fire({
            icon: 'success',
            title: 'Đã vào danh sách chờ!',
            text: 'Khi có người chơi rời trận, hệ thống sẽ tự động chuyển bạn vào trận.',
            confirmButtonColor: '#e63b6f'
        });
        await fetchCollaborationData();
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: e?.response?.data?.message || 'Không thể tham gia danh sách chờ.',
            confirmButtonColor: '#e63b6f'
        });
    } finally {
        actionLoading.value = false;
    }
};

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('vi-VN');
};

const formatTime = (t) => {
    if (!t) return '';
    return t.substring(0, 5);
};

watch(() => props.modelValue, (val) => {
    if (val && props.booking) {
        activeTab.value = isHost.value ? 'invite' : 'players';
        fetchCollaborationData();
    } else {
        leaveRealtime();
    }
});

onUnmounted(() => {
    leaveRealtime();
});
</script>

<template>
    <div v-if="modelValue" class="modal-backdrop fade show" style="background: rgba(15, 23, 42, 0.65); z-index: 1050;"></div>

    <div v-if="modelValue" class="modal fade show d-block" tabindex="-1" style="z-index: 1055;" @click.self="emit('update:modelValue', false)">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                
                <!-- Modal Header -->
                <div class="modal-header bg-light px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-icon bg-pink-soft text-pink rounded-3 d-flex align-items-center justify-content-center">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-dark">
                                Rủ Người Chơi Cùng (Match Invitation)
                            </h5>
                            <span class="text-muted small">
                                Mã đơn: <strong>{{ booking?.booking_code || `#${booking?.booking_id}` }}</strong>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" @click="emit('update:modelValue', false)"></button>
                </div>

                <!-- Booking Summary Bar -->
                <div class="bg-gradient-pink-soft px-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
                    <div class="d-flex align-items-center gap-2 small text-dark">
                        <span class="badge bg-white text-dark shadow-sm px-2 py-1 rounded-pill">
                            <i class="bi bi-geo-alt-fill text-pink me-1"></i>
                            {{ booking?.court?.court_name || 'Sân cầu lông' }}
                        </span>
                        <span>•</span>
                        <span><i class="bi bi-calendar-event me-1"></i>{{ formatDate(booking?.booking_date) }}</span>
                        <span>•</span>
                        <span><i class="bi bi-clock me-1"></i>{{ formatTime(booking?.start_time) }} - {{ formatTime(booking?.end_time) }}</span>
                    </div>

                    <!-- Slot Counter Badge -->
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill px-3 py-1 fw-bold" :class="isFull ? 'bg-danger text-white' : 'bg-success text-white'">
                            <i class="bi" :class="isFull ? 'bi-lock-fill' : 'bi-unlock-fill'"></i>
                            {{ confirmedParticipants.length || 1 }} / {{ openPlayData?.max_players || maxCapacity }} người
                            <span v-if="!isFull" class="ms-1 font-monospace">({{ remainingSlots }} slot trống)</span>
                        </span>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-4">
                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-pink" role="status"></div>
                        <p class="text-muted mt-2 small">Đang tải thông tin người chơi...</p>
                    </div>

                    <!-- Invitee Join Hero Banner (if opened by someone not yet joined) -->
                    <div v-else-if="!isHost && !isParticipant && !isInWaitlist" class="invitee-banner p-3 mb-4 rounded-3 border border-pink-soft bg-pink-light">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h6 class="fw-bold mb-1 text-pink-dark">
                                    <i class="bi bi-envelope-heart-fill me-1"></i>
                                    {{ openPlayData?.host?.full_name || 'Người đặt sân' }} đã mời bạn chơi!
                                </h6>
                                <p class="small text-muted mb-0">
                                    Tham gia giao lưu và chia sẻ niềm đam mê thể thao cùng nhau.
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <button v-if="!isFull" class="btn btn-pink px-4 py-2 rounded-pill fw-bold shadow-sm" :disabled="actionLoading" @click="handleJoinMatch">
                                    <i class="bi bi-check-circle-fill me-1"></i> Tham Gia Ngay
                                </button>
                                <button v-else class="btn btn-warning px-3 py-2 rounded-pill fw-bold shadow-sm" :disabled="actionLoading" @click="handleJoinWaitlist">
                                    <i class="bi bi-hourglass-split me-1"></i> Vào Danh Sách Chờ
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs -->
                    <ul class="nav nav-pills nav-fill mb-4 p-1 bg-light rounded-3">
                        <li v-if="isHost" class="nav-item">
                            <button class="nav-link rounded-3 fw-semibold small py-2" :class="{ 'active bg-pink text-white shadow-sm': activeTab === 'invite' }" @click="activeTab = 'invite'">
                                <i class="bi bi-person-plus-fill me-1"></i> Mời Thêm Người Chơi
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link rounded-3 fw-semibold small py-2" :class="{ 'active bg-pink text-white shadow-sm': activeTab === 'players' }" @click="activeTab = 'players'">
                                <i class="bi bi-people-fill me-1"></i> Danh Sách Tham Gia ({{ confirmedParticipants.length }}/{{ openPlayData?.max_players || maxCapacity }})
                            </button>
                        </li>
                    </ul>

                    <!-- TAB 1: INVITE PLAYERS (Host only) -->
                    <div v-if="activeTab === 'invite' && isHost" class="invite-tab-content">
                        
                        <!-- Capacity Selector Section -->
                        <div class="card border-0 bg-light rounded-3 p-3 mb-3">
                            <label class="form-label fw-bold small text-dark mb-2">
                                1. Bạn muốn mời thêm bao nhiêu người vào sân?
                            </label>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div class="stepper-box d-flex align-items-center bg-white rounded-pill border px-2 py-1 shadow-sm">
                                    <button class="btn btn-sm btn-light rounded-circle px-2" :disabled="additionalSlots <= 1" @click="additionalSlots--">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <span class="px-3 fw-bold text-pink font-monospace fs-5">{{ additionalSlots }}</span>
                                    <button class="btn btn-sm btn-light rounded-circle px-2" :disabled="additionalSlots >= 11" @click="additionalSlots++">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <div class="text-muted small">
                                    <span class="badge bg-secondary me-1">Host: 1 người (Bạn)</span> + 
                                    <span class="badge bg-pink text-white ms-1">{{ additionalSlots }} người mời</span> = 
                                    <strong class="text-dark ms-1">Tổng {{ maxCapacity }} người</strong>
                                </div>
                                <button class="btn btn-sm btn-outline-pink rounded-pill px-3 fw-bold ms-auto" :disabled="actionLoading" @click="initOrUpdateCapacity">
                                    <i class="bi bi-check2 me-1"></i> Lưu Thiết Lập
                                </button>
                            </div>
                        </div>

                        <!-- 2 Invitation Options -->
                        <div class="row g-3">
                            <!-- Option A: Direct User Search & Send Notification -->
                            <div class="col-md-6">
                                <div class="card h-100 border rounded-3 p-3 shadow-none">
                                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                                        <i class="bi bi-search text-pink me-2"></i>
                                        A. Mời Người Dùng Hệ Thống
                                    </h6>
                                    <p class="small text-muted mb-2">Tìm kiếm bạn bè qua tên, SĐT hoặc Email để gửi thông báo mời trực tiếp:</p>

                                    <!-- Search Input -->
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text" class="form-control rounded-start-pill" placeholder="Nhập tên, sđt hoặc email..." v-model="searchQuery" @input="handleSearch">
                                        <span class="input-group-text bg-white border-start-0 rounded-end-pill">
                                            <i v-if="searching" class="spinner-border spinner-border-sm text-pink"></i>
                                            <i v-else class="bi bi-search text-muted"></i>
                                        </span>
                                    </div>

                                    <!-- Search Results -->
                                    <div class="search-results-box border rounded-3 p-2 bg-white overflow-auto mb-2" style="max-height: 180px;">
                                        <div v-if="!searchQuery" class="text-muted text-center py-3 small">
                                            Nhập từ khóa để tìm bạn bè...
                                        </div>
                                        <div v-else-if="!searchResults.length && !searching" class="text-muted text-center py-3 small">
                                            Không tìm thấy người dùng phù hợp.
                                        </div>
                                        <div v-for="u in searchResults" :key="u.user_id" 
                                             class="d-flex align-items-center justify-content-between p-2 rounded-2 hover-bg-light cursor-pointer"
                                             :class="{ 'bg-pink-soft': selectedUserIds.includes(u.user_id) }"
                                             @click="toggleUserSelection(u.user_id)">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar-sm rounded-circle bg-pink text-white d-flex align-items-center justify-content-center fw-bold small">
                                                    {{ u.full_name?.charAt(0) || 'U' }}
                                                </div>
                                                <div>
                                                    <div class="fw-bold small text-dark">{{ u.full_name }}</div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">{{ u.phone || u.email }}</div>
                                                </div>
                                            </div>
                                            <input type="checkbox" class="form-check-input" :checked="selectedUserIds.includes(u.user_id)">
                                        </div>
                                    </div>

                                    <button class="btn btn-pink btn-sm w-100 rounded-pill fw-bold" 
                                            :disabled="!selectedUserIds.length || actionLoading" 
                                            @click="sendUserInvites">
                                        <i class="bi bi-send-fill me-1"></i> Gửi Lời Mời ({{ selectedUserIds.length }} người)
                                    </button>
                                </div>
                            </div>

                            <!-- Option B: Share Link -->
                            <div class="col-md-6">
                                <div class="card h-100 border rounded-3 p-3 shadow-none bg-light-subtle">
                                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                                        <i class="bi bi-link-45deg text-pink me-2 fs-5"></i>
                                        B. Chia Sẻ Liên Kết Mời Chơi
                                    </h6>
                                    <p class="small text-muted mb-3">
                                        Gửi link trực tiếp qua Zalo, Messenger hoặc SMS. Bất kỳ ai mở link đều có thể bấm tham gia vào trận:
                                    </p>

                                    <div class="input-group input-group-sm mb-3">
                                        <input type="text" class="form-control font-monospace small bg-white" readonly :value="shareLink">
                                        <button class="btn btn-outline-pink px-3" type="button" @click="copyShareLink">
                                            <i class="bi" :class="copied ? 'bi-check-lg text-success' : 'bi-clipboard'"></i>
                                            {{ copied ? 'Đã chép' : 'Chép link' }}
                                        </button>
                                    </div>

                                    <div class="p-2 bg-white rounded-3 border small text-muted">
                                        <i class="bi bi-shield-check text-success me-1"></i>
                                        Hệ thống sẽ tự động khóa và thông báo khi đã nhận đủ số lượng người chơi.
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- TAB 2: PARTICIPANTS LIST -->
                    <div v-else class="players-tab-content">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0">
                                Danh Sách Người Chơi ({{ confirmedParticipants.length }}/{{ openPlayData?.max_players || maxCapacity }})
                            </h6>
                            <button v-if="isParticipant && !isHost" class="btn btn-outline-danger btn-sm rounded-pill fw-semibold" @click="handleLeaveMatch">
                                <i class="bi bi-box-arrow-right me-1"></i> Rời Khỏi Trận
                            </button>
                        </div>

                        <!-- Participants list -->
                        <div class="d-flex flex-column gap-2 mb-4">
                            <!-- Host Row -->
                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border bg-light">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-circle bg-pink text-white rounded-circle d-flex align-items-center justify-content-center fw-bold">
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">
                                            {{ openPlayData?.host?.full_name || booking?.user?.full_name || 'Host' }}
                                            <span v-if="isHost" class="badge bg-pink text-white ms-1">Bạn</span>
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary small">Chủ sân (Host)</span>
                                    </div>
                                </div>
                                <span class="badge bg-success small"><i class="bi bi-check2"></i> Đã xác nhận</span>
                            </div>

                            <!-- Other Participants -->
                            <template v-for="p in confirmedParticipants" :key="p.id">
                                <div v-if="p.role !== 'host'" class="d-flex align-items-center justify-content-between p-3 rounded-3 border bg-white shadow-sm">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle bg-secondary-subtle text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold">
                                            {{ p.user?.full_name?.charAt(0) || p.guest_name?.charAt(0) || 'P' }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                {{ p.user?.full_name || p.guest_name || 'Người chơi' }}
                                                <span v-if="p.user_id === currentUserId" class="badge bg-pink text-white ms-1">Bạn</span>
                                            </div>
                                            <span class="text-muted small">Tham gia: {{ formatDate(p.joined_at) }}</span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success-subtle text-success small"><i class="bi bi-check2"></i> Đã tham gia</span>
                                        <button v-if="isHost" class="btn btn-sm btn-outline-danger px-2 py-1 rounded-circle" title="Xóa người chơi" @click="handleRemoveParticipant(p.id, p.user?.full_name || p.guest_name)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty Slots Placeholders -->
                            <div v-for="slot in remainingSlots" :key="'empty-slot-' + slot" class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-dashed text-muted">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-circle border border-dashed rounded-circle d-flex align-items-center justify-content-center text-muted">
                                        <i class="bi bi-plus-lg"></i>
                                    </div>
                                    <span class="small fst-italic">Slot còn trống</span>
                                </div>
                                <span class="badge bg-light text-muted border">Chờ người tham gia</span>
                            </div>
                        </div>

                        <!-- Waitlist (if any) -->
                        <div v-if="openPlayData?.waitlists?.length" class="mt-4">
                            <h6 class="fw-bold text-dark mb-2 small text-uppercase">
                                <i class="bi bi-hourglass-split text-warning me-1"></i> Danh Sách Chờ (Waitlist)
                            </h6>
                            <div class="d-flex flex-column gap-2">
                                <div v-for="w in openPlayData.waitlists" :key="w.id" class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border small">
                                    <span>{{ w.user?.full_name || 'Người dùng' }}</span>
                                    <span class="badge bg-warning text-dark">Vị trí #{{ w.position }}</span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer bg-light px-4 py-2 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" @click="emit('update:modelValue', false)">
                        Đóng
                    </button>

                    <div v-if="!isHost && !isParticipant && !isInWaitlist && !isFull">
                        <button class="btn btn-pink rounded-pill px-4 fw-bold shadow-sm" :disabled="actionLoading" @click="handleJoinMatch">
                            <i class="bi bi-check-circle-fill me-1"></i> Tham Gia Trận Chơi
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.text-pink {
    color: var(--court-primary, #E63B6F) !important;
}
.text-pink-dark {
    color: #BE185D !important;
}
.bg-pink {
    background-color: var(--court-primary, #E63B6F) !important;
}
.bg-pink-soft {
    background-color: #FDF2F8 !important;
}
.bg-pink-light {
    background-color: #FFF1F2 !important;
}
.border-pink-soft {
    border-color: #FCE7F3 !important;
}
.btn-pink {
    background-color: var(--court-primary, #E63B6F);
    color: #ffffff;
    border: none;
}
.btn-pink:hover {
    background-color: #D81B60;
    color: #ffffff;
}
.btn-outline-pink {
    color: var(--court-primary, #E63B6F);
    border-color: var(--court-primary, #E63B6F);
}
.btn-outline-pink:hover {
    background-color: var(--court-primary, #E63B6F);
    color: #ffffff;
}
.bg-gradient-pink-soft {
    background: linear-gradient(135deg, #FFF1F2 0%, #FDF2F8 100%);
}
.avatar-icon {
    width: 44px;
    height: 44px;
}
.avatar-circle {
    width: 40px;
    height: 40px;
}
.user-avatar-sm {
    width: 32px;
    height: 32px;
}
.cursor-pointer {
    cursor: pointer;
}
.hover-bg-light:hover {
    background-color: #f8fafc;
}
</style>
