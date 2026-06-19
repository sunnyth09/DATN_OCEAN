import axios from 'axios';
import api from '@/axios';

const GHN_BASE_URL = 'https://dev-online-gateway.ghn.vn/shiip/public-api';

const getGhnHeaders = () => {
  const token = import.meta.env.VITE_TOKEN_GHN_SANBOX;
  const shopId = import.meta.env.VITE_SHOPID_GHN_SANBOX;

  if (!token || !shopId) {
    return null;
  }

  return {
    token: token,
    shop_id: shopId,
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
    return axios.get(`${GHN_BASE_URL}/master-data/province`, {
      headers: {
        ...getGhnHeaders(),
      },
    });
  },

  listDistricts(provinceCode) {
    return axios.get(`${GHN_BASE_URL}/master-data/district`, {
      headers: {
        ...getGhnHeaders(),
      },
      params: {
        province_id: provinceCode,
      },
    });
  },

  listWards(districtCode) {
    return axios.get(`${GHN_BASE_URL}/master-data/ward`, {
      headers: {
        ...getGhnHeaders(),
      },
      params: {
        district_id: districtCode,
      },
    });
  },

  async getShippingFee({ districtCode, wardCode, weight = 1000, serviceTypeId = 2 }) {
    const ghnHeaders = getGhnHeaders();

    if (!districtCode || !wardCode || !ghnHeaders) {
      return 0;
    }

    const response = await axios.get(`${GHN_BASE_URL}/v2/shipping-order/fee`, {
      params: {
        service_type_id: serviceTypeId,
        to_district_id: Number.parseInt(districtCode, 10),
        to_ward_code: wardCode,
        weight,
      },
      headers: ghnHeaders,
    });

    return response.data?.data?.total || 0;
  },
};
