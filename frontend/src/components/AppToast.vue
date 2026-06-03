<script setup>
import { computed } from 'vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
  toastId: {
    type: String,
    default: 'appToast',
  },
});

const { toast } = useToast(props.toastId);

const toastClass = computed(() => {
  switch (toast.value.type) {
    case 'error':
    case 'danger':
      return 'text-bg-danger';
    case 'warning':
      return 'text-bg-warning';
    case 'info':
      return 'text-bg-info';
    default:
      return 'text-bg-success';
  }
});

const closeButtonClass = computed(() => {
  switch (toast.value.type) {
    case 'warning':
    case 'info':
      return 'btn-close';
    default:
      return 'btn-close btn-close-white';
  }
});
</script>

<template>
  <div class="toast-container position-fixed app-toast-host">
    <div
      :id="toastId"
      class="toast align-items-center border-0 shadow-sm"
      :class="toastClass"
      role="alert"
      aria-live="assertive"
      aria-atomic="true"
    >
      <div class="d-flex">
        <div class="toast-body">{{ toast.message }}</div>
        <button
          type="button"
          :class="closeButtonClass"
          class="me-2 m-auto"
          data-bs-dismiss="toast"
          aria-label="Close"
        ></button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.app-toast-host {
  top: 85px;
  right: 20px;
  left: auto;
  padding: 0;
  z-index: 1080;
}
</style>
