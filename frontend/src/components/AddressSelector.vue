<template>
  <div class="address-selector">
    <div class="address-selector__row">
      <!-- Tỉnh / Thành phố -->
      <div class="address-selector__field">
        <label class="address-selector__label" for="province-select">
          <i class="fas fa-map-marker-alt"></i>
          Tỉnh / Thành phố <span class="required">*</span>
        </label>
        <div class="address-selector__select-wrapper">
          <select
            id="province-select"
            v-model="selectedProvince"
            @change="onProvinceChange"
            class="address-selector__select"
            :disabled="loadingProvinces"
          >
            <option value="">-- Chọn Tỉnh/Thành phố --</option>
            <option
              v-for="province in provinces"
              :key="province.code"
              :value="province.code"
            >
              {{ province.name }}
            </option>
          </select>
          <div v-if="loadingProvinces" class="address-selector__spinner"></div>
        </div>
      </div>

      <!-- Quận / Huyện -->
      <div class="address-selector__field">
        <label class="address-selector__label" for="district-select">
          <i class="fas fa-building"></i>
          Quận / Huyện <span class="required">*</span>
        </label>
        <div class="address-selector__select-wrapper">
          <select
            id="district-select"
            v-model="selectedDistrict"
            @change="onDistrictChange"
            class="address-selector__select"
            :disabled="!selectedProvince || loadingDistricts"
          >
            <option value="">-- Chọn Quận/Huyện --</option>
            <option
              v-for="district in districts"
              :key="district.code"
              :value="district.code"
            >
              {{ district.name }}
            </option>
          </select>
          <div v-if="loadingDistricts" class="address-selector__spinner"></div>
        </div>
      </div>

      <!-- Phường / Xã -->
      <div class="address-selector__field">
        <label class="address-selector__label" for="ward-select">
          <i class="fas fa-home"></i>
          Phường / Xã <span class="required">*</span>
        </label>
        <div class="address-selector__select-wrapper">
          <select
            id="ward-select"
            v-model="selectedWard"
            @change="onWardChange"
            class="address-selector__select"
            :disabled="!selectedDistrict || loadingWards"
          >
            <option value="">-- Chọn Phường/Xã --</option>
            <option
              v-for="ward in wards"
              :key="ward.code"
              :value="ward.code"
            >
              {{ ward.name }}
            </option>
          </select>
          <div v-if="loadingWards" class="address-selector__spinner"></div>
        </div>
      </div>
    </div>

    <!-- Địa chỉ chi tiết -->
    <div class="address-selector__field address-selector__field--full">
      <label class="address-selector__label" for="address-detail">
        <i class="fas fa-pen"></i>
        Địa chỉ chi tiết
      </label>
      <input
        id="address-detail"
        v-model="addressDetail"
        @input="onDetailChange"
        type="text"
        class="address-selector__input"
        placeholder="Số nhà, tên đường, tòa nhà..."
      />
    </div>

    <!-- Hiển thị địa chỉ đầy đủ -->
    <div v-if="fullAddress" class="address-selector__preview">
      <i class="fas fa-location-dot"></i>
      <span>{{ fullAddress }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '@/axios';

// Props
const props = defineProps({
  // Giá trị ban đầu (dùng khi edit)
  initialProvince: { type: [String, Number], default: '' },
  initialDistrict: { type: [String, Number], default: '' },
  initialWard: { type: [String, Number], default: '' },
  initialDetail: { type: String, default: '' },
  // Tên trường emit ra ngoài
  provinceName: { type: String, default: 'province' },
  districtName: { type: String, default: 'district' },
  wardName: { type: String, default: 'ward' },
});

// Emits
const emit = defineEmits(['change', 'update:province', 'update:district', 'update:ward', 'update:detail']);

// Data
const provinces = ref([]);
const districts = ref([]);
const wards = ref([]);

const selectedProvince = ref('');
const selectedDistrict = ref('');
const selectedWard = ref('');
const addressDetail = ref('');

const loadingProvinces = ref(false);
const loadingDistricts = ref(false);
const loadingWards = ref(false);

// Computed - Tên đầy đủ
const selectedProvinceName = computed(() => {
  const p = provinces.value.find(item => item.code == selectedProvince.value);
  return p ? p.name : '';
});

const selectedDistrictName = computed(() => {
  const d = districts.value.find(item => item.code == selectedDistrict.value);
  return d ? d.name : '';
});

const selectedWardName = computed(() => {
  const w = wards.value.find(item => item.code == selectedWard.value);
  return w ? w.name : '';
});

const fullAddress = computed(() => {
  const parts = [];
  if (addressDetail.value) parts.push(addressDetail.value);
  if (selectedWardName.value) parts.push(selectedWardName.value);
  if (selectedDistrictName.value) parts.push(selectedDistrictName.value);
  if (selectedProvinceName.value) parts.push(selectedProvinceName.value);
  return parts.join(', ');
});

// Methods
async function fetchProvinces() {
  loadingProvinces.value = true;
  try {
    const response = await api.get('/location/provinces');
    provinces.value = response.data.data || [];
  } catch (error) {
    console.error('Lỗi khi tải danh sách tỉnh/thành phố:', error);
    provinces.value = [];
  } finally {
    loadingProvinces.value = false;
  }
}

async function fetchDistricts(provinceCode) {
  if (!provinceCode) {
    districts.value = [];
    return;
  }
  loadingDistricts.value = true;
  try {
    const response = await api.get(`/location/districts/${provinceCode}`);
    districts.value = response.data.data || [];
  } catch (error) {
    console.error('Lỗi khi tải danh sách quận/huyện:', error);
    districts.value = [];
  } finally {
    loadingDistricts.value = false;
  }
}

async function fetchWards(districtCode) {
  if (!districtCode) {
    wards.value = [];
    return;
  }
  loadingWards.value = true;
  try {
    const response = await api.get(`/location/wards/${districtCode}`);
    wards.value = response.data.data || [];
  } catch (error) {
    console.error('Lỗi khi tải danh sách phường/xã:', error);
    wards.value = [];
  } finally {
    loadingWards.value = false;
  }
}

function onProvinceChange() {
  // Reset quận + phường khi đổi tỉnh
  selectedDistrict.value = '';
  selectedWard.value = '';
  districts.value = [];
  wards.value = [];

  if (selectedProvince.value) {
    fetchDistricts(selectedProvince.value);
  }

  emitChange();
}

function onDistrictChange() {
  // Reset phường khi đổi quận
  selectedWard.value = '';
  wards.value = [];

  if (selectedDistrict.value) {
    fetchWards(selectedDistrict.value);
  }

  emitChange();
}

function onWardChange() {
  emitChange();
}

function onDetailChange() {
  emitChange();
}

function emitChange() {
  const data = {
    province_code: selectedProvince.value,
    province_name: selectedProvinceName.value,
    district_code: selectedDistrict.value,
    district_name: selectedDistrictName.value,
    ward_code: selectedWard.value,
    ward_name: selectedWardName.value,
    address_detail: addressDetail.value,
    full_address: fullAddress.value,
  };

  emit('change', data);
  emit('update:province', selectedProvinceName.value);
  emit('update:district', selectedDistrictName.value);
  emit('update:ward', selectedWardName.value);
  emit('update:detail', addressDetail.value);
}

// Lifecycle
onMounted(async () => {
  await fetchProvinces();

  // Nếu có giá trị ban đầu (edit mode)
  if (props.initialProvince) {
    selectedProvince.value = props.initialProvince;
    await fetchDistricts(props.initialProvince);

    if (props.initialDistrict) {
      selectedDistrict.value = props.initialDistrict;
      await fetchWards(props.initialDistrict);

      if (props.initialWard) {
        selectedWard.value = props.initialWard;
      }
    }
  }

  if (props.initialDetail) {
    addressDetail.value = props.initialDetail;
  }
});
</script>

<style scoped>
.address-selector {
  width: 100%;
}

.address-selector__row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

@media (max-width: 768px) {
  .address-selector__row {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}

.address-selector__field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.address-selector__field--full {
  margin-top: 16px;
}

.address-selector__label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 6px;
}

.address-selector__label i {
  color: #0ea5e9;
  font-size: 0.8rem;
}

.address-selector__label .required {
  color: #ef4444;
  font-size: 0.75rem;
}

.address-selector__select-wrapper {
  position: relative;
}

.address-selector__select,
.address-selector__input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.9rem;
  color: #1e293b;
  background: #fff;
  transition: all 0.2s ease;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
}

.address-selector__select {
  padding-right: 36px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 12px;
  cursor: pointer;
}

.address-selector__select:focus,
.address-selector__input:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}

.address-selector__select:hover,
.address-selector__input:hover {
  border-color: #94a3b8;
}

.address-selector__select:disabled {
  background-color: #f8fafc;
  color: #94a3b8;
  cursor: not-allowed;
}

.address-selector__input::placeholder {
  color: #94a3b8;
}

/* Loading Spinner */
.address-selector__spinner {
  position: absolute;
  top: 50%;
  right: 40px;
  transform: translateY(-50%);
  width: 16px;
  height: 16px;
  border: 2px solid #e2e8f0;
  border-top-color: #0ea5e9;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: translateY(-50%) rotate(360deg);
  }
}

/* Preview */
.address-selector__preview {
  margin-top: 14px;
  padding: 12px 16px;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border: 1px solid #bae6fd;
  border-radius: 10px;
  font-size: 0.875rem;
  color: #0369a1;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  line-height: 1.5;
}

.address-selector__preview i {
  color: #0ea5e9;
  margin-top: 2px;
  flex-shrink: 0;
}
</style>