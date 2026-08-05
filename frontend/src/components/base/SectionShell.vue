<script setup>
// Khung section dùng chung cho storefront.
// Chuẩn hoá 2 thứ vốn bị mỗi section tự khai báo rời rạc:
//  - nhịp dọc (--section-py)
//  - nền section (default / alt / dark) qua token, không dùng Bootstrap bg-light
// và bọc nội dung trong .os-container để thẳng lề với header/footer.
import { computed } from 'vue';

const props = defineProps({
  // default | alt | dark
  background: { type: String, default: 'default' },
  // normal | tight | none
  spacing: { type: String, default: 'normal' },
  // thẻ render ngữ nghĩa
  as: { type: String, default: 'section' },
  // false khi section cần nội dung tràn viền (vd: marquee)
  contained: { type: Boolean, default: true },
});

const classes = computed(() => [
  'section-shell',
  `section-shell--bg-${props.background}`,
  `section-shell--sp-${props.spacing}`,
]);
</script>

<template>
  <component :is="as" :class="classes">
    <div v-if="contained" class="os-container">
      <slot />
    </div>
    <slot v-else />
  </component>
</template>

<style scoped>
.section-shell {
  width: 100%;
  /* clip thay vì hidden: không tạo scroll container, không phá position: sticky */
  overflow-x: clip;
}

/* Nhịp dọc */
.section-shell--sp-normal {
  padding-block: var(--section-py);
}

.section-shell--sp-tight {
  padding-block: var(--section-py-tight);
}

.section-shell--sp-none {
  padding-block: 0;
}

/* Nền */
.section-shell--bg-default {
  background: var(--background);
  color: var(--text-main);
}

.section-shell--bg-alt {
  background: var(--surface-alt);
  color: var(--text-main);
}

.section-shell--bg-dark {
  background: var(--surface-dark);
  color: var(--text-on-dark);
}

@media (max-width: 768px) {
  /* Thu nhịp dọc trên mobile: 64px quá thoáng ở màn nhỏ */
  .section-shell--sp-normal {
    padding-block: var(--space-5);
  }

  .section-shell--sp-tight {
    padding-block: var(--space-4);
  }
}
</style>
