<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <h1>Liên Hệ Hỗ Trợ</h1>
        <p class="hero-sub">Chúng tôi luôn sẵn sàng lắng nghe bạn</p>
      </div>
    </section>
    <section class="page-content container">
      <div class="contact-grid">
        <div class="contact-card">
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2">
              <path
                d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" />
            </svg></div>
          <h3>Hotline</h3>
          <p class="contact-value">1900-OCEAN (1900 6232)</p>
          <p class="contact-note">Thứ 2 - CN: 8:00 - 21:00</p>
        </div>
        <div class="contact-card">
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <polyline points="22,6 12,13 2,6" />
            </svg></div>
          <h3>Email</h3>
          <p class="contact-value">support@oceanstore.vn</p>
          <p class="contact-note">Phản hồi trong 24 giờ</p>
        </div>
        <div class="contact-card">
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
              <circle cx="12" cy="10" r="3" />
            </svg></div>
          <h3>Showroom</h3>
          <p class="contact-value">101 Y Ngông</p>
          <p class="contact-note">Phường Buôn Ma Thuột, Tỉnh Đắk Lắk</p>
        </div>
        <div class="contact-card">
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2">
              <path
                d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
            </svg></div>
          <h3>Live Chat</h3>
          <p class="contact-value">Chat trực tuyến</p>
          <p class="contact-note">Phản hồi tức thì 24/7</p>
        </div>
      </div>

      <div class="contact-layout">
        <div class="info-panel">
          <div class="mini-map">
            <iframe
              src="https://www.google.com/maps?q=101+Y+Ngông,+Buôn+Ma+Thuột,+Đắk+Lắk&output=embed"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              title="Mini map Ocean Sport"
            ></iframe>
          </div>
          <div class="location-meta">
            <h3>Showroom OCEAN SPORT</h3>
            <p>101 Y Ngông, Phường Buôn Ma Thuột, Tỉnh Đắk Lắk</p>
            <a
              href="https://www.google.com/maps?q=101+Y+Ngông,+Buôn+Ma+Thuột,+Đắk+Lắk"
              target="_blank"
              rel="noopener noreferrer"
            >
              Xem bản đồ lớn
            </a>
          </div>
        </div>
        <div class="form-panel">
          <h2>Gửi yêu cầu hỗ trợ</h2>
          <form @submit.prevent="submitContact" class="contact-form" novalidate>
          <div v-if="successMsg" class="alert-success">{{ successMsg }}</div>
          <div v-if="errorMsg" class="alert-error">{{ errorMsg }}</div>
          <div class="form-row-2">
            <div class="form-group">
              <label>Họ và tên</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="Nguyễn Văn A"
                :aria-invalid="Boolean(fieldErrors.name)"
                @input="clearFieldError('name')"
              />
              <span v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</span>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="your@email.com"
                :aria-invalid="Boolean(fieldErrors.email)"
                @input="clearFieldError('email')"
              />
              <span v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</span>
            </div>
          </div>
          <div class="form-group">
            <label>Chủ đề</label>
            <select
              v-model="form.subject"
              :aria-invalid="Boolean(fieldErrors.subject)"
              @change="clearFieldError('subject')"
            >
              <option value="">-- Chọn chủ đề --</option>
              <option>Hỏi về đơn hàng</option>
              <option>Đổi/Trả sản phẩm</option>
              <option>Khiếu nại chất lượng</option>
              <option>Hợp tác kinh doanh</option>
              <option>Khác</option>
            </select>
            <span v-if="fieldErrors.subject" class="field-error">{{ fieldErrors.subject }}</span>
          </div>
          <div class="form-group">
            <label>Nội dung</label>
            <textarea
              v-model="form.message"
              rows="5"
              placeholder="Mô tả chi tiết vấn đề bạn gặp phải..."
              :aria-invalid="Boolean(fieldErrors.message)"
              @input="clearFieldError('message')"
            ></textarea>
            <span v-if="fieldErrors.message" class="field-error">{{ fieldErrors.message }}</span>
          </div>

          <!-- CAPTCHA -->
          <div class="turnstile-container" style="margin-bottom: 16px;">
             <div class="captcha-box" v-show="!turnstileToken">
                <div class="turnstile-wrapper">
                  <div id="turnstile-contact"></div>
                </div>
             </div>
             <div class="captcha-box success" v-if="turnstileToken" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 10px 14px; display: inline-flex; align-items: center; gap: 10px;">
                <span class="icon text-success" style="color: #22c55e;">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <span class="captcha-text" style="color: #15803d; font-weight: 600; font-size: 0.85rem;">Xác thực thành công</span>
             </div>
          </div>

          <button type="submit" class="btn-primary" :disabled="isSubmitting || !turnstileToken">
            {{ isSubmitting ? 'Đang gửi...' : 'Gửi yêu cầu' }}
          </button>
        </form>
      </div>
    </div>
    </section>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, onBeforeUnmount } from 'vue';
import api from '@/axios';

const form = reactive({ name: '', email: '', subject: '', message: '' });
const isSubmitting = ref(false);
const successMsg = ref('');
const errorMsg = ref('');
const fieldErrors = reactive({});

const turnstileToken = ref('');
let turnstileWidgetId = null;

const loadTurnstile = () => {
  if (window.turnstile) {
    renderTurnstile();
    return;
  }
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad';
  script.async = true;
  window.onTurnstileLoad = () => renderTurnstile();
  document.head.appendChild(script);
};

