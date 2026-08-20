import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import '../config/app_config.dart';

typedef RealtimeEventCallback = void Function(String event, dynamic data);

/// Dịch vụ WebSocket Realtime kết nối Laravel Reverb.
/// Quản lý đăng ký kênh (subscribe channel) và lắng nghe sự kiện tức thời.
class RealtimeService {
  static final RealtimeService _instance = RealtimeService._internal();
  factory RealtimeService() => _instance;
  RealtimeService._internal();

  WebSocket? _socket;
  bool _isConnected = false;
  bool _isConnecting = false;
  Timer? _reconnectTimer;
  Timer? _pingTimer;

  final Set<String> _subscribedChannels = {};
  final Map<String, List<RealtimeEventCallback>> _listeners = {};

  bool get isConnected => _isConnected;

  /// Kết nối đến Laravel Reverb WebSocket
  Future<void> connect() async {
    if (_isConnected || _isConnecting) return;
    _isConnecting = true;

    try {
      final url = AppConfig.reverbWsUrl;
      debugPrint('[RealtimeService] Connecting to Reverb: $url');
      _socket = await WebSocket.connect(url).timeout(const Duration(seconds: 8));
      _isConnected = true;
      _isConnecting = false;
      debugPrint('[RealtimeService] Connected successfully!');

      _socket!.listen(
        _onMessage,
        onError: (err) {
          debugPrint('[RealtimeService] Socket error: $err');
          _handleDisconnect();
        },
        onDone: () {
          debugPrint('[RealtimeService] Socket closed by server');
          _handleDisconnect();
        },
        cancelOnError: true,
      );

      _startPing();
      _resubscribeAll();
    } catch (e) {
      debugPrint('[RealtimeService] Connect failed: $e');
      _isConnecting = false;
      _handleDisconnect();
    }
  }

  void _onMessage(dynamic rawMessage) {
    try {
      final Map<String, dynamic> data = jsonDecode(rawMessage.toString());
      final String? event = data['event'];
      final String? channel = data['channel'];
      final dynamic payload = data['data'];

      if (event == 'pusher:ping') {
        _send({'event': 'pusher:pong', 'data': {}});
        return;
      }

      if (event == 'pusher:connection_established') {
        debugPrint('[RealtimeService] Connection established: $payload');
        _resubscribeAll();
        return;
      }

      if (channel != null && event != null) {
        final cleanEvent = event.startsWith('.') ? event.substring(1) : event;
        final key1 = '$channel:$event';
        final key2 = '$channel:$cleanEvent';
        final wildcardKey = '$channel:*';

        dynamic parsedPayload = payload;
        if (payload is String) {
          try {
            parsedPayload = jsonDecode(payload);
          } catch (_) {}
        }

        final callbacksToInvoke = <RealtimeEventCallback>{};
        if (_listeners.containsKey(key1)) callbacksToInvoke.addAll(_listeners[key1]!);
        if (_listeners.containsKey(key2)) callbacksToInvoke.addAll(_listeners[key2]!);
        if (_listeners.containsKey(wildcardKey)) callbacksToInvoke.addAll(_listeners[wildcardKey]!);

        for (final cb in callbacksToInvoke) {
          cb(cleanEvent, parsedPayload);
        }
      }
    } catch (e) {
      debugPrint('[RealtimeService] Error parsing message: $e');
    }
  }

  /// Đăng ký lắng nghe kênh và sự kiện
  void subscribe(String channel, String event, RealtimeEventCallback callback) {
    final key = '$channel:$event';
    _listeners.putIfAbsent(key, () => []).add(callback);

    if (!_subscribedChannels.contains(channel)) {
      _subscribedChannels.add(channel);
      _sendSubscribe(channel);
    }

    if (!_isConnected && !_isConnecting) {
      connect();
    }
  }

  /// Hủy đăng ký lắng nghe
  void unsubscribe(String channel, [String? event, RealtimeEventCallback? callback]) {
    if (event != null && callback != null) {
      final key = '$channel:$event';
      _listeners[key]?.remove(callback);
      if (_listeners[key]?.isEmpty ?? false) {
        _listeners.remove(key);
      }
    } else if (event != null) {
      _listeners.remove('$channel:$event');
    } else {
      _listeners.removeWhere((k, _) => k.startsWith('$channel:'));
      _subscribedChannels.remove(channel);
      _sendUnsubscribe(channel);
    }
  }

  void _sendSubscribe(String channel) {
    if (!_isConnected) return;
    _send({
      'event': 'pusher:subscribe',
      'data': {'channel': channel},
    });
  }

  void _sendUnsubscribe(String channel) {
    if (!_isConnected) return;
    _send({
      'event': 'pusher:unsubscribe',
      'data': {'channel': channel},
    });
  }

  void _resubscribeAll() {
    for (final ch in _subscribedChannels) {
      _sendSubscribe(ch);
    }
  }

  void _send(Map<String, dynamic> msg) {
    try {
      if (_socket != null && _isConnected) {
        _socket!.add(jsonEncode(msg));
      }
    } catch (_) {}
  }

  void _startPing() {
    _pingTimer?.cancel();
    _pingTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _send({'event': 'pusher:ping', 'data': {}});
    });
  }

  void _handleDisconnect() {
    _isConnected = false;
    _pingTimer?.cancel();
    try {
      _socket?.close();
    } catch (_) {}
    _socket = null;

    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(const Duration(seconds: 4), () {
      if (_subscribedChannels.isNotEmpty) {
        connect();
      }
    });
  }

  void disconnect() {
    _reconnectTimer?.cancel();
    _pingTimer?.cancel();
    _isConnected = false;
    _subscribedChannels.clear();
    _listeners.clear();
    try {
      _socket?.close();
    } catch (_) {}
    _socket = null;
  }
}
