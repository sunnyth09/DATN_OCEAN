<script setup>
import { ref, onMounted, watch } from 'vue';
import { addressService } from '@/services/addressService';

const selectedProvince = ref(null);
const selectedDistrict = ref(null);
const selectedWard = ref(null);

const provinces = ref([]);
const districts = ref([]);
const wards = ref([]);
const shippingFee = ref(0);

const getProvinces = async () => {
    try {
        const response = await addressService.listProvinces();
        provinces.value = response.data?.data || [];
    } catch (error) {
        console.error('Không thể tải danh sách tỉnh/thành:', error.response?.data || error.message);
    }
};

const getDistricts = async () => {
    selectedDistrict.value = null;
    selectedWard.value = null;
    districts.value = [];
    wards.value = [];
    shippingFee.value = 0;

    if (!selectedProvince.value) return;

    try {
        const response = await addressService.listDistricts(selectedProvince.value);
        districts.value = response.data?.data || [];
    } catch (error) {
        console.error('Không thể tải danh sách quận/huyện:', error.response?.data || error.message);
    }
};

const getWards = async () => {
    selectedWard.value = null;
    wards.value = [];
    shippingFee.value = 0;

    if (!selectedDistrict.value) return;

    try {
        const response = await addressService.listWards(selectedDistrict.value);
        wards.value = response.data?.data || [];
    } catch (error) {
        console.error('Không thể tải danh sách phường/xã:', error.response?.data || error.message);
    }
};

const getShippingFee = async () => {
    if (!selectedDistrict.value || !selectedWard.value) return;

    try {
        shippingFee.value = await addressService.getShippingFee({
            districtCode: selectedDistrict.value,
            wardCode: selectedWard.value,
            weight: 500,
        });
    } catch (error) {
        shippingFee.value = 0;
        console.error('Không thể tính phí vận chuyển:', error.response?.data || error.message);
    }
};

watch(selectedProvince, getDistricts);
watch(selectedDistrict, getWards);
watch(selectedWard, getShippingFee);

onMounted(getProvinces);
</script>

<template>
    <select v-model="selectedProvince" name="province" id="province">
        <option :value="null">Chọn tỉnh thành</option>
        <option v-for="item in provinces" :key="item.ProvinceID" :value="item.ProvinceID">{{ item.ProvinceName }}
        </option>
    </select>

    <select :disabled="!selectedProvince" v-model="selectedDistrict" name="district" id="district">
        <option :value="null">Chọn quận huyện</option>
        <option v-for="item in districts" :key="item.DistrictID" :value="item.DistrictID">{{ item.DistrictName }}
        </option>
    </select>

    <select :disabled="!selectedDistrict" v-model="selectedWard" name="ward" id="ward">
        <option :value="null">Chọn phường xã</option>
        <option v-for="item in wards" :key="item.WardCode" :value="item.WardCode">{{ item.WardName }}
        </option>
    </select>

    <p>Phí vận chuyển: {{ shippingFee }}</p>
</template>
