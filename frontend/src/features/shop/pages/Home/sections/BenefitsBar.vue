<script setup>
import AppIcon from '@/components/AppIcon.vue';
</script>
<template>
        <section class="benefits-bar reveal-on-scroll">
            <div class="container">
                <div class="benefits-inner">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="shipping" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Miễn phí vận chuyển</span>
                            <span class="benefit-sub">Đơn hàng từ 500K</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="return" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Đổi trả 30 ngày</span>
                            <span class="benefit-sub">Không cần lý do</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="payment" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Thanh toán bảo mật</span>
                            <span class="benefit-sub">SSL 256-bit</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="shield" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Hàng chính hãng 100%</span>
                            <span class="benefit-sub">Cam kết đảm bảo</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="heart" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Hỗ trợ 24/7</span>
                            <span class="benefit-sub">Tư vấn nhiệt tình</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
</template>

<style scoped>
.benefits-bar {
    background: #ffffff;
    padding: 24px 0;
    position: relative;
    border-bottom: 1px solid #f1f5f9;
}

.benefits-inner {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    background: #ffffff;
}

.benefit-item {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    padding: 8px;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: transparent;
}

.benefit-item:hover {
    background: #f8fafc;
}

.benefit-item:hover .benefit-icon {
    color: var(--primary);
}

.benefit-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 50%;
    color: #64748b;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.benefit-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.benefit-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    transition: color 0.3s;
}

.benefit-item:hover .benefit-title {
    color: var(--primary);
}

.benefit-sub {
    font-size: 0.78rem;
    color: #64748b;
}

@media (max-width: 1200px) {
    .benefits-inner {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .benefits-inner {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 8px;
    }
    .benefit-item {
        padding: 14px 16px;
    }
}

@media (max-width: 480px) {
    .benefits-inner {
        grid-template-columns: 1fr;
    }
}
</style>
