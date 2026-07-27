<script setup>
import { computed } from "vue";

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  width: {
    type: [Number, String],
    default: 24,
  },
  height: {
    type: [Number, String],
    default: 24,
  },
  strokeWidth: {
    type: [Number, String],
    default: 1.8,
  },
});

const icons = {
  shipping: {
    viewBox: "0 0 24 24",
    paths: [
      { type: "rect", x: "1", y: "3", width: "15", height: "13", rx: "2" },
      { type: "path", d: "M16 8h4l3 4v4h-7V8z" },
      { type: "circle", cx: "5.5", cy: "18.5", r: "2.5" },
      { type: "circle", cx: "18.5", cy: "18.5", r: "2.5" },
    ],
  },
  return: {
    viewBox: "0 0 24 24",
    paths: [
      { type: "polyline", points: "1 4 1 10 7 10" },
      { type: "path", d: "M3.51 15a9 9 0 102.13-9.36L1 10" },
    ],
  },
  payment: {
    viewBox: "0 0 24 24",
    paths: [
      { type: "rect", x: "3", y: "11", width: "18", height: "11", rx: "2", ry: "2" },
      { type: "path", d: "M7 11V7a5 5 0 0110 0v4" },
    ],
  },
  shield: {
    viewBox: "0 0 24 24",
    paths: [
      { type: "path", d: "M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" },
    ],
  },
  heart: {
    viewBox: "0 0 24 24",
    paths: [
      { type: "path", d: "M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" },
    ],
  },
};

const currentIcon = computed(() => icons[props.name] || null);
</script>

<template>
  <svg
    v-if="currentIcon"
    :width="width"
    :height="height"
    :viewBox="currentIcon.viewBox"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="app-icon"
  >
    <template v-for="(elem, index) in currentIcon.paths" :key="index">
      <rect
        v-if="elem.type === 'rect'"
        :x="elem.x"
        :y="elem.y"
        :width="elem.width"
        :height="elem.height"
        :rx="elem.rx"
        :ry="elem.ry"
      />
      <polyline
        v-else-if="elem.type === 'polyline'"
        :points="elem.points"
      />
      <circle
        v-else-if="elem.type === 'circle'"
        :cx="elem.cx"
        :cy="elem.cy"
        :r="elem.r"
      />
      <path
        v-else-if="elem.type === 'path'"
        :d="elem.d"
      />
    </template>
  </svg>
</template>

<style scoped>
.app-icon {
  display: inline-block;
  vertical-align: middle;
  flex-shrink: 0;
}
</style>
