// src/composables/useSwiperOptions.js

import { Navigation, Pagination, Autoplay, EffectFade } from 'swiper/modules'

export function useSwiperOptions(customOptions = {}) {
  const defaultOptions = {
    modules: [Navigation, Pagination, Autoplay, EffectFade],
    slidesPerView: 1,
    loop: true,
    effect: 'fade',
    autoplay: {
      delay: 4000,
      disableOnInteraction: false
    },
    pagination: {
      clickable: true
    },
    navigation: true
  }

  return {
    ...defaultOptions,
    ...customOptions
  }
}
