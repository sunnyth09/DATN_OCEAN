<script setup>
import { computed } from 'vue';

const props = defineProps({
  Categories: {
    type: Array,
    default: () => []
  },
  topCategories: {
    type: Array,
    default: () => []
  },
  isLoadingCategories: {
    type: Boolean,
    default: false
  }
});

// Fallback images only when database image is empty
const DEFAULT_SPORT_IMAGES = {
  pickleball: 'https://images.unsplash.com/photo-1599474924187-334a4ae5bd3c?q=80&w=800&auto=format&fit=crop',
  badminton: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?q=80&w=800&auto=format&fit=crop',
  tennis: 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?q=80&w=800&auto=format&fit=crop',
};

// 3 Main Sport Pillars (Pickleball, Cầu Lông, Tennis) directly from Database
const mainSports = computed(() => {
  const all = props.Categories || [];
  
  // 1. Pickleball
  const pickleCat = all.find(c => (c.name || '').toLowerCase() === 'pickleball') || 
                    all.find(c => (c.name || '').toLowerCase().includes('pickleball') && !c.parentName) || 
                    { id: 1, name: 'Pickleball' };
  
  const pickleSubs = all.filter(c => {
    const n = (c.name || '').toLowerCase();
    const p = (c.parentName || '').toLowerCase();
    return (n.includes('pickleball') || p.includes('pickleball')) && c.id !== pickleCat.id;
  }).map(c => ({
    id: c.id,
    name: c.name,
    shortName: c.name.replace(/pickleball/gi, '').trim() || c.name
  }));

  // 2. Cầu Lông
  const badCat = all.find(c => (c.name || '').toLowerCase() === 'cầu lông') || 
                 all.find(c => (c.name || '').toLowerCase().includes('cầu lông') && !c.parentName) || 
                 { id: 2, name: 'Cầu Lông' };

  const badSubs = all.filter(c => {
    const n = (c.name || '').toLowerCase();
    const p = (c.parentName || '').toLowerCase();
    return (n.includes('cầu lông') || p.includes('cầu lông')) && c.id !== badCat.id;
  }).map(c => ({
    id: c.id,
    name: c.name,
    shortName: c.name.replace(/cầu lông/gi, '').trim() || c.name
  }));

  // 3. Tennis
  const tenCat = all.find(c => (c.name || '').toLowerCase() === 'tennis') || 
                 all.find(c => (c.name || '').toLowerCase().includes('tennis') && !c.parentName) || 
                 { id: 3, name: 'Tennis' };

  const tenSubs = all.filter(c => {
    const n = (c.name || '').toLowerCase();
    const p = (c.parentName || '').toLowerCase();
    return (n.includes('tennis') || p.includes('tennis')) && c.id !== tenCat.id;
  }).map(c => ({
    id: c.id,
    name: c.name,
    shortName: c.name.replace(/tennis/gi, '').trim() || c.name
  }));

  return [
    {
      id: pickleCat.id,
      name: pickleCat.rawName || pickleCat.name || 'Pickleball',
      tag: 'XU HƯỚNG MỚI',
      description: 'Vợt, bóng, giày & trang bị thi đấu tiêu chuẩn',
      image: (pickleCat.image && String(pickleCat.image).trim() !== '' && pickleCat.image !== '0') 
        ? pickleCat.image 
        : DEFAULT_SPORT_IMAGES.pickleball,
      subcategories: pickleSubs.length > 0 ? pickleSubs : [
        { id: pickleCat.id, shortName: 'Vợt' },
        { id: pickleCat.id, shortName: 'Giày' },
        { id: pickleCat.id, shortName: 'Balo' }
      ]
    },
    {
      id: badCat.id,
      name: badCat.rawName || badCat.name || 'Cầu Lông',
      tag: 'BÁN CHẠY NHẤT',
      description: 'Vợt Yonex, Lining, cước đan & quả cầu chính hãng',
      image: (badCat.image && String(badCat.image).trim() !== '' && badCat.image !== '0') 
        ? badCat.image 
        : DEFAULT_SPORT_IMAGES.badminton,
      subcategories: badSubs.length > 0 ? badSubs : [
        { id: badCat.id, shortName: 'Vợt Cầu Lông' },
        { id: badCat.id, shortName: 'Giày Cầu Lông' },
        { id: badCat.id, shortName: 'Quả Cầu & Cước' }
      ]
    },
    {
      id: tenCat.id,
      name: tenCat.rawName || tenCat.name || 'Tennis',
      tag: 'CHUYÊN NGHIỆP',
      description: 'Vợt Wilson, Babolat, Head & phụ kiện sân đấu',
      image: (tenCat.image && String(tenCat.image).trim() !== '' && tenCat.image !== '0') 
        ? tenCat.image 
        : DEFAULT_SPORT_IMAGES.tennis,
      subcategories: tenSubs.length > 0 ? tenSubs : [
        { id: tenCat.id, shortName: 'Vợt' },
        { id: tenCat.id, shortName: 'Giày' },
        { id: tenCat.id, shortName: 'Balo' }
      ]
    }
  ];
});

