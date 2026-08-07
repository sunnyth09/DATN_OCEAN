<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/axios';
import AppIcon from '@/components/AppIcon.vue';
import { getStorageUrl } from '@/utils/url';

const posts = ref([]);
const categories = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const selectedCategory = ref('all');

// Load more
const currentPage = ref(1);
const postsPerPage = 6;

const fetchPosts = async () => {
  try {
    isLoading.value = true;
    const res = await api.get('/posts', { params: { status: 'published' } });
    posts.value = res.data || [];
  } catch (e) {
    console.error('Lỗi tải bài viết:', e);
  } finally {
    isLoading.value = false;
  }
};

const fetchCategories = async () => {
  try {
    const res = await api.get('/post-categories');
    categories.value = res.data?.data || res.data || [];
  } catch (e) {
    console.error('Lỗi tải danh mục bài viết:', e);
  }
};

onMounted(() => {
  fetchPosts();
  fetchCategories();
});

// Filter posts
const filteredPosts = computed(() => {
  let result = posts.value;

  // Filter by category
  if (selectedCategory.value !== 'all') {
    result = result.filter(p => String(p.post_category_id) === String(selectedCategory.value));
  }

  // Filter by search query
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    result = result.filter(p =>
      (p.title && p.title.toLowerCase().includes(q)) ||
      (p.summary && p.summary.toLowerCase().includes(q))
    );
  }

  return result;
});

// Separate the featured post (latest featured or just latest)
const featuredPost = computed(() => {
  const featured = filteredPosts.value.filter(p => p.is_featured);
  if (featured.length > 0) return featured[0];
  return filteredPosts.value[0] || null;
});

// Remaining posts (excluding the featured one)
const regularPosts = computed(() => {
  if (!featuredPost.value) return filteredPosts.value;
  return filteredPosts.value.filter(p => p.post_id !== featuredPost.value.post_id);
});

// Visible regular posts
const visiblePosts = computed(() => {
  return regularPosts.value.slice(0, currentPage.value * postsPerPage);
});

const hasMorePosts = computed(() => {
  return visiblePosts.value.length < regularPosts.value.length;
});

const loadMorePosts = () => {
  if (hasMorePosts.value) currentPage.value += 1;
};

watch([searchQuery, selectedCategory], () => {
  currentPage.value = 1;
});

