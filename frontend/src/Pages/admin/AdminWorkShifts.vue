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
  if (name.includes('sáng')) return 'shift-label-morning';
  if (name.includes('chiều')) return 'shift-label-afternoon';
  if (name.includes('tối')) return 'shift-label-night';
  return '';
};

onMounted(() => { fetchShifts(); });
</script>
<template>
  <div class="admin-ws animate-in">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Quản lý Ca Làm Việc & Phân Ca
        </h2>
        <p class="section-desc">Thiết lập ca làm việc và phân ca cho nhân viên theo ngày trong tuần.</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="ws-tabs ocean-card">
      <button class="ws-tab" :class="{ active: activeTab === 'shifts' }" @click="activeTab = 'shifts'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Danh sách Ca
      </button>
      <button class="ws-tab" :class="{ active: activeTab === 'assign' }" @click="activeTab = 'assign'; loadAssignments()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Phân Ca Nhân Viên
      </button>
    </div>

    <!-- ===== TAB 1: DANH SÁCH CA ===== -->
    <div v-if="activeTab === 'shifts'">
      <div class="tab-header-actions">
        <button class="btn-create" @click="openShiftForm(null)" id="btn-add-shift">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Thêm ca
        </button>
      </div>

      <div class="ocean-card table-wrapper">
        <table class="ws-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tên ca</th>
              <th>Bắt đầu</th>
              <th>Kết thúc</th>
              <th>Buffer sớm</th>
              <th class="status-th">Trạng thái</th>
              <th class="actions-th">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingShifts">
              <td colspan="7" class="empty-cell"><div class="ws-spinner"></div></td>
            </tr>
            <tr v-else-if="shifts.length === 0">
              <td colspan="7" class="empty-cell">Chưa có ca nào.</td>
            </tr>
            <tr v-else v-for="s in shifts" :key="s.id" class="ws-row">
              <td class="id-cell">#{{ s.id }}</td>
              <td>
                <div class="shift-info-cell">
                  <div class="shift-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  </div>
                  <span class="shift-name">{{ s.name }}</span>
                </div>
              </td>
              <td><span class="time-badge time-start">{{ s.start_time?.substring(0, 5) }}</span></td>
              <td><span class="time-badge time-end">{{ s.end_time?.substring(0, 5) }}</span></td>
              <td class="buffer-cell">{{ s.early_buffer_minutes }} phút</td>
              <td class="status-cell">
                <span class="ws-status-badge" :class="s.is_active ? 'status-active' : 'status-inactive'">
                  {{ s.is_active ? 'Hoạt động' : 'Tắt' }}
                </span>
              </td>
              <td class="actions-cell">
                <button class="btn-action edit" @click="openShiftForm(s)" title="Sửa">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn-action delete" @click="deleteShift(s)" title="Vô hiệu hóa">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ===== TAB 2: PHÂN CA NHÂN VIÊN ===== -->
    <div v-if="activeTab === 'assign'">
      <div v-if="loadingAssign" class="loading-state">
        <div class="ws-spinner"></div>
        <p>Đang tải bảng phân ca...</p>
      </div>
      <div v-else>
        <div class="ocean-card table-wrapper">
          <table class="ws-table schedule-table">
            <thead>
              <tr>
                <th style="min-width: 180px;">Nhân viên</th>
                <th v-for="day in dayLabels" :key="day.value" class="text-center" style="min-width: 120px;">
                  {{ day.label }}
                </th>
                <th class="text-center" style="min-width: 80px;">Lưu</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="staff in assignData.staff" :key="staff.user_id" class="ws-row">
                <td>
                  <div class="staff-assign-cell">
                    <div class="assign-avatar">{{ (staff.full_name || '?')[0].toUpperCase() }}</div>
                    <div>
                      <div class="assign-name">{{ staff.full_name }}</div>
                      <span class="assign-role">{{ staff.role }}</span>
                    </div>
                  </div>
                </td>
                <td v-for="day in dayLabels" :key="day.value" class="text-center schedule-cell">
                  <div v-for="shift in assignData.shifts" :key="shift.id" class="shift-check-item">
                    <label class="shift-checkbox-label">
                      <input type="checkbox"
                             :checked="isAssigned(staff, shift.id, day.value)"
                             @change="toggleAssign(staff, shift.id, day.value, $event)" />
                      <span class="shift-check-text" :class="getShiftLabelClass(shift.name)">
                        {{ getShiftShortName(shift.name) }}
                      </span>
                    </label>
                  </div>
                </td>
                <td class="text-center">
                  <button class="btn-save-assign" @click="saveStaffAssignment(staff)" :disabled="staff._saving">
                    <span v-if="staff._saving" class="ws-spinner-sm"></span>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="schedule-hint">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Tick ca cho từng nhân viên theo ngày, sau đó nhấn nút lưu.
        </p>
      </div>
    </div>

    <!-- ===== MODAL: Thêm/Sửa Ca ===== -->
    <Teleport to="body">
    <Transition name="ws-modal">
      <div v-if="showShiftModal" class="ws-modal-overlay" @click.self="showShiftModal = false">
        <div class="ws-modal-box">
          <div class="ws-modal-head">
            <h3>{{ editingShift ? 'Sửa ca' : 'Thêm ca mới' }}</h3>
            <button class="ws-btn-close" @click="showShiftModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form @submit.prevent="submitShiftForm" class="ws-modal-body">
            <div class="ws-form-group">
              <label>Tên ca <span class="ws-required">*</span></label>
              <input type="text" class="ws-form-control" v-model="shiftForm.name" placeholder="Ví dụ: Ca sáng" />
            </div>
            <div class="ws-form-row">
              <div class="ws-form-group">
                <label>Giờ bắt đầu <span class="ws-required">*</span></label>
                <input type="time" class="ws-form-control" v-model="shiftForm.start_time" />
              </div>
              <div class="ws-form-group">
                <label>Giờ kết thúc <span class="ws-required">*</span></label>
                <input type="time" class="ws-form-control" v-model="shiftForm.end_time" />
              </div>
            </div>
            <div class="ws-form-row">
              <div class="ws-form-group">
                <label>Buffer sớm (phút)</label>
                <input type="number" class="ws-form-control" v-model.number="shiftForm.early_buffer_minutes" min="0" max="120" />
              </div>
              <div class="ws-form-group">
                <label>Trạng thái</label>
                <select class="ws-form-control ws-form-select" v-model="shiftForm.is_active">
                  <option :value="true">Hoạt động</option>
                  <option :value="false">Tắt</option>
                </select>
              </div>
            </div>
            <div class="ws-modal-footer">
              <button type="button" @click="showShiftModal = false" class="ws-btn-outline">Hủy bỏ</button>
              <button type="submit" class="ws-btn-primary" :disabled="submittingShift">
                <svg v-if="!submittingShift" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span v-if="submittingShift" class="ws-spinner-sm"></span>
                {{ submittingShift ? 'Đang lưu...' : (editingShift ? 'Cập nhật' : 'Tạo mới') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
    <Transition name="ws-toast">
      <div v-if="toastVisible" class="ws-toast" :class="'ws-toast-' + toast.type">{{ toast.message }}</div>
    </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ===== Page Header ===== */
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.section-title { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }
.section-desc { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }

/* ===== Tabs ===== */
.ws-tabs { display: flex; gap: 4px; padding: 6px; margin-bottom: 20px; }
.ws-tab {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 20px; border-radius: 8px; border: none;
  background: transparent; color: var(--text-muted);
  font-weight: 600; font-size: 0.85rem; cursor: pointer;
  transition: all 0.2s; font-family: var(--font-inter);
}
.ws-tab:hover { background: var(--hover-bg); color: var(--text-main); }
.ws-tab.active { background: var(--primary); color: white; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.25); }
.ws-tab.active svg { stroke: white; }

.tab-header-actions { display: flex; justify-content: flex-end; margin-bottom: 16px; }

.btn-create {
  display: flex; align-items: center; gap: 8px;
  background: var(--primary); color: white; border: none;
  padding: 10px 20px; border-radius: 10px;
  font-weight: 600; font-size: 0.85rem; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.25);
  font-family: var(--font-inter);
}
.btn-create:hover { background: #d82f65; transform: translateY(-1px); }

/* ===== Table ===== */
.table-wrapper { overflow-x: auto; }
.ws-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.ws-table th {
  text-align: left; padding: 14px 16px; font-weight: 700; font-size: 0.72rem;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--text-muted); border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.ws-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.ws-row { transition: background 0.15s; }
.ws-row:hover { background: var(--hover-bg); }
.id-cell { color: var(--text-light); font-weight: 700; font-size: 0.8rem; }
.empty-cell { text-align: center; padding: 40px !important; color: var(--text-light); }

.shift-info-cell { display: flex; align-items: center; gap: 12px; }
.shift-name { font-weight: 600; color: var(--text-main); }
.shift-icon {
  width: 36px; height: 36px; border-radius: 50%; background: #e8f5e9; color: #2e7d32;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.time-badge {
  display: inline-flex; padding: 4px 10px; border-radius: 6px;
  font-size: 0.8rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;
}
.time-start { background: #e8f5e9; color: #2e7d32; }
.time-end { background: #ffebee; color: #c62828; }
.buffer-cell { color: var(--text-muted); }

.ws-status-badge {
  display: inline-flex; align-items: center; padding: 5px 12px;
  border-radius: 20px; font-size: 0.75rem; font-weight: 700;
}
.status-active { background: #e8f5e9; color: #2e7d32; }
.status-inactive { background: #f5f5f5; color: #757575; }

.status-th, .actions-th { text-align: center !important; }
.status-cell, .actions-cell { text-align: center; }
.actions-cell { display: flex; gap: 6px; justify-content: center; }

.btn-action {
  width: 34px; height: 34px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s; border: 1.5px solid;
}
.btn-action.edit { background: #e3f2fd; color: #1565c0; border-color: #bbdefb; }
.btn-action.edit:hover { background: #bbdefb; color: #0d47a1; }
.btn-action.delete { background: #ffebee; color: var(--primary); border-color: #ffcdd2; }
.btn-action.delete:hover { background: #ffcdd2; color: #c62828; }

/* Schedule Table */
.schedule-table th { text-align: center; }
.schedule-cell { padding: 8px 4px !important; }
.shift-check-item { display: inline-flex; margin: 2px 2px; }
.shift-checkbox-label { display: flex; align-items: center; gap: 3px; cursor: pointer; font-size: 0.8rem; }
.shift-checkbox-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
.shift-check-text { font-weight: 700; font-size: 0.78rem; }
.shift-label-morning { color: #1565c0; }
.shift-label-afternoon { color: #2e7d32; }
.shift-label-night { color: #c62828; }

.staff-assign-cell { display: flex; align-items: center; gap: 12px; }
.assign-avatar {
  width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.assign-name { font-weight: 600; color: var(--text-main); font-size: 0.85rem; }
.assign-role { font-size: 0.72rem; color: var(--text-muted); }

.btn-save-assign {
  width: 36px; height: 36px; border-radius: 8px; border: none;
  background: var(--primary); color: white; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  transition: all 0.2s; box-shadow: 0 2px 8px rgba(230, 59, 111, 0.25);
}
.btn-save-assign:hover { background: #d82f65; transform: translateY(-1px); }
.btn-save-assign:disabled { opacity: 0.5; cursor: not-allowed; }

.schedule-hint {
  display: flex; align-items: center; gap: 6px;
  font-size: 0.82rem; color: var(--text-muted); margin-top: 12px;
}

.loading-state { text-align: center; padding: 60px 0; }
.loading-state p { color: var(--text-muted); margin-top: 12px; font-size: 0.85rem; }

/* Spinner */
.ws-spinner {
  width: 32px; height: 32px; border: 3px solid var(--border-color);
  border-top-color: var(--primary); border-radius: 50%; animation: wsSpin 0.7s linear infinite;
  margin: 0 auto;
}
.ws-spinner-sm {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff; border-radius: 50%; animation: wsSpin 0.6s linear infinite; display: inline-block;
}
@keyframes wsSpin { to { transform: rotate(360deg); } }
.text-center { text-align: center; }
</style>

<!-- Non-scoped styles for Teleported modals/toasts -->
<style>
/* ===== WS Modal Overlay ===== */
.ws-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.ws-modal-box {
  width: 100%; max-width: 520px; padding: 0;
  background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px; overflow: hidden;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.ws-modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px; border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.ws-modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #102a43); }
.ws-btn-close {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted, #627d98); display: flex; align-items: center; justify-content: center;
  padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.ws-btn-close:hover { background: var(--hover-bg, #e6f4fa); color: var(--primary, var(--primary)); }

.ws-modal-body { padding: 24px; }
.ws-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

/* Form */
.ws-form-group { margin-bottom: 16px; }
.ws-form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main, #102a43); margin-bottom: 8px; }
.ws-required { color: var(--primary, var(--primary)); }
.ws-form-control {
  width: 100%; padding: 10px 14px; border-radius: 8px;
  border: 1px solid var(--border-color, #d9e8f0); background: var(--ocean-deepest, #f0f7fa);
  color: var(--text-main, #102a43); font-family: var(--font-inter, 'Inter', sans-serif);
  font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.ws-form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.ws-form-control::placeholder { color: var(--text-light, #9fb3c8); }
.ws-form-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 14px center;
}
.ws-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .ws-form-row { grid-template-columns: 1fr; } }

/* Buttons */
.ws-btn-outline {
  padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color, #d9e8f0);
  background: var(--card-bg); color: var(--text-main, #102a43); font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter, 'Inter', sans-serif);
}
.ws-btn-outline:hover { border-color: var(--ocean-mid, #b3e0f2); background: var(--ocean-deepest, #f0f7fa); }
.ws-btn-primary {
  padding: 10px 20px; border-radius: 8px; border: none;
  background: var(--primary); color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
  font-family: var(--font-inter, 'Inter', sans-serif);
}
.ws-btn-primary:hover { background: #d82f65; }
.ws-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

/* Toast */
.ws-toast {
  position: fixed; top: 24px; right: 24px; z-index: 2000;
  padding: 14px 22px; border-radius: 10px; color: #fff;
  font-size: 0.85rem; font-weight: 600;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}
.ws-toast-success { background: var(--seafoam, #26a69a); }
.ws-toast-error { background: var(--coral, #ef5350); }

/* Transitions */
.ws-modal-enter-active, .ws-modal-leave-active { transition: all 0.25s ease; }
.ws-modal-enter-from, .ws-modal-leave-to { opacity: 0; }
.ws-modal-enter-from .ws-modal-box, .ws-modal-leave-to .ws-modal-box { transform: scale(0.95) translateY(10px); }

.ws-toast-enter-active { transition: all 0.3s ease; }
.ws-toast-leave-active { transition: all 0.2s ease; }
.ws-toast-enter-from { opacity: 0; transform: translateX(40px); }
.ws-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