const handleImgError = (e, sportName) => {
  if (e.target) {
    const key = (sportName || '').toLowerCase();
    if (key.includes('pickleball')) e.target.src = DEFAULT_SPORT_IMAGES.pickleball;
    else if (key.includes('cầu lông') || key.includes('badminton')) e.target.src = DEFAULT_SPORT_IMAGES.badminton;
    else e.target.src = DEFAULT_SPORT_IMAGES.tennis;
  }
};
</script>

<template>
  <section class="section-categories py-5 reveal-on-scroll">
    <div class="container">
      
      <!-- Section Header -->
      <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
        <div>
          <span class="section-kicker">KHÁM PHÁ THEO BỘ MÔN</span>
          <h2 class="section-title mb-1 fw-bold">DANH MỤC THỂ THAO NỔI BẬT</h2>
          <p class="section-subtitle mb-0">Trang thiết bị, vợt và phụ kiện chính hãng chuyên dụng cho từng môn thi đấu</p>
        </div>
        <router-link to="/product" class="link-view-all d-inline-flex align-items-center gap-2">
          <span>Tất cả danh mục</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </router-link>
      </div>

      <!-- Loading Skeletons -->
      <div v-if="isLoadingCategories" class="row g-4">
        <div class="col-lg-4 col-md-6" v-for="i in 3" :key="i">
          <div class="pillar-skeleton skeleton-pulse"></div>
        </div>
      </div>

      <!-- 3 SPORT PILLAR HERO CARDS (BẢN CHUẨN ĐÚNG GU 100%) -->
      <div v-else class="row g-4">
        <div 
          v-for="sport in mainSports" 
          :key="sport.id" 
          class="col-lg-4 col-md-6"
        >
          <div class="sport-pillar-card">
            <router-link :to="'/product?category=' + sport.id" class="pillar-link">
              
              <!-- Background Image Box với ảnh gốc từ web -->
              <div class="pillar-img-box">
                <img 
                  :src="sport.image" 
                  :alt="sport.name" 
                  class="pillar-img" 
                  loading="lazy"
                  @error="(e) => handleImgError(e, sport.name)"
                />
                <div class="pillar-gradient"></div>
              </div>

              <!-- Foreground Content -->
              <div class="pillar-content">
                <span class="pillar-tag">{{ sport.tag }}</span>
                <h3 class="pillar-name">{{ sport.name }}</h3>
                <p class="pillar-desc">{{ sport.description }}</p>

                <!-- Subcategory quick chips -->
                <div class="pillar-chips" v-if="sport.subcategories.length > 0">
                  <router-link
                    v-for="sub in sport.subcategories"
                    :key="sub.id"
                    :to="'/product?category=' + sub.id"
                    class="pillar-chip"
                    @click.stop
                  >
                    {{ sub.shortName }}
                  </router-link>
                </div>

                <div class="pillar-cta">
                  <span>Khám phá bộ sưu tập</span>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                  </svg>
                </div>
              </div>

            </router-link>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>

