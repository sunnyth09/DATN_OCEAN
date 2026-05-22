import { ref } from 'vue';
import { defineStore } from 'pinia';
import { catalogService } from '@/services/catalogService';

export const useCatalogStore = defineStore('catalog', () => {
  const categories = ref([]);
  const isFetchingCategories = ref(false);
  const hasFetchedCategories = ref(false);

  const fetchCategories = async (force = false) => {
    if ((hasFetchedCategories.value || isFetchingCategories.value) && !force) {
      return categories.value;
    }

    isFetchingCategories.value = true;
    try {
      const response = await catalogService.listCategories();
      categories.value = response.data?.data || [];
      hasFetchedCategories.value = true;
      return categories.value;
    } catch (error) {
      console.error('Error fetching categories:', error);
      categories.value = [];
      return categories.value;
    } finally {
      isFetchingCategories.value = false;
    }
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
