# SEO Audit & Fix Checklist — Quyền Sport

> Stack: Laravel 11 (backend) + Vue 3 SPA (Vite, Vue Router) + MySQL + Docker/WSL
> Ngày audit: 2026-07-06
> Vấn đề gốc: Dự án là **CSR thuần túy** (Client-Side Rendering) — server chỉ trả về `<div id="app"></div>` rỗng, toàn bộ nội dung do JS render sau khi tải. Đây là nguyên nhân của phần lớn vấn đề bên dưới.

---

## Mức độ ưu tiên

| Ưu tiên | Ý nghĩa |
|---|---|
| 🔴 Cao | Ảnh hưởng trực tiếp khả năng index/ranking, sửa ngay |
| 🟡 Trung bình | Ảnh hưởng chất lượng SEO, sửa trong đợt tiếp theo |
| 🟢 Thấp | Dọn dẹp, tối ưu thêm |

---

## 1. 🔴 `index.html` — Meta description sai hoàn toàn

**Hiện trạng:**
```html
<meta name="description" content="Ocean Admin - Hệ thống quản trị đại dương">
<title>Quyền Sport</title>
```
Đây là nội dung rác copy từ template admin khác, không liên quan đến shop thể thao.

**Cách sửa:**
```html
<meta name="description" content="Quyền Sport - Cửa hàng thiết bị thể thao vợt & bóng, sân thể thao chính hãng. Giao hàng nhanh, bảo hành uy tín.">
<meta property="og:site_name" content="Quyền Sport">
<meta property="og:type" content="website">
```

---

## 2. 🔴 Title & Meta không đổi theo dữ liệu động (chỉ đổi theo route tĩnh)

**Nguyên nhân:** `router/index.js` set title qua `to.meta.title` khai báo tĩnh:
```js
{ path: "product/:id", meta: { title: 'Chi tiết sản phẩm' } }
```
→ Mọi sản phẩm (giày, vợt, quần áo...) đều ra chung 1 title `"Chi tiết sản phẩm | Quyền Sport"` → Google coi là **duplicate content**, không xếp hạng riêng được theo từ khóa sản phẩm.

**Cách sửa:** Cài `@unhead/vue`, set title/meta ngay trong component sau khi có dữ liệu thật:

```bash
npm install @unhead/vue
```

```js
// main.js
import { createHead } from '@unhead/vue'
const head = createHead()
app.use(head)
```

```js
// productDetail.vue
import { useHead } from '@unhead/vue'

useHead(() => ({
  title: product.value ? `${product.value.name} | Quyền Sport` : 'Đang tải...',
  meta: [
    { name: 'description', content: product.value?.short_description || '' },
    { property: 'og:title', content: product.value?.name },
    { property: 'og:image', content: product.value?.image },
    { property: 'og:type', content: 'product' },
    { property: 'product:price:amount', content: product.value?.min_price },
    { property: 'product:price:currency', content: 'VND' },
  ],
}))
```

Áp dụng tương tự cho: `Product.vue` (danh sách/danh mục), `CourtDetail.vue`, các trang `Static/*.vue`.

---

## 3. 🔴 Thiếu Structured Data (JSON-LD / Schema.org)

Trang sản phẩm đã có sẵn dữ liệu (giá, tồn kho, 41 đánh giá 5 sao, breadcrumb) nhưng chưa khai báo schema cho Google → **không thể hiện rich snippet** (sao vàng, giá) trên kết quả tìm kiếm.

**Cần thêm 3 loại schema:**

```js
useHead(() => ({
  script: [
    {
      type: 'application/ld+json',
      children: JSON.stringify({
        "@context": "https://schema.org",
        "@type": "Product",
        "name": product.value?.name,
        "image": product.value?.image,
        "description": product.value?.short_description,
        "offers": {
          "@type": "Offer",
          "priceCurrency": "VND",
          "price": product.value?.min_price,
          "availability": product.value?.stock > 0
            ? "https://schema.org/InStock"
            : "https://schema.org/OutOfStock"
        },
        "aggregateRating": product.value?.review_count > 0 ? {
          "@type": "AggregateRating",
          "ratingValue": product.value?.rating,
          "reviewCount": product.value?.review_count
        } : undefined
      })
    }
  ]
}))
```

Và `BreadcrumbList` cho breadcrumb "Trang chủ > Pickleball > Tên sản phẩm" đã có sẵn trên UI.

---

## 4. 🔴 `main.js` — Mount app bị block bởi `initSessionSync()`

**Hiện trạng:**
```js
initSessionSync().then(() => {
    const app = createApp(App);
    ...
    app.mount('#app');
});
```
Toàn bộ app chỉ mount **sau khi** đồng bộ session xong → tăng thời gian màn hình trắng → ảnh hưởng **FCP/LCP** (Core Web Vitals, yếu tố xếp hạng của Google).

