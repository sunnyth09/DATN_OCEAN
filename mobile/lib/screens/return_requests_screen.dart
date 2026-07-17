import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../config/app_theme.dart';

class ReturnRequestsScreen extends StatefulWidget {
  const ReturnRequestsScreen({super.key});

  @override
  State<ReturnRequestsScreen> createState() => _ReturnRequestsScreenState();
}

class _ReturnRequestsScreenState extends State<ReturnRequestsScreen> {
  List<dynamic> requests = [];
  bool isLoading = true;
  String currentFilter = 'all';
  int currentPage = 1;
  int lastPage = 1;

  final List<Map<String, String>> filterTabs = [
    {'value': 'all', 'label': 'Tất cả'},
    {'value': 'pending', 'label': 'Chờ xử lý'},
    {'value': 'approved', 'label': 'Đã duyệt'},
    {'value': 'rejected', 'label': 'Từ chối'},
    {'value': 'completed', 'label': 'Hoàn tất'},
  ];

  @override
  void initState() {
    super.initState();
    fetchReturnRequests();
  }

  Future<void> fetchReturnRequests({int page = 1}) async {
    try {
      setState(() => isLoading = true);
      final params = <String, dynamic>{'page': page};
      if (currentFilter != 'all') params['status'] = currentFilter;

      final res = await ApiClient().dio.get('/profile/return-requests', queryParameters: params);
      if (res.data['status'] == 'success') {
        final data = res.data['data'];
        if (data is Map) {
          if (mounted) {
            setState(() {
            requests = data['data'] ?? [];
            currentPage = data['current_page'] ?? 1;
            lastPage = data['last_page'] ?? 1;
            isLoading = false;
          });
          }
        } else if (data is List) {
          if (mounted) {
            setState(() {
            requests = data;
            isLoading = false;
          });
          }
        }
      } else {
        if (mounted) setState(() => isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => isLoading = false);
    }
  }

  String _formatDate(dynamic value) {
    if (value == null) return '—';
    final d = DateTime.tryParse(value.toString());
    if (d == null) return '—';
    return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
  }

  String _formatPrice(dynamic value) {
    try {
      final num p = num.parse(value.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(
        RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
        (m) => '${m[1]}.',
      );
      return '$formatted₫';
    } catch (_) {
      return '0₫';
    }
  }

  String _getStatusLabel(String? status) {
    switch (status) {
      case 'pending': return 'Chờ xử lý';
      case 'approved': return 'Đã duyệt';
      case 'rejected': return 'Từ chối';
      case 'completed': return 'Hoàn tất';
      case 'processing': return 'Đang xử lý';
      default: return status ?? 'Không rõ';
    }
  }

  Color _getStatusColor(String? status) {
    switch (status) {
      case 'pending': return const Color(0xFFD97706);
      case 'approved': return AppColors.success;
      case 'rejected': return AppColors.error;
      case 'completed': return AppColors.success;
      case 'processing': return AppColors.info;
      default: return const Color(0xFF475569);
    }
  }

  Color _getStatusBg(String? status) {
    switch (status) {
      case 'pending': return const Color(0xFFFEF3C7);
      case 'approved': return const Color(0xFFDCFCE3);
      case 'rejected': return const Color(0xFFFEE2E2);
      case 'completed': return const Color(0xFFDCFCE3);
      case 'processing': return const Color(0xFFDBEAFE);
      default: return const Color(0xFFF8FAFC);
    }
  }

  String _getRefundLabel(String? refundStatus) {
    switch (refundStatus) {
      case 'pending': return 'Chưa hoàn';
      case 'refunded': return 'Đã hoàn';
      case 'partial': return 'Hoàn một phần';
      default: return refundStatus ?? 'Chưa hoàn';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Yêu cầu hoàn hàng'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
      ),
      body: Column(
        children: [
          // Filter tabs
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: filterTabs.map((tab) {
                  final isActive = currentFilter == tab['value'];
                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: GestureDetector(
                      onTap: () {
                        setState(() => currentFilter = tab['value']!);
                        fetchReturnRequests();
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        decoration: BoxDecoration(
                          color: isActive ? AppColors.primary.withValues(alpha: 0.1) : Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: isActive ? AppColors.primary : const Color(0xFFCBD5E1)),
                        ),
                        child: Text(
                          tab['label']!,
                          style: TextStyle(
                            fontSize: 13, fontWeight: FontWeight.w600,
                            color: isActive ? AppColors.primary : const Color(0xFF475569),
                          ),
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ),
          // Content
          Expanded(
            child: isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : requests.isEmpty
                    ? _buildEmpty()
                    : RefreshIndicator(
                        onRefresh: () => fetchReturnRequests(),
                        child: ListView.separated(
                          padding: const EdgeInsets.all(16),
                          itemCount: requests.length + (lastPage > 1 ? 1 : 0),
                          separatorBuilder: (_, _) => const SizedBox(height: 12),
                          itemBuilder: (context, index) {
                            if (index == requests.length) return _buildPagination();
                            return _buildRequestCard(requests[index]);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.assignment_return_outlined, size: 64, color: Color(0xFFCBD5E1)),
          const SizedBox(height: 16),
          const Text('Chưa có yêu cầu hoàn hàng nào', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Color(0xFF0F172A))),
          const SizedBox(height: 8),
          const Text('Khi bạn gửi yêu cầu hoàn hàng, thông tin sẽ hiển thị ở đây.', textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
        ],
      ),
    );
  }

  Widget _buildRequestCard(Map<String, dynamic> item) {
    final status = item['status']?.toString();
    final order = item['order'] as Map<String, dynamic>?;
    final orderCode = order?['order_code'] ?? item['order_id']?.toString() ?? '';
    final reason = item['reason']?.toString() ?? '';
    final description = item['description']?.toString() ?? 'Không có mô tả bổ sung.';
    final refundAmount = item['refund_amount'];

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.02), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top row
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('#$orderCode', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
                      const SizedBox(height: 4),
                      Text(reason, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: Color(0xFF0F172A))),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: _getStatusBg(status),
                    borderRadius: BorderRadius.circular(30),
                    border: Border.all(color: _getStatusColor(status).withValues(alpha: 0.3)),
                  ),
                  child: Text(
                    _getStatusLabel(status),
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: _getStatusColor(status)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(description, style: const TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.55)),
            const SizedBox(height: 12),
            // Meta
            Wrap(
              spacing: 16,
              runSpacing: 6,
              children: [
                _metaItem('Gửi lúc: ${_formatDate(item['requested_at'] ?? item['created_at'])}'),
                _metaItem('Hoàn tiền: ${_getRefundLabel(item['refund_status']?.toString())}'),
                if (refundAmount != null && num.tryParse(refundAmount.toString()) != null && num.parse(refundAmount.toString()) > 0)
                  _metaItem('Số tiền: ${_formatPrice(refundAmount)}'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _metaItem(String text) {
    return Text(text, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)));
  }

  Widget _buildPagination() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          IconButton(
            onPressed: currentPage > 1 ? () => fetchReturnRequests(page: currentPage - 1) : null,
            icon: const Icon(Icons.chevron_left),
            color: AppColors.primary,
          ),
          Text('Trang $currentPage / $lastPage', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
          IconButton(
            onPressed: currentPage < lastPage ? () => fetchReturnRequests(page: currentPage + 1) : null,
            icon: const Icon(Icons.chevron_right),
            color: AppColors.primary,
          ),
        ],
      ),
    );
  }
}
