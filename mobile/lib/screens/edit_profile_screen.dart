import 'dart:io';
import '../config/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_client.dart';
import '../config/app_config.dart';

class EditProfileScreen extends StatefulWidget {
  final Map<String, dynamic> userData;
  const EditProfileScreen({super.key, required this.userData});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  late TextEditingController _nameCtrl;
  late TextEditingController _phoneCtrl;
  bool _isSaving = false;
  XFile? _pickedImage;
  final _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: widget.userData['full_name'] ?? widget.userData['name'] ?? '');
    _phoneCtrl = TextEditingController(text: widget.userData['phone'] ?? '');
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    final picked = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 80, maxWidth: 800);
    if (picked != null) setState(() => _pickedImage = picked);
  }

  Future<void> _save() async {
    if (_nameCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng nhập họ tên!'), backgroundColor: Colors.orange));
      return;
    }
    setState(() => _isSaving = true);
    try {
      final token = await ApiClient().dio.options.headers['Authorization'];
      
      // Build FormData for multipart (avatar support)
      final formData = FormData.fromMap({
        'full_name': _nameCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
        if (_pickedImage != null)
          'avatar': await MultipartFile.fromFile(_pickedImage!.path, filename: 'avatar.jpg'),
      });

      final response = await ApiClient().dio.post(
        '/profile',
        data: formData,
        options: Options(contentType: 'multipart/form-data'),
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Cập nhật thành công!'), backgroundColor: Colors.green));
        Navigator.pop(context, response.data['data'] ?? response.data['user']);
      }
    } on DioException catch (e) {
      if (mounted) {
        final msg = e.response?.data?['message'] ?? 'Cập nhật thất bại!';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
      }
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kết nối!'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final avatar = widget.userData['avatar_url'];
    final avatarUrl = AppConfig.imageUrl(avatar?.toString());

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Chỉnh sửa hồ sơ', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFFE63B6F)),
        actions: [
          TextButton(
            onPressed: _isSaving ? null : _save,
            child: _isSaving
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFE63B6F)))
                : const Text('Lưu', style: TextStyle(color: Color(0xFFE63B6F), fontWeight: FontWeight.bold, fontSize: 16)),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Avatar picker
            Center(
              child: Stack(
                children: [
                  GestureDetector(
                    onTap: _pickImage,
                    child: Container(
                      width: 100, height: 100,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: const Color(0xFFE63B6F), width: 3),
                        boxShadow: [BoxShadow(color: const Color(0xFFE63B6F).withOpacity(0.2), blurRadius: 12)],
                      ),
                      child: ClipOval(
                        child: _pickedImage != null
                            ? Image.file(File(_pickedImage!.path), fit: BoxFit.cover)
                            : (avatarUrl.isNotEmpty
                                ? Image.network(avatarUrl, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _defaultAvatar())
                                : _defaultAvatar()),
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 0, right: 0,
                    child: GestureDetector(
                      onTap: _pickImage,
                      child: Container(
                        padding: const EdgeInsets.all(7),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE63B6F),
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                        child: const Icon(Icons.camera_alt, size: 14, color: Colors.white),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            const Text('Nhấn để đổi ảnh đại diện', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
            const SizedBox(height: 28),

            // Editable fields
            _buildCard(children: [
              _buildField(label: 'Họ và tên', ctrl: _nameCtrl, hint: 'Nhập họ tên của bạn', icon: Icons.person_outline),
              const Divider(color: Color(0xFFF1F5F9), height: 1),
              _buildField(label: 'Số điện thoại', ctrl: _phoneCtrl, hint: 'Nhập số điện thoại', icon: Icons.phone_outlined, type: TextInputType.phone),
            ]),
            const SizedBox(height: 16),

            // Read-only fields
            _buildCard(children: [
              _buildReadOnly(label: 'Email', value: widget.userData['email']?.toString() ?? '', icon: Icons.email_outlined),
              const Divider(color: Color(0xFFF1F5F9), height: 1),
              _buildReadOnly(label: 'Vai trò', value: _formatRole(widget.userData['role']), icon: Icons.badge_outlined),
            ]),

            const SizedBox(height: 32),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _isSaving ? null : _save,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFE63B6F),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isSaving
                    ? const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                        SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                        SizedBox(width: 10),
                        Text('Đang lưu...', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      ])
                    : const Text('Lưu thay đổi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatRole(dynamic role) {
    switch (role?.toString()) {
      case 'admin': return '👑 Quản trị viên';
      case 'seller': return '🏪 Nhân viên bán hàng';
      case 'staff': return '👷 Nhân viên kho';
      default: return '👤 Khách hàng';
    }
  }

  Widget _defaultAvatar() => Container(
    color: const Color(0xFFFFF0F3),
    child: const Icon(Icons.person, size: 50, color: Color(0xFFE63B6F)),
  );

  Widget _buildCard({required List<Widget> children}) => Container(
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 10)]),
    child: Column(children: children),
  );

  Widget _buildField({required String label, required TextEditingController ctrl, required String hint, required IconData icon, TextInputType type = TextInputType.text}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF94A3B8), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.w500)),
                const SizedBox(height: 4),
                TextField(
                  controller: ctrl,
                  keyboardType: type,
                  style: const TextStyle(fontSize: 15, color: Color(0xFF0F172A), fontWeight: FontWeight.w500),
                  decoration: InputDecoration(hintText: hint, hintStyle: const TextStyle(color: Color(0xFFCBD5E1)), border: InputBorder.none, isDense: true, contentPadding: EdgeInsets.zero),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReadOnly({required String label, required String value, required IconData icon}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF94A3B8), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.w500)),
                const SizedBox(height: 4),
                Text(value, style: const TextStyle(fontSize: 15, color: Color(0xFF64748B))),
              ],
            ),
          ),
          const Icon(Icons.lock_outline, size: 14, color: Color(0xFFCBD5E1)),
        ],
      ),
    );
  }
}
