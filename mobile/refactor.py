import os, re

path = 'lib/screens/category_screen.dart'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

if 'package:provider/provider.dart' not in text:
    text = text.replace('import \'package:flutter/material.dart\';', 'import \'package:flutter/material.dart\';\nimport \'package:provider/provider.dart\';\nimport \'../providers/category_provider.dart\';')

vars_to_remove = [
    r'List<dynamic> products = \[\];\s*',
    r'bool isLoading = true;\s*',
    r'bool isFetchingMore = false;\s*',
    r'bool hasMore = true;\s*',
    r'String\? errorMessage;\s*',
    r'int currentPage = 1;\s*',
    r'List<dynamic> categories = \[\];\s*',
    r'int\? selectedCategoryId;\s*',
    r'String\? selectedCategoryName;\s*',
    r'String _sortBy = \'newest\'; // newest \| price_asc \| price_desc \| popular\s*',
    r'RangeValues _priceRange = const RangeValues\(0, 50000000\);\s*',
    r'bool _filterInStock = false;\s*',
    r'String _searchQuery = \'\';\s*'
]
for v in vars_to_remove:
    text = re.sub(v, '', text)

methods_to_remove = [
    r'Future<void> _loadAll\(\).*?^  }',
    r'Future<void> fetchCategories\(\).*?^  }',
    r'Future<void> fetchProducts\(\{.*?^  }',
    r'void _resetAndFetch\(\).*?^  }',
    r'void _onSearchChanged\(.*?^  }',
    r'void _selectCategory\(.*?^  }',
]
for m in methods_to_remove:
    text = re.sub(m, '', text, flags=re.MULTILINE | re.DOTALL)

text = text.replace('Widget build(BuildContext context) {', 'Widget build(BuildContext context) {\n    final provider = context.watch<CategoryProvider>();')

replacements = {
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
}

def replace_word(word, replacement, txt):
    return re.sub(r'\b' + word + r'\b', replacement, txt)

for k, v in replacements.items():
    text = replace_word(k, v, text)

text = text.replace('fetchProducts(loadMore: true)', 'provider.loadMore()')
text = text.replace('_resetAndFetch();', '')
text = text.replace('_selectCategory', 'provider.selectCategory')
text = text.replace('_onSearchChanged', 'provider.updateSearch')

init_state_pattern = r'void initState\(\) \{.*?_loadAll\(\);.*?\}'
new_init = '''void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CategoryProvider>().loadAll();
    });
    _scrollCtrl.addListener(_onScroll);
  }'''
text = re.sub(init_state_pattern, new_init, text, flags=re.MULTILINE | re.DOTALL)

on_scroll_pattern = r'void _onScroll\(\) \{.*?\}'
new_on_scroll = '''void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 250) {
      context.read<CategoryProvider>().loadMore();
    }
  }'''
text = re.sub(on_scroll_pattern, new_on_scroll, text, flags=re.MULTILINE | re.DOTALL)

apply_btn_pattern = r'setState\(\(\) \{\s*provider.sortBy = tmpSort;\s*provider.priceRange = tmpPrice;\s*provider.filterInStock = tmpInStock;\s*\}\);'
new_apply_btn = '''context.read<CategoryProvider>().applyFilters(sort: tmpSort, price: tmpPrice, inStock: tmpInStock);'''
text = re.sub(apply_btn_pattern, new_apply_btn, text, flags=re.MULTILINE | re.DOTALL)

with open('lib/screens/category_screen_new.dart', 'w', encoding='utf-8') as f:
    f.write(text)
print("Done")
