<template>
  <div class="static-page">
    <section class="page-hero">
      <div class="container">
        <h1>Câu Hỏi Thường Gặp</h1>
        <p class="hero-sub">Giải đáp nhanh mọi thắc mắc của bạn</p>
      </div>
    </section>
    <section class="page-content container">
      <div v-for="(item, i) in faqItems" :key="i" class="faq-item" :class="{ open: openIndex === i }">
        <button class="faq-question" @click="toggle(i)">
          <span>{{ item.q }}</span>
          <svg :class="{ rotated: openIndex === i }" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div v-show="openIndex === i" class="faq-answer">
          <p>{{ item.a }}</p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';
const openIndex = ref(0);
const toggle = (i) => { openIndex.value = openIndex.value === i ? -1 : i; };

const faqItems = [
  { q: 'Làm thế nào để đặt hàng trên Ocean Store?', a: 'Bạn chỉ cần tìm sản phẩm yêu thích, chọn size/màu, thêm vào giỏ hàng và tiến hành thanh toán. Bạn có thể thanh toán bằng chuyển khoản, COD hoặc ví điện tử.' },
  { q: 'Thời gian giao hàng là bao lâu?', a: 'Thời gian giao hàng từ 2-5 ngày làm việc tùy khu vực. Nội thành Buôn Ma Thuột giao trong 1-2 ngày. Các tỉnh thành khác từ 3-5 ngày.' },
  { q: 'Tôi có thể đổi/trả hàng không?', a: 'Có. Ocean Store hỗ trợ đổi trả miễn phí trong 30 ngày kể từ ngày nhận hàng. Sản phẩm phải còn nguyên tem mác, chưa qua sử dụng.' },
  { q: 'Phí vận chuyển là bao nhiêu?', a: 'Miễn phí vận chuyển cho đơn hàng từ 500.000đ. Đơn hàng dưới 500.000đ phí ship từ 15.000đ - 30.000đ tùy khu vực.' },
  { q: 'Sản phẩm trên Ocean Store có chính hãng không?', a: 'Tất cả sản phẩm trên Ocean Store đều 100% chính hãng, có tem chống hàng giả và giấy chứng nhận xuất xứ rõ ràng.' },
  { q: 'Tôi quên mật khẩu, phải làm sao?', a: 'Bạn vào trang Đăng nhập → Quên mật khẩu → Nhập email đã đăng ký → Nhận mã OTP qua email → Đặt mật khẩu mới.' },
  { q: 'Làm sao để liên hệ hỗ trợ?', a: 'Bạn có thể gọi Hotline 1900-OCEAN (1900 6232), gửi email support@oceanstore.vn.' },
  { q: 'Ocean Store có cửa hàng offline không?', a: 'Hiện tại Ocean Store có showroom tại 134 Nguyễn Thị Định, P. Buôn Ma Thuột, Tỉnh Đắk Lắk. Mở cửa từ 8h-21h hàng ngày.' },
];
</script>

<style scoped>
.static-page { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
.container { max-width: 900px; margin: 0 auto; padding: 0 24px; }
.page-hero {
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
.page-content { padding: 48px 24px 64px; }
.faq-item { border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 12px; overflow: hidden; transition: box-shadow 0.2s; }
.faq-item.open { box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-color: #c7d2fe; }
.faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; background: #fff; border: none; cursor: pointer; font-size: 0.95rem; font-weight: 600; color: #2D3436; text-align: left; font-family: inherit; transition: background 0.2s; }
.faq-question:hover { background: #F8F9FA; }
.faq-question svg { flex-shrink: 0; color: #9ca3af; transition: transform 0.3s; }
.faq-question svg.rotated { transform: rotate(180deg); color: #E63B6F; }
.faq-answer { padding: 0 20px 18px; }
.faq-answer p { font-size: 0.9rem; color: #6b7280; line-height: 1.7; margin: 0; }
</style>
