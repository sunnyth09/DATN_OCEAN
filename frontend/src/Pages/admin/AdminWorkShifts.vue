<template>
  <div class="work-shifts-container p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0 fw-bold text-gray-800">Quản lý Ca Làm Việc & Phân Ca</h2>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'shifts' }" @click="activeTab = 'shifts'">
          <i class="bi bi-clock me-1"></i> Danh sách Ca
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'assign' }" @click="activeTab = 'assign'; loadAssignments()">
          <i class="bi bi-people me-1"></i> Phân Ca Nhân Viên
        </button>
      </li>
    </ul>

    <!-- ===== TAB 1: DANH SÁCH CA ===== -->
    <div v-if="activeTab === 'shifts'">
      <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm rounded-pill px-3" @click="openShiftForm(null)" id="btn-add-shift">
          <i class="bi bi-plus-lg me-1"></i> Thêm ca
        </button>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">ID</th>
                  <th>Tên ca</th>
                  <th>Bắt đầu</th>
                  <th>Kết thúc</th>
                  <th>Buffer sớm</th>
                  <th>Trạng thái</th>
                  <th class="text-end pe-4">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loadingShifts">
                  <td colspan="7" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td>
                </tr>
                <tr v-else-if="shifts.length === 0">
                  <td colspan="7" class="text-center py-4 text-muted">Chưa có ca nào.</td>
                </tr>
                <tr v-else v-for="s in shifts" :key="s.id">
                  <td class="ps-4 fw-medium text-muted">#{{ s.id }}</td>
                  <td class="fw-bold">{{ s.name }}</td>
                  <td><span class="badge text-bg-success">{{ s.start_time?.substring(0, 5) }}</span></td>
                  <td><span class="badge text-bg-danger">{{ s.end_time?.substring(0, 5) }}</span></td>
                  <td>{{ s.early_buffer_minutes }} phút</td>
                  <td>
                    <span class="badge" :class="s.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                      {{ s.is_active ? 'Hoạt động' : 'Tắt' }}
                    </span>
                  </td>
                  <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-primary me-1" @click="openShiftForm(s)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" @click="deleteShift(s)"><i class="bi bi-trash"></i></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TAB 2: PHÂN CA NHÂN VIÊN ===== -->
    <div v-if="activeTab === 'assign'">
      <div v-if="loadingAssign" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="text-muted mt-2">Đang tải bảng phân ca...</p>
      </div>
      <div v-else>
        <div class="card shadow-sm border-0">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered align-middle mb-0 schedule-table">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3" style="min-width: 180px;">Nhân viên</th>
                    <th v-for="day in dayLabels" :key="day.value" class="text-center" style="min-width: 120px;">
                      {{ day.label }}
                    </th>
                    <th class="text-center" style="min-width: 80px;">Lưu</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="staff in assignData.staff" :key="staff.user_id">
                    <td class="ps-3">
                      <div class="fw-bold small">{{ staff.full_name }}</div>
                      <span class="badge text-bg-light text-muted" style="font-size: 0.7rem;">{{ staff.role }}</span>
                    </td>
                    <td v-for="day in dayLabels" :key="day.value" class="text-center p-1">
                      <div v-for="shift in assignData.shifts" :key="shift.id" class="form-check form-check-inline mb-0">
                        <input class="form-check-input"
                               type="checkbox"
                               :id="'cb-' + staff.user_id + '-' + shift.id + '-' + day.value"
                               :checked="isAssigned(staff, shift.id, day.value)"
                               @change="toggleAssign(staff, shift.id, day.value, $event)" />
                        <label class="form-check-label small"
                               :for="'cb-' + staff.user_id + '-' + shift.id + '-' + day.value"
                               :class="getShiftLabelClass(shift.name)">
                          {{ getShiftShortName(shift.name) }}
                        </label>
                      </div>
                    </td>
                    <td class="text-center">
                      <button class="btn btn-sm btn-primary px-2 py-1" @click="saveStaffAssignment(staff)" :disabled="staff._saving">
                        <span v-if="staff._saving" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-check-lg"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <p class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Tick ca cho từng nhân viên theo ngày, sau đó nhấn nút <i class="bi bi-check-lg"></i> để lưu.</p>
      </div>
    </div>

    <!-- ===== MODAL: Thêm/Sửa Ca ===== -->
    <div v-if="showShiftModal" class="modal-overlay" @click.self="showShiftModal = false">
      <div class="modal-box">
        <div class="modal-header-custom">
          <h5 class="fw-bold mb-0">{{ editingShift ? 'Sửa ca' : 'Thêm ca mới' }}</h5>
          <button class="btn-close" @click="showShiftModal = false"></button>
        </div>
        <div class="modal-body-custom">
          <div class="mb-3">
            <label class="form-label fw-medium">Tên ca <span class="text-danger">*</span></label>
            <input type="text" class="form-control" v-model="shiftForm.name" placeholder="Ví dụ: Ca sáng" />
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-medium">Giờ bắt đầu <span class="text-danger">*</span></label>
              <input type="time" class="form-control" v-model="shiftForm.start_time" />
            </div>
            <div class="col-6">
              <label class="form-label fw-medium">Giờ kết thúc <span class="text-danger">*</span></label>
              <input type="time" class="form-control" v-model="shiftForm.end_time" />
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-medium">Buffer sớm (phút)</label>
              <input type="number" class="form-control" v-model.number="shiftForm.early_buffer_minutes" min="0" max="120" />
            </div>
            <div class="col-6">
              <label class="form-label fw-medium">Trạng thái</label>
              <select class="form-select" v-model="shiftForm.is_active">
                <option :value="true">Hoạt động</option>
                <option :value="false">Tắt</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary w-100 fw-bold" @click="submitShiftForm" :disabled="submittingShift">
            <span v-if="submittingShift" class="spinner-border spinner-border-sm me-2"></span>
            {{ editingShift ? 'Cập nhật' : 'Tạo mới' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="ws-toast">
      <div v-if="toastVisible" class="ws-toast" :class="'ws-toast-' + toast.type">{{ toast.message }}</div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import api from '../../axios';
import Swal from 'sweetalert2';

const activeTab = ref('shifts');

// ===== TOAST =====
const toastVisible = ref(false);
let toastTimer = null;
const toast = ref({ message: '', type: 'success' });
const showToast = (msg, type = 'success') => {
  toast.value = { message: msg, type };
  toastVisible.value = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastVisible.value = false; }, 3000);
};

