<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <div class="hero-pill">ĐIỀU KHOẢN DỊCH VỤ</div>
        <h1>Điều Khoản Sử Dụng</h1>
        <p class="hero-sub">Quy định và quyền lợi của khách hàng khi trải nghiệm mua sắm tại Ocean Sport</p>
      </div>
    </section>
    <section class="page-content container">
      <div class="content-block">
        <h2>1. Giới thiệu</h2>
        <p>Chào mừng bạn đến với Ocean Store. Khi truy cập và sử dụng website oceanstore.vn, bạn đồng ý tuân thủ các điều khoản và điều kiện được nêu dưới đây.</p>
      </div>
      <div class="content-block">
        <h2>2. Tài khoản người dùng</h2>
        <p>Bạn chịu trách nhiệm bảo mật thông tin tài khoản, bao gồm mật khẩu. Mọi hoạt động xảy ra dưới tài khoản của bạn là trách nhiệm của bạn.</p>
        <p>Ocean Store có quyền tạm khóa hoặc xóa tài khoản nếu phát hiện hành vi vi phạm điều khoản sử dụng.</p>
      </div>
      <div class="content-block">
        <h2>3. Đặt hàng & Thanh toán</h2>
        <p>Khi đặt hàng trên Ocean Store, bạn cam kết cung cấp thông tin chính xác và đầy đủ. Chúng tôi có quyền từ chối hoặc hủy đơn hàng trong các trường hợp:</p>
        <ul class="styled-list"><li>Sản phẩm hết hàng hoặc lỗi giá</li><li>Thông tin đơn hàng không chính xác</li><li>Nghi ngờ giao dịch gian lận</li></ul>
      </div>
      <div class="content-block">
        <h2>4. Giá cả & Khuyến mãi</h2>
        <p>Giá sản phẩm có thể thay đổi mà không cần thông báo trước. Các chương trình khuyến mãi có thời hạn và điều kiện riêng, được nêu rõ tại thời điểm áp dụng.</p>
      </div>
      <div class="content-block">
        <h2>5. Vận chuyển</h2>
        <p>Ocean Store cam kết giao hàng đúng thời gian quy định. Thời gian giao hàng dự kiến từ 2–5 ngày làm việc tùy khu vực. Phí vận chuyển được tính dựa trên địa chỉ giao hàng và trọng lượng đơn hàng.</p>
      </div>
      <div class="content-block">
        <h2>6. Sở hữu trí tuệ</h2>
        <p>Toàn bộ nội dung trên website — bao gồm hình ảnh, logo, văn bản, thiết kế — thuộc quyền sở hữu của Ocean Store. Nghiêm cấm sao chép, sử dụng lại mà không có sự cho phép bằng văn bản.</p>
      </div>
      <div class="content-block">
        <h2>7. Giới hạn trách nhiệm</h2>
        <p>Ocean Store không chịu trách nhiệm về thiệt hại gián tiếp phát sinh từ việc sử dụng website, bao gồm nhưng không giới hạn: mất dữ liệu, gián đoạn kinh doanh, hoặc mất lợi nhuận.</p>
      </div>
      <div class="content-block">
        <h2>8. Thay đổi điều khoản</h2>
        <p>Ocean Store có quyền cập nhật điều khoản dịch vụ bất kỳ lúc nào. Các thay đổi sẽ có hiệu lực ngay khi được đăng tải trên website.</p>
      </div>
      <div class="content-block">
        <h2>9. Liên hệ</h2>
        <p>Nếu có bất kỳ câu hỏi nào về Điều khoản dịch vụ, vui lòng liên hệ:</p>
        <p><strong>Email:</strong> legal@oceanstore.vn | <strong>Hotline:</strong> 1900-OCEAN (1900 6232)</p>
      </div>
    </section>
  </div>
</template>
<script setup></script>
<style scoped>
.static-page {
  font-family: var(--font-inter, 'Inter', sans-serif);
  padding-top: 24px;
}
.container { max-width: 900px; margin: 0 auto; padding: 0 24px; }
.page-hero {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
  color: #fff;
  text-align: left;
}

.page-hero .container {
  max-width: 100%;
  padding: 36px 36px;
  border-radius: 16px;
  margin-bottom: 24px;
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.18), transparent 28%),
    linear-gradient(135deg, var(--primary) 0%, #d92f66 48%, #f05a8a 100%);
  box-shadow: 0 10px 28px rgba(230, 59, 111, 0.16);
  position: relative;
  overflow: hidden;
}

.hero-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  padding: 5px 14px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  margin-bottom: 10px;
  backdrop-filter: blur(8px);
}

.page-hero h1 {
  font-size: clamp(1.75rem, 2.6vw, 2.25rem);
  font-weight: 800;
  letter-spacing: -0.5px;
  margin: 0 0 8px;
  line-height: 1.2;
}

.hero-sub {
  max-width: 580px;
  margin: 0;
  color: rgba(255, 255, 255, 0.95);
  font-size: 0.95rem;
  line-height: 1.55;
}
.page-content { padding: 48px 24px 64px; }
.content-block { margin-bottom: 32px; }
.content-block h2 { font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #FFF0F3; }
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
