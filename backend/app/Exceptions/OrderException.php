<?php

namespace App\Exceptions;

use Exception;

/**
 * OrderException — Lỗi nghiệp vụ khi tạo/xử lý đơn hàng.
 *
 * Dùng cho các lỗi mà thông điệp AN TOÀN để hiển thị cho người dùng
 * (hết hàng, địa chỉ không hợp lệ, coupon sai...). OrderService bắt
 * exception này và trả về HTTP 400 kèm message gốc; các lỗi khác
 * (QueryException, lỗi hệ thống) trả về 500 với message chung chung
 * để tránh lộ thông tin nội bộ.
 */
class OrderException extends Exception {}
