<script setup>
import ProductCard from '@/components/ProductCard.vue';
import ProductSkeleton from '@/components/ProductSkeleton.vue';
defineProps(['activeTab', 'filteredProducts', 'isLoadingFeatured']);
defineEmits(['update:activeTab']);
</script>
<template>
    <section class="py-5 bg-light reveal-on-scroll">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="section-title accent-title mb-2">SẢN PHẨM BÁN CHẠY</h2>
                <p class="section-subtitle">Được yêu thích nhất bởi cộng đồng thể thao Việt Nam</p>
            </div>
            <div class="segmented-control-wrap mb-4">
                <div class="segmented-control">
                    <div class="segmented-bg"
                        :style="{ transform: activeTab === 'sale' ? 'translateX(calc(100% + 4px))' : 'translateX(0)' }">
                    </div>
                    <button class="seg-btn" :class="{ active: activeTab === 'all' }"
                        @click="$emit('update:activeTab', 'all')">
                        Tất cả
                    </button>
                    <button class="seg-btn" :class="{ active: activeTab === 'sale' }"
                        @click="$emit('update:activeTab', 'sale')">
                        Đang Sale
                    </button>
                </div>
            </div>
            <!-- Products Bootstrap row -->
            <div class="row g-4">
                <template v-if="isLoadingFeatured">
                    <div v-for="i in 8" :key="i" class="col-6 col-md-4 col-lg-3">
                        <ProductSkeleton />
                    </div>
                </template>
                <template v-else-if="filteredProducts.length > 0">
                    <div v-for="product in filteredProducts.slice(0, 8)" :key="product.id"
                        class="col-6 col-md-4 col-lg-3">
                        <ProductCard :product="product" />
                    </div>
                </template>
                <template v-else>
                    <div class="col-12 text-center py-5 text-muted">
                        <p>Chưa có sản phẩm nào. Vui lòng quay lại sau!</p>
                    </div>
                </template>
            </div>
            <!-- View more -->
            <div class="text-center mt-5" v-if="filteredProducts.length > 0">
                <router-link to="/product" class="btn-view-more">
                    Xem thêm sản phẩm
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </router-link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.segmented-control-wrap {
    display: flex;
    justify-content: center;
}

.segmented-control {
    position: relative;
    display: inline-flex;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 99px;
    padding: 4px;
    gap: 4px;
}

.segmented-bg {
    position: absolute;
    top: 4px;
    left: 4px;
    width: calc(50% - 6px);
    height: calc(100% - 8px);
    background: var(--primary);
    border-radius: 99px;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.seg-btn {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 110px;
    padding: 8px 16px;
    border: none;
    background: transparent;
    font-size: 0.95rem;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition: color 0.3s;
    border-radius: 99px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1.2;
}

.seg-btn.active {
    color: #fff;
}

.btn-view-more {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 40px;
    border: 2px solid var(--primary);
    color: var(--primary);
    background: transparent;
    border-radius: 99px;
    font-size: .95rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s;
}

.btn-view-more:hover {
    background: var(--primary);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(230, 59, 111, .3);
}
</style>
