import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import '../config/app_config.dart';
import '../services/api_client.dart';
import '../widgets/network_image_widget.dart';
import '../utils/format_utils.dart';
import '../widgets/app_empty_state.dart';

import '../config/app_theme.dart';

class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final TextEditingController _searchController = TextEditingController();
  final FocusNode _searchFocus = FocusNode();
  
  List<String> _recentSearches = [];
  final String _historyKey = 'recent_searches_v1';
  
  Timer? _debounce;
  List<dynamic> _searchResults = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadHistory();
    // Auto focus when screen opens
    Future.delayed(const Duration(milliseconds: 100), () {
      if (mounted) _searchFocus.requestFocus();
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _searchFocus.dispose();
    super.dispose();
  }

  Future<void> _loadHistory() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _recentSearches = prefs.getStringList(_historyKey) ?? [];
    });
  }

  Future<void> _saveHistory(String query) async {
    if (query.trim().isEmpty) return;
    
    final prefs = await SharedPreferences.getInstance();
    List<String> history = prefs.getStringList(_historyKey) ?? [];
    
    // Remove if already exists to put it at the top
    history.remove(query.trim());
    history.insert(0, query.trim());
    
    // Keep max 10 recent searches
    if (history.length > 10) {
      history = history.sublist(0, 10);
    }
    
    await prefs.setStringList(_historyKey, history);
    setState(() {
      _recentSearches = history;
    });
  }

  Future<void> _clearHistory() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_historyKey);
    setState(() {
      _recentSearches = [];
    });
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    
    final trimmedQuery = query.trim();
    if (trimmedQuery.isEmpty) {
      setState(() {
        _searchResults = [];
        _isLoading = false;
      });
      return;
    }

    setState(() {
      _isLoading = true;
    });

    _debounce = Timer(const Duration(milliseconds: 500), () {
      _fetchLiveResults(trimmedQuery);
    });
  }

  Future<void> _fetchLiveResults(String query) async {
    if (!mounted) return;
    try {
      final response = await ApiClient().dio.get(
        '/products',
        queryParameters: {'search': query, 'per_page': 10},
      );

      if (response.statusCode == 200) {
        final data = response.data;
        List<dynamic> fetched = [];
        if (data is List) {
          fetched = data;
        } else if (data['data'] is List) {
          fetched = data['data'];
        }

        if (mounted) {
          setState(() {
            _searchResults = fetched;
            _isLoading = false;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _searchResults = [];
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _searchResults = [];
          _isLoading = false;
        });
      }
    }
  }

  void _performSearch(String query) {
    if (query.trim().isEmpty) return;
    _saveHistory(query);
    // Navigate to product list screen with search query
    context.push('/product-list', extra: {'searchQuery': query.trim()});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: AppColors.textPrimary),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
        ),
        titleSpacing: 0,
        title: Container(
          height: 40,
          margin: const EdgeInsets.only(right: 16),
          decoration: BoxDecoration(
            color: Colors.grey.shade100,
            borderRadius: BorderRadius.circular(20),
          ),
          child: TextField(
            controller: _searchController,
            focusNode: _searchFocus,
            textInputAction: TextInputAction.search,
            onSubmitted: _performSearch,
            decoration: InputDecoration(
              hintText: 'Tìm kiếm sản phẩm...',
              hintStyle: TextStyle(color: Colors.grey.shade500, fontSize: 14),
              prefixIcon: Icon(Icons.search, color: Colors.grey.shade500, size: 20),
              suffixIcon: _searchController.text.isNotEmpty
                  ? IconButton(
                      icon: Icon(Icons.close, color: Colors.grey.shade500, size: 18),
                      onPressed: () {
                        _searchController.clear();
                        setState(() {});
                      },
                    )
                  : IconButton(
                      icon: const Icon(Icons.camera_alt_outlined, color: AppColors.primary, size: 19),
                      onPressed: () {
                        context.push('/product-scanner');
                      },
                    ),
              isDense: true,
              filled: false,
              border: InputBorder.none,
              enabledBorder: InputBorder.none,
              focusedBorder: InputBorder.none,
              errorBorder: InputBorder.none,
              focusedErrorBorder: InputBorder.none,
              disabledBorder: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            ),
            onChanged: (val) {
              setState(() {}); // For suffix icon
              _onSearchChanged(val);
            },
          ),
        ),
      ),
      body: _searchController.text.trim().isEmpty
          ? (_recentSearches.isEmpty ? _buildEmptyState() : _buildRecentSearches())
          : _buildLiveSearchView(),
    );
  }

  final List<String> _popularKeywords = [
    'Vợt Tennis',
    'Cầu Lông',
    'Babolat',
    'Wilson',
    'Yonex',
    'Giày thể thao',
    'Túi vợt',
    'Bóng tennis',
  ];

  Widget _buildEmptyState() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildPopularSearches(),
        ],
      ),
    );
  }

  Widget _buildRecentSearches() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Lịch sử tìm kiếm',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 14.5,
                  color: AppColors.textPrimary,
                ),
              ),
              InkWell(
                onTap: _clearHistory,
                child: const Text(
                  'Xóa tất cả',
                  style: TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w600,
                    fontSize: 12.5,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _recentSearches.map((query) {
              return InkWell(
                onTap: () {
                  _searchController.text = query;
                  _performSearch(query);
                },
                borderRadius: BorderRadius.circular(20),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.history_rounded, size: 14, color: AppColors.textMuted),
                      const SizedBox(width: 6),
                      Text(
                        query,
                        style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                      ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 24),
          _buildPopularSearches(),
        ],
      ),
    );
  }

  Widget _buildPopularSearches() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.local_fire_department_rounded, color: Color(0xFFEF4444), size: 18),
            SizedBox(width: 6),
            Text(
              'Từ khóa phổ biến',
              style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 14.5,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: _popularKeywords.map((keyword) {
            return InkWell(
              onTap: () {
                _searchController.text = keyword;
                _performSearch(keyword);
              },
              borderRadius: BorderRadius.circular(20),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFFFD1DC)),
                ),
                child: Text(
                  keyword,
                  style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.primary),
                ),
              ),
            );
          }).toList(),
        ),
      ],
    );
  }

  Widget _buildLiveSearchView() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    }
    if (_searchResults.isEmpty) {
      return AppEmptyState(
        icon: Icons.search_off_rounded,
        title: 'Không tìm thấy kết quả',
        message: 'Rất tiếc, không có sản phẩm nào khớp với từ khóa "${_searchController.text}".',
      );
    }
    return ListView.separated(
      itemCount: _searchResults.length,
      separatorBuilder: (context, index) => Divider(height: 1, color: Colors.grey.shade200),
      itemBuilder: (context, index) {
        final product = _searchResults[index];
        final name = product['name'] ?? 'Không có tên';
        final price = product['min_price'] ?? product['price'] ?? 0;
        String imageUrl = AppConfig.productImageUrl(product);

        return ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          leading: SizedBox(
            width: 50,
            height: 50,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: NetworkImageWidget(
                imageUrl: imageUrl,
                width: 50,
                height: 50,
                fit: BoxFit.cover,
                errorWidget: Container(
                  color: Colors.grey.shade200,
                  child: const Icon(Icons.image, color: Colors.grey),
                ),
              ),
            ),
          ),
          title: Text(
            name,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
          ),
          subtitle: Text(
            FormatUtils.formatPrice(price),
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: AppColors.primary,
            ),
          ),
          onTap: () {
            _saveHistory(_searchController.text);
            context.push('/product-detail', extra: product);
          },
        );
      },
    );
  }
}
