# Order Return Refund Report

## 1. Trạng thái đơn hàng cũ đang có

### Order `fulfillment_status`
- `pending`
- `confirmed`
- `packing`
- `shipping`
- `delivered`
- `completed`
- `cancelled`
- `returned`

### Order `payment_status`
- `unpaid`
- `paid`
- `failed`
- `refunded`
- `partially_refunded`

### Payment record `payments.status`
- `pending`
- `success`
- `failed`
- `refunded`

### Luồng cũ
- User tạo đơn trong `OrderService`, mặc định `payment_status=unpaid`, `fulfillment_status=pending`.
- User chỉ được hủy đơn khi `pending`.
- Admin đổi trạng thái trong `AdminOrderService`.
- Thanh toán COD được tự đánh dấu `paid` khi admin chuyển sang `completed`.
- Chưa có domain riêng cho hoàn hàng/hoàn tiền.
- Trạng thái đang bị hard-code ở nhiều nơi: backend service, repository, Vue user pages, Vue admin pages, stats/export/affiliate sync.

## 2. Trạng thái mới đã chuẩn hóa

### Order `fulfillment_status`
- `pending`
- `confirmed`
- `processing`
- `packing` `legacy compatibility`
- `shipping`
- `delivered`
- `completed`
- `cancelled`
- `return_requested`
- `return_approved`
- `return_rejected`
- `returned`
- `refunded`

### Order `payment_status`
- `unpaid`
- `paid`
- `failed`
- `refund_pending`
- `refunded`
- `refund_failed`
- `partially_refunded`

### Return request `status`
- `pending`
- `approved`
- `rejected`
- `received`
- `refunded`

### Return request `refund_status`
- `none`
- `pending`
- `success`
- `failed`

### Ghi chú tương thích dữ liệu cũ
- Không xóa trạng thái cũ `packing`, `delivered`.
- Bổ sung `processing` để chuẩn hóa luồng mới nhưng vẫn map/filter tương thích `packing`.
- Khi filter `processing` ở backend, hệ thống lấy cả `processing` và `packing`.

## 3. Quy tắc nghiệp vụ đã triển khai

### User tạo yêu cầu hoàn hàng
- Chỉ tạo được khi đơn thuộc user hiện tại.
- Chỉ tạo được khi đơn ở `completed` hoặc `delivered`.
- Không tạo được nếu đang có yêu cầu hoàn hàng đang xử lý.
- Không tạo được nếu đã từng hoàn thành hoàn hàng/hoàn tiền.
- Có giới hạn thời gian mặc định `7 ngày` sau `completed_at` hoặc `delivered_at`.
- Tạo `return_request.status=pending`.
- Cập nhật `order.fulfillment_status=return_requested`.
- Không tự động hoàn tiền.

### Admin duyệt
- `return_request.status=approved`
- `order.fulfillment_status=return_approved`
- Nếu đơn đã thanh toán thì `order.payment_status=refund_pending`

### Admin từ chối
- `return_request.status=rejected`
- `order.fulfillment_status=completed`
- `payment_status` giữ nguyên

### Admin xác nhận đã nhận hàng hoàn
- `return_request.status=received`
- `order.fulfillment_status=returned`

### Admin xác nhận hoàn tiền
- Hoàn tiền thủ công qua `RefundService -> ManualRefundService`
- `return_request.status=refunded`
- `return_request.refund_status=success`
- `order.fulfillment_status=refunded`
- `order.payment_status=refunded`
- Cập nhật `refund_amount`, `refund_method`, `refunded_at`

## 4. Migration đã thêm

- `backend/database/migrations/2026_05_31_120000_create_return_requests_table.php`
- `backend/database/migrations/2026_05_31_120100_expand_order_and_payment_statuses_for_returns.php`

## 5. File backend đã tạo/sửa

### File tạo mới
- `backend/app/Enums/OrderStatus.php`
- `backend/app/Enums/PaymentStatus.php`
- `backend/app/Enums/RefundMethod.php`
- `backend/app/Enums/RefundStatus.php`
- `backend/app/Enums/ReturnRequestStatus.php`
- `backend/app/Contracts/PaymentGatewayRefundInterface.php`
- `backend/app/Models/ReturnRequest.php`
- `backend/app/Repositories/ReturnRequestRepository.php`
- `backend/app/Http/Requests/StoreReturnRequestRequest.php`
- `backend/app/Http/Requests/UpdateReturnRequestStatusRequest.php`
- `backend/app/Services/ManualRefundService.php`
- `backend/app/Services/RefundService.php`
- `backend/app/Services/ReturnRequestService.php`
- `backend/app/Http/Controllers/Api/ReturnRequestController.php`
- `backend/config/orders.php`

### File đã sửa
- `backend/app/Models/Order.php`
- `backend/app/Models/User.php`
- `backend/app/Repositories/OrderRepository.php`
- `backend/app/Repositories/AdminOrderRepository.php`
- `backend/app/Repositories/StatisticsRepository.php`
- `backend/app/Services/OrderService.php`
- `backend/app/Services/AdminOrderService.php`
- `backend/app/Services/AffiliateService.php`
- `backend/app/Services/StatisticsService.php`
- `backend/app/Console/Commands/CancelExpiredVnpayOrders.php`
- `backend/app/Exports/LastMonthRevenueExport.php`
- `backend/routes/api.php`

