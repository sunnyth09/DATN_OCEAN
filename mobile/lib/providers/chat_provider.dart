import 'dart:async';
import 'package:flutter/material.dart';
import '../utils/app_logger.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_client.dart';
import '../services/realtime_service.dart';

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
  bool _wsConnected = false;

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
      AppLogger.error('Error init staff chat', e, 'Chat');
    } finally {
      _isLoading = false;
      notifyListeners();
      _connectWebSocket();
    }
  }

  /// Kết nối WebSocket qua RealtimeService để nhận tin nhắn tức thời.
  /// Fallback về polling HTTP nếu WebSocket không khả dụng.
  void _connectWebSocket() {
    if (_sessionToken == null || _sessionToken!.isEmpty) {
      _startFallbackPolling();
      return;
    }

    try {
      final realtime = RealtimeService();
      realtime.connect();
      realtime.subscribe(
        'live-chat.$_sessionToken',
        '*',
        (event, data) {
          if (data is Map) {
            final msg = Map<String, dynamic>.from(data);
            if (msg['message'] != 'SYSTEM_SESSION_CLOSED') {
              _staffMessages.add(msg);
              notifyListeners();
            }
          }
        },
      );
      _wsConnected = true;
      AppLogger.info('WebSocket connected for chat session', 'Chat');
    } catch (e) {
      AppLogger.error('WebSocket failed, falling back to polling', e, 'Chat');
      _wsConnected = false;
      _startFallbackPolling();
    }
  }

  /// Polling fallback mỗi 8 giây (thay vì 3s trước đây) khi WebSocket không khả dụng
  void _startFallbackPolling() {
    _pollingTimer?.cancel();
    _pollingTimer = Timer.periodic(const Duration(seconds: 8), (_) async {
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
    if (_wsConnected && _sessionToken != null) {
      try {
        RealtimeService().unsubscribe('live-chat.$_sessionToken');
      } catch (_) {}
      _wsConnected = false;
    }
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
      AppLogger.error(' sending staff message: $e');
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
