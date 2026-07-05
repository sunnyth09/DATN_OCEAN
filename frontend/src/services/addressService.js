import api from '@/axios';

const GHN_BASE_URL = 'https://online-gateway.ghn.vn/shiip/public-api';

const getGhnHeaders = () => {
  const token = import.meta.env.VITE_TOKEN_GHN;
  const shopId = import.meta.env.VITE_SHOPID_GHN;

  if (!token || !shopId) {
    return null;
  }

  return {
    token: token,
  };
};

export const addressService = {
    listProfileAddresses() {
        return api.get('/profile/addresses');
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

        const response = await api.post('/ghn/calculate-fee', {
            district_id: Number.parseInt(districtCode, 10),
            ward_code: String(wardCode),
            weight,
            service_type_id: serviceTypeId,
        });

        return response.data?.data?.total || 0;
    },
};
