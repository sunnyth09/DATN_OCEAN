<script setup>
import ProductCard from '@/components/ProductCard.vue';
import ProductSkeleton from '@/components/ProductSkeleton.vue';
defineProps(['brands']);
</script>
<template>
        <section class="py-5 border-top">
            <div class="container">
                <div class="text-center mb-4">
                    <h2 class="section-title mb-2">THƯƠNG HIỆU ĐỐI TÁC</h2>
                    <p class="section-subtitle">Chúng tôi phân phối chính hãng từ các thương hiệu hàng đầu thế giới</p>
                </div>
            </div>
            <div class="brands-marquee-wrap">
                <div class="brands-track">
                    <div v-for="(brand, idx) in [...brands, ...brands]" :key="idx + '-brand'" class="brand-logo-item">
                        <img :src="brand.logo" :alt="brand.name" :title="brand.name" loading="lazy" />
                    </div>
                </div>
            </div>
        </section>
</template>


<style scoped>
.brands-marquee-wrap {
    overflow: hidden;
    mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent);
    -webkit-mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent);
}

.brands-track {
    display: flex;
    align-items: center;
    gap: 60px;
    animation: marqueeScroll 22s linear infinite;
    width: max-content;
}

.brands-track:hover {
    animation-play-state: paused;
}

@keyframes marqueeScroll {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-50%);
    }
}

.brand-logo-item {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    filter: grayscale(100%) opacity(.5);
    transition: filter .3s;
    flex-shrink: 0;
}

.brand-logo-item:hover {
    filter: grayscale(0%) opacity(1);
}

.brand-logo-item img {
    height: 36px;
    width: auto;
    max-width: 120px;
    object-fit: contain;
}
</style>
