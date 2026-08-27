<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import DOMPurify from 'dompurify';
import { getStorageUrl } from '@/utils/url';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { showToast } = useToast();

const post = ref(null);
const relatedPosts = ref([]);
const isLoading = ref(true);

const comments = ref([]);
const commentsTotal = ref(0);
const commentsLoading = ref(false);
const newComment = ref('');
const isSubmitting = ref(false);
const commentsPage = ref(1);
const commentsLastPage = ref(1);

const parseMarkdownOrHtml = (input) => {
  if (!input) return '';
  let text = String(input).trim();
  if (!text) return '';

  // Kiểm tra xem đã có các thẻ HTML block chưa (p, h1-h6, ul, ol, li, div...)
  const hasHtmlTags = /<\/?(p|h[1-6]|ul|ol|li|blockquote|div|table|section|article)\b/i.test(text);

  // Nếu chưa có thẻ HTML block mà có ký tự Markdown (#, *, -, 1.)
  if (!hasHtmlTags && (/(^|\n)#{1,6}\s/m.test(text) || /(^|\n)[\*\-]\s/m.test(text) || /(^|\n)\d+\.\s/m.test(text))) {
    let lines = text.split(/\r?\n/);
    let html = [];
    let inList = false;
    let listType = '';

    const closeList = () => {
      if (inList) {
        html.push(listType === 'ul' ? '</ul>' : '</ol>');
        inList = false;
        listType = '';
      }
    };

    for (let i = 0; i < lines.length; i++) {
      let line = lines[i].trim();

      if (!line) {
        closeList();
        continue;
      }

      // Tiêu đề #, ##, ###, ####
      let headerMatch = line.match(/^(#{1,6})\s+(.*)/);
      if (headerMatch) {
        closeList();
        let level = headerMatch[1].length;
        html.push(`<h${level}>${headerMatch[2]}</h${level}>`);
        continue;
      }

      // Danh sách không thứ tự * hoặc -
      let ulMatch = line.match(/^[\*\-]\s+(.*)/);
      if (ulMatch) {
        if (!inList || listType !== 'ul') {
          closeList();
          html.push('<ul>');
          inList = true;
          listType = 'ul';
        }
        html.push(`<li>${ulMatch[1]}</li>`);
        continue;
      }

      // Danh sách có thứ tự 1. 2.
      let olMatch = line.match(/^\d+\.\s+(.*)/);
      if (olMatch) {
        if (!inList || listType !== 'ol') {
          closeList();
          html.push('<ol>');
          inList = true;
          listType = 'ol';
        }
        html.push(`<li>${olMatch[1]}</li>`);
        continue;
      }

      // Đoạn văn thông thường
      closeList();
      html.push(`<p>${line}</p>`);
    }

    closeList();
    text = html.join('');

    // Inline formatting: Link Markdown [text](url)
    text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    // Inline formatting: Image Markdown ![alt](url)
    text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" />');
    // Inline formatting: Bold **bold**
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Inline formatting: Italic *italic*
    text = text.replace(/(^|[^\*])\*(?!\*)(.*?)\*/g, '$1<em>$2</em>');
  }

  return text;
};

const sanitizedContent = computed(() => {
  let content = post.value?.content || '';
  if (content) {
    // Chuyển đổi Markdown sang HTML nếu nội dung là Markdown thô
    content = parseMarkdownOrHtml(content);

    // Thay thế đường dẫn ảnh nếu dùng storage
    content = content.replace(/src=["']([^"']+)["']/gi, (match, src) => {
      return `src="${getStorageUrl(src)}"`;
    });

    // Loại bỏ các thẻ <p> rỗng (chứa khoảng trắng, <br>, &nbsp;) - Quill hay tự sinh khoảng trống thừa
    content = content.replace(/<p>(\s|<br\s*\/?>|&nbsp;)*<\/p>/gi, '');

    // Loại bỏ <br> đơn lẻ nằm giữa các block (ngay trước hoặc sau heading/ul/ol)
    content = content.replace(/(<\/h[1-6]>)\s*<br\s*\/?>/gi, '$1');
    content = content.replace(/<br\s*\/?>\s*(<h[1-6])/gi, '$1');
    content = content.replace(/(<\/ul>|<\/ol>|<\/li>)\s*<br\s*\/?>/gi, '$1');
    content = content.replace(/<br\s*\/?>\s*(<ul|<ol)/gi, '$1');

    // Thu gọn nhiều <br> liên tiếp thành 1
    content = content.replace(/(<br\s*\/?>\s*){2,}/gi, '<br/>');

    // Xóa khoảng trắng thừa giữa các block tags
    content = content.replace(/>\s{2,}</g, '> <');
  }

  return DOMPurify.sanitize(content, {
    USE_PROFILES: { html: true },
    ADD_TAGS: ['iframe', 'video', 'audio', 'source', 'figure', 'figcaption'],
    ADD_ATTR: ['allow', 'allowfullscreen', 'frameborder', 'scrolling', 'target', 'style', 'class', 'controls', 'autoplay'],
  });
});

const fetchPostDetail = async (idOrSlug) => {
  try {
    isLoading.value = true;
    const res = await api.get(`/posts/${idOrSlug}`);
    post.value = res.data;
    if (post.value) {
      fetchRelatedPosts(post.value.post_category_id, post.value.post_id);
      fetchComments(post.value.post_id, 1);
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

const fetchComments = async (postId, page = 1) => {
  try {
    if (page === 1) {
      commentsLoading.value = true;
    }
    const res = await api.get(`/posts/${postId}/comments`, { params: { page } });
    if (res.data && res.data.data) {
      const commentData = res.data.data;
      if (page === 1) {
        comments.value = commentData.data || [];
      } else {
        comments.value = [...comments.value, ...(commentData.data || [])];
      }
      commentsPage.value = commentData.current_page || 1;
      commentsLastPage.value = commentData.last_page || 1;
      commentsTotal.value = commentData.total || 0;
    }
  } catch (e) {
    console.error('Lỗi tải bình luận:', e);
  } finally {
    commentsLoading.value = false;
  }
};

const loadMoreComments = () => {
  if (commentsPage.value < commentsLastPage.value) {
    fetchComments(post.value.post_id, commentsPage.value + 1);
  }
};

const submitComment = async () => {
  if (!newComment.value.trim()) return;

  if (!authStore.token || !authStore.user) {
    showToast('Bạn phải đăng nhập mới có thể bình luận.', 'danger');
    router.push({ name: 'login', query: { redirect: route.fullPath } });
    return;
  }

  try {
    isSubmitting.value = true;
    const res = await api.post(`/posts/${post.value.post_id}/comments`, {
      content: newComment.value.trim(),
    });

    if (res.data && res.data.status === 'success') {
      newComment.value = '';
      if (res.data.data) {
        comments.value.unshift(res.data.data);
        commentsTotal.value += 1;
      } else {
        fetchComments(post.value.post_id, 1);
      }
      showToast('Đăng bình luận thành công!', 'success');
    }
  } catch (e) {
    console.error('Lỗi đăng bình luận:', e);
    const msg = e.response?.data?.message || 'Có lỗi xảy ra khi gửi bình luận.';
    showToast(msg, 'danger');
  } finally {
    isSubmitting.value = false;
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
  showToast('Đã sao chép đường dẫn bài viết!', 'success');
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

const getUserName = (user) => {
  if (!user) return 'Thành viên';
  return user.full_name || user.name || 'Thành viên';
};

const getUserFallbackAvatar = (user) => {
  const name = encodeURIComponent(getUserName(user));
  return `https://ui-avatars.com/api/?name=${name}&background=e63b6f&color=fff&size=96&bold=true`;
};

const getUserAvatarUrl = (user) => {
  if (!user?.avatar_url) return getUserFallbackAvatar(user);
  return getStorageUrl(user.avatar_url);
};
</script>

<template>
  <div class="static-page">
    <section class="page-content container">
      <!-- Loading State -->
      <div v-if="isLoading" class="post-detail-skeleton">
        <div class="skeleton-pulse" style="height:30px; width:40%; margin-bottom:20px; border-radius:4px;"></div>
        <div class="skeleton-pulse" style="height:50px; width:80%; margin-bottom:30px; border-radius:8px;"></div>
        <div class="skeleton-pulse" style="height:400px; width:100%; margin-bottom:40px; border-radius:12px;"></div>
        <div class="skeleton-pulse" style="height:20px; width:100%; margin-bottom:12px; border-radius:4px;" v-for="i in 5" :key="i"></div>
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
          <div class="article-body" v-html="sanitizedContent"></div>

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

          <!-- Comments Section -->
          <section class="comments-section">
            <h3 class="comments-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Bình luận ({{ commentsTotal }})
            </h3>

            <!-- Comment Input Form -->
            <div class="comment-form">
              <div v-if="authStore.token && authStore.user" class="form-logged-in">
                <div class="user-meta">
                  <img
                    :src="getUserAvatarUrl(authStore.user)"
                    :alt="getUserName(authStore.user)"
                    class="comment-avatar"
                    @error="e => e.target.src = getUserFallbackAvatar(authStore.user)"
                  />
                  <span class="user-name">{{ getUserName(authStore.user) }}</span>
                </div>
                <div class="input-wrapper">
                  <textarea
                    v-model="newComment"
                    placeholder="Chia sẻ ý kiến của bạn về bài viết này..."
                    rows="3"
                    maxlength="1000"
                    :disabled="isSubmitting"
                  ></textarea>
                  <div class="form-actions">
                    <span class="char-count">{{ newComment.length }}/1000</span>
                    <button
                      @click="submitComment"
                      :disabled="isSubmitting || !newComment.trim()"
                      class="btn-submit-comment"
                    >
                      <span v-if="isSubmitting" class="mini-spinner"></span>
                      {{ isSubmitting ? 'Đang gửi...' : 'Gửi bình luận' }}
                    </button>
                  </div>
                </div>
              </div>
              <div v-else class="form-guest">
                <p>Bạn cần <router-link :to="{ name: 'login', query: { redirect: route.fullPath } }" class="login-link">Đăng nhập</router-link> để tham gia thảo luận.</p>
              </div>
            </div>

            <!-- Comments List -->
            <div v-if="commentsLoading && comments.length === 0" class="comments-loading">
              <div class="spinner-sm"></div>
              <p>Đang tải bình luận...</p>
            </div>
            <div v-else-if="comments.length === 0" class="no-comments">
              <p>Chưa có bình luận nào cho bài viết này. Hãy là người đầu tiên chia sẻ cảm nghĩ!</p>
            </div>
            <div v-else class="comments-list">
              <div v-for="comment in comments" :key="comment.comment_id" class="comment-item">
                <img
                  :src="getUserAvatarUrl(comment.user)"
                  :alt="getUserName(comment.user)"
                  class="comment-item-avatar"
                  @error="e => e.target.src = getUserFallbackAvatar(comment.user)"
                />
                <div class="comment-item-content">
                  <div class="comment-item-header">
                    <span class="comment-author-name">{{ getUserName(comment.user) }}</span>
                    <span class="comment-date">{{ formatDate(comment.created_at) }}</span>
                  </div>
                  <p class="comment-text">{{ comment.content }}</p>
                </div>
              </div>
            </div>

            <!-- Load More Comments Button -->
            <div v-if="commentsPage < commentsLastPage" class="load-more-comments-wrap">
              <button @click="loadMoreComments" class="btn-load-more-comments">
                Xem thêm bình luận
              </button>
            </div>
          </section>
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

/* Rich Text Content (Quill & HTML Render) */
.article-body {
  font-size: 1.05rem;
  line-height: 1.8;
  color: #334155;
  margin-bottom: 48px;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

/* Ẩn các thẻ p rỗng hoặc chỉ chứa <br> / &nbsp; do Quill tự sinh */
.article-body :deep(p:empty),
.article-body :deep(p:blank) {
  display: none;
  margin: 0;
  height: 0;
}

/* Quill thêm class .ql-ui cho các element nội bộ - ẩn đi */
.article-body :deep(.ql-ui) {
  display: none;
}

.article-body :deep(p) {
  margin-top: 0;
  margin-bottom: 0.875rem;
  line-height: 1.8;
  color: #334155;
}

/* Heading styles - margin-top giảm so với trước để bớt khoảng trống */
.article-body :deep(h1),
.article-body :deep(h2),
.article-body :deep(h3),
.article-body :deep(h4),
.article-body :deep(h5),
.article-body :deep(h6) {
  color: #0f172a;
  font-weight: 700;
  line-height: 1.35;
  /* Heading đầu tiên không có margin-top */
}

.article-body :deep(h1) {
  font-size: 1.75rem;
  font-weight: 800;
  margin-top: 1.5rem;
  margin-bottom: 0.625rem;
  line-height: 1.3;
}

.article-body :deep(h2) {
  font-size: 1.4rem;
  margin-top: 1.375rem;
  margin-bottom: 0.5rem;
}

.article-body :deep(h3) {
  font-size: 1.2rem;
  margin-top: 1.25rem;
  margin-bottom: 0.5rem;
}

.article-body :deep(h4) {
  font-size: 1.1rem;
  margin-top: 1rem;
  margin-bottom: 0.375rem;
}

.article-body :deep(h5),
.article-body :deep(h6) {
  font-size: 1rem;
  margin-top: 0.875rem;
  margin-bottom: 0.375rem;
}

/* Heading đầu tiên trong bài: không có khoảng trên */
.article-body :deep(h1:first-child),
.article-body :deep(h2:first-child),
.article-body :deep(h3:first-child),
.article-body :deep(h4:first-child),
.article-body :deep(h5:first-child),
.article-body :deep(h6:first-child) {
  margin-top: 0;
}

/* Paragraph ngay sau heading: không thêm khoảng trên vì heading đã có margin-bottom */
.article-body :deep(h1 + p),
.article-body :deep(h2 + p),
.article-body :deep(h3 + p),
.article-body :deep(h4 + p),
.article-body :deep(h5 + p),
.article-body :deep(h6 + p) {
  margin-top: 0;
}

.article-body :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 12px;
  margin: 1.25rem auto;
  display: block;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
  object-fit: cover;
}

.article-body :deep(figure) {
  margin: 1.5rem 0;
  text-align: center;
}

.article-body :deep(figcaption) {
  font-size: 0.88rem;
  color: #64748b;
  margin-top: 0.5rem;
  font-style: italic;
}

.article-body :deep(ul),
.article-body :deep(ol) {
  margin-top: 0.25rem;
  margin-bottom: 0.875rem;
  padding-left: 1.75rem;
}

/* List ngay sau heading: không thêm margin trên */
.article-body :deep(h1 + ul),
.article-body :deep(h2 + ul),
.article-body :deep(h3 + ul),
.article-body :deep(h1 + ol),
.article-body :deep(h2 + ol),
.article-body :deep(h3 + ol) {
  margin-top: 0;
}

.article-body :deep(li) {
  margin-bottom: 0.25rem;
  line-height: 1.7;
}

/* Nested lists */
.article-body :deep(li > ul),
.article-body :deep(li > ol) {
  margin-top: 0.25rem;
  margin-bottom: 0.25rem;
}

.article-body :deep(a) {
  color: var(--primary);
  text-decoration: underline;
  text-underline-offset: 3px;
  transition: color 0.2s ease;
}

.article-body :deep(a:hover) {
  color: #d92f66;
}

.article-body :deep(blockquote) {
  border-left: 4px solid var(--primary);
  background: #f8fafc;
  padding: 1rem 1.25rem;
  border-radius: 0 8px 8px 0;
  font-style: italic;
  color: #475569;
  margin: 1.5rem 0;
}

.article-body :deep(blockquote p) {
  margin-bottom: 0;
}

.article-body :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
  font-size: 0.95rem;
}

.article-body :deep(th),
.article-body :deep(td) {
  border: 1px solid #e2e8f0;
  padding: 10px 14px;
  text-align: left;
}

.article-body :deep(th) {
  background-color: #f1f5f9;
  font-weight: 700;
  color: #0f172a;
}

.article-body :deep(tr:nth-child(even)) {
  background-color: #f8fafc;
}

.article-body :deep(iframe),
.article-body :deep(video) {
  max-width: 100%;
  width: 100%;
  border-radius: 12px;
  margin: 1.5rem 0;
  aspect-ratio: 16 / 9;
}

.article-body :deep(hr) {
  border: none;
  border-top: 1px solid #e2e8f0;
  margin: 2rem 0;
}

.article-body :deep(code) {
  background-color: #f1f5f9;
  color: #e63b6f;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.9em;
  font-family: monospace;
}

.article-body :deep(pre) {
  background-color: #0f172a;
  color: #f8fafc;
  padding: 1rem 1.25rem;
  border-radius: 8px;
  overflow-x: auto;
  margin: 1.5rem 0;
}

.article-body :deep(pre code) {
  background: transparent;
  color: inherit;
  padding: 0;
}

/* Quill text alignment & indentation support */
.article-body :deep(.ql-align-center) {
  text-align: center;
}

.article-body :deep(.ql-align-right) {
  text-align: right;
}

.article-body :deep(.ql-align-justify) {
  text-align: justify;
}

.article-body :deep(.ql-indent-1) {
  padding-left: 2rem;
}

.article-body :deep(.ql-indent-2) {
  padding-left: 4rem;
}

.article-body :deep(.ql-indent-3) {
  padding-left: 6rem;
}

.article-body :deep(.ql-size-small) {
  font-size: 0.85em;
}

.article-body :deep(.ql-size-large) {
  font-size: 1.3em;
}

.article-body :deep(.ql-size-huge) {
  font-size: 1.8em;
}

/* Override Quill editor built-in styles nếu còn sót */
.article-body :deep(.ql-editor) {
  padding: 0;
}

/* Xóa khoảng trống cuối bài viết (phần tử cuối không cần margin-bottom) */
.article-body :deep(*:last-child) {
  margin-bottom: 0;
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

/* Comments Section */
.comments-section {
  margin-top: 48px;
  border-top: 1px solid #e2e8f0;
  padding-top: 32px;
}

.comments-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.comments-title .title-icon {
  color: var(--primary);
}

.comment-form {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 32px;
}

.form-logged-in {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.user-meta {
  display: flex;
  align-items: center;
  gap: 10px;
}

.comment-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  border: 1.5px solid #fde8d8;
}

.user-name {
  font-weight: 700;
  font-size: 0.95rem;
  color: #1e293b;
}

.input-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.input-wrapper textarea {
  width: 100%;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  padding: 12px;
  font-size: 0.95rem;
  font-family: inherit;
  outline: none;
  resize: vertical;
  background: #fff;
  transition: border-color 0.2s ease;
}

.input-wrapper textarea:focus {
  border-color: var(--primary);
}

.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.char-count {
  font-size: 0.8rem;
  color: #94a3b8;
}

.btn-submit-comment {
  background: var(--primary);
  color: #fff;
  font-weight: 700;
  padding: 8px 20px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.btn-submit-comment:hover:not(:disabled) {
  background: #d92f66;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
}

.btn-submit-comment:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.form-guest {
  text-align: center;
  padding: 12px 0;
  color: #64748b;
  font-size: 0.95rem;
}

.login-link {
  color: var(--primary);
  font-weight: 700;
  text-decoration: underline;
}

.comments-loading, .no-comments {
  text-align: center;
  padding: 32px 0;
  color: #64748b;
}

.spinner-sm {
  width: 24px;
  height: 24px;
  border: 2.5px solid #f3f3f3;
  border-top: 2.5px solid var(--primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 12px;
}

.comments-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.comment-item {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.comment-item-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #fde8d8;
  flex-shrink: 0;
}

.comment-item-content {
  flex: 1;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 12px;
  padding: 16px;
}

.comment-item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  flex-wrap: wrap;
  gap: 8px;
}

.comment-author-name {
  font-weight: 700;
  font-size: 0.92rem;
  color: #0f172a;
}

.comment-date {
  font-size: 0.78rem;
  color: #94a3b8;
}

.comment-text {
  font-size: 0.95rem;
  line-height: 1.5;
  color: #334155;
  margin: 0;
  word-break: break-word;
}

.load-more-comments-wrap {
  display: flex;
  justify-content: center;
  margin-top: 32px;
}

.btn-load-more-comments {
  background: #fff;
  border: 1.5px solid #e2e8f0;
  color: #475569;
  font-weight: 700;
  font-size: 0.88rem;
  padding: 8px 24px;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-load-more-comments:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: #fff0f3;
}

.mini-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #fff;
  border-top: 2px solid transparent;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

/* ===== Modern Skeleton Loading Styles ===== */
.post-detail-skeleton {
  width: 100%;
  max-width: 800px;
  margin: 0 auto;
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
  top: 0; right: 0; bottom: 0; left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0,
    rgba(255, 255, 255, 0.4) 30%,
    rgba(255, 255, 255, 0.75) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
