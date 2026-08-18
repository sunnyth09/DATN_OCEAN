import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../utils/format_utils.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/price_tag.dart';

class OrderScreen extends StatefulWidget {
  final int initialIndex;
  const OrderScreen({super.key, this.initialIndex = 0});

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
    _tabController = TabController(
      length: 7,
      vsync: this,
      initialIndex: widget.initialIndex.clamp(0, 6),
    );
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
      } else if (decoded is Map) {
        if (decoded['data'] is List) {
          fetchedOrders = decoded['data'];
        } else if (decoded['data'] is Map && decoded['data']['data'] is List) {
          fetchedOrders = decoded['data']['data'];
        } else if (decoded['orders'] is List) {
          fetchedOrders = decoded['orders'];
        }
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

  int _getCountForFilter(String filter) {
    if (filter == 'all') return allOrders.length;
    return allOrders.where((order) {
      String st = (order['fulfillment_status'] ?? order['status'] ?? '').toString().toLowerCase();
      String orderStatus = (order['order_status'] ?? order['status'] ?? '').toString().toLowerCase();

      if (filter == 'pending_confirmation') {
        return (st == 'pending' || orderStatus == 'pending' || orderStatus.contains('unpaid') || orderStatus.contains('waiting'));
      }
      if (filter == 'processing') {
        return (st.contains('processing') || st.contains('confirmed') || st.contains('ready') || st.contains('pickup') || orderStatus.contains('processing') || orderStatus.contains('confirmed'));
      }
      if (filter == 'shipping') {
        return (st.contains('shipping') || st.contains('delivering') || st.contains('transit') || orderStatus.contains('shipping') || orderStatus.contains('delivering'));
      }
      if (filter == 'to_review') {
        return (st.contains('completed') || st.contains('delivered') || st.contains('success') || orderStatus.contains('completed') || orderStatus.contains('delivered'));
      }
      if (filter == 'returns') {
        return (st.contains('return') || st.contains('refund') || orderStatus.contains('return') || orderStatus.contains('refund'));
      }
      if (filter == 'cancelled') {
        return (st.contains('cancel') || st.contains('fail') || orderStatus.contains('cancel') || orderStatus.contains('fail'));
      }
      return false;
    }).length;
  }

  Widget _buildTabItem(String title, int count) {
    return Tab(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(title),
          if (count > 0) ...[
            const SizedBox(width: 5),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
              decoration: BoxDecoration(
                color: const Color(0xFFEF4444),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                count > 99 ? '99+' : count.toString(),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
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
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/me');
            }
          },
        ),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          indicatorWeight: 2.5,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          tabs: [
            _buildTabItem('Tất cả', 0),
            _buildTabItem('Chờ xác nhận', _getCountForFilter('pending_confirmation')),
            _buildTabItem('Chờ lấy hàng', _getCountForFilter('processing')),
            _buildTabItem('Đang giao', _getCountForFilter('shipping')),
            _buildTabItem('Đánh giá', _getCountForFilter('to_review')),
            _buildTabItem('Trả hàng', _getCountForFilter('returns')),
            _buildTabItem('Đã hủy', _getCountForFilter('cancelled')),
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
        _buildOrderList('pending_confirmation'),
        _buildOrderList('processing'),
        _buildOrderList('shipping'),
        _buildOrderList('to_review'),
        _buildOrderList('returns'),
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
        String orderStatus = (order['order_status'] ?? order['status'] ?? '')
            .toString()
            .toLowerCase();

        if (statusFilter == 'pending_confirmation') {
          return (st == 'pending' || orderStatus == 'pending' || orderStatus.contains('unpaid') || orderStatus.contains('waiting'));
        }
        if (statusFilter == 'processing') {
          return (st.contains('processing') || st.contains('confirmed') || st.contains('ready') || st.contains('pickup') || orderStatus.contains('processing') || orderStatus.contains('confirmed'));
        }
        if (statusFilter == 'shipping') {
          return (st.contains('shipping') || st.contains('delivering') || st.contains('transit') || orderStatus.contains('shipping') || orderStatus.contains('delivering'));
        }
        if (statusFilter == 'to_review') {
          return (st.contains('completed') || st.contains('delivered') || st.contains('success') || orderStatus.contains('completed') || orderStatus.contains('delivered'));
        }
        if (statusFilter == 'returns') {
          return (st.contains('return') || st.contains('refund') || orderStatus.contains('return') || orderStatus.contains('refund'));
        }
        if (statusFilter == 'cancelled') {
          return (st.contains('cancel') || st.contains('fail') || orderStatus.contains('cancel') || orderStatus.contains('fail'));
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
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        itemCount: filtered.length,
        itemBuilder: (context, index) {
          final order = filtered[index];
          final orderId = (order['order_id'] ?? order['id']).toString();
          final orderCode = order['order_code'] ?? order['id'].toString();
          final date = FormatUtils.formatDate(order['created_at']);
          final total = order['grand_total'] ?? order['total'] ?? 0;
          final rawStatus = (order['fulfillment_status'] ?? order['status'] ?? 'Unknown').toString();
          final statusText = FormatUtils.translateStatus(rawStatus);

          Color statusColor = const Color(0xFF64748B);
          Color statusBg = const Color(0xFFF1F5F9);
          Color statusBorder = const Color(0xFFE2E8F0);

          final stUpper = rawStatus.toUpperCase();
          if (stUpper == 'PENDING' || stUpper.contains('UNPAID') || stUpper.contains('WAITING')) {
            statusColor = const Color(0xFFD97706);
            statusBg = const Color(0xFFFEF3C7);
            statusBorder = const Color(0xFFFDE68A);
          } else if (stUpper.contains('CONFIRMED') || stUpper.contains('PROCESSING') || stUpper.contains('PACKING') || stUpper.contains('READY') || stUpper.contains('PICKUP')) {
            statusColor = const Color(0xFF7C3AED);
            statusBg = const Color(0xFFF5F3FF);
            statusBorder = const Color(0xFFDDD6FE);
          } else if (stUpper.contains('SHIP') || stUpper.contains('DELIVERING') || stUpper.contains('TRANSIT')) {
            statusColor = const Color(0xFF2563EB);
            statusBg = const Color(0xFFEFF6FF);
            statusBorder = const Color(0xFFBFDBFE);
          } else if (stUpper.contains('COMPLETED') || stUpper.contains('DELIVERED') || stUpper.contains('SUCCESS')) {
            statusColor = const Color(0xFF16A34A);
            statusBg = const Color(0xFFF0FDF4);
            statusBorder = const Color(0xFFBBF7D0);
          } else if (stUpper.contains('RETURN') || stUpper.contains('REFUND')) {
            statusColor = const Color(0xFFEA580C);
            statusBg = const Color(0xFFFFF7ED);
            statusBorder = const Color(0xFFFFEDD5);
          } else if (stUpper.contains('CANCEL') || stUpper.contains('FAIL')) {
            statusColor = const Color(0xFFDC2626);
            statusBg = const Color(0xFFFEF2F2);
            statusBorder = const Color(0xFFFECACA);
          }

          final items = (order['items'] is List) ? (order['items'] as List) : [];
          final itemsCount = items.fold<int>(0, (sum, i) => sum + (int.tryParse(i['quantity']?.toString() ?? '1') ?? 1));

          return GestureDetector(
            onTap: () async {
              await context.push('/order-detail', extra: orderId);
              fetchOrders();
            },
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x0A000000),
                    blurRadius: 8,
                    offset: Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Header Row: Shop/Order code + Vietnamese Status Pill
                  Padding(
                    padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Row(
                            children: [
                              const Icon(Icons.storefront_outlined, size: 16, color: Color(0xFF64748B)),
                              const SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                  '#$orderCode',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 13.5,
                                    color: Color(0xFF1E293B),
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3.5),
                          decoration: BoxDecoration(
                            color: statusBg,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: statusBorder),
                          ),
                          child: Text(
                            statusText,
                            style: TextStyle(
                              color: statusColor,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Divider(height: 1, color: Color(0xFFF1F5F9)),

                  // Body: Danh sách sản phẩm trong đơn hàng
                  if (items.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        children: [
                          ...items.take(2).map((item) {
                            final product = item['product'] is Map ? item['product'] : null;
                            final variant = item['variant'] is Map ? item['variant'] : null;
                            final productName = product?['name']?.toString() ?? 'Sản phẩm thể thao';
                            final itemQty = item['quantity']?.toString() ?? '1';
                            final itemPrice = item['price'] ?? variant?['price'] ?? product?['price'] ?? 0;

                            String itemImg = '';
                            if (variant?['image_url'] != null && variant!['image_url'].toString().isNotEmpty) {
                              itemImg = AppConfig.imageUrl(variant['image_url'].toString());
                            } else if (product != null) {
                              itemImg = AppConfig.productImageUrl(product);
                            }

                            final variantParts = <String>[];
                            if (variant?['color'] != null && variant!['color'].toString().isNotEmpty) {
                              variantParts.add(variant['color'].toString());
                            }
                            if (variant?['size'] != null && variant!['size'].toString().isNotEmpty) {
                              variantParts.add('Size ${variant['size']}');
                            }
                            final variantLabel = variantParts.join(' | ');

                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(8),
                                    child: Container(
                                      width: 56,
                                      height: 56,
                                      color: Colors.white,
                                      child: NetworkImageWidget(
                                        imageUrl: itemImg,
                                        width: 56,
                                        height: 56,
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          productName,
                                          style: const TextStyle(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w600,
                                            color: Color(0xFF1E293B),
                                          ),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                        if (variantLabel.isNotEmpty) ...[
                                          const SizedBox(height: 2),
                                          Text(
                                            'Phân loại: $variantLabel',
                                            style: const TextStyle(
                                              fontSize: 11,
                                              color: Color(0xFF64748B),
                                            ),
                                          ),
                                        ],
                                        const SizedBox(height: 3),
                                        Text(
                                          'x$itemQty',
                                          style: const TextStyle(
                                            fontSize: 11.5,
                                            fontWeight: FontWeight.w700,
                                            color: Color(0xFF94A3B8),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  PriceTag(
                                    price: itemPrice,
                                    fontSize: 13,
                                    color: const Color(0xFF334155),
                                    fontWeight: FontWeight.w600,
                                  ),
                                ],
                              ),
                            );
                          }),
                          if (items.length > 2)
                            Padding(
                              padding: const EdgeInsets.only(top: 2),
                              child: Center(
                                child: Text(
                                  'Xem thêm ${items.length - 2} sản phẩm khác',
                                  style: const TextStyle(
                                    fontSize: 11.5,
                                    color: Color(0xFF64748B),
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    )
                  else
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      child: Text(
                        'Ngày đặt: $date',
                        style: const TextStyle(
                          color: Color(0xFF64748B),
                          fontSize: 12,
                        ),
                      ),
                    ),

                  const Divider(height: 1, color: Color(0xFFF1F5F9)),

                  // Footer: Tổng số lượng & Thành tiền & Action Buttons
                  Padding(
                    padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              '${itemsCount > 0 ? itemsCount : items.length} sản phẩm · $date',
                              style: const TextStyle(
                                fontSize: 11.5,
                                color: Color(0xFF64748B),
                              ),
                            ),
                            Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Text(
                                  'Thành tiền: ',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF64748B),
                                  ),
                                ),
                                PriceTag(
                                  price: total,
                                  fontSize: 15.5,
                                  color: const Color(0xFFEE2A35),
                                  fontWeight: FontWeight.w900,
                                ),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            OutlinedButton(
                              onPressed: () async {
                                await context.push('/order-detail', extra: orderId);
                                fetchOrders();
                              },
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                                minimumSize: Size.zero,
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                side: const BorderSide(color: Color(0xFFCBD5E1)),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              ),
                              child: const Text(
                                'Chi tiết đơn',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: Color(0xFF334155),
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            ElevatedButton(
                              onPressed: () {
                                if (stUpper.contains('COMPLETED') || stUpper.contains('DELIVERED') || stUpper.contains('SUCCESS') || stUpper.contains('CANCEL')) {
                                  context.push('/shop');
                                } else {
                                  context.push('/order-detail', extra: orderId);
                                }
                              },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                foregroundColor: Colors.white,
                                elevation: 0,
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                                minimumSize: Size.zero,
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              ),
                              child: Text(
                                (stUpper.contains('COMPLETED') || stUpper.contains('CANCEL')) ? 'Mua lại' : 'Theo dõi đơn',
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
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
    );
  }
}
