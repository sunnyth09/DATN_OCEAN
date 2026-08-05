<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/axios';
import AppIcon from '@/components/AppIcon.vue';

const posts = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const activeCategory = ref('all');

const fetchPosts = async () => {
    loading.value = true;
    try {
        const res = await api.get('/posts');
        // If the API returns direct array or pagination object
        posts.value = Array.isArray(res.data) ? res.data : (res.data.data || res.data);
    } catch (err) {
        console.error('Error fetching posts:', err);
    } finally {
        loading.value = false;
    }
};

const categories = computed(() => {
    const cats = posts.value.map(p => p.category).filter(Boolean);
    const uniqueCats = [];
    const seenIds = new Set();
    
    cats.forEach(c => {
        if (!seenIds.has(c.post_category_id)) {
            seenIds.add(c.post_category_id);
            uniqueCats.push(c);
        }
    });
    return uniqueCats;
});

const filteredPosts = computed(() => {
    let list = posts.value;
    if (activeCategory.value !== 'all') {
        list = list.filter(p => p.post_category_id === activeCategory.value);
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(p => p.title.toLowerCase().includes(q) || (p.summary && p.summary.toLowerCase().includes(q)));
    }
    return list;
});

const getImageUrl = (path) => {
    const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');
    if (!path || path === '0') return 'https://placehold.co/600x400?text=QS+Sport';
    if (path.startsWith('http')) return path;
    if (path.startsWith('/storage/') || path.startsWith('storage/')) {
        const cleanPath = path.startsWith('/') ? path : `/${path}`;
        return `${BASE_URL}${cleanPath}`;
    }
    return `${BASE_URL}/storage/${path}`;
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`;
};

onMounted(fetchPosts);
</script>

<template>
  <div class="blog-wrapper">
    <!-- Hero Header -->
    <header class="blog-hero">
      <div class="hero-content">
        <span class="hero-badge">Cẩm Nang Thể Thao</span>
        <h1 class="hero-title">Tin Tức & Kiến Thức Thể Thao</h1>
        <p class="hero-desc">Khám phá các mẹo bổ ích, hướng dẫn chọn dụng cụ và cập nhật tin tức mới nhất từ Quyền Sport.</p>
      </div>
    </header>

    <!-- Filter & Search Bar -->
    <div class="filter-bar">
      <div class="categories-tabs">
        <button 
          class="cat-tab" 
          :class="{ active: activeCategory === 'all' }" 
          @click="activeCategory = 'all'"
        >
          Tất cả bài viết
        </button>
        <button 
          v-for="cat in categories" 
          :key="cat.post_category_id" 
          class="cat-tab"
          :class="{ active: activeCategory === cat.post_category_id }"
          @click="activeCategory = cat.post_category_id"
        >
          {{ cat.name }}
        </button>
      </div>

      <div class="search-box">
        <input 
          type="text" 
          v-model="searchQuery" 
          placeholder="Tìm kiếm bài viết..." 
          class="search-input"
        />
        <span class="search-icon">
          <AppIcon name="search" size="18" />
        </span>
      </div>
    </div>

    <!-- Posts Grid -->
    <main class="posts-section">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Đang tải bài viết...</p>
      </div>

      <div v-else-if="filteredPosts.length === 0" class="empty-state">
        <AppIcon name="alert-circle" size="48" />
        <h3>Không tìm thấy bài viết nào</h3>
        <p>Thử tìm kiếm bằng từ khóa khác hoặc chuyển danh mục.</p>
      </div>

      <div v-else class="posts-grid">
        <article 
          v-for="post in filteredPosts" 
          :key="post.post_id" 
          class="post-card"
        >
          <router-link :to="'/posts/' + post.slug" class="card-img-link">
            <div class="card-image-box">
              <img :src="getImageUrl(post.thumbnail_url || post.banner_url)" :alt="post.title" class="card-img"/>
              <span class="card-cat-badge" v-if="post.category">{{ post.category.name }}</span>
            </div>
          </router-link>

          <div class="card-content">
            <div class="card-meta">
              <span class="meta-item">
                <AppIcon name="calendar" size="14" />
                {{ formatDate(post.published_at || post.created_at) }}
              </span>
              <span class="meta-item">
                <AppIcon name="eye" size="14" />
                {{ post.view_count || 0 }} lượt xem
              </span>
            </div>

            <h2 class="card-title">
              <router-link :to="'/posts/' + post.slug">{{ post.title }}</router-link>
            </h2>

            <p class="card-summary">{{ post.summary || 'Nhấp để đọc toàn bộ bài viết hướng dẫn thể thao cao cấp từ Quyền Sport.' }}</p>

            <div class="card-footer">
              <router-link :to="'/posts/' + post.slug" class="read-more-btn">
                Đọc tiếp 
                <AppIcon name="arrow-right" size="14" />
              </router-link>
            </div>
          </div>
        </article>
      </div>
    </main>
  </div>
</template>

<style scoped>
.blog-wrapper {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 16px 60px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: #2d3436;
}

/* Hero Header */
.blog-hero {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  border-radius: 20px;
  padding: 60px 40px;
  color: white;
  margin-bottom: 40px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}
.blog-hero::before {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  background: radial-gradient(circle at 80% 20%, rgba(230, 59, 111, 0.15) 0%, transparent 50%);
  pointer-events: none;
}
.hero-content {
  position: relative;
  max-width: 600px;
  z-index: 2;
}
.hero-badge {
  display: inline-block;
  background: rgba(230, 59, 111, 0.2);
  color: #ff5e8c;
  padding: 6px 16px;
  border-radius: 30px;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  margin-bottom: 16px;
  border: 1px solid rgba(230, 59, 111, 0.3);
}
.hero-title {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 16px;
}
.hero-desc {
  font-size: 1.05rem;
  color: #cbd5e1;
  line-height: 1.6;
}

/* Filter Bar */
.filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}
.categories-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.cat-tab {
  padding: 10px 20px;
  border-radius: 30px;
  border: 1px solid #e2e8f0;
  background: white;
  font-size: 0.9rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
}
.cat-tab:hover {
  border-color: #E63B6F;
  color: #E63B6F;
}
.cat-tab.active {
  background: #E63B6F;
  border-color: #E63B6F;
  color: white;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
}

/* Search Box */
.search-box {
  position: relative;
  min-width: 280px;
}
.search-input {
  width: 100%;
  padding: 11px 16px 11px 40px;
  border-radius: 30px;
  border: 1px solid #e2e8f0;
  font-size: 0.9rem;
  outline: none;
  font-family: inherit;
  transition: border-color 0.2s;
  background: white;
}
.search-input:focus {
  border-color: #E63B6F;
}
.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  display: flex;
  align-items: center;
}

/* Loading/Empty State */
.loading-state, .empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f1f5f9;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.empty-state h3 {
  font-size: 1.25rem;
  color: #1e293b;
  margin: 16px 0 8px;
  font-weight: 700;
}

/* Posts Grid */
.posts-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 30px;
}
.post-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.post-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
}
.card-img-link {
  display: block;
}
.card-image-box {
  position: relative;
  aspect-ratio: 16/10;
  overflow: hidden;
  background: #f1f5f9;
}
.card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}
.post-card:hover .card-img {
  transform: scale(1.05);
}
.card-cat-badge {
  position: absolute;
  top: 14px;
  left: 14px;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(4px);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
}

.card-content {
  padding: 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.card-meta {
  display: flex;
  gap: 16px;
  font-size: 0.78rem;
  color: #64748b;
  margin-bottom: 12px;
}
.meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
}
.card-title {
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.4;
  margin: 0 0 10px;
}
.card-title a {
  color: #1e293b;
  text-decoration: none;
  transition: color 0.2s;
}
.card-title a:hover {
  color: #E63B6F;
}
.card-summary {
  font-size: 0.9rem;
  color: #64748b;
  line-height: 1.5;
  margin-bottom: 20px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  flex: 1;
}
.card-footer {
  border-top: 1px solid #f1f5f9;
  padding-top: 16px;
}
.read-more-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #E63B6F;
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 700;
  transition: gap 0.2s;
}
.read-more-btn:hover {
  gap: 10px;
}

/* Responsive */
@media (max-width: 1024px) {
  .posts-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 640px) {
  .blog-hero {
    padding: 40px 24px;
  }
  .hero-title {
    font-size: 2rem;
  }
  .posts-grid {
    grid-template-columns: 1fr;
  }
  .filter-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .search-box {
    min-width: unset;
  }
}
</style>