// ===== TAB 1: DANH SÁCH CA =====
const shifts = ref([]);
const loadingShifts = ref(true);
const showShiftModal = ref(false);
const submittingShift = ref(false);
const editingShift = ref(null);
const shiftForm = ref({ name: '', start_time: '', end_time: '', early_buffer_minutes: 30, is_active: true });

const fetchShifts = async () => {
  loadingShifts.value = true;
  try {
    const res = await api.get('/admin/work-shifts');
    shifts.value = res.data.data;
  } catch { shifts.value = []; }
  finally { loadingShifts.value = false; }
};

const openShiftForm = (s) => {
  if (s) {
    editingShift.value = s;
    shiftForm.value = {
      name: s.name,
      start_time: s.start_time?.substring(0, 5),
      end_time: s.end_time?.substring(0, 5),
      early_buffer_minutes: s.early_buffer_minutes,
      is_active: s.is_active,
    };
  } else {
    editingShift.value = null;
    shiftForm.value = { name: '', start_time: '', end_time: '', early_buffer_minutes: 30, is_active: true };
  }
  showShiftModal.value = true;
};

const submitShiftForm = async () => {
  if (!shiftForm.value.name || !shiftForm.value.start_time || !shiftForm.value.end_time) {
    showToast('Vui lòng điền đầy đủ.', 'error'); return;
  }
  submittingShift.value = true;
  try {
    if (editingShift.value) {
      await api.put(`/admin/work-shifts/${editingShift.value.id}`, shiftForm.value);
      showToast('Cập nhật ca thành công!');
    } else {
      await api.post('/admin/work-shifts', shiftForm.value);
      showToast('Tạo ca thành công!');
    }
    showShiftModal.value = false;
    fetchShifts();
  } catch (e) { showToast(e.response?.data?.message || 'Lỗi', 'error'); }
  finally { submittingShift.value = false; }
};

