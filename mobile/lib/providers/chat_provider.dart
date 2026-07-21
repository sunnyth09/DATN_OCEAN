import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';

class ChatProvider extends ChangeNotifier {
  List<dynamic> _messages = [];
  bool _isLoading = true;
  bool _isSending = false;

  List<dynamic> get messages => _messages;
  bool get isLoading => _isLoading;
  bool get isSending => _isSending;

  Future<void> fetchMessages() async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await ApiClient().dio.get('/chat/messages');
      if (res.data['status'] == 'success') {
        _messages = res.data['data'] ?? [];
      }
    } catch (e) {
      debugPrint('Error fetching chat messages: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> sendMessage(String text) async {
    if (text.trim().isEmpty) return false;
    
    _isSending = true;
    notifyListeners();

    try {
      final res = await ApiClient().dio.post('/chat/messages', data: {
        'message': text,
      });

      if (res.data['status'] == 'success') {
        _messages.add(res.data['data']);
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('Error sending message: $e');
      return false;
    } finally {
      _isSending = false;
      notifyListeners();
    }
  }
}
