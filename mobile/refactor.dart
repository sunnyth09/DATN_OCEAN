import 'dart:io';

void main() async {
  final file = File('lib/screens/category_screen.dart');
  String text = await file.readAsString();

  if (!text.contains('package:provider/provider.dart')) {
    text = text.replaceFirst(
      "import 'package:flutter/material.dart';",
      "import 'package:flutter/material.dart';\nimport 'package:provider/provider.dart';\nimport '../providers/category_provider.dart';"
    );
  }

  // Remove variables
  final varsToRemove = [
    RegExp(r'List<dynamic> products = \[\];\s*'),
    RegExp(r'bool isLoading = true;\s*'),
    RegExp(r'bool isFetchingMore = false;\s*'),
    RegExp(r'bool hasMore = true;\s*'),
    RegExp(r'String\? errorMessage;\s*'),
    RegExp(r'int currentPage = 1;\s*'),
    RegExp(r'List<dynamic> categories = \[\];\s*'),
    RegExp(r'int\? selectedCategoryId;\s*'),
    RegExp(r'String\? selectedCategoryName;\s*'),
    RegExp(r"String _sortBy = 'newest'; // newest \| price_asc \| price_desc \| popular\s*"),
    RegExp(r'RangeValues _priceRange = const RangeValues\(0, 50000000\);\s*'),
    RegExp(r'bool _filterInStock = false;\s*'),
    RegExp(r"String _searchQuery = '';\s*"),
  ];

  for (final r in varsToRemove) {
    text = text.replaceAll(r, '');
  }

  // Remove methods
  final methodsToRemove = [
    RegExp(r'Future<void> _loadAll\(\).*?^  }', multiLine: true, dotAll: true),
    RegExp(r'Future<void> fetchCategories\(\).*?^  }', multiLine: true, dotAll: true),
    RegExp(r'Future<void> fetchProducts\(\{.*?^  }', multiLine: true, dotAll: true),
    RegExp(r'void _resetAndFetch\(\).*?^  }', multiLine: true, dotAll: true),
    RegExp(r'void _onSearchChanged\(.*?^  }', multiLine: true, dotAll: true),
    RegExp(r'void _selectCategory\(.*?^  }', multiLine: true, dotAll: true),
  ];

  for (final r in methodsToRemove) {
    text = text.replaceAll(r, '');
  }

  // Build method
  text = text.replaceFirst(
    'Widget build(BuildContext context) {',
    'Widget build(BuildContext context) {\n    final provider = context.watch<CategoryProvider>();'
  );

  // Replacements
  final Map<String, String> wordReplacements = {
    'isLoading': 'provider.isLoading',
    'isFetchingMore': 'provider.isFetchingMore',
    'hasMore': 'provider.hasMore',
    'errorMessage': 'provider.errorMessage',
    'products': 'provider.products',
    'categories': 'provider.categories',
    'selectedCategoryId': 'provider.selectedCategoryId',
    'selectedCategoryName': 'provider.selectedCategoryName',
    '_sortBy': 'provider.sortBy',
    '_priceRange': 'provider.priceRange',
    '_filterInStock': 'provider.filterInStock',
    '_searchQuery': 'provider.searchQuery',
  };

  wordReplacements.forEach((key, value) {
    text = text.replaceAll(RegExp(r'\b' + key + r'\b'), value);
  });

  text = text.replaceAll('_resetAndFetch();', '');
  text = text.replaceAll('_selectCategory', 'provider.selectCategory');
  text = text.replaceAll('_onSearchChanged', 'provider.updateSearch');
  text = text.replaceAll('fetchProducts(loadMore: true)', 'provider.loadMore()');

  // Fix initState
  text = text.replaceAllMapped(
    RegExp(r'void initState\(\) \{.*?_loadAll\(\);.*?\}', multiLine: true, dotAll: true),
    (match) => '''void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CategoryProvider>().loadAll();
    });
    _scrollCtrl.addListener(_onScroll);
  }'''
  );

  // Fix onScroll
  text = text.replaceAllMapped(
    RegExp(r'void _onScroll\(\) \{.*?\}', multiLine: true, dotAll: true),
    (match) => '''void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 250) {
      context.read<CategoryProvider>().loadMore();
    }
  }'''
  );

  // Fix apply filter
  text = text.replaceAllMapped(
    RegExp(r'setState\(\(\) \{\s*provider.sortBy = tmpSort;\s*provider.priceRange = tmpPrice;\s*provider.filterInStock = tmpInStock;\s*\}\);', multiLine: true, dotAll: true),
    (match) => 'context.read<CategoryProvider>().applyFilters(sort: tmpSort, price: tmpPrice, inStock: tmpInStock);'
  );

  // Fix hasActiveFilter
  text = text.replaceAll(
    'bool get _hasActiveFilter =>\n      provider.selectedCategoryId != null ||\n      provider.searchQuery.isNotEmpty ||\n      provider.filterInStock ||\n      provider.priceRange.start > 0 ||\n      provider.priceRange.end < 50000000 ||\n      provider.sortBy != \'newest\';',
    'bool get _hasActiveFilter => provider.selectedCategoryId != null || provider.searchQuery.isNotEmpty || provider.filterInStock || provider.priceRange.start > 0 || provider.priceRange.end < 50000000 || provider.sortBy != \'newest\';'
  );

  await File('lib/screens/category_screen_new.dart').writeAsString(text);
  print('Done');
}
