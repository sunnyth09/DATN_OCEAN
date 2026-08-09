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
    // Lấy sản phẩm bán chạy nhất
    featuredProductsRequest = catalogService.getBestSelling(8)
      .then((response) => {
        homeFeaturedProducts.value = response;
        hasFetchedFeaturedProducts.value = true;
        return homeFeaturedProducts.value;
      })
      .catch((err) => {
        console.error('Error fetching best selling products:', err);
        homeFeaturedProducts.value = null;
        return [];
      })
      .finally(() => {
        isFetchingFeaturedProducts.value = false;
        featuredProductsRequest = null;
      });

    return featuredProductsRequest;
  };

  // ── On Sale Products ──
  const homeOnSaleProducts = ref([]);
  const hasFetchedOnSaleProducts = ref(false);
  const isFetchingOnSaleProducts = ref(false);
  let onSaleProductsRequest = null;

  const fetchOnSaleProducts = async (force = false) => {
    if (hasFetchedOnSaleProducts.value && !force) {
      return homeOnSaleProducts.value;
    }
    if (onSaleProductsRequest && !force) {
      return onSaleProductsRequest;
    }

    isFetchingOnSaleProducts.value = true;
    onSaleProductsRequest = catalogService.getOnSale(8)
      .then((response) => {
        homeOnSaleProducts.value = response;
        hasFetchedOnSaleProducts.value = true;
        return homeOnSaleProducts.value;
      })
      .catch((err) => {
        console.error('Error fetching on-sale products:', err);
        homeOnSaleProducts.value = null;
        return [];
      })
      .finally(() => {
        isFetchingOnSaleProducts.value = false;
        onSaleProductsRequest = null;
      });

    return onSaleProductsRequest;
  };

  const reset = () => {
    categories.value = [];
    hasFetchedCategories.value = false;
    brands.value = [];
    hasFetchedBrands.value = false;
    homeFeaturedProducts.value = [];
    hasFetchedFeaturedProducts.value = false;
    homeOnSaleProducts.value = [];
    hasFetchedOnSaleProducts.value = false;
    hasSeenSplash.value = false;
  };

  const hasSeenSplash = ref(false);

  return {
    categories,
    brands,
    homeFeaturedProducts,
    homeOnSaleProducts,
    isFetchingCategories,
    hasFetchedCategories,
    hasFetchedBrands,
    isFetchingFeaturedProducts,
    hasFetchedFeaturedProducts,
    isFetchingOnSaleProducts,
    hasFetchedOnSaleProducts,
    hasSeenSplash,
    fetchCategories,
    fetchBrands,
    fetchFeaturedProducts,
    fetchOnSaleProducts,
    reset,
  };
});
