import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../providers/open_play_provider.dart';

class CreateOpenPlayScreen extends StatefulWidget {
  const CreateOpenPlayScreen({super.key});

  @override
  State<CreateOpenPlayScreen> createState() => _CreateOpenPlayScreenState();
}

class _CreateOpenPlayScreenState extends State<CreateOpenPlayScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descController = TextEditingController();
  final _rulesController = TextEditingController();

  int? _selectedBookingId;
  String _skillLevel = 'all_levels';
  String _genderRule = 'any';
  String _matchType = 'doubles';
  int _maxPlayers = 4;
  String _joinMode = 'auto';
  String _paymentMode = 'split_payment';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final provider = context.read<OpenPlayProvider>();
      await provider.fetchEligibleBookings();
      if (provider.eligibleBookings.isNotEmpty) {
        setState(() {
          _selectedBookingId = provider.eligibleBookings.first['booking_id'] as int?;
        });
      }
    });
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descController.dispose();
    _rulesController.dispose();
    super.dispose();
  }

  String _formatCurrency(int amount) {
    return NumberFormat.currency(locale: 'vi_VN', symbol: 'đ', decimalDigits: 0).format(amount);
  }

  Future<void> _submit() async {
    if (_selectedBookingId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chọn một lịch đặt sân hợp lệ.')),
      );
      return;
    }

    if (!_formKey.currentState!.validate()) return;

    final provider = context.read<OpenPlayProvider>();
    try {
      final res = await provider.createMatch({
        'booking_id': _selectedBookingId,
        'title': _titleController.text.trim(),
        'description': _descController.text.trim().isEmpty ? null : _descController.text.trim(),
        'skill_level': _skillLevel,
        'gender_rule': _genderRule,
        'match_type': _matchType,
        'max_players': _maxPlayers,
        'join_mode': _joinMode,
        'payment_mode': _paymentMode,
        'rules': _rulesController.text.trim().isEmpty ? null : _rulesController.text.trim(),
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Tạo kèo Open Play thành công!')),
        );
        context.pushReplacement('/open-plays/${res.id}');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OpenPlayProvider>();
    final bookings = provider.eligibleBookings;

    final selectedBooking = bookings.firstWhere(
      (b) => b['booking_id'] == _selectedBookingId,
      orElse: () => null,
    );
    final totalAmount = selectedBooking != null ? (selectedBooking['total_amount'] is int ? selectedBooking['total_amount'] : int.tryParse('${selectedBooking['total_amount']}') ?? 0) : 0;
    final slotPrice = _paymentMode == 'split_payment' ? (totalAmount / _maxPlayers).floor() : 0;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Tạo Kèo Giao Lưu', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: provider.isLoading
          ? const Center(child: CircularProgressIndicator())
          : bookings.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(32),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.calendar_today_outlined, size: 64, color: Colors.grey.shade400),
                        const SizedBox(height: 16),
                        const Text(
                          'Bạn chưa có lịch đặt sân hợp lệ',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Cần đặt sân trước khi tạo kèo Open Play để các thành viên khác tham gia.',
                          textAlign: TextAlign.center,
                          style: TextStyle(fontSize: 13, color: Colors.grey),
                        ),
                        const SizedBox(height: 20),
                        ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          onPressed: () => context.push('/courts'),
                          child: const Text('Đặt Sân Ngay', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ),
                  ),
                )
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      // Section 1: Chọn Booking
                      const Text('1. Chọn Lịch Đặt Sân', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      ...bookings.map((b) {
                        final bId = b['booking_id'] as int?;
                        final isSelected = _selectedBookingId == bId;
                        final courtName = b['court']?['court_name']?.toString() ?? 'Sân Cầu Lông';
                        final dateStr = b['booking_date']?.toString() ?? '';
                        final timeStr = '${b['start_time']?.toString().substring(0, 5)} - ${b['end_time']?.toString().substring(0, 5)}';

                        return InkWell(
                          onTap: () => setState(() => _selectedBookingId = bId),
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: isSelected ? AppColors.primary.withValues(alpha: 0.08) : Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: isSelected ? AppColors.primary : Colors.grey.shade200,
                                width: isSelected ? 2 : 1,
                              ),
                            ),
                            child: Row(
                              children: [
                                Radio<int>(
                                  value: bId!,
                                  groupValue: _selectedBookingId,
                                  activeColor: AppColors.primary,
                                  onChanged: (v) => setState(() => _selectedBookingId = v),
                                ),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(courtName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                      const SizedBox(height: 2),
                                      Text('$dateStr | $timeStr', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      }),
                      const SizedBox(height: 16),

                      // Section 2: Thông tin kèo
                      const Text('2. Thông Tin Kèo', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      TextFormField(
                        controller: _titleController,
                        decoration: InputDecoration(
                          labelText: 'Tên kèo giao lưu *',
                          filled: true,
                          fillColor: Colors.white,
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Vui lòng nhập tên kèo' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _descController,
                        maxLines: 2,
                        decoration: InputDecoration(
                          labelText: 'Mô tả thêm',
                          filled: true,
                          fillColor: Colors.white,
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Trình độ & Giới tính
                      Row(
                        children: [
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              value: _skillLevel,
                              decoration: InputDecoration(
                                labelText: 'Trình độ',
                                filled: true,
                                fillColor: Colors.white,
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              items: const [
                                DropdownMenuItem(value: 'all_levels', child: Text('Mọi trình độ')),
                                DropdownMenuItem(value: 'beginner', child: Text('Mới chơi')),
                                DropdownMenuItem(value: 'intermediate', child: Text('Trung bình')),
                                DropdownMenuItem(value: 'advanced', child: Text('Nâng cao')),
                              ],
                              onChanged: (v) => setState(() => _skillLevel = v!),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              value: _genderRule,
                              decoration: InputDecoration(
                                labelText: 'Giới tính',
                                filled: true,
                                fillColor: Colors.white,
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              items: const [
                                DropdownMenuItem(value: 'any', child: Text('Nam & Nữ')),
                                DropdownMenuItem(value: 'male_only', child: Text('Chỉ Nam')),
                                DropdownMenuItem(value: 'female_only', child: Text('Chỉ Nữ')),
                              ],
                              onChanged: (v) => setState(() => _genderRule = v!),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Số người & Hình thức thanh toán
                      Row(
                        children: [
                          Expanded(
                            child: DropdownButtonFormField<int>(
                              value: _maxPlayers,
                              decoration: InputDecoration(
                                labelText: 'Số người',
                                filled: true,
                                fillColor: Colors.white,
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              items: [2, 4, 6, 8]
                                  .map((n) => DropdownMenuItem(value: n, child: Text('$n người')))
                                  .toList(),
                              onChanged: (v) => setState(() => _maxPlayers = v!),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              value: _paymentMode,
                              decoration: InputDecoration(
                                labelText: 'Chi phí',
                                filled: true,
                                fillColor: Colors.white,
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              items: const [
                                DropdownMenuItem(value: 'split_payment', child: Text('Chia đều tiền')),
                                DropdownMenuItem(value: 'host_pays', child: Text('Host bao sân')),
                              ],
                              onChanged: (v) => setState(() => _paymentMode = v!),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Price Calculation Box
                      if (_paymentMode == 'split_payment')
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.amber.shade50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.amber.shade200),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Dự tính mỗi slot:'),
                              Text(
                                '${_formatCurrency(slotPrice)}/người',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.deepOrange),
                              ),
                            ],
                          ),
                        ),
                      const SizedBox(height: 24),

                      // Submit Button
                      SizedBox(
                        height: 50,
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          onPressed: provider.isActionLoading ? null : _submit,
                          child: provider.isActionLoading
                              ? const CircularProgressIndicator(color: Colors.white)
                              : const Text('Đăng Kèo Giao Lưu', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                        ),
                      ),
                      const SizedBox(height: 30),
                    ],
                  ),
                ),
    );
  }
}
