<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';

const staff = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const currentPage = ref(1);
const pagination = ref(null);
const isSubmitting = ref(false);
const formError = ref('');
const showCreateModal = ref(false);
const showDeleteModal = ref(false);
const deletingMember = ref(null);
const toastVisible = ref(false);

let searchTimer = null;
let toastTimer = null;

const newStaff = ref({ full_name: '', email: '', password: '', role: 'staff' });
const toast = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  toastVisible.value = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastVisible.value = false; }, 3000);
};

const debouncedFetch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchStaff();
  }, 500);
};

const changePage = (page) => {
  if (page < 1 || (pagination.value && page > pagination.value.last_page)) return;
  currentPage.value = page;
  fetchStaff();
};

const fetchStaff = async () => {
  try {
    loading.value = true;
    const response = await api.get('/admin/staff', { params: { search: searchQuery.value, per_page: 5, page: currentPage.value } });
    staff.value = response.data.data.data;
    pagination.value = {
      current_page: response.data.data.current_page,
      last_page: response.data.data.last_page,
      total: response.data.data.total,
    };
  } catch (error) {
    showToast('Lỗi tải danh sách nhân sự!', 'error');
  } finally {
    loading.value = false;
  }
};

const updateRole = async (adminId, newRole) => {
  try {
    const result = await api.put(`/admin/staff/${adminId}/role`, { role: newRole });
    showToast(result.data.message, 'success');
    fetchStaff();
  } catch (error) {
    showToast(error.response?.data?.message || 'Lỗi cập nhật role!', 'error');
    fetchStaff();
  }
};

const openCreateModal = () => {
  newStaff.value = { full_name: '', email: '', password: '', role: 'staff' };
  formError.value = '';
  showCreateModal.value = true;
};

const createStaff = async () => {
  if (!newStaff.value.full_name || !newStaff.value.email || !newStaff.value.password) {
    formError.value = 'Vui lòng nhập đầy đủ Họ tên, Email và Mật khẩu.';
    return;
  }
  if (newStaff.value.password.length < 6) {
    formError.value = 'Mật khẩu phải có ít nhất 6 ký tự.';
    return;
  }
  formError.value = '';
  isSubmitting.value = true;
  try {
    await api.post('/admin/staff', newStaff.value);
    showCreateModal.value = false;
    showToast('Đã tạo nhân sự mới thành công!', 'success');
    fetchStaff();
  } catch (error) {
    formError.value = error.response?.data?.message || 'Không thể tạo nhân sự.';
  } finally {
    isSubmitting.value = false;
  }
};

const openDeleteConfirm = (member) => {
  deletingMember.value = member;
  showDeleteModal.value = true;
};

const confirmDelete = async () => {
  if (!deletingMember.value) return;
  try {
    const id = deletingMember.value.user_id || deletingMember.value.admin_id;
    await api.delete(`/admin/staff/${id}`);
    showDeleteModal.value = false;
    showToast('Đã xóa tài khoản nhân sự thành công!', 'success');
    fetchStaff();
  } catch (error) {
    showDeleteModal.value = false;
    showToast(error.response?.data?.message || 'Không thể xóa nhân sự.', 'error');
  }
};

