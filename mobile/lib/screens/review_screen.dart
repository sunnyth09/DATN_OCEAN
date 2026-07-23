import 'dart:io';
import 'package:go_router/go_router.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../config/app_config.dart';
import '../services/api_client.dart';

class ReviewScreen extends StatefulWidget {
  final Map<String, dynamic> orderItem;
  final int productId;
  final String productName;
  final String? productImage;
  const ReviewScreen({
    super.key,
    required this.orderItem,
    required this.productId,
    required this.productName,
    this.productImage,
  });

  @override
  State<ReviewScreen> createState() => _ReviewScreenState();
}

class _ReviewScreenState extends State<ReviewScreen> {
  int _rating = 5;
  final _commentCtrl = TextEditingController();
  bool _isSubmitting = false;
  
  final List<File> _selectedImages = [];
  final ImagePicker _picker = ImagePicker();

  Future<void> _pickImages() async {
    if (_selectedImages.length >= 5) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Chỉ được chọn tối đa 5 ảnh')));
      return;
    }
    
    try {
      final List<XFile> images = await _picker.pickMultiImage(
        imageQuality: 70, // Basic compression
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
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Không thể chọn ảnh')));
    }
  }
  
  void _removeImage(int index) {
    setState(() {
      _selectedImages.removeAt(index);
    });
  }

  @override
  void dispose() {
    _commentCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_commentCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng nhập nội dung đánh giá!'), backgroundColor: Colors.orange));
      return;
    }
    setState(() => _isSubmitting = true);
    try {
      final orderItemId = widget.orderItem['order_item_id'] ?? widget.orderItem['id'];

      final formData = FormData.fromMap({
        'product_id': widget.productId,
        'order_item_id': orderItemId,
        'rating': _rating,
        'content': _commentCtrl.text.trim(),
      });

      for (var i = 0; i < _selectedImages.length; i++) {
        formData.files.add(
          MapEntry(
            'images[]',
            await MultipartFile.fromFile(_selectedImages[i].path, filename: 'review_image_$i.jpg'),
          ),
        );
      }

      await ApiClient().dio.post(
        '/profile/orders/feedback',
        data: formData,
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đánh giá thành công! Cảm ơn bạn.'), backgroundColor: Colors.green));
        context.pop(true);
      }
    } on DioException catch (e) {
      final message = e.response?.data is Map ? e.response?.data['message'] : null;
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message ?? 'Không thể gửi đánh giá!'), backgroundColor: Colors.red));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kết nối!'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final imageUrl = AppConfig.imageUrl(widget.productImage);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Đánh giá sản phẩm', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFFE63B6F)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Product card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: imageUrl.isNotEmpty
                        ? Image.network(imageUrl, width: 70, height: 70, fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => _placeholder())
                        : _placeholder(),
                  ),
                  const SizedBox(width: 14),
                  Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(widget.productName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)), maxLines: 2),
                      const SizedBox(height: 6),
                      Row(children: List.generate(5, (i) => Icon(Icons.star, size: 14, color: i < _rating ? Colors.amber : const Color(0xFFE2E8F0)))),
                    ],
                  )),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Star rating
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
              child: Column(
                children: [
                  const Text('Chất lượng sản phẩm', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(5, (i) {
                      final filled = i < _rating;
                      return GestureDetector(
                        onTap: () => setState(() => _rating = i + 1),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          margin: const EdgeInsets.symmetric(horizontal: 6),
                          child: Icon(filled ? Icons.star : Icons.star_border, size: 44, color: filled ? Colors.amber : const Color(0xFFCBD5E1)),
                        ),
                      );
                    }),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    ['', 'Rất tệ', 'Không hài lòng', 'Bình thường', 'Hài lòng', 'Rất xuất sắc!'][_rating],
                    style: TextStyle(color: _rating >= 4 ? Colors.green : (_rating <= 2 ? Colors.red : Colors.orange), fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Comment
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Nhận xét của bạn', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _commentCtrl,
                    maxLines: 5,
                    maxLength: 1000,
                    decoration: InputDecoration(
                      hintText: 'Chia sẻ trải nghiệm của bạn về sản phẩm này...',
                      hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 14),
                      filled: true,
                      fillColor: const Color(0xFFF8FAFC),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE63B6F))),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text('Thêm hình ảnh (Tối đa 5 ảnh)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A))),
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
                              child: Image.file(
                                _selectedImages[index],
                                width: 70,
                                height: 70,
                                fit: BoxFit.cover,
                              ),
                            ),
                            Positioned(
                              top: -8,
                              right: -8,
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
                            width: 70,
                            height: 70,
                            decoration: BoxDecoration(
                              color: const Color(0xFFF1F5F9),
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
                ],
              ),
            ),
            const SizedBox(height: 24),

            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _isSubmitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFE63B6F),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isSubmitting
                  ? const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                      SizedBox(width: 10),
                      Text('Đang gửi...', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    ])
                  : const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.send_rounded, size: 18),
                      SizedBox(width: 8),
                      Text('Gửi đánh giá', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    ]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _placeholder() => Container(
    width: 70, height: 70,
    decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(10)),
    child: const Icon(Icons.image_outlined, color: Colors.grey, size: 28),
  );
}
