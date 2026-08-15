import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../utils/format_utils.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/price_tag.dart';

class OrderScreen extends StatefulWidget {
  const OrderScreen({super.key});

  @override
  State<OrderScreen> createState() => _OrderScreenState();
}

class _OrderScreenState extends State<OrderScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<dynamic> allOrders = [];
  bool isLoading = true;
  bool isGuest = false;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 5, vsync: this);
    fetchOrders();
  }

  Future<void> fetchOrders() async {
    if (!mounted) return;
    setState(() {
      isLoading = true;
      errorMessage = null;
      isGuest = false;
    });

    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn) {
      if (mounted) {
        setState(() {
          isGuest = true;
          isLoading = false;
        });
      }
      return;
    }

    try {
      final response = await ApiClient().dio.get('/profile/orders');
      final decoded = response.data;
      List<dynamic> fetchedOrders = [];

      if (decoded is List) {
        fetchedOrders = decoded;
      } else if (decoded['data'] is List) {
        fetchedOrders = decoded['data'];
      } else if (decoded['data'] != null && decoded['data']['data'] is List) {
        fetchedOrders = decoded['data']['data'];
      } else if (decoded['orders'] is List) {
        fetchedOrders = decoded['orders'];
      }

      if (mounted) {
        setState(() {
          allOrders = fetchedOrders;
          isLoading = false;
        });
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        if (mounted) {
          setState(() {
            isGuest = true;
            isLoading = false;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            errorMessage = 'Lỗi truy xuất đơn hàng (${e.response?.statusCode})';
            isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi kết nối máy chủ.';
          isLoading = false;
        });
      }
    }
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Đơn Hàng Của Tôi',
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 18,
          ),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Tất cả'),
            Tab(text: 'Chờ xử lý'),
            Tab(text: 'Đang giao'),
            Tab(text: 'Hoàn thành'),
            Tab(text: 'Đã hủy'),
          ],
        ),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.primary),
      );
    }

    if (isGuest) {
      return AppEmptyState(
        icon: Icons.receipt_long_outlined,
        title: 'Chưa đăng nhập',
        message: 'Đăng nhập để xem và theo dõi lịch sử đơn hàng của bạn.',
        buttonText: 'Đăng nhập ngay',
        onAction: () async {
          await context.push('/login');
          fetchOrders();
        },
      );
    }

    if (errorMessage != null) {
      return AppEmptyState(
        icon: Icons.error_outline_rounded,
        title: 'Không thể tải đơn hàng',
        message: errorMessage!,
        buttonText: 'Thử lại',
        onAction: fetchOrders,
      );
    }

    return TabBarView(
      controller: _tabController,
      children: [
        _buildOrderList('all'),
        _buildOrderList('pending'),
        _buildOrderList('shipping'),
        _buildOrderList('completed'),
        _buildOrderList('cancelled'),
      ],
    );
  }

  Widget _buildOrderList(String statusFilter) {
    List<dynamic> filtered = allOrders;
    if (statusFilter != 'all') {
      filtered = allOrders.where((order) {
        String st = (order['fulfillment_status'] ?? order['status'] ?? '')
            .toString()
            .toLowerCase();
        if (statusFilter == 'pending' &&
            (st.contains('pending') || st.contains('processing'))) {
          return true;
        }
        if (statusFilter == 'shipping' &&
            (st.contains('shipping') || st.contains('delivering'))) {
          return true;
        }
        if (statusFilter == 'completed' &&
            (st.contains('completed') ||
                st.contains('delivered') ||
                st.contains('success'))) {
          return true;
        }
        if (statusFilter == 'cancelled' &&
            (st.contains('cancel') || st.contains('fail'))) {
          return true;
        }
        return false;
      }).toList();
    }

    if (filtered.isEmpty) {
      return AppEmptyState(
        icon: Icons.receipt_long_outlined,
        title: 'Không có đơn hàng nào',
        message: 'Chưa có đơn hàng nào ở trạng thái này.',
        buttonText: 'Mua sắm ngay',
        onAction: () => context.go('/shop'),
      );
    }

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: fetchOrders,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: filtered.length,
        itemBuilder: (context, index) {
          final order = filtered[index];
          final orderCode = order['order_code'] ?? order['id'].toString();
          final date = FormatUtils.formatDate(order['created_at']);
          final total = order['grand_total'] ?? order['total'] ?? 0;
          final status =
              (order['fulfillment_status'] ?? order['status'] ?? 'Unknown')
                  .toString()
                  .toUpperCase();

          Color statusColor = AppColors.textSecondary;
          Color statusBg = AppColors.surfaceDim;
          if (status.contains('PENDING')) {
            statusColor = AppColors.warning;
            statusBg = AppColors.warningLight;
          } else if (status.contains('SHIP')) {
            statusColor = AppColors.info;
            statusBg = AppColors.infoLight;
          } else if (status.contains('COMPLETED') ||
              status.contains('DELIVERED') ||
              status.contains('SUCCESS')) {
            statusColor = AppColors.success;
            statusBg = AppColors.successLight;
          } else if (status.contains('CANCEL') || status.contains('FAIL')) {
            statusColor = AppColors.error;
            statusBg = AppColors.errorLight;
          }

          return GestureDetector(
            onTap: () async {
              await context.push('/order-detail',
                  extra: (order['order_id'] ?? order['id']).toString());
              fetchOrders();
            },
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.border),
                boxShadow: AppShadows.card,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Đơn hàng: #$orderCode',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: statusBg,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          FormatUtils.translateStatus(status),
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  const Divider(),
                  const SizedBox(height: 10),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Ngày đặt: $date',
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 12,
                        ),
                      ),
                      PriceTag(
                        price: total,
                        fontSize: 15,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
