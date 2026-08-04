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
     * Tính phí vận chuyển qua Ocean Express (proxy qua Backend /ghn/calculate-fee).
     * wardCode: Ocean Express location ID (e.g. "VN-01-00004") — đây là WardCode từ /location/wards
     * weight: trọng lượng gói hàng tính bằng gram
     */
    async getShippingFee({ wardCode, weight = 500 }) {
        if (!wardCode) {
            return 0;
        }

        try {
            const response = await api.post('/ghn/calculate-fee', {
                ward_code: String(wardCode),
                weight: weight,
            });

            return response.data?.data?.total || 0;
        } catch (error) {
            console.error('Lỗi tính phí vận chuyển Ocean Express (qua Backend):', error);
            return 30000; // Fallback fee
        }
    },
};
