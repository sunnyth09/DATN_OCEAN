<script setup>
import { getAppBaseUrl } from '@/utils/url';
const BASE_URL = getAppBaseUrl();
</script>

<template>
    <section class="promo-banners py-5">
        <div class="container">
            <div class="promo-banners-grid">
                <!-- Left Banner -->
                <div class="promo-card promo-dark" style="background-image: url('https://images.unsplash.com/photo-1579952363873-27f3bade9f55?q=80&w=800'); background-size: cover; background-position: center;">
                    <div class="promo-overlay-dark"></div>
                    <div class="promo-content">
                        <span class="promo-badge">GIẢM GIÁ ĐẶC BIỆT</span>
                        <h3 class="promo-title">Giảm <span>20%</span><br>đơn hàng đầu tiên</h3>
                        <p class="promo-desc">Chọn đồ chất, thể hiện phong cách, nhận ưu đãi cực lớn.</p>
                        <router-link to="/product" class="btn-promo">
                            Mua ngay
                        </router-link>
                    </div>
                </div>

                <!-- Right Banner -->
                <div class="promo-card promo-light" style="background-image: url('https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=800'); background-size: cover; background-position: center;">
                    <div class="promo-overlay-light"></div>
                    <div class="promo-content">
                        <span class="promo-badge-sale">SẮP DIỄN RA</span>
                        <h3 class="promo-title-dark">Flash Sale<br><span>12:00 - 14:00</span></h3>
                        <p class="promo-desc-dark">Săn deal đồ thể thao với mức giá chấn động. Cơ hội duy nhất trong ngày.</p>
                        <router-link to="/flash-sale" class="btn-promo-outline">
                            Xem chi tiết
                        </router-link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.promo-overlay-dark {
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(30, 27, 75, 0.9) 0%, rgba(30, 27, 75, 0.4) 100%);
    z-index: 1;
}

.promo-overlay-light {
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(253, 242, 248, 0.95) 0%, rgba(253, 242, 248, 0.6) 100%);
    z-index: 1;
}
.promo-banners-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.promo-card {
    border-radius: 20px;
    padding: 40px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 280px;
}

.promo-dark {
    background: #1e1b4b; /* dark blue */
    color: white;
}

.promo-light {
    background: #fdf2f8; /* very light pink */
    color: #111827;
}

.promo-content {
    position: relative;
    z-index: 2;
    max-width: 60%;
}

.promo-badge {
    display: inline-block;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 16px;
    letter-spacing: 1px;
}

.promo-badge-sale {
    display: inline-block;
    padding: 6px 12px;
    background: white;
    color: #e11d48;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 16px;
    letter-spacing: 1px;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.1);
}

.promo-title {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 12px;
}

.promo-title span {
    color: #f43f5e;
}

.promo-title-dark {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 12px;
    color: #111827;
}

.promo-title-dark span {
    color: #e11d48;
}

.promo-desc, .promo-desc-dark {
    font-size: 0.95rem;
    margin-bottom: 24px;
    opacity: 0.9;
    line-height: 1.5;
}

.promo-desc-dark {
    color: #4b5563;
}

.btn-promo {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f43f5e;
    color: white;
    padding: 12px 24px;
    border-radius: 99px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-promo:hover {
    background: #e11d48;
    transform: translateY(-2px);
}

.btn-promo-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: white;
    color: #111827;
    border: 1px solid #e5e7eb;
    padding: 12px 24px;
    border-radius: 99px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-promo-outline:hover {
    border-color: #f43f5e;
    color: #f43f5e;
}

@media (max-width: 991px) {
    .promo-banners-grid {
        grid-template-columns: 1fr;
    }
}
</style>
