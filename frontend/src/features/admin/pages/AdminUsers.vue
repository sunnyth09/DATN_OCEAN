<template>
  <div class="admin-users animate-in">
    <div class="page-header">
      <div class="header-info">
        <h1 class="page-title">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
          Quản Lý Khách Hàng
        </h1>
        <p class="page-subtitle">Quản lý tài khoản khách hàng và người bán hàng trên hệ thống.</p>
      </div>
      <button class="btn-primary" @click="openCreateModal">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Thêm Khách Hàng
      </button>
    </div>

    <!-- Filters & Search -->
    <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
      <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="searchQuery" @input="debouncedFetch" type="text" placeholder="Tìm kiếm theo tên, email hoặc SĐT..." class="search-input" />
      </div>
      <span class="table-count"><strong>{{ users.length }}</strong> khách hàng</span>
    </div>

    <!-- Table -->
    <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Họ tên</th>
              <th>Email</th>
              <th>SĐT</th>
              <th>Điểm thưởng</th>
              <th>Vai trò</th>
              <th>Trạng thái</th>
              <th>Ngày tạo</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="loading-cell"><div class="spinner"></div><p>Đang tải...</p></td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="9" class="empty-cell">
                <span class="empty-emoji">👥</span>
                <h3>Không tìm thấy khách hàng</h3>
              </td>
            </tr>
            <tr v-for="user in users" :key="user.user_id" v-else>
              <td><span class="badge-id">#{{ user.user_id }}</span></td>
              <td>
                <div class="user-info-cell">
                  <div class="avatar-circle">{{ (user.full_name || '?')[0].toUpperCase() }}</div>
                  <span class="prod-name">{{ user.full_name || '—' }}</span>
                </div>
              </td>
              <td class="email-cell">{{ user.email }}</td>
              <td>{{ user.phone || '—' }}</td>
              <td>
                <strong style="color: #E63B6F; font-size: 0.9rem">{{ user.reward_points ?? 0 }}</strong>
              </td>
              <td>
                <select :value="user.role" @change="updateRole(user.user_id, $event.target.value)" class="role-select" :class="'role-' + user.role">
                  <option value="customer">Khách hàng</option>
                  <option value="seller">Người bán</option>
                  <option value="staff">Nhân viên</option>
                  <option value="admin">Quản trị viên</option>
                </select>
              </td>
              <td>
                <select :value="user.status" @change="updateStatus(user.user_id, $event.target.value)" class="status-select" :class="'status-' + user.status">
                  <option value="active">Hoạt động</option>
                  <option value="inactive">Không hoạt động</option>
                  <option value="banned">Bị cấm</option>
                </select>
              </td>
              <td style="color:var(--text-muted); font-size:0.8rem">{{ formatDate(user.created_at) }}</td>
              <td>
                <div class="actions-cell">
                  <button class="btn-icon view" title="Xem chi tiết" @click="viewUser(user.user_id)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                  <button class="btn-icon edit" title="Sửa" @click="openEditModal(user)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <button class="btn-icon del" title="Xóa" @click="confirmDelete(user)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                  </button>
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
    </div>

    <!-- ===== Modal: Tạo / Sửa User (giống AdminProduct) ===== -->
    <Teleport to="body">
      <div class="qv-backdrop" v-if="isFormModalOpen" @click.self="closeFormModal">
        <div class="qv-modal animate-in" style="max-width:520px">
          <div class="qv-header">
            <h2>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              {{ isEditing ? 'Sửa Khách Hàng' : 'Thêm Khách Hàng Mới' }}
            </h2>
            <button class="qv-close" @click="closeFormModal">×</button>
          </div>
          <form @submit.prevent="handleSubmit" novalidate class="qv-body">
            <div class="qv-form-group">
              <label class="qv-form-label">Họ tên <span style="color:var(--coral)">*</span></label>
              <input v-model="form.full_name" type="text" class="qv-form-input" :class="{'is-invalid': errors.full_name}" placeholder="Nguyễn Văn A" />
              <span v-if="errors.full_name" class="field-error">{{ errors.full_name }}</span>
            </div>
            <div class="qv-meta" style="margin-bottom:14px">
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Email <span style="color:var(--coral)">*</span></label>
                <input v-model="form.email" type="email" class="qv-form-input" :class="{'is-invalid': errors.email}" placeholder="email@example.com" />
                <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
              </div>
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Số điện thoại</label>
                <input v-model="form.phone" type="text" class="qv-form-input" :class="{'is-invalid': errors.phone}" placeholder="0901234567" />
                <span v-if="errors.phone" class="field-error">{{ errors.phone }}</span>
              </div>
            </div>
            <div class="qv-form-group">
              <label class="qv-form-label">{{ isEditing ? 'Mật khẩu mới (bỏ trống = giữ nguyên)' : 'Mật khẩu' }} <span v-if="!isEditing" style="color:var(--coral)">*</span></label>
              <input v-model="form.password" type="password" class="qv-form-input" :class="{'is-invalid': errors.password}" placeholder="Tối thiểu 8 ký tự, chữ hoa, số, ký tự đặc biệt" />
              <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
            </div>
            <div class="qv-meta" style="margin-bottom:14px">
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Vai trò</label>
                <select v-model="form.role" class="qv-form-input">
                  <option value="customer">Khách hàng</option>
                  <option value="seller">Người bán</option>
                  <option value="staff">Nhân viên</option>
                  <option value="admin">Quản trị viên</option>
                </select>
              </div>
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Trạng thái</label>
                <select v-model="form.status" class="qv-form-input">
                  <option value="active">Hoạt động</option>
                  <option value="inactive">Không hoạt động</option>
                  <option value="banned">Bị cấm</option>
                </select>
              </div>
            </div>
            <!-- Inline error -->
            <div v-if="formError" class="form-error-box">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              {{ formError }}
            </div>
            <div class="qv-footer">
              <button type="submit" class="btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Đang lưu...' : (isEditing ? 'Cập nhật' : 'Tạo mới') }}
              </button>
              <button type="button" class="btn-outline" @click="closeFormModal">Hủy</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ===== Modal: Chi tiết User (giống Quick View sản phẩm) ===== -->
    <Teleport to="body">
      <div class="qv-backdrop" v-if="isDetailModalOpen" @click.self="closeDetailModal">
        <div class="qv-modal animate-in" style="max-width:620px">
          <div class="qv-header">
            <h2>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Chi Tiết Khách Hàng
            </h2>
            <button class="qv-close" @click="closeDetailModal">×</button>
          </div>

          <div v-if="detailLoading" class="qv-loading"><div class="spinner"></div><p>Đang tải...</p></div>

          <div class="qv-body" v-if="detailData && !detailLoading">
            <!-- Profile header -->
            <div class="qv-top" style="gap:16px; margin-bottom:20px; align-items:center">
              <div class="detail-avatar-lg">{{ (detailData.full_name || '?')[0].toUpperCase() }}</div>
              <div style="flex:1">
                <h3 class="qv-name" style="font-size:1.15rem; margin:0">{{ detailData.full_name }}</h3>
                <p class="qv-slug" style="margin:2px 0 0 0">{{ detailData.email }}</p>
              </div>
              <span class="badge-status" :class="detailData.status" style="font-size:0.72rem; padding:5px 12px">
                {{ detailData.status === 'active' ? 'Hoạt động' : detailData.status === 'banned' ? 'Bị cấm' : 'Không hoạt động' }}
              </span>
            </div>

            <!-- Info grid (giống qv-meta AdminProduct) -->
            <div class="qv-meta" style="margin-bottom:20px">
              <div class="qv-meta-item">
                <span class="qv-meta-label">ID</span>
                <span class="qv-meta-value">#{{ detailData.user_id }}</span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Điểm thưởng</span>
                <span class="qv-meta-value" style="color: #E63B6F; font-weight: 800">{{ detailData.reward_points ?? 0 }} điểm</span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Số điện thoại</span>
                <span class="qv-meta-value">{{ detailData.phone || '—' }}</span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Vai trò</span>
                <span class="badge-type" :class="detailData.role">
                  {{ detailData.role === 'admin' ? 'Quản trị viên' : (detailData.role === 'staff' ? 'Nhân viên' : (detailData.role === 'seller' ? 'Người bán' : 'Khách hàng')) }}
                </span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Ngày tạo</span>
                <span class="qv-meta-value">{{ formatDate(detailData.created_at) }}</span>
              </div>
            </div>

            <!-- Điều Chỉnh Điểm Thưởng section -->
            <div class="qv-section">
              <h4 class="qv-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                Điều Chỉnh Điểm Thưởng
              </h4>
              <div class="points-adjustment-box">
                <div class="adjust-inputs">
                  <div class="adj-field">
                    <label class="adj-label">Số điểm (Cộng: dương, Trừ: âm)</label>
                    <input v-model.number="adjustForm.delta" type="number" placeholder="VD: 100 hoặc -50" class="qv-form-input adj-input" />
                  </div>
                  <div class="adj-field" style="flex: 2">
                    <label class="adj-label">Lý do điều chỉnh</label>
                    <input v-model="adjustForm.description" type="text" placeholder="Nhập lý do điều chỉnh điểm..." class="qv-form-input adj-input" />
                  </div>
                </div>
                <button type="button" class="btn-primary btn-adjust animate-in" style="margin-top:10px" @click="submitPointsAdjustment" :disabled="isAdjusting">
                  {{ isAdjusting ? 'Đang lưu...' : 'Xác Nhận Thay Đổi' }}
                </button>
              </div>
            </div>

            <!-- Địa chỉ section -->
            <div class="qv-section">
              <h4 class="qv-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Địa chỉ ({{ detailAddresses.length }})
              </h4>
              <div v-if="detailAddresses.length === 0" class="qv-empty">Chưa có địa chỉ nào</div>
              <div v-else class="addr-list">
                <div v-for="addr in detailAddresses" :key="addr.id" class="addr-card">
                  <div class="addr-top">
                    <strong>{{ addr.recipient_name }}</strong>
                    <span style="color:var(--text-muted)">{{ addr.phone }}</span>
                    <span v-if="addr.is_default" class="badge-status active" style="font-size:0.6rem; padding:2px 8px; margin-left:auto">Mặc định</span>
                  </div>
                  <div class="addr-text">{{ addr.address_line }}, {{ addr.ward }}, {{ addr.province }}</div>
                </div>
              </div>
            </div>

            <!-- Mã giảm giá section -->
            <div class="qv-section">
              <h4 class="qv-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 12V8H6a2 2 0 01-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 00-2 2c0 1.1.9 2 2 2h4v-4h-4z"/></svg>
                Mã giảm giá đã lưu ({{ detailCoupons.length }})
              </h4>
              <div v-if="detailCoupons.length === 0" class="qv-empty">Chưa lưu mã nào</div>
              <div v-else class="qv-variants-table-wrap">
                <table class="qv-variants-table">
                  <thead>
                    <tr>
                      <th>Mã</th>
                      <th>Loại</th>
                      <th>Giá trị</th>
                      <th>Đã dùng</th>
                      <th>Ngày lưu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="cp in detailCoupons" :key="cp.code">
                      <td><code>{{ cp.code }}</code></td>
                      <td><span class="badge-type" :class="cp.type">{{ cp.type }}</span></td>
                      <td class="qv-v-price">{{ cp.type === 'percent' ? cp.value + '%' : formatCurrency(cp.value) }}</td>
                      <td><span class="badge-stock" :class="{ good: cp.used_count === 0, low: cp.used_count > 0 }">{{ cp.used_count }}</span></td>
                      <td style="font-size:0.78rem; color:var(--text-muted)">{{ formatDate(cp.saved_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Footer -->
            <div class="qv-footer">
              <button class="btn-primary" @click="closeDetailModal(); openEditModal(detailData)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Chỉnh Sửa
              </button>
              <button class="btn-outline" @click="closeDetailModal">Đóng</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== Modal: Xác nhận xóa ===== -->
    <Teleport to="body">
      <div class="qv-backdrop" v-if="isDeleteModalOpen" @click.self="isDeleteModalOpen = false">
        <div class="qv-modal animate-in" style="max-width:420px">
          <div class="qv-header" style="background:#dc3545; color:#fff; border-radius:16px 16px 0 0">
            <h2 style="color:#fff">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Xóa Khách Hàng?
            </h2>
            <button class="qv-close" style="color:#fff" @click="isDeleteModalOpen = false">×</button>
          </div>
          <div class="qv-body" style="text-align:center;padding:28px">
            <p style="font-size:0.9rem; color:var(--text-main)">Bạn có chắc muốn xóa <strong>{{ deleteTarget?.full_name }}</strong>?</p>
            <p style="font-size:0.78rem; color:var(--text-muted)">{{ deleteTarget?.email }}</p>
            <p style="font-size:0.75rem; color:var(--coral); margin-top:8px">Thao tác này sẽ xóa mềm — có thể khôi phục sau.</p>
          </div>
          <div class="qv-footer" style="padding: 0 24px 20px; justify-content: center;">
            <button class="btn-del" @click="handleDelete" :disabled="isSubmitting">{{ isSubmitting ? 'Đang xóa...' : 'Xóa' }}</button>
            <button class="btn-outline" @click="isDeleteModalOpen = false">Hủy</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
      <div class="toast-container position-fixed admin-users-toast-host" style="z-index: 1080">
        <div class="toast align-items-center border-0" :class="toast.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="usersToast" role="alert">
          <div class="d-flex">
            <div class="toast-body">{{ toast.message }}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import api from '@/axios';
import { Toast } from 'bootstrap';

const users = ref([]);
const loading = ref(true);
const searchQuery = ref('');
let searchTimer = null;
const pagination = ref(null);
const currentPage = ref(1);
const toast = ref({ message: '', type: 'success' });

const isFormModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const isSubmitting = ref(false);
const formError = ref('');
const errors = ref({});
const form = ref({ full_name: '', email: '', phone: '', password: '', role: 'customer', status: 'active' });

const isDetailModalOpen = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);
const detailAddresses = ref([]);
const detailCoupons = ref([]);

const adjustForm = ref({ delta: null, description: '' });
const isAdjusting = ref(false);

const submitPointsAdjustment = async () => {
  if (!adjustForm.value.delta) {
    showToast('Vui lòng nhập số điểm!', 'danger');
    return;
  }
  if (!adjustForm.value.description.trim()) {
    showToast('Vui lòng nhập lý do điều chỉnh!', 'danger');
    return;
  }

  isAdjusting.value = true;
  try {
    const res = await api.post(`/admin/loyalty/users/${detailData.value.user_id}/adjust`, {
      delta: adjustForm.value.delta,
      description: adjustForm.value.description.trim(),
    });
    
    if (res.data.status === 'success') {
      showToast('Điều chỉnh điểm thưởng thành công!');
      detailData.value.reward_points = res.data.data.balance_after;
      adjustForm.value.delta = null;
      adjustForm.value.description = '';
      fetchUsers();
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi điều chỉnh điểm!', 'danger');
  } finally {
    isAdjusting.value = false;
  }
};

const isDeleteModalOpen = ref(false);
const deleteTarget = ref(null);

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('usersToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const debouncedFetch = () => { 
  clearTimeout(searchTimer); 
  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchUsers();
  }, 500); 
};

const changePage = (page) => {
  if (page < 1 || (pagination.value && page > pagination.value.last_page)) return;
  currentPage.value = page;
  fetchUsers();
};

const fetchUsers = async () => {
  try {
    loading.value = true;
    const response = await api.get('/admin/users', { params: { search: searchQuery.value, per_page: 10, page: currentPage.value } });
    users.value = response.data.data;
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    };
  } catch (error) {
    showToast('Lỗi tải danh sách!', 'danger');
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  isEditing.value = false; editingId.value = null; formError.value = ''; errors.value = {};
  form.value = { full_name: '', email: '', phone: '', password: '', role: 'customer', status: 'active' };
  isFormModalOpen.value = true;
};

const openEditModal = (user) => {
  isEditing.value = true; editingId.value = user.user_id; formError.value = ''; errors.value = {};
  form.value = { full_name: user.full_name || '', email: user.email || '', phone: user.phone || '', password: '', role: user.role || 'customer', status: user.status || 'active' };
  isFormModalOpen.value = true;
};

const closeFormModal = () => { isFormModalOpen.value = false; };

const handleSubmit = async () => {
  formError.value = '';
  errors.value = {};

  // Frontend validation
  let hasError = false;
  if (!form.value.full_name.trim()) { errors.value.full_name = 'Vui lòng nhập họ tên.'; hasError = true; }
  if (!form.value.email.trim()) { errors.value.email = 'Vui lòng nhập email.'; hasError = true; }
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) { errors.value.email = 'Email không hợp lệ.'; hasError = true; }
  
  if (!isEditing.value && !form.value.password) { errors.value.password = 'Vui lòng nhập mật khẩu.'; hasError = true; }

  if (hasError) return;

  isSubmitting.value = true;
  try {
    const payload = { ...form.value };
    if (isEditing.value && !payload.password) delete payload.password;
    if (isEditing.value) {
      await api.put(`/admin/users/${editingId.value}`, payload);
      showToast('Cập nhật thành công!');
    } else {
      await api.post('/admin/users', payload);
      showToast('Tạo khách hàng thành công!');
    }
    closeFormModal(); fetchUsers();
  } catch (error) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      // Backend validation errors mapped to fields
      const backendErrors = error.response.data.errors;
      for (const key in backendErrors) {
        errors.value[key] = backendErrors[key][0];
      }
      // formError.value = error.response.data.message || 'Vui lòng kiểm tra lại các trường nhập liệu!';
    } else {
      formError.value = error.response?.data?.message || 'Có lỗi xảy ra!';
    }
  } finally { isSubmitting.value = false; }
};

