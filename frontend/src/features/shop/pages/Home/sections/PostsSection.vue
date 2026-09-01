<script setup>
import { computed } from 'vue';
import { getStorageUrl } from '@/utils/url';

const props = defineProps({
    homePosts: { type: Array, default: () => [] },
});

const featuredHomePost = computed(() => {
    const feat = props.homePosts.find(p => p.is_featured);
    return feat || props.homePosts[0] || null;
});

const sideHomePosts = computed(() => {
    if (!featuredHomePost.value) return props.homePosts.slice(0, 3);
    return props.homePosts.filter(p => p.post_id !== featuredHomePost.value.post_id).slice(0, 3);
});

const FALLBACK_POST_IMG = 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80';

const getPostImage = (url) => {
    if (!url || url === '0' || url === 'null') return FALLBACK_POST_IMG;
    return getStorageUrl(url);
};

const handleImageError = (e) => {
    e.target.src = FALLBACK_POST_IMG;
};

const getAuthorName = (author) => {
    if (!author) return 'Ban Biên Tập';
    return author.full_name || author.name || 'Ban Biên Tập';
};

const getAuthorFallbackAvatar = (author) => {
    const name = encodeURIComponent(getAuthorName(author));
    return `https://ui-avatars.com/api/?name=${name}&background=e63b6f&color=fff&size=80&bold=true`;
};

const getAuthorAvatar = (author) => {
    if (!author?.avatar_url) return getAuthorFallbackAvatar(author);
    return getStorageUrl(author.avatar_url);
};

const getSummary = (post, limit = 96) => {
    const text = post?.summary || post?.excerpt || post?.content || '';
    const plain = String(text).replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
    if (!plain) return '';
    return plain.length > limit ? `${plain.slice(0, limit).trim()}...` : plain;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
</script>

<template>
    <section class="py-5 home-news-section" v-if="homePosts.length > 0">
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-4">
                <div>
                    <h2 class="section-title mb-1">TIN TỨC &amp; BÀI VIẾT</h2>
                    <p class="section-subtitle mb-0">Cập nhật tin tức thể thao và khuyến mãi mới nhất</p>
                </div>
                <router-link to="/posts" class="link-more d-flex align-items-center gap-1">
                    Xem tất cả
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </router-link>
            </div>

            <!-- Featured large + 3 side posts -->
            <div class="home-news-grid">
                <!-- Large featured post -->
                <router-link
                    v-if="featuredHomePost"
                    :to="'/posts/' + (featuredHomePost.slug || featuredHomePost.post_id)"
                    class="home-news-featured"
                >
                    <img :src="getPostImage(featuredHomePost.thumbnail_url)" :alt="featuredHomePost.title" class="home-news-featured-img" @error="handleImageError" />
                    <div class="home-news-featured-overlay">
                        <span class="hn-tag">{{ featuredHomePost.category?.name || 'Tin nổi bật' }}</span>
                        <h3 class="hn-title">{{ featuredHomePost.title }}</h3>
                        <p v-if="getSummary(featuredHomePost, 120)" class="hn-desc">
                            {{ getSummary(featuredHomePost, 120) }}
                        </p>
                        <div class="hn-author">
                            <img
                                :src="getAuthorAvatar(featuredHomePost.author)"
                                :alt="getAuthorName(featuredHomePost.author)"
                                class="hn-avt"
                                @error="e => e.target.src = getAuthorFallbackAvatar(featuredHomePost.author)"
                            />
                            <span class="hn-author-name">{{ getAuthorName(featuredHomePost.author) }}</span>
                        </div>
                    </div>
                </router-link>

                <!-- 3 small side posts -->
                <div class="home-news-side">
                    <div v-for="post in sideHomePosts" :key="post.post_id" class="home-post-card">
                        <router-link :to="'/posts/' + (post.slug || post.post_id)" class="home-post-img-wrap">
                            <img :src="getPostImage(post.thumbnail_url)" :alt="post.title" class="home-post-img" @error="handleImageError" />
                            <span class="home-post-tag">{{ post.category?.name || 'Tin tức' }}</span>
                        </router-link>
                        <div class="home-post-content">
                            <span class="home-post-date">{{ formatDate(post.published_at) }}</span>
                            <h3 class="home-post-title">
                                <router-link :to="'/posts/' + (post.slug || post.post_id)">
                                    {{ post.title }}
                                </router-link>
                            </h3>
                            <p v-if="getSummary(post)" class="home-post-desc">{{ getSummary(post) }}</p>
                            <div class="home-post-author">
                                <img
                                    :src="getAuthorAvatar(post.author)"
                                    :alt="getAuthorName(post.author)"
                                    class="hn-avt"
                                    @error="e => e.target.src = getAuthorFallbackAvatar(post.author)"
                                />
                                <span class="home-post-author-name">{{ getAuthorName(post.author) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* ── Shared section header styles ── */
.section-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: -0.5px;
    margin: 0;
}

.section-subtitle {
    color: #636E72;
    font-size: 0.95rem;
}

.link-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    white-space: nowrap;
    transition: gap 0.2s, color 0.2s;
}

.link-more:hover {
    color: #d82f65;
    gap: 10px;
}

/* ── Component styles ── */
.home-news-section {
    background: var(--card-bg, #fff);
}

.home-news-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 24px;
    align-items: start;
}

/* Featured */
.home-news-featured {
    position: relative;
    display: block;
    border-radius: 20px;
    overflow: hidden;
    height: 480px;
    text-decoration: none;
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.home-news-featured:hover {
    transform: translateY(-4px);
}

.home-news-featured-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.home-news-featured:hover .home-news-featured-img {
    transform: scale(1.04);
}

.home-news-featured-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(10, 14, 30, 0.88) 0%, rgba(10, 14, 30, 0.3) 55%, transparent 100%);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 32px;
}

