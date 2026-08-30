<script setup>
import AppIcon from '@/components/AppIcon.vue';

const benefits = [
    {
        icon: 'shipping',
        title: 'Miễn phí vận chuyển',
        sub: 'Đơn hàng từ 500K toàn quốc'
    },
    {
        icon: 'return',
        title: 'Đổi trả 30 ngày',
        sub: 'Thủ tục nhanh gọn & tiện lợi'
    },
    {
        icon: 'payment',
        title: 'Thanh toán bảo mật',
        sub: 'Mã hóa SSL 256-bit chuẩn quốc tế'
    },
    {
        icon: 'shield',
        title: 'Hàng chính hãng 100%',
        sub: 'Cam kết bồi hoàn nếu phát hiện giả'
    },
    {
        icon: 'heart',
        title: 'Hỗ trợ 24/7',
        sub: 'Tư vấn kỹ thuật chọn vợt & size'
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
                            <AppIcon :name="item.icon" width="22" height="22" :stroke-width="2.2" />
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
    margin-top: -24px;
    margin-bottom: 24px;
}

.benefits-floating-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid rgba(230, 59, 111, 0.12);
    border-radius: 18px;
    padding: 16px 24px;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.05), 0 2px 8px rgba(0, 0, 0, 0.02);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

html.dark .benefits-floating-card {
    background: rgba(22, 24, 25, 0.95);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    align-items: center;
}

.benefit-card-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 8px 10px;
    border-radius: 12px;
    transition: all 0.25s ease;
}

.benefit-card-item:hover {
    background: rgba(230, 59, 111, 0.04);
}

html.dark .benefit-card-item:hover {
    background: rgba(255, 255, 255, 0.04);
}

.benefit-icon-wrapper {
    width: 44px;
    height: 44px;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(230, 59, 111, 0.1) 0%, rgba(230, 59, 111, 0.2) 100%);
    color: var(--primary);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

html.dark .benefit-icon-wrapper {
    background: linear-gradient(135deg, rgba(255, 178, 191, 0.12) 0%, rgba(255, 178, 191, 0.24) 100%);
}

.benefit-card-item:hover .benefit-icon-wrapper {
    transform: scale(1.1) rotate(-4deg);
    background: var(--primary);
    color: #ffffff;
    box-shadow: 0 6px 16px rgba(230, 59, 111, 0.3);
}

.benefit-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.benefit-main-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.25;
    transition: color 0.2s ease;
}

.benefit-card-item:hover .benefit-main-title {
    color: var(--primary);
}

.benefit-sub-text {
    font-size: 0.76rem;
    color: var(--text-secondary);
    line-height: 1.35;
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
    }

    .benefits-floating-card {
        padding: 14px 16px;
    }

    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .benefit-card-item {
        padding: 6px 8px;
    }

    .benefit-icon-wrapper {
        width: 38px;
        height: 38px;
        min-width: 38px;
    }
}

@media (max-width: 480px) {
    .benefits-grid {
        grid-template-columns: 1fr;
    }
}
</style>
