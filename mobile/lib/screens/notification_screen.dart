import 'dart:convert';
import '../config/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_config.dart';

String get kBaseUrl => AppConfig.kBaseUrl;

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  List<dynamic> notifications = [];
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    fetchNotifications();
  }

  Future<void> fetchNotifications() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      if (token == null) {
        setState(() => isLoading = false);
        return;
      }

      final res = await http.get(
        Uri.parse('$kBaseUrl/profile/notifications'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            notifications = data['data'] ?? [];
            isLoading = false;
          });
        }
      } else {
        if (mounted) setState(() => isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => isLoading = false);
    }
  }

  Future<void> markAsRead(String id) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      await http.post(
        Uri.parse('$kBaseUrl/profile/notifications/$id/read'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );
      fetchNotifications();
    } catch (e) {
      // Bỏ qua lỗi
    }
  }
  
  Future<void> markAllAsRead() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      await http.post(
        Uri.parse('$kBaseUrl/profile/notifications/read-all'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );
      fetchNotifications();
    } catch (e) {
      // Bỏ qua lỗi
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Thông báo', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFFE63B6F)),
        actions: [
          IconButton(
            icon: const Icon(Icons.done_all), 
            onPressed: notifications.isNotEmpty ? markAllAsRead : null,
            tooltip: 'Đánh dấu đã đọc tất cả',
          )
        ],
      ),
      body: isLoading
        ? const Center(child: CircularProgressIndicator(color: Color(0xFFE63B6F)))
        : notifications.isEmpty
          ? const Center(child: Text('Bạn không có thông báo nào.', style: TextStyle(color: Colors.grey)))
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: notifications.length,
              itemBuilder: (context, index) {
                final notif = notifications[index];
                final data = notif['data'] ?? {};
                final isRead = notif['read_at'] != null;
                final title = data['title'] ?? 'Thông báo hệ thống';
                final message = data['message'] ?? '';
                final date = notif['created_at']?.split('T')?[0] ?? '';

                return GestureDetector(
                  onTap: () {
                    if (!isRead) markAsRead(notif['id']);
                  },
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: isRead ? Colors.white : const Color(0xFFF0F9FF),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: isRead ? Colors.transparent : const Color(0xFFFF8FAB).withOpacity(0.3)),
                      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 10)]
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(color: const Color(0xFFFFF0F3), shape: BoxShape.circle),
                          child: const Icon(Icons.notifications_active, color: Color(0xFFE63B6F), size: 18),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(title, style: TextStyle(fontWeight: isRead ? FontWeight.w600 : FontWeight.bold, fontSize: 14)),
                              const SizedBox(height: 4),
                              Text(message, style: TextStyle(color: const Color(0xFF475569), fontSize: 13, height: 1.4)),
                              const SizedBox(height: 6),
                              Text(date, style: const TextStyle(color: Colors.grey, fontSize: 11)),
                            ]
                          )
                        )
                      ],
                    ),
                  )
                );
              }
            )
    );
  }
}