const renderTurnstile = () => {
  const container = document.getElementById('turnstile-contact');
  if (!container || !window.turnstile) return;
  container.innerHTML = '';
  turnstileWidgetId = window.turnstile.render('#turnstile-contact', {
    sitekey: import.meta.env.VITE_TURNSTILE_SITE_KEY,
    callback: (token) => { turnstileToken.value = token; },
    'expired-callback': () => { turnstileToken.value = ''; },
    'error-callback': () => { turnstileToken.value = ''; },
    theme: 'light',
  });
};

onMounted(() => {
  loadTurnstile();
});

onBeforeUnmount(() => {
  if (turnstileWidgetId !== null && window.turnstile) {
    window.turnstile.remove(turnstileWidgetId);
  }
});

const clearFieldError = (field) => {
  delete fieldErrors[field];
};

const submitContact = async () => {
  successMsg.value = '';
  errorMsg.value = '';
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

  // Client-side validation
  if (!form.name.trim()) { fieldErrors.name = 'Vui lòng nhập họ tên.'; }
  if (!form.email.trim()) {
    fieldErrors.email = 'Vui lòng nhập email.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    fieldErrors.email = 'Email không hợp lệ.';
  }
  if (!form.subject) { fieldErrors.subject = 'Vui lòng chọn chủ đề.'; }
  if (!form.message.trim()) { fieldErrors.message = 'Vui lòng nhập nội dung.'; }
  if (Object.keys(fieldErrors).length > 0) return;

  isSubmitting.value = true;
  try {
    const res = await api.post('/SubmitContact', { ...form, turnstile_token: turnstileToken.value });
    successMsg.value = res.data.message;
    // Reset form
    form.name = '';
    form.email = '';
    form.subject = '';
    form.message = '';

    // Reset turnstile
    if (window.turnstile && turnstileWidgetId !== null) {
      window.turnstile.reset(turnstileWidgetId);
      turnstileToken.value = '';
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response.data.errors) {
      Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
      for (const [key, msgs] of Object.entries(err.response.data.errors)) {
        fieldErrors[key] = msgs[0];
      }
    } else {
      errorMsg.value = err.response?.data?.message || 'Đã xảy ra lỗi, vui lòng thử lại.';
    }

    if (window.turnstile && turnstileWidgetId !== null) {
      window.turnstile.reset(turnstileWidgetId);
      turnstileToken.value = '';
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<style scoped>
.static-page {
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  padding-top: 24px;
}

.container {
  max-width: 1120px;
  margin: 0 auto;
  padding: 0 24px;
}

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
  position: relative;
  display: inline-block;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 900;
  margin: 0 0 16px;
  padding-bottom: 14px;
}

.page-hero h1::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 0;
  width: 96px;
  height: 4px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.9);
  transform: translateX(-50%);
}

.hero-sub {
  max-width: 700px;
  margin: 0 auto;
  color: rgba(255, 255, 255, 0.9);
  font-size: 1.05rem;
  line-height: 1.7;
}
.page-hero h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 8px; position: relative; z-index: 1; }
.hero-sub { opacity: 0.85; font-size: 0.95rem; max-width: 500px; margin: 0; position: relative; z-index: 1; line-height: 1.6; }

.page-content {
  padding: 24px 24px 64px;
}

.contact-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 48px;
}

.contact-card {
  background: #f8f9fa;
  border: 1px solid #e9eef5;
  border-radius: 10px;
  padding: 18px 14px;
  text-align: center;
  transition: box-shadow 0.2s;
}

.contact-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}

.contact-icon {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: #fff0f3;
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 10px;
}

.contact-card h3 {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 6px;
}

.contact-value {
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}

.contact-note {
  font-size: 0.75rem;
  color: #9ca3af;
  margin: 0;
}

.contact-layout {
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  gap: 24px;
}

.info-panel {
  border-radius: 8px;
}

.mini-map {
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e9ecef;
  aspect-ratio: 16 / 10;
}

.mini-map iframe {
  width: 100%;
  height: 100%;
  border: 0;
}

.location-meta {
  margin-top: 12px;
}

.location-meta h3 {
  margin: 0 0 6px;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-main);
}

.location-meta p {
  margin: 0 0 8px;
  color: #6b7280;
  font-size: 0.82rem;
}

.location-meta a {
  font-size: 0.82rem;
  font-weight: 600;
  color: #dc2626;
  text-decoration: underline;
}

.form-panel {
  border-radius: 8px;
}

.form-panel h2 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 14px;
}

.contact-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #333;
  margin-bottom: 6px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1.5px solid #E9ECEF;
  border-radius: 10px;
  font-size: 0.9rem;
  font-family: inherit;
  color: var(--text-main);
  background: #F8F9FA;
  outline: none;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--primary);
  background: var(--card-bg);
}

.form-group textarea {
  resize: vertical;
}

.btn-primary {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s;
  align-self: flex-start;
}

.btn-primary:hover {
  background: #C4305D;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.alert-success {
  background: #e8f5e9;
  border: 1px solid #c8e6c9;
  color: #2e7d32;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 500;
}

.alert-error {
  background: #ffebee;
  border: 1px solid #ffcdd2;
  color: #c62828;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 500;
}

.field-error {
  display: block;
  color: #dc2626;
  font-size: 0.8rem;
  margin-top: 4px;
}

@media (max-width: 768px) {
  .static-page { padding-top: 16px; }
  .page-hero { padding: 0 16px; }
  .page-hero .container { padding: 40px 18px; border-radius: 18px; }
  .page-content { padding: 36px 24px 56px; }

  .contact-grid {
    grid-template-columns: 1fr 1fr;
  }

  .contact-layout {
    grid-template-columns: 1fr;
  }

  .form-row-2 {
    grid-template-columns: 1fr;
  }
}
</style>
