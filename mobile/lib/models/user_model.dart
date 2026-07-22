/// Model người dùng đăng nhập.
class User {
  final int? id;
  final String fullName;
  final String email;
  final String? phone;
  final String? avatarUrl;
  final String? role;
  final Map<String, dynamic> raw;

  const User({
    this.id,
    this.fullName = '',
    this.email = '',
    this.phone,
    this.avatarUrl,
    this.role,
    this.raw = const {},
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: _toInt(json['id'] ?? json['user_id']),
      fullName: (json['full_name'] ?? json['name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      phone: json['phone']?.toString(),
      avatarUrl: (json['avatar_url'] ?? json['avatar'])?.toString(),
      role: json['role']?.toString(),
      raw: json,
    );
  }
}

int? _toInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  return int.tryParse(value.toString());
}
