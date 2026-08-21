export const ORDER_STATUS_LABELS = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  processing: 'Đang xử lý',
  packing: 'Đang xử lý',
  awaiting_pickup: 'Chờ lấy hàng',
  shipping: 'Đang giao hàng',
  delivered: 'Đã giao hàng',
  completed: 'Hoàn thành',
  cancelled: 'Đã hủy',
  return_requested: 'Yêu cầu hoàn hàng',
  return_approved: 'Đã duyệt hoàn hàng',
  return_rejected: 'Từ chối hoàn hàng',
  returning: 'Khách đang gửi hàng hoàn',
  warehouse_received: 'Kho đã nhận hàng hoàn',
  inspection_failed: 'Hoàn hàng không đạt QC',
  inspected_ok: 'Hoàn hàng đạt QC',
  returned: 'Đã nhận hàng hoàn',
  refunded: 'Đã hoàn tiền',
};

export const ORDER_STATUS_DESCRIPTIONS = {
  pending: 'Đơn hàng đang chờ xác nhận',
  confirmed: 'Đơn hàng đã được xác nhận',
  processing: 'Đơn hàng đang được xử lý',
  packing: 'Đơn hàng đang được xử lý',
  awaiting_pickup: 'Đơn hàng đang chờ bên vận chuyển tiếp nhận',
  shipping: 'Đơn hàng đang được giao đến bạn',
  delivered: 'Đơn hàng đã được giao thành công',
  completed: 'Đơn hàng đã hoàn thành',
  cancelled: 'Đơn hàng đã bị hủy',
  return_requested: 'Bạn đã gửi yêu cầu hoàn hàng',
  return_approved: 'Yêu cầu hoàn hàng đã được duyệt',
  return_rejected: 'Yêu cầu hoàn hàng đã bị từ chối',
  returning: 'Khách đang gửi hàng hoàn về kho',
  warehouse_received: 'Kho đã nhận hàng hoàn và chờ QC',
  inspection_failed: 'Hàng hoàn không đạt kiểm tra chất lượng',
  inspected_ok: 'Hàng hoàn đã đạt kiểm tra chất lượng',
  returned: 'Shop đã nhận lại hàng hoàn',
  refunded: 'Đơn hàng đã được hoàn tiền',
};

export const ORDER_STATUS_SUMMARY_LABELS = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đang xử lý',
  processing: 'Đang xử lý',
  packing: 'Đang xử lý',
  awaiting_pickup: 'Chờ lấy hàng',
  shipping: 'Đang giao',
  delivered: 'Đã giao',
  completed: 'Hoàn thành',
  cancelled: 'Đã hủy',
  return_requested: 'Chờ duyệt hoàn',
  return_approved: 'Đã duyệt hoàn',
  return_rejected: 'Từ chối hoàn',
  returning: 'Đang gửi hoàn',
  warehouse_received: 'Kho đã nhận',
  inspection_failed: 'QC fail',
  inspected_ok: 'QC pass',
  returned: 'Đã nhận hàng hoàn',
  refunded: 'Đã hoàn tiền',
};

export const PAYMENT_STATUS_LABELS = {
  unpaid: 'Chưa thanh toán',
  paid: 'Đã thanh toán',
  failed: 'Thanh toán thất bại',
  refund_pending: 'Chờ hoàn tiền',
  refunded: 'Đã hoàn tiền',
  refund_failed: 'Hoàn tiền thất bại',
  partially_refunded: 'Hoàn một phần',
};

export const RETURN_REQUEST_STATUS_LABELS = {
  pending: 'Chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Đã từ chối',
  received: 'Đã nhận hàng hoàn',
  refunded: 'Đã hoàn tiền',
  return_pending: 'Chờ duyệt',
  return_approved: 'Đã duyệt',
  return_rejected: 'Đã từ chối',
  returning: 'Khách đang gửi hàng',
  warehouse_received: 'Kho đã nhận hàng',
  inspection_failed: 'QC không đạt',
  inspected_ok: 'QC đạt',
  refunding: 'Đang hoàn tiền',
  refund_pending: 'Chờ hoàn tiền',
  refund_failed: 'Hoàn tiền thất bại',
  return_completed: 'Hoàn tất',
};

export const RETURN_REFUND_STATUS_LABELS = {
  none: 'Chưa hoàn tiền',
  pending: 'Chờ hoàn tiền',
  success: 'Đã hoàn tiền',
  failed: 'Hoàn tiền thất bại',
};

export const RETURN_REASON_OPTIONS = [
  'Sản phẩm bị lỗi / hư hỏng',
  'Sản phẩm không đúng mô tả',
  'Giao sai sản phẩm / sai biến thể',
  'Sản phẩm không còn nhu cầu sử dụng',
  'Kích thước / màu sắc không phù hợp',
  'Lý do khác',
];

