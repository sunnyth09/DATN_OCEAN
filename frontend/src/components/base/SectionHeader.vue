<script setup>
// Header dùng chung cho mọi section storefront.
// LUẬT CĂN LỀ (chấm dứt việc lúc trái lúc giữa không theo quy tắc):
//  - Section có danh sách + link "Xem thêm"  -> align="left",  link đặt ở slot #action
//  - Section thuần trưng bày (thương hiệu, testimonial) -> align="center"
import { computed } from 'vue';

const props = defineProps({
  // nhãn nhỏ phía trên tiêu đề (vd: "ƯU ĐÃI")
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  // left | center
  align: { type: String, default: 'left' },
  // gạch accent dưới tiêu đề
  accent: { type: Boolean, default: false },
  // đảo màu chữ khi đặt trên nền tối
  onDark: { type: Boolean, default: false },
  // cấp heading để giữ đúng thứ tự a11y trong trang
  level: { type: String, default: 'h2' },
});

const classes = computed(() => [
  'section-header',
  `section-header--${props.align}`,
  { 'section-header--accent': props.accent, 'section-header--on-dark': props.onDark },
]);
</script>

<template>
  <div :class="classes">
    <div class="section-header__text">
      <p v-if="eyebrow" class="section-header__eyebrow">{{ eyebrow }}</p>
      <component :is="level" class="section-header__title">{{ title }}</component>
      <p v-if="subtitle" class="section-header__subtitle">{{ subtitle }}</p>
    </div>

    <div v-if="$slots.action" class="section-header__action">
      <slot name="action" />
    </div>
  </div>
</template>

<style scoped>
.section-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-5);
}

.section-header--center {
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.section-header__eyebrow {
  margin: 0 0 var(--space-2);
  font-size: var(--fs-label-sm);
  line-height: var(--lh-label-sm);
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--primary);
}

.section-header__title {
  margin: 0;
  font-size: var(--fs-headline-lg);
  line-height: var(--lh-headline-lg);
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text-main);
}

.section-header__subtitle {
  margin: var(--space-2) 0 0;
  font-size: var(--fs-body-md);
  line-height: var(--lh-body-md);
  color: var(--text-secondary);
  max-width: 60ch;
}

.section-header--center .section-header__subtitle {
  margin-inline: auto;
}

/* Gạch accent */
.section-header--accent .section-header__title::after {
  content: '';
  display: block;
  width: 60px;
  height: 4px;
  margin-top: var(--space-2);
  border-radius: var(--radius-sm);
  background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

.section-header--center.section-header--accent .section-header__title::after {
  margin-inline: auto;
}

/* Trên nền tối */
.section-header--on-dark .section-header__title {
  color: var(--text-on-dark);
}

.section-header--on-dark .section-header__subtitle {
  color: var(--text-on-dark-muted);
}

.section-header__action {
  flex-shrink: 0;
}

@media (max-width: 768px) {
  .section-header {
    /* Ở mobile, action xuống dòng thay vì bị bóp cạnh tiêu đề */
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-3);
    margin-bottom: var(--space-4);
  }

  .section-header--center {
    align-items: center;
  }

  .section-header__title {
    font-size: var(--fs-headline-md);
    line-height: var(--lh-headline-md);
  }
}
</style>
