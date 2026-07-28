# Workflow Admin — Hoàn hàng / Hoàn tiền

## 1. Tổng quan

Workflow hoàn hàng cho admin xử lý yêu cầu trả hàng từ khách sau khi đơn đã giao hoặc hoàn thành. Luồng mới hỗ trợ:

- Hoàn theo từng sản phẩm và số lượng.
- Kho xác nhận số lượng thực nhận.
- QC pass/fail theo từng sản phẩm.
- Chỉ hoàn tiền và cộng tồn kho cho số lượng QC đạt.
- Ghi nhận refund transaction để chống hoàn tiền trùng.

```text
return_pending
  ├── return_rejected
  └── return_approved
        └── returning
              └── warehouse_received
                    ├── inspection_failed
                    └── inspected_ok
                          └── refunding
                                ├── refund_pending
                                ├── refund_failed
                                └── return_completed
```

---

## 2. Khách gửi yêu cầu hoàn hàng

Khách gửi yêu cầu từ chi tiết đơn hàng.

Khách chọn:

- Sản phẩm cần hoàn.
- Số lượng hoàn.
- Lý do hoàn hàng.
- Phương thức hoàn tiền.
- Ảnh/video minh chứng.

Hệ thống tạo:

- `return_requests`
- `return_request_items`

Trạng thái ban đầu:

```text
return_pending
```

Ý nghĩa: yêu cầu đang chờ admin duyệt.

---

## 3. Admin duyệt hoặc từ chối

Admin vào:

```text
/admin/return-requests/{id}
```

### 3.1. Từ chối

Điều kiện:

```text
status = return_pending
```

Admin nhập ghi chú/lý do từ chối và bấm **Từ chối yêu cầu**.

Trạng thái chuyển:

```text
return_pending -> return_rejected
```

Hệ thống:

- Lưu `admin_note`.
- Lưu `reject_reason`.
- Không hoàn tiền.
- Không cộng tồn kho.
- Kết thúc yêu cầu.

### 3.2. Duyệt

Điều kiện:

```text
status = return_pending
```

Admin có thể nhập:

- Đơn vị vận chuyển hoàn.
- Mã vận đơn hoàn.
- Ghi chú admin.

Trạng thái chuyển:

```text
return_pending -> return_approved
```

Hệ thống:

- Lưu `return_carrier`.
- Lưu `return_tracking_code`.
- Lưu `approved_at`.
- Cập nhật trạng thái đơn hàng sang `return_approved`.
- Chưa hoàn tiền.
- Chưa cộng tồn kho.

---

## 4. Khách gửi hàng hoàn

Điều kiện:

```text
status = return_approved
```

Admin bấm **Chuyển sang khách đang gửi hàng**.

Trạng thái chuyển:

```text
return_approved -> returning
```

Hệ thống:

- Lưu hoặc cập nhật `return_carrier`.
- Lưu hoặc cập nhật `return_tracking_code`.
- Lưu `returning_at`.
- Cập nhật trạng thái đơn hàng sang `returning`.

Nếu shop/kho đã nhận trực tiếp, admin có thể bỏ qua bước này và xác nhận kho nhận ngay.

---

## 5. Kho nhận hàng hoàn

Điều kiện:

```text
status = return_approved hoặc returning
```

Admin nhập số lượng kho thực nhận cho từng sản phẩm.

Ví dụ:

| Sản phẩm | Khách yêu cầu | Kho nhận |
|---|---:|---:|
| Áo A | 2 | 2 |
| Quần B | 1 | 1 |

Trạng thái chuyển:

```text
returning -> warehouse_received
```

hoặc:

```text
return_approved -> warehouse_received
```

Hệ thống:

- Lưu `received_quantity` từng dòng hoàn hàng.
- Lưu `warehouse_received_at`.
- Cập nhật trạng thái đơn hàng sang `warehouse_received`.
- Chưa cộng tồn kho.
- Chưa hoàn tiền.

