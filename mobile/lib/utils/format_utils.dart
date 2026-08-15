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
    final st = status.toUpperCase();
    if (st.contains('PENDING') || st.contains('PROCESSING')) return 'CHỜ XỬ LÝ';
    if (st.contains('SHIP') || st.contains('DELIVERING')) return 'ĐANG GIAO';
    if (st.contains('COMPLETED') || st.contains('DELIVERED') || st.contains('SUCCESS')) return 'HOÀN THÀNH';
    if (st.contains('CANCEL') || st.contains('FAIL')) return 'ĐÃ HỦY';
    return status;
  }
}
