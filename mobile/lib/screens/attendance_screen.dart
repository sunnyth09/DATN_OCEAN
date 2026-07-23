import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:permission_handler/permission_handler.dart';
import '../services/attendance_service.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  final AttendanceService _attendanceService = AttendanceService();
  final TextEditingController _noteController = TextEditingController();
  
  String _currentTime = '';
  String _currentDate = '';
  Timer? _timer;
  
  bool _isLoading = false;
  String? _currentWifiInfo;
  
  Map<String, dynamic>? _todayStatus;
  bool _isLoadingStatus = true;

  @override
  void initState() {
    super.initState();
    _startClock();
    _loadNetworkInfo();
    _loadTodayStatus();
  }

  Future<void> _loadTodayStatus() async {
    setState(() {
      _isLoadingStatus = true;
    });
    try {
      final res = await _attendanceService.getTodayStatus();
      if (mounted && res['status'] == 'success') {
        setState(() {
          _todayStatus = res['data'];
        });
      }
    } catch (_) {
      // Ignore
    } finally {
      if (mounted) {
        setState(() {
          _isLoadingStatus = false;
        });
      }
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _noteController.dispose();
    super.dispose();
  }

  void _startClock() {
    _updateTime();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      _updateTime();
    });
  }

  void _updateTime() {
    final now = DateTime.now();
    setState(() {
      _currentTime = DateFormat('HH:mm:ss').format(now);
      _currentDate = DateFormat('EEEE, dd/MM/yyyy', 'vi').format(now);
    });
  }

  Future<void> _loadNetworkInfo() async {
    final info = await _attendanceService.getCurrentNetworkInfo();
    setState(() {
      _currentWifiInfo = info['ssid'];
    });
  }

  Future<void> _handleCheck(bool isCheckIn) async {
    setState(() {
      _isLoading = true;
    });

    try {
      final res = isCheckIn 
          ? await _attendanceService.checkIn(note: _noteController.text.trim())
          : await _attendanceService.checkOut();

      if (!mounted) return;

      if (res['status'] == 'success') {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message'] ?? 'Thành công!', style: const TextStyle(fontWeight: FontWeight.bold)),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
          ),
        );
        _noteController.clear();
      } else if (res['needs_settings'] == true) {
        // Quyền vị trí bị từ chối vĩnh viễn — không request lại được, phải ra Settings.
        _showOpenSettingsDialog(res['message'] ?? 'Cần cấp quyền Vị trí.');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message'] ?? 'Lỗi không xác định!'),
            backgroundColor: Colors.red,
            behavior: SnackBarBehavior.floating,
            duration: const Duration(seconds: 4),
          ),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Có lỗi xảy ra.'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
    
    // Tải lại thông tin ca sau khi check-in/out
    _loadTodayStatus();
  }

  /// Hiện dialog hướng dẫn mở Cài đặt khi quyền Vị trí bị từ chối vĩnh viễn.
  void _showOpenSettingsDialog(String message) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cần quyền Vị trí'),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Để sau'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.pop(dialogContext);
              openAppSettings();
            },
            child: const Text('Mở Cài đặt'),
          ),
        ],
      ),
    );
  }

  /// Nút check-in/out: disable ngay khi đang xử lý để chặn spam tap,
  /// hiện spinner tại chỗ nên layout không nhảy.
  Widget _buildCheckButton({
    required bool isCheckIn,
    required IconData icon,
    required String label,
    required Color color,
  }) {
    return ElevatedButton(
      onPressed: _isLoading ? null : () => _handleCheck(isCheckIn),
      style: ElevatedButton.styleFrom(
        backgroundColor: color,
        disabledBackgroundColor: color.withValues(alpha: 0.6),
        padding: const EdgeInsets.symmetric(vertical: 20),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        elevation: 0,
      ),
      child: _isLoading
          ? const SizedBox(
              height: 28,
              width: 28,
              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 3),
            )
          : Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, color: Colors.white, size: 28),
                const SizedBox(height: 8),
                Text(label, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
              ],
            ),
    );
  }

  Widget _buildShiftInfo() {
    if (_isLoadingStatus) {
      return const Padding(
        padding: EdgeInsets.only(bottom: 20),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    
    if (_todayStatus == null) return const SizedBox.shrink();

    final currentShift = _todayStatus!['current_shift'];
    final state = _todayStatus!['state'];
    
    if (currentShift != null && currentShift['is_assigned'] == true) {
      String statusText = '';
      Color statusColor = Colors.blue;
      Color bgColor = const Color(0xFFE3F2FD);
      
      if (state == 'checked_in') {
        statusText = 'Đang làm việc';
        statusColor = Colors.green;
        bgColor = const Color(0xFFE8F5E9);
      } else if (state == 'checked_out') {
        statusText = 'Đã hoàn tất ca';
        statusColor = Colors.grey;
        bgColor = const Color(0xFFF5F5F5);
      } else {
        statusText = 'Sắp vào ca - Vui lòng Check-in';
        statusColor = Colors.orange;
        bgColor = const Color(0xFFFFF3E0);
      }

      return Container(
        width: double.infinity,
        margin: const EdgeInsets.only(bottom: 20),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: bgColor,
          border: Border.all(color: statusColor.withValues(alpha: 0.5)),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.info_outline, color: statusColor),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    statusText,
                    style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Ca hiện tại: ${currentShift['name']} (${currentShift['start_time']} - ${currentShift['end_time']})',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF0F172A)),
            ),
          ],
        ),
      );
    }

    // Nếu không có ca hiện tại, tìm ca tiếp theo trong ngày
    final shifts = _todayStatus!['shifts'] as List?;
    if (shifts != null && shifts.isNotEmpty) {
      final nextShifts = shifts.where((s) => s['is_assigned'] == true && s['state'] != 'checked_out').toList();
      if (nextShifts.isNotEmpty) {
        final nextShift = nextShifts.first;
        return Container(
          width: double.infinity,
          margin: const EdgeInsets.only(bottom: 20),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFFE3F2FD),
            border: Border.all(color: Colors.blue.withValues(alpha: 0.5)),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.event_note, color: Colors.blue),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Text(
                      'Ca làm việc sắp tới',
                      style: TextStyle(color: Colors.blue, fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                '${nextShift['shift_name']} (${nextShift['start_time']} - ${nextShift['end_time']})',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF0F172A)),
              ),
            ],
          ),
        );
      }
    }

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F5F5),
        border: Border.all(color: Colors.grey.withValues(alpha: 0.5)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Row(
        children: [
          Icon(Icons.event_busy, color: Colors.grey),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'Hôm nay bạn không có ca làm việc nào (hoặc đã hoàn tất).',
              style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: const Text('Chấm Công Điện Tử', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        backgroundColor: const Color(0xFFE63B6F),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          children: [
            _buildShiftInfo(),
            // Clock Card
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 30, horizontal: 20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 20, offset: const Offset(0, 10)),
                ],
              ),
              child: Column(
                children: [
                  const Icon(Icons.access_time_filled, size: 48, color: Color(0xFFE63B6F)),
                  const SizedBox(height: 16),
                  Text(_currentTime, style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: 2)),
                  const SizedBox(height: 8),
                  Text(_currentDate.toUpperCase(), style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF64748B))),
                  
                  const Padding(padding: EdgeInsets.symmetric(vertical: 20), child: Divider()),
                  
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        _currentWifiInfo != null ? Icons.wifi : Icons.wifi_off, 
                        color: _currentWifiInfo != null ? Colors.green : Colors.grey,
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        _currentWifiInfo != null ? 'WiFi: $_currentWifiInfo' : 'Không có kết nối WiFi',
                        style: TextStyle(
                          color: _currentWifiInfo != null ? Colors.green.shade700 : Colors.grey,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  GestureDetector(
                    onTap: _loadNetworkInfo,
                    child: const Text('Làm mới kết nối mạng', style: TextStyle(color: Color(0xFFE63B6F), fontSize: 12, decoration: TextDecoration.underline)),
                  )
                ],
              ),
            ),
            
            const SizedBox(height: 30),
            
            TextField(
              controller: _noteController,
              decoration: InputDecoration(
                hintText: 'Ghi chú (Tùy chọn, đi muộn, về sớm...)',
                filled: true,
                fillColor: Colors.white,
                prefixIcon: const Icon(Icons.note_alt_outlined),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
              ),
            ),
            
            const SizedBox(height: 30),
            
            // Buttons — giữ nguyên vị trí, disable khi đang xử lý để chống spam.
            Row(
              children: [
                Expanded(
                  child: _buildCheckButton(
                    isCheckIn: true,
                    color: const Color(0xFF10B981),
                    icon: Icons.login,
                    label: 'CHECK-IN',
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _buildCheckButton(
                    isCheckIn: false,
                    color: const Color(0xFFF43F5E),
                    icon: Icons.logout,
                    label: 'CHECK-OUT',
                  ),
                ),
              ],
            ),
            
            const SizedBox(height: 30),
            const Text(
              'Hệ thống yêu cầu đứng trong vòng 50m quanh công ty (GPS) hoặc kết nối đúng mạng WiFi nội bộ để điểm danh hợp lệ.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
            )
          ],
        ),
      ),
    );
  }
}
