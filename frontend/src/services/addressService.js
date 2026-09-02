import api from '@/axios';

export const addressService = {
    listProfileAddresses(params = {}) {
        return api.get('/profile/addresses', { params });
    },

    createProfileAddress(payload) {
        return api.post('/profile/addresses', payload);
    },

    updateProfileAddress(addressId, payload) {
        return api.put(`/profile/addresses/${addressId}`, payload);
    },

    deleteProfileAddress(addressId) {
        return api.delete(`/profile/addresses/${addressId}`);
    },

    setDefaultProfileAddress(addressId) {
        return api.put(`/profile/addresses/${addressId}/default`);
    },

    listProvinces() {
        return api.get('/location/provinces');
    },

    listDistricts(provinceCode) {
        return api.get(`/location/districts/${provinceCode}`);
    },

    listWards(districtCode) {
        return api.get(`/location/wards/${districtCode}`);
    },

    /**
     * Tính phí vận chuyển qua Ocean Express / GHN (proxy qua Backend /ghn/calculate-fee).
     * wardCode: Location ID (e.g. "VN-01-00004" hoặc mã phường)
     * weight: trọng lượng gói hàng tính bằng gram
     */
    async getShippingFee({ wardCode, districtId, weight = 500 }) {
        if (!wardCode && !districtId) {
            return 0;
        }

        try {
            const payload = {
                ward_code: wardCode ? String(wardCode) : undefined,
                to_ward_code: wardCode ? String(wardCode) : undefined,
                weight: Number(weight) || 500,
            };
            if (districtId) {
                payload.district_id = districtId;
                payload.to_district_id = districtId;
            }

            const response = await api.post('/ghn/calculate-fee', payload);
            const fee = response.data?.data?.total ?? response.data?.data?.fee ?? response.data?.data?.service_fee ?? response.data?.total ?? response.data?.fee;

            return typeof fee === 'number' ? fee : 30000;
        } catch (error) {
            console.warn('Sử dụng phí vận chuyển tiêu chuẩn (fallback):', error?.response?.data?.message || error?.message);
            return 30000; // Fallback fee
        }
    },
};
