<script setup>
</script>
<template>
    <section class="community-section py-0 position-relative">
        <div class="community-image-bg d-none d-lg-block">
            <img src="https://images.unsplash.com/photo-1526232761682-d26e03ac148e?w=800&q=80"
                alt="community" class="community-img" />
        </div>

        <div class="container position-relative" style="z-index: 1;">
            <div class="row">
                <!-- Left content -->
                <div class="col-lg-6">
                    <div class="community-content h-100">
                        <span class="community-tag mb-3 d-flex align-items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                            CỘNG ĐỒNG OCEAN SPORT
                        </span>
                        <h2 class="community-title mb-0">Hơn cả một cửa hàng.<br />Chúng tôi là <em>đam mê</em>.</h2>

                        <!-- Stats row -->
                        <div class="d-flex gap-5 py-4 my-3 community-stats-row">
                            <div>
                                <div class="community-stat-num">10,000+</div>
                                <div class="community-stat-label">Thành viên</div>
                            </div>
                            <div>
                                <div class="community-stat-num">500+</div>
                                <div class="community-stat-label">Buổi Workshop</div>
                            </div>
                            <div>
                                <div class="community-stat-num">50+</div>
                                <div class="community-stat-label">Đối tác</div>
                            </div>
                        </div>

                        <p class="community-desc mb-4">Tham gia câu lạc bộ của chúng tôi để nhận ưu đãi độc quyền, kết
                            nối với cộng đồng yêu thể thao và nâng cao kỹ năng qua các buổi giao lưu.</p>

                        <ul class="community-benefits mb-4 list-unstyled">
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="9 12 12 15 16 9"></polyline>
                                </svg>
                                <span>Giảm giá 10% cho mọi đơn hàng.</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="9 12 12 15 16 9"></polyline>
                                </svg>
                                <span>Tham gia các buổi workshop nâng cao kỹ năng miễn phí.</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="9 12 12 15 16 9"></polyline>
                                </svg>
                                <span>Cập nhật sản phẩm mới nhất trước mọi người.</span>
                            </li>
                        </ul>

                        <router-link to="/client/register" class="btn-community">
                            Đăng ký tham gia ngay
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </router-link>
                    </div>
                </div>

                <!-- Mobile image -->
                <div class="col-12 d-lg-none px-0">
                    <div class="community-images-mobile">
                        <img src="https://images.unsplash.com/photo-1526232761682-d26e03ac148e?w=800&q=80"
                            alt="community" class="community-img-mobile w-100 h-100 object-fit-cover" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.community-section {
    width: 100%;
    background: #111827;
    position: relative;
}

.community-image-bg {
    position: absolute;
    top: 0;
    right: 0;
    width: 50%;
    height: 100%;
    overflow: hidden;
}

.community-image-bg .community-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .6s;
}

.community-image-bg:hover .community-img {
    transform: scale(1.04);
}

.community-content {
    padding: 60px 48px 60px 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.community-tag {
    color: #94a3b8;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.community-title {
    font-size: 2.1rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin: 0 0 0;
    letter-spacing: -.5px;
}

.community-title em {
    font-style: italic;
    color: var(--primary);
}

.community-stats-row {
    border-top: 1px solid rgba(255, 255, 255, .08);
    border-bottom: 1px solid rgba(255, 255, 255, .08);
}

.community-stat-num {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}

.community-stat-label {
    font-size: .85rem;
    color: #94a3b8;
    font-weight: 500;
    margin-top: 6px;
}

.community-desc {
    color: #94a3b8;
    font-size: .95rem;
    line-height: 1.7;
    max-width: 480px;
}

.community-benefits {
    color: #94a3b8;
    font-size: .95rem;
}

.community-benefits span {
    color: #cbd5e1;
}

.btn-community {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--primary);
    color: #fff;
    border: none;
    height: 42px;
    padding: 0 24px;
    border-radius: 8px;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .3s;
    text-decoration: none;
    width: fit-content;
    box-shadow: 0 4px 14px rgba(240, 62, 101, 0.35);
}

.btn-community:hover {
    transform: translateY(-2px);
    background: var(--primary-dark, #d82f65);
    box-shadow: 0 6px 18px rgba(240, 62, 101, 0.45);
}

.community-images-mobile {
    height: 300px;
    overflow: hidden;
}

.community-img-mobile {
    transition: transform .6s;
}

.community-images-mobile:hover .community-img-mobile {
    transform: scale(1.04);
}

@media (max-width: 991px) {
    .community-content {
        padding: 40px 15px;
    }
}
</style>
