import api from '@/axios';

export const extractCollection = (response) => {
  return response?.data?.data?.data || response?.data?.data || response?.data || [];
};

export const catalogService = {
  listProducts(params = {}) {
    return api.get('/products', { params });
  },

  listCategories() {
    return api.get('/categories');
  },

  listBrands() {
    return api.get('/brands');
  },

  searchProducts(search, params = {}) {
    return api.get('/products', {
      params: {
        ...params,
        search,
      },
    });
  },
};
