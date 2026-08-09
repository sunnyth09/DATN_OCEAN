<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import DOMPurify from 'dompurify';
import AppIcon from '@/icons/AppIcon.vue';
import { getStorageUrl } from '@/utils/url';

const route = useRoute();
const router = useRouter();

const post = ref(null);
const relatedPosts = ref([]);
const isLoading = ref(true);

const sanitizedContent = computed(() => DOMPurify.sanitize(post.value?.content || '', {
  USE_PROFILES: { html: true },
}));

const fetchPostDetail = async (idOrSlug) => {
  try {
    isLoading.value = true;
    const res = await api.get(`/posts/${idOrSlug}`);
    post.value = res.data;
    if (post.value) {
      fetchRelatedPosts(post.value.post_category_id, post.value.post_id);
    }
  } catch (e) {
    console.error('Lỗi tải chi tiết bài viết:', e);
    // If not found, redirect to post list
    router.push({ name: 'post-list' });
  } finally {
    isLoading.value = false;
  }
};

const fetchRelatedPosts = async (categoryId, excludeId) => {
  try {
    const res = await api.get('/posts', { params: { status: 'published', limit: 4 } });
    const data = res.data || [];
    relatedPosts.value = data
      .filter(p => p.post_id !== excludeId)
      .slice(0, 3);
  } catch (e) {
    console.error('Lỗi tải bài viết liên quan:', e);
  }
};

onMounted(() => {
  fetchPostDetail(route.params.idOrSlug);
});

