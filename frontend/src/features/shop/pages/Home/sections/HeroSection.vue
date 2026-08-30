<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const currentSlide = ref(0);
let autoPlayTimer = null;

const slides = [
    {
        tag: 'BỘ SƯU TẬP OCEAN 2026',
        tagIcon: '⚡',
        titlePart1: 'Tốc Độ. Sức Mạnh.',
        titleHighlight: 'Bứt Phá.',
        desc: 'Khám phá các thiết bị thể thao đỉnh cao chuyên biệt cho Cầu Lông, Pickleball, Tennis & Chạy bộ. Nâng tầm kỹ năng, bứt phá mọi giới hạn.',
        primaryBtnText: 'Khám phá ngay',
        primaryBtnLink: '/product',
        secondaryBtnText: 'Săn voucher',
        secondaryBtnLink: '/coupon',
        image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1200&q=80',
        badge1: { icon: '🔥', title: 'Top 1 Trending', sub: 'Bộ sưu tập Hot nhất' },
        badge2: { icon: '⭐', title: '4.9/5 Đánh giá', sub: 'Từ 15.000+ khách hàng' },
        badge3: { icon: '🏷️', title: 'Ưu đãi tới 45%', sub: 'Số lượng có hạn' },
        accentColor: '#E63B6F'
    },
    {
        tag: 'DÒNG VỢT THI ĐẤU PRO',
        tagIcon: '🏸',
        titlePart1: 'Chuẩn Xác Từng Cú Smash.',
        titleHighlight: 'Làm Chủ Trận Đấu.',
        desc: 'Vợt Cầu Lông & Vợt Pickleball chính hãng Yonex, Victor, Lining, Franklin. Trợ lực tối đa, độ đầm tay hoàn hảo cho vận động viên phong trào & chuyên nghiệp.',
        primaryBtnText: 'Xem dòng vợt Pro',
        primaryBtnLink: '/product?category=1',
        secondaryBtnText: 'Đặt sân thể thao',
        secondaryBtnLink: '/courts',
        image: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=1200&q=80',
        badge1: { icon: '🏸', title: 'Carbon Graphite', sub: 'Siêu nhẹ & Bền bỉ' },
        badge2: { icon: '🛡️', title: 'Bảo hành 12 tháng', sub: 'Chính hãng 100%' },
        badge3: { icon: '⚡', title: 'Giao hỏa tốc 2H', sub: 'Nội thành TP' },
        accentColor: '#0ea5e9'
    },
    {
        tag: 'GIÀY & PHỤ KIỆN HIỆU NĂNG',
        tagIcon: '👟',
        titlePart1: 'Bám Sân Tối Ưu.',
        titleHighlight: 'Bảo Vệ Đôi Chân.',
        desc: 'Đế đệm cao su chống lật cổ chân, công nghệ thoáng khí Air-Mesh giảm chấn thương. Sẵn sàng cho mọi set đấu kéo dài.',
        primaryBtnText: 'Mua giày thể thao',
        primaryBtnLink: '/product?category=2',
        secondaryBtnText: 'Hướng dẫn chọn size',
        secondaryBtnLink: '/shopping-guide',
        image: 'https://images.unsplash.com/photo-1556906781-9a412961c28c?w=1200&q=80',
        badge1: { icon: '👟', title: 'Đệm khí Power Cushion', sub: 'Giảm chấn 40%' },
        badge2: { icon: '🔄', title: 'Đổi size miễn phí', sub: 'Trong 30 ngày' },
        badge3: { icon: '🎁', title: 'Tặng túi đựng giày', sub: 'Cho đơn từ 1Tr' },
        accentColor: '#10b981'
    }
];

const nextSlide = () => {
    currentSlide.value = (currentSlide.value + 1) % slides.length;
};

const prevSlide = () => {
    currentSlide.value = (currentSlide.value - 1 + slides.length) % slides.length;
};

const goToSlide = (index) => {
    currentSlide.value = index;
    resetTimer();
};

const startTimer = () => {
    stopTimer();
    autoPlayTimer = setInterval(nextSlide, 6000);
};

