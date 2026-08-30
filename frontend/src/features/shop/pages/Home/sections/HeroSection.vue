<script setup>
</script>

<template>
    <section class="hero-section">
        <div class="hero-split container">
            <!-- Left Side -->
            <div class="hero-left">
                <div class="hero-content">
                    <span class="hero-tag">BỘ SƯU TẬP MỚI 2026</span>
                    <h1 class="hero-title">
                        Tốc độ.<br />Sức mạnh.<br /><span>Chiến thắng.</span>
                    </h1>
                    <p class="hero-desc">
                        Khám phá những thiết bị chất lượng cao, được thiết kế để nâng tầm kỹ năng và đưa bạn đến chiến thắng. Đam mê bắt đầu từ đây.
                    </p>
                    <div class="d-flex gap-3 flex-wrap hero-btns">
                        <router-link to="/product" class="btn-primary-hero">
                            Mua ngay
                        </router-link>
                        <router-link to="/about" class="btn-outline-hero">
                            Tìm hiểu thêm
                        </router-link>
                    </div>
                    
                    <div class="hero-stats-glass">
                        <div class="hero-stat-item">
                            <span class="hero-stat-num">50K+</span>
                            <span class="hero-stat-label">Khách hàng</span>
                        </div>
                        <div class="hero-stat-divider"></div>
                        <div class="hero-stat-item">
                            <span class="hero-stat-num">1000+</span>
                            <span class="hero-stat-label">Sản phẩm</span>
                        </div>
                        <div class="hero-stat-divider"></div>
                        <div class="hero-stat-item">
                            <span class="hero-stat-num">100%</span>
                            <span class="hero-stat-label">Chính hãng</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side -->
            <div class="hero-right">
                <div class="hero-img-box">
                    <img 
                        src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1200&q=80" 
                        alt="Ocean Sport Collection" 
                        class="hero-img" 
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-section {
    position: relative;
    width: 100%;
    background-color: var(--background, #ffffff);
    padding: 44px 0 52px;
    overflow: hidden;
}

.hero-split {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 48px;
    min-height: 530px;
}

.hero-left {
    flex: 1.1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.hero-right {
    flex: 0.9;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.hero-img-box {
    width: 100%;
    max-width: 560px;
    height: 500px;
    border-radius: 26px;
    overflow: hidden;
    background: #f8fafc;
    box-shadow: 0 20px 44px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.04);
}

html.dark .hero-img-box {
    background: #191c1d;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 20px 44px rgba(0, 0, 0, 0.35);
}

.hero-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.4s ease;
}

.hero-img-box:hover .hero-img {
    transform: scale(1.02);
}

.hero-content {
    max-width: 560px;
}

.hero-tag {
    display: inline-block;
    color: var(--primary, #E63B6F);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 1.5px;
    margin-bottom: 18px;
    text-transform: uppercase;
    background-color: rgba(230, 59, 111, 0.08);
    border: 1px solid rgba(230, 59, 111, 0.18);
    border-radius: 999px;
    padding: 6px 18px;
}

html.dark .hero-tag {
    background-color: rgba(255, 178, 191, 0.12);
    border-color: rgba(255, 178, 191, 0.25);
    color: var(--primary-light, #ffd9de);
}

.hero-title {
    font-size: clamp(2.4rem, 4.2vw, 3.6rem); 
    font-weight: 900;
    line-height: 1.12;
    color: var(--text-main, #2D3436);
    letter-spacing: -0.8px;
    margin: 0 0 18px;
}

.hero-title span {
    color: var(--primary, #E63B6F); 
}

.hero-desc {
    font-size: 1.05rem;
    color: var(--text-secondary, #636E72);
    line-height: 1.65;
    margin-bottom: 26px;
    max-width: 520px;
}

.hero-btns {
    margin-bottom: 16px;
}

.btn-primary-hero {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--primary, #E63B6F);
    color: #fff;
    border: 1px solid var(--primary, #E63B6F);
    min-height: 48px;
    padding: 12px 28px;
    border-radius: 12px;
    font-size: 0.98rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 14px rgba(230, 59, 111, 0.25);
    text-decoration: none;
}

.btn-primary-hero:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(230, 59, 111, 0.35);
    color: #fff;
    background: var(--primary-dark, #b50c4d);
    border-color: var(--primary-dark, #b50c4d);
}

.btn-outline-hero {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: var(--text-main, #2D3436);
    border: 1.5px solid var(--border-color, #E9ECEF);
    min-height: 48px;
    padding: 12px 28px;
    border-radius: 12px;
    font-size: 0.98rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-outline-hero:hover {
    background: var(--hover-bg, #FFF0F3);
    border-color: var(--primary, #E63B6F);
    color: var(--primary, #E63B6F);
    transform: translateY(-2px);
}

.hero-stats-glass {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 30px;
    margin-top: 24px;
    padding-top: 22px;
    border-top: 1px solid var(--border-color, #E9ECEF);
}

.hero-stat-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.hero-stat-num {
    font-size: 1.35rem;
    font-weight: 850;
    color: var(--text-main, #2D3436);
    line-height: 1.1;
}

.hero-stat-label {
    font-size: 0.85rem;
    color: var(--text-secondary, #636E72);
    font-weight: 500;
}

.hero-stat-divider {
    width: 1px;
    height: 32px;
    background: var(--border-color, #E9ECEF);
}

@media (max-width: 991px) {
    .hero-split {
        flex-direction: column;
        padding-top: 16px;
        padding-bottom: 20px;
        gap: 28px;
        min-height: auto;
    }
    .hero-left {
        align-items: center;
        text-align: center;
    }
    .hero-right {
        justify-content: center;
    }
    .hero-img-box {
        max-width: 420px;
        height: 360px;
    }
    .hero-desc {
        max-width: 100%;
    }
    .hero-stats-glass {
        justify-content: center;
    }
}
</style>
