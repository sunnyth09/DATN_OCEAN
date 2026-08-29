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

const FALLBACK_POST_IMG = 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80';

const getImageUrl = (url) => {
  if (!url || url === '0' || url === 'null') return FALLBACK_POST_IMG;
  return getStorageUrl(url);
};

const handleImgError = (e) => {
  e.target.src = FALLBACK_POST_IMG;
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
    <div class="container" style="padding-top: 24px;">
      <div class="page-hero-card">
        <div class="hero-pill">OCEAN SPORT NEWS</div>
        <h1>Tin Tức &amp; Sự Kiện</h1>
        <p class="hero-sub">Cập nhật xu hướng thời trang thể thao mới nhất và các chương trình khuyến mãi độc quyền từ Ocean Sport</p>
      </div>
    </div>

      <!-- Main Content -->
      <section class="page-content">
      <!-- Search & Category Filters -->
      <div class="filter-section">
        <div class="category-tabs" v-if="categories.length <= 4">
          <button type="button" class="category-tab-btn" :class="{ active: selectedCategory === 'all' }"
            @click="selectedCategory = 'all'">
            Tất cả
          </button>
          <button v-for="cat in categories" :key="cat.post_category_id" type="button" class="category-tab-btn"
            :class="{ active: String(selectedCategory) === String(cat.post_category_id) }"
            @click="selectedCategory = cat.post_category_id">
            {{ cat.name }}
          </button>
        </div>
        <div class="category-select-wrap" v-else>
          <select id="post-category-filter" v-model="selectedCategory" class="category-select"
            aria-label="Lọc bài viết theo danh mục">
            <option value="all">Tất cả</option>
            <option v-for="cat in categories" :key="cat.post_category_id" :value="cat.post_category_id">
              {{ cat.name }}
            </option>
          </select>
        </div>

        <div class="search-bar">
          <AppIcon name="search" size="18" class="search-icon" />
          <input type="text" v-model="searchQuery" placeholder="Tìm kiếm bài viết..." aria-label="Tìm kiếm bài viết" />
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="post-list-skeleton">
        <div class="posts-grid">
          <div class="post-card-skeleton" v-for="i in 8" :key="i"
            style="background:#fff; border-radius:12px; overflow:hidden; border:1px solid #eee;">
            <div class="skeleton-pulse" style="height:170px; width:100%;"></div>
            <div style="padding: 16px;">
              <div class="skeleton-pulse" style="height:20px; width:80%; margin-bottom:10px; border-radius:4px;"></div>
              <div class="skeleton-pulse" style="height:14px; width:100%; margin-bottom:8px; border-radius:4px;"></div>
              <div class="skeleton-pulse" style="height:14px; width:60%; border-radius:4px;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredPosts.length === 0" class="empty-state">
        <AppIcon name="folder" size="48" class="empty-icon" />
        <h3>Không tìm thấy bài viết</h3>
        <p>Thử tìm kiếm với từ khóa khác hoặc chuyển danh mục.</p>
      </div>

      <template v-else>
        <!-- Featured Post (only on page 1 of All/Category) -->
        <div v-if="featuredPost" class="featured-post-card">
          <router-link :to="'/posts/' + (featuredPost.slug || featuredPost.post_id)" class="featured-img-wrap">
            <img :src="getImageUrl(featuredPost.thumbnail_url)" :alt="featuredPost.title" class="featured-img"
              @error="handleImgError" />
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
                <img :src="getAuthorAvatarUrl(featuredPost.author)" :alt="getAuthorName(featuredPost.author)"
                  class="author-avt" @error="e => e.target.src = getAuthorFallbackAvatar(featuredPost.author)" />
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

        <hr v-if="featuredPost && regularPosts.length > 0" class="section-divider" />

        <!-- Regular Posts Grid -->
        <div v-if="regularPosts.length > 0" class="posts-grid">
          <article v-for="post in visiblePosts" :key="post.post_id" class="post-card">
            <router-link :to="'/posts/' + (post.slug || post.post_id)" class="post-img-wrap">
              <img :src="getImageUrl(post.thumbnail_url)" :alt="post.title" class="post-img" @error="handleImgError" />
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
                <img :src="getAuthorAvatarUrl(post.author)" :alt="getAuthorName(post.author)" class="author-avt"
                  @error="e => e.target.src = getAuthorFallbackAvatar(post.author)" />
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
  </div>
</template>

<style scoped>
.static-page {
  font-family: var(--font-inter, 'Inter', sans-serif);
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
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
}

.post-card-author-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
}