const stopTimer = () => {
    if (autoPlayTimer) {
        clearInterval(autoPlayTimer);
        autoPlayTimer = null;
    }
};

const resetTimer = () => {
    stopTimer();
    startTimer();
};

onMounted(() => {
    startTimer();
});

onUnmounted(() => {
    stopTimer();
});
</script>

<template>
    <section class="hero-section" @mouseenter="stopTimer" @mouseleave="startTimer">
        <!-- Background Ambient Glow -->
        <div class="hero-ambient-glow glow-1"></div>
        <div class="hero-ambient-glow glow-2"></div>

        <div class="container hero-container">
            <div class="hero-slider">
                <div 
                    v-for="(slide, idx) in slides" 
                    :key="idx" 
                    class="hero-slide"
                    :class="{ 'is-active': currentSlide === idx }"
                >
                    <!-- Left Content -->
                    <div class="hero-left">
                        <div class="hero-tag-pill">
                            <span class="hero-tag-icon">{{ slide.tagIcon }}</span>
                            <span class="hero-tag-text">{{ slide.tag }}</span>
                        </div>

                        <h1 class="hero-heading">
                            {{ slide.titlePart1 }}
                            <br />
                            <span class="hero-gradient-text">{{ slide.titleHighlight }}</span>
                        </h1>

                        <p class="hero-description">
                            {{ slide.desc }}
                        </p>

                        <div class="hero-actions">
                            <router-link :to="slide.primaryBtnLink" class="btn-hero-main">
                                <span>{{ slide.primaryBtnText }}</span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                </svg>
                            </router-link>
                            <router-link :to="slide.secondaryBtnLink" class="btn-hero-sub">
                                <span>{{ slide.secondaryBtnText }}</span>
                            </router-link>
                        </div>

                        <!-- Stats Bar -->
                        <div class="hero-stats">
                            <div class="stat-box">
                                <span class="stat-number">50K+</span>
                                <span class="stat-title">Vận động viên</span>
                            </div>
                            <div class="stat-sep"></div>
                            <div class="stat-box">
                                <span class="stat-number">1000+</span>
                                <span class="stat-title">Dụng cụ thể thao</span>
                            </div>
                            <div class="stat-sep"></div>
                            <div class="stat-box">
                                <span class="stat-number">100%</span>
                                <span class="stat-title">Hàng chính hãng</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Visual Showcase -->
                    <div class="hero-right">
                        <div class="hero-visual-card">
                            <div class="card-ambient-backdrop"></div>
                            
                            <img :src="slide.image" :alt="slide.titleHighlight" class="hero-main-img" />

                            <!-- Floating Badge 1: Top Right -->
                            <div class="floating-badge badge-top-right">
                                <span class="f-icon">{{ slide.badge1.icon }}</span>
                                <div class="f-text">
                                    <strong>{{ slide.badge1.title }}</strong>
                                    <small>{{ slide.badge1.sub }}</small>
                                </div>
                            </div>

                            <!-- Floating Badge 2: Bottom Left -->
                            <div class="floating-badge badge-bottom-left">
                                <span class="f-icon">{{ slide.badge2.icon }}</span>
                                <div class="f-text">
                                    <strong>{{ slide.badge2.title }}</strong>
                                    <small>{{ slide.badge2.sub }}</small>
                                </div>
                            </div>

                            <!-- Floating Badge 3: Discount pill -->
                            <div class="floating-badge badge-discount">
                                <span class="f-icon">{{ slide.badge3.icon }}</span>
                                <div class="f-text">
                                    <strong>{{ slide.badge3.title }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slider Navigation Controls -->
            <div class="hero-controls">
                <button type="button" class="btn-nav btn-prev" @click="prevSlide" aria-label="Slide trước">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>

                <div class="hero-dots">
                    <button 
                        v-for="(_, idx) in slides" 
                        :key="idx" 
                        type="button" 
                        class="dot-btn"
                        :class="{ active: currentSlide === idx }"
                        @click="goToSlide(idx)"
                        :aria-label="`Chuyển slide ${idx + 1}`"
                    >
                        <span class="dot-progress"></span>
                    </button>
                </div>

                <button type="button" class="btn-nav btn-next" @click="nextSlide" aria-label="Slide tiếp theo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M9 18l6-6-6-6" />
                    </svg>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* =======================================================
   HERO SECTION WRAPPER
======================================================= */
.hero-section {
    position: relative;
    width: 100%;
    min-height: 560px;
    background: linear-gradient(180deg, rgba(248, 249, 250, 0.8) 0%, rgba(255, 255, 255, 1) 100%);
    overflow: hidden;
    padding: 32px 0 48px;
}

html.dark .hero-section {
    background: linear-gradient(180deg, #121517 0%, #0e1112 100%);
}

/* Ambient Glow Orbs */
.hero-ambient-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 1;
}

.glow-1 {
    top: -80px;
    right: 5%;
    width: 480px;
    height: 480px;
    background: radial-gradient(circle, rgba(230, 59, 111, 0.15) 0%, rgba(230, 59, 111, 0) 70%);
}

.glow-2 {
    bottom: -60px;
    left: 10%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, rgba(14, 165, 233, 0) 70%);
}

