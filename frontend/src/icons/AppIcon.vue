<script setup>
import { computed } from 'vue';

const props = defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
  strokeWidth: { type: [Number, String], default: 2 },
  title: { type: String, default: '' },
  decorative: { type: Boolean, default: true },
});

const normalizedName = computed(() => props.name.trim().toLowerCase());
const isDecorative = computed(() => props.decorative && !props.title);
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="strokeWidth"
    stroke-linecap="round" stroke-linejoin="round" :aria-hidden="isDecorative ? 'true' : undefined"
    :role="isDecorative ? undefined : 'img'">
    <title v-if="!isDecorative">{{ title || name }}</title>

    <template v-if="normalizedName === 'dashboard'">
      <rect x="3" y="3" width="7" height="7" rx="1" />
      <rect x="14" y="3" width="7" height="7" rx="1" />
      <rect x="3" y="14" width="7" height="7" rx="1" />
      <rect x="14" y="14" width="7" height="7" rx="1" />
    </template>
    <template v-else-if="normalizedName === 'clock'">
      <circle cx="12" cy="12" r="10" />
      <polyline points="12 6 12 12 16 14" />
    </template>
    <template v-else-if="normalizedName === 'order'">
      <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
      <line x1="3" y1="6" x2="21" y2="6" />
      <path d="M16 10a4 4 0 0 1-8 0" />
    </template>
    <template v-else-if="normalizedName === 'pos'">
      <path
        d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
      <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
      <line x1="12" y1="22.08" x2="12" y2="12" />
    </template>
    <template v-else-if="normalizedName === 'store'">
      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
      <polyline points="9 22 9 12 15 12 15 22" />
    </template>
    <template v-else-if="normalizedName === 'users'">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </template>
    <template v-else-if="normalizedName === 'chat'">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
    </template>
    <template v-else-if="normalizedName === 'contact'">
      <path
        d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
    </template>
    <template v-else-if="normalizedName === 'chevron-down'">
      <polyline points="6 9 12 15 18 9" />
    </template>
    <template v-else-if="normalizedName === 'menu'">
      <line x1="3" y1="6" x2="21" y2="6" />
      <line x1="3" y1="12" x2="21" y2="12" />
      <line x1="3" y1="18" x2="21" y2="18" />
    </template>
    <template v-else-if="normalizedName === 'x'">
      <line x1="18" y1="6" x2="6" y2="18" />
      <line x1="6" y1="6" x2="18" y2="18" />
    </template>
    <template v-else-if="normalizedName === 'search'">
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </template>
    <template v-else-if="normalizedName === 'bell'">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
    </template>
    <template v-else-if="normalizedName === 'cart'">
      <circle cx="9" cy="20" r="1.6" />
      <circle cx="18" cy="20" r="1.6" />
      <path d="M3 4h2.2l1.9 9.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.7L20 7H7.1" />
    </template>
    <template v-else-if="normalizedName === 'bag'">
      <path d="M6 7h12l-1 12H7L6 7Z" />
      <path d="M9 9V7a3 3 0 0 1 6 0v2" />
    </template>
    <template v-else-if="normalizedName === 'cart'">
      <circle cx="9" cy="20" r="1.6" />
      <circle cx="18" cy="20" r="1.6" />
      <path d="M3 4h2.2l1.9 9.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.7L20 7H7.1" />
    </template>
    <template v-else-if="normalizedName === 'user'">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
      <circle cx="12" cy="7" r="4" />
    </template>
    <template v-else-if="normalizedName === 'heart'">
      <path
        d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
    </template>
    <template v-else-if="normalizedName === 'folder'">
      <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
    </template>
    <template v-else-if="normalizedName === 'edit'">
      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
    </template>
    <template v-else-if="normalizedName === 'trash'">
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
    </template>
    <template v-else-if="normalizedName === 'check'">
      <polyline points="20 6 9 17 4 12" />
    </template>
    <template v-else-if="normalizedName === 'arrow-right'">
      <path d="M5 12h14" />
      <path d="M12 5l7 7-7 7" />
    </template>
    <template v-else-if="normalizedName === 'shield'">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
    </template>
    <template v-else-if="normalizedName === 'lock'">
      <rect x="4" y="11" width="16" height="10" rx="2" />
      <path d="M8 11V7a4 4 0 0 1 8 0v4" />
    </template>
    <template v-else-if="normalizedName === 'truck'">
      <path d="M10 17H5a2 2 0 0 1-2-2V6h11v11" />
      <path d="M14 9h3l4 4v2a2 2 0 0 1-2 2h-1" />
      <circle cx="7" cy="17" r="2" />
      <circle cx="16" cy="17" r="2" />
    </template>
    <template v-else-if="normalizedName === 'clipboard-list'">
      <rect x="8" y="2" width="8" height="4" rx="1" />
      <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
      <path d="M8 11h.01" />
      <path d="M12 11h4" />
      <path d="M8 16h.01" />
      <path d="M12 16h4" />
    </template>
    <template v-else-if="normalizedName === 'zap'">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
    </template>
    <template v-else-if="normalizedName === 'ticket'">
      <path d="M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
      <path d="M12 4l-2 2" />
      <path d="M12 4l2 2" />
      <path d="M8 12H8.01" />
      <path d="M12 12h.01" />
      <path d="M16 12h.01" />
      <path d="M8 16h.01" />
      <path d="M12 16h.01" />
      <path d="M16 16h.01" />
    </template>
    <template v-else-if="normalizedName === 'voucher'">
      <path d="M4 5h16a2 2 0 0 1 2 2v10a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V7a2 2 0 0 1 2-2z" />
      <path d="M6 2h12" />
      <path d="M10 12H8" />
      <path d="M10 15H8" />
      <path d="M14 12h2" />
      <path d="M14 15h2" />
    </template>
    <template v-else>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v4" />
      <path d="M12 16h.01" />
    </template>
  </svg>
</template>
