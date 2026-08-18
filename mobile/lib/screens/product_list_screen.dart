import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../widgets/shimmer_loading.dart';
import '../widgets/product_card.dart';

class ProductListScreen extends StatefulWidget {
  final int? categoryId;
  final String? categoryName;
  final String? searchQuery;

  const ProductListScreen({
    super.key,
    this.categoryId,
    this.categoryName,
    this.searchQuery,
  });

  @override
  State<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends State<ProductListScreen> {
  List<dynamic> products = [];
  bool isLoading = true;
  bool isFetchingMore = false;
  bool hasMore = true;
  String? errorMessage;
  int currentPage = 1;
  String currentSearch = '';

  late TextEditingController _searchCtrl;
  Timer? _debounce;
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    currentSearch = widget.searchQuery ?? '';
    _searchCtrl = TextEditingController(text: currentSearch);

    _scrollController.addListener(_onScroll);
    fetchProducts();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _debounce?.cancel();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      if (!isLoading && !isFetchingMore && hasMore) {
        setState(() {
          currentPage++;
        });
        fetchProducts(loadMore: true);
      }
    }
  }

  void _onSearchChanged(String text) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      if (mounted) {
        setState(() {
          currentSearch = text.trim();
          currentPage = 1;
          hasMore = true;
        });
        fetchProducts();
      }
    });
  }

  Future<void> fetchProducts({bool loadMore = false}) async {
    if (!mounted) return;

    setState(() {
      if (loadMore) {
        isFetchingMore = true;
      } else {
        isLoading = true;
        errorMessage = null;
        products.clear();
      }
    });

    try {
      final Map<String, dynamic> params = {'page': currentPage};
      if (widget.categoryId != null) {
        params['category_id'] = widget.categoryId;
      }
      if (currentSearch.isNotEmpty) {
        params['search'] = currentSearch;
      }

      final response = await ApiClient().dio.get(
        '/products',
        queryParameters: params,
      );

      if (response.statusCode == 200) {
        final data = response.data;
        List<dynamic> fetched = [];

        if (data is List) {
          fetched = data;
          hasMore = false; // Usually not paginated if it returns a flat array
        } else if (data['data'] is List) {
          fetched = data['data'];
          if (data['page'] != null && data['total_pages'] != null) {
            hasMore =
                (int.parse(data['page'].toString()) <
                int.parse(data['total_pages'].toString()));
          } else {
            hasMore = fetched.isNotEmpty;
          }
        }

        if (mounted) {
          setState(() {
            if (loadMore) {
              products.addAll(fetched);
            } else {
              products = fetched;
            }
            isLoading = false;
            isFetchingMore = false;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            errorMessage = 'Lỗi truy xuất (${response.statusCode})';
            isLoading = false;
            isFetchingMore = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Không thể kết nối đến máy chủ';
          isLoading = false;
          isFetchingMore = false;
          if (loadMore) currentPage--; // Revert page on failure
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    String title = "Danh sách sản phẩm";
    if (widget.categoryName != null) {
      title = widget.categoryName!;
    } else if (widget.searchQuery != null && widget.searchQuery!.isNotEmpty) {
      title = 'Tìm kiếm: "${widget.searchQuery}"';
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // Màu nền nhẹ kiểu dáng Figma
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF0F172A)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/shop');
            }
          },
        ),
        title: Text(
          title,
          style: const TextStyle(
            color: Color(0xFF0F172A),
            fontWeight: FontWeight.w800,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(70),
          child: Padding(
            padding: const EdgeInsets.only(left: 20, right: 20, bottom: 16),
            child: Container(
              height: 48,
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9), // Màu nền search box figma
                borderRadius: BorderRadius.circular(24),
              ),
              child: TextField(
                controller: _searchCtrl,
                onChanged: _onSearchChanged,
                onSubmitted: (t) {
                  _debounce?.cancel();
                  setState(() {
                    currentSearch = t.trim();
                    currentPage = 1;
                    hasMore = true;
                  });
                  fetchProducts();
                },
                decoration: InputDecoration(
                  hintText: 'Tìm kiếm sản phẩm...',
                  hintStyle: const TextStyle(
                    color: Color(0xFF64748B),
                    fontSize: 14,
                  ),
                  prefixIcon: const Icon(
                    Icons.search,
                    color: Color(0xFF64748B),
                    size: 20,
                  ),
                  suffixIcon: _searchCtrl.text.isNotEmpty
                      ? IconButton(
                          icon: const Icon(
                            Icons.close,
                            color: Color(0xFF64748B),
                            size: 18,
                          ),
                          onPressed: () {
                            _searchCtrl.clear();
                            _onSearchChanged('');
                          },
                        )
                      : null,
                  isDense: true,
                  filled: false,
                  border: InputBorder.none,
                  enabledBorder: InputBorder.none,
                  focusedBorder: InputBorder.none,
                  errorBorder: InputBorder.none,
                  focusedErrorBorder: InputBorder.none,
                  disabledBorder: InputBorder.none,
                  contentPadding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ),
        ),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () async {
        setState(() {
          currentPage = 1;
          hasMore = true;
        });
        await fetchProducts();
      },
      child: CustomScrollView(
        controller: _scrollController,
        cacheExtent: 800,
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        slivers: [
          if (isLoading && products.isEmpty)
            const SliverShimmerLoading(
              padding: EdgeInsets.all(20),
              crossAxisSpacing: 16,
              mainAxisSpacing: 20,
              childAspectRatio: 0.62,
            )
          else if (errorMessage != null && products.isEmpty)
            SliverToBoxAdapter(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.all(40),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(
                        Icons.error_outline,
                        size: 48,
                        color: Colors.red,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        errorMessage!,
                        style: const TextStyle(color: Colors.red),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () => fetchProducts(),
                        child: const Text('Thử lại'),
                      ),
                    ],
                  ),
                ),
              ),
            )
          else if (products.isEmpty)
            SliverToBoxAdapter(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.only(top: 100),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.inventory_2_outlined,
                        size: 100,
                        color: const Color(0xFFCBD5E1).withValues(alpha: 0.5),
                      ),
                      const SizedBox(height: 20),
                      const Text(
                        'Không có sản phẩm nào.',
                        style: TextStyle(
                          color: Color(0xFF64748B),
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.all(16),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 14,
                  childAspectRatio: 0.65,
                ),
                delegate: SliverChildBuilderDelegate(
                  (context, index) {
                    return ProductCard(product: products[index]);
                  },
                  childCount: products.length,
                  addRepaintBoundaries: true,
                  addAutomaticKeepAlives: false,
                ),
              ),
            ),
          if (isFetchingMore)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.only(bottom: 30),
                child: Center(
                  child: CircularProgressIndicator(color: AppColors.primary),
                ),
              ),
            ),
          if (!hasMore && products.length > 5)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.only(bottom: 30),
                child: Center(
                  child: Text(
                    'Bạn đã xem hết sản phẩm',
                    style: TextStyle(color: Color(0xFF64748B)),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
