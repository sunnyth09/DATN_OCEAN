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

  const reset = () => {
    categories.value = [];
    hasFetchedCategories.value = false;
    brands.value = [];
    hasFetchedBrands.value = false;
  };

  return {
    categories,
    brands,
    isFetchingCategories,
    hasFetchedCategories,
    hasFetchedBrands,
    fetchCategories,
    fetchBrands,
    reset,
  };
});
