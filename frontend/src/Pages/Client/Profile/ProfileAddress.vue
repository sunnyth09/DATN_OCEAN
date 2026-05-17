<template>
  <div class="profile-address">
    <div class="section-header">
      <div>
        <h1 class="section-title">Sổ địa chỉ</h1>
        <p class="section-desc">Quản lý địa chỉ giao hàng của bạn</p>
      </div>
      <button class="btn-add" @click="openAddForm">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Thêm địa chỉ
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <span>Đang tải địa chỉ...</span>
    </div>

    <!-- Address List -->
    <div v-else-if="addresses.length > 0" class="address-list">
      <div
        v-for="address in addresses"
        :key="address.address_id"
        class="address-card"
        :class="{ 'address-card--default': address.is_default }"
      >
        <div class="address-card-header">
          <div class="address-card-info">
            <div class="address-name-row">
              <h3 class="address-name">{{ address.recipient_name }}</h3>
              <span v-if="address.is_default" class="default-badge">Mặc định</span>
              <span class="type-badge" :class="'type-badge--' + address.address_type">
                {{ address.address_type === 'home' ? 'Nhà riêng' : address.address_type === 'office' ? 'Văn phòng' : 'Khác' }}
              </span>
            </div>
            <p class="address-phone">{{ address.phone }}</p>
          </div>
          <div class="address-card-actions">
            <button class="action-btn action-btn--edit" @click="openEditForm(address)" title="Sửa">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button
              v-if="!address.is_default"
              class="action-btn action-btn--default"
              @click="setDefault(address.address_id)"
              title="Đặt mặc định"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </button>
            <button
              v-if="!address.is_default"
              class="action-btn action-btn--delete"
              @click="deleteAddress(address.address_id)"
              title="Xóa"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            </button>
          </div>
        </div>
        <div class="address-card-body">
          <p class="address-full">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {{ formatFullAddress(address) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
          <circle cx="12" cy="10" r="3"/>
        </svg>
      </div>
      <h3 class="empty-title">Chưa có địa chỉ nào</h3>
      <p class="empty-desc">Thêm địa chỉ giao hàng để đặt hàng nhanh hơn</p>
      <button class="btn-add btn-add--large" @click="openAddForm">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Thêm địa chỉ mới
      </button>
    </div>

    <!-- Modal Form -->
    <teleport to="body">
      <transition name="modal-fade">
        <div v-if="showForm" class="modal-overlay" @click.self="closeForm">
          <div class="modal-content">
            <div class="modal-header">
              <h2 class="modal-title">{{ isEditing ? 'Sửa địa chỉ' : 'Thêm địa chỉ mới' }}</h2>
              <button class="modal-close" @click="closeForm">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>

            <div class="modal-body">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="recipient_name">Họ tên người nhận <span class="required">*</span></label>
                  <input id="recipient_name" v-model="form.recipient_name" type="text" class="form-input" placeholder="Nguyễn Văn A" />
                </div>
                <div class="form-group">
                  <label class="form-label" for="phone">Số điện thoại <span class="required">*</span></label>
                  <input id="phone" v-model="form.phone" type="text" class="form-input" placeholder="0912345678" />
                </div>
              </div>

              <!-- Address Selector -->
              <div class="form-group-grid">
                <div class="form-group">
                  <label class="form-label">Tỉnh / Thành phố <span class="required">*</span></label>
                  <select v-model="form.province_code" class="form-select" :disabled="loadingGHN.provinces">
                    <option value="">Chọn tỉnh thành</option>
                    <option v-for="p in provinces" :key="p.ProvinceID" :value="p.ProvinceID">{{ p.ProvinceName }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Quận / Huyện <span class="required">*</span></label>
                  <select v-model="form.district_code" class="form-select" :disabled="!form.province_code || loadingGHN.districts">
                    <option value="">Chọn quận huyện</option>
                    <option v-for="d in districts" :key="d.DistrictID" :value="d.DistrictID">{{ d.DistrictName }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Phường / Xã <span class="required">*</span></label>
                  <select v-model="form.ward_code" class="form-select" :disabled="!form.district_code || loadingGHN.wards">
                    <option value="">Chọn phường xã</option>
                    <option v-for="w in wards" :key="w.WardCode" :value="w.WardCode">{{ w.WardName }}</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="address_line">Số nhà, tên đường</label>
                <input id="address_line" v-model="form.address_line" type="text" class="form-input" placeholder="Ví dụ: 123 Đường ABC..." />
              </div>

              <div v-if="shippingFee > 0" class="shipping-preview">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <span>Phí vận chuyển dự kiến: <strong>{{ shippingFee.toLocaleString() }}đ</strong></span>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Loại địa chỉ</label>
                  <div class="type-selector">
                    <label class="type-option" :class="{ 'type-option--active': form.address_type === 'home' }">
                      <input type="radio" v-model="form.address_type" value="home" class="type-radio" />
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                      Nhà riêng
                    </label>
                    <label class="type-option" :class="{ 'type-option--active': form.address_type === 'office' }">
                      <input type="radio" v-model="form.address_type" value="office" class="type-radio" />
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
                      Văn phòng
                    </label>
                    <label class="type-option" :class="{ 'type-option--active': form.address_type === 'other' }">
                      <input type="radio" v-model="form.address_type" value="other" class="type-radio" />
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                      Khác
                    </label>
                  </div>
                </div>
                <div class="form-group form-group--checkbox">
                  <label class="checkbox-label">
                    <input type="checkbox" v-model="form.is_default" class="checkbox-input" />
                    <span class="checkbox-custom"></span>
                    Đặt làm địa chỉ mặc định
                  </label>
                </div>
              </div>

              <!-- Error -->
              <div v-if="formError" class="form-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                {{ formError }}
              </div>
            </div>

            <div class="modal-footer">
              <button class="btn-cancel" @click="closeForm">Hủy</button>
              <button class="btn-save" @click="handleSubmit" :disabled="submitting">
                <div v-if="submitting" class="btn-spinner"></div>
                {{ isEditing ? 'Cập nhật' : 'Thêm địa chỉ' }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import api from '@/axios';
import Swal from 'sweetalert2';

const TOKEN_GHN = import.meta.env.VITE_TOKEN_GHN;
const SHOPID_GHN = import.meta.env.VITE_SHOPID_GHN;

const addresses = ref([]);
const loading = ref(true);
const showForm = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitting = ref(false);
const formError = ref('');
const provinces = ref([]);
const districts = ref([]);
const wards = ref([]);
const shippingFee = ref(0);
const loadingGHN = ref({
  provinces: false,
  districts: false,
  wards: false,
  fee: false,
});

const form = ref({
  recipient_name: '',
  phone: '',
  address_line: '',
  ward: '',
  district: '',
  province: '',
  ward_code: '',
  district_code: '',
  province_code: '',
  address_type: 'home',
  is_default: false,
});

// --- GHN API Functions ---
async function getGHNProvinces() {
  if (!TOKEN_GHN) return;
  loadingGHN.value.provinces = true;
  try {
    const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/master-data/province', {
      headers: { Token: TOKEN_GHN },
    });
    provinces.value = response.data?.data || [];
  } catch (error) {
    console.error("Lỗi tải tỉnh thành GHN:", error);
  } finally {
    loadingGHN.value.provinces = false;
  }
}

async function getGHNDistricts(provinceId) {
  if (!TOKEN_GHN || !provinceId) {
    districts.value = [];
    return;
  }
  loadingGHN.value.districts = true;
  try {
    const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/master-data/district', {
      params: { province_id: provinceId },
      headers: { Token: TOKEN_GHN },
    });
    districts.value = response.data?.data || [];
  } catch (error) {
    console.error("Lỗi tải quận huyện GHN:", error);
  } finally {
    loadingGHN.value.districts = false;
  }
}

async function getGHNWards(districtId) {
  if (!TOKEN_GHN || !districtId) {
    wards.value = [];
    return;
  }
  loadingGHN.value.wards = true;
  try {
    const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/master-data/ward', {
      params: { district_id: districtId },
      headers: { Token: TOKEN_GHN },
    });
    wards.value = response.data?.data || [];
  } catch (error) {
    console.error("Lỗi tải phường xã GHN:", error);
  } finally {
    loadingGHN.value.wards = false;
  }
}