// Refetch if the route changes (e.g. clicking related post)
watch(
  () => route.params.idOrSlug,
  (newVal) => {
    if (newVal) {
      fetchPostDetail(newVal);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }
);

const getImageUrl = (url) => {
  if (!url || url === '0') return 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80';
  return getStorageUrl(url);
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const shareOnFacebook = () => {
  const url = encodeURIComponent(window.location.href);
  window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank');
};

const copyLink = () => {
  navigator.clipboard.writeText(window.location.href);
  alert('Đã sao chép đường dẫn bài viết!');
};

const getAuthorName = (author) => {
  if (!author) return 'Ban Biên Tập';
  return author.full_name || author.name || 'Ban Biên Tập';
};

const getAuthorFallbackAvatar = (author) => {
  const name = encodeURIComponent(getAuthorName(author));
  return `https://ui-avatars.com/api/?name=${name}&background=e63b6f&color=fff&size=96&bold=true`;
};

const getAuthorAvatarUrl = (author) => {
  if (!author?.avatar_url) return getAuthorFallbackAvatar(author);
  return getStorageUrl(author.avatar_url);
};

const getAuthorRole = (author) => {
  if (!author) return 'Biên tập viên';
  const roleMap = { admin: 'Quản trị viên', staff: 'Biên tập viên', seller: 'Cộng tác viên' };
  return roleMap[author.role] || 'Biên tập viên';
};
</script>

<template>
  <div class="static-page">
    <section class="page-content container">
      <!-- Loading State -->
      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Đang tải bài viết...</p>
      </div>

      <div v-else-if="post" class="detail-layout">
        <!-- Main Article Column -->
        <article class="article-col">
          <!-- Breadcrumb -->
          <nav class="breadcrumb">
            <router-link to="/">Trang chủ</router-link>
            <span class="sep">/</span>
            <router-link to="/posts">Tin tức</router-link>
            <span class="sep">/</span>
            <span class="current">{{ post.title }}</span>
          </nav>

          <!-- Post Header -->
          <header class="article-header">
            <div class="post-meta">
              <span class="post-tag">{{ post.category?.name || 'Tin tức' }}</span>
              <span class="post-date">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ formatDate(post.published_at) }}
              </span>
              <span class="post-views">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                {{ post.view_count || 0 }} lượt xem
              </span>
              <span class="post-author-chip">
                <img
                  :src="getAuthorAvatarUrl(post.author)"
                  :alt="getAuthorName(post.author)"
                  class="author-avatar-sm"
                  @error="e => e.target.src = getAuthorFallbackAvatar(post.author)"
                />
                {{ getAuthorName(post.author) }}
              </span>
            </div>
            <h1 class="article-title">{{ post.title }}</h1>
            <p class="article-summary">{{ post.summary }}</p>
          </header>

          <!-- Banner / Featured Image -->
          <div class="article-banner">
            <img :src="getImageUrl(post.banner_url || post.thumbnail_url)" :alt="post.title" @error="e => e.target.src = 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80'" />
          </div>

          <!-- Rich Text Content (Quill Render) -->
          <div class="article-body ql-editor" v-html="sanitizedContent"></div>

          <!-- Author Bio Card -->
          <div class="author-bio-card">
            <img
              :src="getAuthorAvatarUrl(post.author)"
              :alt="getAuthorName(post.author)"
              class="author-avatar-lg"
              @error="e => e.target.src = getAuthorFallbackAvatar(post.author)"
            />
            <div class="author-bio-info">
              <div class="author-bio-top">
                <span class="author-bio-name">{{ getAuthorName(post.author) }}</span>
                <span class="author-bio-role">{{ getAuthorRole(post.author) }}</span>
              </div>
              <p class="author-bio-desc">Bài viết được biên soạn và đăng tải bởi đội ngũ biên tập Ocean. Mọi thông tin được kiểm duyệt kỹ lưỡng trước khi xuất bản.</p>
            </div>
          </div>

          <!-- Article Footer / Share -->
          <footer class="article-footer">
            <div class="share-box">
              <span class="share-label">Chia sẻ bài viết:</span>
              <div class="share-buttons">
                <button @click="shareOnFacebook" class="btn-share facebook" aria-label="Chia sẻ qua Facebook">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                  Facebook
                </button>
                <button @click="copyLink" class="btn-share link" aria-label="Sao chép đường dẫn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                  Sao chép link
                </button>
              </div>
            </div>
          </footer>
        </article>

        <!-- Sidebar (Related / Latest Posts) -->
        <aside class="sidebar-col">
          <div class="sidebar-widget">
            <h3 class="widget-title">Bài viết liên quan</h3>
            <div v-if="relatedPosts.length === 0" class="widget-empty">
              Không có bài viết liên quan nào khác.
            </div>
            <div v-else class="widget-posts">
              <div v-for="rp in relatedPosts" :key="rp.post_id" class="widget-post-item">
                <router-link :to="'/posts/' + (rp.slug || rp.post_id)" class="widget-post-img">
                  <img :src="getImageUrl(rp.thumbnail_url)" :alt="rp.title" @error="e => e.target.src = 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80'" />
                </router-link>
                <div class="widget-post-info">
                  <span class="widget-post-date">{{ formatDate(rp.published_at).split(' ')[0] }}</span>
                  <h4 class="widget-post-title">
                    <router-link :to="'/posts/' + (rp.slug || rp.post_id)">{{ rp.title }}</router-link>
                  </h4>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </div>
</template>

