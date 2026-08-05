<script setup>
</script>
<template>
    <section class="community-section py-5">
        <div class="container">
            <div class="row g-4">
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
                        <div class="d-flex gap-4 py-4 my-3 community-stats-row">
                            <div>
                                <div class="community-stat-num">10K+</div>
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

                        <router-link to="/client/register" class="btn-community">
                            Đăng ký tham gia ngay
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </router-link>
                    </div>
                </div>

                <!-- Right image -->
                <div class="col-lg-6">
                    <div class="community-images h-100">
                        <img src="https://images.unsplash.com/photo-1526232761682-d26e03ac148e?w=800&q=80"
                            alt="community" class="community-img" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.community-section {
    width: 100%;
}

.community-content {
    background: #111827;
    /* Dark background */
    border-radius: 24px;
    padding: 60px 48px;
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
    color: #fff;
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

.btn-community {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: #111827;
    border: none;
    padding: 13px 30px;
    border-radius: 99px;
    font-size: .95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .3s;
    text-decoration: none;
    width: fit-content;
}

.btn-community:hover {
    transform: translateY(-3px);
    background: #f1f5f9;
}

.community-images {
    border-radius: 24px;
    overflow: hidden;
    position: relative;
    min-height: 400px;
}

.community-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .6s;
    position: absolute;
    top: 0;
    left: 0;
}

.community-images:hover .community-img {
    transform: scale(1.04);
}

@media (max-width: 991px) {
    .community-content {
        padding: 40px 30px;
    }

    .community-images {
        min-height: 300px;
    }
}
</style>
