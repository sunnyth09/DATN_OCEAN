<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/icons/AppIcon.vue';
import FlashSaleBoard from '@/components/FlashSaleBoard.vue';
import api from '@/axios.js';

const route = useRoute();
const targetId = route.query.id ? parseInt(route.query.id) : null;
const items = ref([]);

onMounted(async () => {
    try {
        const { data } = await api.get('flash-sale');
        const list = data.data ?? [];
        if (targetId) {
            items.value = list.filter(item => item.id === targetId);
        } else {
            items.value = list;
        }
    } catch (e) {
        console.error('Lỗi tải danh sách flash sale', e);
    }
});
</script>

<template>
  <div class="flash-sale-page">

    <!-- ══ HERO ══ -->
    <section class="hero">
      <div class="hero-inner">
        <h1 class="hero-title">
          <AppIcon name="zap" size="28" stroke-width="2.5" />
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
        <div v-if="items.length > 0" class="boards-container">
          <div class="board-wrapper" v-for="item in items" :key="item.item_id">
            <FlashSaleBoard :item-id="item.item_id" />
          </div>
        </div>
        
        <div v-else class="text-center py-5" style="display: flex; justify-content: center; width: 100%; margin: 40px 0;">
           <h4 class="text-muted">Hiện tại không có chương trình Flash Sale nào.</h4>
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
.text-muted {
  color: #949a9e;
  text-align: center; 
}
.flash-sale-page {
  min-height: 100vh;
  background: var(--background, #fff);
}

/* ── HERO ── */
.hero {
  /* Banner thể thao */
  background: linear-gradient(135deg, rgba(230, 59, 111, 0.85), rgba(160, 32, 78, 0.9)), url('https://images.unsplash.com/photo-1517649763962-0c623066013b?q=80&w=2070&auto=format&fit=crop') center/cover no-repeat;
  color: #fff;
  border-radius: var(--radius-lg, 16px);
  padding: 40px 32px;
  margin: 32px auto 40px;
  max-width: 1140px;
  width: calc(100% - 40px);
  position: relative;
  overflow: hidden;
  text-align: left;
  box-shadow: var(--shadow-md);
}

.hero::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
}

.hero-inner {
  position: relative;
  z-index: 1;
}

.hero-title {
  color: #fff;
  font-size: 1.75rem;
  font-weight: 800;
  margin: 0 0 8px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
}

.hero-title svg {
  flex-shrink: 0;
}

.hero-accent {
  display: inline-flex;
  align-items: center;
  color: #fff;
  background: rgba(255,255,255,0.2);
  border-radius: 8px;
  padding: 2px 10px;
  font-size: 1rem;
}

.hero-sub {
  color: rgba(255,255,255,0.85);
  font-size: 0.95rem;
  max-width: 500px;
  margin: 0;
  line-height: 1.6;
}

/* ── CONTENT ── */
.content {
  padding: 32px 20px 60px;
}

.content-inner {
  max-width: 1140px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 32px;
}

/* Board wrapper nổi lên so với hero */
.boards-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 24px;
}

.board-wrapper {
  display: flex;
  justify-content: center;
  width: 100%;
}

/* ── RULES CARD ── */
.rules-card {
  background: var(--card-bg, #fff);
  border: 1px solid var(--border-subtle, #F1F3F5);
  border-radius: var(--radius-lg, 16px);
  padding: 32px 28px;
  max-width: 600px;
  margin: 0 auto;
  width: 100%;
  box-shadow: var(--shadow-sm);
}

.rules-title {
  color: var(--primary, #E63B6F);
  font-size: 14px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin: 0 0 16px;
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
  gap: 12px;
}

.rules-list li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 14px;
  color: var(--text-secondary, #636E72);
  line-height: 1.5;
}

.rule-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  line-height: 1.5;
  color: var(--primary, #E63B6F);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.rules-list li strong {
  color: var(--primary, #E63B6F);
  font-weight: 700;
}

@media (max-width: 480px) {
  .hero { padding: 24px; margin: 16px auto; }
  .content { padding: 0 12px 40px; }
}
</style>
