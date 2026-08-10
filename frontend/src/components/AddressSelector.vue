<script setup>
import { ref, computed, onMounted } from "vue";
import { addressService } from "@/services/addressService";

// Props
const props = defineProps({
    // Giá trị ban đầu (dùng khi edit)
    initialProvince: { type: [String, Number], default: "" },
    initialDistrict: { type: [String, Number], default: "" },
    initialWard: { type: [String, Number], default: "" },
    initialDetail: { type: String, default: "" },
    // Tên trường emit ra ngoài
    provinceName: { type: String, default: "province" },
    districtName: { type: String, default: "district" },
    wardName: { type: String, default: "ward" },
});

// Emits
const emit = defineEmits([
    "change",
    "update:province",
    "update:district",
    "update:ward",
    "update:detail",
]);

// Data
const provinces = ref([]);
const wards = ref([]);

const selectedProvince = ref("");
// district ảo — Ocean Express không có cấp Quận/Huyện
// DistrictID = provinceCode, tự động được set khi chọn tỉnh
const selectedDistrict = ref("");
const selectedWard = ref("");
const addressDetail = ref("");

const loadingProvinces = ref(false);
const loadingWards = ref(false);

// Computed - Tên đầy đủ
const selectedProvinceName = computed(() => {
    const p = provinces.value.find(
        (item) => item.id == selectedProvince.value,
    );
    return p ? p.name : "";
});

// districtName không hiển thị trực tiếp nhưng giữ cho emitChange compat
const selectedDistrictName = computed(() => "");

const selectedWardName = computed(() => {
    const w = wards.value.find((item) => item.id == selectedWard.value);
    return w ? w.name : "";
});

const fullAddress = computed(() => {
    const parts = [];
    if (addressDetail.value) parts.push(addressDetail.value);
    if (selectedWardName.value) parts.push(selectedWardName.value);
    if (selectedProvinceName.value) parts.push(selectedProvinceName.value);
    return [...new Set(parts)].join(", ");
});

// Methods
async function fetchProvinces() {
    loadingProvinces.value = true;
    try {
        const response = await addressService.listProvinces();
        provinces.value = response.data.data || [];
    } catch (error) {
        console.error("Lỗi khi tải danh sách tỉnh/thành phố:", error);
        provinces.value = [];
    } finally {
        loadingProvinces.value = false;
    }
}

/**
 * Ocean Express không có cấp Quận/Huyện.
 * Khi chọn tỉnh → tự động dùng provinceCode làm districtCode để load phường/xã.
 */
async function fetchWards(provinceCode) {
    if (!provinceCode) {
        wards.value = [];
        return;
    }
    loadingWards.value = true;
    try {
        // API: GET /location/wards/:districtCode — districtCode = provinceCode (district ảo)
        const response = await addressService.listWards(provinceCode);
        wards.value = response.data.data || [];
    } catch (error) {
        console.error("Lỗi khi tải danh sách phường/xã:", error);
        wards.value = [];
    } finally {
        loadingWards.value = false;
    }
}

function onProvinceChange() {
    // Reset phường khi đổi tỉnh
    selectedWard.value = "";
    wards.value = [];

    if (selectedProvince.value) {
        // District ảo = provinceCode — bỏ qua step chọn quận/huyện
        selectedDistrict.value = selectedProvince.value;
        fetchWards(selectedProvince.value);
    } else {
        selectedDistrict.value = "";
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
        // district_code = provinceCode (ảo, để backward compat với DB)
        district_code: selectedDistrict.value,
        district_name: selectedDistrictName.value,
        ward_code: selectedWard.value,
        ward_name: selectedWardName.value,
        address_detail: addressDetail.value,
        full_address: fullAddress.value,
    };

    emit("change", data);
    emit("update:province", selectedProvinceName.value);
    emit("update:district", selectedDistrictName.value);
    emit("update:ward", selectedWardName.value);
    emit("update:detail", addressDetail.value);
}

// Lifecycle
onMounted(async () => {
    await fetchProvinces();

    // Nếu có giá trị ban đầu (edit mode)
    if (props.initialProvince) {
        selectedProvince.value = props.initialProvince;

        // District ảo: dùng initialDistrict nếu có, fallback về provinceCode
        const districtCode = props.initialDistrict || props.initialProvince;
        selectedDistrict.value = districtCode;
        await fetchWards(districtCode);

        if (props.initialWard) {
            selectedWard.value = props.initialWard;
        }
    }

    if (props.initialDetail) {
        addressDetail.value = props.initialDetail;
    }
});
</script>
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
                        <option value="">Chọn Tỉnh/Thành phố</option>
                        <option
                            v-for="province in provinces"
                            :key="province.id"
                            :value="province.id"
                        >
                            {{ province.name }}
                        </option>
                    </select>
                    <div
                        v-if="loadingProvinces"
                        class="address-selector__spinner"
                    ></div>
                </div>
            </div>

            <!-- Phường / Xã — span 2 cột vì không còn Quận/Huyện -->
            <div class="address-selector__field address-selector__field--ward">
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
                        :disabled="!selectedProvince || loadingWards"
                    >
                        <option value="">Chọn Phường/Xã</option>
                        <option
                            v-for="ward in wards"
                            :key="ward.id"
                            :value="ward.id"
                        >
                            {{ ward.name }}
                        </option>
                    </select>
                    <div
                        v-if="loadingWards"
                        class="address-selector__spinner"
                    ></div>
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

<style scoped>
.address-selector {
    width: 100%;
}

.address-selector__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
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
    color: var(--primary);
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
    border-radius: 6px;
    font-size: 0.9rem;
    color: var(--text-main);
    background: var(--card-bg);
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
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.15);
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
    border-top-color: var(--primary);
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
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #334155;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    line-height: 1.5;
}

.address-selector__preview i {
    color: var(--primary);
    margin-top: 2px;
    flex-shrink: 0;
}
</style>
