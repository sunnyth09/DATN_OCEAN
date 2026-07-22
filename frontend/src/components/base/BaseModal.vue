<script setup>
import { computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  size: { type: String, default: 'md' }, // sm, md, lg
  closeOnOutsideClick: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'close']);

const close = () => {
  emit('update:modelValue', false);
  emit('close');
};

const handleBackdropClick = (e) => {
  if (props.closeOnOutsideClick && e.target.classList.contains('base-modal-overlay')) {
    close();
  }
};

const handleEscape = (e) => {
  if (e.key === 'Escape' && props.modelValue) {
    close();
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape);
});

// Prevent body scroll when open
watch(() => props.modelValue, (val) => {
  if (val) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});
</script>

<template>
  <Teleport to="body">
    <Transition name="base-modal">
      <div v-if="modelValue" class="base-modal-overlay" @mousedown="handleBackdropClick">
        <div class="base-modal-content" :class="`base-modal--${size}`">
          <!-- Header -->
          <div class="base-modal-header">
            <h3 class="base-modal-title">
              <slot name="title">{{ title }}</slot>
            </h3>
            <button class="base-modal-close" @click="close" aria-label="Close">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
          
          <!-- Body -->
          <div class="base-modal-body">
            <slot></slot>
          </div>
          
          <!-- Footer -->
          <div v-if="$slots.footer" class="base-modal-footer">
            <slot name="footer"></slot>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.base-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 20px;
}

.base-modal-content {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: var(--radius-md, 12px);
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  transform: scale(1);
}

.base-modal--sm { max-width: 400px; }
.base-modal--md { max-width: 550px; }
.base-modal--lg { max-width: 800px; }

.base-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.base-modal-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-main);
}

.base-modal-close {
  background: transparent;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s, color 0.2s;
}

.base-modal-close:hover {
  background: var(--surface-container);
  color: var(--error);
}

.base-modal-body {
  padding: 20px;
  overflow-y: auto;
}

.base-modal-footer {
  padding: 16px 20px;
  border-top: 1px solid var(--border-color, #e2e8f0);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 0 0 var(--radius-md, 12px) var(--radius-md, 12px);
}

/* Transitions */
.base-modal-enter-active,
.base-modal-leave-active {
  transition: opacity 0.3s ease;
}

.base-modal-enter-active .base-modal-content,
.base-modal-leave-active .base-modal-content {
  transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.base-modal-enter-from,
.base-modal-leave-to {
  opacity: 0;
}

.base-modal-enter-from .base-modal-content,
.base-modal-leave-to .base-modal-content {
  transform: scale(0.95) translateY(10px);
}
</style>