const toggleStatus = async (member) => {
  const currentIsActive = member.status === 'active' || member.status === 1;
  const newStatus = currentIsActive ? 'inactive' : 'active';
  const prevStatus = member.status; // save state for rollback
  
  // Optimistic UI update for immediate response feeling
  member.status = newStatus;
  
  const id = member.user_id || member.admin_id;
  try {
    await api.put(`/admin/staff/${id}/status`, { status: newStatus });
    showToast(`Tài khoản đã chuyển sang: ${newStatus === 'active' ? 'Hoạt động' : 'Tạm khóa'}`, 'success');
  } catch (error) {
    member.status = prevStatus; // Rollback
    showToast(error.response?.data?.message || 'Lỗi cập nhật trạng thái!', 'error');
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

onMounted(fetchStaff);
</script>
<template>
  <div class="admin-staff animate-in">
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Quản lý Nhân sự</h2>
        <p class="section-desc">Quản lý tài khoản và phân quyền cho đội ngũ quản trị viên.</p>
      </div>
      <button @click="openCreateModal" class="btn-create">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Thêm nhân sự
      </button>
    </div>

    <!-- Search -->
    <div class="search-bar ocean-card">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input v-model="searchQuery" @input="debouncedFetch" type="text" placeholder="Tìm kiếm theo tên hoặc email..." class="search-input" />
      <span class="user-count">{{ pagination ? pagination.total : 0 }} nhân sự</span>
    </div>

    <!-- Table -->
    <div class="ocean-card table-wrapper">
      <table class="user-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Họ tên</th>
            <th>Email</th>
            <th>Tham gia</th>
            <th class="status-th">Trạng thái</th>
            <th class="actions-th">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="staff.length === 0">
            <td colspan="6" class="empty-cell">Chưa có nhân sự nào.</td>
          </tr>
          <tr v-for="member in staff" :key="member.user_id || member.admin_id" class="user-row" :class="{'row-inactive': member.status === 'inactive' || member.status === 0}">
            <td class="id-cell">#{{ member.user_id || member.admin_id }}</td>
            <td>
              <div class="user-info-cell">
                <div class="avatar-circle" :class="'avatar-' + member.role">{{ (member.full_name || '?')[0].toUpperCase() }}</div>
                <span class="user-name">{{ member.full_name || '—' }}</span>
              </div>
            </td>
            <td class="email-cell">{{ member.email }}</td>
            <td>
              <select 
                :value="member.role" 
                @change="updateRole(member.admin_id, $event.target.value)"
                class="role-select"
                :class="'role-' + member.role"
                :disabled="member.email === 'admin123@gmail.com'"
              >
                <option value="admin">Quản trị viên</option>
                <option value="staff">Nhân viên kho (Staff)</option>
                <option value="seller">Nhân viên bán hàng (Seller)</option>
              </select>
            </td>
            <td class="date-cell">{{ formatDate(member.created_at) }}</td>
            <td class="status-cell">
              <label v-if="member.email !== 'admin123@gmail.com'" class="staff-toggle" :title="member.status === 'active' || member.status === 1 ? 'Đang hoạt động' : 'Tạm khóa'">
                <input type="checkbox" :checked="member.status === 'active' || member.status === 1" @change="toggleStatus(member)" />
                <span class="staff-slider"></span>
              </label>
              <span v-else class="super-badge">Vĩnh viễn</span>
            </td>
            <td class="actions-cell">
              <button v-if="member.email !== 'admin123@gmail.com'" @click="openDeleteConfirm(member)" class="btn-delete" title="Xóa nhân sự">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"></path>
                  <line x1="10" y1="11" x2="10" y2="17"></line>
                  <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
              </button>
              <div v-else class="super-lock">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d32f2f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination-controls">
          <button :disabled="currentPage === 1" @click="changePage(currentPage - 1)" class="btn-page">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
              Trước
          </button>
          <div class="page-numbers">
              <button 
                  v-for="page in pagination.last_page" 
                  :key="page" 
                  @click="changePage(page)" 
                  class="btn-page-number" 
                  :class="{'active': currentPage === page}"
              >
                  {{ page }}
              </button>
          </div>
          <button :disabled="currentPage === pagination.last_page" @click="changePage(currentPage + 1)" class="btn-page">
              Sau
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
      </div>
    </div>

    <!-- ===== MODAL: Thêm nhân sự ===== -->
    <Teleport to="body">
    <Transition name="staff-modal">
      <div v-if="showCreateModal" class="staff-modal-overlay" @click.self="showCreateModal = false">
        <div class="staff-modal-box">
          <div class="staff-modal-head">
            <h3>Thêm nhân sự mới</h3>
            <button class="staff-btn-close" @click="showCreateModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form @submit.prevent="createStaff" class="staff-modal-body">
            <div class="staff-form-group">
              <label>Họ tên <span class="staff-required">*</span></label>
              <input v-model="newStaff.full_name" type="text" class="staff-form-control" placeholder="VD: Nguyễn Văn A" autocomplete="off" />
            </div>
            <div class="staff-form-group">
              <label>Email <span class="staff-required">*</span></label>
              <input v-model="newStaff.email" type="email" class="staff-form-control" placeholder="VD: admin@ocean.vn" autocomplete="off" />
            </div>
            <div class="staff-form-row">
              <div class="staff-form-group">
                <label>Mật khẩu <span class="staff-required">*</span></label>
                <input v-model="newStaff.password" type="password" class="staff-form-control" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password" />
              </div>
              <div class="staff-form-group">
                <label>Vai trò</label>
                <select v-model="newStaff.role" class="staff-form-control staff-form-select">
                  <option value="staff">Nhân viên kho (Staff)</option>
                  <option value="admin">Quản trị viên (Admin)</option>
                  <option value="seller">Nhân viên bán hàng (Seller)</option>
                </select>
              </div>
            </div>

            <div v-if="formError" class="staff-form-error">
              {{ formError }}
            </div>

            <div class="staff-modal-footer">
              <button type="button" @click="showCreateModal = false" class="staff-btn-outline">Hủy bỏ</button>
              <button type="submit" class="staff-btn-primary" :disabled="isSubmitting">
                <svg v-if="!isSubmitting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span v-if="isSubmitting" class="staff-spinner-sm"></span>
                {{ isSubmitting ? 'Đang tạo...' : 'Tạo tài khoản' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- ===== MODAL: Xác nhận xóa ===== -->
    <Teleport to="body">
    <Transition name="staff-modal">
      <div v-if="showDeleteModal" class="staff-modal-overlay" @click.self="showDeleteModal = false">
        <div class="staff-modal-box" style="max-width:440px">
          <div class="staff-modal-head staff-modal-head-danger">
            <h3><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Xóa nhân sự?</h3>
            <button class="staff-btn-close" @click="showDeleteModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="staff-modal-body">
            <p>Bạn có chắc chắn muốn xóa tài khoản <strong>{{ deletingMember?.full_name }}</strong>?</p>
            <p class="staff-text-hint">Hành động này không thể hoàn tác.</p>
            <div class="staff-modal-footer">
              <button type="button" @click="showDeleteModal = false" class="staff-btn-outline">Giữ lại</button>
              <button type="button" @click="confirmDelete" class="staff-btn-danger">
                Đồng ý xóa
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- ===== TOAST ===== -->
    <Teleport to="body">
    <Transition name="staff-toast">
      <div v-if="toastVisible" class="staff-toast" :class="'staff-toast-' + toast.type">
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
  background: var(--primary); color: white; border: none;
  padding: 10px 20px; border-radius: 10px;
  font-weight: 600; font-size: 0.85rem; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.25);
  font-family: var(--font-inter);
}
.btn-create:hover { background: #d82f65; transform: translateY(-1px); }

/* ===== Search ===== */
.search-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; margin-bottom: 16px;
}
.search-bar svg { color: var(--text-light); flex-shrink: 0; }
.search-input { flex: 1; background: transparent; border: none; outline: none; font-size: 0.9rem; color: var(--text-main); font-family: var(--font-inter); }
.search-input::placeholder { color: var(--text-light); }
.user-count { font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; background: var(--ocean-deepest); padding: 4px 10px; border-radius: 20px; }

/* ===== Table ===== */
.table-wrapper { overflow-x: auto; }
.user-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.user-table th {
  text-align: left; padding: 14px 16px; font-weight: 700; font-size: 0.72rem;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--text-muted); border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.user-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.user-row { transition: background 0.15s; }
.user-row:hover { background: var(--hover-bg); }
.id-cell { color: var(--text-light); font-weight: 700; font-size: 0.8rem; }
.email-cell { color: var(--primary); font-weight: 500; }
.date-cell { color: var(--text-muted); font-size: 0.8rem; }
.empty-cell { text-align: center; padding: 40px !important; color: var(--text-light); }

.user-info-cell { display: flex; align-items: center; gap: 12px; }
.user-name { font-weight: 600; color: var(--text-main); }
.avatar-circle {
  width: 36px; height: 36px; border-radius: 50%; color: white;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.avatar-admin { background: #d32f2f; }
.avatar-staff { background: var(--primary); }
.avatar-seller { background: #ef6c00; }

.role-select {
  padding: 6px 10px; border-radius: 8px; border: 1.5px solid var(--border-color);
  font-size: 0.8rem; font-weight: 600; cursor: pointer; background: var(--card-bg); outline: none;
  transition: all 0.2s; font-family: var(--font-inter);
}
.role-select:hover { border-color: var(--ocean-mid); }
.role-admin { color: #d32f2f; border-color: #ffcdd2; background: #ffebee; }
.role-staff { color: #1565c0; border-color: #bbdefb; background: #e3f2fd; }
.role-seller { color: #ef6c00; border-color: #ffe0b2; background: #fff3e0; }

.actions-th { text-align: center !important; }
.actions-cell { text-align: center; }
.status-th { text-align: center !important; }
.status-cell { text-align: center; }

/* Pagination Controls */
.pagination-controls {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid var(--border-color);
}
.btn-page {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--card-bg, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
.btn-page:hover:not(:disabled) { background: var(--hover-bg, #f4f6f8); border-color: var(--primary); color: var(--primary); }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--card-bg, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.btn-page-number:hover:not(.active) { background: var(--hover-bg, #f4f6f8); }
.btn-page-number.active {
    background: var(--primary); color: white; border-color: var(--primary);
}

/* Switch Toggle CSS */
.staff-toggle { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
.staff-toggle input { opacity: 0; width: 0; height: 0; }
.staff-slider {
  position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
  background-color: #ffcdd2; transition: .3s; border-radius: 24px;
}
.staff-slider:before {
  position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
  background-color: var(--card-bg); transition: .3s; border-radius: 50%;
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.staff-toggle input:checked + .staff-slider { background-color: var(--seafoam, #26a69a); }
.staff-toggle input:checked + .staff-slider:before { transform: translateX(20px); }

.row-inactive { opacity: 0.6; filter: grayscale(80%); }

.btn-delete {
  background: #ffebee; color: var(--coral); border: 1.5px solid #ffcdd2;
  width: 34px; height: 34px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s;
}
.btn-delete:hover { background: #ffcdd2; color: #c62828; border-color: #ef9a9a; }

.super-badge { background: #ffebee; color: #d32f2f; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; }
.super-lock { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: var(--surface-container); border: 1.5px dashed #ccc; border-radius: 8px; cursor: not-allowed; opacity: 0.7; }
</style>

<!-- Non-scoped styles for Teleported modals/toasts -->
<style>
/* ===== Staff Modal Overlay ===== */
.staff-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.staff-modal-box {
  width: 100%; max-width: 520px; padding: 0;
  background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px; overflow: hidden;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.staff-modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px; border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.staff-modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #102a43); }
.staff-modal-head-danger { background: #ffebee; }
.staff-modal-head-danger h3 { color: #c62828; }
.staff-btn-close {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted, #627d98); display: flex; align-items: center; justify-content: center;
  padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.staff-btn-close:hover { background: var(--hover-bg, #e6f4fa); color: var(--coral, #ef5350); }

.staff-modal-body { padding: 24px; }
.staff-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

/* Form */
.staff-form-group { margin-bottom: 16px; }
.staff-form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main, #102a43); margin-bottom: 8px; }
.staff-required { color: var(--coral, #ef5350); }
.staff-form-control {
  width: 100%; padding: 10px 14px; border-radius: 8px;
  border: 1px solid var(--border-color, #d9e8f0); background: var(--ocean-deepest, #f0f7fa);
  color: var(--text-main, #102a43); font-family: var(--font-inter, 'Inter', sans-serif);
  font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.staff-form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.staff-form-control::placeholder { color: var(--text-light, #9fb3c8); }
.staff-form-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 14px center;
}
.staff-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .staff-form-row { grid-template-columns: 1fr; } }

.staff-form-error {
  padding: 10px 14px; background: #ffebee; border: 1px solid #ffcdd2;
  border-radius: 8px; color: #c62828; font-size: 0.85rem; font-weight: 500;
  margin-bottom: 8px;
}
.staff-text-hint { color: var(--text-muted, #627d98); font-size: 0.85rem; margin-top: 8px; }

/* Buttons */
.staff-btn-outline {
  padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color, #d9e8f0);
  background: var(--card-bg); color: var(--text-main, #102a43); font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter, 'Inter', sans-serif);
}
.staff-btn-outline:hover { border-color: var(--ocean-mid, #b3e0f2); background: var(--ocean-deepest, #f0f7fa); }
.staff-btn-primary {
  padding: 10px 20px; border-radius: 8px; border: none;
  background: var(--primary); color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
  font-family: var(--font-inter, 'Inter', sans-serif);
}
.staff-btn-primary:hover { background: #d82f65; }
.staff-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.staff-btn-danger {
  padding: 10px 20px; border-radius: 8px; border: none;
  background: var(--coral, #ef5350); color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter, 'Inter', sans-serif);
}
.staff-btn-danger:hover { background: #e53935; }

.staff-spinner-sm {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff; border-radius: 50%; animation: staffSpin 0.6s linear infinite; display: inline-block;
}
@keyframes staffSpin { to { transform: rotate(360deg); } }

/* Toast */
.staff-toast {
  position: fixed; top: 24px; right: 24px; z-index: 2000;
  padding: 14px 22px; border-radius: 10px; color: #fff;
  font-size: 0.85rem; font-weight: 600;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}
.staff-toast-success { background: var(--seafoam, #26a69a); }
.staff-toast-error { background: var(--coral, #ef5350); }

/* Transitions */
.staff-modal-enter-active, .staff-modal-leave-active { transition: all 0.25s ease; }
.staff-modal-enter-from, .staff-modal-leave-to { opacity: 0; }
.staff-modal-enter-from .staff-modal-box, .staff-modal-leave-to .staff-modal-box { transform: scale(0.95) translateY(10px); }

.staff-toast-enter-active { transition: all 0.3s ease; }
.staff-toast-leave-active { transition: all 0.2s ease; }
.staff-toast-enter-from { opacity: 0; transform: translateX(40px); }
.staff-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