Lý do: hàng cần qua bước QC trước khi nhập lại tồn bán được.

---

## 6. QC hàng hoàn

Điều kiện:

```text
status = warehouse_received
```

Admin nhập cho từng sản phẩm:

- `qc_pass_quantity`
- `qc_fail_quantity`
- `qc_note`

### 6.1. Tất cả QC lỗi

Nếu tổng `qc_pass_quantity = 0`:

```text
warehouse_received -> inspection_failed
```

Hệ thống:

- Không hoàn tiền.
- Không cộng tồn kho.
- Cập nhật trạng thái đơn hàng sang `inspection_failed`.

### 6.2. Có sản phẩm QC đạt

Nếu tổng `qc_pass_quantity > 0`:

```text
warehouse_received -> inspected_ok
```

Hệ thống:

- Tính lại tiền hoàn dựa trên số lượng QC đạt.
- Cộng tồn kho cho số lượng QC đạt.
- Tăng `order_items.returned_quantity`.
- Ghi `inventory_transactions` loại `return`.
- Cập nhật trạng thái đơn hàng sang `inspected_ok`.

Ví dụ khách yêu cầu hoàn 2 sản phẩm nhưng QC đạt 1:

- Chỉ hoàn tiền 1 sản phẩm.
- Chỉ cộng tồn 1 sản phẩm.
- Sản phẩm QC lỗi không được hoàn tiền và không cộng tồn.

---

## 7. Hoàn tiền

Điều kiện:

```text
status = inspected_ok hoặc refund_pending hoặc refund_failed
```

Admin nhập/chọn:

- Số tiền hoàn.
- Phương thức hoàn tiền.
- Ghi chú hoàn tiền.

Khi bắt đầu xử lý:

```text
inspected_ok -> refunding
```

Hệ thống tạo hoặc reuse `refund_transactions` theo `idempotency_key`.

### 7.1. Hoàn tiền thành công

```text
refunding -> return_completed
```

Hệ thống:

- Cập nhật `refund_status = success`.
- Lưu `refunded_at`.
- Lưu `completed_at`.
- Cập nhật payment status:
  - `partially_refunded` nếu hoàn một phần.
  - `refunded` nếu hoàn đủ toàn bộ đơn.
- Chống hoàn tiền trùng bằng cách kiểm tra transaction `success`.

### 7.2. Hoàn tiền thất bại

```text
refunding -> refund_failed
```

Admin có thể retry:

```text
refund_failed -> refunding -> return_completed
```

### 7.3. Hoàn tiền pending / timeout

```text
refunding -> refund_pending
```

Admin có thể retry khi cần.

---

## 8. Quy tắc nghiệp vụ chính

1. Admin duyệt không đồng nghĩa với hoàn tiền.
2. Phải có kho nhận và QC đạt mới được hoàn tiền.
3. Chỉ QC đạt mới cộng tồn kho.
4. QC lỗi không cộng tồn và không hoàn tiền.
5. Hỗ trợ hoàn một phần theo từng sản phẩm.
6. Số lượng hoàn không vượt quá số lượng khách đã mua trừ đi số lượng đã hoàn/đang xử lý.
7. Refund transaction dùng để chống hoàn tiền trùng.
8. Nếu refund bằng ví, giao dịch ví tham chiếu theo refund transaction.

---

## 9. API admin chính

```text
GET   /api/admin/return-requests
GET   /api/admin/return-requests/{id}
PATCH /api/admin/return-requests/{id}/approve
PATCH /api/admin/return-requests/{id}/reject
PATCH /api/admin/return-requests/{id}/returning
PATCH /api/admin/return-requests/{id}/received
PATCH /api/admin/return-requests/{id}/inspect
PATCH /api/admin/return-requests/{id}/refund
```

---

## 10. Bảng dữ liệu liên quan

- `orders`
- `order_items`
- `return_requests`
- `return_request_items`
- `refund_transactions`
- `inventory_transactions`
- `wallet_transactions`
