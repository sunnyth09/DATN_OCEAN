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

  const reset = () => {
    categories.value = [];
    hasFetchedCategories.value = false;
  };

  return {
    categories,
    isFetchingCategories,
    hasFetchedCategories,
    fetchCategories,
    reset,
  };
});
