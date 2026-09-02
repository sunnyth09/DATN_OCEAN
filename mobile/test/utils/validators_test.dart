import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/utils/validators.dart';

void main() {
  group('Validators.required', () {
    test('returns null for non-empty value', () {
      expect(Validators.required('Hello'), isNull);
    });

    test('returns error for null', () {
      expect(Validators.required(null), isNotNull);
    });

    test('returns error for empty string', () {
      expect(Validators.required(''), isNotNull);
    });

    test('returns error for whitespace-only string', () {
      expect(Validators.required('   '), isNotNull);
    });

    test('uses custom field name in error', () {
      expect(Validators.required('', 'Email'), 'Email không được để trống');
    });
  });

  group('Validators.email', () {
    test('accepts valid email', () {
      expect(Validators.email('test@example.com'), isNull);
    });

    test('accepts email with dots in local part', () {
      expect(Validators.email('first.last@example.com'), isNull);
    });

    test('accepts email with subdomain', () {
      expect(Validators.email('user@sub.domain.com'), isNull);
    });

    test('rejects null', () {
      expect(Validators.email(null), isNotNull);
    });

    test('rejects empty string', () {
      expect(Validators.email(''), isNotNull);
    });

    test('rejects missing @', () {
      expect(Validators.email('testexample.com'), isNotNull);
    });

    test('rejects missing domain', () {
      expect(Validators.email('test@'), isNotNull);
    });

    test('rejects missing TLD', () {
      expect(Validators.email('test@example'), isNotNull);
    });

    test('accepts email with + sign', () {
      expect(Validators.email('test+tag@example.com'), isNull);
    });
  });

  group('Validators.password', () {
    test('accepts valid password', () {
      expect(Validators.password('abc123'), isNull);
    });

    test('rejects null', () {
      expect(Validators.password(null), isNotNull);
    });

    test('rejects empty', () {
      expect(Validators.password(''), isNotNull);
    });

    test('rejects too short (default min 6)', () {
      expect(Validators.password('abc12'), isNotNull);
    });

    test('respects custom minLength', () {
      expect(Validators.password('abcde', minLength: 8), isNotNull);
      expect(Validators.password('abcdefgh', minLength: 8), isNull);
    });
  });

  group('Validators.confirmPassword', () {
    test('returns null when passwords match', () {
      expect(Validators.confirmPassword('abc123', 'abc123'), isNull);
    });

    test('returns error when passwords do not match', () {
      expect(Validators.confirmPassword('abc123', 'xyz789'), isNotNull);
    });

    test('returns error for null', () {
      expect(Validators.confirmPassword(null, 'abc123'), isNotNull);
    });

    test('returns error for empty', () {
      expect(Validators.confirmPassword('', 'abc123'), isNotNull);
    });
  });

  group('Validators.phone', () {
    test('accepts valid VN phone (0xxx)', () {
      expect(Validators.phone('0912345678'), isNull);
    });

    test('accepts valid VN phone (+84xxx)', () {
      expect(Validators.phone('+84912345678'), isNull);
    });

    test('accepts phone with spaces/dashes', () {
      expect(Validators.phone('091-234-5678'), isNull);
      expect(Validators.phone('091 234 5678'), isNull);
    });

    test('rejects null', () {
      expect(Validators.phone(null), isNotNull);
    });

    test('rejects empty', () {
      expect(Validators.phone(''), isNotNull);
    });

    test('rejects too short', () {
      expect(Validators.phone('0912'), isNotNull);
    });

    test('rejects non-numeric', () {
      expect(Validators.phone('abcdefghij'), isNotNull);
    });
  });

  group('Validators.minLength', () {
    test('accepts valid length', () {
      expect(Validators.minLength('Hello World', 5), isNull);
    });

    test('rejects too short', () {
      expect(Validators.minLength('Hi', 5), isNotNull);
    });

    test('rejects null', () {
      expect(Validators.minLength(null, 1), isNotNull);
    });
  });

  group('Validators.maxLength', () {
    test('accepts valid length', () {
      expect(Validators.maxLength('Hello', 10), isNull);
    });

    test('rejects too long', () {
      expect(Validators.maxLength('Hello World!', 5), isNotNull);
    });

    test('accepts null', () {
      expect(Validators.maxLength(null, 10), isNull);
    });
  });

  group('Validators.positiveNumber', () {
    test('accepts positive integer', () {
      expect(Validators.positiveNumber('42'), isNull);
    });

    test('accepts positive decimal', () {
      expect(Validators.positiveNumber('3.14'), isNull);
    });

    test('rejects zero', () {
      expect(Validators.positiveNumber('0'), isNotNull);
    });

    test('rejects negative', () {
      expect(Validators.positiveNumber('-5'), isNotNull);
    });

    test('rejects non-numeric', () {
      expect(Validators.positiveNumber('abc'), isNotNull);
    });

    test('rejects null', () {
      expect(Validators.positiveNumber(null), isNotNull);
    });

    test('rejects empty', () {
      expect(Validators.positiveNumber(''), isNotNull);
    });
  });
}