async function getGHNShippingFee() {
  if (!TOKEN_GHN || !SHOPID_GHN || !form.value.district_code || !form.value.ward_code) {
    shippingFee.value = 0;
    return;
  }
  loadingGHN.value.fee = true;
  try {
    const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee', {
      params: {
        "service_type_id": 2,
        "to_district_id": parseInt(form.value.district_code),
        "to_ward_code": form.value.ward_code,
        "weight": 1000, // Default weight
      },
      headers: {
        Token: TOKEN_GHN,
        ShopId: SHOPID_GHN,
      },
    });
    shippingFee.value = response.data?.data?.total || 0;
  } catch (error) {
    console.error("Lỗi tính phí vận chuyển GHN:", error);
    shippingFee.value = 0;
  } finally {
    loadingGHN.value.fee = false;
  }
}

// Watchers for nested selection
watch(() => form.value.province_code, async (newVal) => {
  if (newVal) {
    const p = provinces.value.find(i => i.ProvinceID === newVal);
    if (p) form.value.province = p.ProvinceName;
    await getGHNDistricts(newVal);
  } else {
    districts.value = [];
    form.value.province = '';
  }
  // If not manually triggered by openEditForm
  if (!isInitializing) {
    form.value.district_code = '';
    form.value.ward_code = '';
  }
});

