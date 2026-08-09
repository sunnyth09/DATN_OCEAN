<script setup>
defineProps(['Categories', 'isLoadingCategories', 'getCatIcon', 'getCatGradient']);
</script>
<template>
    <section class="section-categories py-5 bg-light reveal-on-scroll">
        <div class="container">

            <!-- Quick filters (Trang mục biệt yêu) -->
            <!-- <div class="quick-filters-wrap mb-5">
                <div class="text-center mb-4">
                    <h2 class="section-title mb-1">TRANG PHỤC BẠN YÊU</h2>
                    <p class="section-subtitle mb-0">Tìm kiếm trang phục theo phong cách của bạn.</p>
                </div>

                <div class="quick-filter-buttons">
                    <router-link to="/product?category=pickleball" class="quick-filter-btn">
                        <div class="qf-icon">🎾</div>
                        <span>Pickleball</span>
                    </router-link>
                    <router-link to="/product?category=running" class="quick-filter-btn">
                        <div class="qf-icon">👟</div>
                        <span>Chạy bộ</span>
                    </router-link>
                    <router-link to="/product?category=tennis" class="quick-filter-btn">
                        <div class="qf-icon">🏸</div>
                        <span>Tennis</span>
                    </router-link>
                </div>
            </div> -->

            <!-- Categories Grid -->
            <div class="categories-grid-wrap mt-5">
                <div class="d-flex align-items-end justify-content-between mb-4">
                    <div>
                        <h2 class="section-title mb-1 fw-bold">DANH MỤC SẢN PHẨM</h2>
                        <p class="section-subtitle mb-0">Khám phá các danh mục sản phẩm nổi bật</p>
                    </div>
                    <router-link to="/product" class="link-more d-flex align-items-center gap-1">
                        Xem tất cả
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </router-link>
                </div>

                <div class="custom-cat-grid">
                    <template v-if="isLoadingCategories">
                        <div class="cat-grid-large skeleton-pulse"></div>
                        <div class="cat-grid-small skeleton-pulse" v-for="i in 4" :key="i"></div>
                    </template>
                    <template v-else-if="Categories.length > 0">
                        <!-- Large Card -->
                        <div class="cat-grid-large" v-if="Categories[0]">
                            <router-link :to="'/product?category=' + Categories[0].id" class="cat-card-modern">
                                <div class="cat-card-img"
                                    :style="{ backgroundImage: 'url(' + (Categories[0].image || 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?q=80&w=800') + ')' }">
                                </div>
                                <div class="cat-card-overlay">
                                    <h3>{{ Categories[0].name }}</h3>
                                    <!-- Optional Subcategories here -->
                                    <div class="sub-categories" v-if="Categories[0].children">
                                        <span class="sub-cat" v-for="child in Categories[0].children.slice(0, 3)"
                                            :key="child.id">{{ child.name }}</span>
                                    </div>
                                </div>
                            </router-link>
                        </div>

                        <!-- Small Cards (max 4) -->
                        <div class="cat-grid-small" v-for="cat in Categories.slice(1, 5)" :key="cat.id">
                            <router-link :to="'/product?category=' + cat.id" class="cat-card-modern">
                                <div class="cat-card-img"
                                    :style="{ backgroundImage: 'url(' + (cat.image || 'https://images.unsplash.com/photo-1626245100063-2fb5e5812301?q=80&w=800') + ')' }">
                                </div>
                                <div class="cat-card-overlay">
                                    <h3>{{ cat.name }}</h3>
                                    <!-- Optional Subcategories here -->
                                    <div class="sub-categories" v-if="cat.children">
                                        <span class="sub-cat" v-for="child in cat.children.slice(0, 2)"
                                            :key="child.id">{{ child.name }}</span>
                                    </div>
                                </div>
                            </router-link>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </section>
</template>

<style scoped>
/* Quick Filters */
.quick-filter-buttons {
    display: flex;
    justify-content: center;
    gap: 24px;
    flex-wrap: wrap;
}

.quick-filter-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 120px;
    height: 120px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    text-decoration: none;
    color: #111827;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.quick-filter-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(225, 29, 72, 0.1);
    border-color: rgba(225, 29, 72, 0.2);
    color: #e11d48;
}

.qf-icon {
    font-size: 2.5rem;
    margin-bottom: 8px;
}

/* Custom CSS Grid for Categories */
.custom-cat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    grid-template-rows: 240px 240px;
    gap: 20px;
}

.cat-grid-large {
    grid-column: 1 / 2;
    grid-row: 1 / 3;
    border-radius: 20px;
    overflow: hidden;
}

.cat-grid-small {
    border-radius: 20px;
    overflow: hidden;
}

.cat-card-modern {
    display: block;
    width: 100%;
    height: 100%;
    position: relative;
    overflow: hidden;
    background: #111827;
}

.cat-card-img {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    transition: transform 0.6s ease;
    opacity: 0.8;
}

.cat-card-modern:hover .cat-card-img {
    transform: scale(1.08);
    opacity: 0.9;
}

.cat-card-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 30px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
}

.cat-card-overlay h3 {
    color: #fff;
    margin: 0 0 8px 0;
    font-size: 1.5rem;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.cat-grid-large .cat-card-overlay h3 {
    font-size: 2.2rem;
}

.sub-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.sub-cat {
    font-size: 0.8rem;
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 99px;
    backdrop-filter: blur(4px);
    transition: all 0.2s;
}

.sub-cat:hover {
    background: rgba(255, 255, 255, 0.4);
}

.link-more {
    color: var(--primary);
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.link-more:hover {
    color: var(--primary);
    opacity: 0.8;
    text-decoration: underline;
}

@media (max-width: 991px) {
    .custom-cat-grid {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto;
    }

    .cat-grid-large {
        grid-column: 1 / 3;
        grid-row: 1 / 2;
        height: 300px;
    }

    .cat-grid-small {
        height: 200px;
    }
}
</style>
