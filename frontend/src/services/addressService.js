import axios from 'axios';
import api from '@/axios';

const GHN_BASE_URL = 'https://online-gateway.ghn.vn/shiip/public-api';

const getGhnHeaders = () => {
  const token = import.meta.env.VITE_TOKEN_GHN;
  const shopId = import.meta.env.VITE_SHOPID_GHN;

  if (!token || !shopId || token.includes('here') || shopId.includes('here')) {
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

  async listProvinces() {
    const headers = getGhnHeaders();
    if (!headers) {
      const response = await axios.get('https://provinces.open-api.vn/api/p/');
      return {
        data: {
          data: response.data.map(item => ({
            ProvinceID: item.code,
            ProvinceName: item.name,
          })),
        },
      };
    }
    return axios.get(`${GHN_BASE_URL}/master-data/province`, {
      headers,
    });
  },

  async listDistricts(provinceCode) {
    const headers = getGhnHeaders();
    if (!headers) {
      const response = await axios.get(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`);
      return {
        data: {
          data: (response.data.districts || []).map(item => ({
            DistrictID: item.code,
            DistrictName: item.name,
          })),
        },
      };
    }
    return axios.get(`${GHN_BASE_URL}/master-data/district`, {
      headers,
      params: {
        province_id: provinceCode,
      },
    });
  },

  async listWards(districtCode) {
    const headers = getGhnHeaders();
    if (!headers) {
      const response = await axios.get(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`);
      return {
        data: {
          data: (response.data.wards || []).map(item => ({
            WardCode: String(item.code),
            WardName: item.name,
          })),
        },
      };
    }
    return axios.get(`${GHN_BASE_URL}/master-data/ward`, {
      headers,
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
