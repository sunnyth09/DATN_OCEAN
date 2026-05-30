import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { Toast } from 'bootstrap';

export const useToastStore = defineStore('toast', () => {
    const registry = ref({});

    const ensureToast = (toastId = 'appToast') => {
        if (!registry.value[toastId]) {
            registry.value[toastId] = { message: '', type: 'success' };
        }
        return registry.value[toastId];
    };

    const getToast = (toastId = 'appToast') => computed(() => ensureToast(toastId));

    const showToast = (toastId = 'appToast', message, type = 'success') => {
        registry.value[toastId] = { message, type };

        requestAnimationFrame(() => {
            const el = document.getElementById(toastId);
            if (el) {
                Toast.getOrCreateInstance(el, { delay: 3000 }).show();
            }
        });
    };

    return {
        registry,
        ensureToast,
        getToast,
        showToast,
    };
});
