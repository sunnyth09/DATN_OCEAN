class FormatUtils {
  FormatUtils._();

  static String formatPrice(dynamic price) {
    try {
      final value = num.parse(price.toString());
      final formatted = value
          .toStringAsFixed(0)
          .replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (match) => '${match[1]}.',
          );
      return '$formatted đ';
    } catch (_) {
      return price.toString();
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