const viewUser = async (userId) => {
  isDetailModalOpen.value = true; detailLoading.value = true;
  detailData.value = null; detailAddresses.value = []; detailCoupons.value = [];
  try {
    const res = await api.get(`/admin/users/${userId}`);
    if (res.data.status === 'success') {
      detailData.value = res.data.data;
      detailAddresses.value = res.data.addresses || [];
      detailCoupons.value = res.data.saved_coupons || [];
    }
  } catch (e) {
    showToast('Lỗi tải chi tiết!', 'danger');
    isDetailModalOpen.value = false;
  } finally { detailLoading.value = false; }
};

const closeDetailModal = () => {
  isDetailModalOpen.value = false;
  adjustForm.value = { delta: null, description: '' };
};

const confirmDelete = (user) => { deleteTarget.value = user; isDeleteModalOpen.value = true; };

const handleDelete = async () => {
  isSubmitting.value = true;
  try {
    await api.delete(`/admin/users/${deleteTarget.value.user_id}`);
    showToast('Đã xóa khách hàng!');
    isDeleteModalOpen.value = false; fetchUsers();
  } catch (e) { showToast(e.response?.data?.message || 'Lỗi xóa!', 'danger'); }
  finally { isSubmitting.value = false; }
};

const updateRole = async (userId, newRole) => {
  try { const r = await api.put(`/admin/users/${userId}/role`, { role: newRole }); showToast(r.data.message); fetchUsers(); }
  catch (e) { showToast(e.response?.data?.message || 'Lỗi!', 'danger'); fetchUsers(); }
};

