<script setup>
import { computed, useAttrs } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  options: { type: Array, default: () => [] }, // [{ value: 1, label: 'Option 1' }]
  error: { type: String, default: '' },
  placeholder: { type: String, default: 'Chọn một tùy chọn' },
  required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);
const attrs = useAttrs();

const wrapperClasses = computed(() => [
  'base-select',
  { 'base-select--has-error': !!props.error },
]);

const onChange = (event) => {
  emit('update:modelValue', event.target.value);
  emit('change', event.target.value);
};
</script>

<template>
  <div :class="wrapperClasses">
    <label v-if="label" class="base-select__label">
      {{ label }}
      <span v-if="required" class="base-select__required">*</span>
    </label>
    
    <div class="base-select__inner">
      <select
        :value="modelValue"
        @change="onChange"
        class="base-select__field"
        v-bind="attrs"
      >
        <option value="" disabled>{{ placeholder }}</option>
        <option 
          v-for="(opt, i) in options" 
          :key="i" 
          :value="opt.value !== undefined ? opt.value : opt"
        >
          {{ opt.label !== undefined ? opt.label : opt }}
        </option>
      </select>
      <!-- Custom Chevron Icon -->
      <svg class="base-select__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </div>
    
    <span v-if="error" class="base-select__error-msg">{{ error }}</span>
  </div>
</template>

<style scoped>
.base-select {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.base-select__label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-main);
}

.base-select__required {
  color: var(--error, #ba1a1a);
  margin-left: 2px;
}

.base-select__inner {
  position: relative;
  display: flex;
  align-items: center;
}

.base-select__field {
  width: 100%;
  padding: 10px 40px 10px 14px;
  font-family: inherit;
  font-size: 1rem;
  color: var(--text-main);
  background-color: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: var(--radius-sm, 8px);
  outline: none;
  transition: all 0.2s ease;
  appearance: none;
  cursor: pointer;
}

.base-select__field:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-fixed, #ffd9de);
}

.base-select--has-error .base-select__field {
  border-color: var(--error, #ba1a1a);
}

.base-select--has-error .base-select__field:focus {
  box-shadow: 0 0 0 3px rgba(186, 26, 26, 0.15);
}

.base-select__chevron {
  position: absolute;
  right: 14px;
  pointer-events: none;
  color: var(--text-light, #94a3b8);
  transition: transform 0.2s;
}

.base-select__field:focus + .base-select__chevron {
  color: var(--primary);
  transform: rotate(180deg);
}

.base-select__error-msg {
  font-size: 0.85rem;
  color: var(--error, #ba1a1a);
  animation: slide-down 0.2s ease;
}

@keyframes slide-down {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
