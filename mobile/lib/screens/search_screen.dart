import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

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

  void _performSearch(String query) {
    if (query.trim().isEmpty) return;
    _saveHistory(query);
    // Navigate to product list screen with search query
    context.push('/products', extra: {'searchQuery': query.trim()});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: AppColors.textPrimary),
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
                        setState(() {}); // Update suffix icon visibility
                      },
                    )
                  : null,
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            ),
            onChanged: (val) {
              setState(() {}); // For suffix icon
            },
          ),
        ),
      ),
      body: _recentSearches.isEmpty
          ? _buildEmptyState()
          : _buildRecentSearches(),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.search, size: 64, color: Colors.grey.shade300),
          const SizedBox(height: 16),
          Text(
            'Nhập từ khóa để tìm kiếm',
            style: TextStyle(color: Colors.grey.shade500),
          ),
        ],
      ),
    );
  }

  Widget _buildRecentSearches() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.all(16.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Tìm kiếm gần đây',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                  color: AppColors.textPrimary,
                ),
              ),
              InkWell(
                onTap: _clearHistory,
                child: const Text(
                  'Xóa lịch sử',
                  style: TextStyle(
                    color: AppColors.primary,
                    fontSize: 14,
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView.separated(
            itemCount: _recentSearches.length,
            separatorBuilder: (context, index) => Divider(height: 1, color: Colors.grey.shade200),
            itemBuilder: (context, index) {
              final query = _recentSearches[index];
              return ListTile(
                leading: const Icon(Icons.history, color: Colors.grey, size: 20),
                title: Text(query, style: const TextStyle(fontSize: 15)),
                trailing: const Icon(Icons.north_west, color: Colors.grey, size: 16),
                onTap: () {
                  _searchController.text = query;
                  _performSearch(query);
                },
              );
            },
          ),
        ),
      ],
    );
  }
}
