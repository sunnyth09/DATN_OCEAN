<template>
  <div class="work-locations-container p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0 fw-bold text-gray-800">Quản lý Vị trí Làm việc</h2>
      <button class="btn btn-primary btn-sm rounded-pill px-3" @click="openForm(null)" id="btn-add-location">
        <i class="bi bi-plus-lg me-1"></i> Thêm vị trí
      </button>
    </div>

    <!-- Table Card -->
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">ID</th>
                <th>Tên vị trí</th>
                <th>Địa chỉ</th>
                <th>Vĩ độ</th>
                <th>Kinh độ</th>
                <th>Bán kính</th>
                <th>Trạng thái</th>
                <th class="text-end pe-4">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="8" class="text-center py-4">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="locations.length === 0">
                <td colspan="8" class="text-center py-4 text-muted">Chưa có vị trí làm việc nào.</td>
              </tr>
              <tr v-else v-for="loc in locations" :key="loc.id">
                <td class="ps-4 fw-medium text-muted">#{{ loc.id }}</td>
                <td class="fw-bold">{{ loc.name }}</td>
                <td class="text-muted small">{{ loc.address || '-' }}</td>
                <td class="small font-monospace">{{ Number(loc.latitude).toFixed(6) }}</td>
                <td class="small font-monospace">{{ Number(loc.longitude).toFixed(6) }}</td>
                <td>
                  <span class="badge text-bg-primary">{{ loc.radius_meters }}m</span>
                </td>
                <td>
                  <span class="badge" :class="loc.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                    {{ loc.is_active ? 'Hoạt động' : 'Tắt' }}
                  </span>
                </td>
                <td class="text-end pe-4">
                  <button class="btn btn-sm btn-outline-primary me-1" @click="openForm(loc)" title="Sửa">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(loc)" title="Vô hiệu hóa">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeForm">
      <div class="modal-box">
        <div class="modal-header-custom">
          <h5 class="fw-bold mb-0">{{ editingLocation ? 'Sửa vị trí' : 'Thêm vị trí mới' }}</h5>
          <button class="btn-close" @click="closeForm"></button>
        </div>
        <div class="modal-body-custom">
          <div class="mb-3">
            <label class="form-label fw-medium">Tên vị trí <span class="text-danger">*</span></label>
            <input type="text" class="form-control" v-model="form.name" placeholder="Ví dụ: Cửa hàng chính" id="input-location-name" />
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Địa chỉ</label>
            <input type="text" class="form-control" v-model="form.address" placeholder="Ví dụ: 123 Trần Phú, Nha Trang" />
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-medium">Vĩ độ (Latitude) <span class="text-danger">*</span></label>
              <input type="number" step="0.00000001" class="form-control" v-model.number="form.latitude" placeholder="12.7098567" id="input-latitude" />
            </div>
            <div class="col-6">
              <label class="form-label fw-medium">Kinh độ (Longitude) <span class="text-danger">*</span></label>
              <input type="number" step="0.00000001" class="form-control" v-model.number="form.longitude" placeholder="108.0733147" id="input-longitude" />
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-medium">Bán kính (mét) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" v-model.number="form.radius_meters" placeholder="200" min="10" max="5000" id="input-radius" />
            </div>
            <div class="col-6">
              <label class="form-label fw-medium">Trạng thái</label>
              <select class="form-select" v-model="form.is_active">
                <option :value="true">Hoạt động</option>
                <option :value="false">Tắt</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary w-100 fw-bold" @click="submitForm" :disabled="submitting" id="btn-submit-location">
            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
            {{ editingLocation ? 'Cập nhật' : 'Tạo mới' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="wl-toast">
      <div v-if="toastVisible" class="wl-toast" :class="'wl-toast-' + toast.type">
        {{ toast.message }}
      </div>
    </Transition>
  </div>
</template>

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

<style scoped>
.work-locations-container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Modal Overlay */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9998;
  backdrop-filter: blur(3px);
}

.modal-box {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.modal-header-custom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #eee;
}

.modal-body-custom {
  padding: 24px;
}

/* Toast */
.wl-toast {
  position: fixed;
  bottom: 30px;
  right: 30px;
  padding: 14px 28px;
  border-radius: 12px;
  color: white;
  font-weight: 500;
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  z-index: 9999;
  font-size: 0.95rem;
}

.wl-toast-success { background: linear-gradient(135deg, #10b981, #059669); }
.wl-toast-error { background: linear-gradient(135deg, #ef4444, #dc2626); }

.wl-toast-enter-active { transition: all 0.3s ease; }
.wl-toast-leave-active { transition: all 0.2s ease; }
.wl-toast-enter-from { opacity: 0; transform: translateX(40px); }
.wl-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
