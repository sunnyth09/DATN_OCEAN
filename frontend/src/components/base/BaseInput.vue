<script setup>
import { computed, useAttrs } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  error: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const attrs = useAttrs();

const wrapperClasses = computed(() => [
  'base-input',
  { 'base-input--has-error': !!props.error },
]);

const onInput = (event) => {
  emit('update:modelValue', event.target.value);
};
</script>

<template>
  <div :class="wrapperClasses">
    <label v-if="label" class="base-input__label">
      {{ label }}
      <span v-if="required" class="base-input__required">*</span>
    </label>
    
    <div class="base-input__inner">
      <input
        :type="type"
        :value="modelValue"
        @input="onInput"
        :placeholder="placeholder"
        class="base-input__field"
        v-bind="attrs"
      />
    </div>
    
    <span v-if="error" class="base-input__error-msg">{{ error }}</span>
  </div>
</template>

<style scoped>
.base-input {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.base-input__label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-main);
}

.base-input__required {
  color: var(--error, #ba1a1a);
  margin-left: 2px;
}

.base-input__field {
  width: 100%;
  padding: 10px 14px;
  font-family: inherit;
  font-size: 1rem;
  color: var(--text-main);
  background-color: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: var(--radius-sm, 8px);
  outline: none;
  transition: all 0.2s ease;
}

.base-input__field::placeholder {
  color: var(--text-light, #94a3b8);
}

.base-input__field:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-fixed, #ffd9de);
}

.base-input--has-error .base-input__field {
  border-color: var(--error, #ba1a1a);
}

.base-input--has-error .base-input__field:focus {
  box-shadow: 0 0 0 3px rgba(186, 26, 26, 0.15);
}

.base-input__error-msg {
  font-size: 0.85rem;
  color: var(--error, #ba1a1a);
  animation: slide-down 0.2s ease;
}

@keyframes slide-down {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
