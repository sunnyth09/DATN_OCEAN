<script setup>
</script>

<template>
    <section class="hero-section">
        <div class="hero-split container">
            <!-- Left Side -->
            <div class="hero-left py-5 ps-0">
                <div class="hero-content">
                    <span class="hero-tag">BỘ SƯU TẬP MỚI 2026</span>
                    <h1 class="hero-title">
                        Tốc độ.<br />Sức mạnh.<br /><span>Chiến thắng.</span>
                    </h1>
                    <p class="hero-desc">
                        Khám phá những thiết bị chất lượng cao, được thiết kế để nâng tầm kỹ năng và đưa bạn đến chiến thắng. Đam mê bắt đầu từ đây.
                    </p>
                    <div class="d-flex gap-3 flex-wrap hero-btns mb-4">
                        <router-link to="/product" class="btn-primary-hero">
                            Mua ngay
                        </router-link>
                        <router-link to="/about" class="btn-outline-hero">
                            Tìm hiểu thêm
                        </router-link>
                    </div>
                    
                    <div class="hero-stats-glass mt-3">
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
            <div class="hero-right py-5">
                <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1000&q=80" alt="product" class="hero-img rounded-4 border-start" />
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-section {
    position: relative;
    width: 100%;
    min-height: 80vh;
    background-color: var(--background);
    overflow: hidden;
}

.hero-split {
    display: flex;
    min-height: 80vh;
}

.hero-left {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    /* Căn lề trái theo container 1440px: (100vw - 1440) / 2. Đảm bảo lề tối thiểu là 24px */
    padding-left: max(24px, calc((100vw - 1440px) / 2 + 24px));
    padding-right: 60px;
    padding-top: 60px;
    padding-bottom: 60px;
    background: transparent;
}

.hero-right {
    flex: 1;
    position: relative;
    min-height: 400px;
}

.hero-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    /* position: absolute; */
    top: 0;
    left: 0;
}

.hero-content {
    max-width: 550px;
    position: relative;
    z-index: 10;
}

.hero-tag {
    display: inline-block;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 2px;
    margin-bottom: 24px;
    text-transform: uppercase;
    background-color: var(--primary-container);
    border-radius: var(--radius-full);
    padding: 8px 20px;
}

.hero-title {
    font-size: clamp(2.5rem, 4.5vw, 4.5rem); 
    font-weight: 900;
    line-height: 1.1;
    color: var(--text-main);
    letter-spacing: -1px;
    margin: 0 0 24px;
}

.hero-title span {
    color: var(--primary); 
}

.hero-desc {
    font-size: 1.1rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 40px;
    max-width: 90%;
}

.btn-primary-hero {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--primary);
    color: #fff;
    border: none;
    padding: 8px 28px;
    border-radius: var(--radius-md);
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: var(--shadow-md);
    text-decoration: none;
}

.btn-primary-hero:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    color: #fff;
}

.btn-outline-hero {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: var(--text-main);
    border: 1px solid var(--primary);
    padding: 12px 32px;
    border-radius: var(--radius-md);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-outline-hero:hover {
    background: var(--surface);
    color: var(--primary);
}

.hero-stats-glass {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 32px;
    margin-top: 40px;
}

.hero-stat-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.hero-stat-num {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1;
}

.hero-stat-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.hero-stat-divider {
    width: 1px;
    height: 32px;
    background: var(--border-color);
}

@media (max-width: 991px) {
    .hero-split {
        flex-direction: column-reverse;
    }
    .hero-right {
        min-height: 300px;
    }
    .hero-left {
        padding: 40px 5%;
        align-items: center;
        text-align: center;
    }
    .hero-desc {
        max-width: 100%;
    }
    .hero-stats-glass {
        justify-content: center;
    }
}
</style>
