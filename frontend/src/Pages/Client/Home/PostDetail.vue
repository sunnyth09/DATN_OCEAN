<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { useAuthStore } from '@/stores/auth';
import AppIcon from '@/icons/AppIcon.vue';
import { getStorageUrl } from '@/utils/url';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const post = ref(null);
const loading = ref(true);
const comments = ref([]);
const commentPage = ref(1);
const commentTotalPages = ref(1);
const loadingComments = ref(false);

// Form data
const commentContent = ref('');
const submittingComment = ref(false);
const alertMessage = ref({ show: false, text: '', type: 'success' });

const slug = computed(() => route.params.id);

const showAlert = (text, type = 'success') => {
    alertMessage.value = { show: true, text, type };
    setTimeout(() => {
        alertMessage.value.show = false;
    }, 4500);
};

const fetchPost = async () => {
    loading.value = true;
    try {
        const res = await api.get(`/posts/${slug.value}`);
        post.value = res.data;
        fetchComments();
    } catch (err) {
        console.error('Error fetching post:', err);
        showAlert('Không tìm thấy bài viết hoặc bài viết đã bị ẩn.', 'error');
    } finally {
        loading.value = false;
    }
};

const fetchComments = async (page = 1) => {
    if (!post.value) return;
    loadingComments.value = true;
    try {
        const res = await api.get(`/posts/${post.value.post_id}/comments?page=${page}`);
        if (res.data.status === 'success') {
            comments.value = res.data.data.data || [];
            commentPage.value = res.data.data.current_page || 1;
            commentTotalPages.value = res.data.data.last_page || 1;
        }
    } catch (err) {
        console.error('Error fetching comments:', err);
    } finally {
        loadingComments.value = false;
    }
};

const submitComment = async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }

    if (!commentContent.value.trim()) {
        showAlert('Nội dung bình luận không được để trống.', 'error');
        return;
    }

    submittingComment.value = true;

    try {
        const payload = {
            content: commentContent.value,
        };

        const res = await api.post(`/posts/${post.value.post_id}/comments`, payload);
        if (res.data.status === 'success') {
            showAlert(res.data.message, 'success');
            commentContent.value = '';
            // Refresh comments to show the new comment
            fetchComments(1);
        }
    } catch (err) {
        console.error('Error posting comment:', err);
        const errMsg = err.response?.data?.message || err.message || 'Không thể đăng bình luận lúc này.';
        showAlert(errMsg, 'error');
    } finally {
        submittingComment.value = false;
    }
};