.hn-tag {
    display: inline-block;
    background: var(--primary);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 8px;
    margin-bottom: 10px;
    width: fit-content;
}

.hn-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.4;
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hn-desc {
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.5;
    margin-bottom: 16px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hn-author {
    display: flex;
    align-items: center;
    gap: 8px;
}

.hn-avt {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.4);
    flex-shrink: 0;
}

.hn-author-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

/* Side posts */
.home-news-side {
    display: grid;
    grid-template-rows: repeat(3, minmax(0, 1fr));
    gap: 16px;
    height: 480px;
}

.home-post-card {
    display: grid;
    grid-template-columns: 4fr 8fr;
    gap: 0;
    min-height: 0;
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    overflow: hidden;
    transition: all 0.25s ease;
}

.home-post-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

.home-post-img-wrap {
    position: relative;
    width: 100%;
    min-width: 0;
    overflow: hidden;
}

.home-post-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.home-post-card:hover .home-post-img {
    transform: scale(1.08);
}

.home-post-tag {
    position: absolute;
    top: 8px;
    left: 8px;
    background: var(--primary);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 6px;
}

.home-post-content {
    min-width: 0;
    overflow: hidden;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 4px;
}

.home-post-date {
    font-size: 0.75rem;
    color: #94a3b8;
}

.home-post-title {
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin: 0;
}

.home-post-title a {
    color: var(--text-main);
    text-decoration: none;
    transition: color 0.2s;
}

.home-post-title a:hover {
    color: var(--primary);
}

.home-post-desc {
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin: 0;
}

.home-post-author {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: auto;
    padding-top: 4px;
}

.home-post-author-name {
    font-size: 0.76rem;
    font-weight: 600;
    color: #64748b;
}

@media (max-width: 991px) {
    .home-news-grid {
        grid-template-columns: 1fr;
    }

    .home-news-featured {
        height: 320px;
    }

    .home-news-side {
        height: auto;
    }
}

@media (max-width: 576px) {
    .home-post-card {
        grid-template-columns: 1fr;
    }

    .home-post-img-wrap {
        height: 160px;
    }

    .home-post-content {
        padding: 12px;
    }
}
</style>
