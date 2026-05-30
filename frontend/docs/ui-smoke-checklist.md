# UI Smoke Checklist

## Breakpoints

- `390x844`: mobile portrait
- `768x1024`: tablet portrait
- `1280x800`: laptop

## Mobile Nav

1. Mở trang `/` ở `390x844`.
2. Nhấn nút menu ở header.
3. Xác nhận drawer hiển thị danh mục, `Liên hệ`, hành động tìm kiếm, login/profile.
4. Chuyển route từ drawer và xác nhận drawer tự đóng.
5. Thử mở search từ drawer và xác nhận modal search mở đúng.

## Admin Sidebar

1. Mở `/admin` ở `768x1024` và `390x844`.
2. Nhấn nút menu trong header backoffice.
3. Xác nhận sidebar trượt ra từ trái, overlay chặn nền.
4. Nhấn overlay hoặc đổi route trong admin và xác nhận sidebar đóng lại.
5. Kiểm tra dark mode toggle vẫn hoạt động bình thường.

## Cart To Checkout

1. Mở `/cart` với ít nhất một sản phẩm được chọn.
2. Đi tới `/checkout`.
3. Nếu tài khoản chưa có địa chỉ, xác nhận form địa chỉ mới tự mở.
4. Chọn địa chỉ có sẵn hoặc nhập địa chỉ mới rồi quan sát phí vận chuyển.
5. Áp mã giảm giá, đổi phương thức thanh toán, đặt hàng.

## Profile Address

1. Mở `/profile/addresses` ở `390x844`.
2. Mở modal thêm địa chỉ.
3. Xác nhận modal bám đáy màn hình, form và footer không tràn ngang.
4. Thử tạo, sửa, đặt mặc định và xoá một địa chỉ.

## Regression Notes

- `ClientHeader` dùng drawer riêng cho mobile, không phụ thuộc hover.
- `BackOfficeShell` quản lý overlay/sidebar ở mobile thay vì ép sidebar luôn hiện.
- `Checkout` tự chuyển sang flow thêm địa chỉ khi sổ địa chỉ trống.
