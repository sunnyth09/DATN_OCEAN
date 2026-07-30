<script setup>
import { ref } from 'vue';
import api from '../axios.js';
import Swal from 'sweetalert2';
import { getAppBaseUrl } from '@/utils/url';

const newsletterEmail = ref('');

const submitNewsletter = async () => {
  if (!newsletterEmail.value) return;
  try {
    await api.post('/submitcontactemail', { email: newsletterEmail.value });
    newsletterEmail.value = '';
    Swal.fire('Thành công', 'Đăng ký nhận tin thành công!', 'success');
  } catch (e) {
    Swal.fire('Lỗi', 'Có lỗi xảy ra!', 'error');
  }
};

const BASE_URL = getAppBaseUrl();
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-grid">
        <!-- Col 1: Brand + Newsletter -->
        <div class="footer-brand-col">
          <router-link to="/" class="footer-logo">
              <img :src="BASE_URL + '/storage/logo/OCEAN_SPORT_LOGO_v0.png?v=2'" alt="logo-ocean" width="70" height="auto">
          </router-link>
          <p class="footer-desc">Ocean Sport — Nơi hội tụ các thương hiệu uy tín, mang đến trải nghiệm mua sắm tuyệt vời cho bạn.</p>

          <div class="newsletter">
            <h4 class="newsletter-title">Đăng ký nhận tin</h4>
            <form @submit.prevent="submitNewsletter" class="newsletter-form">
              <input id="newsletter-email" name="newsletter_email" v-model="newsletterEmail" type="email" placeholder="Email của bạn..." class="newsletter-input" />
              <button type="submit" class="newsletter-btn">Gửi</button>
            </form>
          </div>
        </div>

        <!-- Col 2: Về Ocean Store -->
        <div class="footer-col">
          <h4 class="footer-heading">Về chúng tôi</h4>
          <ul class="footer-links">
            <li><router-link to="/brand-story">Câu chuyện thương hiệu</router-link></li>
            <li><router-link to="/careers">Tuyển dụng</router-link></li>
            <li><router-link to="/terms">Điều khoản dịch vụ</router-link></li>
            <li><router-link to="/privacy">Chính sách bảo mật</router-link></li>
          </ul>
        </div>

        <!-- Col 3: Hỗ Trợ Khách Hàng -->
        <div class="footer-col">
          <h4 class="footer-heading">Hỗ Trợ Khách Hàng</h4>
          <ul class="footer-links">
            <li><router-link to="/faq">Câu hỏi thường gặp (FAQ)</router-link></li>
            <li><router-link to="/return-policy">Chính sách đổi trả</router-link></li>
            <li><router-link to="/contact">Liên hệ hỗ trợ</router-link></li>
            <li><router-link to="/shopping-guide">Hướng dẫn mua hàng</router-link></li>
          </ul>
        </div>

        <!-- Col 4: Kết Nối -->
        <div class="footer-col">
          <h4 class="footer-heading">Kết Nối Với Chúng Tôi</h4>
          <ul class="footer-contact">
            <li>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              101 Y Ngông, Phường Buôn Ma Thuột, Tỉnh Đắk Lắk
            </li>
            <li>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
              1900-OSport (1900 6232)
            </li>
            <li>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              contact@oceansport.vn
            </li>
          </ul>
          <div class="social-icons">
            <a href="https://www.facebook.com/profile.php?id=100078846346372" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Facebook Ocean Sport">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <button type="button" class="social-icon" aria-label="Instagram Ocean Sport" disabled>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
            </button>
            <button type="button" class="social-icon" aria-label="YouTube Ocean Sport" disabled>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>© 2026 Ocean Sport. All rights reserved.</p>
      </div>
    </div>
  </footer>
</template>


<style scoped>
.site-footer {
  background: #f8fafc;
  border-top: 1px solid #e5e7eb;
  padding: 64px 0 0;
}

.footer-inner {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 24px;
}

.footer-grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr 1.2fr;
  gap: 48px;
}

.footer-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
}

.footer-logo-text {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--primary);
}

.footer-desc { font-size: 0.95rem; color: #64748b; line-height: 1.6; margin-bottom: 24px; }

.newsletter-title { font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-bottom: 10px; }

.newsletter-form {
  display: flex;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  overflow: hidden;
}

.newsletter-input { flex: 1; padding: 10px 14px; border: none; outline: none; font-size: 0.95rem; font-family: inherit; background: var(--card-bg); color: #333; }

.newsletter-input::placeholder { color: #9ca3af; }

.newsletter-btn { padding: 10px 20px; background: var(--primary); border: none; color: #fff; font-size: 0.95rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: all 0.2s ease; border-radius: 0 8px 8px 0; }

.newsletter-btn:hover { background: #d82f65; transform: scale(1.02); }

.footer-heading { font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 20px; font-style: normal; }

.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li { margin-bottom: 10px; }

.footer-links a { font-size: 0.95rem; color: #64748b; text-decoration: none; transition: all 0.2s; }

.footer-links a:hover { color: var(--primary); text-decoration: underline; text-underline-offset: 4px; }

.footer-contact {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-contact li { display: flex; align-items: flex-start; gap: 8px; font-size: 0.95rem; color: #64748b; margin-bottom: 12px; line-height: 1.5; }

.footer-contact li svg {
  flex-shrink: 0;
  margin-top: 2px;
  color: #9ca3af;
}

.social-icons {
  display: flex;
  gap: 12px;
  margin-top: 16px;
}

.social-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #d1d5db;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  background: transparent;
  padding: 0;
  text-decoration: none;
  transition: all 0.2s;
}

.social-icon:not(:disabled):hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.25);
}

.social-icon:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.footer-bottom {
  border-top: 1px solid #e5e7eb;
  margin-top: 40px;
  padding: 20px 0;
  text-align: center;
  font-size: 0.8rem;
  color: #9ca3af;
}

@media (max-width: 1024px) {
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 40px; }
}

@media (max-width: 768px) {
  .footer-grid { grid-template-columns: 1fr; gap: 32px; }
}
</style>
