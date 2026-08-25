<template>
  <div class="vpicker-card">
    <h4 class="vpicker-title">Chọn phiên bản</h4>
    <div class="vpicker-list">
      <div
        v-for="variant in product.variants"
        :key="variant.variant_id"
        class="vpicker-item"
        :class="{ 'is-unavailable': variant.stock <= 0 }"
      >
        <div class="vpicker-top">
          <span class="vpicker-name">{{ variant.variant_name || [variant.color, variant.size].filter(Boolean).join(' / ') }}</span>
          <div v-if="variant.stock > 0" class="vpicker-actions">
            <button class="vpicker-add-btn" @click="$emit('add-variant', product, variant)">Thêm giỏ</button>
            <button class="vpicker-buy-btn" @click="$emit('buy-now', product, variant)">Mua ngay</button>
          </div>
          <span v-else class="vpicker-sold-out">Hết hàng</span>
        </div>
        <div class="vpicker-bottom">
          <span class="vpicker-price" :class="{ 'on-sale': variant.has_sale }">{{ variant.price }}</span>
          <span v-if="variant.compare_at_price" class="vpicker-old-price">{{ variant.compare_at_price }}</span>
          <span v-if="variant.stock > 0 && variant.stock <= 5" class="vpicker-low">Còn {{ variant.stock }}</span>
        </div>
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

defineEmits(['add-variant', 'buy-now']);
</script>

<style scoped>
.vpicker-card {
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  padding: 10px 12px;
  margin-top: 6px;
}
.vpicker-title {
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px;
}
.vpicker-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.vpicker-item {
  background: #fff;
  border: 1px solid #f1f5f9;
  border-radius: 8px;
  padding: 8px 10px;
  transition: border-color 0.2s;
}
.vpicker-item:hover {
  border-color: #fbcfe8;
}
.vpicker-item.is-unavailable {
  opacity: 0.45;
  pointer-events: none;
}
.vpicker-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.vpicker-name {
  font-size: 0.78rem;
  font-weight: 600;
  color: #334155;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vpicker-actions {
  display: flex;
  gap: 5px;
  flex-shrink: 0;
}
.vpicker-add-btn, .vpicker-buy-btn {
  padding: 3px 10px;
  border-radius: 14px;
  font-size: 0.68rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  border: 1px solid transparent;
}
.vpicker-add-btn {
  background: #f0fdf4;
  border-color: #86efac;
  color: #15803d;
}
.vpicker-add-btn:hover {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}
.vpicker-buy-btn {
  background: linear-gradient(135deg, #E63B6F, #d62e5f);
  border-color: #E63B6F;
  color: #fff;
}
.vpicker-buy-btn:hover {
  background: linear-gradient(135deg, #d62e5f, #b50c4d);
  box-shadow: 0 2px 6px rgba(230, 59, 111, 0.3);
}
.vpicker-sold-out {
  flex-shrink: 0;
  font-size: 0.68rem;
  color: #9ca3af;
  font-style: italic;
}
.vpicker-bottom {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin-top: 3px;
}
.vpicker-price {
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
}
.vpicker-price.on-sale {
  color: #dc2626;
}
.vpicker-old-price {
  font-size: 0.68rem;
  color: #9ca3af;
  text-decoration: line-through;
}
.vpicker-low {
  font-size: 0.62rem;
  font-weight: 700;
  color: #d97706;
  background: #fef3c7;
  border: 1px solid #fcd34d;
  padding: 1px 6px;
  border-radius: 999px;
  white-space: nowrap;
  margin-left: auto;
}
</style>

