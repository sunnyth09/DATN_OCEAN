 <script setup>
import { ref } from 'vue';
import axios  from "axios";
import Swal from 'sweetalert2';

const email = ref('');

const SubmitContact = async () => {
  if(email.value === ""){
    Swal.fire('Lưu ý', 'Vui lòng nhập email!', 'warning');
    return;
  }

  try {
    const reponse = await axios.post("http://localhost:8000/api/SubmitContact", {
      email: email.value
    }); 
    if (reponse.data.status == "success"){
     Swal.fire('Thành công', reponse.data.message, 'success');
     email.value = "";
    }
  } catch (error) {
        Swal.fire('Lỗi', "Lỗi gửi email: " + error, 'error');
        console.error('Lỗi đăng ký:', error);
    };
};

</script>    

<template>
    <footer class="site-footer">
      <div class="footer-container">
        <div class="footer-grid">
          <div class="footer-brand">
            <h2 class="brand-name">OCEAN</h2>
            <p class="brand-desc">Phân phối thời trang cao cấp với thiết kế tinh xảo và tính ứng dụng cao nhất cho cuộc sống hiện đại.</p>
          </div>
          <div class="footer-col">
            <h3 class="col-title">Danh Mục</h3>
            <ul class="col-links">
              <li><a href="#">Sản Phẩm Mới</a></li>
              <li><a href="#">Áo Khoác Nam</a></li>
              <li><a href="#">Váy Đầm Nữ</a></li>
              <li><a href="#">Giày Phụ Kiện</a></li>
            </ul>
          </div>
          <div class="footer-col">
            <h3 class="col-title">Hỗ Trợ</h3>
            <ul class="col-links">
              <li><a href="#">Chính Sách Giao Hàng</a></li>
              <li><a href="#">Đổi Trả Khách Hàng</a></li>
              <li><a href="#">Câu Hỏi Thường Gặp</a></li>
              <li><a href="#">Liên Hệ</a></li>
            </ul>
          </div>
          <div class="footer-col">
            <h3 class="col-title">Nhận Bản Tin</h3>
            <p class="newsletter-desc">Đăng ký để nhận ưu đãi hấp dẫn ngay lập tức.</p>
            <form @submit.prevent="SubmitContact" class="newsletter-form">
              <input v-model="email" type="email" placeholder="Email của bạn..." class="newsletter-input">
              <button type="submit" class="newsletter-btn">→</button>
            </form>
          </div>
        </div>
        <div class="footer-bottom">
          <p>© 2026 Ocean Fashion. All rights reserved.</p>
          <div class="footer-legal">
            <a href="#">Điều Khoản Phục Vụ</a>
            <a href="#">Bảo Mật Quyền Riêng Tư</a>
          </div>
        </div>
      </div>
    </footer>
</template>

<style scoped>
.site-footer {
  background: #111827;
  padding: 64px 0 32px;
  color: #fff;
  border-top: 1px solid #e5e7eb;
}
.footer-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 16px;
}
.footer-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 48px;
  margin-bottom: 48px;
}
@media (min-width: 768px) {
  .footer-grid { grid-template-columns: repeat(4, 1fr); }
}
.brand-name {
  font-size: 1.5rem;
  font-family: Georgia, serif;
  font-weight: 700;
  letter-spacing: 0.15em;
  margin-bottom: 24px;
}
.brand-desc {
  color: #9ca3af;
  font-size: 0.875rem;
  font-weight: 300;
  line-height: 1.6;
  margin-bottom: 24px;
}
.col-title {
  font-size: 0.875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 20px;
}
.col-links {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.col-links a {
  color: #9ca3af;
  font-size: 0.875rem;
  font-weight: 300;
  text-decoration: none;
  transition: color 0.2s;
}
.col-links a:hover { color: #fff; }
.newsletter-desc {
  color: #9ca3af;
  font-size: 0.875rem;
  font-weight: 300;
  margin-bottom: 16px;
}
.newsletter-form {
  display: flex;
  border-bottom: 1px solid #4b5563;
  padding-bottom: 4px;
  transition: border-color 0.2s;
}
.newsletter-form:focus-within { border-color: #fff; }
.newsletter-input {
  background: transparent;
  border: none;
  outline: none;
  font-size: 0.875rem;
  width: 100%;
  font-weight: 300;
  color: #fff;
}
.newsletter-input::placeholder { color: #6b7280; }
.newsletter-btn {
  background: none;
  border: none;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  transition: color 0.2s;
}
.newsletter-btn:hover { color: #d1d5db; }
.footer-bottom {
  border-top: 1px solid #1f2937;
  padding-top: 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #6b7280;
  font-size: 0.75rem;
  font-weight: 300;
  gap: 16px;
}
@media (min-width: 768px) {
  .footer-bottom { flex-direction: row; justify-content: space-between; }
}
.footer-legal {
  display: flex;
  gap: 24px;
}
.footer-legal a {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.2s;
}
.footer-legal a:hover { color: #fff; }
</style>