<style scoped>
.static-page {
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  padding-top: 24px;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.page-content {
  padding: 24px 24px 64px;
}

.detail-layout {
  display: grid;
  grid-template-columns: minmax(0, 2.2fr) minmax(320px, 344px);
  gap: 48px;
  align-items: start;
}

/* Breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 0.88rem;
  color: #64748b;
  margin-bottom: 24px;
}

.breadcrumb a {
  color: #64748b;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s ease;
}

.breadcrumb a:hover {
  color: var(--primary);
}

.breadcrumb .sep {
  color: #cbd5e1;
}

.breadcrumb .current {
  color: #0f172a;
  font-weight: 600;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
  max-width: 300px;
}

/* Header */
.article-header {
  margin-bottom: 32px;
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

.post-date, .post-views {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.article-title {
  font-size: 2.2rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.3;
  margin-bottom: 16px;
}

.article-summary {
  font-size: 1.1rem;
  line-height: 1.6;
  color: #475569;
  font-weight: 500;
  border-left: 4px solid var(--primary);
  padding-left: 20px;
  margin: 20px 0;
}

/* Banner */
.article-banner {
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 40px;
  max-height: 480px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.article-banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Quill Rendering */
.article-body {
  font-size: 1.05rem;
  line-height: 1.8;
  color: #334155;
  margin-bottom: 48px;
}

.article-body :deep(p) {
  margin-bottom: 1.5rem;
}

.article-body :deep(h2) {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin-top: 2rem;
  margin-bottom: 1rem;
}

.article-body :deep(h3) {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}

.article-body :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 12px;
  margin: 1.5rem 0;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
}

.article-body :deep(ul), .article-body :deep(ol) {
  margin-bottom: 1.5rem;
  padding-left: 1.5rem;
}

.article-body :deep(li) {
  margin-bottom: 0.5rem;
}

.article-body :deep(a) {
  color: var(--primary);
  text-decoration: underline;
}

.article-body :deep(blockquote) {
  border-left: 4px solid #cbd5e1;
  padding-left: 1.25rem;
  font-style: italic;
  color: #475569;
  margin: 1.5rem 0;
}

/* Author chip in meta */
.post-author-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: #374151;
}

.author-avatar-sm {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  object-fit: cover;
  border: 1.5px solid #fde8d8;
  flex-shrink: 0;
}

/* Author Bio Card */
.author-bio-card {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  background: linear-gradient(135deg, #fff7f0 0%, #fff0f3 100%);
  border: 1px solid #fde8d8;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 36px;
}

.author-avatar-lg {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(230, 59, 111, 0.18);
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(var(--primary-rgb, 239,68,68), 0.22);
}

.author-bio-info {
  flex: 1;
  min-width: 0;
}

.author-bio-top {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.author-bio-name {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
}

.author-bio-role {
  background: #fff0f3;
  color: var(--primary);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.author-bio-desc {
  font-size: 0.88rem;
  color: #64748b;
  line-height: 1.6;
  margin: 0;
}

/* Footer / Share */
.article-footer {
  border-top: 1px solid #e2e8f0;
  padding-top: 24px;
  margin-bottom: 24px;
}

.share-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.share-label {
  font-weight: 700;
  color: #475569;
  font-size: 0.95rem;
}

.share-buttons {
  display: flex;
  gap: 10px;
}

.btn-share {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  transition: all 0.2s ease;
}

.btn-share.facebook {
  color: #1877f2;
  border-color: #e8f4fd;
  background: #f0f8ff;
}

.btn-share.facebook:hover {
  background: #1877f2;
  color: #fff;
  border-color: #1877f2;
}

.btn-share.link:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: #fff0f3;
}

/* Sidebar Widget */
.sidebar-col {
  position: sticky;
  top: 94px;
  min-width: 0;
}

.sidebar-widget {
  width: 100%;
  background: var(--card-bg);
  border: 1px solid #f1f5f9;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01);
}

.widget-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid #fff0f3;
}

.widget-empty {
  color: #94a3b8;
  font-size: 0.88rem;
  text-align: center;
  padding: 20px 0;
}

.widget-posts {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.widget-post-item {
  display: flex;
  gap: 12px;
  align-items: center;
}

.widget-post-img {
  width: 70px;
  height: 70px;
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
}

.widget-post-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.widget-post-item:hover .widget-post-img img {
  transform: scale(1.06);
}

.widget-post-info {
  flex-grow: 1;
  min-width: 0;
}

.widget-post-date {
  font-size: 0.75rem;
  color: #94a3b8;
  display: block;
  margin-bottom: 4px;
}

.widget-post-title {
  font-size: 0.88rem;
  font-weight: 700;
  line-height: 1.4;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.widget-post-title a {
  color: #1e293b;
  text-decoration: none;
  transition: color 0.2s ease;
}

.widget-post-title a:hover {
  color: var(--primary);
}

/* Loading */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100px 0;
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
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@media (max-width: 991px) {
  .detail-layout {
    grid-template-columns: 1fr;
    gap: 40px;
  }
  .sidebar-col {
    position: static;
  }
}

@media (max-width: 768px) {
  .page-content {
    padding: 16px 16px 40px;
  }
  .article-title {
    font-size: 1.6rem;
  }
  .article-summary {
    font-size: 0.95rem;
    padding-left: 14px;
  }
  .featured-info {
    padding: 24px;
  }
}
</style>
