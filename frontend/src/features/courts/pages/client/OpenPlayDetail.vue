<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOpenPlayStore } from '@/features/courts/stores/useOpenPlayStore';
import { useAuthStore } from '@/stores/auth';
import Swal from 'sweetalert2';
import QRCode from 'qrcode';

const route = useRoute();
const router = useRouter();
const openPlayStore = useOpenPlayStore();
const authStore = useAuthStore();

const matchId = computed(() => Number(route.params.id));
const match = computed(() => openPlayStore.currentMatch);

// Guest OTP State
const showGuestModal = ref(false);
const guestPhone = ref('');
const guestOtp = ref('');
const guestName = ref('');
const otpSent = ref(false);
const isSendingOtp = ref(false);
const isVerifyingOtp = ref(false);

// QR Modal State
const showQrModal = ref(false);
const qrDataUrl = ref('');
const currentQrCode = ref('');

// Payment Modal State
const showPayModal = ref(false);
const selectedPaymentMethod = ref('wallet');
const isPaying = ref(false);

onMounted(async () => {
  await loadDetail();
  setupRealtime();
});

onUnmounted(() => {
  if (window.Echo && matchId.value) {
    window.Echo.leave(`open-play.${matchId.value}`);
  }
});

const loadDetail = async () => {
  try {
    await openPlayStore.fetchMatchDetail(matchId.value);
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Lỗi',
      text: 'Không tìm thấy trận đấu hoặc trận đã bị xóa.',
    });
    router.push({ name: 'open-plays-list' });
  }
};

const setupRealtime = () => {
  if (!window.Echo || !matchId.value) return;
  window.Echo.channel(`open-play.${matchId.value}`)
    .listen('.ParticipantJoined', () => {
      loadDetail();
    })
    .listen('.ParticipantLeft', () => {
      loadDetail();
    })
    .listen('.ParticipantApproved', () => {
      loadDetail();
    })
    .listen('.ParticipantRejected', () => {
      loadDetail();
    })
    .listen('.ParticipantCheckedIn', () => {
      loadDetail();
    })
    .listen('.PaymentUpdated', () => {
      loadDetail();
    })
    .listen('.OpenPlayUpdated', () => {
      loadDetail();
    })
    .listen('.OpenPlayCancelled', (e) => {
      Swal.fire({
        icon: 'warning',
        title: 'Trận đấu đã bị hủy',
        text: e.reason || 'Host đã hủy trận đấu này.',
      });
      loadDetail();
    });
};

const isHost = computed(() => {
  if (!authStore.isAuthenticated || !match.value) return false;
  return match.value.host_user_id === authStore.user?.user_id;
});

const myParticipation = computed(() => {
  if (!authStore.isAuthenticated || !match.value) return null;
  return match.value.participants?.find(
    (p) => p.user_id === authStore.user?.user_id && !['cancelled', 'rejected'].includes(p.status)
  );
});

const myWaitlist = computed(() => {
  if (!authStore.isAuthenticated || !match.value) return null;
  return match.value.waitlists?.find(
    (w) => w.user_id === authStore.user?.user_id && w.status === 'waiting'
  );
});

const confirmedParticipants = computed(() => {
  return match.value?.participants?.filter((p) => ['confirmed', 'checked_in', 'completed'].includes(p.status)) || [];
});

const pendingParticipants = computed(() => {
  return match.value?.participants?.filter((p) => p.status === 'pending') || [];
});

const activeWaitlists = computed(() => {
  return match.value?.waitlists?.filter((w) => w.status === 'waiting').sort((a, b) => a.position - b.position) || [];
});