const updateStatus = async (userId, newStatus) => {
  try { const r = await api.put(`/admin/users/${userId}/status`, { status: newStatus }); showToast(r.data.message); fetchUsers(); }
  catch (e) { showToast(e.response?.data?.message || 'Lỗi!', 'danger'); fetchUsers(); }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatCurrency = (val) => {
  if (!val) return '0₫';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

onMounted(fetchUsers);
</script>

<style scoped>
/* Toast */
:global(.admin-users-toast-host) {
  top: 20px !important;
  right: 20px !important;
  left: auto !important;
  padding: 0 !important;
}

/* Header */
.page-header {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;
}
.page-title {
  font-size: 1.5rem; font-weight: 800; color: var(--text-main);
  display: flex; align-items: center; gap: 12px;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

/* Buttons */
.btn-primary {
  display: flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 8px; border: none;
  background: var(--primary); color: white; font-family: var(--font-inter); font-size: 0.85rem;
  font-weight: 700; cursor: pointer; transition: all 0.2s; text-decoration: none;
  box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover { background: var(--ocean-bright); transform: translateY(-2px); box-shadow: 0 6px 14px rgba(3, 169, 244, 0.3); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.btn-outline {
  padding: 10px 22px; border-radius: 8px; border: 1px solid var(--border-color);
  background: var(--card-bg); color: var(--text-muted); font-family: var(--font-inter); font-size: 0.85rem;
  font-weight: 700; cursor: pointer; transition: all 0.2s; text-decoration: none;
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.btn-del {
  padding: 10px 28px; border-radius: 8px; border: none; background: #dc3545; color: #fff;
  font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
}
.btn-del:hover { background: #c82333; }

/* Search */
.filters-bar { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; margin-bottom: 24px; }
.search-box {
  display: flex; align-items: center; gap: 10px; background: var(--ocean-deepest);
  border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 16px; flex: 1; max-width: 400px;
}
.search-box:focus-within { border-color: var(--primary); background: var(--card-bg); box-shadow: 0 0 0 3px rgba(230, 59, 111,0.1); }
.search-box svg { color: var(--text-light); }
.search-input { background: none; border: none; outline: none; color: var(--text-main); font-family: var(--font-inter); font-size: 0.9rem; width: 100%; }
.table-count { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
.table-count strong { color: var(--text-main); font-weight: 800; }

/* Pagination Controls */
.pagination-controls {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid var(--border-color);
}
.btn-page {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
.btn-page:hover:not(:disabled) { background: var(--hover-bg, #f4f6f8); border-color: var(--primary, #e63b6f); color: var(--primary, #e63b6f); }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.btn-page-number:hover:not(.active) { background: var(--hover-bg, #f4f6f8); }
.btn-page-number.active {
    background: var(--primary, #e63b6f); color: white; border-color: var(--primary, #e63b6f);
}

/* Table */
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
  padding: 12px 12px; font-size: 0.7rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 1px solid var(--border-color);
  background: var(--ocean-deepest); white-space: nowrap;
}
.data-table td { padding: 12px 12px; border-bottom: 1px solid var(--border-color); transition: background 0.15s; vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--hover-bg); }

.badge-id { padding: 3px 6px; border-radius: 5px; font-size: 0.75rem; font-weight: 700; background: rgba(230, 59, 111,0.1); color: var(--primary); }
.user-info-cell { display: flex; align-items: center; gap: 8px; }
.avatar-circle {
  width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: white;
  display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.72rem; flex-shrink: 0;
}
.prod-name { font-size: 0.82rem; font-weight: 700; color: var(--text-main); white-space: nowrap; }
.email-cell { color: #1d4ed8; font-size: 0.8rem; }

.role-select, .status-select {
  padding: 3px 6px; border-radius: 5px; border: 1px solid #eee; font-size: 0.75rem; font-weight: 500; cursor: pointer; background: var(--card-bg); outline: none;
}
.role-customer { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.role-seller { color: #ef6c00; border-color: #ffe0b2; background: #fff3e0; }
.status-active { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.status-inactive { color: #757575; border-color: #e0e0e0; background: #f5f5f5; }
.status-banned { color: #d32f2f; border-color: #ffcdd2; background: #ffebee; }

/* Action buttons */
.actions-cell { display: flex; gap: 6px; }
.btn-icon {
  width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color);
  background: var(--ocean-deepest); color: var(--text-muted); cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.btn-icon:hover { border-color: currentColor; background: var(--card-bg); }
.edit:hover { color: var(--seafoam); border-color: var(--seafoam); background: rgba(38,166,154,0.05); }
.del:hover { color: var(--coral); border-color: var(--coral); background: rgba(239,83,80,0.05); }
.view:hover { color: #8e24aa; border-color: #8e24aa; background: rgba(142,36,170,0.05); }

/* ===== Quick View Modal (shared with AdminProduct) ===== */
.qv-backdrop {
  position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
  background: rgba(0,0,0,0.55); display: flex; align-items: center; justify-content: center;
  z-index: 1000; backdrop-filter: blur(2px);
}
.qv-modal {
  background: var(--card-bg); border-radius: 16px; width: 94%; max-width: 900px;
  max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.qv-header {
  padding: 18px 24px; border-bottom: 1px solid var(--border-color);
  display: flex; justify-content: space-between; align-items: center;
  position: sticky; top: 0; background: var(--card-bg); z-index: 10; border-radius: 16px 16px 0 0;
}
.qv-header h2 {
  font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);
  display: flex; align-items: center; gap: 10px;
}
.qv-close {
  background: none; border: none; font-size: 1.6rem; line-height: 1;
  color: var(--text-muted); cursor: pointer; transition: 0.2s; padding: 0; width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center; border-radius: 8px;
}
.qv-close:hover { color: var(--coral); background: rgba(239,83,80,0.08); }
.qv-loading { padding: 60px 20px; text-align: center; color: var(--text-muted); }
.qv-body { padding: 24px; }

/* Inline error box */
.form-error-box {
  display: flex; align-items: center; gap: 8px; padding: 10px 14px; margin-bottom: 14px;
  background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
  color: #dc2626; font-size: 0.82rem; font-weight: 600;
  animation: shakeError 0.35s ease;
}
@keyframes shakeError { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }

.qv-top { display: flex; gap: 28px; margin-bottom: 24px; }
.qv-name { font-size: 1.35rem; font-weight: 800; color: var(--text-main); line-height: 1.35; margin: 0; }
.qv-slug { font-size: 0.8rem; color: var(--text-light); margin: -4px 0 0 0; }

.qv-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.qv-meta-item { display: flex; flex-direction: column; gap: 3px; padding: 8px 12px; background: var(--ocean-deepest, #f8fafc); border-radius: 8px; }
.qv-meta-label { font-size: 0.7rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }
.qv-meta-value { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }

.qv-section { margin-bottom: 20px; }
.qv-section-title {
  font-size: 0.9rem; font-weight: 800; color: var(--text-main);
  padding-bottom: 10px; border-bottom: 1px solid var(--border-color); margin-bottom: 12px;
  display: flex; align-items: center; gap: 8px;
}
.qv-empty { text-align: center; padding: 20px; color: var(--text-muted); font-size: 0.85rem; background: var(--ocean-deepest); border-radius: 8px; }

.qv-footer { display: flex; gap: 12px; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--border-color); }

/* Variants table (coupons table) */
.qv-variants-table-wrap { overflow-x: auto; }
.qv-variants-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
.qv-variants-table th { padding: 10px 12px; text-align: left; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest, #f8fafc); }
.qv-variants-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.qv-variants-table tbody tr:hover td { background: rgba(230, 59, 111,0.03); }
.qv-variants-table code { font-size: 0.78rem; background: var(--surface-container); padding: 2px 6px; border-radius: 4px; }
.qv-v-price { font-weight: 700; color: var(--seafoam); }

.badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
.badge-type.customer { background: rgba(38,166,154,0.15); color: #167a70; }
.badge-type.seller { background: rgba(255,167,38,0.15); color: #e65100; }
.badge-type.admin { background: rgba(3,169,244,0.15); color: #d82f65; }
.badge-type.staff { background: rgba(156,39,176,0.15); color: #7b1fa2; }
.badge-type.percent { background: rgba(239,83,80,0.15); color: #c62828; }
.badge-type.fixed { background: rgba(38,166,154,0.15); color: #167a70; }
.badge-type.free_ship { background: rgba(3,169,244,0.15); color: #d82f65; }

.badge-stock { padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; }
.badge-stock.good { background: rgba(38,166,154,0.15); color: #167a70; }
.badge-stock.low { background: rgba(255,167,38,0.15); color: #e65100; }

/* Detail avatar */
.detail-avatar-lg {
  width: 52px; height: 52px; border-radius: 50%; background: var(--primary); color: #fff;
  display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; flex-shrink: 0;
}

/* Addr cards */
.addr-list { display: flex; flex-direction: column; gap: 6px; }
.addr-card { padding: 10px 14px; border-radius: 8px; background: var(--ocean-deepest); border: 1px solid var(--border-color); font-size: 0.82rem; }
.addr-top { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.addr-text { color: var(--text-muted); font-size: 0.78rem; }

/* Form */
.qv-form-group { margin-bottom: 14px; }
.qv-form-label { display: block; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; }
.qv-form-input {
  width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid var(--border-color);
  font-size: 0.85rem; font-family: var(--font-inter); color: var(--text-main); box-sizing: border-box;
  outline: none; transition: all 0.2s; background: var(--ocean-deepest);
}
.qv-form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111,0.08); background: var(--card-bg); }
.qv-form-input.is-invalid { border-color: var(--coral); background: #fef2f2; }
.qv-form-input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(239,83,80,0.1); }
.field-error { display: block; color: var(--coral); font-size: 0.72rem; font-weight: 600; margin-top: 6px; animation: fadeSlideUp 0.2s ease; }
.loading-cell { text-align: center; padding: 60px 20px !important; color: var(--text-muted); }
.empty-cell { text-align: center; padding: 60px 20px !important; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-cell h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.spinner { width: 30px; height: 30px; border: 3px solid var(--border-color); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
.animate-in { animation: fadeSlideUp 0.35s ease both; }
@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

.points-adjustment-box {
  background: var(--ocean-deepest);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.adjust-inputs {
  display: flex;
  gap: 12px;
}
.adj-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}
.adj-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
}
.adj-input {
  background: var(--card-bg);
}
.btn-adjust {
  align-self: flex-end;
  padding: 8px 18px;
}

@media (max-width: 768px) {
  .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
  .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
  .qv-meta { grid-template-columns: 1fr; }
}
</style>