/* Container */
.hero-container {
    position: relative;
    z-index: 10;
}

.hero-slider {
    position: relative;
    min-height: 480px;
}

/* =======================================================
   SLIDES & TRANSITIONS
======================================================= */
.hero-slide {
    display: none;
    grid-template-columns: 1.15fr 0.95fr;
    align-items: center;
    gap: 48px;
    opacity: 0;
    transform: translateY(12px);
    transition: opacity 0.45s ease, transform 0.45s ease;
}

.hero-slide.is-active {
    display: grid;
    opacity: 1;
    transform: translateY(0);
}

/* =======================================================
   LEFT CONTENT
======================================================= */
.hero-left {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 16px 0;
}

.hero-tag-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    background: rgba(230, 59, 111, 0.1);
    border: 1px solid rgba(230, 59, 111, 0.22);
    border-radius: 999px;
    margin-bottom: 20px;
}

html.dark .hero-tag-pill {
    background: rgba(255, 178, 191, 0.15);
    border-color: rgba(255, 178, 191, 0.3);
}

.hero-tag-icon {
    font-size: 0.95rem;
}

.hero-tag-text {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    color: var(--primary);
    text-transform: uppercase;
}

.hero-heading {
    font-size: clamp(2.2rem, 3.8vw, 3.4rem);
    font-weight: 900;
    line-height: 1.14;
    letter-spacing: -0.8px;
    color: var(--text-main);
    margin: 0 0 20px 0;
}

.hero-gradient-text {
    background: linear-gradient(135deg, var(--primary) 0%, #ff4d84 60%, #ff85a2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: inline-block;
}

.hero-description {
    font-size: 1.05rem;
    line-height: 1.65;
    color: var(--text-secondary);
    max-width: 520px;
    margin: 0 0 32px 0;
}

/* Actions */
.hero-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 36px;
}

.btn-hero-main {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 48px;
    padding: 0 28px;
    background: linear-gradient(135deg, var(--primary) 0%, #d82f65 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.98rem;
    border-radius: 12px;
    text-decoration: none;
    box-shadow: 0 10px 24px rgba(230, 59, 111, 0.28);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
}

.btn-hero-main:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(230, 59, 111, 0.38);
    color: #ffffff;
}

.btn-hero-sub {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0 24px;
    background: var(--card-bg);
    color: var(--text-main);
    font-weight: 600;
    font-size: 0.95rem;
    border-radius: 12px;
    text-decoration: none;
    border: 1.5px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
}

.btn-hero-sub:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--hover-bg);
    transform: translateY(-2px);
}

/* Stats */
.hero-stats {
    display: flex;
    align-items: center;
    gap: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
    width: 100%;
    max-width: 520px;
}

.stat-box {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.1;
}

.stat-title {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text-secondary);
}

