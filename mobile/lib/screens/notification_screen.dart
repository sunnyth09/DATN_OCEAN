import 'dart:async';
import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  Timer? _timer;
  List<dynamic> notifications = [];
  bool isLoading = true;
  int unreadCount = 0;

  @override
  void initState() {
    super.initState();
    fetchNotifications();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => fetchNotifications(silent: true));
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> fetchNotifications({bool silent = false}) async {
    if (!silent && mounted) setState(() => isLoading = true);
    try {
      final res = await ApiClient().dio.get('/profile/notifications');
      final payload = res.data['data'];
      final items = payload is Map ? payload['data'] : payload;
      if (mounted) {
        setState(() {
          notifications = items is List ? items : [];
          unreadCount = int.tryParse((res.data['unread_count'] ?? 0).toString()) ?? 0;
          isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => isLoading = false);
    }
  }

  Future<void> markAsRead(String id) async {
    try {
      await ApiClient().dio.post('/profile/notifications/$id/read');
      fetchNotifications(silent: true);
    } catch (_) {}
  }

  Future<void> markAllAsRead() async {
    try {
      await ApiClient().dio.post('/profile/notifications/read-all');
      fetchNotifications(silent: true);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: Text('Thong bao${unreadCount > 0 ? ' ($unreadCount)' : ''}'),
        actions: [
          IconButton(
            icon: const Icon(Icons.done_all),
            onPressed: notifications.isNotEmpty ? markAllAsRead : null,
            tooltip: 'Danh dau da doc',
          ),
        ],
      ),
      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : notifications.isEmpty
              ? const Center(child: Text('Ban khong co thong bao nao.', style: TextStyle(color: Colors.grey)))
              : RefreshIndicator(
                  onRefresh: fetchNotifications,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: notifications.length,
                    itemBuilder: (context, index) {
                      final notif = notifications[index];
                      final rawData = notif['data'];
                      final data = rawData is Map ? rawData : {};
                      final isRead = notif['read_at'] != null;
                      final title = data['title'] ?? 'Thong bao he thong';
                      final message = data['message'] ?? '';
                      final date = notif['created_at']?.toString().split('T').first ?? '';

                      return InkWell(
                        onTap: () {
                          if (!isRead) markAsRead(notif['id'].toString());
                        },
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: isRead ? Colors.white : const Color(0xFFF0F9FF),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: isRead ? Colors.transparent : AppColors.primaryLight.withOpacity(0.35)),
                            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 10)],
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: const BoxDecoration(color: Color(0xFFFFF0F3), shape: BoxShape.circle),
                                child: Icon(
                                  data['type'] == 'court_booking' ? Icons.sports_tennis : Icons.notifications_active,
                                  color: AppColors.primary,
                                  size: 18,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(title.toString(), style: TextStyle(fontWeight: isRead ? FontWeight.w600 : FontWeight.bold, fontSize: 14)),
                                    const SizedBox(height: 4),
                                    Text(message.toString(), style: const TextStyle(color: Color(0xFF475569), fontSize: 13, height: 1.4)),
                                    const SizedBox(height: 6),
                                    Text(date, style: const TextStyle(color: Colors.grey, fontSize: 11)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