watch(() => form.value.district_code, async (newVal) => {
  if (newVal) {
    const d = districts.value.find(i => i.DistrictID === newVal);
    if (d) form.value.district = d.DistrictName;
    await getGHNWards(newVal);
  } else {
    wards.value = [];
    form.value.district = '';
  }
  if (!isInitializing) {
    form.value.ward_code = '';
  }
});

watch(() => form.value.ward_code, async (newVal) => {
  if (newVal) {
    const w = wards.value.find(i => i.WardCode === newVal);
    if (w) form.value.ward = w.WardName;
    await getGHNShippingFee();
  } else {
    form.value.ward = '';
    shippingFee.value = 0;
  }
});

let isInitializing = false;

// Fetch addresses
async function fetchAddresses() {
  loading.value = true;
  try {
    const res = await api.get('/profile/addresses');
    addresses.value = res.data?.data || [];
  } catch (e) {
    console.error('Lỗi tải địa chỉ:', e);
  } finally {
    loading.value = false;
  }
}

// Format full address
function formatFullAddress(addr) {
  const parts = [];
  if (addr.address_line) parts.push(addr.address_line);
  if (addr.ward) parts.push(addr.ward);
  if (addr.district) parts.push(addr.district);
  if (addr.province) parts.push(addr.province);
  return parts.join(', ') || 'Chưa có địa chỉ';
}

// Removed onAddressChange and AddressSelector logic

// Open Add form
async function openAddForm() {
  isEditing.value = false;
  editingId.value = null;
  formError.value = '';
  isInitializing = true;
  form.value = {
    recipient_name: '',
    phone: '',
    address_line: '',
    ward: '',
    district: '',
    province: '',
    ward_code: '',
    district_code: '',
    province_code: '',
    address_type: 'home',
    is_default: false,
  };
  shippingFee.value = 0;
  if (provinces.value.length === 0) await getGHNProvinces();
  isInitializing = false;
  showForm.value = true;
}