<style scoped>
.section-categories {
  background: #f8fafc;
}

/* Header */
.section-kicker {
  display: block;
  color: var(--primary, #e63b6f);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.section-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: var(--text-main, #2D3436);
  letter-spacing: -0.5px;
}

.section-subtitle {
  color: var(--text-secondary, #636E72);
  font-size: 0.9rem;
}

.link-view-all {
  padding: 8px 18px;
  border-radius: 999px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  color: #0f172a;
  font-size: 0.88rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.25s ease;
  white-space: nowrap;
}

.link-view-all:hover {
  background: var(--primary, #e63b6f);
  border-color: var(--primary, #e63b6f);
  color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(230, 59, 111, 0.25);
}

/* ── 3 SPORT PILLAR HERO CARDS ── */
.sport-pillar-card {
  height: 380px;
  min-height: 380px;
  border-radius: 20px;
  overflow: hidden;
  position: relative;
  background: #1e293b;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.12);
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
}

.sport-pillar-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 22px 45px rgba(15, 23, 42, 0.22);
}

.pillar-link {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  height: 100%;
  padding: 24px 26px;
  text-decoration: none;
  position: relative;
}

/* Background Image Box */
.pillar-img-box {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  background: #1e293b;
  overflow: hidden;
}

.pillar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 8%;
  transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.sport-pillar-card:hover .pillar-img {
  transform: scale(1.05);
}

/* Gradient Overlay tạo chiều sâu và độ tương phản tự nhiên */
.pillar-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    rgba(15, 23, 42, 0.08) 0%,
    rgba(15, 23, 42, 0.38) 42%,
    rgba(15, 23, 42, 0.90) 80%,
    rgba(15, 23, 42, 0.98) 100%
  );
  pointer-events: none;
}

.pillar-content {
  position: relative;
  z-index: 2;
}

.pillar-tag {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.pillar-name {
  color: #ffffff;
  font-size: 1.8rem;
  font-weight: 900;
  letter-spacing: -0.5px;
  margin: 0 0 6px 0;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.pillar-desc {
  color: rgba(255, 255, 255, 0.88);
  font-size: 0.86rem;
  line-height: 1.45;
  margin-bottom: 14px;
}

/* Subcategory chips */
.pillar-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 16px;
}

.pillar-chip {
  display: inline-block;
  padding: 5px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
}

.pillar-chip:hover {
  background: var(--primary, #e63b6f);
  border-color: var(--primary, #e63b6f);
  color: #ffffff;
  transform: translateY(-2px);
}

.pillar-cta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #ffffff;
  font-size: 0.86rem;
  font-weight: 700;
  transition: transform 0.2s ease;
}

.sport-pillar-card:hover .pillar-cta {
  color: #ff85a8;
  transform: translateX(4px);
}

/* Skeleton Pulse */
.pillar-skeleton {
  height: 380px;
  border-radius: 20px;
}

.skeleton-pulse {
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%;
  animation: skeletonLoad 1.5s infinite;
}

@keyframes skeletonLoad {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Responsive */
@media (max-width: 991px) {
  .sport-pillar-card {
    height: 350px;
    min-height: 350px;
  }

  .pillar-link {
    padding: 20px;
  }

  .pillar-name {
    font-size: 1.5rem;
  }
}

@media (max-width: 768px) {
  .section-title {
    font-size: 1.5rem;
  }
}

@media (max-width: 480px) {
  .sport-pillar-card {
    height: 320px;
    min-height: 320px;
  }

  .pillar-link {
    padding: 18px;
  }

  .pillar-name {
    font-size: 1.35rem;
  }
}
</style>
