<script setup>
import { onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  mediaUrl: {
    type: String,
    default: ''
  },
  mediaType: {
    type: String, // 'image' or 'video'
    default: 'image'
  }
});

const emit = defineEmits(['close']);

const close = () => {
  emit('close');
};

// Lock body scroll when preview modal is open
onMounted(() => {
  document.body.style.overflow = 'hidden';
});

onBeforeUnmount(() => {
  document.body.style.overflow = '';
});
</script>

<template>
  <Transition name="fade">
    <div v-if="show" class="media-preview-overlay" @click="close">
      <div class="media-preview-wrapper" @click.stop>
        <button class="media-preview-close" @click="close">&times;</button>
        
        <img v-if="mediaType === 'image'" :src="mediaUrl" alt="Media Preview" class="media-preview-content" />
        <video v-else-if="mediaType === 'video'" :src="mediaUrl" controls autoplay class="media-preview-content" />
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.media-preview-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(8px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.media-preview-wrapper {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  background: #0f172a;
}

.media-preview-content {
  max-width: 100%;
  max-height: 90vh;
  object-fit: contain;
}

.media-preview-close {
  position: absolute;
  top: 16px;
  right: 16px;
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  font-size: 24px;
  line-height: 32px;
  text-align: center;
  cursor: pointer;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.media-preview-close:hover {
  background: rgba(225, 29, 72, 0.9);
}

/* Vue transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
