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
}
