<script setup>
import { useRoute } from 'vue-router';
import AppIcon from '@/icons/AppIcon.vue';
import FlashSaleBoard from '@/components/FlashSaleBoard.vue';

const route = useRoute();
const targetId = route.query.id ? parseInt(route.query.id) : null;
</script>

<template>
  <div class="flash-sale-page">

    <!-- ══ HERO ══ -->
    <section class="hero">
      <div class="hero-inner">
        <div class="hero-eyebrow">
          <span class="eyebrow-dot"></span>
          Chương trình đặc biệt
          <span class="eyebrow-dot"></span>
        </div>
        <h1 class="hero-title">
          <AppIcon name="zap" size="52" stroke-width="2.5" />
          Flash Sale
          <span class="hero-accent">Giá Sốc</span>
        </h1>
        <p class="hero-sub">Số lượng cực kỳ có hạn — Cơ hội săn deal không thể bỏ lỡ!</p>
      </div>
    </section>

    <!-- ══ BOARD ══ -->
    <section class="content">
      <div class="content-inner">

        <!-- Board component -->
        <div class="board-wrapper">
          <FlashSaleBoard :flash-sale-id="targetId ?? undefined" />
        </div>

        <!-- Quy tắc -->
        <div class="rules-card">
          <h3 class="rules-title">
            <AppIcon name="clipboard-list" size="16" stroke-width="2.3" />
            Điều kiện tham gia
          </h3>
          <ul class="rules-list">
            <li>
              <span class="rule-icon">
                <AppIcon name="lock" size="16" />
              </span>
              Phải <strong>đăng nhập</strong> để mua hàng
            </li>
            <li>
              <span class="rule-icon">
                <AppIcon name="cart" size="16" />
              </span>
              Tối đa <strong>1 sản phẩm / khách hàng</strong>
            </li>
            <li>
              <span class="rule-icon">
                <AppIcon name="truck" size="16" />
              </span>
              Flash Sale được <strong>miễn phí vận chuyển</strong>
            </li>
            <li>
              <span class="rule-icon">
                <AppIcon name="zap" size="16" />
              </span>
              Đơn hàng xử lý theo thứ tự — đặt sớm ưu tiên trước
            </li>
            <li>
              <span class="rule-icon">
                <AppIcon name="x" size="16" />
              </span>
              Không áp dụng thêm mã giảm giá khác
            </li>
          </ul>
        </div>

      </div>
    </section>

  </div>
</template>

<style scoped>
.flash-sale-page {
  min-height: 100vh;
  background: #F8F9FA;
}

/* ── HERO ── */
.hero {
  background: linear-gradient(135deg, #E63B6F 0%, #E63B6F 60%, #FF6B9D 100%);
  padding: 64px 20px 56px;
  text-align: center;
}

.hero-inner {
  max-width: 600px;
  margin: 0 auto;
}

.hero-eyebrow {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: rgba(255,255,255,0.75);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  margin-bottom: 16px;
}
.eyebrow-dot {
  width: 5px;
  height: 5px;
  background: rgba(255,255,255,0.5);
  border-radius: 50%;
}

.hero-title {
  color: #fff;
  font-size: clamp(36px, 8vw, 64px);
  font-weight: 900;
  line-height: 1.1;
  margin: 0 0 8px;
  letter-spacing: -1px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px;
}

.hero-title svg {
  flex-shrink: 0;
}

.hero-accent {
  display: block;
  color: #fff;
  background: rgba(255,255,255,0.2);
  border-radius: 8px;
  padding: 0 12px;
  display: inline-block;
  margin-top: 4px;
}

.hero-sub {
  color: rgba(255,255,255,0.75);
  font-size: 15px;
  margin: 12px 0 0;
}

/* ── CONTENT ── */
.content {
  padding: 32px 20px 60px;
}

.content-inner {
  max-width: 560px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Board wrapper nổi lên so với hero */
.board-wrapper {
  margin-top: -32px;
}

/* ── RULES CARD ── */
.rules-card {
  background: #fff;
  border: 1px solid #F1F3F5;
  border-radius: 14px;
  padding: 20px 22px;
}

.rules-title {
  color: #E63B6F;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin: 0 0 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rules-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rules-list li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: #636E72;
  line-height: 1.5;
}

.rule-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  line-height: 1.5;
  color: #E63B6F;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.rules-list li strong {
  color: #E63B6F;
  font-weight: 700;
}

@media (max-width: 480px) {
  .hero { padding: 48px 16px 44px; }
  .content { padding: 24px 12px 40px; }
}
</style>