const deleteShift = async (s) => {
  const r = await Swal.fire({
    title: 'Xác nhận', text: `Vô hiệu hóa ca "${s.name}"?`, icon: 'warning',
    showCancelButton: true, confirmButtonText: 'Vô hiệu hóa', cancelButtonText: 'Hủy', confirmButtonColor: '#dc3545',
  });
  if (r.isConfirmed) {
    try { await api.delete(`/admin/work-shifts/${s.id}`); showToast('Đã vô hiệu hóa.'); fetchShifts(); }
    catch { showToast('Lỗi.', 'error'); }
  }
};

// ===== TAB 2: PHÂN CA =====
const dayLabels = [
  { value: 1, label: 'Thứ 2' }, { value: 2, label: 'Thứ 3' }, { value: 3, label: 'Thứ 4' },
  { value: 4, label: 'Thứ 5' }, { value: 5, label: 'Thứ 6' }, { value: 6, label: 'Thứ 7' },
  { value: 0, label: 'CN' },
];

const loadingAssign = ref(false);
const assignData = reactive({ staff: [], shifts: [] });

const loadAssignments = async () => {
  loadingAssign.value = true;
  try {
    const res = await api.get('/admin/shift-assignments');
    assignData.staff = res.data.data.staff.map(s => ({ ...s, _saving: false }));
    assignData.shifts = res.data.data.shifts;
  } catch { assignData.staff = []; assignData.shifts = []; }
  finally { loadingAssign.value = false; }
};

const isAssigned = (staff, shiftId, dayOfWeek) => {
  return staff.assignments.some(a => a.work_shift_id === shiftId && a.day_of_week === dayOfWeek);
};

const toggleAssign = (staff, shiftId, dayOfWeek, event) => {
  const checked = event.target.checked;
  if (checked) {
    staff.assignments.push({ work_shift_id: shiftId, day_of_week: dayOfWeek });
  } else {
    staff.assignments = staff.assignments.filter(a => !(a.work_shift_id === shiftId && a.day_of_week === dayOfWeek));
  }
};

const saveStaffAssignment = async (staff) => {
  staff._saving = true;
  try {
    await api.post('/admin/shift-assignments', {
      user_id: staff.user_id,
      user_type: staff.user_type,
      assignments: staff.assignments.map(a => ({
        work_shift_id: a.work_shift_id,
        day_of_week: a.day_of_week,
      })),
    });
    showToast(`Lưu phân ca cho "${staff.full_name}" thành công!`);
  } catch (e) { showToast(e.response?.data?.message || 'Lỗi khi lưu.', 'error'); }
  finally { staff._saving = false; }
};

const getShiftShortName = (name) => {
  if (name.includes('sáng')) return 'S';
  if (name.includes('chiều')) return 'C';
  if (name.includes('tối')) return 'T';
  return name.charAt(0);
};

const getShiftLabelClass = (name) => {
  if (name.includes('sáng')) return 'text-primary';
  if (name.includes('chiều')) return 'text-success';
  if (name.includes('tối')) return 'text-danger';
  return '';
};

onMounted(() => { fetchShifts(); });
</script>

<style scoped>
.work-shifts-container { max-width: 1400px; margin: 0 auto; }

.schedule-table th, .schedule-table td { vertical-align: middle; }
.schedule-table .form-check { margin: 2px 0; }
.schedule-table .form-check-label { font-size: 0.8rem; font-weight: 600; cursor: pointer; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9998; backdrop-filter: blur(3px); }
.modal-box { background: white; border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header-custom { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #eee; }
.modal-body-custom { padding: 24px; }

.ws-toast { position: fixed; bottom: 30px; right: 30px; padding: 14px 28px; border-radius: 12px; color: white; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.2); z-index: 9999; }
.ws-toast-success { background: linear-gradient(135deg, #10b981, #059669); }
.ws-toast-error { background: linear-gradient(135deg, #ef4444, #dc2626); }
.ws-toast-enter-active { transition: all 0.3s ease; }
.ws-toast-leave-active { transition: all 0.2s ease; }
.ws-toast-enter-from, .ws-toast-leave-to { opacity: 0; transform: translateX(40px); }

.nav-tabs .nav-link { color: #6b7280; font-weight: 500; }
.nav-tabs .nav-link.active { color: #1d4ed8; font-weight: 600; border-color: #1d4ed8; border-bottom: 2px solid #1d4ed8; }
</style>