// Open Edit form
async function openEditForm(address) {
  isEditing.value = true;
  editingId.value = address.address_id;
  formError.value = '';
  isInitializing = true;

  form.value = {
    recipient_name: address.recipient_name,
    phone: address.phone,
    address_line: address.address_line || '',
    ward: address.ward || '',
    district: address.district || '',
    province: address.province || '',
    ward_code: address.ward_code || '',
    district_code: address.district_code || '',
    province_code: parseInt(address.province_code) || '',
    address_type: address.address_type || 'home',
    is_default: address.is_default || false,
  };

  if (provinces.value.length === 0) await getGHNProvinces();
  
  if (form.value.province_code) {
    await getGHNDistricts(form.value.province_code);
    form.value.district_code = parseInt(address.district_code) || '';
    if (form.value.district_code) {
      await getGHNWards(form.value.district_code);
      form.value.ward_code = address.ward_code || '';
      if (form.value.ward_code) {
        await getGHNShippingFee();
      }
    }
  }

  isInitializing = false;
  showForm.value = true;
}

// Close form
function closeForm() {
  showForm.value = false;
  formError.value = '';
}

// Submit form
async function handleSubmit() {
  // Validate
  if (!form.value.recipient_name.trim()) {
    formError.value = 'Vui lòng nhập họ tên người nhận';
    return;
  }
  if (!form.value.phone.trim()) {
    formError.value = 'Vui lòng nhập số điện thoại';
    return;
  }
  if (!form.value.province) {
    formError.value = 'Vui lòng chọn Tỉnh/Thành phố';
    return;
  }
  if (!form.value.district) {
    formError.value = 'Vui lòng chọn Quận/Huyện';
    return;
  }

  submitting.value = true;
  formError.value = '';

  try {
    if (isEditing.value) {
      await api.put(`/profile/addresses/${editingId.value}`, form.value);
    } else {
      await api.post('/profile/addresses', form.value);
    }
    closeForm();
    await fetchAddresses();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Đã xảy ra lỗi. Vui lòng thử lại.';
  } finally {
    submitting.value = false;
  }
}

// Delete address
async function deleteAddress(id) {
  const result = await Swal.fire({
    title: 'Xác nhận xóa',
    text: 'Bạn có chắc chắn muốn xóa địa chỉ này?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Xóa',
    cancelButtonText: 'Hủy'
  });
  if (!result.isConfirmed) return;
  
  try {
    await api.delete(`/profile/addresses/${id}`);
    await fetchAddresses();
    Swal.fire('Thành công', 'Đã xóa địa chỉ!', 'success');
  } catch (e) {
    Swal.fire('Thất bại', 'Xóa địa chỉ thất bại!', 'error');
  }
}

// Set default
async function setDefault(id) {
  try {
    await api.put(`/profile/addresses/${id}/default`);
    await fetchAddresses();
  } catch (e) {
    Swal.fire('Thất bại', 'Đặt mặc định thất bại!', 'error');
  }
}

onMounted(fetchAddresses);
</script>

<style scoped>
.profile-address {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.section-desc {
  font-size: 0.9rem;
  color: #6b7280;
  margin: 4px 0 0;
}

/* Button Add */
.btn-add {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #1a56db;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
}

.btn-add:hover {
  background: #1648b8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
}

.btn-add--large {
  padding: 12px 28px;
  font-size: 0.95rem;
}

/* Loading */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 0;
  color: #6b7280;
  font-size: 0.9rem;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #1a56db;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Address List */
.address-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.address-card {
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  padding: 20px;
  transition: all 0.2s;
}

.address-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.address-card--default {
  border-color: #93c5fd;
  background: #fafbff;
}

.address-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.address-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.address-name {
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.default-badge {
  display: inline-flex;
  padding: 2px 10px;
  background: #1a56db;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 600;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.type-badge {
  display: inline-flex;
  padding: 2px 10px;
  font-size: 0.7rem;
  font-weight: 600;
  border-radius: 20px;
}

.type-badge--home {
  background: #ecfdf5;
  color: #059669;
}

.type-badge--office {
  background: #eff6ff;
  color: #2563eb;
}

.type-badge--other {
  background: #f3f4f6;
  color: #6b7280;
}

.address-phone {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 4px 0 0;
}

.address-card-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.action-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: #6b7280;
}

.action-btn--edit:hover {
  background: #eff6ff;
  border-color: #93c5fd;
  color: #2563eb;
}

.action-btn--default:hover {
  background: #ecfdf5;
  border-color: #6ee7b7;
  color: #059669;
}

.action-btn--delete:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #dc2626;
}