const getImageUrl = (path) => {
    if (!path || path === '0') return 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80';
    return getStorageUrl(path);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`;
};

onMounted(() => {
    fetchPost();
});

watch(slug, (newSlug, oldSlug) => {
    if (newSlug && newSlug !== oldSlug) {
        fetchPost();
    }
});
</script>

<template>
  <div class="blog-detail-container" v-if="post">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <router-link to="/">Trang chủ</router-link>
      <span class="sep">&gt;</span>
      <router-link to="/posts">Tin tức</router-link>
      <span class="sep">&gt;</span>
      <span class="current">{{ post.title }}</span>
    </nav>

    <div class="detail-layout">
      <!-- Main Content -->
      <main class="post-main">
        <article class="post-article">
          <header class="article-header">
            <span class="cat-badge" v-if="post.category">{{ post.category.name }}</span>
            <h1 class="article-title">{{ post.title }}</h1>
            
            <div class="article-meta">
              <span class="meta-item">
                <AppIcon name="calendar" size="14" />
                Đăng ngày: {{ formatDate(post.published_at || post.created_at) }}
              </span>
              <span class="meta-item">
                <AppIcon name="eye" size="14" />
                {{ post.view_count }} lượt xem
              </span>
            </div>
          </header>

          <div class="article-banner" v-if="post.banner_url || post.thumbnail_url">
            <img :src="getImageUrl(post.banner_url || post.thumbnail_url)" :alt="post.title" @error="e => e.target.src = 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80'" />
          </div>

          <div class="article-content" v-html="post.content"></div>
        </article>

        <!-- Comments Section -->
        <section class="comments-section" id="comments">
          <h2 class="section-title">
            <AppIcon name="message-square" size="20" />
            Bình luận bài viết
          </h2>

          <!-- Alert for Feedback -->
          <Transition name="alert">
            <div v-if="alertMessage.show" class="alert-box" :class="alertMessage.type">
              <AppIcon :name="alertMessage.type === 'success' ? 'check' : 'alert-circle'" size="16" />
              <span>{{ alertMessage.text }}</span>
            </div>
          </Transition>

          <!-- Comment Form -->
          <div class="comment-form-container">
            <div v-if="!authStore.isAuthenticated" class="login-prompt">
              <AppIcon name="lock" size="24" />
              <p>Bạn phải đăng nhập để viết bình luận.</p>
              <router-link :to="'/client/login?redirect=' + route.fullPath" class="login-btn">
                Đăng Nhập Ngay
              </router-link>
            </div>

            <form v-else @submit.prevent="submitComment" class="comment-form">


              <div class="form-header">
                <span class="comment-user-name">Bình luận dưới tên: <strong>{{ authStore.user?.full_name }}</strong></span>
              </div>

              <textarea 
                v-model="commentContent"
                placeholder="Chia sẻ ý kiến của bạn về bài viết này..."
                class="comment-textarea"
                rows="4"
                required
              ></textarea>

              <div class="form-footer">
                <div></div>
                <button type="submit" class="submit-btn" :disabled="submittingComment">
                  <span v-if="submittingComment" class="btn-spinner"></span>
                  Gửi bình luận
                </button>
              </div>
            </form>
          </div>

          <!-- Comments List -->
          <div class="comments-list">
            <div v-if="loadingComments && comments.length === 0" class="comments-loading">
              <div class="mini-spinner"></div>
              <span>Đang tải bình luận...</span>
            </div>

            <div v-else-if="comments.length === 0" class="no-comments">
              Chưa có bình luận nào cho bài viết này. Hãy là người đầu tiên chia sẻ cảm nghĩ!
            </div>

            <div v-else class="comments-cards">
              <div v-for="comment in comments" :key="comment.comment_id" class="comment-card">
                <img 
                  :src="comment.user?.avatar_url ? getImageUrl(comment.user.avatar_url) : 'https://placehold.co/48x48?text=U'" 
                  class="comment-avatar"
                  :alt="comment.user?.full_name"
                  @error="e => e.target.src = 'https://placehold.co/48x48?text=U'"
                />
                <div class="comment-body">
                  <div class="comment-head">
                    <span class="comment-author">{{ comment.user?.full_name || 'Khách hàng' }}</span>
                    <span class="comment-date">{{ formatDate(comment.created_at) }}</span>
                  </div>
                  <p class="comment-text">{{ comment.content }}</p>
                </div>
              </div>

              <!-- Pagination -->
              <div class="comments-pagination" v-if="commentTotalPages > 1">
                <button 
                  class="page-btn" 
                  :disabled="commentPage === 1"
                  @click="fetchComments(commentPage - 1)"
                >
                  &lt; Trước
                </button>
                <span class="page-info">Trang {{ commentPage }} / {{ commentTotalPages }}</span>
                <button 
                  class="page-btn" 
                  :disabled="commentPage === commentTotalPages"
                  @click="fetchComments(commentPage + 1)"
                >
                  Sau &gt;
                </button>
              </div>
            </div>
          </div>
        </section>
      </main>

      <!-- Sidebar -->
      <aside class="post-sidebar">
        <div class="sidebar-box info-box">
          <h3>Về Quyền Sport</h3>
          <p>Cửa hàng thể thao uy tín chuyên cung cấp các dòng vợt tennis, cầu lông, giày thể thao và dụng cụ chính hãng cao cấp.</p>
          <router-link to="/brand-story" class="sidebar-link">Đọc câu chuyện của chúng tôi &rarr;</router-link>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.blog-detail-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px 16px 60px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: #2d3436;
}

/* Breadcrumb */
.breadcrumb {
  font-size: 0.85rem;
  color: #636e72;
  margin-bottom: 24px;
}
.breadcrumb a {
  color: #636e72;
  text-decoration: none;
}
.breadcrumb a:hover {
  color: #E63B6F;
}
.breadcrumb .sep {
  margin: 0 8px;
  color: #b2bec3;
}
.breadcrumb .current {
  color: #2d3436;
  font-weight: 600;
}

/* Layout */
.detail-layout {
  display: grid;
  grid-template-columns: 3fr 1fr;
  gap: 40px;
}

/* Article */
.post-main {
  display: flex;
  flex-direction: column;
  gap: 40px;
}
.post-article {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 32px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}
.cat-badge {
  display: inline-block;
  background: rgba(230, 59, 111, 0.08);
  color: #E63B6F;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 12px;
}
.article-title {
  font-size: 2.2rem;
  font-weight: 800;
  line-height: 1.3;
  margin: 0 0 16px;
  color: #0f172a;
}
.article-meta {
  display: flex;
  gap: 20px;
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
}
.article-banner {
  aspect-ratio: 16/9;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 28px;
}
.article-banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.article-content {
  font-size: 1.05rem;
  line-height: 1.8;
  color: #334155;
}
.article-content :deep(p) {
  margin-bottom: 20px;
}
.article-content :deep(h2), .article-content :deep(h3) {
  color: #0f172a;
  margin: 32px 0 16px;
  font-weight: 700;
}
.article-content :deep(img) {
  max-width: 100%;
  border-radius: 8px;
  margin: 20px 0;
}

/* Sidebar */
.post-sidebar {
  display: flex;
  flex-direction: column;
  gap: 30px;
}
.sidebar-box {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 24px;
}
.sidebar-box h3 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 12px;
  padding-bottom: 8px;
  border-bottom: 2px solid #E63B6F;
  width: fit-content;
}
.sidebar-box p {
  font-size: 0.9rem;
  color: #64748b;
  line-height: 1.6;
  margin: 0 0 16px;
}
.sidebar-link {
  color: #E63B6F;
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 600;
}
.sidebar-link:hover {
  text-decoration: underline;
}

/* Comments Section */
.comments-section {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 32px;
}
.section-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 24px;
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Comment Form */
.comment-form-container {
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid #e2e8f0;
}
.login-prompt {
  text-align: center;
  padding: 28px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px dashed #cbd5e1;
  color: #64748b;
}
.login-prompt p {
  margin: 10px 0 16px;
  font-weight: 500;
}
.login-btn {
  display: inline-block;
  background: #E63B6F;
  color: white;
  font-weight: 700;
  padding: 10px 24px;
  border-radius: 8px;
  text-decoration: none;
  font-size: 0.9rem;
  transition: background 0.2s;
}
.login-btn:hover {
  background: #c22955;
}

.comment-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.hidden-hp {
  position: absolute;
  left: -9999px;
  width: 1px;
  height: 1px;
  overflow: hidden;
  opacity: 0;
}
.form-header {
  font-size: 0.9rem;
  color: #475569;
}
.comment-textarea {
  width: 100%;
  padding: 14px;
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.95rem;
  outline: none;
  resize: vertical;
  background: white;
  transition: border-color 0.2s;
}
.comment-textarea:focus {
  border-color: #E63B6F;
}
.form-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
}
.recaptcha-notice {
  font-size: 0.78rem;
  color: #94a3b8;
}
.submit-btn {
  background: #E63B6F;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 12px 24px;
  font-size: 0.92rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background 0.2s;
}
.submit-btn:hover {
  background: #c22955;
}
.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Comments List */
.comments-loading {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #64748b;
  font-size: 0.9rem;
}
.mini-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid #f1f5f9;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.no-comments {
  color: #64748b;
  font-size: 0.95rem;
  text-align: center;
  padding: 24px 0;
  line-height: 1.5;
}
.comments-cards {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.comment-card {
  display: flex;
  gap: 16px;
}
.comment-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}
.comment-body {
  flex: 1;
  background: #f8fafc;
  border-radius: 12px;
  padding: 16px;
  border: 1px solid #e2e8f0;
}
.comment-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.comment-author {
  font-weight: 700;
  font-size: 0.92rem;
  color: #1e293b;
}
.comment-date {
  font-size: 0.8rem;
  color: #94a3b8;
}
.comment-text {
  font-size: 0.95rem;
  color: #334155;
  line-height: 1.5;
  margin: 0;
  word-break: break-word;
}

/* Alert Box */
.alert-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-size: 0.9rem;
  font-weight: 500;
}
.alert-box.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}
.alert-box.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Pagination */
.comments-pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}
.page-btn {
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}
.page-btn:hover:not(:disabled) {
  border-color: #E63B6F;
  color: #E63B6F;
}
.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.page-info {
  font-size: 0.88rem;
  color: #64748b;
}

/* Transitions */
.alert-enter-active, .alert-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.alert-enter-from, .alert-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* Responsive */
@media (max-width: 1024px) {
  .detail-layout {
    grid-template-columns: 1fr;
  }
  .post-sidebar {
    display: none;
  }
}
@media (max-width: 640px) {
  .article-title {
    font-size: 1.7rem;
  }
  .post-article, .comments-section {
    padding: 20px;
  }
  .form-footer {
    flex-direction: column;
    align-items: stretch;
  }
  .submit-btn {
    justify-content: center;
  }
}
</style>
