import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_client.dart';

enum ChatMode { staff, ai }

class ChatProvider extends ChangeNotifier {
  static const String _sessionTokenKey = 'live_chat_session_token';

  ChatMode _mode = ChatMode.staff;
  String? _sessionToken;
  bool _isLoading = true;
  bool _isSending = false;

  // Live Chat với nhân viên
  List<Map<String, dynamic>> _staffMessages = [];
  Timer? _pollingTimer;

  // AI Chatbot
  final List<Map<String, dynamic>> _aiMessages = [];
  final List<Map<String, dynamic>> _aiHistory = [];

  // Sản phẩm đang quan tâm / hỏi tư vấn
  Map<String, dynamic>? _inquiryProduct;

  ChatMode get mode => _mode;
  bool get isLoading => _isLoading;
  bool get isSending => _isSending;
  List<Map<String, dynamic>> get staffMessages => _staffMessages;
  List<Map<String, dynamic>> get aiMessages => _aiMessages;
  Map<String, dynamic>? get inquiryProduct => _inquiryProduct;

  void setMode(ChatMode newMode) {
    _mode = newMode;
    notifyListeners();
  }

  void setInquiryProduct(Map<String, dynamic>? product) {
    _inquiryProduct = product;
    notifyListeners();
  }

  void clearInquiryProduct() {
    _inquiryProduct = null;
    notifyListeners();
  }

  /// Khởi tạo phiên Live Chat với nhân viên
  Future<void> initStaffChat() async {
    _isLoading = true;
    notifyListeners();

    try {
      final prefs = await SharedPreferences.getInstance();
      _sessionToken = prefs.getString(_sessionTokenKey);

      final res = await ApiClient().dio.post('/live-chat/init', data: {
        if (_sessionToken != null && _sessionToken!.isNotEmpty)
          'session_token': _sessionToken,
      });

      if (res.data != null && res.data['session'] != null) {
        final session = res.data['session'];
        _sessionToken = session['session_token']?.toString();
        if (_sessionToken != null) {
          await prefs.setString(_sessionTokenKey, _sessionToken!);
        }

        final rawMsgs = res.data['messages'] as List<dynamic>? ?? [];
        _staffMessages = rawMsgs
            .map((m) => Map<String, dynamic>.from(m as Map))
            .where((m) => m['message'] != 'SYSTEM_SESSION_CLOSED')
            .toList();
      }
    } catch (e) {
      debugPrint('Error init staff chat: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
      startPolling();
    }
  }

  /// Bắt đầu polling ngầm mỗi 3 giây để nhận tin nhắn mới từ Admin/Staff
  void startPolling() {
    _pollingTimer?.cancel();
    _pollingTimer = Timer.periodic(const Duration(seconds: 3), (_) async {
      if (_sessionToken == null || _sessionToken!.isEmpty) return;
      try {
        final res = await ApiClient().dio.post('/live-chat/init', data: {
          'session_token': _sessionToken,
        });
        if (res.data != null && res.data['messages'] != null) {
          final rawMsgs = res.data['messages'] as List<dynamic>? ?? [];
          final newMsgs = rawMsgs
              .map((m) => Map<String, dynamic>.from(m as Map))
              .where((m) => m['message'] != 'SYSTEM_SESSION_CLOSED')
              .toList();

          if (newMsgs.length != _staffMessages.length) {
            _staffMessages = newMsgs;
            notifyListeners();
          }
        }
      } catch (_) {}
    });
  }

  void stopPolling() {
    _pollingTimer?.cancel();
    _pollingTimer = null;
  }

  /// Gửi tin nhắn tới nhân viên CSKH
  Future<bool> sendStaffMessage(String text) async {
    if (text.trim().isEmpty) return false;

    _isSending = true;
    notifyListeners();

    // Optimistic UI update
    final optimisticMsg = {
      'message': text,
      'sender_type': 'user',
      'created_at': DateTime.now().toIso8601String(),
      'is_temp': true,
    };
    _staffMessages.add(optimisticMsg);
    notifyListeners();

    try {
      final res = await ApiClient().dio.post('/live-chat/message', data: {
        'session_token': _sessionToken,
        'message': text,
      });

      if (res.data['success'] == true) {
        final confirmed = Map<String, dynamic>.from(res.data['message'] as Map);
        _staffMessages.remove(optimisticMsg);
        _staffMessages.add(confirmed);
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('Error sending staff message: $e');
      _staffMessages.remove(optimisticMsg);
      return false;
    } finally {
      _isSending = false;
      notifyListeners();
    }
  }

  /// Gửi tin nhắn tới AI Chatbot
  Future<bool> sendAiMessage(String text) async {
    if (text.trim().isEmpty) return false;

    _isSending = true;
    // Add user message
    _aiMessages.add({
      'role': 'user',
      'text': text,
      'created_at': DateTime.now().toIso8601String(),
    });
    notifyListeners();

    try {
      final res = await ApiClient().dio.post('/chatbot/message', data: {
        'message': text,
        'history': _aiHistory,
      });

      final reply = res.data['reply']?.toString() ??
          res.data['message']?.toString() ??
          'Xin lỗi, tôi chưa hiểu yêu cầu. Bạn có thể hỏi lại được không?';

      _aiMessages.add({
        'role': 'model',
        'text': reply,
        'created_at': DateTime.now().toIso8601String(),
      });

      // Cập nhật history cho lượt chat tiếp theo
      _aiHistory.add({
        'role': 'user',
        'parts': [{'text': text}],
      });
      _aiHistory.add({
        'role': 'model',
        'parts': [{'text': reply}],
      });

      return true;
    } catch (e) {
      _aiMessages.add({
        'role': 'model',
        'text': 'Hệ thống đang bận. Bạn có thể chuyển sang tab "Nhân viên CSKH" để được hỗ trợ trực tiếp nhé!',
        'created_at': DateTime.now().toIso8601String(),
      });
      return false;
    } finally {
      _isSending = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    stopPolling();
    super.dispose();
  }
}
