<template>
    <div class="product-skeleton-card" aria-hidden="true">
        <!-- Hình học phải khớp ProductCard.vue để không giật layout (CLS) khi swap -->
        <div class="skeleton-media">
            <div class="skeleton-img pulse"></div>
        </div>
        <div class="skeleton-info">
            <div class="skeleton-text pulse category"></div>
            <div class="skeleton-text pulse title"></div>
            <div class="skeleton-text pulse title short"></div>
            <div class="skeleton-text pulse price"></div>
        </div>
    </div>
</template>

<style scoped>
/* Các giá trị dưới đây được đồng bộ với ProductCard.vue .card-link / .media /
   .image-shell / .content. Nếu sửa một bên, phải sửa bên kia. */
.product-skeleton-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 360px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-md);
}

.skeleton-media {
    padding: 14px 14px 0;
}

.skeleton-img {
    width: 100%;
    height: 210px;
    border-radius: var(--radius-sm);
    background-color: var(--surface-alt);
}

.skeleton-info {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 8px;
    padding: 14px 20px 20px;
}

.skeleton-text {
    background-color: var(--border-subtle);
    border-radius: var(--radius-sm);
}

.skeleton-text.category {
    width: 40%;
    height: 13px;
}

.skeleton-text.title {
    width: 90%;
    height: 15px;
}

.skeleton-text.title.short {
    width: 60%;
}

.skeleton-text.price {
    width: 45%;
    height: 20px;
    margin-top: auto;
}

.pulse {
    animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 0.9;
    }
    50% {
        opacity: 0.4;
    }
}

/* Tôn trọng người dùng bật giảm chuyển động */
@media (prefers-reduced-motion: reduce) {
    .pulse {
        animation: none;
        opacity: 0.7;
    }
}

@media (max-width: 768px) {
    .product-skeleton-card {
        min-height: unset;
        border-radius: 12px;
    }

    .skeleton-media {
        padding: 8px 8px 0;
    }

    .skeleton-img {
        height: 120px;
        border-radius: 8px 8px 0 0;
    }

    .skeleton-info {
        padding: 8px 10px 10px;
        gap: 4px;
    }
}
</style>
