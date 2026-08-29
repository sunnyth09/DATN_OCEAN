<script setup>
defineProps(['featuredProduct', 'sideCategories', 'isLoadingFeatured', 'isLoadingCategories']);
</script>
<template>
        <section class="py-5 reveal-on-scroll">
            <div class="container">
                <div class="d-flex align-items-end justify-content-between mb-4">
                    <div>
                        <h2 class="section-title mb-1">TRANG BỊ THIẾT YẾU</h2>
                        <p class="section-subtitle mb-0">Lựa chọn hàng đầu cho vận động viên.</p>
                    </div>
                    <router-link to="/product" class="link-more d-flex align-items-center gap-1">
                        Xem tất cả
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </router-link>
                </div>

                <!-- Loading -->
                <div v-if="isLoadingCategories || isLoadingFeatured" class="row g-4">
                    <div class="col-lg-7">
                        <div class="skeleton-pulse" style="min-height:420px;border-radius:16px;"></div>
                    </div>
                    <div class="col-lg-5">
                        <div class="row g-4 h-100">
                            <div class="col-12">
                                <div class="skeleton-pulse" style="min-height:190px;border-radius:16px;"></div>
                            </div>
                            <div class="col-12">
                                <div class="skeleton-pulse" style="min-height:190px;border-radius:16px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div v-else class="row g-4">
                    <!-- Big featured product -->
                    <div class="col-lg-7" v-if="featuredProduct">
                        <div class="equip-big-card h-100">
                            <div class="equip-big-img">
                                <img :src="featuredProduct.image" :alt="featuredProduct.name" />
                            </div>
                            <div class="d-flex flex-column align-items-start gap-3 mt-4">
                                <span v-if="featuredProduct.discount_percent > 0" class="equip-badge">
                                    Giảm {{ featuredProduct.discount_percent }}%
                                </span>
                                <span v-else-if="featuredProduct.badge" class="equip-badge">{{ featuredProduct.badge
                                    }}</span>
                                <h3 class="equip-big-name mb-0">{{ featuredProduct.name }}</h3>
                                <p class="equip-big-desc mb-0">
                                    <span v-if="featuredProduct.category_name">{{ featuredProduct.category_name }}
                                        &middot;
                                    </span>
                                    <strong class="text-danger">{{ featuredProduct.price }}</strong>
                                </p>
                                <router-link :to="'/product/' + (featuredProduct.slug || featuredProduct.id)"
                                    class="btn-buy-now btn">
                                    Mua ngay
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Side category cards -->
                    <div class="col-lg-5">
                        <div class="d-flex flex-column gap-4 h-100">
                            <template v-if="sideCategories.length > 0">
                                <router-link v-for="cat in sideCategories" :key="cat.id"
                                    :to="'/product?category=' + cat.id" class="equip-small-card flex-fill">
                                    <div class="equip-small-info">
                                        <h4 class="equip-small-name mb-1">{{ cat.name }}</h4>
                                        <span class="equip-small-link">
                                            Xem thêm
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="3">
                                                <path d="M9 18l6-6-6-6" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="equip-small-img" v-if="cat.image">
                                        <img :src="cat.image" :alt="cat.name" />
                                    </div>
                                </router-link>
                            </template>
                            <div v-if="sideCategories.length < 2"
                                class="equip-small-card equip-small-card--empty flex-fill">
                                <div class="text-center">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                                        <circle cx="10" cy="10" r="5.5" stroke="#E63B6F" stroke-width="2.5" />
                                        <path d="M14 14L18.5 18.5" stroke="#E63B6F" stroke-width="3"
                                            stroke-linecap="round" />
                                        <circle cx="17.5" cy="12.5" r="2.5" fill="#E63B6F" />
                                    </svg>
                                    <p class="mb-0 mt-2 fw-bold small">Khám phá thêm</p>
                                    <p class="mb-0 text-muted" style="font-size:.8rem;">Xem tất cả danh mục</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
</template>


<style scoped>
.equip-big-card {
    background: var(--card-bg);
    border: none;
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05), 0 0 0 1px rgba(0, 0, 0, 0.02);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.equip-big-card:hover {
    box-shadow: 0 25px 50px rgba(230, 59, 111, 0.12);
    transform: translateY(-6px);
}

.equip-big-img {
    width: 100%;
    height: 340px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    overflow: hidden;
}

.equip-big-img img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    mix-blend-mode: multiply;
    transition: transform .4s;
}

.equip-big-card:hover .equip-big-img img {
    transform: scale(1.05);
}

.equip-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--primary), #ff6b9d);
    color: #fff;
    font-size: .8rem;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 20px;
}

.equip-big-name {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
}

.equip-big-desc {
    font-size: .95rem;
    color: #636E72;
    line-height: 1.5;
}

.btn-buy-now {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--primary);
    color: #fff;
    height: 42px;
    padding: 0 22px;
    border-radius: 8px;
    font-weight: 700;
    font-size: .9rem;
    text-decoration: none;
    transition: all .2s;
    box-shadow: 0 4px 14px rgba(230, 59, 111, .35);
}

.btn-buy-now:hover {
    background: #d82f65;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(230, 59, 111, .45);
    color: #fff;
}

.equip-small-card {
    background: var(--card-bg);
    border: none;
    border-radius: 20px;
    padding: 24px;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    min-height: 160px;
    text-decoration: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04), 0 0 0 1px rgba(0, 0, 0, 0.02);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    position: relative;
}

.equip-small-card:hover {
    box-shadow: 0 20px 40px rgba(230, 59, 111, 0.12);
    transform: translateY(-5px);
}

.equip-small-card--empty {
    flex-direction: column;
    justify-content: center;
    background: #f8f9fa;
}

.equip-small-img {
    position: static;
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    transform: none;
    flex-shrink: 0;
}

.equip-small-img img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    mix-blend-mode: multiply;
    transition: transform .4s ease;
}

.equip-small-card:hover .equip-small-img img {
    transform: scale(1.1);
}

.equip-small-info {
    position: relative;
    z-index: 2;
    flex: 1;
    padding-right: 16px;
}

.equip-small-name {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 8px !important;
}

.equip-small-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--primary);
    font-size: .85rem;
    font-weight: 700;
}
</style>
