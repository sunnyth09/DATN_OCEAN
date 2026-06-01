<template>
  <div class="profile-info">
    <div class="section-header">
      <h1 class="section-title">Thông tin tài khoản</h1>
      <p class="section-desc">Quản lý và cập nhật thông tin cá nhân của bạn</p>
    </div>

    <!-- Thông báo thành công toàn cục -->
    <div v-if="globalSuccess" class="alert alert-success">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      {{ globalSuccess }}
    </div>

    <!-- Thông báo lỗi toàn cục -->
    <div v-if="globalError" class="alert alert-error">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      {{ globalError }}
    </div>

    <!-- Card chỉnh sửa -->
    <div class="info-card">
      <form @submit.prevent="updateProfile" class="profile-form">

        <!-- Avatar Upload -->
        <div class="avatar-section">
          <div class="avatar-wrapper">
            <img
              :src="previewAvatar || avatarUrl"
              :alt="user.full_name"
              class="avatar-img"
              @error="onAvatarError"
            />
            <label for="avatar-input" class="avatar-upload-btn" title="Đổi ảnh đại diện">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </label>
            <input type="file" id="avatar-input" accept="image/jpeg,image/png,image/gif,image/jpg" class="sr-only" @change="onAvatarChange" />
          </div>
          <div class="avatar-info">
            <h4>Ảnh đại diện</h4>
            <p>JPG, PNG hoặc GIF. Tối đa 2MB.</p>
            <span v-if="errors.avatar" class="error-text">{{ errors.avatar[0] }}</span>
          </div>
        </div>

        <div class="divider"></div>

        <!-- Form fields -->
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">
              Họ và tên <span class="required" v-if="isEditing">*</span>
            </label>
            <input
              type="text"
              v-model="form.full_name"
              class="form-input"
              :class="{ 'form-input--error': errors.full_name, 'form-input--disabled': !isEditing }"
              :disabled="!isEditing"
              placeholder="Nhập họ và tên"
              maxlength="120"
              required
            />
            <span v-if="errors.full_name" class="error-text">{{ errors.full_name[0] }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">
              Email <span class="label-hint">(Không thể thay đổi)</span>
            </label>
            <input type="email" :value="user.email" class="form-input form-input--disabled" disabled />
          </div>

          <div class="form-group">
            <label class="form-label">Số điện thoại</label>
            <input
              type="tel"
              v-model="form.phone"
              class="form-input"
              :class="{ 'form-input--error': errors.phone, 'form-input--disabled': !isEditing }"
              :disabled="!isEditing"
              placeholder="Nhập số điện thoại"
              maxlength="20"
            />
            <span v-if="errors.phone" class="error-text">{{ errors.phone[0] }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Ngày sinh</label>
            <input
              type="date"
              v-model="form.date_of_birth"
              class="form-input"
              :class="{ 'form-input--error': errors.date_of_birth, 'form-input--disabled': !isEditing }"
              :disabled="!isEditing"
            />
            <span v-if="errors.date_of_birth" class="error-text">{{ errors.date_of_birth[0] }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Ngày tham gia</label>
            <input type="text" :value="formatDate(user.created_at)" class="form-input form-input--disabled" disabled />
          </div>

          <div class="form-group">
            <label class="form-label">Trạng thái tài khoản</label>
            <div class="status-wrapper">
              <span class="status-badge" :class="'status-badge--' + (user.status || 'active')">
                {{ statusLabel(user.status) }}
              </span>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button v-if="!isEditing" type="button" class="btn-primary" @click.prevent="isEditing = true">Sửa thông tin</button>
          <template v-else>
            <button type="button" class="btn-outline" @click="cancelEdit" style="margin-right: 12px;">Hủy</button>
            <button type="submit" class="btn-primary" :disabled="loading || !isChanged">
              <span v-if="loading" class="spinner"></span>
              <span v-else>Lưu thay đổi</span>
            </button>
          </template>
        </div>
      </form>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
      <div class="stat-card">
        <div class="stat-icon stat-icon--blue">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-number">0</span>
          <span class="stat-label">Đơn hàng</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--pink">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-number">0</span>
          <span class="stat-label">Yêu thích</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--green">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-number">{{ addressCount }}</span>
          <span class="stat-label">Địa chỉ</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/axios';

// Lấy base URL từ env (ví dụ: http://localhost:8383)
const BASE_URL = import.meta.env.VITE_BASE_URL || 'http://localhost:8383';
const router = useRouter();

// FIX M2: Allowed file types for avatar upload
const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
const MAX_AVATAR_SIZE = 2 * 1024 * 1024; // 2MB

// FIX M1: Vietnamese phone regex
const VN_PHONE_REGEX = /^(0[2-9]|\+84[2-9])[0-9]{8,9}$/;

const user = ref({});
const addressCount = ref(0);
const form = ref({ full_name: '', phone: '', date_of_birth: '' });
const avatarFile = ref(null);
const previewAvatar = ref(null);
const errors = ref({});
const globalError = ref('');
const globalSuccess = ref('');
const loading = ref(false);
const isEditing = ref(false);
const originalData = ref({ full_name: '', phone: '', date_of_birth: '' });

// Tính toán avatar URL đúng (xử lý Google URL, local URL, và fallback)
const avatarUrl = computed(() => {
  const path = user.value.avatar_url;
  if (!path) {
    const name = encodeURIComponent(user.value.full_name || 'User');
    return `https://ui-avatars.com/api/?name=${name}&background=4f46e5&color=fff&size=128`;
  }
  if (path.startsWith('http')) return path;
  return `${BASE_URL}${path}`;
});

const isChanged = computed(() => {
  if (avatarFile.value) return true;
  if (form.value.full_name !== originalData.value.full_name) return true;
  const curPhone = form.value.phone || '';
  const oldPhone = originalData.value.phone || '';
  if (curPhone !== oldPhone) return true;
  const curDob = form.value.date_of_birth || '';
  const oldDob = originalData.value.date_of_birth || '';
  return curDob !== oldDob;
});

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const statusLabel = (status) => {
  const map = { active: 'Đang hoạt động', inactive: 'Tạm khóa', banned: 'Bị cấm' };
  return map[status] || 'Đang hoạt động';
};

const cancelEdit = () => {
  isEditing.value = false;
  form.value.full_name = originalData.value.full_name;
  form.value.phone = originalData.value.phone;
  form.value.date_of_birth = originalData.value.date_of_birth;
  avatarFile.value = null;
  // FIX M3: Revoke old preview URL to prevent memory leak
  if (previewAvatar.value) {
    URL.revokeObjectURL(previewAvatar.value);
  }
  previewAvatar.value = null;
  errors.value = {};
  globalError.value = '';
  globalSuccess.value = '';
};

// FIX M10: Fallback khi avatar URL bị hỏng
const onAvatarError = (e) => {
  const name = encodeURIComponent(user.value.full_name || 'User');
  e.target.src = `https://ui-avatars.com/api/?name=${name}&background=4f46e5&color=fff&size=128`;
};

const onAvatarChange = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  // FIX M2: Validate file type bằng JS (không chỉ dựa vào accept attribute)
  if (!ALLOWED_AVATAR_TYPES.includes(file.type)) {
    globalError.value = 'Chỉ hỗ trợ ảnh định dạng JPG, PNG hoặc GIF.';
    setTimeout(() => globalError.value = '', 4000);
    e.target.value = '';
    return;
  }

  if (file.size > MAX_AVATAR_SIZE) {
    globalError.value = 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB.';
    setTimeout(() => globalError.value = '', 4000);
    e.target.value = '';
    return;
  }

  avatarFile.value = file;

  // FIX M3: Revoke URL cũ trước khi tạo mới
  if (previewAvatar.value) {
    URL.revokeObjectURL(previewAvatar.value);
  }
  previewAvatar.value = URL.createObjectURL(file);
  
  // Tự động chuyển sang chế độ Sửa khi người dùng chọn xong ảnh
  isEditing.value = true;
};

const syncUser = (data) => {
  user.value = data;
  form.value.full_name = data.full_name || '';
  form.value.phone     = data.phone     || '';
  
  // Convert UTC timestamp back to Local Date properly
  let dob = data.date_of_birth || '';
  if (dob) {
    if (dob.includes('T')) {
      const d = new Date(dob);
      dob = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
  }
  form.value.date_of_birth = dob;
  
  originalData.value   = { full_name: data.full_name || '', phone: data.phone || '', date_of_birth: dob };
};

// FIX M1: Client-side validation trước khi submit
const validateForm = () => {
  const errs = {};
  const name = (form.value.full_name || '').trim();
  if (!name) {
    errs.full_name = ['Họ và tên là bắt buộc.'];
  } else if (name.length > 120) {
    errs.full_name = ['Họ và tên không được dài quá 120 ký tự.'];
  }

  const phone = (form.value.phone || '').trim();
  if (phone && !VN_PHONE_REGEX.test(phone)) {
    errs.phone = ['Số điện thoại không đúng định dạng Việt Nam (vd: 0912345678).'];
  }

  const dob = form.value.date_of_birth;
  if (dob) {
    const dobDate = new Date(dob);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (dobDate >= today) {
      errs.date_of_birth = ['Ngày sinh phải trước ngày hôm nay.'];
    } else if (dobDate.getFullYear() < 1900) {
      errs.date_of_birth = ['Ngày sinh không hợp lệ.'];
    }
  }

  return errs;
};

const updateProfile = async () => {
  // FIX M1: Validate frontend trước khi gửi request
  const validationErrors = validateForm();
  if (Object.keys(validationErrors).length > 0) {
    errors.value = validationErrors;
    return;
  }

  loading.value     = true;
  errors.value      = {};
  globalError.value = '';
  globalSuccess.value = '';

  const phoneRegex = /^(0|\+84)(3|5|7|8|9)[0-9]{8}$/;
  if (form.value.phone && !phoneRegex.test(form.value.phone.trim())) {
    errors.value.phone = ['Số điện thoại không hợp lệ (phải là số hợp lệ tại Việt Nam).'];
    loading.value = false;
    return;
  }

  if (form.value.date_of_birth) {
    const dob = new Date(form.value.date_of_birth);
    const now = new Date();
    dob.setHours(0, 0, 0, 0);
    now.setHours(0, 0, 0, 0);
    
    if (dob > now) {
      errors.value.date_of_birth = ['Ngày sinh không thể vượt quá ngày hiện tại.'];
      loading.value = false;
      return;
    }
    
    if (user.value?.created_at) {
      const joinDate = new Date(user.value.created_at);
      joinDate.setHours(0, 0, 0, 0);
      if (dob > joinDate) {
        errors.value.date_of_birth = ['Ngày sinh không thể vượt quá thời gian tham gia hệ thống.'];
        loading.value = false;
        return;
      }
    }
  }

  const formData = new FormData();
  formData.append('full_name', form.value.full_name.trim());
  formData.append('phone', form.value.phone || '');
  formData.append('date_of_birth', form.value.date_of_birth || '');
  if (avatarFile.value) {
    formData.append('avatar', avatarFile.value);
  }

  try {
    const res = await api.post('/profile', formData);
    globalSuccess.value = res.data.message || 'Cập nhật tài khoản thành công!';
    setTimeout(() => globalSuccess.value = '', 4000);

    syncUser(res.data.data);
    sessionStorage.setItem('user', JSON.stringify(res.data.data));
    window.dispatchEvent(new Event('user-updated'));

    avatarFile.value   = null;
    // FIX M3: Revoke preview URL
    if (previewAvatar.value) {
      URL.revokeObjectURL(previewAvatar.value);
    }
    previewAvatar.value = null;
    isEditing.value = false;
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {};
    } else {
      globalError.value = err.response?.data?.message || 'Đã xảy ra lỗi, vui lòng thử lại.';
    }
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  // Hiện data từ sessionStorage ngay (không chờ API)
  const cached = sessionStorage.getItem('user');
  if (cached) {
    try { syncUser(JSON.parse(cached)); } catch (_) {}
  }

  // Lấy dữ liệu mới nhất từ server
  try {
    const res = await api.get('/me');
    const userData = res.data?.user || res.data;
    if (userData) {
      syncUser(userData);
      sessionStorage.setItem('user', JSON.stringify(userData));
    }
  } catch (e) {
    // FIX M9: Xử lý lỗi API /me — redirect login nếu 401, hiện warning nếu lỗi khác
    if (e.response?.status === 401) {
      sessionStorage.removeItem('auth_token');
      sessionStorage.removeItem('user');
      router.push({ name: 'login', query: { redirect: '/profile' } });
      return;
    }
    if (!cached) {
      globalError.value = 'Không thể tải thông tin tài khoản. Vui lòng thử lại sau.';
    }
  }

  // Đếm địa chỉ
  try {
    const res = await api.get('/profile/addresses');
    addressCount.value = Array.isArray(res.data?.data) ? res.data.data.length : 0;
  } catch (_) {}
});

// FIX M3: Cleanup object URL khi component unmount
onUnmounted(() => {
  if (previewAvatar.value) {
    URL.revokeObjectURL(previewAvatar.value);
  }
});
</script>

<style scoped>
.profile-info {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Header */
.section-header { margin-bottom: 4px; }
.section-title  { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.section-desc   { font-size: 0.875rem; color: #6b7280; margin: 4px 0 0; }

/* Alert */
.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
}
.alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }

/* Card */
.info-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
}
.profile-form { padding: 24px; }

/* Avatar */
.avatar-section {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 24px;
}
.avatar-wrapper {
  position: relative;
  width: 88px;
  height: 88px;
  flex-shrink: 0;
}
.avatar-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e5e7eb;
  background: #f3f4f6;
}
.avatar-upload-btn {
  position: absolute;
  bottom: 2px;
  right: 2px;
  background: #E63B6F ;
  color: #fff;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border: 2px solid #fff;
  transition: background 0.2s;
}
.avatar-upload-btn:hover { background: #b50c4d; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }

.avatar-info h4 { font-size: 0.95rem; font-weight: 600; color: #111827; margin: 0 0 4px; }
.avatar-info p  { font-size: 0.8rem; color: #9ca3af; margin: 0; }

/* Divider */
.divider { height: 1px; background: #f3f4f6; margin: 0 -24px 24px; }

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}
.required    { color: #ef4444; }
.label-hint  { font-size: 0.75rem; color: #9ca3af; font-weight: 400; }

.form-input {
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #111827;
  outline: none;
  transition: border 0.15s, box-shadow 0.15s;
  background: #fff;
}
.form-input:focus { border-color: #E63B6F; box-shadow: 0 0 0 3px rgba(230,59,111,0.12); }
.form-input--error { border-color: #ef4444; }
.form-input--disabled { background: #f9fafb; color: #6b7280; cursor: not-allowed; }

/* Status */
.status-wrapper { display: flex; align-items: center; height: 42px; }
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 14px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
}
.status-badge--active   { background: #ecfdf5; color: #059669; }
.status-badge--inactive { background: #fefce8; color: #ca8a04; }
.status-badge--banned   { background: #fef2f2; color: #dc2626; }

/* Actions */
.form-actions {
  margin-top: 24px;
  display: flex;
  justify-content: flex-end;
}
.btn-primary {
  padding: 10px 28px;
  background: #E63B6F;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 130px;
  transition: background 0.2s;
}
.btn-primary:hover:not(:disabled) { background: #cb184d; }
.btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }

.btn-outline {
  padding: 10px 28px;
  background: #fff;
  color: #4b5563;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.btn-outline:hover {
  background: #f9fafb;
  color: #111827;
  border-color: #9ca3af;
}

.spinner {
  width: 17px;
  height: 17px;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.error-text { font-size: 0.8rem; color: #ef4444; }

/* Quick Stats */
.quick-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}
.stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: box-shadow 0.2s, border-color 0.2s;
}
.stat-card:hover { border-color: #c7d2fe; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-icon--blue  { background: #eff6ff; color: #2563eb; }
.stat-icon--pink  { background: #fdf2f8; color: #ec4899; }
.stat-icon--green { background: #ecfdf5; color: #059669; }

.stat-info { display: flex; flex-direction: column; }
.stat-number { font-size: 1.5rem; font-weight: 700; color: #111827; line-height: 1; }
.stat-label  { font-size: 0.8rem; color: #6b7280; margin-top: 4px; }

@media (max-width: 640px) {
  .form-grid    { grid-template-columns: 1fr; }
  .quick-stats  { grid-template-columns: 1fr; }
  .profile-form { padding: 16px; }
  .divider      { margin: 0 -16px 20px; }
}
</style>
