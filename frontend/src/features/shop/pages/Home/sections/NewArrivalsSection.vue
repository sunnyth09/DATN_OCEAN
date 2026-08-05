<script setup>
import ProductCard from '@/components/ProductCard.vue';
import ProductSkeleton from '@/components/ProductSkeleton.vue';
</script>
<template>
        <section class="py-5 reveal-on-scroll">
            <div class="container">
                <div class="row g-4">
                    <!-- Banner 1 -->
                    <div class="col-md-6">
                        <div class="promo-banner promo-banner--dark">
                            <div class="promo-banner-content">
                                <span class="promo-banner-tag">ƯU ĐÃI ĐỘC QUYỀN</span>
                                <h3 class="promo-banner-title">Giảm <em>20%</em><br />đơn hàng đầu tiên</h3>
                                <p class="promo-banner-desc">Đăng ký tài khoản ngay hôm nay để nhận mã giảm giá 20% cho
                                    đơn hàng
                                    đầu tiên.</p>
                                <router-link to="/client/register" class="promo-banner-btn">
                                    Đăng ký ngay
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </router-link>
                            </div>
                            <div class="promo-banner-deco">
                                <div class="deco-circle deco-c1"></div>
                                <div class="deco-circle deco-c2"></div>
                                <span class="deco-badge">20% OFF</span>
                            </div>
                        </div>
                    </div>
                    <!-- Banner 2 -->
                    <div class="col-md-6">
                        <div class="promo-banner promo-banner--red">
                            <div class="promo-banner-content">
                                <span class="promo-banner-tag">MỖI NGÀY</span>
                                <h3 class="promo-banner-title">Flash Sale<br /><em>12:00 – 14:00</em></h3>
                                <p class="promo-banner-desc">Giảm đến 50% cho hàng trăm sản phẩm thể thao mỗi ngày trong
                                    khung
                                    giờ vàng.</p>
                                <router-link to="/flash-sale" class="promo-banner-btn promo-banner-btn--outline">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10" />
                                    </svg>
                                    Xem Flash Sale
                                </router-link>
                            </div>
                            <div class="promo-banner-deco">
                                <div class="deco-circle deco-c3"></div>
                                <div class="deco-circle deco-c4"></div>
                                <span class="deco-badge deco-badge--light">⚡ HOT</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
</template>


<style scoped>
.promo-banner {
    border-radius: 4px;
    padding: 44px 36px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    min-height: 260px;
    height: 100%;
    box-shadow: var(--shadow-md);
}

.promo-banner--dark {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
}

.promo-banner--red {
    background: #fff0f3;
    border: 1px solid #ffe4e6;
}

.promo-banner--red .promo-banner-title {
    color: #1e293b;
}

.promo-banner--red .promo-banner-desc {
    color: #64748b;
}

.promo-banner--red .promo-banner-tag {
    background: #fff;
    color: var(--primary);
    border-color: #ffe4e6;
}

.promo-banner--red .promo-banner-btn--outline {
    border-color: var(--primary);
    color: var(--primary);
}

.promo-banner--red .promo-banner-btn--outline:hover {
    background: var(--primary);
    color: #fff;
}

.promo-banner--red .deco-circle {
    background: var(--primary);
    opacity: 0.05;
}

.promo-banner-content {
    position: relative;
    z-index: 2;
    flex: 1;
}

.promo-banner-tag {
    display: inline-block;
    background: rgba(230, 59, 111, .2);
    border: 1px solid rgba(230, 59, 111, .4);
    color: #ff8fab;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    padding: 5px 14px;
    border-radius: 99px;
    margin-bottom: 14px;
    text-transform: uppercase;
}

.promo-banner-title {
    font-size: 1.7rem;
    font-weight: 900;
    color: #fff;
    margin: 0 0 10px;
    line-height: 1.2;
    letter-spacing: -.5px;
}

.promo-banner-title em {
    color: var(--primary);
    font-style: italic;
}

.promo-banner-desc {
    color: rgba(255, 255, 255, .65);
    font-size: .85rem;
    line-height: 1.6;
    margin: 0 0 22px;
    max-width: 280px;
}

.promo-banner-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary);
    color: #fff;
    padding: 11px 26px;
    border-radius: 99px;
    font-size: .88rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s;
    box-shadow: 0 4px 16px rgba(230, 59, 111, .4);
}

.promo-banner-btn:hover {
    background: #d82f65;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(230, 59, 111, .5);
    color: #fff;
}

.promo-banner-btn--outline {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, .4);
    box-shadow: none;
}

.promo-banner-btn--outline:hover {
    background: rgba(255, 255, 255, .1);
    border-color: rgba(255, 255, 255, .8);
}

/* Deco elements */
.promo-banner-deco {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 220px;
    pointer-events: none;
}

.deco-circle {
    position: absolute;
    border-radius: 50%;
    opacity: .15;
}

.deco-c1 {
    width: 180px;
    height: 180px;
    background: var(--card-bg);
    top: -40px;
    right: -40px;
}

.deco-c2 {
    width: 120px;
    height: 120px;
    background: var(--primary);
    bottom: -20px;
    right: 60px;
    opacity: .25;
}

.deco-c3 {
    width: 200px;
    height: 200px;
    background: var(--primary);
    top: -50px;
    right: -50px;
}

.deco-c4 {
    width: 100px;
    height: 100px;
    background: var(--card-bg);
    bottom: -20px;
    right: 80px;
    opacity: .12;
}

.deco-badge {
    position: absolute;
    bottom: 28px;
    right: 24px;
    background: rgba(255, 255, 255, .12);
    border: 2px solid rgba(255, 255, 255, .2);
    color: #fff;
    font-size: 1.6rem;
    font-weight: 900;
    padding: 10px 18px;
    border-radius: 12px;
    backdrop-filter: blur(8px);
    letter-spacing: -1px;
}

.deco-badge--light {
    background: rgba(230, 59, 111, .2);
    border-color: rgba(230, 59, 111, .4);
    color: #ff8fab;
    font-size: 1.1rem;
}
</style>
