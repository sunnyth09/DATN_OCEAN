<script setup>
// Nút dùng chung dựa trên design token (var(--primary)...), tự hỗ trợ dark mode admin.
// Dùng cho code MỚI. Không thay các .btn-* scoped hiện có.
import { computed } from 'vue';

const props = defineProps({
  // primary | secondary | outline | danger | ghost
  variant: { type: String, default: 'primary' },
  // sm | md | lg
  size: { type: String, default: 'md' },
  block: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  // thẻ render: 'button' | 'a' | RouterLink...
  as: { type: [String, Object], default: 'button' },
  type: { type: String, default: 'button' },
});

const classes = computed(() => [
  'base-btn',
  `base-btn--${props.variant}`,
  `base-btn--${props.size}`,
  { 'base-btn--block': props.block, 'base-btn--loading': props.loading },
]);

const isNativeButton = computed(() => props.as === 'button');
</script>

<template>
  <component
    :is="as"
    :class="classes"
    :type="isNativeButton ? type : undefined"
    :disabled="isNativeButton ? (disabled || loading) : undefined"
    :aria-busy="loading ? 'true' : undefined"
  >
    <span v-if="loading" class="base-btn__spinner" aria-hidden="true"></span>
    <slot />
  </component>
</template>

<style scoped>
.base-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: var(--radius-sm, 8px);
  border: 1px solid transparent;
  font-family: inherit;
  font-weight: 600;
  line-height: 1.2;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.1s;
  white-space: nowrap;
}

.base-btn:disabled,
.base-btn--loading {
  opacity: 0.6;
  cursor: not-allowed;
  pointer-events: none;
}

.base-btn:active {
  transform: translateY(1px);
}

/* Sizes */
.base-btn--sm { padding: 6px 14px; font-size: 0.8rem; }
.base-btn--md { padding: 10px 20px; font-size: 0.9rem; }
.base-btn--lg { padding: 13px 26px; font-size: 1rem; }

.base-btn--block { width: 100%; }

/* Variants (token-based) */
.base-btn--primary {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}
.base-btn--primary:hover {
  background: var(--primary-dark);
  border-color: var(--primary-dark);
}

.base-btn--secondary {
  background: var(--surface-container);
  color: var(--text-main);
  border-color: var(--border-color);
}
.base-btn--secondary:hover {
  background: var(--surface-container-low);
}

.base-btn--outline {
  background: transparent;
  color: var(--primary);
  border-color: var(--primary);
}
.base-btn--outline:hover {
  background: var(--primary);
  color: #fff;
}

.base-btn--danger {
  background: var(--error, #ba1a1a);
  color: #fff;
  border-color: var(--error, #ba1a1a);
}
.base-btn--danger:hover {
  filter: brightness(0.92);
}

.base-btn--ghost {
  background: transparent;
  color: var(--text-main);
  border-color: transparent;
}
.base-btn--ghost:hover {
  background: var(--hover-bg);
  color: var(--primary);
}

.base-btn__spinner {
  width: 1em;
  height: 1em;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: base-btn-spin 0.7s linear infinite;
}

@keyframes base-btn-spin {
  to { transform: rotate(360deg); }
}
</style>
