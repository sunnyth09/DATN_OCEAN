import api from '@/axios';

export const extractCollection = (response) => {
  return response?.data?.data?.data || response?.data?.data || response?.data || [];
};

export const catalogService = {
  listProducts(params = {}, config = {}) {
    return api.get('/products', { ...config, params });
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

  getBestSelling(limit = 8) {
    return api.get('/products/home/best-selling', { params: { limit } });
  },

  getOnSale(limit = 8) {
    return api.get('/products/home/on-sale', { params: { limit } });
  },

  viewProduct(productId) {
    return api.post('/tracking/view-product', { product_id: productId });
  },

  getRecentlyViewed() {
    return api.get('/tracking/recently-viewed');
  },

  getSearchHistory() {
    return api.get('/tracking/search-history');
  },
};
