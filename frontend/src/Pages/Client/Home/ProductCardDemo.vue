<template>
  <div class="product-demo-page container py-5">
    <h1 class="text-center mb-5" style="color: #E63B6F;">Bộ Sưu Tập 20 Phong Cách Thẻ Sản Phẩm</h1>
    <p class="text-center text-muted mb-5">Danh sách các phong cách thẻ sản phẩm được thiết kế độc quyền với Ocean Blue Theme. Vui lòng chọn mẫu bạn ưng ý nhất để tích hợp vào trang web.</p>
    
    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-3">Đang tải dữ liệu sản phẩm mẫu...</p>
    </div>

    <div v-else class="demo-grid">
      <!-- Style 1: Tối giản, bo tròn góc cao (Mẫu hiện tại) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 1: Tối giản Nổi bật (Default)</h5>
        <div class="card-style-1" v-if="products[0]">
          <div class="img-wrapper">
            <img :src="getImageUrl(products[0])" :alt="products[0].name" />
            <button class="fav-btn"><i class="bi bi-heart"></i></button>
            <span class="badge" v-if="products[0].is_featured">HOT</span>
          </div>
          <div class="info">
            <p class="category">{{ products[0].category?.name || 'Danh mục' }}</p>
            <h3 class="name">{{ products[0].name }}</h3>
            <div class="price-row">
              <span class="price">{{ formatPrice(products[0].min_price) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Style 2: Viền ngoài liền mạch, action hiển thị khi hover -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 2: Outline Liền Mạch</h5>
        <div class="card-style-2" v-if="products[1]">
          <div class="img-wrapper">
            <img :src="getImageUrl(products[1])" :alt="products[1].name" />
            <div class="hover-actions">
              <button><i class="bi bi-cart-plus"></i></button>
              <button><i class="bi bi-heart"></i></button>
            </div>
          </div>
          <div class="info">
            <h3 class="name">{{ products[1].name }}</h3>
            <span class="price">{{ formatPrice(products[1].min_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Style 3: Thẻ nổi 3D, bóng đổ sâu -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 3: 3D Đổ Bóng Sâu</h5>
        <div class="card-style-3" v-if="products[2]">
          <div class="img-wrapper">
            <img :src="getImageUrl(products[2])" :alt="products[2].name" />
            <div class="overlay">
               <button class="btn-ocean">Xem chi tiết</button>
            </div>
          </div>
          <div class="info text-center">
            <h3 class="name">{{ products[2].name }}</h3>
            <span class="price">{{ formatPrice(products[2].min_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Style 4: Classic E-commerce, ảnh chiếm 70% -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 4: Cổ điển Chuẩn Mực</h5>
        <div class="card-style-4" v-if="products[3]">
          <div class="img-wrapper">
            <img :src="getImageUrl(products[3])" :alt="products[3].name" />
          </div>
          <div class="info">
            <h3 class="name">{{ products[3].name }}</h3>
            <div class="bottom-row">
              <span class="price">{{ formatPrice(products[3].min_price) }}</span>
              <button class="buy-btn"><i class="bi bi-plus-lg"></i></button>
            </div>
          </div>
        </div>
      </div>

      <!-- Style 5: Glassmorphism trên Ảnh -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 5: Kính Mờ (Glassmorphism)</h5>
        <div class="card-style-5" v-if="products[4]">
          <img :src="getImageUrl(products[4])" :alt="products[4].name" class="bg-img" />
          <div class="info-glass">
            <h3 class="name">{{ products[4].name }}</h3>
            <span class="price">{{ formatPrice(products[4].min_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Style 6: Hình nền xám, viền bo tròn góc đối xứng -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 6: Đối xứng Bất quy tắc</h5>
        <div class="card-style-6" v-if="products[5]">
          <div class="img-wrapper">
            <img :src="getImageUrl(products[5])" :alt="products[5].name" />
          </div>
          <div class="info">
            <h3 class="name">{{ products[5].name }}</h3>
            <p class="category text-ocean">{{ products[5].category?.name || 'Sản phẩm' }}</p>
            <span class="price">{{ formatPrice(products[5].min_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Style 7: Thiết kế Dạng Tag/Label (Thẻ Nhãn) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 7: Dạng Bảng Giá / Thẻ Nhãn</h5>
        <div class="card-style-7" v-if="products[6]">
          <div class="badge-ribbon">MỚI</div>
          <img :src="getImageUrl(products[6])" :alt="products[6].name" />
          <div class="info">
            <div class="price-tag">{{ formatPrice(products[6].min_price) }}</div>
            <h3 class="name">{{ products[6].name }}</h3>
          </div>
        </div>
      </div>

      <!-- Style 8: Dark Mode, Nổi bật Ảnh -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 8: Không gian Tối (Darkness)</h5>
        <div class="card-style-8" v-if="products[7]">
          <img :src="getImageUrl(products[7])" :alt="products[7].name" />
          <div class="info">
            <h3 class="name">{{ products[7].name }}</h3>
            <span class="price">{{ formatPrice(products[7].min_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Style 9: Split Layout (Trắng/Xanh) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 9: Chia Khối Nửa Màu</h5>
        <div class="card-style-9" v-if="products[8]">
          <div class="img-wrapper">
            <img :src="getImageUrl(products[8])" :alt="products[8].name" />
          </div>
          <div class="info">
            <h3 class="name">{{ products[8].name }}</h3>
            <span class="price">{{ formatPrice(products[8].min_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Style 10: Tròn lượn sóng Soft Aesthetic -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 10: Mềm mại (Soft Wave)</h5>
        <div class="card-style-10" v-if="products[9]">
          <div class="img-wrapper">
            <img :src="getImageUrl(products[9])" :alt="products[9].name" />
          </div>
          <div class="info text-center">
            <h3 class="name">{{ products[9].name }}</h3>
            <div class="divider"></div>
            <span class="price">{{ formatPrice(products[9].min_price) }}</span>
          </div>
        </div>
      </div>
      
      <!-- Style 11: Gradient Viền Tuyệt Đẹp -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 11: Viền Gradient Mở Trọng Tâm</h5>
        <div class="card-style-11" v-if="products[10]">
          <div class="inner-box">
             <img :src="getImageUrl(products[10])" :alt="products[10].name" />
             <div class="info">
               <h3 class="name">{{ products[10].name }}</h3>
               <span class="price">{{ formatPrice(products[10].min_price) }}</span>
             </div>
          </div>
        </div>
      </div>

       <!-- Style 12: Float Box Ảnh Nhảy Khỏi Thẻ -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 12: Khung Tranh Phá Cách</h5>
        <div class="card-style-12" v-if="products[11]">
          <div class="bg-shape"></div>
          <img :src="getImageUrl(products[11])" :alt="products[11].name" class="float-img"/>
          <div class="info text-right">
            <h3 class="name">{{ products[11].name }}</h3>
            <span class="price">{{ formatPrice(products[11].min_price) }}</span>
          </div>
        </div>
      </div>

       <!-- Style 13: Horizontal Mini Card (Cho Khám Phá/Lựa) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 13: Micro (Bố Cục Ngang)</h5>
        <div class="card-style-13" v-if="products[12]">
          <img :src="getImageUrl(products[12])" :alt="products[12].name" />
          <div class="info">
            <h3 class="name">{{ products[12].name }}</h3>
            <span class="price">{{ formatPrice(products[12].min_price) }}</span>
            <span class="view-more">Chi tiết &rarr;</span>
          </div>
        </div>
      </div>

      <!-- Style 14: Ảnh Bọc Toàn Bề Mặt, Gradient Phủ Đáy -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 14: Bao Phủ Biển (Cover Gradient)</h5>
        <div class="card-style-14" v-if="products[13]">
          <img :src="getImageUrl(products[13])" :alt="products[13].name" />
          <div class="info-overlay">
            <h3 class="name">{{ products[13].name }}</h3>
            <span class="price">{{ formatPrice(products[13].min_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Style 15: Neon Glow, Siêu Hiện Đại -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 15: Cyberpunk / Neon Glow</h5>
        <div class="card-style-15" v-if="products[14]">
          <img :src="getImageUrl(products[14])" :alt="products[14].name" />
          <div class="info">
            <h3 class="name">{{ products[14].name }}</h3>
            <span class="price text-glow">{{ formatPrice(products[14].min_price) }}</span>
          </div>
        </div>
      </div>

       <!-- Style 16: Thẻ Đứng Cứng Cáp (Blocky Solid) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 16: Hình Khối Chắc Chắn (Blocky)</h5>
        <div class="card-style-16" v-if="products[15]">
          <div class="img-wrapper">
             <img :src="getImageUrl(products[15])" :alt="products[15].name" />
             <div class="label-sale">-10%</div>
          </div>
          <div class="info">
             <h3 class="name">{{ products[15].name }}</h3>
             <button class="block-btn">{{ formatPrice(products[15].min_price) }}</button>
          </div>
        </div>
      </div>

      <!-- Style 17: Floating Action Button (Nút Nổi Lơ Lửng) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 17: Nút Mua Tròn Nổi (FAB)</h5>
        <div class="card-style-17" v-if="products[16]">
          <div class="img-wrapper">
             <img :src="getImageUrl(products[16])" :alt="products[16].name" />
             <button class="fab-btn"><i class="bi bi-bag"></i></button>
          </div>
          <div class="info">
             <p class="category">{{ products[16].category?.name || 'Mới' }}</p>
             <h3 class="name">{{ products[16].name }}</h3>
             <span class="price">{{ formatPrice(products[16].min_price) }}</span>
          </div>
        </div>
      </div>

       <!-- Style 18: Thẻ Gập Góc (Folded Corner) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 18: Hiệu Ứng Gập Giấy Thư Tín</h5>
        <div class="card-style-18" v-if="products[17]">
          <div class="fold-corner"></div>
          <div class="img-wrapper">
             <img :src="getImageUrl(products[17])" :alt="products[17].name" />
          </div>
          <div class="info">
             <h3 class="name">{{ products[17].name }}</h3>
             <span class="price">{{ formatPrice(products[17].min_price) }}</span>
          </div>
        </div>
      </div>

       <!-- Style 19: Typography Driven, Chữ Siêu To -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 19: Typography Táo Bạo</h5>
        <div class="card-style-19" v-if="products[18]">
          <img :src="getImageUrl(products[18])" :alt="products[18].name" />
          <div class="info">
             <span class="price-huge">{{ formatPrice(products[18].min_price).replace(' ₫', '') }}</span>
             <span class="currency">VNĐ</span>
             <h3 class="name">{{ products[18].name }}</h3>
          </div>
        </div>
      </div>

      <!-- Style 20: Tối giản Kiểu Nhật (Zen/Muji Style) -->
      <div class="demo-card-container">
        <h5 class="style-title">Style 20: Zen Thuần Khiết Muji</h5>
        <div class="card-style-20" v-if="products[19]">
          <img :src="getImageUrl(products[19])" :alt="products[19].name" />
          <div class="info">
             <h3 class="name">{{ products[19].name }}</h3>
             <span class="price">{{ formatPrice(products[19].min_price) }}</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../axios';
import { getStorageUrl } from '@/utils/url';

const products = ref([]);
const isLoading = ref(true);

const getImageUrl = (product) => {
    if (!product) return '';
    try {
        const imagePath = product.thumbnail_url || (product.mainImage ? product.mainImage.image_url : null);
        if (!imagePath) return '/placeholder-product.jpg';
        return getStorageUrl(imagePath);
    } catch {
        return '/placeholder-product.jpg';
    }
};

const formatPrice = (price) => {
    return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(price);
};

onMounted(async () => {
    try {
        const res = await api.get('/products?limit=25');
        products.value = res.data.data.filter(p => !p.thumbnail_url?.includes('picsum')).slice(0, 20);
        if (products.value.length < 20) {
            products.value = [...products.value, ...res.data.data.slice(0, 20 - products.value.length)];
        }
    } catch (e) {
        console.error("Lỗi lấy sản phẩm model", e);
    } finally {
        isLoading.value = false;
    }
});
</script>

<style scoped>
/* GENERAL STYLES */
.demo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 40px;
}
.demo-card-container {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.style-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2D3436;
    margin-bottom: 20px;
    text-align: center;
    border-bottom: 2px solid #E63B6F;
    padding-bottom: 6px;
    display: inline-block;
}

/* ================== S1: DEFAULT ================== */
.card-style-1 {
    width: 100%;
    max-width: 280px;
}
.card-style-1 .img-wrapper {
    position: relative; border-radius: 16px; aspect-ratio: 4/5; overflow: hidden; background: #f8fafc;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.3s;
}
.card-style-1:hover .img-wrapper { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08); }
.card-style-1 .img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s; }
.card-style-1:hover img { transform: scale(1.05); }
.card-style-1 .fav-btn { position: absolute; top: 12px; right: 12px; border:none; background:#fff; border-radius:50%; width:34px; height:34px; color:#9ca3af; box-shadow: 0 2px 5px rgba(0,0,0,0.1); opacity:0; transform:translateX(10px); transition: 0.3s; cursor:pointer; }
.card-style-1:hover .fav-btn { opacity:1; transform:translateX(0); }
.card-style-1 .badge { position: absolute; top:12px; left:12px; background:#0f172a; color:#fff; padding:4px 8px; font-size:0.65rem; border-radius:4px; font-weight:700;}
.card-style-1 .info { padding: 16px 0 12px; text-align: left; }
.card-style-1 .category { font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom:6px; }
.card-style-1 .name { font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-bottom:6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; transition: 0.2s;}
.card-style-1:hover .name { color: #E63B6F; }
.card-style-1 .price { font-weight: 700; color: #E63B6F; font-size: 0.95rem; }

/* ================== S2: OUTLINE ================== */
.card-style-2 { width: 100%; max-width: 280px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; transition: border-color 0.3s; }
.card-style-2:hover { border-color: #E63B6F; }
.card-style-2 .img-wrapper { position: relative; aspect-ratio: 1/1; border-radius: 6px; overflow: hidden; background: #f8fafc; }
.card-style-2 img { width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
.card-style-2:hover img { opacity: 0.8; }
.card-style-2 .hover-actions { position: absolute; bottom: -40px; left:0; width:100%; display:flex; justify-content:center; gap:10px; transition: 0.3s; }
.card-style-2:hover .hover-actions { bottom: 12px; }
.card-style-2 .hover-actions button { border:none; background:#E63B6F; color:white; width:40px; height:40px; border-radius:50%; box-shadow:0 4px 6px rgba(0,136,209,0.3); cursor:pointer; transition:0.2s; }
.card-style-2 .hover-actions button:hover { background: #016babaa; transform: translateY(-3px);}
.card-style-2 .info { padding-top: 16px; text-align: center; }
.card-style-2 .name { font-size: 0.9rem; font-weight: 600; color: #334155; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-style-2 .price { font-weight: 800; color: #E63B6F; }

/* ================== S3: 3D DEEP SHADOW ================== */
.card-style-3 { width: 100%; max-width: 280px; background: white; border-radius: 20px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.card-style-3:hover { transform: translateY(-10px); box-shadow: 0 30px 50px -10px rgba(230, 59, 111, 0.2); }
.card-style-3 .img-wrapper { position: relative; width: 100%; aspect-ratio: 4/3; }
.card-style-3 img { width: 100%; height: 100%; object-fit: cover; }
.card-style-3 .overlay { position: absolute; inset:0; background: rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; opacity:0; transition:0.3s; }
.card-style-3:hover .overlay { opacity: 1; }
.card-style-3 .btn-ocean { background: #E63B6F; color:white; border:none; padding:10px 20px; border-radius:30px; font-weight:600; font-size:0.85rem; cursor:pointer;}
.card-style-3 .info { padding: 20px; }
.card-style-3 .name { font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
.card-style-3 .price { color: #E63B6F; font-weight: 600; }

/* ================== S4: CLASSIC ================== */
.card-style-4 { width: 100%; max-width: 280px; }
.card-style-4 .img-wrapper { aspect-ratio: 1/1; overflow:hidden; background:#f1f5f9; margin-bottom: 12px; }
.card-style-4 img { width: 100%; height: 100%; object-fit: cover; }
.card-style-4 .info { padding: 0 8px; }
.card-style-4 .name { font-size: 0.95rem; font-weight: 500; color: #475569; margin-bottom:12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.card-style-4 .bottom-row { display: flex; justify-content: space-between; align-items: center; }
.card-style-4 .price { font-size: 1.1rem; font-weight: 800; color: #0f172a; }
.card-style-4 .buy-btn { width:32px; height:32px; border-radius:50%; border:1px solid #cbd5e1; background:white; color:#64748b; cursor:pointer; transition:0.2s; }
.card-style-4 .buy-btn:hover { background:#E63B6F; border-color:#E63B6F; color:white; }

/* ================== S5: GLASSMORPHISM ================== */
.card-style-5 { position: relative; width: 100%; max-width: 280px; border-radius: 16px; overflow: hidden; aspect-ratio: 3/4; }
.card-style-5 .bg-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.card-style-5:hover .bg-img { transform: scale(1.1); }
.card-style-5 .info-glass { position: absolute; bottom: 12px; left: 12px; right: 12px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.5); }
.card-style-5 .name { font-size: 0.9rem; font-weight: 700; color: #0f172a; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-style-5 .price { font-weight: 700; color: #E63B6F; }

/* ================== S6: ASYMMETRICAL ================== */
.card-style-6 { width: 100%; max-width: 280px; }
.card-style-6 .img-wrapper { background: #e2e8f0; aspect-ratio: 1/1; border-radius: 40px 10px 40px 10px; overflow:hidden; transition:0.3s; margin-bottom:16px;}
.card-style-6:hover .img-wrapper { border-radius: 10px 40px 10px 40px; background: #bae6fd;}
.card-style-6 img { width: 100%; height: 100%; object-fit: cover; mix-blend-mode: multiply; }
.card-style-6 .info { text-align: center; }
.card-style-6 .name { font-size: 1rem; font-weight: 700; color: #334155; margin-bottom:4px;}
.card-style-6 .text-ocean { font-size: 0.75rem; font-weight: 600; margin-bottom: 8px;}
.card-style-6 .price { display:inline-block; font-weight: 700; color: white; background: #E63B6F; padding:4px 16px; border-radius:20px; font-size: 0.85rem;}

/* ================== S7: LABEL / TAG ================== */
.card-style-7 { position:relative; width: 100%; max-width: 280px; border: 2px solid #f1f5f9; padding: 12px; background: white; transition: 0.2s; }
.card-style-7:hover { border-color: #E63B6F; }
.card-style-7 .badge-ribbon { position:absolute; top: -10px; right: 20px; background: #ef4444; color:white; font-size:0.7rem; font-weight:700; padding:6px 12px; text-transform:uppercase; z-index:1; }
.card-style-7 .badge-ribbon::after { content:''; position:absolute; bottom: -8px; left:0; width:0; height:0; border-left: 17px solid transparent; border-right: 17px solid transparent; border-top: 8px solid #ef4444; }
.card-style-7 img { width: 100%; aspect-ratio: 1/1; object-fit:cover; margin-bottom: 16px; border: 1px solid #e2e8f0; }
.card-style-7 .info { display: flex; flex-direction: column-reverse; align-items:center; }
.card-style-7 .name { font-size: 0.85rem; color: #475569; font-weight: 600; text-align:center; padding: 0 10px; }
.card-style-7 .price-tag { font-size: 1.1rem; font-weight: 800; color: #E63B6F; margin-top:8px;}

/* ================== S8: DARK MODE ================== */
.card-style-8 { width: 100%; max-width: 280px; background: #0f172a; border-radius: 12px; overflow:hidden; border: 1px solid #1e293b; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.card-style-8:hover { box-shadow: 0 0 20px rgba(230, 59, 111, 0.4); border-color:#0ea5e9;}
.card-style-8 img { width: 100%; aspect-ratio: 4/5; object-fit:cover; opacity: 0.8; transition:0.3s;}
.card-style-8:hover img { opacity: 1; }
.card-style-8 .info { padding: 16px; }
.card-style-8 .name { font-size: 0.95rem; font-weight: 600; color: #f8fafc; margin-bottom:6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-style-8 .price { font-weight: 600; color: #38bdf8; }

/* ================== S9: SPLIT HALVES ================== */
.card-style-9 { width: 100%; max-width: 280px; display: flex; flex-direction: column; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 12px; overflow:hidden;}
.card-style-9 .img-wrapper { aspect-ratio: 4/3; background: #f8fafc; overflow:hidden;}
.card-style-9 img { width: 100%; height: 100%; object-fit:cover; transition:0.3s; }
.card-style-9:hover img { transform: translateY(-5px); }
.card-style-9 .info { background: #E63B6F; color: white; padding: 20px; display: flex; flex-direction: column; justify-content: center; align-items: flex-start;}
.card-style-9 .name { font-size: 1rem; font-weight: 600; margin-bottom:8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: white; }
.card-style-9 .price { font-weight: 800; font-size: 1.1rem; color: #bae6fd; }

/* ================== S10: SOFT WAVE ================== */
.card-style-10 { width: 100%; max-width: 280px; background: #fff; padding: 16px; border-radius: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
.card-style-10 .img-wrapper { width:100%; aspect-ratio:1/1; border-radius: 50%; overflow:hidden; border: 6px solid #f1f5f9; margin-bottom:16px; transition:0.4s;}
.card-style-10:hover .img-wrapper { border-color: #E63B6F; transform: rotate(5deg); }
.card-style-10 img { width: 100%; height: 100%; object-fit:cover; }
.card-style-10 .name { font-size: 0.95rem; font-weight: 700; color:#334155; }
.card-style-10 .divider { width: 40px; height: 3px; background: #e2e8f0; margin: 10px auto; transition:0.3s; }
.card-style-10:hover .divider { width: 80px; background: #E63B6F; }
.card-style-10 .price { font-weight: 700; color: #E63B6F; }

/* ================== S11: GRADIENT BORDER ================== */
.card-style-11 { width: 100%; max-width: 280px; background: linear-gradient(135deg, #E63B6F 0%, #0ea5e9 100%); padding: 3px; border-radius: 12px; transition: 0.3s; }
.card-style-11:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 15px 30px rgba(230, 59, 111,0.3); }
.card-style-11 .inner-box { background: white; width: 100%; height: 100%; border-radius: 9px; padding: 12px; }
.card-style-11 img { width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 6px;}
.card-style-11 .info { padding-top: 12px; text-align: center; }
.card-style-11 .name { font-size: 0.9rem; font-weight: 600; color: #0f172a; margin-bottom:6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-style-11 .price { font-weight: 800; color: #E63B6F; }

/* ================== S12: FLOAT OUT BOX ================== */
.card-style-12 { position:relative; width: 100%; max-width: 280px; padding-top: 40px; }
.card-style-12 .bg-shape { position:absolute; top:80px; bottom:0; left:0; right:0; background: #f0f9ff; border-radius: 16px; z-index:0; transition:0.3s;}
.card-style-12:hover .bg-shape { background: #e0f2fe; }
.card-style-12 .float-img { position:relative; z-index:1; width:80%; margin: 0 auto; display:block; aspect-ratio:1/1; object-fit:cover; border-radius:12px; box-shadow:0 15px 25px rgba(0,0,0,0.1); transition:transform 0.3s;}
.card-style-12:hover .float-img { transform: translateY(-10px); }
.card-style-12 .info { position:relative; z-index:1; padding: 20px; padding-top: 16px; }
.card-style-12 .name { font-size: 0.95rem; font-weight: 600; color: #1e293b; margin-bottom:4px; text-align: center;}
.card-style-12 .price { font-weight: 800; color: #E63B6F; display:block; text-align: center; }

/* ================== S13: MICRO HORIZONTAL ================== */
.card-style-13 { width: 100%; max-width: 280px; display: flex; align-items: center; gap: 12px; padding: 12px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; transition:0.2s;}
.card-style-13:hover { border-color: #E63B6F; background:#f8fafc; }
.card-style-13 img { width: 80px; height: 80px; object-fit:cover; border-radius: 8px;}
.card-style-13 .info { flex:1; overflow:hidden;}
.card-style-13 .name { font-size: 0.85rem; font-weight: 600; color:#334155; margin-bottom:4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-style-13 .price { font-weight: 700; color: #E63B6F; font-size:0.9rem; display:block; margin-bottom:4px;}
.card-style-13 .view-more { font-size:0.75rem; color:#64748b; font-weight: 600; }

/* ================== S14: COVER GRADIENT ================== */
.card-style-14 { position:relative; width:100%; max-width:280px; aspect-ratio:4/5; border-radius:16px; overflow:hidden; }
.card-style-14 img { width:100%; height:100%; object-fit:cover; transition:transform 0.5s; }
.card-style-14:hover img { transform:scale(1.08); }
.card-style-14 .info-overlay { position:absolute; bottom:0; left:0; width:100%; padding:40px 20px 20px; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%); color:white;}
.card-style-14 .name { font-size: 1rem; font-weight: 600; margin-bottom:4px; color:white; -webkit-line-clamp:1; display:-webkit-box; -webkit-box-orient:vertical; overflow:hidden;}
.card-style-14 .price { font-size: 1.1rem; font-weight: 700; color: #38bdf8;}

/* ================== S15: CYBERPUNK NEON ================== */
.card-style-15 { width: 100%; max-width: 280px; background: #030712; border: 1px solid #1f2937; padding: 16px; border-radius: 8px; transition:0.3s; }
.card-style-15:hover { box-shadow: inset 0 0 20px rgba(230, 59, 111,0.2), 0 0 15px rgba(230, 59, 111,0.5); border-color:#0ea5e9;}
.card-style-15 img { width: 100%; aspect-ratio: 1/1; object-fit:cover; filter: contrast(1.1) brightness(0.9); margin-bottom:16px;}
.card-style-15 .info { text-align:center;}
.card-style-15 .name { font-size: 0.9rem; font-weight: 500; font-family: monospace; color: #9ca3af; margin-bottom:8px; text-transform:uppercase; letter-spacing:1px;}
.card-style-15 .text-glow { font-size: 1.1rem; font-weight: 800; font-family: monospace; color: #7dd3fc; text-shadow: 0 0 8px rgba(125,211,252,0.6);}

/* ================== S16: BLOCKY SOLID ================== */
.card-style-16 { width:100%; max-width:280px; background: white; border: 3px solid #0f172a; position:relative; box-shadow: 6px 6px 0 #E63B6F; transition: 0.2s;}
.card-style-16:hover { box-shadow: 2px 2px 0 #E63B6F; transform:translate(4px, 4px); }
.card-style-16 .img-wrapper { position:relative; border-bottom: 3px solid #0f172a; aspect-ratio:1/1;}
.card-style-16 img { width: 100%; height:100%; object-fit:cover;}
.card-style-16 .label-sale { position:absolute; top:12px; left:-3px; background: #ef4444; color:white; font-weight:900; padding:4px 12px; border: 3px solid #0f172a; border-left:none;}
.card-style-16 .info { padding: 16px; }
.card-style-16 .name { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 12px; text-transform:uppercase; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.card-style-16 .block-btn { width: 100%; background: #0f172a; color: white; border:none; padding:10px; font-weight:800; text-align:center; cursor:pointer;}
.card-style-16 .block-btn:hover { background: #E63B6F; }

/* ================== S17: FAB BUTTON ================== */
.card-style-17 { width: 100%; max-width: 280px; background:#fff; border-radius: 16px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); padding-bottom: 16px;}
.card-style-17 .img-wrapper { position:relative; width:100%; aspect-ratio:1/1; border-radius: 16px; overflow:hidden;}
.card-style-17 img { width:100%; height:100%; object-fit:cover; transition:0.3s;}
.card-style-17:hover img { transform:scale(1.05);}
.card-style-17 .fab-btn { position:absolute; bottom:-20px; right: 20px; width:46px; height:46px; border-radius:50%; background:#E63B6F; color:white; border:none; box-shadow:0 6px 12px rgba(230, 59, 111,0.3); z-index:2; cursor:pointer; font-size:1.1rem; transition:0.2s;}
.card-style-17 .fab-btn:hover { transform:scale(1.1); }
.card-style-17 .info { padding: 24px 16px 0; }
.card-style-17 .category { font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform:uppercase; margin-bottom:4px;}
.card-style-17 .name { font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom:6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 30px;}
.card-style-17 .price { font-weight: 700; color: #E63B6F; }

/* ================== S18: FOLD CORNER ================== */
.card-style-18 { position:relative; width:100%; max-width:280px; background:white; padding:12px; border: 1px solid #cbd5e1; box-shadow: 2px 2px 5px rgba(0,0,0,0.05); transition:0.3s;}
.card-style-18:hover { box-shadow: 8px 8px 15px rgba(0,0,0,0.08); transform: translateY(-4px);}
.card-style-18 .fold-corner { position:absolute; top:-1px; right:-1px; width: 0; height: 0; border-bottom: 30px solid #e2e8f0; border-right: 30px solid transparent; box-shadow: -2px 2px 2px rgba(0,0,0,0.1); z-index:2;}
.card-style-18::after { content:''; position:absolute; top:-1px; right:-1px; width:30px; height:30px; background:#f8fafc; z-index:1;} /* Hiding background behind fold */
.card-style-18 .img-wrapper { aspect-ratio:1/1; overflow:hidden; margin-bottom:16px;}
.card-style-18 img { width:100%; height:100%; object-fit:cover;}
.card-style-18 .info { text-align:center;}
.card-style-18 .name { font-size:0.95rem; font-weight:600; color:#334155; margin-bottom:8px;}
.card-style-18 .price { font-weight:700; color:#E63B6F; font-size:1.05rem;}

/* ================== S19: HUGE TYPOGRAPHY ================== */
.card-style-19 { position:relative; width:100%; max-width:280px; aspect-ratio:3/4; overflow:hidden; background: #e0f2fe; }
.card-style-19 img { position:absolute; top:0; right:-20%; height:80%; object-fit:contain; filter:drop-shadow(-10px 10px 10px rgba(0,0,0,0.1)); transition:0.4s;}
.card-style-19:hover img { right:-10%; }
.card-style-19 .info { position:absolute; bottom:20px; left:20px; width:80%;}
.card-style-19 .price-huge { font-size: 2.5rem; font-weight: 900; color: #E63B6F; line-height:1; letter-spacing:-1px; display:block;}
.card-style-19 .currency { font-size: 0.9rem; font-weight: 700; color: #0369a1; text-transform:uppercase;}
.card-style-19 .name { font-size: 0.9rem; font-weight: 700; color: #0f172a; margin-top:8px; border-top:2px solid #bae6fd; padding-top:8px;}

/* ================== S20: MUJI ZEN ================== */
.card-style-20 { width: 100%; max-width: 280px; }
.card-style-20 img { width:100%; aspect-ratio:4/5; object-fit:cover; margin-bottom:8px;}
.card-style-20 .info { display: flex; justify-content: space-between; align-items: flex-start; padding: 4px 0;}
.card-style-20 .name { font-size: 0.85rem; font-weight: 400; color: #4b5563; max-width: 65%; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; letter-spacing:0.5px;}
.card-style-20 .price { font-size: 0.9rem; font-weight: 400; color: #111827; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .demo-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; }
    .card-style-1, .card-style-2, .card-style-3, .card-style-4, .card-style-5, .card-style-6, .card-style-7, .card-style-8, .card-style-9, .card-style-10, .card-style-11, .card-style-12, .card-style-13, .card-style-14, .card-style-15, .card-style-16, .card-style-17, .card-style-18, .card-style-19, .card-style-20 { max-width: 100%; }
    .card-style-19 .price-huge { font-size: 1.5rem; }
    .card-style-13 { flex-direction: column; text-align:center;}
}
</style>