export const RETURN_REQUEST_ADMIN_STATUS_OPTIONS = [
  { value: 'all', label: 'Tất cả' },
  { value: 'return_pending', label: 'Chờ duyệt' },
  { value: 'return_approved', label: 'Đã duyệt' },
  { value: 'returning', label: 'Đang gửi hàng' },
  { value: 'warehouse_received', label: 'Kho đã nhận' },
  { value: 'inspection_failed', label: 'QC fail' },
  { value: 'inspected_ok', label: 'QC pass' },
  { value: 'refunding', label: 'Đang hoàn tiền' },
  { value: 'refund_pending', label: 'Chờ hoàn tiền' },
  { value: 'refund_failed', label: 'Hoàn tiền lỗi' },
  { value: 'return_completed', label: 'Hoàn tất' },
  { value: 'return_rejected', label: 'Đã từ chối' },
];

export const RETURN_REQUEST_CUSTOMER_STATUS_OPTIONS = [
  { value: 'all', label: 'Tất cả' },
  { value: 'return_pending', label: 'Chờ duyệt' },
  { value: 'return_approved', label: 'Đã duyệt' },
  { value: 'returning', label: 'Đang gửi hàng' },
  { value: 'warehouse_received', label: 'Kho đã nhận' },
  { value: 'inspected_ok', label: 'QC đạt' },
  { value: 'refund_pending', label: 'Chờ hoàn tiền' },
  { value: 'return_completed', label: 'Hoàn tất' },
  { value: 'return_rejected', label: 'Đã từ chối' },
  { value: 'inspection_failed', label: 'QC không đạt' },
  { value: 'refund_failed', label: 'Hoàn tiền lỗi' },
];

export const RETURN_REQUEST_REFUND_METHOD_OPTIONS = [
  { value: 'wallet', label: 'Hoàn vào ví' },
  { value: 'bank_transfer', label: 'Chuyển khoản' },
  { value: 'cash', label: 'Tiền mặt' },
  { value: 'vnpay', label: 'VNPay' },
  { value: 'other', label: 'Khác' },
];

export const RETURN_SHIPPING_METHOD_OPTIONS = [
  { value: 'pickup_original_address', label: 'Đơn vị vận chuyển đến lấy hàng' },
  { value: 'dropoff_post_office', label: 'Tôi tự gửi tại bưu cục' },
];

export const getOrderStatusLabel = (status) => ORDER_STATUS_LABELS[status] || status || 'Đang xử lý';
export const getOrderStatusDescription = (status) => ORDER_STATUS_DESCRIPTIONS[status] || getOrderStatusLabel(status);
export const getOrderStatusSummaryLabel = (status) => ORDER_STATUS_SUMMARY_LABELS[status] || getOrderStatusLabel(status);
export const getPaymentStatusLabel = (status) => PAYMENT_STATUS_LABELS[status] || status || 'Chưa rõ';
export const getReturnRequestStatusLabel = (status) => RETURN_REQUEST_STATUS_LABELS[status] || status || 'Chưa rõ';
export const getReturnRefundStatusLabel = (status) => RETURN_REFUND_STATUS_LABELS[status] || status || 'Chưa rõ';
export const getRefundMethodLabel = (method) => RETURN_REQUEST_REFUND_METHOD_OPTIONS.find((item) => item.value === method)?.label || method || 'Chưa chọn';
export const getReturnShippingMethodLabel = (method) => RETURN_SHIPPING_METHOD_OPTIONS.find((item) => item.value === method)?.label || method || 'Chưa chọn';

export const getOrderStatusTone = (status) => {
  if (['pending', 'confirmed', 'processing', 'packing', 'awaiting_pickup'].includes(status)) return 'status-info';
  if (['shipping', 'return_requested', 'return_approved', 'returning', 'warehouse_received', 'inspected_ok'].includes(status)) return 'status-warning';
  if (['delivered', 'completed', 'refunded'].includes(status)) return 'status-success';
  if (['cancelled', 'return_rejected', 'returned', 'inspection_failed'].includes(status)) return 'status-danger';
  return 'status-default';
};

export const getReturnRequestStatusTone = (status) => {
  if (['pending', 'return_pending'].includes(status)) return 'status-info';
  if (['approved', 'received', 'return_approved', 'returning', 'warehouse_received', 'inspected_ok', 'refunding', 'refund_pending'].includes(status)) return 'status-warning';
  if (['refunded', 'return_completed'].includes(status)) return 'status-success';
  if (['rejected', 'return_rejected', 'inspection_failed', 'refund_failed'].includes(status)) return 'status-danger';
  return 'status-default';
};
