import 'dart:io';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';
import '../config/app_theme.dart';

class CreateReturnRequestScreen extends StatefulWidget {
  final String orderId;
  const CreateReturnRequestScreen({super.key, required this.orderId});

  @override
  State<CreateReturnRequestScreen> createState() => _CreateReturnRequestScreenState();
}

class _CreateReturnRequestScreenState extends State<CreateReturnRequestScreen> {
  final List<String> presetReasons = [
    'Sản phẩm bị lỗi/hư hỏng',
    'Giao sai sản phẩm',
    'Sản phẩm không giống mô tả',
    'Thiếu phụ kiện/quà tặng',
    'Lý do khác...',
  ];

  String? selectedReason;
  final TextEditingController _descriptionCtrl = TextEditingController();
  final List<File> _selectedImages = [];
  final ImagePicker _picker = ImagePicker();
  bool _isSubmitting = false;

  Future<void> _pickImages() async {
    if (_selectedImages.length >= 5) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Chỉ được chọn tối đa 5 ảnh')));
      return;
    }
    
    try {
      final List<XFile> images = await _picker.pickMultiImage(
        imageQuality: 70,
      );
      
      if (images.isNotEmpty) {
        setState(() {
          for (var img in images) {
            if (_selectedImages.length < 5) {
              _selectedImages.add(File(img.path));
            }
          }
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Không thể chọn ảnh')));
      }
    }
  }

  void _removeImage(int index) {
    setState(() {
      _selectedImages.removeAt(index);
    });
  }

  Future<void> _submit() async {
    if (selectedReason == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng chọn lý do hoàn hàng!'), backgroundColor: Colors.orange));
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final formData = FormData.fromMap({
        'reason': selectedReason,
        'description': _descriptionCtrl.text.trim(),
      });

      for (var i = 0; i < _selectedImages.length; i++) {
        formData.files.add(
          MapEntry(
            'images[]',
            await MultipartFile.fromFile(_selectedImages[i].path, filename: 'return_image_$i.jpg'),
          ),
        );
      }

      final response = await ApiClient().dio.post(
        '/orders/${widget.orderId}/return-request',
        data: formData,
      );

      if (!mounted) return;
      if (response.statusCode == 200 || response.statusCode == 201) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã gửi yêu cầu hoàn hàng thành công!'), backgroundColor: Colors.green),
        );
        context.pop(true);
      }
    } on DioException catch (e) {
      if (mounted) {
        final message = e.response?.data is Map ? e.response?.data['message'] : null;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message ?? 'Không thể gửi yêu cầu hoàn hàng!'), backgroundColor: Colors.red),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Lỗi kết nối!'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Yêu cầu Hoàn hàng', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppColors.primary),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/orders');
            }
          },
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Lý do hoàn hàng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
            const SizedBox(height: 12),
            ...presetReasons.map((reason) {
              final isSelected = selectedReason == reason;
              return GestureDetector(
                onTap: () => setState(() => selectedReason = reason),
                child: Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: isSelected ? const Color(0xFFFFF5F5) : Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: isSelected ? Colors.red.shade300 : const Color(0xFFE2E8F0)),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
                        color: isSelected ? Colors.red : const Color(0xFF64748B),
                        size: 20,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          reason,
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                            color: isSelected ? Colors.red.shade700 : const Color(0xFF334155)
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }),
            const SizedBox(height: 16),
            
            const Text('Mô tả thêm', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
            const SizedBox(height: 12),
            TextField(
              controller: _descriptionCtrl,
              maxLines: 4,
              maxLength: 2000,
              decoration: InputDecoration(
                hintText: 'Nhập thông tin chi tiết về tình trạng sản phẩm...',
                hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 14),
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.primary, width: 1.5)),
              ),
            ),
            const SizedBox(height: 16),

            const Text('Hình ảnh minh chứng (Tối đa 5 ảnh)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                ...List.generate(_selectedImages.length, (index) {
                  return Stack(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.file(_selectedImages[index], width: 70, height: 70, fit: BoxFit.cover),
                      ),
                      Positioned(
                        top: -8, right: -8,
                        child: IconButton(
                          icon: const Icon(Icons.cancel, color: Colors.red),
                          onPressed: () => _removeImage(index),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
                        ),
                      ),
                    ],
                  );
                }),
                if (_selectedImages.length < 5)
                  GestureDetector(
                    onTap: _pickImages,
                    child: Container(
                      width: 70, height: 70,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFE2E8F0), style: BorderStyle.solid),
                      ),
                      child: const Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.add_a_photo_outlined, color: Color(0xFF64748B), size: 24),
                          SizedBox(height: 4),
                          Text('Thêm ảnh', style: TextStyle(color: Color(0xFF64748B), fontSize: 10)),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 28),
            
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton(
                onPressed: _isSubmitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isSubmitting
                  ? const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                      SizedBox(width: 8),
                      Text('Đang gửi...', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
                    ])
                  : const Text('Gửi Yêu Cầu', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
