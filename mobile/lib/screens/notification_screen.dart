import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';
import '../widgets/app_empty_state.dart';

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  List<dynamic> notifications = [];
  bool isLoading = true;
  bool _isFetching = false;
  int unreadCount = 0;

  @override
  void initState() {
    super.initState();
    fetchNotifications();
  }

  Future<void> fetchNotifications({bool silent = false}) async {
    if (_isFetching) return;
    _isFetching = true;
    if (!silent && mounted) setState(() => isLoading = true);

    try {
      final res = await ApiClient().dio.get('/profile/notifications');
      final payload = res.data['data'];
      final items = payload is Map ? payload['data'] : payload;
      if (mounted) {
        setState(() {
          notifications = items is List ? items : [];
          unreadCount =
              int.tryParse((res.data['unread_count'] ?? 0).toString()) ?? 0;
          isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => isLoading = false);
    } finally {
      _isFetching = false;
    }
  }

  Future<void> markAsRead(String id) async {
    try {
      await ApiClient().dio.post('/profile/notifications/$id/read');
      await fetchNotifications(silent: true);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Không thể đánh dấu thông báo đã đọc.'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await ApiClient().dio.post('/profile/notifications/read-all');
      await fetchNotifications(silent: true);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Không thể đánh dấu tất cả thông báo đã đọc.'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          unreadCount > 0 ? 'Thông báo ($unreadCount)' : 'Thông báo',
          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
        ),
        actions: [
          if (notifications.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.done_all_rounded),
              onPressed: markAllAsRead,
              tooltip: 'Đánh dấu tất cả đã đọc',
            ),
        ],
      ),
      body: isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : notifications.isEmpty
              ? AppEmptyState(
                  icon: Icons.notifications_off_outlined,
                  title: 'Không có thông báo mới',
                  message: 'Các thông báo về đơn hàng và ưu đãi sẽ xuất hiện tại đây.',
                  buttonText: 'Tải lại',
                  onAction: fetchNotifications,
                )
              : RefreshIndicator(
                  color: AppColors.primary,
                  onRefresh: fetchNotifications,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: notifications.length,
                    itemBuilder: (context, index) {
                      final notif = notifications[index];
                      final rawData = notif['data'];
                      final data = rawData is Map ? rawData : {};
                      final isRead = notif['read_at'] != null;
                      final title = data['title'] ?? 'Thông báo hệ thống';
                      final message = data['message'] ?? '';
                      final date = FormatUtils.formatDate(notif['created_at']);
                      final isCourt = data['type'] == 'court_booking';

                      return InkWell(
                        onTap: () {
                          if (!isRead) markAsRead(notif['id'].toString());
                          final orderId = data['order_id'] ?? data['orderId'];
                          final bookingId = data['booking_id'] ?? data['bookingId'];
                          final screen = data['screen']?.toString();

                          if (orderId != null) {
                            context.push('/order-detail', extra: orderId.toString());
                          } else if (bookingId != null || isCourt) {
                            context.push('/booking-history');
                          } else if (screen == 'orders') {
                            context.push('/orders');
                          } else if (screen == 'coupon' || screen == 'coupons') {
                            context.push('/my-coupons');
                          } else if (screen == 'flash_sale') {
                            context.push('/flash-sale');
                          }
                        },
                        borderRadius: BorderRadius.circular(18),
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: isRead ? Colors.white : AppColors.primaryContainer.withValues(alpha: 0.35),
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(
                              color: isRead
                                  ? AppColors.border
                                  : AppColors.primary.withValues(alpha: 0.3),
                            ),
                            boxShadow: AppShadows.card,
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: isCourt
                                      ? AppColors.courtLight
                                      : AppColors.primaryContainer,
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  isCourt
                                      ? Icons.sports_tennis_rounded
                                      : Icons.notifications_active_rounded,
                                  color: isCourt
                                      ? AppColors.court
                                      : AppColors.primary,
                                  size: 20,
                                ),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        Expanded(
                                          child: Text(
                                            title.toString(),
                                            style: TextStyle(
                                              fontWeight: isRead
                                                  ? FontWeight.w700
                                                  : FontWeight.w900,
                                              fontSize: 14,
                                              color: AppColors.textPrimary,
                                            ),
                                          ),
                                        ),
                                        if (!isRead)
                                          Container(
                                            width: 8,
                                            height: 8,
                                            decoration: const BoxDecoration(
                                              color: AppColors.primary,
                                              shape: BoxShape.circle,
                                            ),
                                          ),
                                      ],
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      message.toString(),
                                      style: const TextStyle(
                                        color: AppColors.textSecondary,
                                        fontSize: 13,
                                        height: 1.4,
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      date,
                                      style: const TextStyle(
                                        color: AppColors.textMuted,
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
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
