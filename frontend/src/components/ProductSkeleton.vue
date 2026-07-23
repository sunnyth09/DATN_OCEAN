<template>
    <div class="product-skeleton-card">
        <div class="skeleton-img pulse"></div>
        <div class="skeleton-info">
            <div class="skeleton-text pulse title"></div>
            <div class="skeleton-text pulse title" style="width: 60%"></div>
            <div class="skeleton-text pulse price" style="margin-top: 16px;"></div>
        </div>
    </div>
</template>

<style scoped>
.product-skeleton-card {
    display: flex;
    flex-direction: column;
    background: var(--card-bg);
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    overflow: hidden;
    height: 100%;
}

.skeleton-img {
    width: 100%;
    aspect-ratio: 3/4;
    background-color: #f8fafc;
}

.skeleton-info {
    padding: 24px;
}

.skeleton-text {
    height: 14px;
    background-color: #f1f5f9;
    border-radius: 6px;
    margin-bottom: 12px;
}

.skeleton-text.title {
    width: 90%;
}

.skeleton-text.price {
    width: 40%;
    height: 18px;
    margin-bottom: 0;
}

.pulse {
    animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 0.8;
    }
    50% {
        opacity: 0.3;
    }
}
</style>
