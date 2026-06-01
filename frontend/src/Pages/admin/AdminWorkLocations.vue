<script setup>
import { ref, onMounted } from 'vue';
import api from '../../axios';
import Swal from 'sweetalert2';

const locations = ref([]);
const loading = ref(true);
const showModal = ref(false);
const submitting = ref(false);
const editingLocation = ref(null);

const form = ref({
  name: '',
  address: '',
  latitude: null,
  longitude: null,
  radius_meters: 200,
  is_active: true,
});

const toastVisible = ref(false);
let toastTimer = null;
const toast = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  toastVisible.value = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastVisible.value = false; }, 3000);
};

const fetchLocations = async () => {
  loading.value = true;
  try {
    const response = await api.get('/admin/work-locations');
    if (response.data.status === 'success') {
      locations.value = response.data.data;
    }
  } catch {
    locations.value = [];
  } finally {
    loading.value = false;
  }
};

const openForm = (loc) => {
  if (loc) {
    editingLocation.value = loc;
    form.value = {
      name: loc.name,
      address: loc.address || '',
      latitude: loc.latitude,
      longitude: loc.longitude,
      radius_meters: loc.radius_meters,
      is_active: loc.is_active,
    };
  } else {
    editingLocation.value = null;
    form.value = {
      name: '',
      address: '',
      latitude: null,
      longitude: null,
      radius_meters: 200,
      is_active: true,
    };
  }
  showModal.value = true;
};

const closeForm = () => {
  showModal.value = false;
  editingLocation.value = null;
};

const submitForm = async () => {
  if (!form.value.name || form.value.latitude === null || form.value.longitude === null || !form.value.radius_meters) {
    showToast('Vui lòng điền đầy đủ các trường bắt buộc.', 'error');
    return;
  }

  submitting.value = true;
  try {
    if (editingLocation.value) {
      await api.put(`/admin/work-locations/${editingLocation.value.id}`, form.value);
      showToast('Cập nhật vị trí thành công!', 'success');
    } else {
      await api.post('/admin/work-locations', form.value);
      showToast('Tạo vị trí mới thành công!', 'success');
    }
    closeForm();
    fetchLocations();
  } catch (error) {
    const msg = error.response?.data?.message || 'Đã xảy ra lỗi.';
    showToast(msg, 'error');
  } finally {
    submitting.value = false;
  }
};

const confirmDelete = async (loc) => {
  const result = await Swal.fire({
    title: 'Xác nhận',
    text: `Bạn có chắc muốn vô hiệu hóa vị trí "${loc.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Vô hiệu hóa',
    cancelButtonText: 'Hủy',
    confirmButtonColor: '#dc3545',
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/admin/work-locations/${loc.id}`);
      showToast('Đã vô hiệu hóa vị trí.', 'success');
      fetchLocations();
    } catch {
      showToast('Không thể vô hiệu hóa vị trí.', 'error');
    }
  }
};

