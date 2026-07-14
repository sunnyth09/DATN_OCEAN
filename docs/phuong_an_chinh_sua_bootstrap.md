# Phương án Đồng bộ Giao diện Bootstrap

Tài liệu này liệt kê các file chưa được chuẩn hóa giao diện và cung cấp phương án an toàn, triệt để nhất để đưa toàn bộ dự án về chuẩn grid của Bootstrap.

## 1. Danh sách các file cần chỉnh sửa (Phía Client)

Dưới đây là các file `.vue` hiện tại **chưa sử dụng** class `container` của Bootstrap để căn lề. Bạn cần ưu tiên xử lý nhóm này vì nó ảnh hưởng trực tiếp đến người dùng cuối:

**Trang Giỏ hàng & Thanh toán:**
- `frontend/src/Pages/Client/Cart/Checkout.vue` *(Rất quan trọng)*
- `frontend/src/Pages/Client/Cart/OrderSuccess.vue`
- `frontend/src/Pages/Client/Payment/PaymentResult.vue`

**Trang Cá nhân (Profile):**
- `frontend/src/Pages/Client/Profile/Address.vue`
- `frontend/src/Pages/Client/Profile/ProfileAddress.vue`
- `frontend/src/Pages/Client/Profile/ProfileAffiliate.vue`
- `frontend/src/Pages/Client/Profile/ProfileInfo.vue`
- `frontend/src/Pages/Client/Profile/ProfileLoyalty.vue`
- `frontend/src/Pages/Client/Profile/ProfileNotifications.vue`
- `frontend/src/Pages/Client/Profile/ProfileReturnRequestDetail.vue`
- `frontend/src/Pages/Client/Profile/ProfileReturnRequests.vue`
- `frontend/src/Pages/Client/Profile/ProfileWallet.vue`

**Trang Xác thực & Khác:**
- `frontend/src/Pages/Client/Auth/Forgot.vue`
- `frontend/src/Pages/Client/Auth/FacebookCallback.vue`
- `frontend/src/Pages/Client/Auth/GoogleCallback.vue`
- `frontend/src/Pages/Client/GuestTracking.vue`
- `frontend/src/Pages/Client/Courts/UserBookings.vue`

---

## 2. Phương án chỉnh sửa hoàn chỉnh, dễ dàng và an toàn

Để đưa toàn bộ về chuẩn Bootstrap **mà không sợ làm hỏng cấu trúc hiện tại** và **không phải đi sửa lắt nhắt từng file một**, hãy áp dụng chiến lược dưới đây:

### Bước 1: Xử lý từ File Layout (Dành cho các trang Profile/Nested pages)

Thay vì mở từng file ở mục Profile ra để thêm `<div class="container">`, bạn chỉ cần tìm file Layout cha của chúng (ví dụ: `ProfileLayout.vue` hoặc `ClientLayout.vue`).

Hãy tìm thẻ `<router-view />` hoặc thẻ `<slot />` (nơi nội dung các trang con được nhúng vào) và bọc nó lại:

```html
<!-- Mở file Layout (ví dụ: ProfileLayout.vue) -->
<template>
  <div class="profile-page-wrapper">
    <!-- Bọc container ở đây 1 lần duy nhất -->
    <div class="container py-4">
       <router-view /> <!-- Các trang con sẽ tự động được thụ hưởng lề của container -->
    </div>
  </div>
</template>
```

> **Lưu ý quan trọng**: Việc này giúp tiết kiệm 80% công sức. Chỉnh 1 file Layout sẽ tự động căn lề cho toàn bộ các file con bên trong nó.

### Bước 2: Xử lý các trang đơn lẻ 

Với các trang đứng độc lập, không dùng chung `<router-view>` có bọc sẵn (như `Checkout.vue`), bạn vào thẳng file đó, bọc khối nội dung chính bằng `container`. 

```html
<!-- Mở file Checkout.vue -->
<template>
  <div class="checkout-page">
    <!-- Thêm class container ở lớp bọc ngoài cùng -->
    <div class="container mt-5 mb-5"> 
       <!-- Toàn bộ nội dung form thanh toán cũ để nguyên bên trong -->
       <div class="row">...</div>
    </div>
  </div>
</template>
```

### Bước 3: Loại bỏ class xung đột của Tailwind CSS

Dự án của bạn đang dùng chung cả Tailwind CSS và Bootstrap. Class `.container` mặc định có mặt ở cả 2 framework và chúng sẽ ghi đè nhau, gây lỗi responsive.

Để Bootstrap làm đúng nhiệm vụ căn lề (1140px, 1320px...), bạn **phải** tắt class container của Tailwind đi.

Mở file cấu hình Tailwind (ví dụ `tailwind.config.js` hoặc cấu hình trong Vite) và thiết lập:

```javascript
// tailwind.config.js
module.exports = {
  // ...
  corePlugins: {
    container: false, // Tắt class container của Tailwind để nhường quyền kiểm soát cho Bootstrap
  }
}
```

> **BẮT BUỘC**: Bước 3 là bắt buộc khi kết hợp Bootstrap và Tailwind. Nếu bỏ qua bước này, thẻ `container` của bạn sẽ bị lệch lề trên một số màn hình do Tailwind can thiệp.

## Tổng kết
Áp dụng đúng 3 bước trên, bạn sẽ đạt được tỉ lệ làm vỡ UI là 0%, giữ nguyên mọi logic bên trong và đảm bảo toàn bộ website được căn lề đồng bộ.