**Cách sửa:** Tách phần đồng bộ session ra khỏi đường găng render:
```js
const app = createApp(App);
app.use(pinia);
app.use(router);

const authStore = useAuthStore(pinia);
const cartStore = useCartStore(pinia);
const uiStore = useUiStore(pinia);

authStore.hydrate();
cartStore.bindWindowListeners();
uiStore.initializeBackofficeTheme();

app.mount('#app'); // mount ngay

// Chạy song song, không chặn render
initSessionSync();
cartStore.fetchCount();
```

---

## 5. 🟡 Route admin có thể bị Google index nhầm

Chưa có cơ chế `noindex` cho `/admin/*`.

**Cách sửa:**
- Thêm `robots.txt` (đặt ở `public/robots.txt` của Laravel):
```
User-agent: *
Disallow: /admin
Disallow: /profile
Disallow: /checkout
Disallow: /cart
Allow: /

Sitemap: https://oceansport.com/sitemap.xml
```
- Set thêm `meta robots: noindex` qua `useHead()` cho route admin (phòng trường hợp URL vẫn bị crawl trước khi robots.txt được đọc).

---

## 6. 🟡 Thiếu route catch-all (404) → gây "Soft 404"

Router hiện chưa có:
```js
{ path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../Pages/Client/NotFound.vue'), meta: { title: 'Không tìm thấy trang' } }
```
Thiếu route này khiến URL sai vẫn trả **HTTP 200** (vì SPA), Google Search Console sẽ báo lỗi "Soft 404", lãng phí crawl budget.

---

## 7. 🟡 Core Web Vitals — Ảnh hero (LCP) chưa tối ưu

`Home.vue`:
```html
<img :src="BASE_URL + '/storage/banners/banner_1.jpg'" alt="hero" class="hero-bg-img" />
```
- Không có `width`/`height` hay `aspect-ratio` cố định → có thể gây **CLS** (Cumulative Layout Shift).
- Đây là ảnh LCP (ảnh lớn nhất, tải đầu tiên) nhưng chưa có `fetchpriority="high"`.

**Cách sửa:**
```html
<img
  :src="BASE_URL + '/storage/banners/banner_1.jpg'"
  alt="hero"
  class="hero-bg-img"
  width="1920" height="560"
  fetchpriority="high"
/>
```

---

## 8. 🟢 Dead code / thiếu đồng nhất — dọn dẹp

| File | Vấn đề |
|---|---|
| `Home.vue` | Biến `banners`, hàm `getCatIcon`/`catIcons` khai báo nhưng không dùng trong template |
| `Home.vue` | Ảnh hero dùng `BASE_URL + '/storage/...'` trực tiếp, không đồng nhất với `getStorageUrl()` dùng ở nơi khác |
| `Home.vue` | Nút "Đăng ký tham gia ngay" có `@click="() => {}"` — chưa nối logic |
| `App.vue` | `onMounted` xóa `auth_token`/`user` khỏi localStorage mỗi lần khởi động app — cần xác nhận không còn nơi nào dùng 2 key này để tránh xóa nhầm phiên đăng nhập |

---

## 9. 🟢 Định hướng dài hạn: SSR / Prerender

CSR thuần chỉ nên là giải pháp tạm. Khi các mục 🔴 ở trên đã ổn định, cân nhắc:

| Giải pháp | Mức độ phù hợp với stack hiện tại |
|---|---|
| Chuyển sang SSR (vd Nuxt) | Tốt nhất về SEO nhưng cần refactor lớn |
| Prerendering (build sẵn HTML tĩnh cho từng route sản phẩm) | Chi phí sửa thấp hơn, phù hợp nếu số lượng sản phẩm không quá lớn/không đổi liên tục |
| Dynamic rendering (Laravel phát hiện bot, trả HTML tĩnh riêng cho crawler) | Giải pháp trung gian, cần thêm middleware ở Laravel |

---

## Thứ tự thực hiện đề xuất

1. ✅ Sửa `initSessionSync` chặn mount app (#4)
2. ✅ Sửa meta description sai trong `index.html` (#1)
3. ✅ Cài `@unhead/vue`, set title/meta động cho `productDetail.vue` + `Product.vue` (#2)
4. ✅ Thêm JSON-LD Product + Breadcrumb schema (#3)
5. ✅ Thêm `robots.txt` chặn `/admin` (#5)
6. ✅ Thêm route 404 (#6)
7. ✅ Tối ưu ảnh hero LCP (#7)
8. ✅ Dọn dead code (#8)
9. ⏳ Đánh giá SSR/Prerender khi có thời gian (#9)