<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <h1>Chính Sách Đổi Trả</h1>
        <p class="hero-sub">Đổi trả dễ dàng — Bảo vệ quyền lợi khách hàng</p>
      </div>
    </section>
    <section class="page-content container">
      <div class="highlight-box">
        <strong>Cam kết:</strong> Ocean Store hỗ trợ đổi trả miễn phí trong <strong>30 ngày</strong> kể từ ngày nhận hàng.
      </div>

      <div class="content-block">
        <h2>Điều kiện đổi trả</h2>
        <div class="two-col">
          <div class="col accept">
            <h3><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Được đổi trả</h3>
            <ul><li>Sản phẩm lỗi do nhà sản xuất</li><li>Giao sai sản phẩm, sai size, sai màu</li><li>Sản phẩm chưa qua sử dụng, còn nguyên tem mác</li><li>Còn trong thời hạn 30 ngày</li></ul>
          </div>
          <div class="col reject">
            <h3><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>Không được đổi trả</h3>
            <ul><li>Sản phẩm đã qua sử dụng, giặt ủi</li><li>Không còn tem mác, nhãn sản phẩm</li><li>Sản phẩm sale/giảm giá trên 50%</li><li>Quá thời hạn 30 ngày</li></ul>
          </div>
        </div>
      </div>

      <div class="content-block">
        <h2>Quy trình đổi trả</h2>
        <div class="steps">
          <div class="step"><span class="step-num">1</span><div><h4>Liên hệ</h4><p>Gọi Hotline 1900-OCEAN hoặc gửi email support@oceanstore.vn kèm mã đơn hàng và lý do đổi trả.</p></div></div>
          <div class="step"><span class="step-num">2</span><div><h4>Xác nhận</h4><p>Nhân viên CSKH xác nhận yêu cầu và hướng dẫn gửi hàng trong vòng 24 giờ.</p></div></div>
          <div class="step"><span class="step-num">3</span><div><h4>Gửi hàng</h4><p>Đóng gói sản phẩm cẩn thận và gửi về kho Ocean Store. Phí ship do Ocean Store chịu.</p></div></div>
          <div class="step"><span class="step-num">4</span><div><h4>Hoàn tất</h4><p>Sau khi nhận và kiểm tra, chúng tôi gửi sản phẩm mới hoặc hoàn tiền trong 3-5 ngày.</p></div></div>
        </div>
      </div>

      <div class="content-block">
        <h2>Hoàn tiền</h2>
        <p>Nếu chọn hoàn tiền thay vì đổi sản phẩm, số tiền sẽ được chuyển lại qua phương thức thanh toán ban đầu trong vòng <strong>3-5 ngày làm việc</strong>.</p>
      </div>
    </section>
  </div>
</template>
<script setup></script>
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
    linear-gradient(135deg, #E63B6F 0%, #d92f66 48%, #f05a8a 100%);
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
<<<<<<< HEAD

=======
>>>>>>> tai/Notification
.page-content { padding: 48px 24px 64px; }
.highlight-box { background: #FFF0F3; border-left: 4px solid #E63B6F; padding: 16px 20px; border-radius: 8px; font-size: 0.95rem; color: #2D3436; line-height: 1.6; margin-bottom: 36px; }
.content-block { margin-bottom: 36px; }
.content-block h2 { font-size: 1.3rem; font-weight: 700; color: #2D3436; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #FFF0F3; }
.content-block p { font-size: 0.95rem; color: #4b5563; line-height: 1.8; margin-bottom: 10px; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.col { background: #F8F9FA; border-radius: 12px; padding: 24px; border: 1px solid #FFE0E8; }
.col h3 { font-size: 1rem; font-weight: 700; margin-bottom: 12px; }
.col.accept h3 { color: #16a34a; }
.col.reject h3 { color: #dc2626; }
.col ul { padding-left: 20px; margin: 0; }
.col li { font-size: 0.9rem; color: #4b5563; line-height: 1.7; margin-bottom: 6px; }
.steps { display: flex; flex-direction: column; gap: 16px; }
.step { display: flex; gap: 16px; align-items: flex-start; }
.step-num { width: 36px; height: 36px; border-radius: 50%; background: #E63B6F; color: #fff; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.step h4 { font-size: 1rem; font-weight: 700; color: #2D3436; margin: 0 0 4px; }
.step p { font-size: 0.9rem; color: #6b7280; line-height: 1.6; margin: 0; }
@media (max-width: 768px) { .static-page { padding-top: 16px; } .page-hero { padding: 0 16px; } .page-hero .container { padding: 40px 18px; border-radius: 18px; } .page-content { padding: 36px 24px 56px; } .two-col { grid-template-columns: 1fr;  } }
</style>
