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

    async getShippingFee({ districtCode, wardCode, weight = 1000, serviceTypeId = 2 }) {
        if (!districtCode || !wardCode) {
            return 0;
        }

        try {
            const response = await api.post('/ghn/calculate-fee', {
                service_type_id: serviceTypeId,
                to_district_id: Number.parseInt(districtCode, 10),
                to_ward_code: String(wardCode),
                weight: weight,
            });

            return response.data?.data?.total || 0;
        } catch (error) {
            console.error('Lỗi tính phí vận chuyển GHN (qua Backend):', error);
            return 30000; // Fallback fee
        }
    },
};