onMounted(() => {
  fetchLocations();
});
</script>
<template>
  <div class="admin-wl animate-in">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>Quản lý Vị trí Làm việc
        </h2>
        <p class="section-desc">Thiết lập các địa điểm làm việc và bán kính chấm công GPS.</p>
      </div>
      <button @click="openForm(null)" class="btn-create" id="btn-add-location">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Thêm vị trí
      </button>
    </div>

    <!-- Table -->
    <div class="ocean-card table-wrapper">
      <table class="wl-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tên vị trí</th>
            <th>Địa chỉ</th>
            <th>Tọa độ</th>
            <th>Bán kính</th>
            <th class="status-th">Trạng thái</th>
            <th class="actions-th">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="empty-cell">
              <div class="wl-spinner"></div>
            </td>
          </tr>
          <tr v-else-if="locations.length === 0">
            <td colspan="7" class="empty-cell">Chưa có vị trí làm việc nào.</td>
          </tr>
          <tr v-else v-for="loc in locations" :key="loc.id" class="wl-row">
            <td class="id-cell">#{{ loc.id }}</td>
            <td>
              <div class="loc-info-cell">
                <div class="loc-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <span class="loc-name">{{ loc.name }}</span>
              </div>
            </td>
            <td class="address-cell">{{ loc.address || '—' }}</td>
            <td>
              <div class="coord-cell">
                <span class="coord-value">{{ Number(loc.latitude).toFixed(6) }}</span>
                <span class="coord-sep">,</span>
                <span class="coord-value">{{ Number(loc.longitude).toFixed(6) }}</span>
              </div>
            </td>
            <td><span class="radius-badge">{{ loc.radius_meters }}m</span></td>
            <td class="status-cell">
              <span class="status-badge" :class="loc.is_active ? 'status-active' : 'status-inactive'">
                {{ loc.is_active ? 'Hoạt động' : 'Tắt' }}
              </span>
            </td>
            <td class="actions-cell">
              <button class="btn-action edit" @click="openForm(loc)" title="Sửa">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="btn-action delete" @click="confirmDelete(loc)" title="Vô hiệu hóa">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
    <Transition name="wl-modal">
      <div v-if="showModal" class="wl-modal-overlay" @click.self="closeForm">
        <div class="wl-modal-box">
          <div class="wl-modal-head">
            <h3>{{ editingLocation ? 'Sửa vị trí' : 'Thêm vị trí mới' }}</h3>
            <button class="wl-btn-close" @click="closeForm">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form @submit.prevent="submitForm" class="wl-modal-body">
            <div class="wl-form-group">
              <label>Tên vị trí <span class="wl-required">*</span></label>
              <input type="text" class="wl-form-control" v-model="form.name" placeholder="Ví dụ: Cửa hàng chính" id="input-location-name" />
            </div>
            <div class="wl-form-group">
              <label>Địa chỉ</label>
              <input type="text" class="wl-form-control" v-model="form.address" placeholder="Ví dụ: 123 Trần Phú, Nha Trang" />
            </div>
            <div class="wl-form-row">
              <div class="wl-form-group">
                <label>Vĩ độ (Latitude) <span class="wl-required">*</span></label>
                <input type="number" step="0.00000001" class="wl-form-control" v-model.number="form.latitude" placeholder="12.7098567" id="input-latitude" />
              </div>
              <div class="wl-form-group">
                <label>Kinh độ (Longitude) <span class="wl-required">*</span></label>
                <input type="number" step="0.00000001" class="wl-form-control" v-model.number="form.longitude" placeholder="108.0733147" id="input-longitude" />
              </div>
            </div>
            <div class="wl-form-row">
              <div class="wl-form-group">
                <label>Bán kính (mét) <span class="wl-required">*</span></label>
                <input type="number" class="wl-form-control" v-model.number="form.radius_meters" placeholder="200" min="10" max="5000" id="input-radius" />
              </div>
              <div class="wl-form-group">
                <label>Trạng thái</label>
                <select class="wl-form-control wl-form-select" v-model="form.is_active">
                  <option :value="true">Hoạt động</option>
                  <option :value="false">Tắt</option>
                </select>
              </div>
            </div>
            <div class="wl-modal-footer">
              <button type="button" @click="closeForm" class="wl-btn-outline">Hủy bỏ</button>
              <button type="submit" class="wl-btn-primary" :disabled="submitting" id="btn-submit-location">
                <svg v-if="!submitting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span v-if="submitting" class="wl-spinner-sm"></span>
                {{ submitting ? 'Đang lưu...' : (editingLocation ? 'Cập nhật' : 'Tạo mới') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
    <Transition name="wl-toast">
      <div v-if="toastVisible" class="wl-toast" :class="'wl-toast-' + toast.type">
        {{ toast.message }}
      </div>
    </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ===== Page Header ===== */
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.section-title { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }
.section-desc { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }
.btn-create {
  display: flex; align-items: center; gap: 8px;
  background: #E63B6F; color: white; border: none;
  padding: 10px 20px; border-radius: 10px;
  font-weight: 600; font-size: 0.85rem; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.25);
  font-family: var(--font-inter);
}
.btn-create:hover { background: #d82f65; transform: translateY(-1px); }

/* ===== Table ===== */
.table-wrapper { overflow-x: auto; }
.wl-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.wl-table th {
  text-align: left; padding: 14px 16px; font-weight: 700; font-size: 0.72rem;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--text-muted); border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.wl-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.wl-row { transition: background 0.15s; }
.wl-row:hover { background: var(--hover-bg); }
.id-cell { color: var(--text-light); font-weight: 700; font-size: 0.8rem; }
.empty-cell { text-align: center; padding: 40px !important; color: var(--text-light); }

.loc-info-cell { display: flex; align-items: center; gap: 12px; }
.loc-name { font-weight: 600; color: var(--text-main); }
.loc-icon {
  width: 36px; height: 36px; border-radius: 50%; background: #fce4ec; color: #E63B6F;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.address-cell { color: var(--text-muted); font-size: 0.82rem; max-width: 250px; }
.coord-cell { display: flex; align-items: center; gap: 2px; font-family: 'JetBrains Mono', monospace; font-size: 0.78rem; color: var(--text-muted); }
.coord-sep { color: var(--text-light); margin: 0 2px; }

.radius-badge {
  display: inline-flex; align-items: center; padding: 4px 10px;
  border-radius: 20px; font-size: 0.78rem; font-weight: 700;
  background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb;
}

.status-badge {
  display: inline-flex; align-items: center; padding: 5px 12px;
  border-radius: 20px; font-size: 0.75rem; font-weight: 700;
}
.status-active { background: #e8f5e9; color: #2e7d32; }
.status-inactive { background: #f5f5f5; color: #757575; }

.status-th { text-align: center !important; }
.status-cell { text-align: center; }
.actions-th { text-align: center !important; }
.actions-cell { text-align: center; display: flex; gap: 6px; justify-content: center; }

.btn-action {
  width: 34px; height: 34px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s; border: 1.5px solid;
}
.btn-action.edit { background: #e3f2fd; color: #1565c0; border-color: #bbdefb; }
.btn-action.edit:hover { background: #bbdefb; color: #0d47a1; border-color: #90caf9; }
.btn-action.delete { background: #ffebee; color: var(--primary); border-color: #ffcdd2; }
.btn-action.delete:hover { background: #ffcdd2; color: #c62828; border-color: #ef9a9a; }

/* Spinner */
.wl-spinner {
  width: 32px; height: 32px; border: 3px solid var(--border-color);
  border-top-color: var(--primary); border-radius: 50%; animation: wlSpin 0.7s linear infinite;
  margin: 0 auto;
}
@keyframes wlSpin { to { transform: rotate(360deg); } }
</style>

<!-- Non-scoped styles for Teleported modals/toasts -->
<style>
/* ===== WL Modal Overlay ===== */
.wl-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.wl-modal-box {
  width: 100%; max-width: 520px; padding: 0;
  background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px; overflow: hidden;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.wl-modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px; border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.wl-modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #102a43); }
.wl-btn-close {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted, #627d98); display: flex; align-items: center; justify-content: center;
  padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.wl-btn-close:hover { background: var(--hover-bg, #e6f4fa); color: var(--primary, #E63B6F); }

.wl-modal-body { padding: 24px; }
.wl-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

/* Form */
.wl-form-group { margin-bottom: 16px; }
.wl-form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main, #102a43); margin-bottom: 8px; }
.wl-required { color: var(--primary, #E63B6F); }
.wl-form-control {
  width: 100%; padding: 10px 14px; border-radius: 8px;
  border: 1px solid var(--border-color, #d9e8f0); background: var(--ocean-deepest, #f0f7fa);
  color: var(--text-main, #102a43); font-family: var(--font-inter, 'Inter', sans-serif);
  font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.wl-form-control:focus { border-color: #E63B6F; outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.wl-form-control::placeholder { color: var(--text-light, #9fb3c8); }
.wl-form-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 14px center;
}
.wl-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .wl-form-row { grid-template-columns: 1fr; } }

/* Buttons */
.wl-btn-outline {
  padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color, #d9e8f0);
  background: #fff; color: var(--text-main, #102a43); font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter, 'Inter', sans-serif);
}
.wl-btn-outline:hover { border-color: var(--ocean-mid, #b3e0f2); background: var(--ocean-deepest, #f0f7fa); }
.wl-btn-primary {
  padding: 10px 20px; border-radius: 8px; border: none;
  background: #E63B6F; color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
  font-family: var(--font-inter, 'Inter', sans-serif);
}
.wl-btn-primary:hover { background: #d82f65; }
.wl-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.wl-spinner-sm {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff; border-radius: 50%; animation: wlSpin 0.6s linear infinite; display: inline-block;
}

/* Toast */
.wl-toast {
  position: fixed; top: 24px; right: 24px; z-index: 2000;
  padding: 14px 22px; border-radius: 10px; color: #fff;
  font-size: 0.85rem; font-weight: 600;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}
.wl-toast-success { background: var(--seafoam, #26a69a); }
.wl-toast-error { background: var(--coral, #ef5350); }

/* Transitions */
.wl-modal-enter-active, .wl-modal-leave-active { transition: all 0.25s ease; }
.wl-modal-enter-from, .wl-modal-leave-to { opacity: 0; }
.wl-modal-enter-from .wl-modal-box, .wl-modal-leave-to .wl-modal-box { transform: scale(0.95) translateY(10px); }

.wl-toast-enter-active { transition: all 0.3s ease; }
.wl-toast-leave-active { transition: all 0.2s ease; }
.wl-toast-enter-from { opacity: 0; transform: translateX(40px); }
.wl-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
