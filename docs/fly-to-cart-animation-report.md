# Báo Cáo Tính Năng: Animation Bay Vào Giỏ Hàng (Fly To Cart)

## 1. Giới thiệu
Tính năng "Fly To Cart" tạo hiệu ứng chuyển động mượt mà khi người dùng thêm sản phẩm vào giỏ hàng. Ảnh của sản phẩm sẽ được clone và bay theo một đường cong tới icon giỏ hàng trên thanh header, giúp tăng cường trải nghiệm người dùng (UX) và mang lại cảm giác phản hồi trực quan ngay lập tức.

## 2. Các file đã chỉnh sửa
- `src/composables/useFlyToCart.js` (Tạo mới): Chứa logic chính để clone phần tử ảnh, tính toán toạ độ (BoundingClientRect) và thực hiện hiệu ứng di chuyển sử dụng Web Animations API & CSS Transition.
- `src/components/ClientHeader.vue`: Thêm `id="cart-icon"` vào thẻ chứa icon giỏ hàng, và thêm animation class `.cart-pop-animation` (rung nhẹ/nảy lên) để icon giỏ hàng phản hồi khi ảnh bay đến nơi.
- `src/components/ProductCard.vue`: Import composable, thêm `ref="productImageRef"`, disable nút "Thêm vào giỏ" (chống spam click) khi đang gọi API, và chạy animation bay vào giỏ hàng nếu API trả về thành công.
- `src/Pages/Client/Home/productDetail.vue`: Tương tự như ProductCard, import composable, gắn ref vào ảnh chính của sản phẩm, disable nút khi đang xử lý thêm, và chạy animation.

## 3. Cách hoạt động của animation
1. **Lấy toạ độ**: `useFlyToCart` nhận vào tham số là `imageElement` (ảnh gốc) và `cartIconId`. Nó dùng `getBoundingClientRect()` để lấy chính xác toạ độ `top, left, width, height` của ảnh và giỏ hàng.
2. **Clone ảnh**: Bức ảnh đang hiển thị sẽ được `cloneNode()`, gỡ bỏ `id` để tránh đụng độ DOM, và được set thuộc tính `position: fixed` với toạ độ ban đầu trùng với ảnh thật.
3. **Thực thi hiệu ứng**: Bằng việc thay đổi giá trị `top`, `left`, `transform: scale(0.1)` qua CSS `transition: all 0.8s cubic-bezier(0.25, 1, 0.5, 1)`, bức ảnh từ từ thu nhỏ và bay về vị trí của icon giỏ hàng.
4. **Kết thúc (Cleanup)**: Sau 800ms, ảnh clone bị xoá khỏi DOM. Đồng thời, một class `.cart-pop-animation` được add vào `#cart-icon` để tạo hiệu ứng nảy (pop) thông báo sản phẩm đã vào giỏ.

## 4. Cách test chức năng
- Mở danh sách sản phẩm (Home, Product List), nhấn vào icon giỏ hàng trên bất kỳ Product Card nào.
- Mở trang chi tiết sản phẩm, chọn màu/size (nếu có), bấm "Thêm vào giỏ hàng".
- Hãy scroll trang xuống và bấm, đảm bảo ảnh vẫn bay chính xác đến vị trí icon giỏ hàng đang ở trạng thái fixed/sticky (nhờ `getBoundingClientRect` luôn lấy toạ độ chuẩn trên màn hình).

## 5. Các fallback đã xử lý
- **Lỗi API / Unauthenticated**: Không chạy animation nếu người dùng chưa đăng nhập hoặc API báo lỗi.
- **Không tìm thấy ảnh hoặc giỏ hàng**: Hàm composable sẽ in ra warning ở console và bỏ qua (không gây crash app).
- **Chống Spam Click**: Button được disable (`isAddingToCart`) và xoay icon spinner để tránh trường hợp người dùng bấm liên tục gây ra gọi API rác.

## 6. Lưu ý nâng cấp trong tương lai
- **Đường bay cong phức tạp hơn**: Hiện tại sử dụng CSS transition với `cubic-bezier` để tạo cảm giác tự nhiên. Nếu muốn quỹ đạo bay (trajectory) lượn cong chính xác theo trục parabol (như bezier curve), có thể tích hợp thư viện **GSAP** (`gsap.to`) kèm plugin MotionPath hoặc dùng Javascript tách rời 2 trục (X dùng ease-linear, Y dùng ease-in).
- **Thêm Particle Effects**: Bằng CSS hoặc Canvas khi ảnh va chạm vào giỏ hàng, nếu muốn hiệu ứng hoành tráng hơn.
