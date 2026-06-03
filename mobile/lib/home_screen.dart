import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'services/auth_service.dart';
import 'productDetail.dart';
import 'screens/product_list_screen.dart';
import 'screens/notification_screen.dart';
import 'package:dio/dio.dart';
import 'services/api_client.dart';
import 'widgets/shimmer_loading.dart';
import 'config/app_config.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  // ===== STATE =====
  List<dynamic> products = [];
  bool isLoading = true;
  String? errorMessage;
  int currentPage = 1;
  int totalPages = 1;
  int totalProducts = 0;
  String search = '';
  final ScrollController _scrollController = ScrollController();

  // ===== CATEGORIES =====
  List<dynamic> categories = [];
  bool isCatLoading = true;

  // ===== GỌI API =====
  Future<void> fetchProducts() async {
    if (!mounted) return;
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final response = await ApiClient().dio.get(
        '/products',
        queryParameters: {
          'page': currentPage,
          'search': search,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data;
        List<dynamic> fetched = [];

        if (data is List) {
          fetched = data;
          if (mounted) {
            setState(() {
              products = fetched;
              totalPages = 1;
              totalProducts = fetched.length;
              isLoading = false;
            });
          }
        } else if (data['data'] is List) {
          fetched = data['data'];
          if (mounted) {
            setState(() {
              products = fetched;
              totalPages = data['total_pages'] ?? 1;
              totalProducts = data['total'] ?? fetched.length;
              isLoading = false;
            });
          }
        }
      } else {
        if (mounted) {
          setState(() {
            errorMessage = 'Lỗi server: ${response.statusCode}';
            isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Không kết nối được API!\nLỗi: $e';
          isLoading = false;
        });
      }
    }
  }

  @override
  void initState() {
    super.initState();
    fetchProducts();
    fetchCategories();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            currentPage = 1;
            await fetchProducts();
          },
          child: CustomScrollView(
            controller: _scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: _buildHeader()),
              SliverToBoxAdapter(child: _buildSearchBar()),
              SliverToBoxAdapter(child: _buildHeroBanner()),
              SliverToBoxAdapter(child: _buildCategories()),
              _buildProductsSection(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text(
            'Quyền Sport',
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
          Row(
            children: [
              IconButton(icon: const Icon(Icons.notifications_none, color: Color(0xFF64748B)), onPressed: () {
                Navigator.push(context, MaterialPageRoute(builder: (context) => const NotificationScreen()));
              }),
              IconButton(icon: const Icon(Icons.grid_view, color: Color(0xFF64748B)), onPressed: () {}),
            ],
          )
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(30),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, 4)),
          ],
        ),
        child: TextField(
          decoration: InputDecoration(
            hintText: 'Bạn muốn tìm gì?',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 15),
            prefixIcon: const Icon(Icons.search, color: Color(0xFF94A3B8)),
            suffixIcon: const Icon(Icons.filter_list, color: Color(0xFFE63B6F)),
            border: InputBorder.none,
            contentPadding: const EdgeInsets.symmetric(vertical: 15),
          ),
          onChanged: (text) {
            // debounce 400ms
            Future.delayed(const Duration(milliseconds: 400), () {
              if (mounted) {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => ProductListScreen(searchQuery: text.trim()),
                  ),
                );
              }
            });
          },
          onSubmitted: (text) {
            if (text.trim().isNotEmpty) {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => ProductListScreen(searchQuery: text.trim()),
                ),
              );
            }
          },
        ),
      ),
    );
  }

  Widget _buildHeroBanner() {
    return Container(
      margin: const EdgeInsets.all(20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFE63B6F), Color(0xFFFF8FAB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Text('HOT DEAL', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(height: 12),
          const Text(
            'Mùa Hè Sôi Động\nCÙNG QUYỀN SPORT',
            style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, height: 1.2),
          ),
          const SizedBox(height: 8),
          const Text(
            'Giảm ngay 25% cho tất cả đồ thể thao chính hãng.',
            style: TextStyle(color: Colors.white70, fontSize: 13),
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () {},
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: const Color(0xFFE63B6F),
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
            ),
            child: const Text('Khám phá ngay', style: TextStyle(fontWeight: FontWeight.bold)),
          )
        ],
      ),
    );
  }

  // ===== FETCH CATEGORIES =====
  Future<void> fetchCategories() async {
    try {
      final res = await ApiClient().dio.get('/categories');
      final data = res.data['data'] as List? ?? [];
      // Chỉ lấy cấp 1 (parent_id == null hoặc == 0)
      final rootCats = data.where((c) {
        final pid = c['parent_id'];
        return pid == null || pid == 0;
      }).toList();
      if (mounted) setState(() { categories = rootCats; isCatLoading = false; });
    } catch (_) {
      if (mounted) setState(() => isCatLoading = false);
    }
  }

  /// Lấy icon thích hợp dựa trên tên danh mục
  IconData _iconForCategory(String name) {
    final n = name.toLowerCase();
    if (n.contains('lặn') || n.contains('bơi') || n.contains('dưới nước')) return Icons.scuba_diving;
    if (n.contains('lướt')) return Icons.surfing;
    if (n.contains('dã ngoại') || n.contains('leo núi') || n.contains('cắm trại')) return Icons.hiking;
    if (n.contains('phụ kiện') || n.contains('đồng hồ') || n.contains('kính')) return Icons.watch;
    if (n.contains('quần áo') || n.contains('thời trang') || n.contains('áo')) return Icons.checkroom;
    if (n.contains('giày') || n.contains('dép') || n.contains('sản phẩm')) return Icons.format_list_bulleted;
    if (n.contains('kayak') || n.contains('chèo') || n.contains('thỹền')) return Icons.rowing;
    if (n.contains('câu cá') || n.contains('bắt cá')) return Icons.phishing;
    if (n.contains('thể thao') || n.contains('sport')) return Icons.sports;
    if (n.contains('bảo hộ') || n.contains('an toàn')) return Icons.security;
    if (n.contains('đèn') || n.contains('chiếu sáng')) return Icons.flashlight_on;
    if (n.contains('tús') || n.contains('balo')) return Icons.backpack;
    if (n.contains('máy ảnh') || n.contains('camera') || n.contains('quay')) return Icons.camera_alt;
    if (n.contains('kife') || n.contains('dao') || n.contains('công cụ')) return Icons.handyman;
    if (n.contains('giày lặn') || n.contains('chân nhái')) return Icons.do_not_step;
    if (n.contains('xe') || n.contains('đạp')) return Icons.directions_bike;
    if (n.contains('sóng') || n.contains('biển')) return Icons.waves;
    return Icons.category_outlined;
  }

  /// Màu gradient theo index cho đẹp
  List<Color> _colorsForIndex(int index) {
    const palettes = [
      [Color(0xFFFFF0F3), Color(0xFFBAE6FD)],
      [Color(0xFFF0FDF4), Color(0xFFBBF7D0)],
      [Color(0xFFFFF7ED), Color(0xFFFED7AA)],
      [Color(0xFFFDF4FF), Color(0xFFF5D0FE)],
      [Color(0xFFFFF1F2), Color(0xFFFFCDD2)],
      [Color(0xFFF0F9FF), Color(0xFFB3E5FC)],
      [Color(0xFFF0FFF4), Color(0xFFB3DFBD)],
      [Color(0xFFFFFBEB), Color(0xFFFDE68A)],
    ];
    return palettes[index % palettes.length];
  }

  static const List<Color> _iconColors = [
    Color(0xFFE63B6F), Color(0xFF16A34A), Color(0xFFD97706),
    Color(0xFF9333EA), Color(0xFFE11D48), Color(0xFFE63B6F),
    Color(0xFF059669), Color(0xFFCA8A04),
  ];

  Widget _buildCategories() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Danh mục phổ biến',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
              TextButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ProductListScreen()),
                ),
                child: const Text('Xem tất cả', style: TextStyle(color: Color(0xFFE63B6F), fontWeight: FontWeight.w600)),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 110,
          child: isCatLoading
            ? ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 10),
                itemCount: 5,
                itemBuilder: (_, __) => Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  child: Column(
                    children: [
                      Container(width: 60, height: 60, decoration: BoxDecoration(color: Colors.grey.shade200, borderRadius: BorderRadius.circular(30))),
                      const SizedBox(height: 8),
                      Container(width: 50, height: 10, decoration: BoxDecoration(color: Colors.grey.shade200, borderRadius: BorderRadius.circular(4))),
                    ],
                  ),
                ),
              )
            : categories.isEmpty
              ? const Center(child: Text('Chưa có danh mục', style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  itemCount: categories.length,
                  itemBuilder: (context, index) {
                    final cat = categories[index];
                    final catName = cat['name']?.toString() ?? '';
                    final catId = cat['category_id'] ?? cat['id'];
                    final colors = _colorsForIndex(index);
                    final iconColor = _iconColors[index % _iconColors.length];
                    final icon = _iconForCategory(catName);

                    return GestureDetector(
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ProductListScreen(
                            categoryId: catId is int ? catId : int.tryParse(catId.toString()),
                            categoryName: catName,
                          ),
                        ),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 64,
                              height: 64,
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  colors: colors,
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                ),
                                borderRadius: BorderRadius.circular(20),
                                boxShadow: [
                                  BoxShadow(
                                    color: colors[1].withOpacity(0.5),
                                    blurRadius: 8,
                                    offset: const Offset(0, 3),
                                  ),
                                ],
                              ),
                              child: Icon(icon, color: iconColor, size: 30),
                            ),
                            const SizedBox(height: 8),
                            SizedBox(
                              width: 70,
                              child: Text(
                                catName,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF475569)),
                                textAlign: TextAlign.center,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildProductsSection() {
    return SliverPadding(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 20),
      sliver: SliverMainAxisGroup(
        slivers: [
          SliverToBoxAdapter(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                     const Text('Dành cho bạn', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
                     const Icon(Icons.more_horiz, color: Color(0xFF94A3B8))
                  ],
                ),
                const SizedBox(height: 16),
                if (errorMessage != null && !isLoading)
                  Center(child: Padding(padding: const EdgeInsets.all(20), child: Text(errorMessage!, style: const TextStyle(color: Colors.red))))
                else if (products.isEmpty && !isLoading)
                  const Center(child: Padding(padding: EdgeInsets.all(20), child: Text('Không có sản phẩm nào phù hợp'))),
              ],
            ),
          ),
          if (isLoading && products.isEmpty)
            const SliverShimmerLoading()
          else if (!isLoading && errorMessage == null && products.isNotEmpty)
            SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 16,
                mainAxisSpacing: 16,
                childAspectRatio: 0.65,
              ),
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  return _buildProductCard(products[index]);
                },
                childCount: products.length,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product) {
    final name = product['name'] ?? 'Không tên';
    final dynamic rawPrice = product['min_price'] ?? (product['lowest_price_variant'] != null ? product['lowest_price_variant']['price'] : 0);
    
    final rawImage = product['thumbnail_url'] ?? '';
    final imageUrl = AppConfig.imageUrl(rawImage.toString());

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => ProductDetailScreen(product: product),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 10, offset: const Offset(0, 4)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16)),
                  child: imageUrl.isNotEmpty
                      ? CachedNetworkImage(
                          imageUrl: imageUrl, 
                          height: 160, 
                          width: double.infinity, 
                          fit: BoxFit.cover,
                          placeholder: (context, url) => Container(height: 160, color: const Color(0xFFF1F5F9), child: const Center(child: CircularProgressIndicator(strokeWidth: 2))),
                          errorWidget: (context, url, error) => _imagePlaceholder(),
                        )
                      : _imagePlaceholder(),
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: GestureDetector(
                    onTap: () async {
                      try {
                        final loggedIn = await AuthService.isLoggedIn();
                        if (!loggedIn) {
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng đăng nhập để lưu!')));
                          }
                          return;
                        }
                        await ApiClient().dio.post(
                          '/profile/favorites/toggle',
                          data: {'product_id': product['product_id'] ?? product['id']}
                        );
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đã cập nhật danh sách yêu thích!'), duration: Duration(seconds: 1)));
                        }
                      } catch (_) {}
                    },
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                      child: const Icon(Icons.favorite_border, size: 16, color: Color(0xFF94A3B8)),
                    ),
                  ),
                )
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name.toString(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF1E293B), height: 1.3),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _formatPrice(rawPrice),
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: Color(0xFFE63B6F)),
                      ),
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(color: const Color(0xFFE63B6F), borderRadius: BorderRadius.circular(8)),
                        child: const Icon(Icons.shopping_cart_outlined, size: 14, color: Colors.white),
                      )
                    ],
                  )
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _imagePlaceholder() {
    return Container(height: 160, color: const Color(0xFFF1F5F9), child: const Center(child: Icon(Icons.image_not_supported, size: 30, color: Colors.grey)));
  }

  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]}.');
      return '$formatted đ';
    } catch (_) {
      return price.toString();
    }
  }

}
