<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <h1>Chính Sách Bảo Mật</h1>
        <p class="hero-sub">Cam kết bảo vệ quyền riêng tư và dữ liệu cá nhân của bạn</p>
      </div>
    </section>
    <section class="page-content container">
      <div class="content-block">
        <h2>1. Thông tin chúng tôi thu thập</h2>
        <p>Khi bạn sử dụng Ocean Store, chúng tôi có thể thu thập các loại thông tin sau:</p>
        <ul class="styled-list">
          <li><strong>Thông tin cá nhân:</strong> Họ tên, email, số điện thoại, địa chỉ giao hàng</li>
          <li><strong>Thông tin thanh toán:</strong> Được xử lý bởi cổng thanh toán bên thứ ba, Ocean Store không lưu trữ</li>
          <li><strong>Thông tin kỹ thuật:</strong> IP, trình duyệt, thiết bị, thời gian truy cập</li>
          <li><strong>Cookie:</strong> Nhằm cải thiện trải nghiệm người dùng</li>
        </ul>
      </div>
      <div class="content-block">
        <h2>2. Mục đích sử dụng</h2>
        <ul class="styled-list">
          <li>Xử lý đơn hàng và giao hàng</li>
          <li>Liên hệ hỗ trợ khách hàng</li>
          <li>Gửi thông tin khuyến mãi (nếu bạn đồng ý)</li>
          <li>Cải thiện sản phẩm và dịch vụ</li>
          <li>Ngăn chặn gian lận và bảo vệ an ninh hệ thống</li>
        </ul>
      </div>
      <div class="content-block">
        <h2>3. Chia sẻ thông tin</h2>
        <p>Chúng tôi <strong>không bán</strong> thông tin cá nhân của bạn. Thông tin chỉ được chia sẻ với:</p>
        <ul class="styled-list"><li>Đối tác vận chuyển (để giao hàng)</li><li>Cổng thanh toán (để xử lý giao dịch)</li><li>Cơ quan chức năng (khi có yêu cầu pháp lý)</li></ul>
      </div>
      <div class="content-block">
        <h2>4. Bảo mật dữ liệu</h2>
        <p>Ocean Store áp dụng các biện pháp bảo mật tiêu chuẩn ngành, bao gồm mã hóa SSL/TLS, xác thực hai yếu tố, và kiểm soát truy cập nghiêm ngặt.</p>
      </div>
      <div class="content-block">
        <h2>5. Quyền của bạn</h2>
        <p>Bạn có quyền:</p>
        <ul class="styled-list"><li>Truy cập và xem thông tin cá nhân đã cung cấp</li><li>Yêu cầu chỉnh sửa hoặc xóa thông tin</li><li>Từ chối nhận email marketing</li><li>Yêu cầu xuất dữ liệu cá nhân</li></ul>
      </div>
      <div class="content-block">
        <h2>6. Liên hệ</h2>
        <p>Mọi thắc mắc về chính sách bảo mật, xin liên hệ:</p>
        <p><strong>Email:</strong> privacy@oceanstore.vn | <strong>Hotline:</strong> 1900-OCEAN (1900 6232)</p>
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
<<<<<<< HEAD
  background: linear-gradient(135deg, #e63b6f, #a0204e);
  color: #fff;
  border-radius: 16px;
  padding: 32px;
  margin: 24px auto 28px;
  max-width: 900px;
  width: calc(100% - 48px);
  position: relative;
  overflow: hidden;
  text-align: left;
}
.page-hero::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
}
.page-hero h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 8px; position: relative; z-index: 1; }
.hero-sub { opacity: 0.85; font-size: 0.95rem; max-width: 500px; margin: 0; position: relative; z-index: 1; line-height: 1.6; }
=======
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
>>>>>>> origin/Dev
.page-content { padding: 48px 24px 64px; }
.content-block { margin-bottom: 32px; }
.content-block h2 { font-size: 1.15rem; font-weight: 700; color: #2D3436; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #FFF0F3; }
.content-block p { font-size: 0.95rem; color: #4b5563; line-height: 1.8; margin-bottom: 10px; }
.styled-list { padding-left: 24px; margin: 8px 0; }
.styled-list li { font-size: 0.95rem; color: #4b5563; line-height: 1.8; margin-bottom: 4px; }

@media (max-width: 768px) {
  .static-page { padding-top: 16px; }
  .page-hero { padding: 0 16px; }
  .page-hero .container { padding: 40px 18px; border-radius: 18px; }
  .page-content { padding: 36px 24px 56px; }
}
</style>
