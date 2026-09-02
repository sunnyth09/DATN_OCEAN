<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { getStorageUrl } from '@/utils/url';

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
  },
  mediaList: {
    type: Array, // Array of { url: string, type: 'image'|'video' } or string URLs
    default: () => []
  },
  initialIndex: {
    type: Number,
    default: 0
  }
});

const emit = defineEmits(['close']);

const activeIndex = ref(0);

const items = computed(() => {
  if (Array.isArray(props.mediaList) && props.mediaList.length > 0) {
    return props.mediaList.map((item) => {
      if (typeof item === 'string') {
        const isVid = /\.(mp4|mov|avi|webm)$/i.test(item);
        return { url: getStorageUrl(item), type: isVid ? 'video' : 'image' };
      }
      const rawUrl = item.url || item.src || '';
      return {
        url: getStorageUrl(rawUrl),
        type: item.type || (/\.(mp4|mov|avi|webm)$/i.test(rawUrl) ? 'video' : 'image')
      };
    });
  }
  if (props.mediaUrl) {
    return [{ url: getStorageUrl(props.mediaUrl), type: props.mediaType || 'image' }];
  }
  return [];
});

const currentMedia = computed(() => {
  if (items.value.length === 0) return { url: '', type: 'image' };
  const idx = Math.max(0, Math.min(activeIndex.value, items.value.length - 1));
  return items.value[idx];
});

const prevMedia = () => {
  if (activeIndex.value > 0) {
    activeIndex.value--;
  }
};

const nextMedia = () => {
  if (activeIndex.value < items.value.length - 1) {
    activeIndex.value++;
  }
};

const close = () => {
  emit('close');
};

const handleKeydown = (e) => {
  if (!props.show) return;
  if (e.key === 'ArrowLeft') {
    prevMedia();
  } else if (e.key === 'ArrowRight') {
    nextMedia();
  } else if (e.key === 'Escape') {
    close();
  }
};

watch(
  () => [props.show, props.initialIndex, props.mediaUrl, props.mediaList],
  ([newShow]) => {
    if (newShow) {
      if (typeof props.initialIndex === 'number' && props.initialIndex >= 0) {
        activeIndex.value = props.initialIndex;
      } else if (props.mediaUrl && items.value.length > 0) {
        const found = items.value.findIndex((i) => i.url === props.mediaUrl);
        activeIndex.value = found >= 0 ? found : 0;
      } else {
        activeIndex.value = 0;
      }
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  },
  { immediate: true }
);

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown);
  document.body.style.overflow = '';
});
</script>

<template>
  <Transition name="fade">
    <div v-if="show" class="media-preview-overlay" @click="close">
      <div class="media-preview-wrapper" @click.stop>
        <div class="media-preview-top-bar">
          <span v-if="items.length > 1" class="media-counter">
            {{ activeIndex + 1 }} / {{ items.length }}
          </span>
          <button class="media-preview-close" @click="close" aria-label="Đóng">&times;</button>
        </div>

        <!-- Prev button (<) -->
        <button
          v-if="items.length > 1 && activeIndex > 0"
          type="button"
          class="nav-btn nav-btn--prev"
          @click.stop="prevMedia"
          aria-label="Ảnh trước"
          title="Ảnh trước (Mũi tên trái)"
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </button>

        <!-- Next button (>) -->
        <button
          v-if="items.length > 1 && activeIndex < items.length - 1"
          type="button"
          class="nav-btn nav-btn--next"
          @click.stop="nextMedia"
          aria-label="Ảnh tiếp theo"
          title="Ảnh tiếp theo (Mũi tên phải)"
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </button>

        <img
          v-if="currentMedia.type === 'image'"
          :key="currentMedia.url"
          :src="currentMedia.url"
          alt="Media Preview"
          class="media-preview-content"
        />
        <video
          v-else-if="currentMedia.type === 'video'"
          :key="currentMedia.url"
          :src="currentMedia.url"
          controls
          autoplay
          class="media-preview-content"
        />
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
  background: rgba(15, 23, 42, 0.88);
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
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
  background: #0f172a;
}

.media-preview-top-bar {
  position: absolute;
  top: 16px;
  left: 16px;
  right: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 30;
  pointer-events: none;
}

.media-counter {
  background: rgba(15, 23, 42, 0.75);
  color: #ffffff;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 700;
  border: 1px solid rgba(255, 255, 255, 0.2);
  pointer-events: auto;
}

.media-preview-close {
  pointer-events: auto;
  background: rgba(15, 23, 42, 0.75);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  font-size: 22px;
  line-height: 1;
  text-align: center;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  margin-left: auto;
}

.media-preview-close:hover {
  background: rgba(225, 29, 72, 0.9);
  border-color: rgba(225, 29, 72, 0.9);
}

.nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 44px;
  height: 44px;
  background: rgba(15, 23, 42, 0.75);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #ffffff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 30;
  transition: all 0.2s ease;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}

.nav-btn:hover {
  background: #e63b6f;
  border-color: #e63b6f;
  transform: translateY(-50%) scale(1.08);
}

.nav-btn--prev {
  left: 16px;
}

.nav-btn--next {
  right: 16px;
}

.media-preview-content {
  max-width: 100%;
  max-height: 85vh;
  object-fit: contain;
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