.address-card-body {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #f3f4f6;
}

.address-full {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 0.875rem;
  color: #4b5563;
  margin: 0;
  line-height: 1.6;
}

.address-full svg {
  color: #9ca3af;
  flex-shrink: 0;
  margin-top: 2px;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  background: #fff;
  border: 1.5px dashed #d1d5db;
  border-radius: 16px;
}

.empty-icon {
  color: #d1d5db;
  margin-bottom: 16px;
}

.empty-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #374151;
  margin: 0;
}

.empty-desc {
  font-size: 0.9rem;
  color: #9ca3af;
  margin: 8px 0 20px;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: #fff;
  border-radius: 20px;
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.modal-close {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: none;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #e5e7eb;
  color: #111827;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
}

/* Form */
.form-row,
.form-group-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group-grid {
  grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 640px) {
  .form-row,
  .form-group-grid {
    grid-template-columns: 1fr;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group--checkbox {
  justify-content: flex-end;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

.form-label .required {
  color: #ef4444;
}

.form-input,
.form-select {
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.9rem;
  color: #1e293b;
  outline: none;
  transition: all 0.2s;
  font-family: inherit;
  background-color: #fff;
}

.form-input:focus,
.form-select:focus {
  border-color: #1a56db;
  box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
}

.form-input::placeholder {
  color: #94a3b8;
}

.form-select:disabled {
  background-color: #f8fafc;
  cursor: not-allowed;
  color: #94a3b8;
}

/* Type Selector */
.type-selector {
  display: flex;
  gap: 10px;
}

.type-option {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.type-option:hover {
  border-color: #94a3b8;
}

.type-option--active {
  border-color: #1a56db;
  background: #eff6ff;
  color: #1a56db;
}

.type-radio {
  display: none;
}

/* Checkbox */
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  user-select: none;
}

.checkbox-input {
  display: none;
}

.checkbox-custom {
  width: 20px;
  height: 20px;
  border: 2px solid #d1d5db;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
  position: relative;
}

.checkbox-input:checked + .checkbox-custom {
  background: #1a56db;
  border-color: #1a56db;
}

.checkbox-input:checked + .checkbox-custom::after {
  content: '';
  width: 6px;
  height: 10px;
  border: 2px solid #fff;
  border-top: none;
  border-left: none;
  transform: rotate(45deg);
  margin-top: -2px;
}

/* Shipping preview */
.shipping-preview {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 10px;
  font-size: 0.9rem;
  color: #0369a1;
}

.shipping-preview svg {
  color: #0ea5e9;
  flex-shrink: 0;
}

.shipping-preview strong {
  color: #1a56db;
}


/* Form Error */
.form-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #dc2626;
  font-size: 0.875rem;
  font-weight: 500;
}

/* Buttons */
.btn-cancel {
  padding: 10px 20px;
  background: #f3f4f6;
  color: #374151;
  border: none;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: #e5e7eb;
}

.btn-save {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  background: #1a56db;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}

.btn-save:hover:not(:disabled) {
  background: #1648b8;
}

.btn-save:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

/* Modal Transitions */
.modal-fade-enter-active {
  animation: modal-in 0.25s ease-out;
}

.modal-fade-leave-active {
  animation: modal-in 0.2s ease-in reverse;
}

@keyframes modal-in {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@media (max-width: 640px) {
  .section-header {
    flex-direction: column;
  }

  .address-card-header {
    flex-direction: column;
  }

  .address-card-actions {
    align-self: flex-end;
  }

  .type-selector {
    flex-direction: column;
  }
}
</style>