const handleJoinClick = async () => {
  if (!authStore.isAuthenticated) {
    showGuestModal.value = true;
    return;
  }

  const result = await Swal.fire({
    title: 'Xác nhận tham gia',
    html: `
      <p class="text-secondary mb-2">Bạn đang đăng ký tham gia trận: <strong>${match.value.title}</strong></p>
      ${
        match.value.payment_mode === 'split_payment'
          ? `<div class="alert alert-info py-2">Chi phí chia sân: <strong>${formatCurrency(match.value.slot_price)}</strong> / người</div>`
          : '<div class="alert alert-success py-2">Trận đấu này do Host bao sân (Miễn phí).</div>'
      }
      <p class="small text-muted mb-0">Chế độ duyệt: <strong>${match.value.join_mode === 'auto' ? 'Tự động xác nhận' : 'Host duyệt'}</strong></p>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Đồng ý tham gia',
    cancelButtonText: 'Hủy',
    confirmButtonColor: '#e63b6f',
  });

  if (result.isConfirmed) {
    try {
      const res = await openPlayStore.joinMatch(matchId.value, {
        guest_name: authStore.user?.full_name,
        guest_phone: authStore.user?.phone,
      });
      Swal.fire({
        icon: 'success',
        title: 'Thành công',
        text: res.message || 'Bạn đã tham gia trận!',
      });
    } catch (err) {
      if (err.response?.data?.code === 'OPEN_PLAY_FULL') {
        const joinWait = await Swal.fire({
          icon: 'warning',
          title: 'Trận đã đủ người!',
          text: 'Bạn có muốn tham gia vào danh sách chờ để được tự động đôn khi có người hủy không?',
          showCancelButton: true,
          confirmButtonText: 'Vào danh sách chờ',
          cancelButtonText: 'Không',
          confirmButtonColor: '#e63b6f',
        });
        if (joinWait.isConfirmed) {
          await handleJoinWaitlist();
        }
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Lỗi',
          text: err.response?.data?.message || 'Không thể tham gia trận.',
        });
      }
    }
  }
};

const handleJoinWaitlist = async () => {
  if (!authStore.isAuthenticated) {
    showGuestModal.value = true;
    return;
  }
  try {
    const res = await openPlayStore.joinWaitlist(matchId.value);
    Swal.fire({
      icon: 'success',
      title: 'Danh sách chờ',
      text: res.message || 'Bạn đã vào danh sách chờ!',
    });
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Lỗi',
      text: err.response?.data?.message || 'Không thể vào danh sách chờ.',
    });
  }
};

const handleLeaveWaitlist = async () => {
  const result = await Swal.fire({
    title: 'Rời danh sách chờ?',
    text: 'Bạn có chắc chắn muốn hủy đăng ký khỏi danh sách chờ không?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Rời danh sách chờ',
    cancelButtonText: 'Ở lại',
  });
  if (result.isConfirmed) {
    await openPlayStore.leaveWaitlist(matchId.value);
    Swal.fire('Thành công', 'Bạn đã rời danh sách chờ.', 'success');
  }
};

const handleLeaveMatch = async () => {
  const { value: reason } = await Swal.fire({
    title: 'Xác nhận rời trận',
    input: 'text',
    inputLabel: 'Lý do rời trận (không bắt buộc)',
    inputPlaceholder: 'Nhập lý do...',
    showCancelButton: true,
    confirmButtonText: 'Xác nhận rời',
    cancelButtonText: 'Hủy',
    confirmButtonColor: '#dc3545',
  });

  if (reason !== undefined) {
    try {
      await openPlayStore.leaveMatch(matchId.value, reason);
      Swal.fire('Thành công', 'Bạn đã rời trận.', 'success');
    } catch (err) {
      Swal.fire('Lỗi', err.response?.data?.message || 'Không thể rời trận.', 'error');
    }
  }
};

// Host Management Actions
const handleApprove = async (participantId) => {
  try {
    await openPlayStore.approveParticipant(matchId.value, participantId);
    Swal.fire('Thành công', 'Đã duyệt người chơi vào trận.', 'success');
  } catch (err) {
    Swal.fire('Lỗi', err.response?.data?.message || 'Không thể duyệt.', 'error');
  }
};

const handleReject = async (participantId) => {
  const { value: reason } = await Swal.fire({
    title: 'Từ chối người chơi?',
    input: 'text',
    inputPlaceholder: 'Lý do từ chối...',
    showCancelButton: true,
    confirmButtonText: 'Từ chối',
    cancelButtonText: 'Hủy',
    confirmButtonColor: '#dc3545',
  });
  if (reason !== undefined) {
    try {
      await openPlayStore.rejectParticipant(matchId.value, participantId, reason);
      Swal.fire('Thành công', 'Đã từ chối yêu cầu tham gia.', 'success');
    } catch (err) {
      Swal.fire('Lỗi', err.response?.data?.message || 'Không thể từ chối.', 'error');
    }
  }
};

const handleRemove = async (participantId) => {
  const { value: reason } = await Swal.fire({
    title: 'Loại thành viên khỏi trận?',
    text: 'Thành viên này sẽ bị hủy khỏi trận và hệ thống sẽ tự động đôn người từ danh sách chờ (nếu có).',
    input: 'text',
    inputPlaceholder: 'Lý do...',
    showCancelButton: true,
    confirmButtonText: 'Xác nhận loại',
    cancelButtonText: 'Hủy',
    confirmButtonColor: '#dc3545',
  });
  if (reason !== undefined) {
    try {
      await openPlayStore.removeParticipant(matchId.value, participantId, reason);
      Swal.fire('Thành công', 'Đã loại thành viên khỏi trận.', 'success');
    } catch (err) {
      Swal.fire('Lỗi', err.response?.data?.message || 'Không thể loại.', 'error');
    }
  }
};

const handleCloseRegistration = async () => {
  const res = await Swal.fire({
    title: 'Đóng đăng ký sớm?',
    text: 'Trận đấu sẽ được chuyển sang trạng thái FULL và không nhận thêm người chơi nữa.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Đóng đăng ký',
    cancelButtonText: 'Hủy',
  });
  if (res.isConfirmed) {
    await openPlayStore.openPlayService.closeRegistration(matchId.value);
    await loadDetail();
    Swal.fire('Thành công', 'Đã đóng đăng ký cho trận.', 'success');
  }
};

const handleCancelMatch = async () => {
  const { value: reason } = await Swal.fire({
    title: 'Xác nhận hủy trận Open Play?',
    text: 'Toàn bộ người chơi sẽ nhận được thông báo hủy trận.',
    input: 'text',
    inputPlaceholder: 'Lý do hủy trận...',
    showCancelButton: true,
    confirmButtonText: 'Hủy trận ngay',
    cancelButtonText: 'Đóng',
    confirmButtonColor: '#dc3545',
  });
  if (reason !== undefined) {
    try {
      await openPlayStore.cancelMatch(matchId.value, reason);
      Swal.fire('Đã hủy', 'Trận đấu đã được hủy thành công.', 'success');
    } catch (err) {
      Swal.fire('Lỗi', err.response?.data?.message || 'Không thể hủy trận.', 'error');
    }
  }
};

// QR Check-in Code Generation
const showQrCode = async () => {
  if (!myParticipation.value) return;
  const token = myParticipation.value.check_in_token;
  currentQrCode.value = `OSOP:${matchId.value}:${authStore.user?.user_id}:${token}`;
  qrDataUrl.value = await QRCode.toDataURL(currentQrCode.value, { width: 280, margin: 2 });
  showQrModal.value = true;
};

// Payment Actions
const openPaymentModal = () => {
  showPayModal.value = true;
};

const submitPayment = async () => {
  isPaying.value = true;
  try {
    await openPlayStore.paySlot(matchId.value, {
      payment_method: selectedPaymentMethod.value,
    });
    showPayModal.value = false;
    Swal.fire('Thành công', 'Bạn đã thanh toán phần tiền slot thành công!', 'success');
  } catch (err) {
    Swal.fire('Lỗi thanh toán', err.response?.data?.message || 'Không thể thực hiện thanh toán.', 'error');
  } finally {
    isPaying.value = false;
  }
};

// Guest OTP Handlers
const handleSendGuestOtp = async () => {
  if (!guestPhone.value || guestPhone.value.length < 9) {
    Swal.fire('Lỗi', 'Vui lòng nhập số điện thoại hợp lệ.', 'warning');
    return;
  }
  isSendingOtp.value = true;
  try {
    const res = await openPlayStore.openPlayService.sendGuestOtp(guestPhone.value);
    otpSent.value = true;
    if (res.data?.dev_otp) {
      guestOtp.value = res.data.dev_otp;
    }
    Swal.fire('Mã OTP đã gửi', res.data?.message || 'Vui lòng kiểm tra mã OTP.', 'info');
  } catch (err) {
    Swal.fire('Lỗi', err.response?.data?.message || 'Không thể gửi mã OTP.', 'error');
  } finally {
    isSendingOtp.value = false;
  }
};

const handleVerifyGuestOtp = async () => {
  if (!guestOtp.value) {
    Swal.fire('Lỗi', 'Vui lòng nhập mã OTP.', 'warning');
    return;
  }
  isVerifyingOtp.value = true;
  try {
    const res = await openPlayStore.openPlayService.verifyGuestOtp({
      phone: guestPhone.value,
      otp: guestOtp.value,
      full_name: guestName.value,
    });
    // Set token to auth store
    authStore.setToken(res.data?.token);
    authStore.setUser(res.data?.user);
    showGuestModal.value = false;
    Swal.fire('Thành công', 'Xác thực tài khoản thành công! Bạn có thể tham gia trận ngay.', 'success');
    await loadDetail();
  } catch (err) {
    Swal.fire('Lỗi', err.response?.data?.message || 'Mã OTP không hợp lệ.', 'error');
  } finally {
    isVerifyingOtp.value = false;
  }
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const formatTime = (timeStr) => {
  if (!timeStr) return '';
  return timeStr.substring(0, 5);
};

const formatDateDisplay = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getSkillBadge = (level) => {
  const map = {
    all: { text: 'Mọi trình độ', class: 'badge-skill-all' },
    all_levels: { text: 'Mọi trình độ', class: 'badge-skill-all' },
    beginner: { text: 'Mới chơi', class: 'badge-skill-beginner' },
    intermediate: { text: 'Trung bình', class: 'badge-skill-intermediate' },
    advanced: { text: 'Nâng cao', class: 'badge-skill-advanced' }
  };
  return map[level] || { text: level || 'Mọi trình độ', class: 'badge-skill-all' };
};

const getGenderBadge = (gender) => {
  const map = {
    all: 'Nam & Nữ',
    any: 'Nam & Nữ',
    male_only: 'Chỉ Nam',
    male: 'Chỉ Nam',
    female_only: 'Chỉ Nữ',
    female: 'Chỉ Nữ',
    mixed: 'Đôi Nam Nữ'
  };
  return map[gender] || 'Không giới hạn';
};

const getMatchTypeBadge = (type) => {
  const map = {
    single: 'Đánh đơn',
    singles: 'Đánh đơn',
    double: 'Đánh đôi',
    doubles: 'Đánh đôi',
    mixed: 'Đôi nam nữ',
    any: 'Tự do'
  };
  return map[type] || 'Giao lưu';
};
</script>

<template>
  <div class="court-container open-play-detail-page container py-3 py-md-4">
    <!-- Sleek Navigation Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <router-link
          to="/open-plays"
          class="btn-back-circle"
          title="Quay lại danh sách kèo"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
        </router-link>
        <ol class="breadcrumb court-breadcrumb mb-0">
          <li class="breadcrumb-item">
            <router-link to="/courts" class="text-decoration-none d-inline-flex align-items-center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <span>Sân Thể Thao</span>
            </router-link>
          </li>
          <li class="breadcrumb-item">
            <router-link to="/open-plays" class="text-decoration-none">Kèo Giao Lưu</router-link>
          </li>
          <li class="breadcrumb-item active text-truncate">
            <span class="match-code-chip">{{ match?.open_play_code || 'Chi tiết' }}</span>
          </li>
        </ol>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="openPlayStore.isLoading && !match" class="text-center py-5">
      <div class="spinner-border text-danger" role="status"></div>
      <p class="mt-2 text-muted small">Đang tải thông tin trận đấu...</p>
    </div>

    <div v-else-if="match" class="row g-3 g-lg-4">
      <!-- Left Column: Match Details & Participants -->
      <div class="col-lg-8">
        <!-- Main Card -->
        <div class="open-play-detail-card mb-3 mb-md-4">
          <!-- Top Tags & Host Actions Bar -->
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <span
                class="match-status-badge"
                :class="'status-' + match.status"
              >
                <svg width="7" height="7" viewBox="0 0 8 8" fill="currentColor" class="me-1">
                  <circle cx="4" cy="4" r="4"/>
                </svg>
                {{
                  match.status === 'open' ? 'ĐANG MỞ ĐĂNG KÝ' :
                  match.status === 'full' ? 'ĐÃ ĐỦ NGƯỜI (FULL)' :
                  match.status === 'ongoing' ? 'ĐANG DIỄN RA' :
                  match.status === 'completed' ? 'ĐÃ HOÀN THÀNH' : 'ĐÃ HỦY'
                }}
              </span>
              <span v-if="match.payment_mode === 'split_payment'" class="badge-tag-split">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                  <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                  <circle cx="12" cy="12" r="2"></circle>
                  <path d="M6 12h.01M18 12h.01"></path>
                </svg>
                Chia đều tiền sân
              </span>
              <span v-else class="badge-tag-free">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                  <polyline points="20 12 20 22 4 22 4 12"></polyline>
                  <rect x="2" y="7" width="20" height="5"></rect>
                  <line x1="12" y1="22" x2="12" y2="7"></line>
                  <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                  <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
                </svg>
                Host bao sân (Miễn phí)
              </span>
            </div>

            <!-- Host Actions Dropdown if Host -->
            <div v-if="isHost && !['completed', 'cancelled'].includes(match.status)" class="dropdown">
              <button class="btn-manage-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e63b6f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                  <circle cx="12" cy="12" r="3"></circle>
                  <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span>Quản lý trận</span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                <li v-if="match.status === 'open'">
                  <a class="dropdown-item py-2 small d-flex align-items-center" href="#" @click.prevent="handleCloseRegistration">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                      <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Đóng đăng ký sớm</span>
                  </a>
                </li>
                <li><hr class="dropdown-divider my-1" /></li>
                <li>
                  <a class="dropdown-item py-2 small text-danger d-flex align-items-center" href="#" @click.prevent="handleCancelMatch">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Hủy trận đấu</span>
                  </a>
                </li>
              </ul>
            </div>
          </div>

          <h3 class="fw-bold text-dark mb-3 match-detail-title">{{ match.title }}</h3>

          <!-- Unified 6-Item Sports Specs Dashboard with Pure SVG Vectors -->
          <div class="sports-specs-grid mb-3 mb-md-4">
            <!-- Box 1: Ngày thi đấu -->
            <div class="spec-tile">
              <div class="spec-icon-box bg-pink-subtle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e63b6f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </div>
              <div class="spec-content">
                <span class="spec-label">Ngày thi đấu</span>
                <span class="spec-val">{{ formatDateDisplay(match.booking?.booking_date) }}</span>
              </div>
            </div>

            <!-- Box 2: Thời gian -->
            <div class="spec-tile">
              <div class="spec-icon-box bg-pink-subtle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e63b6f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
              </div>
              <div class="spec-content">
                <span class="spec-label">Khung giờ</span>
                <span class="spec-val">{{ formatTime(match.booking?.start_time) }} - {{ formatTime(match.booking?.end_time) }}</span>
              </div>
            </div>

            <!-- Box 3: Sân thi đấu -->
            <div class="spec-tile">
              <div class="spec-icon-box bg-danger-subtle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
              </div>
              <div class="spec-content">
                <span class="spec-label">Sân thi đấu</span>
                <span class="spec-val text-truncate">{{ match.booking?.court?.court_name || 'Sân cầu lông' }}</span>
              </div>
            </div>

            <!-- Box 4: Trình độ (Golden Trophy SVG) -->
            <div class="spec-tile">
              <div class="spec-icon-box bg-warning-subtle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                  <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                  <path d="M4 22h16"></path>
                  <path d="M10 14.66V17c0 .55-.45 1-1 1H8v4h8v-4h-1c-.55 0-1-.45-1-1v-2.34"></path>
                  <path d="M18 4H6v7a6 6 0 0 0 12 0V4z"></path>
                </svg>
              </div>
              <div class="spec-content">
                <span class="spec-label">Trình độ</span>
                <span class="spec-val">{{ getSkillBadge(match.skill_level).text }}</span>
              </div>
            </div>

            <!-- Box 5: Giới tính (Users Gender SVG) -->
            <div class="spec-tile">
              <div class="spec-icon-box bg-info-subtle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </div>
              <div class="spec-content">
                <span class="spec-label">Giới tính</span>
                <span class="spec-val">{{ getGenderBadge(match.gender_rule) }}</span>
              </div>
            </div>

            <!-- Box 6: Thể thức (Match Gamepad SVG) -->
            <div class="spec-tile">
              <div class="spec-icon-box bg-primary-subtle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e63b6f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="6" width="20" height="12" rx="6"></rect>
                  <line x1="6" y1="12" x2="10" y2="12"></line>
                  <line x1="8" y1="10" x2="8" y2="14"></line>
                  <line x1="15" y1="13" x2="15.01" y2="13"></line>
                  <line x1="18" y1="11" x2="18.01" y2="11"></line>
                </svg>
              </div>
              <div class="spec-content">
                <span class="spec-label">Thể thức thi đấu</span>
                <span class="spec-val">{{ getMatchTypeBadge(match.match_type) }}</span>
              </div>
            </div>
          </div>

          <!-- Description & Rules -->
          <div class="mb-3 mb-md-4">
            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2 section-title-sm">
              <span class="section-icon-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                  <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
              </span>
              <span>Mô tả &amp; Luật chơi</span>
            </h6>
            <p class="text-secondary mb-3 match-desc-text" style="white-space: pre-line;">
              {{ match.description || 'Chưa có mô tả chi tiết từ Host.' }}
            </p>

            <div v-if="match.rules" class="p-3 bg-warning-subtle text-dark border border-warning-subtle rounded-3 small">
              <strong class="d-flex align-items-center mb-1 text-warning-emphasis">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                Quy định đặc biệt từ Host:
              </strong>
              {{ match.rules }}
            </div>
          </div>

          <!-- Host Profile Card -->
          <div class="host-profile-card">
            <div class="d-flex align-items-center gap-3">
              <div class="host-avatar-ring">
                <div class="host-avatar-capsule">
                  {{ (match.host?.full_name || 'H')[0].toUpperCase() }}
                </div>
                <div class="host-crown-badge">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="#f59e0b" stroke="#b45309" stroke-width="1.5">
                    <polygon points="2 4 5 20 19 20 22 4 15 10 12 3 9 10 2 4"></polygon>
                  </svg>
                </div>
              </div>
              <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="host-name">{{ match.host?.full_name || 'Host' }}</span>
                  <span class="host-tag">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="#d97706" stroke-width="1.5" class="me-1">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    Chủ Kèo
                  </span>
                </div>
                <div class="host-subtext">Người mở trận &amp; điều phối giao lưu</div>
              </div>
            </div>
            <div class="host-verified-pill">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <polyline points="9 12 11 14 15 10"></polyline>
              </svg>
              <span>Booking Đã Xác Nhận</span>
            </div>
          </div>
        </div>

        <!-- Participants List Card -->
        <div class="open-play-detail-card mb-3 mb-md-4">
          <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <span class="section-icon-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </span>
              <h6 class="fw-bold text-dark mb-0 section-title-sm">
                Danh Sách Người Chơi ({{ confirmedParticipants.length }} / {{ match.max_players }})
              </h6>
            </div>
            <span v-if="match.available_slots > 0" class="badge-capacity-open">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
              </svg>
              Còn {{ match.available_slots }} slot trống
            </span>
            <span v-else class="badge-capacity-full">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
              </svg>
              Đã đủ người
            </span>
          </div>

          <!-- Player Roster List -->
          <div class="player-roster-list">
            <!-- Confirmed Players -->
            <div
              v-for="(p, idx) in confirmedParticipants"
              :key="p.id"
              class="player-roster-item"
            >
              <div class="d-flex align-items-center gap-2.5">
                <span class="player-slot-index">#{{ idx + 1 }}</span>
                <div
                  class="player-avatar"
                  :class="p.role === 'host' ? 'player-avatar--host' : 'player-avatar--member'"
                >
                  {{ (p.guest_name || p.user?.full_name || 'P')[0].toUpperCase() }}
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="player-name">{{ p.guest_name || p.user?.full_name || 'Người chơi' }}</span>
                    <span v-if="p.role === 'host'" class="badge-host-pill">
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="#ffffff" stroke="none" class="me-1">
                        <polygon points="2 4 5 20 19 20 22 4 15 10 12 3 9 10 2 4"></polygon>
                      </svg>
                      Host
                    </span>
                    <span v-if="p.status === 'checked_in'" class="badge-checkin-pill">
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                      </svg>
                      Đã Check-in
                    </span>
                  </div>
                  <div class="player-time">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                      <circle cx="12" cy="12" r="10"></circle>
                      <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    Tham gia: {{ new Date(p.joined_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}
                  </div>
                </div>
              </div>

              <!-- Payment Status & Host Actions -->
              <div class="d-flex align-items-center gap-2">
                <span
                  v-if="match.payment_mode === 'split_payment'"
                  class="badge-payment-status"
                  :class="p.payment_status === 'paid' ? 'paid' : 'unpaid'"
                >
                  <svg v-if="p.payment_status === 'paid'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                  <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  {{ p.payment_status === 'paid' ? 'Đã đóng tiền' : 'Chưa đóng tiền' }}
                </span>
                <span v-else class="badge-payment-status free">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#e63b6f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                    <polyline points="20 12 20 22 4 22 4 12"></polyline>
                    <rect x="2" y="7" width="20" height="5"></rect>
                    <line x1="12" y1="22" x2="12" y2="7"></line>
                    <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                    <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
                  </svg>
                  Host bao sân
                </span>

                <!-- Host kick action -->
                <button
                  v-if="isHost && p.role !== 'host'"
                  class="btn-kick-player"
                  title="Loại khỏi trận"
                  @click="handleRemove(p.id)"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Empty Slots Placeholders -->
            <div
              v-for="emptyIdx in Math.max(0, match.max_players - confirmedParticipants.length)"
              :key="'empty-' + emptyIdx"
              class="player-roster-empty"
            >
              <div class="d-flex align-items-center gap-2.5">
                <span class="player-slot-index text-muted">#{{ confirmedParticipants.length + emptyIdx }}</span>
                <div class="empty-avatar-circle">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                  </svg>
                </div>
                <span class="empty-slot-text">Vị trí còn trống (Slot {{ confirmedParticipants.length + emptyIdx }})</span>
              </div>
              <span class="empty-slot-badge">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Đang mở nhận
              </span>
            </div>
          </div>

          <!-- Pending Requests (Host View Only) -->
          <div v-if="isHost && pendingParticipants.length > 0" class="mt-3 pt-3 border-top">
            <h6 class="fw-bold text-warning mb-3 d-flex align-items-center gap-2 section-title-sm">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
              <span>Yêu cầu chờ duyệt ({{ pendingParticipants.length }})</span>
            </h6>
            <div class="list-group list-group-flush">
              <div
                v-for="p in pendingParticipants"
                :key="p.id"
                class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between"
              >
                <div>
                  <div class="fw-bold text-dark small">{{ p.guest_name || p.user?.full_name }}</div>
                  <div class="small text-muted" style="font-size: 0.75rem;">{{ p.guest_phone || 'SĐT ẩn' }}</div>
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-success px-3 rounded-pill fw-bold d-inline-flex align-items-center" @click="handleApprove(p.id)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Duyệt
                  </button>
                  <button class="btn btn-sm btn-outline-danger px-3 rounded-pill d-inline-flex align-items-center" @click="handleReject(p.id)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                      <line x1="18" y1="6" x2="6" y2="18"></line>
                      <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Từ chối
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Waitlist Queue -->
        <div v-if="activeWaitlists.length > 0" class="open-play-detail-card">
          <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2 section-title-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 22h14"></path>
              <path d="M5 2h14"></path>
              <path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path>
              <path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path>
            </svg>
            <span>Danh Sách Chờ ({{ activeWaitlists.length }})</span>
          </h6>
          <p class="small text-muted mb-3">Khi có người chơi rời trận, hệ thống sẽ tự động đôn người chơi theo thứ tự hàng đợi.</p>
          <div class="list-group list-group-flush">
            <div
              v-for="w in activeWaitlists"
              :key="w.id"
              class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between"
            >
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary rounded-pill">#{{ w.position }}</span>
                <span class="fw-semibold text-dark small">{{ w.user?.full_name || 'Người chơi' }}</span>
              </div>
              <span class="small text-muted" style="font-size: 0.75rem;">Đang chờ slot trống</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Status Card & Action Bar -->
      <div class="col-lg-4">
        <div class="sticky-join-card sticky-top" style="top: 90px;">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="section-icon-pill">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                <path d="M4 22h16"></path>
                <path d="M10 14.66V17c0 .55-.45 1-1 1H8v4h8v-4h-1c-.55 0-1-.45-1-1v-2.34"></path>
                <path d="M18 4H6v7a6 6 0 0 0 12 0V4z"></path>
              </svg>
            </span>
            <h6 class="fw-bold text-dark mb-0 section-title-sm">Tham Gia Trận Đấu</h6>
          </div>

          <!-- Slot Capacity Progress Card -->
          <div class="capacity-progress-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="progress-title">Tiến độ đủ slot</span>
              <span class="progress-count">
                <strong>{{ confirmedParticipants.length }}</strong> / {{ match.max_players }}
              </span>
            </div>
            <div class="custom-progress-track">
              <div
                class="custom-progress-fill"
                :style="{ width: ((confirmedParticipants.length / match.max_players) * 100) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Slot Price Summary Box -->
          <div class="price-summary-box mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-secondary small fw-semibold" style="font-size: 0.78rem;">Hình thức:</span>
              <span class="fw-bold text-dark small">
                {{ match.payment_mode === 'split_payment' ? 'Chia đều tiền sân' : 'Host bao sân' }}
              </span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-secondary small fw-semibold" style="font-size: 0.78rem;">Giá mỗi slot:</span>
              <span
                class="fw-bold fs-5"
                :class="match.payment_mode === 'split_payment' ? 'text-ocean-primary' : 'text-success'"
              >
                {{ match.payment_mode === 'split_payment' ? formatCurrency(match.slot_price) : 'Miễn phí' }}
              </span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-secondary small fw-semibold" style="font-size: 0.78rem;">Chế độ duyệt:</span>
              <span class="badge bg-light text-dark border">
                {{ match.join_mode === 'auto' ? 'Vào ngay (Auto)' : 'Cần Host duyệt' }}
              </span>
            </div>
          </div>

          <!-- User State: Participating -->
          <div v-if="myParticipation" class="alert alert-success border-0 rounded-4 p-3 mb-3">
            <div class="d-flex align-items-center gap-2 fw-bold text-success mb-2 small">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
              <span>{{ myParticipation.status === 'confirmed' ? 'Bạn đã tham gia trận!' : 'Yêu cầu của bạn đang chờ duyệt' }}</span>
            </div>

            <!-- QR Button -->
            <button
              v-if="myParticipation.status === 'confirmed'"
              class="btn btn-outline-dark btn-sm w-100 fw-bold rounded-pill mb-2 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2"
              @click="showQrCode"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
              </svg>
              <span>Mã QR Check-in</span>
            </button>

            <!-- Payment Button if unpaid -->
            <button
              v-if="match.payment_mode === 'split_payment' && myParticipation.payment_status === 'unpaid'"
              class="btn btn-warning btn-sm w-100 fw-bold rounded-pill mb-2 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2"
              @click="openPaymentModal"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="22" y2="10"></line>
              </svg>
              <span>Thanh toán phần tiền ({{ formatCurrency(match.slot_price) }})</span>
            </button>

            <!-- Leave Button -->
            <button
              v-if="myParticipation.role !== 'host'"
              class="btn btn-outline-danger btn-sm w-100 fw-bold rounded-pill py-2 d-flex align-items-center justify-content-center gap-2"
              @click="handleLeaveMatch"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
              <span>Rời trận</span>
            </button>
          </div>

          <!-- User State: In Waitlist -->
          <div v-else-if="myWaitlist" class="alert alert-warning border-0 rounded-4 p-3 mb-3">
            <div class="d-flex align-items-center gap-2 fw-bold text-warning mb-2 small">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 22h14"></path>
                <path d="M5 2h14"></path>
                <path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path>
                <path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path>
              </svg>
              <span>Bạn đang ở danh sách chờ (#{{ myWaitlist.position }})</span>
            </div>
            <p class="small text-muted mb-2" style="font-size: 0.78rem;">Bạn sẽ nhận được thông báo ngay khi có slot trống!</p>
            <button class="btn btn-outline-danger btn-sm w-100 rounded-pill" @click="handleLeaveWaitlist">
              Hủy khỏi danh sách chờ
            </button>
          </div>

          <!-- Action Buttons for New Players -->
          <div v-else>
            <!-- Available Slots -> Join -->
            <button
              v-if="match.available_slots > 0 && match.status === 'open'"
              class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm mb-2 d-flex align-items-center justify-content-center gap-2"
              @click="handleJoinClick"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
              </svg>
              <span>Tham Gia Trận Này</span>
            </button>

            <!-- Full -> Join Waitlist -->
            <button
              v-else-if="match.status === 'full'"
              class="btn btn-warning w-100 py-2.5 fw-bold rounded-pill shadow-sm mb-2 d-flex align-items-center justify-content-center gap-2"
              @click="handleJoinWaitlist"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 22h14"></path>
                <path d="M5 2h14"></path>
                <path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path>
                <path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path>
              </svg>
              <span>Tham Gia Danh Sách Chờ</span>
            </button>

            <!-- Closed / Cancelled -->
            <button v-else class="btn btn-secondary w-100 py-2.5 fw-bold rounded-pill disabled" disabled>
              Trận đấu đã kết thúc / đã hủy
            </button>
          </div>

          <div class="text-center mt-3">
            <span class="small text-muted d-inline-flex align-items-center" style="font-size: 0.75rem;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              </svg>
              Hệ thống bảo mật &amp; chia tiền sân minh bạch
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- QR Code Check-in Modal -->
    <div v-if="showQrModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.6);" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 p-4 text-center">
          <h5 class="fw-bold text-dark mb-1">Mã QR Check-in</h5>
          <p class="small text-muted mb-3">Đưa mã này cho Host hoặc Lễ tân để xác nhận vào sân</p>
          <div class="d-flex justify-content-center mb-3">
            <img :src="qrDataUrl" alt="QR Code" class="img-fluid border rounded-3 p-2" />
          </div>
          <p class="small font-monospace text-secondary bg-light p-2 rounded mb-3">{{ currentQrCode }}</p>
          <button class="btn btn-primary rounded-pill fw-bold w-100 py-2" @click="showQrModal = false">
            Đóng
          </button>
        </div>
      </div>
    </div>

    <!-- Split Payment Modal -->
    <div v-if="showPayModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.6);" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Thanh toán phần tiền sân</h5>
            <button class="btn-close" @click="showPayModal = false"></button>
          </div>

          <div class="p-3 bg-light rounded-4 mb-3">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-secondary small">Trận đấu:</span>
              <span class="fw-bold text-dark">{{ match.title }}</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-secondary small">Số tiền cần đóng:</span>
              <span class="fw-bold fs-5 text-ocean-primary">{{ formatCurrency(match.slot_price) }}</span>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-semibold text-secondary">Phương thức thanh toán</label>
            <div class="d-flex flex-column gap-2">
              <label class="form-check p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer">
                <div>
                  <input class="form-check-input me-2" type="radio" v-model="selectedPaymentMethod" value="wallet" />
                  <strong>Ví Ocean Sport</strong>
                </div>
                <span class="badge bg-light text-dark border">Khuyên dùng</span>
              </label>
              <label class="form-check p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer">
                <div>
                  <input class="form-check-input me-2" type="radio" v-model="selectedPaymentMethod" value="sepay" />
                  <strong>Chuyển khoản QR (SePay)</strong>
                </div>
                <i class="bi bi-qr-code text-primary"></i>
              </label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-light w-50 rounded-pill py-2" @click="showPayModal = false">Hủy</button>
            <button class="btn btn-primary w-50 rounded-pill py-2 fw-bold" :disabled="isPaying" @click="submitPayment">
              <span v-if="isPaying" class="spinner-border spinner-border-sm me-1"></span>
              Thanh toán ngay
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Guest Join OTP Modal -->
    <div v-if="showGuestModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.6);" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Xác thực SĐT tham gia</h5>
            <button class="btn-close" @click="showGuestModal = false"></button>
          </div>

          <p class="small text-muted mb-3">Bạn chưa đăng nhập. Nhập họ tên và số điện thoại để nhận mã OTP tham gia trận.</p>

          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Họ và tên của bạn</label>
            <input type="text" class="form-control" v-model="guestName" placeholder="Nguyễn Văn A" :disabled="otpSent" />
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Số điện thoại</label>
            <div class="input-group">
              <input type="tel" class="form-control" v-model="guestPhone" placeholder="0901234567" :disabled="otpSent" />
              <button class="btn btn-outline-primary" :disabled="isSendingOtp || otpSent" @click="handleSendGuestOtp">
                <span v-if="isSendingOtp" class="spinner-border spinner-border-sm me-1"></span>
                {{ otpSent ? 'Đã gửi OTP' : 'Gửi mã OTP' }}
              </button>
            </div>
          </div>

          <div v-if="otpSent" class="mb-4">
            <label class="form-label small fw-semibold text-secondary">Mã OTP 6 số</label>
            <input type="text" class="form-control text-center fs-4 fw-bold letter-spacing-2" v-model="guestOtp" maxlength="6" placeholder="------" />
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-light w-50 rounded-pill py-2" @click="showGuestModal = false">Đóng</button>
            <button class="btn btn-primary w-50 rounded-pill py-2 fw-bold" :disabled="!otpSent || isVerifyingOtp" @click="handleVerifyGuestOtp">
              <span v-if="isVerifyingOtp" class="spinner-border spinner-border-sm me-1"></span>
              Xác nhận &amp; Tiếp tục
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
.letter-spacing-2 {
  letter-spacing: 4px;
}

/* Back Button Circle */
.btn-back-circle {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  font-size: 0.9rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: all 0.2s ease;
}
.btn-back-circle:hover {
  color: #e63b6f;
  border-color: #fbcfe8;
  background: #fff0f5;
  transform: translateX(-2px);
}

/* Breadcrumb */
.court-breadcrumb .breadcrumb-item a {
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 600;
  transition: color 0.15s ease;
}
.court-breadcrumb .breadcrumb-item a:hover {
  color: #e63b6f;
}

.match-code-chip {
  background: #f1f5f9;
  color: #334155;
  font-size: 0.76rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

/* Main Detail Card */
.open-play-detail-card {
  background: #ffffff;
  border-radius: 18px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  padding: 22px 24px;
}

.match-detail-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.3px;
  line-height: 1.3;
}

.section-title-sm {
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
}

.section-icon-pill {
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: #fff0f5;
  color: #e63b6f;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

.match-desc-text {
  font-size: 0.85rem;
  line-height: 1.6;
  color: #475569;
}

/* Top Tags */
.match-status-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 9999px;
  letter-spacing: 0.5px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.match-status-badge.status-open {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.match-status-badge.status-full {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.match-status-badge.status-ongoing {
  background: rgba(230, 59, 111, 0.12);
  color: #e63b6f;
  border: 1px solid rgba(230, 59, 111, 0.3);
}

.match-status-badge.status-completed {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.match-status-badge.status-cancelled {
  background: #1e293b;
  color: #ffffff;
}

.badge-tag-split {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.25);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

.badge-tag-free {
  background: rgba(230, 59, 111, 0.1);
  color: #e63b6f;
  border: 1px solid rgba(230, 59, 111, 0.25);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

.btn-manage-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 12px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #475569;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 9999px;
  transition: all 0.2s ease;
}
.btn-manage-pill:hover {
  background: #ffffff;
  color: #e63b6f;
  border-color: #fbcfe8;
}

/* Unified 6-Item Sports Specs Dashboard */
.sports-specs-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

@media (max-width: 768px) {
  .sports-specs-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .sports-specs-grid {
    grid-template-columns: 1fr;
  }
}

.spec-tile {
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 14px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: all 0.15s ease;
}

.spec-tile:hover {
  background: #ffffff;
  border-color: #fce7f3;
  box-shadow: 0 2px 8px rgba(230, 59, 111, 0.05);
}

.spec-icon-box {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.bg-pink-subtle {
  background: #fff0f5;
}
.bg-danger-subtle {
  background: #fef2f2;
}
.bg-warning-subtle {
  background: #fffbeb;
}
.bg-info-subtle {
  background: #f0fdfa;
}
.bg-primary-subtle {
  background: #fdf2f8;
}

.spec-content {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.spec-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #64748b;
  line-height: 1.2;
}

.spec-val {
  font-size: 0.84rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Host Profile Card */
.host-profile-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  background: linear-gradient(135deg, #ffffff 0%, #fffbfe 100%);
  border: 1.5px solid #fce7f3;
  border-radius: 14px;
  box-shadow: 0 2px 10px rgba(230, 59, 111, 0.04);
}

.host-avatar-ring {
  position: relative;
  display: inline-block;
}

.host-avatar-capsule {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: linear-gradient(135deg, #e63b6f 0%, #ff6b8b 100%);
  color: #ffffff;
  font-weight: 800;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(230, 59, 111, 0.25);
}

.host-crown-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  width: 18px;
  height: 18px;
  background: #ffffff;
  border: 1px solid #fef3c7;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.host-name {
  font-size: 0.9rem;
  font-weight: 800;
  color: #0f172a;
}

.host-tag {
  background: #fff0f5;
  color: #e63b6f;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid #fbcfe8;
  display: inline-flex;
  align-items: center;
}

.host-subtext {
  font-size: 0.72rem;
  color: #64748b;
}

.host-verified-pill {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.25);
  font-size: 0.74rem;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

/* Player Roster List */
.player-roster-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.player-roster-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 12px;
  transition: all 0.15s ease;
}
.player-roster-item:hover {
  background: #ffffff;
  border-color: #e2e8f0;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.player-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.9rem;
  flex-shrink: 0;
}
.player-avatar--host {
  background: linear-gradient(135deg, #e63b6f 0%, #ff6b8b 100%);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(230, 59, 111, 0.22);
}
.player-avatar--member {
  background: #ffffff;
  color: #475569;
  border: 1.5px solid #e2e8f0;
}

.player-name {
  font-size: 0.86rem;
  font-weight: 700;
  color: #0f172a;
}

.player-time {
  font-size: 0.72rem;
  color: #94a3b8;
  display: flex;
  align-items: center;
}

.badge-payment-status {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}
.badge-payment-status.paid {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.25);
}
.badge-payment-status.unpaid {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
  border: 1px solid rgba(245, 158, 11, 0.25);
}
.badge-payment-status.free {
  background: #fff0f5;
  color: #e63b6f;
  border: 1px solid #fbcfe8;
}

.btn-kick-player {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 1px solid #fee2e2;
  background: #fff5f5;
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-kick-player:hover {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

/* Empty Slots Placeholder */
.player-roster-empty {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 14px;
  background: #ffffff;
  border: 1.5px dashed #cbd5e1;
  border-radius: 12px;
  opacity: 0.85;
  transition: all 0.15s ease;
}
.player-roster-empty:hover {
  border-color: #fbcfe8;
  background: #fffbfe;
}

.empty-avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
}

.empty-slot-text {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 600;
}

.empty-slot-badge {
  font-size: 0.68rem;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid rgba(16, 185, 129, 0.2);
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
}

/* Capacity Badges */
.badge-capacity-open {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.25);
  font-size: 0.74rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

.badge-capacity-full {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.25);
  font-size: 0.74rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

.badge-host-pill {
  background: linear-gradient(135deg, #e63b6f 0%, #ff6b8b 100%);
  color: #ffffff;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

.badge-checkin-pill {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.25);
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

.player-slot-index {
  font-size: 0.76rem;
  font-weight: 800;
  color: #e63b6f;
  min-width: 22px;
  text-align: center;
}

/* Slot Capacity Progress Card */
.capacity-progress-card {
  background: #fff8fa;
  border: 1px solid #fce7f3;
  border-radius: 14px;
  padding: 10px 14px;
}

.progress-title {
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.progress-count {
  font-size: 0.82rem;
  font-weight: 700;
  color: #0f172a;
}
.progress-count strong {
  color: #e63b6f;
}

.custom-progress-track {
  height: 8px;
  background: #e2e8f0;
  border-radius: 9999px;
  overflow: hidden;
  margin-top: 6px;
}

.custom-progress-fill {
  height: 100%;
  background: linear-gradient(135deg, #e63b6f 0%, #ff6b8b 100%);
  border-radius: 9999px;
  transition: width 0.3s ease;
}

/* Right Sticky Join Card */
.sticky-join-card {
  background: #ffffff;
  border: 1px solid #f1f5f9;
  border-top: 4px solid #e63b6f;
  border-radius: 18px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.price-summary-box {
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 14px;
  padding: 14px 16px;
  margin-bottom: 16px;
}
</style>
