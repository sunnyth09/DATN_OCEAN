<template>
  <ClientHeader/>
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
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg></div>
          <h3>Hotline</h3>
          <p class="contact-value">1900-OCEAN (1900 6232)</p>
          <p class="contact-note">Thứ 2 - CN: 8:00 - 21:00</p>
        </div>
        <div class="contact-card">
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
          <h3>Email</h3>
          <p class="contact-value">support@oceanstore.vn</p>
          <p class="contact-note">Phản hồi trong 24 giờ</p>
        </div>
        <div class="contact-card">
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
          <h3>Showroom</h3>
          <p class="contact-value">134 Nguyễn Thị Định</p>
          <p class="contact-note">P. Buôn Ma Thuột, Tỉnh Đắk Lắk</p>
        </div>
        <div class="contact-card">
          <div class="contact-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></div>
          <h3>Live Chat</h3>
          <p class="contact-value">Chat trực tuyến</p>
          <p class="contact-note">Phản hồi tức thì 24/7</p>
        </div>
      </div>

      <div class="content-block">
        <h2>Gửi yêu cầu hỗ trợ</h2>
        <form @submit.prevent="submitContact" class="contact-form" novalidate>
          <div v-if="successMsg" class="alert-success">{{ successMsg }}</div>
          <div v-if="errorMsg" class="alert-error">{{ errorMsg }}</div>
          <div class="form-row-2">
            <div class="form-group">
              <label>Họ và tên</label>
              <input v-model="form.name" type="text" placeholder="Nguyễn Văn A" />
              <span v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</span>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input v-model="form.email" type="email" placeholder="your@email.com" />
              <span v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</span>
            </div>
          </div>
          <div class="form-group">
            <label>Chủ đề</label>
            <select v-model="form.subject">
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
            <textarea v-model="form.message" rows="5" placeholder="Mô tả chi tiết vấn đề bạn gặp phải..."></textarea>
            <span v-if="fieldErrors.message" class="field-error">{{ fieldErrors.message }}</span>
          </div>
          <button type="submit" class="btn-primary" :disabled="isSubmitting">
            {{ isSubmitting ? 'Đang gửi...' : 'Gửi yêu cầu' }}
          </button>
        </form>
      </div>
    </section>
  </div>
  <Footer_client />
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import ClientHeader from '@/components/ClientHeader.vue';
import Footer_client from '@/Pages/Includes/Layouts/Footer_client.vue';

const form = reactive({ name: '', email: '', subject: '', message: '' });
const isSubmitting = ref(false);
const successMsg = ref('');
const errorMsg = ref('');
const fieldErrors = ref({});

const submitContact = async () => {
  successMsg.value = '';
  errorMsg.value = '';
  fieldErrors.value = {};

  // Client-side validation
  if (!form.name.trim()) { fieldErrors.value.name = 'Vui lòng nhập họ tên.'; }
  if (!form.email.trim()) { fieldErrors.value.email = 'Vui lòng nhập email.'; }
  if (!form.subject) { fieldErrors.value.subject = 'Vui lòng chọn chủ đề.'; }
  if (!form.message.trim()) { fieldErrors.value.message = 'Vui lòng nhập nội dung.'; }
  if (Object.keys(fieldErrors.value).length > 0) return;

  isSubmitting.value = true;
  try {
    const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8383/api';
    const res = await axios.post(`${baseUrl}/SubmitContact`, { ...form });
    successMsg.value = res.data.message;
    // Reset form
    form.name = ''; form.email = ''; form.subject = ''; form.message = '';
  } catch (err) {
    if (err.response?.status === 422 && err.response.data.errors) {
      fieldErrors.value = {};
      for (const [key, msgs] of Object.entries(err.response.data.errors)) {
        fieldErrors.value[key] = msgs[0];
      }
    } else {
      errorMsg.value = err.response?.data?.message || 'Đã xảy ra lỗi, vui lòng thử lại.';
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<style scoped>
.static-page { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
.container { max-width: 900px; margin: 0 auto; padding: 0 24px; }
.page-hero { background: linear-gradient(135deg, #1a56db 0%, #E63B6F 100%); color: #fff; padding: 56px 24px; text-align: center; }
.page-hero h1 { font-size: 2rem; font-weight: 800; margin: 0 0 12px; }
.hero-sub { font-size: 1rem; color: rgba(255,255,255,0.85); margin: 0; }
.page-content { padding: 48px 24px 64px; }
.contact-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 48px; }
.contact-card { background: #F8F9FA; border: 1px solid #FFE0E8; border-radius: 12px; padding: 24px; text-align: center; transition: box-shadow 0.2s; }
.contact-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.contact-icon { width: 48px; height: 48px; border-radius: 12px; background: #FFF0F3; color: #E63B6F; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
.contact-card h3 { font-size: 0.95rem; font-weight: 700; color: #2D3436; margin-bottom: 8px; }
.contact-value { font-size: 0.9rem; font-weight: 600; color: #1a56db; margin-bottom: 4px; }
.contact-note { font-size: 0.78rem; color: #9ca3af; margin: 0; }
.content-block { margin-bottom: 36px; }
.content-block h2 { font-size: 1.3rem; font-weight: 700; color: #2D3436; margin-bottom: 20px; padding-bottom: 8px; border-bottom: 2px solid #FFF0F3; }
.contact-form { display: flex; flex-direction: column; gap: 16px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #333; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #E9ECEF; border-radius: 10px; font-size: 0.9rem; font-family: inherit; color: #2D3436; background: #F8F9FA; outline: none; transition: border-color 0.2s; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #E63B6F; background: #fff; }
.form-group textarea { resize: vertical; }
.btn-primary { padding: 13px 32px; border: none; border-radius: 10px; background: #E63B6F; color: #fff; font-size: 0.95rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: background 0.2s; align-self: flex-start; }
.btn-primary:hover { background: #C4305D; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.alert-success { background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; font-weight: 500; }
.alert-error { background: #ffebee; border: 1px solid #ffcdd2; color: #c62828; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; font-weight: 500; }
.field-error { display: block; color: #dc2626; font-size: 0.8rem; margin-top: 4px; }
@media (max-width: 768px) { .contact-grid { grid-template-columns: 1fr 1fr; } .form-row-2 { grid-template-columns: 1fr; } }
</style>