/* Hero Section */
.page-hero-card {
  padding: 32px;
  border: 1px solid rgba(230, 59, 111, 0.18);
  border-radius: 16px;
  color: #fff;
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.18), transparent 28%),
    linear-gradient(135deg, var(--primary) 0%, #d92f66 48%, #f05a8a 100%);
  box-shadow: 0 18px 44px rgba(230, 59, 111, 0.18);
  margin-bottom: 28px;
  position: relative;
  overflow: hidden;
}

.hero-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.2);
  padding: 5px 14px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.page-hero-card h1 {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.5px;
  margin: 0 0 8px;
  line-height: 1.2;
}

.hero-sub {
  opacity: 0.95;
  font-size: 0.95rem;
  max-width: 580px;
  margin: 0;
  line-height: 1.55;
}

.page-content {
  padding: 0 24px 64px;
}
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

.category-select-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.category-select-label {
  font-size: 0.9rem;
  font-weight: 700;
  color: #334155;
}

.category-select {
  min-width: 220px;
  max-width: 100%;
  padding: 10px 36px 10px 16px;
  border: 1.5px solid #e2e8f0;
  border-radius: 24px;
  background-color: #fff;
  color: #334155;
  font-family: inherit;
  font-size: 0.9rem;
  font-weight: 600;
  outline: none;
  cursor: pointer;
  transition: all 0.2s ease;

  /* Custom dropdown arrow (size 8px) */
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  background-size: 8px 8px;
}

.category-select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

/* Category Tabs */
.category-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.category-tab-btn {
  background: #f1f5f9;
  color: #475569;
  border: none;
  padding: 8px 20px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.category-tab-btn:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.category-tab-btn.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
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

/* Regular Grid (4 Cột Desktop) */
.posts-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 22px;
}

.post-card {
  background: var(--card-bg);
  border: 1px solid #f1f5f9;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
  transition: all 0.3s ease;
}

.post-card:hover {
  transform: translateY(-5px);
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
  top: 10px;
  left: 10px;
  background: var(--primary);
  color: #fff;
  padding: 3px 8px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.7rem;
  text-transform: uppercase;
}

.post-card-content {
  padding: 16px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

.post-card-meta {
  display: flex;
  justify-content: space-between;
  color: #94a3b8;
  font-size: 0.78rem;
  margin-bottom: 10px;
}

.post-card-views {
  display: flex;
  align-items: center;
  gap: 4px;
}

.post-card-title {
  font-size: 1.02rem;
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
  font-size: 0.84rem;
  line-height: 1.55;
  margin-bottom: 14px;
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
}

@media (max-width: 1200px) {
  .posts-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
  }
}

@media (max-width: 900px) {
  .posts-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
  }
}

@media (max-width: 768px) {
  .static-page {
    padding-top: 0;
  }

  .page-hero {
    padding: 32px 0;
  }

  .page-hero .container {
    padding: 0 16px;
  }

  .page-hero h1 {
    font-size: 1.5rem;
  }

  .page-content {
    padding: 16px 0 60px;
  }

  .filter-section {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    margin-bottom: 24px;
  }

  .search-bar {
    max-width: 100%;
  }

  .featured-info {
    padding: 18px;
  }

  .featured-title {
    font-size: 1.15rem;
  }

  /* 2 Cột Trên Điện Thoại */
  .posts-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }

  .post-card {
    border-radius: 12px;
  }

  .post-card-content {
    padding: 12px 10px 14px;
  }

  .post-card-tag {
    top: 8px;
    left: 8px;
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 6px;
  }

  .post-card-meta {
    font-size: 0.72rem;
    margin-bottom: 6px;
  }

  .post-card-title {
    font-size: 0.88rem;
    line-height: 1.35;
    margin-bottom: 6px;
    height: 2.7em;
  }

  .post-card-summary {
    display: none; /* Ẩn tóm tắt trên mobile 2 cột để thẻ gọn gàng, sắc sảo */
  }

  .post-card-author {
    padding-top: 8px;
    gap: 5px;
  }

  .post-card-author-name {
    font-size: 0.72rem;
  }

  .author-avt {
    width: 18px;
    height: 18px;
  }
}

/* ===== Modern Skeleton Loading Styles ===== */
.post-list-skeleton {
  width: 100%;
  pointer-events: none;
}

.skeleton-pulse {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-pulse::after {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(90deg,
      rgba(255, 255, 255, 0) 0,
      rgba(255, 255, 255, 0.4) 30%,
      rgba(255, 255, 255, 0.75) 60%,
      rgba(255, 255, 255, 0) 100%);
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
