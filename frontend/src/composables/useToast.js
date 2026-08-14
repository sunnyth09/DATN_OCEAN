import { computed } from 'vue';
import { useToastStore } from '@/stores/toast';

export function useToast(toastId = 'appToast') {
    const toastStore = useToastStore();

    const toast = computed(() => toastStore.getToast(toastId).value);
    const showToast = (message, type = 'success', data = null) => {
        toastStore.showToast(toastId, message, type, data);
    };

    return { toast, showToast };
}
