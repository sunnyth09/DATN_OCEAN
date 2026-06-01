<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <h1>Hướng Dẫn Mua Hàng</h1>
        <p class="hero-sub">Mua sắm dễ dàng chỉ với vài bước đơn giản</p>
      </div>
    </section>
    <section class="page-content container">
      <div class="steps-timeline">
        <div class="timeline-step">
          <div class="step-marker">1</div>
          <div class="step-content">
            <h3>Tìm kiếm sản phẩm</h3>
            <p>Sử dụng thanh tìm kiếm hoặc duyệt theo danh mục để tìm sản phẩm yêu thích. Bạn có thể lọc theo giá, thương hiệu, size, màu sắc.</p>
          </div>
        </div>
        <div class="timeline-step">
          <div class="step-marker">2</div>
          <div class="step-content">
            <h3>Chọn sản phẩm</h3>
            <p>Xem chi tiết sản phẩm, hình ảnh, mô tả, đánh giá từ khách hàng khác. Chọn size, màu sắc và số lượng phù hợp.</p>
          </div>
        </div>
        <div class="timeline-step">
          <div class="step-marker">3</div>
          <div class="step-content">
            <h3>Thêm vào giỏ hàng</h3>
            <p>Nhấn nút <strong>"Thêm vào giỏ"</strong> để lưu sản phẩm. Bạn có thể tiếp tục mua sắm hoặc tiến hành thanh toán.</p>
          </div>
        </div>
        <div class="timeline-step">
          <div class="step-marker">4</div>
          <div class="step-content">
            <h3>Kiểm tra giỏ hàng</h3>
            <p>Vào <strong>Giỏ hàng</strong> để xem lại các sản phẩm đã chọn, điều chỉnh số lượng hoặc xóa sản phẩm không cần.</p>
          </div>
        </div>
        <div class="timeline-step">
          <div class="step-marker">5</div>
          <div class="step-content">
            <h3>Điền thông tin giao hàng</h3>
            <p>Nhập họ tên, số điện thoại, địa chỉ giao hàng. Nếu đã có tài khoản, thông tin sẽ được tự động điền sẵn.</p>
          </div>
        </div>
        <div class="timeline-step">
          <div class="step-marker">6</div>
          <div class="step-content">
            <h3>Chọn phương thức thanh toán</h3>
            <p>Ocean Store hỗ trợ nhiều phương thức thanh toán:</p>
            <div class="payment-methods">
              <div class="method"><span class="method-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> Chuyển khoản ngân hàng</div>
              <div class="method"><span class="method-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span> Thanh toán khi nhận hàng (COD)</div>
              <div class="method"><span class="method-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span> Ví điện tử (MoMo, ZaloPay, VNPay)</div>
            </div>
          </div>
        </div>
        <div class="timeline-step">
          <div class="step-marker">7</div>
          <div class="step-content">
            <h3>Xác nhận đơn hàng</h3>
            <p>Kiểm tra lại toàn bộ thông tin và nhấn <strong>"Đặt hàng"</strong>. Bạn sẽ nhận email xác nhận đơn hàng ngay lập tức.</p>
          </div>
        </div>
        <div class="timeline-step last">
          <div class="step-marker done">✓</div>
          <div class="step-content">
            <h3>Nhận hàng & Đánh giá</h3>
            <p>Nhận hàng trong 2-5 ngày. Kiểm tra sản phẩm và đánh giá trải nghiệm mua sắm để giúp chúng tôi phục vụ tốt hơn!</p>
          </div>
        </div>
      </div>

      <div class="content-block">
        <h2>Lưu ý khi mua hàng</h2>
        <ul class="note-list">
          <li>Đăng nhập/đăng ký tài khoản để theo dõi đơn hàng và nhận ưu đãi</li>
          <li>Kiểm tra kỹ size chart trước khi đặt hàng</li>
          <li>Đơn hàng từ 500.000đ được miễn phí vận chuyển</li>
          <li>Mọi thắc mắc, liên hệ Hotline 1900-OCEAN</li>
        </ul>
      </div>
    </section>
  </div>
</template>
<script setup></script>
<style scoped>
.static-page { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
.container { max-width: 900px; margin: 0 auto; padding: 0 24px; }
.page-hero { background: #E63B6F; color: #fff; padding: 56px 24px; text-align: center; }
.page-hero h1 { font-size: 2rem; font-weight: 800; margin: 0 0 12px; }
.hero-sub { font-size: 1rem; color: rgba(255,255,255,0.85); margin: 0; }
.page-content { padding: 48px 24px 64px; }
.steps-timeline { position: relative; padding-left: 56px; margin-bottom: 48px; }
.timeline-step { position: relative; padding-bottom: 32px; }
.timeline-step:not(.last)::before { content: ''; position: absolute; left: -38px; top: 36px; bottom: 0; width: 2px; background: #E9ECEF; }
.step-marker { position: absolute; left: -50px; top: 0; width: 36px; height: 36px; border-radius: 50%; background: #E63B6F; color: #fff; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; z-index: 1; }
.step-marker.done { background: #16a34a; }
.step-content h3 { font-size: 1.05rem; font-weight: 700; color: #2D3436; margin: 0 0 8px; }
.step-content p { font-size: 0.9rem; color: #6b7280; line-height: 1.7; margin: 0; }
.payment-methods { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.method { display: flex; align-items: center; gap: 8px; font-size: 0.88rem; color: #4b5563; padding: 8px 12px; background: #F8F9FA; border-radius: 8px; }
.method-icon { font-size: 1.1rem; }
.content-block { margin-bottom: 36px; }
.content-block h2 { font-size: 1.3rem; font-weight: 700; color: #2D3436; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #FFF0F3; }
.note-list { padding-left: 24px; margin: 0; }
.note-list li { font-size: 0.95rem; color: #4b5563; line-height: 1.8; margin-bottom: 6px; }
</style>
