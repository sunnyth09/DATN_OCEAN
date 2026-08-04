<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <h1>Tuyển Dụng</h1>
        <p class="hero-sub">Cùng Ocean Store xây dựng tương lai ngành thời trang Việt Nam</p>
      </div>
    </section>

    <section class="page-content container">
      <div class="content-block">
        <h2>Tại sao chọn Ocean Store?</h2>
        <div class="card-grid">
          <div class="info-card">
            <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L15 22l-4-9-9-4z"/></svg></div>
            <h3>Phát triển nhanh</h3>
            <p>Cơ hội thăng tiến rõ ràng, đào tạo liên tục và môi trường học hỏi không ngừng.</p>
          </div>
          <div class="info-card">
            <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
            <h3>Đãi ngộ hấp dẫn</h3>
            <p>Lương thưởng cạnh tranh, thưởng KPI, bảo hiểm đầy đủ, và nhiều phúc lợi khác.</p>
          </div>
          <div class="info-card">
            <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            <h3>Văn hóa trẻ</h3>
            <p>Đội ngũ năng động, sáng tạo, cởi mở. Team building định kỳ, du lịch hàng năm.</p>
          </div>
        </div>
      </div>

      <div class="content-block">
        <h2>Vị trí đang tuyển</h2>

        <div class="job-card">
          <div class="job-header">
            <div>
              <h3>Frontend Developer (Vue.js)</h3>
              <div class="job-tags"><span class="tag">Full-time</span><span class="tag location">Buôn Ma Thuột</span></div>
            </div>
            <span class="salary">15 - 25 triệu</span>
          </div>
          <p>Phát triển giao diện người dùng cho hệ thống e-commerce sử dụng Vue 3, Vite, Bootstrap.</p>
          <ul><li>Tối thiểu 1 năm kinh nghiệm với Vue.js</li><li>Hiểu biết về RESTful API, responsive design</li></ul>
        </div>

        <div class="job-card">
          <div class="job-header">
            <div>
              <h3>Backend Developer (Laravel)</h3>
              <div class="job-tags"><span class="tag">Full-time</span><span class="tag location">Buôn Ma Thuột</span></div>
            </div>
            <span class="salary">18 - 30 triệu</span>
          </div>
          <p>Xây dựng và duy trì API cho hệ thống Ocean Store trên nền tảng Laravel.</p>
          <ul><li>Thành thạo PHP, Laravel, MySQL</li><li>Kinh nghiệm với Docker, CI/CD là điểm cộng</li></ul>
        </div>

        <div class="job-card">
          <div class="job-header">
            <div>
              <h3>Chuyên viên Marketing</h3>
              <div class="job-tags"><span class="tag">Full-time</span><span class="tag location">Buôn Ma Thuột</span></div>
            </div>
            <span class="salary">10 - 18 triệu</span>
          </div>
          <p>Lên chiến lược marketing online, quản lý social media, chạy quảng cáo Facebook/Google Ads.</p>
          <ul><li>Kinh nghiệm digital marketing tối thiểu 1 năm</li><li>Am hiểu các nền tảng quảng cáo</li></ul>
        </div>

        <div class="job-card">
          <div class="job-header">
            <div>
              <h3>Nhân viên CSKH</h3>
              <div class="job-tags"><span class="tag">Full-time / Part-time</span><span class="tag location">Buôn Ma Thuột</span></div>
            </div>
            <span class="salary">8 - 12 triệu</span>
          </div>
          <p>Hỗ trợ khách hàng qua chat, điện thoại. Xử lý khiếu nại, đổi trả sản phẩm.</p>
          <ul><li>Giao tiếp tốt, kiên nhẫn, nhiệt tình</li><li>Không yêu cầu kinh nghiệm</li></ul>
        </div>
      </div>

      <div class="content-block">
        <h2>Cách ứng tuyển</h2>
        <p>Gửi CV của bạn về email: <strong>hr@oceanstore.vn</strong></p>
        <p>Tiêu đề email: <strong>[Vị trí ứng tuyển] - Họ tên</strong></p>
        <p>Chúng tôi sẽ phản hồi trong vòng 3 ngày làm việc.</p>
      </div>
    </section>
  </div>
</template>

<script setup>
</script>

<style scoped>
.static-page {
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  padding-top: 24px;
}
.container { max-width: 900px; margin: 0 auto; padding: 0 24px; }
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
.hero-sub { max-width: 700px; margin: 0 auto; color: rgba(255,255,255,0.9); font-size: 1.05rem; line-height: 1.7; }

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
  margin-top: 5px;
  z-index: 1;
  line-height: 1.6;
  text-align: center;
}
.page-content { padding: 48px 24px 64px; }
.content-block { margin-bottom: 40px; }
.content-block h2 { font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #FFF0F3; }
.content-block p { font-size: 0.95rem; color: #4b5563; line-height: 1.8; margin-bottom: 12px; }
.card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.info-card { background: #F8F9FA; border: 1px solid #FFE0E8; border-radius: 12px; padding: 24px; text-align: center; }
.card-icon { width: 48px; height: 48px; border-radius: 12px; background: #FFF0F3; color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
.info-card h3 { font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.info-card p { font-size: 0.85rem; color: #6b7280; line-height: 1.6; margin: 0; }
.job-card { background: var(--card-bg); border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 16px; transition: box-shadow 0.2s; }
.job-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.job-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.job-header h3 { font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0 0 8px; }
.job-tags { display: flex; gap: 8px; }
.tag { font-size: 0.72rem; font-weight: 600; padding: 4px 10px; border-radius: 20px; background: #FFF0F3; color: var(--primary); }
.tag.location { background: #f0fdf4; color: #16a34a; }
.salary { font-size: 0.9rem; font-weight: 700; color: var(--primary); white-space: nowrap; }
.job-card p { font-size: 0.9rem; color: #6b7280; line-height: 1.6; margin-bottom: 8px; }
.job-card ul { padding-left: 20px; margin: 0; }
.job-card li { font-size: 0.85rem; color: #6b7280; line-height: 1.6; }
@media (max-width: 768px) { .static-page { padding-top: 16px; } .page-hero { padding: 0 16px; } .page-hero .container { padding: 40px 18px; border-radius: 18px; } .page-content { padding: 36px 24px 56px; } .card-grid { grid-template-columns: 1fr;  } .job-header { flex-direction: column; } .salary { margin-top: 8px; } }
</style>
