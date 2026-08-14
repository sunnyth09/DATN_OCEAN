import { defineStore } from 'pinia';
import { ref } from 'vue';
import { loyaltyService } from '@/services/loyaltyService';

export const useLoyaltyStore = defineStore('loyalty', () => {
    const currentBalance = ref(0);
    const isLoading = ref(false);

    const setBalance = (balance) => {
        currentBalance.value = balance;
    };

    const fetchBalance = async () => {
        const token = sessionStorage.getItem("auth_token");
        if (!token) return;
        
        isLoading.value = true;
        try {
            const res = await loyaltyService.getSummary();
            currentBalance.value = res.data?.data?.current_balance ?? 0;
        } catch (e) {
            currentBalance.value = 0;
        } finally {
            isLoading.value = false;
        }
    };

    return { currentBalance, isLoading, setBalance, fetchBalance };
});
