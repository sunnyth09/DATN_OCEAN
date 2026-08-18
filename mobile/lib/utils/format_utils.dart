import 'package:intl/intl.dart';

class FormatUtils {
  FormatUtils._();

  /// Parse bất kỳ kiểu dữ liệu nào (num, String "149000.00", null) thành kiểu số `num` an toàn tuyệt đối.
  static num parseNum(dynamic value) {
    if (value == null) return 0;
    if (value is num) return value;
    final cleaned = value.toString().replaceAll(RegExp(r'[^0-9.]'), '');
    return num.tryParse(cleaned) ?? 0;
  }

  /// Định dạng ngày về dd/MM/yyyy (nhận ISO string hoặc DateTime).
  static String formatDate(dynamic value, {bool withTime = false}) {
    if (value == null) return '';
    final d = value is DateTime ? value : DateTime.tryParse(value.toString());
    if (d == null) return '';
    final pattern = withTime ? 'dd/MM/yyyy HH:mm' : 'dd/MM/yyyy';
    return DateFormat(pattern).format(d);
  }

  static String formatPrice(dynamic price) {
    try {
      final value = parseNum(price);
      final formatted = value
          .toStringAsFixed(0)
          .replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (match) => '${match[1]}.',
          );
      return '$formatted đ';
    } catch (_) {
      return '$price đ';
    }
  }

  static String translateStatus(String status) {
    final st = status.toUpperCase().trim();
    if (st == 'PENDING' || st.contains('UNPAID') || st.contains('WAITING')) {
      return 'Chờ xác nhận';
    }
    if (st.contains('CONFIRMED') || st.contains('PROCESSING') || st.contains('PACKING') || st.contains('READY') || st.contains('PICKUP')) {
      return 'Chờ lấy hàng';
    }
    if (st.contains('SHIP') || st.contains('DELIVERING') || st.contains('TRANSIT')) {
      return 'Đang giao hàng';
    }
    if (st.contains('COMPLETED') || st.contains('DELIVERED') || st.contains('SUCCESS')) {
      return 'Hoàn thành';
    }
    if (st.contains('RETURN') || st.contains('REFUND')) {
      return 'Trả hàng / Hoàn tiền';
    }
    if (st.contains('CANCEL') || st.contains('FAIL')) {
      return 'Đã hủy';
    }
    return status;
  }

  /// Loại bỏ toàn bộ thẻ HTML (<p>, <br>, <div>, &nbsp;...) và giải mã ký tự đặc biệt
  static String stripHtml(dynamic raw) {
    if (raw == null) return '';
    return raw
        .toString()
        .replaceAll(RegExp(r'<[^>]*>'), '')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&amp;', '&')
        .replaceAll('&quot;', '"')
        .replaceAll('&#39;', "'")
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .trim();
  }
}
