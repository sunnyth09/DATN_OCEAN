<script setup>
import axios from 'axios';
import { ref, onMounted, watch } from 'vue';

const TOKEN_GHN = import.meta.env.VITE_TOKEN_GHN;
const SHOPID_GHN = import.meta.env.VITE_SHOPID_GHN;

const selectedProvince = ref(null);
const selectedDistrict = ref(null);
const selectedWard = ref(null);

const provinces = ref([]);
const districts = ref([]);
const wards = ref([]);
const shippingFee = ref(0);

const getProvinces = async () => {
    if (!TOKEN_GHN) {
        console.error("TOKEN_GHN is missing in .env");
        return;
    }

    try {
        const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/master-data/province', {
            headers: {
                Token: TOKEN_GHN,
            },
        });
        provinces.value = response.data?.data || [];
        console.log("Dữ liệu GHN:", provinces.value);
    } catch (error) {
        console.error("Lỗi 401 thường do token sai hoặc header không được chấp nhận:", error.response?.data || error.message);
    }
};

const getDistricts = async () => {
    if (!TOKEN_GHN) {
        console.error("TOKEN_GHN is missing in .env");
        return;
    }

    try {
        const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/master-data/district', {
            params: {
                province_id: selectedProvince.value,
            },
            headers: {
                Token: TOKEN_GHN,
            },
        });
        districts.value = response.data?.data || [];
        console.log("Dữ liệu GHN:", districts.value);
    } catch (error) {
        console.error("Lỗi 401 thường do token sai hoặc header không được chấp nhận:", error.response?.data || error.message);
    }
};

const getWards = async () => {
    if (!TOKEN_GHN) {
        console.error("TOKEN_GHN is missing in .env");
        return;
    }

    try {
        const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/master-data/ward', {
            params: {
                district_id: selectedDistrict.value,
            },
            headers: {
                Token: TOKEN_GHN,
            },
        });
        wards.value = response.data?.data || [];
        console.log("Dữ liệu GHN:", wards.value);
    } catch (error) {
        console.error("Lỗi 401 thường do token sai hoặc header không được chấp nhận:", error.response?.data || error.message);
    }
};

watch(selectedProvince, () => {
    getDistricts();
});

watch(selectedDistrict, () => {
    getWards();
});

watch(selectedWard, () => {
    getShippingFee();
});

const getShippingFee = async () => {
    if (!TOKEN_GHN) {
        console.error("TOKEN_GHN is missing in .env");
        return;
    }

    try {
        const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee', {
            params: {
                "service_type_id":2,
                "to_district_id": selectedDistrict.value,
                "to_ward_code": selectedWard.value,
                "weight": 3000,
            },
            headers: {
                Token: TOKEN_GHN,
                ShopId: SHOPID_GHN,
            },
        });
        shippingFee.value = response.data?.data?.total || "Lỗi";
        console.log("Dữ liệu GHN:", shippingFee.value);
    } catch (error) {
        console.error("Lỗi 401 thường do token sai hoặc header không được chấp nhận:", error.response?.data || error.message);
    }
};

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