import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/user_model.dart';

void main() {
  group('User', () {
    test('fromJson parses all fields correctly', () {
      final json = {
        'id': 42,
        'full_name': 'Nguyễn Văn A',
        'email': 'nguyen@example.com',
        'phone': '0912345678',
        'avatar_url': '/avatars/42.jpg',
        'role': 'customer',
      };

      final user = User.fromJson(json);

      expect(user.id, 42);
      expect(user.fullName, 'Nguyễn Văn A');
      expect(user.email, 'nguyen@example.com');
      expect(user.phone, '0912345678');
      expect(user.avatarUrl, '/avatars/42.jpg');
      expect(user.role, 'customer');
    });

    test('fromJson handles alternative field names', () {
      final json = {
        'user_id': 7,
        'name': 'Trần Thị B',
        'email': 'tran@test.com',
        'avatar': '/avatars/7.jpg',
      };

      final user = User.fromJson(json);

      expect(user.id, 7);
      expect(user.fullName, 'Trần Thị B');
      expect(user.avatarUrl, '/avatars/7.jpg');
    });

    test('fromJson handles null/empty values', () {
      final user = User.fromJson({});

      expect(user.id, isNull);
      expect(user.fullName, '');
      expect(user.email, '');
      expect(user.phone, isNull);
      expect(user.avatarUrl, isNull);
      expect(user.role, isNull);
    });

    test('fromJson handles string numeric id', () {
      final user = User.fromJson({'id': '123'});
      expect(user.id, 123);
    });

    test('toJson includes all fields', () {
      final user = User.fromJson({
        'id': 1,
        'full_name': 'Test User',
        'email': 'test@example.com',
        'phone': '0909090909',
        'role': 'staff',
      });

      final json = user.toJson();

      expect(json['id'], 1);
      expect(json['full_name'], 'Test User');
      expect(json['name'], 'Test User'); // cả 2 key đều có
      expect(json['email'], 'test@example.com');
      expect(json['phone'], '0909090909');
      expect(json['role'], 'staff');
    });

    test('toJson preserves raw data', () {
      final json = {
        'id': 1,
        'full_name': 'Test',
        'email': 'test@test.com',
        'extra_field': 'extra_value',
        'loyalty_points': 1500,
      };

      final user = User.fromJson(json);
      final output = user.toJson();

      expect(output['extra_field'], 'extra_value');
      expect(output['loyalty_points'], 1500);
    });

    test('const constructor defaults work', () {
      const user = User();

      expect(user.id, isNull);
      expect(user.fullName, '');
      expect(user.email, '');
      expect(user.raw, isEmpty);
    });
  });
}
