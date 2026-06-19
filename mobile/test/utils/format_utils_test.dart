import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/utils/format_utils.dart';

void main() {
  group('FormatUtils.formatPrice', () {
    test('formats a large number with separators', () {
      expect(FormatUtils.formatPrice(1000000), '1.000.000 đ');
    });

    test('returns the original value when parsing fails', () {
      expect(FormatUtils.formatPrice('abc'), 'abc');
    });
  });
}
