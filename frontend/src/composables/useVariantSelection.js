import { ref, computed } from "vue";
import api from "@/axios";
import { useToast } from "@/composables/useToast";

export function useVariantSelection() {
    const { showToast } = useToast();
    const showVariantModal = ref(false);
    const variants = ref([]);
    const hasFetchedVariants = ref(false);
    const selectedColor = ref(null);
    const selectedSize = ref(null);
    const quantity = ref(1);

    const normalizeQuantity = (value) => {
        const parsed = Number.parseInt(String(value ?? '').replace(/[^0-9]/g, ''), 10);
        return Number.isSafeInteger(parsed) ? parsed : 1;
    };

    const uniqueColors = computed(() => {
        return [...new Set(variants.value.map(v => v.color).filter(Boolean))];
    });

    const hasColors = computed(() => uniqueColors.value.length > 0);

    const availableSizes = computed(() => {
        const vars = variants.value;
        if (!vars.length) return [];

        const filtered = selectedColor.value
            ? vars.filter(v => v.color === selectedColor.value)
            : vars;

        const sizeMap = {};
        filtered.forEach(v => {
            const key = v.size || '__no_size__';
            if (!sizeMap[key]) sizeMap[key] = { size: v.size, stock: 0, variant_id: v.variant_id };
            sizeMap[key].stock += v.stock;
            sizeMap[key].variant_id = v.variant_id;
        });
        return Object.values(sizeMap);
    });

    const selectedVariant = computed(() => {
        const vars = variants.value;
        const color = selectedColor.value;
        const size = selectedSize.value;

        if (!vars.length) return null;

        if (!hasColors.value && size) {
            return vars.find(v => v.size === size) || null;
        }
        if (color && size) {
            return vars.find(v => v.color === color && v.size === size) || null;
        }
        if (color && !availableSizes.value.some(s => s.size)) {
            return vars.find(v => v.color === color) || null;
        }
        return null;
    });

    const increaseQuantity = () => {
        if (selectedVariant.value && quantity.value < selectedVariant.value.stock) {
            quantity.value++;
        } else if (!selectedVariant.value) {
            quantity.value++;
        } else {
            showToast(`Chỉ còn ${selectedVariant.value.stock} sản phẩm trong kho!`, "warning");
        }
    };

    const decreaseQuantity = () => {
        if (quantity.value > 1) quantity.value--;
    };

    const selectColor = (color) => {
        selectedColor.value = color;
        const currentSizeOpt = availableSizes.value.find(s => s.size === selectedSize.value);
        if (!currentSizeOpt || currentSizeOpt.stock <= 0) {
            const firstAvailable = availableSizes.value.find(s => s.stock > 0);
            selectedSize.value = firstAvailable ? firstAvailable.size : null;
        }
        quantity.value = 1;
    };

    const fetchVariants = async (productId, defaultVariantId) => {
        try {
            const res = await api.get(`/products/${productId}/variants`);
            if (res.data?.data) {
                variants.value = res.data.data.map(v => ({
                    ...v,
                    stock: Number(v.stock || 0)
                }));
                hasFetchedVariants.value = true;
                
                if (variants.value.length === 1) {
                    selectedColor.value = variants.value[0].color;
                    selectedSize.value = variants.value[0].size;
                } else if (defaultVariantId) {
                    const defaultVar = variants.value.find(v => v.variant_id === defaultVariantId);
                    if (defaultVar) {
                        selectedColor.value = defaultVar.color;
                        selectedSize.value = defaultVar.size;
                    }
                }
            }
        } catch (error) {
            showToast("Lỗi khi tải thông tin sản phẩm.", "danger");
            throw error;
        }
    };

    return {
        showVariantModal,
        variants,
        hasFetchedVariants,
        selectedColor,
        selectedSize,
        quantity,
        normalizeQuantity,
        increaseQuantity,
        decreaseQuantity,
        uniqueColors,
        hasColors,
        availableSizes,
        selectedVariant,
        selectColor,
        fetchVariants
    };
}
