import 'dart:async';
import '../config/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
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

  @override
  void initState() {
    super.initState();
    _startClock();
    _loadNetworkInfo();
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
            // Clock Card
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 30, horizontal: 20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 20, offset: const Offset(0, 10)),
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
            
            // Buttons
            _isLoading 
                ? const Center(child: CircularProgressIndicator())
                : Row(
              children: [
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => _handleCheck(true),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF10B981),
                      padding: const EdgeInsets.symmetric(vertical: 20),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                    ),
                    child: const Column(
                      children: [
                        Icon(Icons.login, color: Colors.white, size: 28),
                        SizedBox(height: 8),
                        Text('CHECK-IN', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => _handleCheck(false),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFF43F5E),
                      padding: const EdgeInsets.symmetric(vertical: 20),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                    ),
                    child: const Column(
                      children: [
                        Icon(Icons.logout, color: Colors.white, size: 28),
                        SizedBox(height: 8),
                        Text('CHECK-OUT', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            
            const SizedBox(height: 30),
            const Text(
              'Hệ thống yêu cầu đứng trong vòng 50m quanh công ty (GPS) hoặc kết nối đúng mạng WiFi nội bộ để điểm danh hợp lệ.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
            )
          ],
        ),
      ),
    );
  }
}
