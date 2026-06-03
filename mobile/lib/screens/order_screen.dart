import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../config/app_theme.dart';
import 'order_detail_screen.dart';
import 'login_screen.dart';

class OrderScreen extends StatefulWidget {
  const OrderScreen({super.key});

  @override
  State<OrderScreen> createState() => _OrderScreenState();
}

class _OrderScreenState extends State<OrderScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<dynamic> allOrders = [];
  bool isLoading = true;
  bool isGuest = false;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    fetchOrders();
  }

  Future<void> fetchOrders() async {
    if (!mounted) return;
    setState(() { isLoading = true; errorMessage = null; isGuest = false; });

    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn) {
      if (mounted) setState(() { isGuest = true; isLoading = false; });
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

      if (mounted) setState(() { allOrders = fetchedOrders; isLoading = false; });
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        if (mounted) setState(() { isGuest = true; isLoading = false; });
      } else {
        if (mounted) setState(() { errorMessage = 'Lỗi truy xuất đơn hàng (${e.response?.statusCode})'; isLoading = false; });
      }
    } catch (e) {
      if (mounted) setState(() { errorMessage = 'Lỗi kết nối máy chủ.'; isLoading = false; });
    }
  }


  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (m) => '${m[1]}.',
          );
      return '$formatted đ';
    } catch (_) {
      return price.toString();
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
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('Đơn Hàng Của Tôi', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.w800, fontSize: 18)),
        centerTitle: true,
        bottom: TabBar(
          controller: _tabController,
          labelColor: const Color(0xFFE63B6F),
          unselectedLabelColor: const Color(0xFF64748B),
          indicatorColor: const Color(0xFFE63B6F),
          isScrollable: true,
          tabs: const [
            Tab(text: 'Tất cả'),
            Tab(text: 'Chờ xử lý'),
            Tab(text: 'Đang giao'),
            Tab(text: 'Hoàn thành'),
          ],
        ),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(child: CircularProgressIndicator(color: Color(0xFFE63B6F)));
    }

    if (isGuest) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.receipt_long_outlined, size: 64, color: Color(0xFF94A3B8)),
              const SizedBox(height: 16),
              const Text('Chưa đăng nhập', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
              const SizedBox(height: 8),
              const Text('Đăng nhập để xem lịch sử đơn hàng của bạn.', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF64748B))),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () async {
                  await Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
                  fetchOrders();
                },
                style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE63B6F), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30))),
                child: const Text('Đăng nhập ngay', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.receipt_long_outlined, size: 60, color: Colors.grey),
            const SizedBox(height: 16),
            Text(errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: fetchOrders, child: const Text('Thử lại')),
          ],
        ),
      );
    }

    return TabBarView(
      controller: _tabController,
      children: [
        _buildOrderList('all'),
        _buildOrderList('pending'),
        _buildOrderList('shipping'),
        _buildOrderList('completed'),
      ],
    );
  }

  Widget _buildOrderList(String statusFilter) {
    List<dynamic> filtered = allOrders;
    // Map theo status, Backend có thể dùng 'fulfillment_status' hoặc 'status'
    if (statusFilter != 'all') {
      filtered = allOrders.where((order) {
        String st = (order['fulfillment_status'] ?? order['status'] ?? '').toString().toLowerCase();
        if (statusFilter == 'pending' && (st.contains('pending') || st.contains('processing'))) return true;
        if (statusFilter == 'shipping' && (st.contains('shipping') || st.contains('delivering'))) return true;
        if (statusFilter == 'completed' && (st.contains('completed') || st.contains('delivered') || st.contains('success'))) return true;
        return false;
      }).toList();
    }

    if (filtered.isEmpty) {
      return const Center(child: Text('Không có đơn hàng nào', style: TextStyle(color: Color(0xFF64748B))));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: filtered.length,
      itemBuilder: (context, index) {
        final order = filtered[index];
        final orderCode = order['order_code'] ?? order['id'].toString();
        final date = order['created_at']?.split('T')?[0] ?? 'N/A';
        final total = _formatPrice(order['grand_total'] ?? order['total']);
        final status = (order['fulfillment_status'] ?? order['status'] ?? 'Unknown').toString().toUpperCase();

        // Màu status cơ bản
        Color statusColor = const Color(0xFF64748B);
        if (status.contains('PENDING')) statusColor = Colors.orange;
        else if (status.contains('SHIP')) statusColor = Colors.blue;
        else if (status.contains('COMPLETED') || status.contains('DELIVERED') || status.contains('SUCCESS')) statusColor = Colors.green;

        return GestureDetector(
          onTap: () {
            Navigator.push(context, MaterialPageRoute(builder: (context) => OrderDetailScreen(orderId: order['order_id'].toString())));
          },
          child: Container(
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10, offset: const Offset(0, 4)),
              ]
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Đơn hàng: #$orderCode', style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: statusColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(status, style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                const Divider(color: Color(0xFFF1F5F9)),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Ngày đặt: $date', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
                    Text(total, style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFFE63B6F))),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
