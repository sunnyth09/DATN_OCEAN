import { ref } from 'vue';
import { defineStore } from 'pinia';
import { catalogService } from '@/services/catalogService';

export const useCatalogStore = defineStore('catalog', () => {
  const categories = ref([]);
  const isFetchingCategories = ref(false);
  const hasFetchedCategories = ref(false);
  let categoriesRequest = null;

  const fetchCategories = async (force = false) => {
    if (hasFetchedCategories.value && !force) {
      return categories.value;
    }

    if (categoriesRequest && !force) {
      return categoriesRequest;
    }

    isFetchingCategories.value = true;
    categoriesRequest = catalogService.listCategories()
      .then((response) => {
        categories.value = response.data?.data || [];
        hasFetchedCategories.value = true;
        return categories.value;
      })
      .catch((error) => {
        console.error('Error fetching categories:', error);
        categories.value = [];
        return categories.value;
      })
      .finally(() => {
        isFetchingCategories.value = false;
        categoriesRequest = null;
      });

    return categoriesRequest;
  };

  const brands = ref([]);
  const hasFetchedBrands = ref(false);
  let brandsRequest = null;

  const fetchBrands = async (force = false) => {
    if (hasFetchedBrands.value && !force) {
      return brands.value;
    }
    if (brandsRequest && !force) {
      return brandsRequest;
    }

    brandsRequest = catalogService.listBrands()
      .then((response) => {
        brands.value = response.data?.data || response.data || [];
        hasFetchedBrands.value = true;
        return brands.value;
      })
      .catch(() => {
        brands.value = [];
        return brands.value;
      })
      .finally(() => {
        brandsRequest = null;
      });

    return brandsRequest;
  };

  const homeFeaturedProducts = ref([]);
  const hasFetchedFeaturedProducts = ref(false);
  const isFetchingFeaturedProducts = ref(false);
  let featuredProductsRequest = null;

  const fetchFeaturedProducts = async (force = false) => {
    if (hasFetchedFeaturedProducts.value && !force) {
      return homeFeaturedProducts.value;
    }
    if (featuredProductsRequest && !force) {
      return featuredProductsRequest;
    }

    isFetchingFeaturedProducts.value = true;
    // Lấy 8 sản phẩm mới nhất làm featured
    featuredProductsRequest = catalogService.listProducts({ limit: 8, sort: 'newest' })
      .then((response) => {
        homeFeaturedProducts.value = response; // Lưu toàn bộ raw response
        hasFetchedFeaturedProducts.value = true;
        return homeFeaturedProducts.value;
      })
      .catch((err) => {
        console.error('Error fetching featured products:', err);
        homeFeaturedProducts.value = null;
        return [];
      })
      .finally(() => {
        isFetchingFeaturedProducts.value = false;
        featuredProductsRequest = null;
      });

    return featuredProductsRequest;
  };

  const reset = () => {
    categories.value = [];
    hasFetchedCategories.value = false;
    brands.value = [];
    hasFetchedBrands.value = false;
    homeFeaturedProducts.value = [];
    hasFetchedFeaturedProducts.value = false;
    hasSeenSplash.value = false;
  };

  const hasSeenSplash = ref(false);

  return {
    categories,
    brands,
    homeFeaturedProducts,
    isFetchingCategories,
    hasFetchedCategories,
    hasFetchedBrands,
    isFetchingFeaturedProducts,
    hasFetchedFeaturedProducts,
    hasSeenSplash,
    fetchCategories,
    fetchBrands,
    fetchFeaturedProducts,
    reset,
  };
});
