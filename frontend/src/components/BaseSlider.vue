<script setup>
import { Swiper, SwiperSlide } from 'swiper/vue'

import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import 'swiper/css/effect-fade'

defineProps({
    items: {
        type: Array,
        default: () => []
    },
    options: {
        type: Object,
        default: () => ({})
    }
})
</script>
<template>
    <Swiper v-bind="options" class="base-slider">
        <SwiperSlide v-for="item in items" :key="item.id">
            <slot name="slide" :item="item">
                <img :src="item.image" :alt="item.title" class="slider-image" />
            </slot>
        </SwiperSlide>
    </Swiper>
</template>



<style scoped>
.base-slider {
    width: 100%;
}

.slider-image {
    width: 100%;
    height: 500px;
    object-fit: cover;
}
</style>