## 6. File frontend đã tạo/sửa

### File tạo mới
- `frontend/src/utils/orderStatus.js`
- `frontend/src/services/returnRequestService.js`
- `frontend/src/stores/returnRequestStore.js`
- `frontend/src/Pages/Client/Profile/ProfileReturnRequests.vue`
- `frontend/src/Pages/Client/Profile/ProfileReturnRequestDetail.vue`
- `frontend/src/Pages/admin/AdminReturnRequests.vue`
- `frontend/src/Pages/admin/AdminReturnRequestDetail.vue`

### File đã sửa
- `frontend/src/Pages/Client/Profile/ProfileOrderDetail.vue`
- `frontend/src/Pages/Client/Profile/ProfileOrders.vue`
- `frontend/src/Pages/admin/AdminOrder.vue`
- `frontend/src/Pages/admin/AdminOrderDetail.vue`
- `frontend/src/components/ProfileAside.vue`
- `frontend/src/components/AdminAside.vue`
- `frontend/src/router/index.js`

## 7. API đã thêm

### User API
- `POST /api/orders/{order}/return-request`
- `GET /api/my/return-requests`
- `GET /api/my/return-requests/{id}`

### Admin API
- `GET /api/admin/return-requests`
- `GET /api/admin/return-requests/{id}`
- `PATCH /api/admin/return-requests/{id}/approve`
- `PATCH /api/admin/return-requests/{id}/reject`
- `PATCH /api/admin/return-requests/{id}/received`
- `PATCH /api/admin/return-requests/{id}/refund`

## 8. Cách test user yêu cầu hoàn hàng

1. Chạy migration mới.
2. Đăng nhập bằng user có đơn ở `completed` hoặc `delivered`.
3. Vào `Hồ sơ -> Đơn hàng -> Chi tiết đơn`.
4. Bấm `Yêu cầu hoàn hàng`.
5. Chọn lý do, nhập mô tả, upload ảnh nếu cần.
6. Gửi form.
7. Kiểm tra:
- API trả `success`
- `order.fulfillment_status = return_requested`
- Có bản ghi mới trong `return_requests`
- `order_status_histories` có log mới
- Trang `Hồ sơ -> Yêu cầu hoàn hàng` hiển thị đúng dữ liệu

## 9. Cách test admin duyệt hoàn hàng

1. Đăng nhập admin.
2. Vào `Admin -> Hoàn hàng`.
3. Mở một yêu cầu đang `pending`.
4. Thử `Duyệt yêu cầu`.
5. Kiểm tra:
- `return_requests.status = approved`
- `orders.fulfillment_status = return_approved`
- Nếu đơn đã thanh toán thì `orders.payment_status = refund_pending`

### Test từ chối
1. Với yêu cầu `pending`, nhập `ghi chú admin`.
2. Bấm `Từ chối yêu cầu`.
3. Kiểm tra:
- `return_requests.status = rejected`
- `orders.fulfillment_status = completed`

## 10. Cách test admin hoàn tiền

1. Duyệt yêu cầu hoàn hàng.
2. Bấm `Xác nhận đã nhận hàng hoàn`.
3. Kiểm tra `return_requests.status = received`, `orders.fulfillment_status = returned`.
4. Nhập `refund_amount`, `refund_method`, `admin_note`.
5. Bấm `Xác nhận hoàn tiền`.
6. Kiểm tra:
- `return_requests.status = refunded`
- `return_requests.refund_status = success`
- `orders.fulfillment_status = refunded`
- `orders.payment_status = refunded`
- Nếu có bản ghi trong `payments` thì `payments.status = refunded`

## 11. Kiểm tra xác minh đã chạy

### Đã chạy thành công
- `php -l` cho toàn bộ file backend mới/sửa liên quan
- `php artisan route:list | Select-String 'return-request'`
- Parse Vue SFC bằng `@vue/compiler-sfc` cho các file `.vue` mới/sửa

### Chưa chạy được đầy đủ
- `npm run build` bị chặn bởi Windows sandbox `spawn EPERM`
- Đã xin quyền build frontend nhưng chưa được cấp nên chưa có production build thực tế

## 12. Lưu ý nếu sau này tích hợp auto refund VNPay/Momo/Stripe

- Hiện tại mới triển khai `manual refund` an toàn.
- Đã để sẵn khung:
  - `PaymentGatewayRefundInterface`
  - `RefundService`
  - `ManualRefundService`
- Khi tích hợp gateway thật cần bổ sung:
  - Transaction/refund reference riêng
  - Mapping lỗi refund theo gateway
  - Retry / idempotency key
  - Webhook/IPN hoàn tiền
  - Đồng bộ `payments.gateway_response`
  - Quy tắc partial refund nếu cần

## 13. Điểm cần lưu ý thêm

- `packing` và `delivered` vẫn được giữ để không làm mất dữ liệu cũ.
- Các trang admin order cũ đã được cập nhật để không vỡ khi đơn chuyển sang trạng thái hoàn hàng.
- Trang hoàn hàng admin riêng là luồng chính để xử lý approve/reject/received/refund.