const getImageUrl = (url) => {
  if (!url || url === '0') return 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80';
  return getStorageUrl(url);
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getAuthorName = (author) => {
  if (!author) return 'Ban Biên Tập';
  return author.full_name || author.name || 'Ban Biên Tập';
};

const getAuthorFallbackAvatar = (author) => {
  const name = encodeURIComponent(getAuthorName(author));
  return `https://ui-avatars.com/api/?name=${name}&background=e63b6f&color=fff&size=80&bold=true`;
};

const getAuthorAvatarUrl = (author) => {
  if (!author?.avatar_url) return getAuthorFallbackAvatar(author);
  return getStorageUrl(author.avatar_url);
};

</script>

<template>
  <div class="static-page">
    <!-- Hero Banner -->
    <section class="page-hero">
      <div class="container">
        <h1>Tin Tức & Sự Kiện</h1>
        <p class="hero-sub">Cập nhật xu hướng thời trang thể thao mới nhất và các chương trình khuyến mãi độc quyền từ
          Ocean Sport</p>
      </div>
    </section>

    <!-- Main Content -->
    <section class="page-content container">
      <!-- Search & Category Filters -->
      <div class="filter-section">
        <div class="category-tabs">
          <button class="filter-tab" :class="{ active: selectedCategory === 'all' }" @click="selectedCategory = 'all'">
            Tất cả
          </button>
          <button v-for="cat in categories" :key="cat.post_category_id" class="filter-tab"
            :class="{ active: selectedCategory === cat.post_category_id }"
            @click="selectedCategory = cat.post_category_id">
            {{ cat.name }}
          </button>
        </div>

        <div class="search-bar">
          <AppIcon name="search" size="18" class="search-icon" />
          <input type="text" v-model="searchQuery" placeholder="Tìm kiếm bài viết..." aria-label="Tìm kiếm bài viết" />
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Đang tải bài viết...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredPosts.length === 0" class="empty-state">
        <AppIcon name="folder" size="48" class="empty-icon" />
        <h3>Không tìm thấy bài viết</h3>
        <p>Thử tìm kiếm với từ khóa khác hoặc chuyển danh mục.</p>
      </div>

      <template v-else>
        <!-- Featured Post (only on page 1 of All/Category) -->
        <div v-if="featuredPost && currentPage === 1" class="featured-post-card">
          <router-link :to="'/posts/' + (featuredPost.slug || featuredPost.post_id)" class="featured-img-wrap">
            <img :src="getImageUrl(featuredPost.thumbnail_url)" :alt="featuredPost.title" class="featured-img" />
          </router-link>
          <div class="featured-info">
            <div class="post-meta">
              <span class="post-tag">{{ featuredPost.category?.name || 'Tin tức' }}</span>
              <span class="post-date">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
                {{ formatDate(featuredPost.published_at) }}
              </span>
              <span class="post-views">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                {{ featuredPost.view_count || 0 }} lượt xem
              </span>
              <span class="post-author-chip">
                <img
                  :src="getAuthorAvatarUrl(featuredPost.author)"
                  :alt="getAuthorName(featuredPost.author)"
                  class="author-avt"
                  @error="e => e.target.src = getAuthorFallbackAvatar(featuredPost.author)"
                />
                {{ getAuthorName(featuredPost.author) }}
              </span>
            </div>
            <h2 class="featured-title">
              <router-link :to="'/posts/' + (featuredPost.slug || featuredPost.post_id)">
                {{ featuredPost.title }}
              </router-link>
            </h2>
            <p class="featured-summary">{{ featuredPost.summary }}</p>
            <router-link :to="'/posts/' + (featuredPost.slug || featuredPost.post_id)" class="btn-read-more">
              Đọc bài viết
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M5 12h14M12 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>

        <hr v-if="featuredPost && currentPage === 1 && regularPosts.length > 0" class="section-divider" />

        <!-- Regular Posts Grid -->
        <div v-if="regularPosts.length > 0" class="posts-grid">
          <article v-for="post in visiblePosts" :key="post.post_id" class="post-card">
            <router-link :to="'/posts/' + (post.slug || post.post_id)" class="post-img-wrap">
              <img :src="getImageUrl(post.thumbnail_url)" :alt="post.title" class="post-img" />
              <span class="post-card-tag">{{ post.category?.name || 'Tin tức' }}</span>
            </router-link>
            <div class="post-card-content">
              <div class="post-card-meta">
                <span class="post-card-date">{{ formatDate(post.published_at) }}</span>
                <span class="post-card-views">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  {{ post.view_count || 0 }}
                </span>
              </div>
              <h3 class="post-card-title">
                <router-link :to="'/posts/' + (post.slug || post.post_id)">
                  {{ post.title }}
                </router-link>
              </h3>
              <p class="post-card-summary">{{ post.summary }}</p>
              <div class="post-card-author">
                <img
                  :src="getAuthorAvatarUrl(post.author)"
                  :alt="getAuthorName(post.author)"
                  class="author-avt"
                  @error="e => e.target.src = getAuthorFallbackAvatar(post.author)"
                />
                <span class="post-card-author-name">{{ getAuthorName(post.author) }}</span>
              </div>
            </div>
          </article>
        </div>

        <!-- Load More -->
        <div v-if="hasMorePosts" class="blog-pagination">
          <button class="btn-load-more" @click="loadMorePosts">
            Xem thêm bài viết
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 5v14M5 12h14" />
            </svg>
          </button>
        </div>
      </template>
    </section>
  </div>
</template>

<style scoped>
.static-page {
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  padding-top: 24px;
}

/* Author elements */
.post-author-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: #374151;
  font-size: 0.83rem;
}

.author-avt {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1.5px solid #fde8d8;
}

.post-card-author {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.post-card-author-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
}



.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Hero Section */
.page-hero {
  max-width: 1160px;
  margin: 0 auto;
  padding: 0 24px;
  color: #fff;
  text-align: center;
}

.page-hero .container {
  max-width: 100%;
  padding: 54px 24px;
  border: 1px solid rgba(230, 59, 111, 0.18);
  border-radius: 24px;
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.18), transparent 28%),
    linear-gradient(135deg, var(--primary) 0%, #d92f66 48%, #f05a8a 100%);
  box-shadow: 0 18px 44px rgba(230, 59, 111, 0.18);
}

.page-hero h1 {
  font-size: 1.75rem;
  font-weight: 800;
  margin: 0 0 8px;
  position: relative;
  z-index: 1;
}

.hero-sub {
  opacity: 0.85;
  font-size: 0.95rem;
  max-width: 500px;
  margin: 5px auto 0;
  z-index: 1;
  line-height: 1.6;
  text-align: center;
}

.page-content {
  padding: 48px 24px 64px;
}

/* Filters */
.filter-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 40px;
}

.category-tabs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.filter-tab {
  background: #f1f5f9;
  border: none;
  padding: 8px 18px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.25s ease;
}

.filter-tab:hover {
  background: #e2e8f0;
  color: var(--primary);
}

.filter-tab.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.25);
}

