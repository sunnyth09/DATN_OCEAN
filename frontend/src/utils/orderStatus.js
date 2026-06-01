export const ORDER_STATUS_LABELS = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  processing: 'Đang xử lý',
  packing: 'Đang xử lý',
  shipping: 'Đang giao hàng',
  delivered: 'Đã giao hàng',
  completed: 'Hoàn thành',
  cancelled: 'Đã hủy',
  return_requested: 'Yêu cầu hoàn hàng',
  return_approved: 'Đã duyệt hoàn hàng',
  return_rejected: 'Từ chối hoàn hàng',
  returned: 'Đã nhận hàng hoàn',
  refunded: 'Đã hoàn tiền',
};

export const ORDER_STATUS_DESCRIPTIONS = {
  pending: 'Đơn hàng đang chờ xác nhận',
  confirmed: 'Đơn hàng đã được xác nhận',
  processing: 'Đơn hàng đang được xử lý',
  packing: 'Đơn hàng đang được xử lý',
  shipping: 'Đơn hàng đang được giao đến bạn',
  delivered: 'Đơn hàng đã được giao thành công',
  completed: 'Đơn hàng đã hoàn thành',
  cancelled: 'Đơn hàng đã bị hủy',
  return_requested: 'Bạn đã gửi yêu cầu hoàn hàng',
  return_approved: 'Yêu cầu hoàn hàng đã được duyệt',
  return_rejected: 'Yêu cầu hoàn hàng đã bị từ chối',
  returned: 'Shop đã nhận lại hàng hoàn',
  refunded: 'Đơn hàng đã được hoàn tiền',
};

export const ORDER_STATUS_SUMMARY_LABELS = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đang xử lý',
  processing: 'Đang xử lý',
  packing: 'Đang xử lý',
  shipping: 'Đang giao',
  delivered: 'Đã giao',
  completed: 'Hoàn thành',
  cancelled: 'Đã hủy',
  return_requested: 'Chờ duyệt hoàn',
  return_approved: 'Đã duyệt hoàn',
  return_rejected: 'Từ chối hoàn',
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
  { value: 'pending', label: 'Chờ duyệt' },
  { value: 'approved', label: 'Đã duyệt' },
  { value: 'rejected', label: 'Đã từ chối' },
  { value: 'received', label: 'Đã nhận hàng hoàn' },
  { value: 'refunded', label: 'Đã hoàn tiền' },
];

export const RETURN_REQUEST_REFUND_METHOD_OPTIONS = [
  { value: 'bank_transfer', label: 'Chuyển khoản' },
  { value: 'cash', label: 'Tiền mặt' },
  { value: 'vnpay', label: 'VNPay' },
  { value: 'momo', label: 'MoMo' },
  { value: 'other', label: 'Khác' },
];

export const getOrderStatusLabel = (status) => ORDER_STATUS_LABELS[status] || status || 'Đang xử lý';
export const getOrderStatusDescription = (status) => ORDER_STATUS_DESCRIPTIONS[status] || getOrderStatusLabel(status);
export const getOrderStatusSummaryLabel = (status) => ORDER_STATUS_SUMMARY_LABELS[status] || getOrderStatusLabel(status);
export const getPaymentStatusLabel = (status) => PAYMENT_STATUS_LABELS[status] || status || 'Chưa rõ';
export const getReturnRequestStatusLabel = (status) => RETURN_REQUEST_STATUS_LABELS[status] || status || 'Chưa rõ';
export const getReturnRefundStatusLabel = (status) => RETURN_REFUND_STATUS_LABELS[status] || status || 'Chưa rõ';

export const getOrderStatusTone = (status) => {
  if (['pending', 'confirmed', 'processing', 'packing'].includes(status)) return 'status-info';
  if (['shipping', 'return_requested', 'return_approved'].includes(status)) return 'status-warning';
  if (['delivered', 'completed', 'refunded'].includes(status)) return 'status-success';
  if (['cancelled', 'return_rejected', 'returned'].includes(status)) return 'status-danger';
  return 'status-default';
};

export const getReturnRequestStatusTone = (status) => {
  if (status === 'pending') return 'status-info';
  if (status === 'approved' || status === 'received') return 'status-warning';
  if (status === 'refunded') return 'status-success';
  if (status === 'rejected') return 'status-danger';
  return 'status-default';
};