.stat-sep {
    width: 1px;
    height: 30px;
    background: var(--border-color);
}

/* =======================================================
   RIGHT VISUAL SHOWCASE
======================================================= */
.hero-right {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px;
}

.hero-visual-card {
    position: relative;
    width: 100%;
    max-width: 480px;
    height: 440px;
    border-radius: 24px;
    overflow: visible;
}

.card-ambient-backdrop {
    position: absolute;
    inset: 0;
    border-radius: 24px;
    background: radial-gradient(circle at 40% 40%, rgba(230, 59, 111, 0.25) 0%, rgba(230, 59, 111, 0.05) 60%, transparent 100%);
    border: 1px solid rgba(230, 59, 111, 0.15);
    box-shadow: 0 20px 48px rgba(0, 0, 0, 0.08);
}

html.dark .card-ambient-backdrop {
    background: radial-gradient(circle at 40% 40%, rgba(230, 59, 111, 0.2) 0%, rgba(0, 0, 0, 0.4) 100%);
    border-color: rgba(255, 255, 255, 0.08);
}

.hero-main-img {
    position: relative;
    z-index: 2;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 24px;
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12);
    transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.hero-visual-card:hover .hero-main-img {
    transform: scale(1.02);
}

/* Floating Glassmorphism Badges */
.floating-badge {
    position: absolute;
    z-index: 5;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
    animation: floatingBob 4s ease-in-out infinite alternate;
}

html.dark .floating-badge {
    background: rgba(25, 28, 29, 0.85);
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
}

.badge-top-right {
    top: -12px;
    right: -16px;
    animation-delay: 0s;
}

.badge-bottom-left {
    bottom: -16px;
    left: -16px;
    animation-delay: 1.5s;
}

.badge-discount {
    top: 50%;
    right: -24px;
    transform: translateY(-50%);
    animation-delay: 0.8s;
}

.f-icon {
    font-size: 1.3rem;
    line-height: 1;
}

.f-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.f-text strong {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.2;
}

.f-text small {
    font-size: 0.72rem;
    color: var(--text-secondary);
}

@keyframes floatingBob {
    0% { transform: translateY(0px); }
    100% { transform: translateY(-8px); }
}

/* =======================================================
   HERO CONTROLS (ARROWS & DOTS)
======================================================= */
.hero-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-top: 32px;
}

.btn-nav {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.btn-nav:hover {
    background: var(--hover-bg);
    border-color: var(--primary);
    color: var(--primary);
    transform: scale(1.08);
}

.hero-dots {
    display: flex;
    align-items: center;
    gap: 8px;
}

.dot-btn {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--border-color);
    border: none;
    cursor: pointer;
    padding: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dot-btn.active {
    width: 32px;
    background: var(--primary);
}

/* =======================================================
   RESPONSIVE BREAKPOINTS
======================================================= */
@media (max-width: 991px) {
    .hero-slide {
        grid-template-columns: 1fr;
        gap: 36px;
        text-align: center;
    }

    .hero-left {
        align-items: center;
        padding: 0;
    }

    .hero-description {
        max-width: 100%;
    }

    .hero-actions {
        justify-content: center;
    }

    .hero-stats {
        justify-content: center;
        max-width: 100%;
    }

    .hero-visual-card {
        max-width: 380px;
        height: 340px;
        margin: 0 auto;
    }

    .badge-top-right {
        right: 0;
    }

    .badge-bottom-left {
        left: 0;
    }

    .badge-discount {
        right: 0;
    }
}

@media (max-width: 576px) {
    .hero-section {
        padding: 20px 0 32px;
        min-height: auto;
    }

    .hero-heading {
        font-size: 2rem;
    }

    .hero-visual-card {
        max-width: 300px;
        height: 280px;
    }

    .floating-badge {
        padding: 8px 12px;
    }

    .f-text strong {
        font-size: 0.78rem;
    }

    .f-text small {
        font-size: 0.68rem;
    }

    .hero-stats {
        gap: 14px;
    }

    .stat-number {
        font-size: 1.1rem;
    }
}
</style>