.search-bar {
  position: relative;
  width: 100%;
  max-width: 320px;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.search-bar input {
  width: 100%;
  padding: 10px 16px 10px 42px;
  border: 1.5px solid #e2e8f0;
  border-radius: 24px;
  font-size: 0.9rem;
  outline: none;
  font-family: inherit;
  transition: all 0.2s ease;
}

.search-bar input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

/* Featured Card */
.featured-post-card {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 40px;
  background: var(--card-bg);
  border: 1px solid #f1f5f9;
  border-radius: 20px;
  overflow: hidden;
  margin-bottom: 48px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
  transition: all 0.3s ease;
  height: 380px;
}

.featured-post-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
}

.featured-img-wrap {
  position: relative;
  height: 100%;
  overflow: hidden;
}

.featured-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
}

.featured-post-card:hover .featured-img {
  transform: scale(1.04);
}

.featured-info {
  padding: 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.post-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 16px;
  font-size: 0.85rem;
  color: #64748b;
}

.post-tag {
  background: #fff0f3;
  color: var(--primary);
  padding: 4px 12px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.8rem;
  text-transform: uppercase;
}

.post-date,
.post-views {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.featured-title {
  font-size: 1.6rem;
  font-weight: 800;
  line-height: 1.4;
  margin-bottom: 12px;
}

.featured-title a {
  color: var(--text-main);
  text-decoration: none;
  transition: color 0.2s ease;
}

.featured-title a:hover {
  color: var(--primary);
}

.featured-summary {
  color: #475569;
  font-size: 0.95rem;
  line-height: 1.6;
  margin-bottom: 24px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.btn-read-more {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--primary);
  text-decoration: none;
  font-weight: 700;
  font-size: 0.95rem;
  transition: all 0.2s ease;
}

.btn-read-more:hover {
  gap: 12px;
}

.section-divider {
  border: 0;
  height: 1px;
  background: #e2e8f0;
  margin: 40px 0;
}

/* Regular Grid */
.posts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 30px;
}

.post-card {
  background: var(--card-bg);
  border: 1px solid #f1f5f9;
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
  transition: all 0.3s ease;
}

.post-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 12px 25px rgba(0, 0, 0, 0.06);
}

.post-img-wrap {
  position: relative;
  padding-bottom: 56.25%;
  /* 16:9 Aspect Ratio */
  overflow: hidden;
}

.post-img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
}

.post-card:hover .post-img {
  transform: scale(1.05);
}

.post-card-tag {
  position: absolute;
  top: 14px;
  left: 14px;
  background: var(--primary);
  color: #fff;
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.75rem;
  text-transform: uppercase;
}

.post-card-content {
  padding: 24px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

.post-card-meta {
  display: flex;
  justify-content: space-between;
  color: #94a3b8;
  font-size: 0.8rem;
  margin-bottom: 12px;
}

.post-card-views {
  display: flex;
  align-items: center;
  gap: 4px;
}

.post-card-title {
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.4;
  margin-bottom: 8px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  height: 2.8em;
}

.post-card-title a {
  color: var(--text-main);
  text-decoration: none;
  transition: color 0.2s ease;
}

.post-card-title a:hover {
  color: var(--primary);
}

.post-card-summary {
  color: #64748b;
  font-size: 0.88rem;
  line-height: 1.6;
  margin-bottom: 16px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  flex-grow: 1;
}

.post-card-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--primary);
  text-decoration: none;
  font-weight: 700;
  font-size: 0.88rem;
  transition: all 0.2s ease;
}

.post-card-link:hover {
  gap: 8px;
}

/* Load more */
.blog-pagination {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 48px;
}

.btn-load-more {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 28px;
  border-radius: 999px;
  border: 1.5px solid var(--primary);
  background: var(--card-bg);
  color: var(--primary);
  font-family: inherit;
  font-weight: 800;
  font-size: 0.92rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-load-more:hover {
  background: var(--primary);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(230, 59, 111, 0.22);
}

/* States */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 0;
  color: #64748b;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid var(--primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(360deg);
  }
}

.empty-state {
  text-align: center;
  padding: 80px 0;
  color: #64748b;
}

.empty-icon {
  color: #cbd5e1;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 6px;
}

@media (max-width: 991px) {
  .featured-post-card {
    grid-template-columns: 1fr;
    gap: 0;
    height: auto;
  }

  .featured-img-wrap {
    height: 240px;
  }
}

@media (max-width: 768px) {
  .static-page {
    padding-top: 16px;
  }

  .page-hero {
    padding: 0 16px;
  }

  .page-hero .container {
    padding: 40px 18px;
    border-radius: 18px;
  }

  .page-content {
    padding: 36px 24px 56px;
  }

  .filter-section {
    flex-direction: column;
    align-items: stretch;
  }

  .search-bar {
    max-width: 100%;
  }

  .featured-info {
    padding: 24px;
  }

  .featured-title {
    font-size: 1.3rem;
  }

  .posts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
