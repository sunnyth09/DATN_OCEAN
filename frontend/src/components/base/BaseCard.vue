<script setup>
// Card dùng chung dựa trên design token, tự hỗ trợ dark mode admin.
// Dùng cho code MỚI. Không thay các .card/.ocean-card hiện có.
defineProps({
  // padding: none | sm | md | lg
  padding: { type: String, default: 'md' },
  // bỏ shadow nếu cần card phẳng
  flat: { type: Boolean, default: false },
});
</script>

<template>
  <div class="base-card" :class="[`base-card--pad-${padding}`, { 'base-card--flat': flat }]">
    <div v-if="$slots.header" class="base-card__header">
      <slot name="header" />
    </div>
    <slot />
    <div v-if="$slots.footer" class="base-card__footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<style scoped>
.base-card {
  background: var(--card-bg);
  border: 1px solid var(--border-subtle, var(--border-color));
  border-radius: var(--radius-lg, 1rem);
  box-shadow: var(--shadow-card);
  color: var(--text-main);
}

.base-card--flat {
  box-shadow: none;
}

.base-card--pad-none { --_pad: 0px; padding: 0; }
.base-card--pad-sm { --_pad: 12px; padding: 12px; }
.base-card--pad-md { --_pad: 20px; padding: 20px; }
.base-card--pad-lg { --_pad: 32px; padding: 32px; }

.base-card__header {
  margin: calc(-1 * var(--_pad, 0px)) calc(-1 * var(--_pad, 0px)) 16px;
  padding: 0 var(--_pad, 0px) 12px;
  border-bottom: 1px solid var(--border-subtle, var(--border-color));
  font-weight: 700;
}

.base-card__footer {
  margin: 16px calc(-1 * var(--_pad, 0px)) calc(-1 * var(--_pad, 0px));
  padding: 12px var(--_pad, 0px) 0;
  border-top: 1px solid var(--border-subtle, var(--border-color));
}
</style>
