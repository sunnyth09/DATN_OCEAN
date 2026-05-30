<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <h1>Câu Chuyện Thương Hiệu</h1>
        <p class="hero-sub">Hành trình xây dựng Ocean Store — nơi kết nối thời trang và đam mê</p>
      </div>
    </section>

    <section class="page-content container">
      <div class="content-block">
        <h2>Khởi nguồn</h2>
        <p>Ocean Store được thành lập vào năm 2024 tại thành phố Buôn Ma Thuột, Đắk Lắk, với mong muốn mang đến cho khách hàng những sản phẩm thời trang chất lượng cao với mức giá hợp lý nhất.</p>
        <p>Xuất phát từ niềm đam mê với thời trang và sự thấu hiểu nhu cầu của người tiêu dùng Việt, chúng tôi đã xây dựng Ocean Store trở thành điểm đến tin cậy cho mọi tín đồ thời trang.</p>
      </div>

      <div class="content-block">
        <h2>Tầm nhìn & Sứ mệnh</h2>
        <div class="card-grid">
          <div class="info-card">
            <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></div>
            <h3>Tầm nhìn</h3>
            <p>Trở thành nền tảng thương mại điện tử thời trang hàng đầu tại Việt Nam, nơi mọi người đều có thể tiếp cận xu hướng thời trang mới nhất.</p>
          </div>
          <div class="info-card">
            <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="18" x2="15" y2="18"/><line x1="10" y1="22" x2="14" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0018 8 6 6 0 006 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 018.91 14"/></svg></div>
            <h3>Sứ mệnh</h3>
            <p>Mang đến trải nghiệm mua sắm trực tuyến tốt nhất — từ sản phẩm chất lượng, dịch vụ tận tâm đến giao hàng nhanh chóng.</p>
          </div>
          <div class="info-card">
            <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
            <h3>Giá trị cốt lõi</h3>
            <p>Chất lượng — Uy tín — Tận tâm — Đổi mới. Đó là bốn giá trị cốt lõi định hình mọi hoạt động của Ocean Store.</p>
          </div>
        </div>
      </div>

      <div class="content-block">
        <h2>Con số ấn tượng</h2>
        <div class="stats-grid">
          <div class="stat-item"><span class="stat-number">50,000+</span><span class="stat-label">Khách hàng tin tưởng</span></div>
          <div class="stat-item"><span class="stat-number">10,000+</span><span class="stat-label">Sản phẩm đa dạng</span></div>
          <div class="stat-item"><span class="stat-number">100+</span><span class="stat-label">Thương hiệu đối tác</span></div>
          <div class="stat-item"><span class="stat-number">99%</span><span class="stat-label">Khách hàng hài lòng</span></div>
        </div>
      </div>

      <div class="content-block">
        <h2>Cam kết của chúng tôi</h2>
        <ul class="check-list">
          <li>100% sản phẩm chính hãng, nguồn gốc rõ ràng</li>
          <li>Đổi trả miễn phí trong vòng 30 ngày</li>
          <li>Giao hàng nhanh chóng trên toàn quốc</li>
          <li>Hỗ trợ khách hàng 24/7</li>
          <li>Bảo mật thông tin tuyệt đối</li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script setup>
</script>

<style scoped>
.static-page { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
.container { max-width: 900px; margin: 0 auto; padding: 0 24px; }
.page-hero { background: #E63B6F; color: #fff; padding: 56px 24px; text-align: center; }
.page-hero h1 { font-size: 2rem; font-weight: 800; margin: 0 0 12px; }
.hero-sub { font-size: 1rem; color: rgba(255,255,255,0.85); margin: 0; max-width: 600px; margin: 0 auto; line-height: 1.6; }
.page-content { padding: 48px 24px 64px; }
.content-block { margin-bottom: 40px; }
.content-block h2 { font-size: 1.3rem; font-weight: 700; color: #2D3436; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #FFF0F3; }
.content-block p { font-size: 0.95rem; color: #4b5563; line-height: 1.8; margin-bottom: 12px; }
.card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.info-card { background: #F8F9FA; border: 1px solid #FFE0E8; border-radius: 12px; padding: 24px; text-align: center; }
.card-icon { width: 48px; height: 48px; border-radius: 12px; background: #FFF0F3; color: #E63B6F; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
.info-card h3 { font-size: 1rem; font-weight: 700; color: #2D3436; margin-bottom: 8px; }
.info-card p { font-size: 0.85rem; color: #6b7280; line-height: 1.6; margin: 0; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.stat-item { background: #FFF0F3; border-radius: 12px; padding: 24px 16px; text-align: center; }
.stat-number { display: block; font-size: 1.5rem; font-weight: 800; color: #1a56db; margin-bottom: 6px; }
.stat-label { font-size: 0.82rem; color: #6b7280; }
.check-list { list-style: none; padding: 0; margin: 0; }
.check-list li { padding: 10px 0 10px 28px; position: relative; font-size: 0.95rem; color: #4b5563; border-bottom: 1px solid #f3f4f6; }
.check-list li::before { content: '✓'; position: absolute; left: 0; color: #16a34a; font-weight: 700; }
@media (max-width: 768px) { .card-grid, .stats-grid { grid-template-columns: 1fr; } }
</style>
