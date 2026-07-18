<template>
  <div class="product-detail-card">
    <h4 class="pd-name">Chọn màu/size cho {{ product.name }}</h4>
    <div class="pd-variants">
      <div
        v-for="variant in product.variants"
        :key="variant.variant_id"
        class="pd-variant-row"
        :class="{ 'variant-unavailable': variant.stock <= 0 }"
      >
        <span class="pd-variant-name">{{ variant.variant_name || [variant.color, variant.size].filter(Boolean).join(' / ') }}</span>
        <span class="pd-variant-price" :class="{ 'has-sale': variant.has_sale }">
          {{ variant.price }}
          <small v-if="variant.compare_at_price" class="pd-compare-price">{{ variant.compare_at_price }}</small>
        </span>
        <span class="pd-variant-status" :class="variant.stock > 0 ? 'in-stock' : 'out-stock'">{{ variant.status }}</span>
        <span v-if="variant.stock > 0 && variant.stock <= 5" class="stock-low-chip">Còn {{ variant.stock }}</span>
        <button v-if="variant.stock > 0" class="mini-add-btn" @click="$emit('add-variant', product, variant)">Thêm</button>
        <span v-else class="out-stock-label">Hết</span>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  product: {
    type: Object,
    required: true
  }
});

defineEmits(['add-variant']);
</script>

<style scoped>
.product-detail-card { border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; padding: 12px 14px; margin-top: 6px; }
.pd-name { font-size: 0.9rem; font-weight: 700; color: #1e293b; margin: 0 0 10px; line-height: 1.3; }
.pd-variants { display: flex; flex-direction: column; gap: 6px; }
.pd-variant-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.75rem; padding: 6px 8px; background: white; border: 1px solid #f1f5f9; border-radius: 6px; }
.variant-unavailable { opacity: 0.5; pointer-events: none; }
.variant-unavailable .pd-variant-name { text-decoration: line-through; text-decoration-color: #9ca3af; }
.pd-variant-name { font-weight: 500; color: #334155; flex: 1; }
.pd-variant-price { font-weight: 600; color: #475569; }
.pd-variant-price.has-sale { color: #dc2626; font-weight: 700; }
.pd-compare-price { font-size: 0.68rem; color: #9ca3af; text-decoration: line-through; margin-left: 4px; font-weight: 400; }
.pd-variant-status { font-size: 0.68rem; font-weight: 600; }
.pd-variant-status.in-stock { color: #16a34a; }
.pd-variant-status.out-stock { color: #dc2626; }
.stock-low-chip { font-size: 0.65rem; font-weight: 700; color: #d97706; background: #fef3c7; border: 1px solid #fcd34d; padding: 1px 6px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
.mini-add-btn { background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.mini-add-btn:hover { background: #E63B6F; color: white; border-color: #E63B6F; }
.out-stock-label { font-size: 0.68rem; color: #9ca3af; font-style: italic; flex-shrink: 0; }
</style>
