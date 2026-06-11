<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../axios';
import Swal from 'sweetalert2';

const loading = ref(true);
const search = ref('');
const staffList = ref([]);
const stats = ref({ total: 0, registered_count: 0, not_registered: 0 });
const filter = ref('all'); // 'all' | 'registered' | 'not_registered'
const expandedUser = ref(null);
const BASE_URL = import.meta.env.VITE_BASE_URL || 'http://localhost:8383';

// --- FETCH DATA ---
const fetchData = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/face/management', { params: { search: search.value } });
    if (res.data.status === 'success') {
      staffList.value = res.data.data.staff;
      stats.value = {
        total: res.data.data.total,
        registered_count: res.data.data.registered_count,
        not_registered: res.data.data.not_registered,
      };
    }
  } catch {
    staffList.value = [];
  } finally {
    loading.value = false;
  }
};

// --- FILTERED LIST ---
const filteredList = computed(() => {
  if (filter.value === 'registered') return staffList.value.filter(s => s.face_registered);
  if (filter.value === 'not_registered') return staffList.value.filter(s => !s.face_registered);
  return staffList.value;
});

// --- SEARCH (debounced) ---
let searchTimer = null;
const onSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchData(), 400);
};

// --- RESET USER FACE ---
const handleResetUser = async (staff) => {
  const result = await Swal.fire({
    title: 'Xác nhận xóa',
    html: `Xóa toàn bộ <b>${staff.face_count}</b> ảnh đăng ký khuôn mặt của <b>${staff.full_name}</b>?<br><small class="text-muted">Nhân viên sẽ cần đăng ký lại.</small>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    confirmButtonText: 'Xóa',
    cancelButtonText: 'Hủy',
  });

  if (!result.isConfirmed) return;

  try {
    const res = await api.post(`/admin/face/reset-user/${staff.admin_id}`);
    if (res.data.status === 'success') {
      Swal.fire({ icon: 'success', title: 'Thành công', text: res.data.message, timer: 2000, showConfirmButton: false });
      fetchData();
    }
  } catch (error) {
    Swal.fire({ icon: 'error', title: 'Lỗi', text: error.response?.data?.message || 'Có lỗi xảy ra.' });
  }
};

// --- TOGGLE EXPAND ---
const toggleExpand = (adminId) => {
  expandedUser.value = expandedUser.value === adminId ? null : adminId;
};

// --- AVATAR ---
const getAvatar = (staff) => {
  if (!staff.avatar_url) return null;
  return staff.avatar_url.startsWith('http') ? staff.avatar_url : `${BASE_URL}${staff.avatar_url}`;
};

const getInitial = (name) => (name?.[0] || '?').toUpperCase();

const getRoleBadge = (role) => {
  const map = { admin: { label: 'Admin', class: 'role-admin' }, seller: { label: 'Seller', class: 'role-seller' }, staff: { label: 'Staff', class: 'role-staff' } };
  return map[role] || { label: role, class: 'role-default' };
};

const getFaceImage = (path) => {
  if (!path) return null;
  return path.startsWith('http') ? path : `${BASE_URL}${path}`;
};

onMounted(() => fetchData());
</script>

<template>
  <div class="face-mgmt p-4">
    <!-- Header -->
    <div class="fm-header mb-4">
      <div>
        <h2 class="h4 mb-1 fw-bold text-gray-800">
          <span class="text-primary me-1">#</span> Quản Lý Khuôn Mặt
        </h2>
        <p class="text-muted small mb-0">Xem và quản lý đăng ký khuôn mặt của tất cả nhân viên</p>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="stat-card stat-total" @click="filter = 'all'" :class="{ 'stat-active': filter === 'all' }">
          <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
          <div class="stat-info">
            <div class="stat-number">{{ stats.total }}</div>
            <div class="stat-label">Tổng nhân viên</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card stat-registered" @click="filter = 'registered'" :class="{ 'stat-active': filter === 'registered' }">
          <div class="stat-icon"><i class="bi bi-shield-fill-check"></i></div>
          <div class="stat-info">
            <div class="stat-number">{{ stats.registered_count }}</div>
            <div class="stat-label">Đã đăng ký</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card stat-pending" @click="filter = 'not_registered'" :class="{ 'stat-active': filter === 'not_registered' }">
          <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div class="stat-info">
            <div class="stat-number">{{ stats.not_registered }}</div>
            <div class="stat-label">Chưa đăng ký</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-3">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input v-model="search" @input="onSearch" type="text" class="form-control border-start-0"
            placeholder="Tìm kiếm theo tên hoặc email..." id="input-search-face">
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="text-muted mt-2">Đang tải dữ liệu...</p>
    </div>

    <!-- Staff List -->
    <div v-else-if="filteredList.length > 0" class="staff-list">
      <div v-for="staff in filteredList" :key="staff.admin_id" class="staff-card card shadow-sm border-0 mb-3">
        <div class="card-body p-3">
          <div class="staff-row" @click="toggleExpand(staff.admin_id)">
            <!-- Avatar -->
            <div class="staff-avatar">
              <img v-if="getAvatar(staff)" :src="getAvatar(staff)" alt="" class="avatar-img" />
              <div v-else class="avatar-placeholder">{{ getInitial(staff.full_name) }}</div>
            </div>

            <!-- Info -->
            <div class="staff-info">
              <div class="staff-name">{{ staff.full_name }}</div>
              <div class="staff-email">{{ staff.email }}</div>
            </div>

            <!-- Role -->
            <span class="role-badge" :class="getRoleBadge(staff.role).class">
              {{ getRoleBadge(staff.role).label }}
            </span>

            <!-- Face Status -->
            <div class="face-status">
              <span v-if="staff.face_registered" class="face-badge face-badge--ok">
                <i class="bi bi-shield-fill-check me-1"></i>{{ staff.face_count }} ảnh
              </span>
              <span v-else class="face-badge face-badge--no">
                <i class="bi bi-shield-fill-exclamation me-1"></i>Chưa đăng ký
              </span>
            </div>

            <!-- Actions -->
            <div class="staff-actions" @click.stop>
              <button v-if="staff.face_registered" @click="handleResetUser(staff)" class="btn btn-sm btn-outline-danger" title="Xóa ảnh đăng ký"
                :id="'btn-reset-' + staff.admin_id">
                <i class="bi bi-trash3"></i>
              </button>
            </div>

            <!-- Expand arrow -->
            <i class="bi expand-arrow" :class="expandedUser === staff.admin_id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
          </div>

          <!-- Expanded: Face Photos -->
          <Transition name="slide-fade">
            <div v-if="expandedUser === staff.admin_id && staff.face_registered" class="expanded-content mt-3 pt-3">
              <div class="face-photos-row">
                <div v-for="enc in staff.face_encodings" :key="enc.id" class="face-photo-card">
                  <img v-if="enc.image_path" :src="getFaceImage(enc.image_path)" alt="" class="face-photo-img" />
                  <div v-else class="face-photo-placeholder"><i class="bi bi-person-bounding-box"></i></div>
                  <div class="face-photo-label">{{ enc.label }}</div>
                  <div class="face-photo-date">{{ enc.created_at }}</div>
                </div>
              </div>
            </div>
            <div v-else-if="expandedUser === staff.admin_id && !staff.face_registered" class="expanded-content mt-3 pt-3">
              <p class="text-muted small mb-0 text-center">
                <i class="bi bi-info-circle me-1"></i>
                Nhân viên chưa đăng ký khuôn mặt. Họ có thể tự đăng ký qua trang <b>"Đăng ký khuôn mặt"</b>.
              </p>
            </div>
          </Transition>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-5">
      <i class="bi bi-person-x fs-1 text-muted"></i>
      <p class="text-muted mt-2">Không tìm thấy nhân viên nào.</p>
    </div>
  </div>
</template>

<style scoped>
.face-mgmt { max-width: 1000px; margin: 0 auto; }
.fm-header { display: flex; justify-content: space-between; align-items: center; }

/* Stats */
.stat-card {
  display: flex; align-items: center; gap: 16px;
  padding: 20px 24px; border-radius: 16px; cursor: pointer;
  transition: all 0.3s; border: 2px solid transparent;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-active { border-color: currentColor !important; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
.stat-total { background: #eef2ff; color: #4f46e5; }
.stat-registered { background: #dcfce7; color: #16a34a; }
.stat-pending { background: #fef3c7; color: #d97706; }
.stat-icon { font-size: 1.6rem; opacity: 0.8; }
.stat-number { font-size: 1.8rem; font-weight: 800; line-height: 1; }
.stat-label { font-size: 0.8rem; font-weight: 500; opacity: 0.8; margin-top: 2px; }

/* Staff card */
.staff-row {
  display: flex; align-items: center; gap: 14px; cursor: pointer;
  transition: background 0.2s; padding: 4px; border-radius: 10px;
}
.staff-row:hover { background: #f8fafc; }
.staff-avatar { flex-shrink: 0; }
.avatar-img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
.avatar-placeholder {
  width: 44px; height: 44px; border-radius: 50%;
  background: #eef2ff; color: #4f46e5;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 1.1rem;
}
.staff-info { flex: 1; min-width: 0; }
.staff-name { font-weight: 600; font-size: 0.95rem; color: #1e293b; }
.staff-email { font-size: 0.8rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Role */
.role-badge {
  padding: 4px 12px; border-radius: 20px; font-size: 0.75rem;
  font-weight: 600; white-space: nowrap;
}
.role-admin { background: #fce7f3; color: #be185d; }
.role-seller { background: #dbeafe; color: #1d4ed8; }
.role-staff { background: #e0e7ff; color: #4338ca; }

/* Face badge */
.face-badge {
  padding: 5px 14px; border-radius: 20px; font-size: 0.78rem;
  font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center;
}
.face-badge--ok { background: #dcfce7; color: #166534; }
.face-badge--no { background: #fef3c7; color: #92400e; }

.staff-actions { flex-shrink: 0; }
.expand-arrow { color: #94a3b8; font-size: 0.9rem; flex-shrink: 0; }

/* Expanded */
.expanded-content { border-top: 1px solid #f1f5f9; }
.face-photos-row { display: flex; gap: 16px; flex-wrap: wrap; }
.face-photo-card { text-align: center; width: 110px; }
.face-photo-img {
  width: 100%; aspect-ratio: 1; object-fit: cover;
  border-radius: 12px; border: 2px solid #e2e8f0;
  transition: transform 0.2s;
}
.face-photo-img:hover { transform: scale(1.05); }
.face-photo-placeholder {
  width: 100%; aspect-ratio: 1; border-radius: 12px;
  background: #f1f5f9; display: flex; align-items: center;
  justify-content: center; font-size: 2rem; color: #cbd5e1;
}
.face-photo-label { font-weight: 600; font-size: 0.75rem; color: #334155; margin-top: 6px; }
.face-photo-date { font-size: 0.65rem; color: #94a3b8; }

/* Transitions */
.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-leave-active { transition: all 0.2s ease; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
