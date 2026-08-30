<script setup>
import AppIcon from '@/components/AppIcon.vue';

const benefits = [
    {
        icon: 'shipping',
        title: 'Miễn phí vận chuyển',
        sub: 'Đơn hàng từ 500K'
    },
    {
        icon: 'return',
        title: 'Đổi trả 30 ngày',
        sub: 'Thủ tục nhanh gọn'
    },
    {
        icon: 'payment',
        title: 'Thanh toán bảo mật',
        sub: 'Mã hóa SSL 256-bit'
    },
    {
        icon: 'shield',
        title: 'Hàng chính hãng 100%',
        sub: 'Cam kết bồi hoàn'
    },
    {
        icon: 'heart',
        title: 'Hỗ trợ 24/7',
        sub: 'Tư vấn nhiệt tình'
    }
];
</script>

<template>
    <section class="benefits-bar-section">
        <div class="container">
            <div class="benefits-floating-card">
                <div class="benefits-grid">
                    <div 
                        v-for="(item, idx) in benefits" 
                        :key="idx" 
                        class="benefit-card-item"
                    >
                        <div class="benefit-icon-wrapper">
                            <AppIcon :name="item.icon" width="20" height="20" :stroke-width="2.2" />
                        </div>
                        <div class="benefit-info">
                            <strong class="benefit-main-title">{{ item.title }}</strong>
                            <span class="benefit-sub-text">{{ item.sub }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.benefits-bar-section {
    position: relative;
    z-index: 20;
    margin-top: -20px;
    margin-bottom: 24px;
}

.benefits-floating-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid rgba(230, 59, 111, 0.12);
    border-radius: 14px;
    padding: 12px 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 2px 6px rgba(0, 0, 0, 0.02);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

html.dark .benefits-floating-card {
    background: rgba(22, 24, 25, 0.95);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    align-items: center;
}

.benefit-card-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 6px;
    border-radius: 10px;
    transition: all 0.25s ease;
}

.benefit-card-item:hover {
    background: rgba(230, 59, 111, 0.04);
}

html.dark .benefit-card-item:hover {
    background: rgba(255, 255, 255, 0.04);
}

.benefit-icon-wrapper {
    width: 38px;
    height: 38px;
    min-width: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(230, 59, 111, 0.08) 0%, rgba(230, 59, 111, 0.18) 100%);
    color: var(--primary, #E63B6F);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

html.dark .benefit-icon-wrapper {
    background: linear-gradient(135deg, rgba(255, 178, 191, 0.12) 0%, rgba(255, 178, 191, 0.24) 100%);
}

.benefit-card-item:hover .benefit-icon-wrapper {
    transform: scale(1.08) rotate(-3deg);
    background: var(--primary, #E63B6F);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.25);
}

.benefit-info {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}

.benefit-main-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-main, #2D3436);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color 0.2s ease;
}

.benefit-card-item:hover .benefit-main-title {
    color: var(--primary, #E63B6F);
}

.benefit-sub-text {
    font-size: 0.72rem;
    color: var(--text-secondary, #636E72);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* =======================================================
   RESPONSIVE BREAKPOINTS
======================================================= */
@media (max-width: 1200px) {
    .benefits-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
}

@media (max-width: 768px) {
    .benefits-bar-section {
        margin-top: -12px;
        margin-bottom: 20px;
    }

    .benefits-floating-card {
        padding: 10px 14px;
    }

    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .benefit-card-item {
        padding: 4px 6px;
    }

    .benefit-icon-wrapper {
        width: 34px;
        height: 34px;
        min-width: 34px;
    }

    .benefit-main-title {
        font-size: 0.78rem;
    }

    .benefit-sub-text {
        font-size: 0.68rem;
    }
}

@media (max-width: 480px) {
    .benefits-grid {
        grid-template-columns: 1fr;
    }
}
</style>
