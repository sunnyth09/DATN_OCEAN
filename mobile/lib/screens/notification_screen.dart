import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../services/storage_service.dart';
import '../utils/format_utils.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/app_toast.dart';
import 'package:dio/dio.dart';

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  List<dynamic> notifications = [];
  bool isLoading = true;
  bool _isFetching = false;
  // P1-04: Track trạng thái chưa đăng nhập để hiển thị đúng UI
  bool isUnauthenticated = false;
  int unreadCount = 0;

  @override
  void initState() {
    super.initState();
    fetchNotifications();
  }

  Future<void> fetchNotifications({bool silent = false}) async {
    if (_isFetching) return;
    _isFetching = true;
    if (!silent && mounted) setState(() { isLoading = true; isUnauthenticated = false; });

    // P1-04: Kiểm tra auth trước khi gọi API
    final token = StorageService.readSync('access_token') ?? await StorageService.read('access_token');
    if (token == null || token.trim().isEmpty || token == 'null') {
      _isFetching = false;
      if (mounted) setState(() { isLoading = false; isUnauthenticated = true; });
      return;
    }

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
          isUnauthenticated = false;
        });
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        if (mounted) setState(() { isLoading = false; isUnauthenticated = true; });
      } else {
        if (mounted) setState(() => isLoading = false);
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
        AppToast.showError(
          context,
          message: 'Không thể đánh dấu thông báo đã đọc.',
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
        AppToast.showError(
          context,
          message: 'Không thể đánh dấu tất cả thông báo đã đọc.',
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
          // P1-04: Hi\u1ec3n th\u1ecb \u0111\u00fang tr\u1ea1ng th\u00e1i khi ch\u01b0a \u0111\u0103ng nh\u1eadp
          : isUnauthenticated
              ? AppEmptyState(
                  icon: Icons.person_outline_rounded,
                  title: 'B\u1ea1n ch\u01b0a \u0111\u0103ng nh\u1eadp',
                  message: '\u0110\u0103ng nh\u1eadp \u0111\u1ec3 xem th\u00f4ng b\u00e1o v\u1ec1 \u0111\u01a1n h\u00e0ng v\u00e0 \u01b0u \u0111\u00e3i c\u1ee7a b\u1ea1n.',
                  buttonText: '\u0110\u0103ng nh\u1eadp ngay',
                  onAction: () async {
                    await context.push('/login');
                    if (mounted) fetchNotifications();
                  },
                )
          : notifications.isEmpty
              ? AppEmptyState(
                  icon: Icons.notifications_off_outlined,
                  title: 'Kh\u00f4ng c\u00f3 th\u00f4ng b\u00e1o m\u1edbi',
                  message: 'C\u00e1c th\u00f4ng b\u00e1o v\u1ec1 \u0111\u01a1n h\u00e0ng v\u00e0 \u01b0u \u0111\u00e3i s\u1ebd xu\u1ea5t hi\u1ec7n t\u1ea1i \u0111\u00e2y.',
                  buttonText: 'T\u1ea3i l\u1ea1i',
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
