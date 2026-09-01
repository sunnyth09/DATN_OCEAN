import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/utils/format_utils.dart';

void main() {
  group('FormatUtils.parseNum', () {
    test('handles null', () {
      expect(FormatUtils.parseNum(null), 0);
    });

    test('handles int', () {
      expect(FormatUtils.parseNum(42), 42);
    });

    test('handles double', () {
      expect(FormatUtils.parseNum(3.14), 3.14);
    });

    test('handles string number', () {
      expect(FormatUtils.parseNum('149000'), 149000);
    });

    test('handles string decimal', () {
      expect(FormatUtils.parseNum('149000.50'), 149000.5);
    });

    test('handles string with currency symbols', () {
      expect(FormatUtils.parseNum('149.000 đ'), 149.0);
    });

    test('handles empty string', () {
      expect(FormatUtils.parseNum(''), 0);
    });

    test('handles non-numeric string', () {
      expect(FormatUtils.parseNum('abc'), 0);
    });
  });

  group('FormatUtils.formatPrice', () {
    test('formats standard price', () {
      expect(FormatUtils.formatPrice(149000), '149.000 đ');
    });

    test('formats large price', () {
      expect(FormatUtils.formatPrice(1500000), '1.500.000 đ');
    });

    test('formats zero', () {
      expect(FormatUtils.formatPrice(0), '0 đ');
    });

    test('formats string number', () {
      expect(FormatUtils.formatPrice('850000'), '850.000 đ');
    });

    test('formats decimal price (rounds to int)', () {
      expect(FormatUtils.formatPrice(149000.99), '149.001 đ');
    });

    test('handles null', () {
      expect(FormatUtils.formatPrice(null), '0 đ');
    });
  });

  group('FormatUtils.formatDate', () {
    test('formats ISO string', () {
      expect(FormatUtils.formatDate('2026-08-31T10:30:00'), '31/08/2026');
    });

    test('formats ISO string with time', () {
      expect(
        FormatUtils.formatDate('2026-08-31T10:30:00', withTime: true),
        '31/08/2026 10:30',
      );
    });

    test('formats DateTime object', () {
      expect(
        FormatUtils.formatDate(DateTime(2026, 1, 15)),
        '15/01/2026',
      );
    });

    test('handles null', () {
      expect(FormatUtils.formatDate(null), '');
    });

    test('handles invalid date string', () {
      expect(FormatUtils.formatDate('not-a-date'), '');
    });
  });

  group('FormatUtils.translateStatus', () {
    test('translates PENDING', () {
      expect(FormatUtils.translateStatus('PENDING'), 'Chờ xác nhận');
    });

    test('translates DELIVERING', () {
      expect(FormatUtils.translateStatus('DELIVERING'), 'Đang giao hàng');
    });

    test('translates COMPLETED', () {
      expect(FormatUtils.translateStatus('COMPLETED'), 'Hoàn thành');
    });

    test('translates CANCELLED', () {
      expect(FormatUtils.translateStatus('CANCELLED'), 'Đã hủy');
    });

    test('translates RETURN_REQUEST', () {
      expect(FormatUtils.translateStatus('RETURN_REQUEST'), 'Trả hàng / Hoàn tiền');
    });

    test('translates CONFIRMED', () {
      expect(FormatUtils.translateStatus('CONFIRMED'), 'Chờ lấy hàng');
    });

    test('returns original for unknown status', () {
      expect(FormatUtils.translateStatus('CUSTOM_STATUS'), 'CUSTOM_STATUS');
    });

    test('is case insensitive', () {
      expect(FormatUtils.translateStatus('pending'), 'Chờ xác nhận');
      expect(FormatUtils.translateStatus('Completed'), 'Hoàn thành');
    });
  });

  group('FormatUtils.stripHtml', () {
    test('strips HTML tags', () {
      expect(
        FormatUtils.stripHtml('<p>Hello <b>World</b></p>'),
        'Hello World',
      );
    });

    test('decodes HTML entities', () {
      expect(FormatUtils.stripHtml('A &amp; B'), 'A & B');
      expect(FormatUtils.stripHtml('&lt;script&gt;'), '<script>');
      expect(FormatUtils.stripHtml('&quot;quoted&quot;'), '"quoted"');
    });

    test('replaces nbsp', () {
      expect(FormatUtils.stripHtml('Hello&nbsp;World'), 'Hello World');
    });

    test('handles null', () {
      expect(FormatUtils.stripHtml(null), '');
    });

    test('trims whitespace', () {
      expect(FormatUtils.stripHtml('  hello  '), 'hello');
    });
  });
}
