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
</script>

<template>
  <div class="container py-4">
    <!-- Breadcrumb -->
    <nav class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><router-link to="/courts" class="text-decoration-none">Sân thể thao</router-link></li>
        <li class="breadcrumb-item"><router-link to="/open-plays" class="text-decoration-none">Kèo giao lưu</router-link></li>
        <li class="breadcrumb-item active">{{ match?.open_play_code || 'Chi tiết' }}</li>
      </ol>
    </nav>

    <!-- Loading State -->
    <div v-if="openPlayStore.isLoading && !match" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">Đang tải thông tin trận đấu...</p>
    </div>

    <div v-else-if="match" class="row g-4">
      <!-- Left Column: Match Details & Participants -->
      <div class="col-lg-8">
        <!-- Main Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <span class="badge bg-dark rounded-pill px-3 py-2 fw-semibold">
                Mã: {{ match.open_play_code }}
              </span>
              <span
                class="badge rounded-pill px-3 py-2 fw-semibold"
                :class="{
                  'bg-success': match.status === 'open',
                  'bg-danger': match.status === 'full',
                  'bg-primary': match.status === 'ongoing',
                  'bg-secondary': match.status === 'completed',
                  'bg-dark': match.status === 'cancelled',
                }"
              >
                {{
                  match.status === 'open' ? 'ĐANG MỞ ĐĂNG KÝ' :
                  match.status === 'full' ? 'ĐÃ ĐỦ NGƯỜI (FULL)' :
                  match.status === 'ongoing' ? 'ĐANG DIỄN RA' :
                  match.status === 'completed' ? 'ĐÃ HOÀN THÀNH' : 'ĐÃ HỦY'
                }}
              </span>
              <span v-if="match.payment_mode === 'split_payment'" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                <i class="bi bi-cash-coin me-1"></i>Chia tiền sân
              </span>
              <span v-else class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2">
                <i class="bi bi-gift me-1"></i>Host bao sân
              </span>
            </div>

            <!-- Host Actions Dropdown if Host -->
            <div v-if="isHost && !['completed', 'cancelled'].includes(match.status)" class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-gear-fill me-1"></i> Quản lý trận
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                <li v-if="match.status === 'open'">
                  <a class="dropdown-item" href="#" @click.prevent="handleCloseRegistration">
                    <i class="bi bi-lock-fill me-2 text-warning"></i>Đóng đăng ký sớm
                  </a>
                </li>
                <li><hr class="dropdown-divider" /></li>
                <li>
                  <a class="dropdown-item text-danger" href="#" @click.prevent="handleCancelMatch">
                    <i class="bi bi-trash-fill me-2"></i>Hủy trận đấu
                  </a>
                </li>
              </ul>
            </div>
          </div>

          <h2 class="fw-bold text-dark mb-3">{{ match.title }}</h2>

          <!-- Key Info Grid -->
          <div class="row g-3 p-3 bg-light rounded-4 mb-4">
            <div class="col-sm-6 col-md-3">
              <div class="small text-muted mb-1">Ngày thi đấu</div>
              <div class="fw-bold text-dark"><i class="bi bi-calendar-event text-primary me-1"></i>{{ formatDateDisplay(match.booking?.booking_date) }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="small text-muted mb-1">Thời gian</div>
              <div class="fw-bold text-dark"><i class="bi bi-clock-history text-primary me-1"></i>{{ formatTime(match.booking?.start_time) }} - {{ formatTime(match.booking?.end_time) }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="small text-muted mb-1">Sân thi đấu</div>
              <div class="fw-bold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ match.booking?.court?.court_name }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="small text-muted mb-1">Trình độ</div>
              <div class="fw-bold text-primary"><i class="bi bi-award-fill me-1"></i>{{ match.skill_level }}</div>
            </div>
          </div>

          <!-- Description & Rules -->
          <div class="mb-4">
            <h5 class="fw-bold text-dark mb-2">Mô tả & Luật chơi</h5>
            <p class="text-secondary mb-3" style="white-space: pre-line;">{{ match.description || 'Chưa có mô tả chi tiết từ Host.' }}</p>
            <div v-if="match.rules" class="p-3 bg-warning-subtle text-dark border border-warning-subtle rounded-3 small">
              <strong class="d-block mb-1"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Quy định đặc biệt từ Host:</strong>
              {{ match.rules }}
            </div>
          </div>

          <!-- Host Banner -->
          <div class="d-flex align-items-center justify-content-between p-3 border rounded-4 bg-white">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                {{ (match.host?.full_name || 'H')[0].toUpperCase() }}
              </div>
              <div>
                <div class="small text-muted">Người tổ chức (Host)</div>
                <div class="fw-bold fs-6 text-dark">{{ match.host?.full_name }}</div>
              </div>
            </div>
            <span class="badge bg-success-subtle text-success border px-3 py-2">
              <i class="bi bi-check-circle-fill me-1"></i>Sân đã xác nhận
            </span>
          </div>
        </div>

        <!-- Participants List -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="fw-bold text-dark mb-0">
              <i class="bi bi-people-fill text-primary me-2"></i>
              Danh Sách Người Chơi ({{ confirmedParticipants.length }} / {{ match.max_players }})
            </h5>
            <span v-if="match.available_slots > 0" class="badge bg-success text-white">
              Còn {{ match.available_slots }} chỗ
            </span>
            <span v-else class="badge bg-danger text-white">
              Đã đủ người
            </span>
          </div>

          <div class="list-group list-group-flush">
            <div
              v-for="p in confirmedParticipants"
              :key="p.id"
              class="list-group-item px-0 py-3 d-flex align-items-center justify-content-between"
            >
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-secondary-subtle text-dark d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                  {{ (p.guest_name || p.user?.full_name || 'P')[0].toUpperCase() }}
                </div>
                <div>
                  <div class="fw-bold text-dark d-flex align-items-center gap-2">
                    <span>{{ p.guest_name || p.user?.full_name || 'Người chơi' }}</span>
                    <span v-if="p.role === 'host'" class="badge bg-primary small">Host</span>
                    <span v-if="p.status === 'checked_in'" class="badge bg-success small"><i class="bi bi-qr-code-scan me-1"></i>Đã Check-in</span>
                  </div>
                  <div class="small text-muted">
                    Tham gia: {{ new Date(p.joined_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}
                  </div>
                </div>
              </div>

              <!-- Payment Status & Host Actions -->
              <div class="d-flex align-items-center gap-2">
                <span
                  v-if="match.payment_mode === 'split_payment'"
                  class="badge rounded-pill px-3 py-1"
                  :class="p.payment_status === 'paid' ? 'bg-success text-white' : 'bg-warning text-dark'"
                >
                  {{ p.payment_status === 'paid' ? 'Đã đóng tiền' : 'Chưa đóng tiền' }}
                </span>
                <span v-else class="badge bg-light text-muted border">Miễn phí</span>

                <!-- Host kick action -->
                <button
                  v-if="isHost && p.role !== 'host'"
                  class="btn btn-sm btn-outline-danger rounded-circle p-1"
                  style="width: 28px; height: 28px;"
                  title="Loại khỏi trận"
                  @click="handleRemove(p.id)"
                >
                  <i class="bi bi-x"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Pending Requests (Host View Only) -->
          <div v-if="isHost && pendingParticipants.length > 0" class="mt-4 pt-3 border-top">
            <h6 class="fw-bold text-warning mb-3">
              <i class="bi bi-clock-fill me-1"></i>Yêu cầu chờ duyệt ({{ pendingParticipants.length }})
            </h6>
            <div class="list-group list-group-flush">
              <div
                v-for="p in pendingParticipants"
                :key="p.id"
                class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between"
              >
                <div>
                  <div class="fw-bold text-dark">{{ p.guest_name || p.user?.full_name }}</div>
                  <div class="small text-muted">{{ p.guest_phone || 'SĐT ẩn' }}</div>
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-success px-3 rounded-pill fw-bold" @click="handleApprove(p.id)">
                    <i class="bi bi-check me-1"></i>Duyệt
                  </button>
                  <button class="btn btn-sm btn-outline-danger px-3 rounded-pill" @click="handleReject(p.id)">
                    <i class="bi bi-x me-1"></i>Từ chối
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Waitlist Queue -->
        <div v-if="activeWaitlists.length > 0" class="card border-0 shadow-sm rounded-4 p-4">
          <h5 class="fw-bold text-dark mb-3">
            <i class="bi bi-hourglass-split text-warning me-2"></i>
            Danh Sách Chờ ({{ activeWaitlists.length }})
          </h5>
          <p class="small text-muted">Khi có người chơi rời trận, hệ thống sẽ tự động đôn người chơi theo thứ tự hàng đợi.</p>
          <div class="list-group list-group-flush">
            <div
              v-for="w in activeWaitlists"
              :key="w.id"
              class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between"
            >
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary rounded-pill">#{{ w.position }}</span>
                <span class="fw-semibold text-dark">{{ w.user?.full_name || 'Người chơi' }}</span>
              </div>
              <span class="small text-muted">Đang chờ</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Status Card & Action Bar -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 90px;">
          <h5 class="fw-bold text-dark mb-3">Tham Gia Trận Đấu</h5>

          <!-- Slot price calculation -->
          <div class="p-3 bg-light rounded-4 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-secondary small">Hình thức thanh toán:</span>
              <span class="fw-bold text-dark">{{ match.payment_mode === 'split_payment' ? 'Chia đều tiền sân' : 'Host bao sân' }}</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-secondary small">Giá mỗi người chơi:</span>
              <span class="fw-bold fs-5 text-primary">
                {{ match.payment_mode === 'split_payment' ? formatCurrency(match.slot_price) : 'Miễn phí' }}
              </span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-secondary small">Chế độ tham gia:</span>
              <span class="badge bg-secondary text-white">{{ match.join_mode === 'auto' ? 'Vào ngay (Auto)' : 'Cần Host duyệt' }}</span>
            </div>
          </div>

          <!-- User State: Participating -->
          <div v-if="myParticipation" class="alert alert-success border-0 rounded-4 p-3 mb-3">
            <div class="d-flex align-items-center gap-2 fw-bold text-success mb-2">
              <i class="bi bi-check-circle-fill"></i>
              <span>{{ myParticipation.status === 'confirmed' ? 'Bạn đã tham gia trận!' : 'Yêu cầu của bạn đang chờ duyệt' }}</span>
            </div>

            <!-- QR Button -->
            <button
              v-if="myParticipation.status === 'confirmed'"
              class="btn btn-outline-dark btn-sm w-100 fw-bold rounded-pill mb-2 py-2"
              @click="showQrCode"
            >
              <i class="bi bi-qr-code me-2"></i>Mã QR Check-in
            </button>

            <!-- Payment Button if unpaid -->
            <button
              v-if="match.payment_mode === 'split_payment' && myParticipation.payment_status === 'unpaid'"
              class="btn btn-warning btn-sm w-100 fw-bold rounded-pill mb-2 py-2"
              @click="openPaymentModal"
            >
              <i class="bi bi-credit-card me-2"></i>Thanh toán phần tiền ({{ formatCurrency(match.slot_price) }})
            </button>

            <!-- Leave Button -->
            <button
              v-if="myParticipation.role !== 'host'"
              class="btn btn-outline-danger btn-sm w-100 fw-bold rounded-pill py-2"
              @click="handleLeaveMatch"
            >
              <i class="bi bi-box-arrow-left me-2"></i>Rời trận
            </button>
          </div>

          <!-- User State: In Waitlist -->
          <div v-else-if="myWaitlist" class="alert alert-warning border-0 rounded-4 p-3 mb-3">
            <div class="d-flex align-items-center gap-2 fw-bold text-warning mb-2">
              <i class="bi bi-hourglass-split"></i>
              <span>Bạn đang ở danh sách chờ (#{{ myWaitlist.position }})</span>
            </div>
            <p class="small text-muted mb-2">Bạn sẽ nhận được thông báo ngay khi có slot trống!</p>
            <button class="btn btn-outline-danger btn-sm w-100 rounded-pill" @click="handleLeaveWaitlist">
              Hủy khỏi danh sách chờ
            </button>
          </div>

          <!-- Action Buttons for New Players -->
          <div v-else>
            <!-- Available Slots -> Join -->
            <button
              v-if="match.available_slots > 0 && match.status === 'open'"
              class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm mb-2"
              @click="handleJoinClick"
            >
              <i class="bi bi-plus-circle me-2"></i>Tham Gia Trận Này
            </button>

            <!-- Full -> Join Waitlist -->
            <button
              v-else-if="match.status === 'full'"
              class="btn btn-warning w-100 py-3 fw-bold rounded-pill shadow-sm mb-2"
              @click="handleJoinWaitlist"
            >
              <i class="bi bi-hourglass-split me-2"></i>Tham Gia Danh Sách Chờ
            </button>

            <!-- Closed / Cancelled -->
            <button v-else class="btn btn-secondary w-100 py-3 fw-bold rounded-pill disabled" disabled>
              Trận đấu đã kết thúc / đã hủy
            </button>
          </div>

          <div class="text-center mt-3">
            <span class="small text-muted">
              <i class="bi bi-shield-lock me-1"></i>Hệ thống bảo mật & chia tiền sân minh bạch
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
          <div class="small text-muted mb-3">Mã: <code>{{ match?.open_play_code }}</code></div>
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
            <h5 class="fw-bold text-dark mb-0">Thanh Toán Phần Tiền Sân</h5>
            <button class="btn-close" @click="showPayModal = false"></button>
          </div>

          <div class="p-3 bg-light rounded-4 mb-3 text-center">
            <div class="small text-muted">Số tiền cần thanh toán:</div>
            <div class="fs-4 fw-bold text-primary">{{ formatCurrency(match?.slot_price) }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold small text-secondary">Phương thức thanh toán</label>
            <div class="list-group">
              <label class="list-group-item d-flex align-items-center gap-3 cursor-pointer">
                <input class="form-check-input" type="radio" value="wallet" v-model="selectedPaymentMethod" />
                <div>
                  <div class="fw-bold text-dark"><i class="bi bi-wallet2 text-primary me-2"></i>Ví cá nhân Ocean Sport</div>
                  <div class="small text-muted">Thanh toán trừ trực tiếp từ số dư ví của bạn</div>
                </div>
              </label>
              <label class="list-group-item d-flex align-items-center gap-3 cursor-pointer">
                <input class="form-check-input" type="radio" value="cash" v-model="selectedPaymentMethod" />
                <div>
                  <div class="fw-bold text-dark"><i class="bi bi-cash me-2 text-success"></i>Tiền mặt tại sân</div>
                  <div class="small text-muted">Đóng tiền mặt trực tiếp cho Host khi tới sân</div>
                </div>
              </label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-light w-50 rounded-pill py-2" @click="showPayModal = false">Hủy</button>
            <button class="btn btn-primary w-50 rounded-pill py-2 fw-bold" :disabled="isPaying" @click="submitPayment">
              <span v-if="isPaying" class="spinner-border spinner-border-sm me-1"></span>
              Xác nhận thanh toán
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Guest OTP Verification Modal -->
    <div v-if="showGuestModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.6);" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Xác Thực Tham Gia Trận</h5>
            <button class="btn-close" @click="showGuestModal = false"></button>
          </div>

          <p class="small text-muted mb-3">
            Bạn chưa đăng nhập. Vui lòng nhập số điện thoại để nhận mã xác thực OTP tham gia trận giao lưu.
          </p>

          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Họ và tên của bạn</label>
            <input type="text" class="form-control" v-model="guestName" placeholder="VD: Nguyễn Văn A" />
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
              Xác nhận & Tiếp tục
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
</style>
