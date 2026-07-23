import os, re

# Fix category_screen.dart
path = 'lib/screens/category_screen.dart'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace('provider.', 'context.read<CategoryProvider>().')
text = text.replace('final context.read<CategoryProvider>() = context.watch<CategoryProvider>();', '')

build_idx = text.find('Widget build(BuildContext context)')
if build_idx != -1:
    before = text[:build_idx]
    after = text[build_idx:]
    after = after.replace('context.read<CategoryProvider>()', 'context.watch<CategoryProvider>()')
    text = before + after

text = text.replace('currentPage', 'context.read<CategoryProvider>().currentPage')
text = text.replace('context.watch<CategoryProvider>().currentPage', 'context.read<CategoryProvider>().currentPage')
text = text.replace('_resetAndFetch();', '')
text = text.replace('context.watch<CategoryProvider>().updateSearch', 'context.read<CategoryProvider>().updateSearch')

with open(path, 'w', encoding='utf-8') as f:
    f.write(text)

# Also fix product_detail_screen.dart
path2 = 'lib/screens/product_detail_screen.dart'
with open(path2, 'r', encoding='utf-8') as f:
    text2 = f.read()

text2 = text2.replace('provider.', 'context.read<ProductDetailProvider>().')
text2 = text2.replace('final context.read<ProductDetailProvider>() = context.watch<ProductDetailProvider>();', '')

build_idx2 = text2.find('Widget build(BuildContext context)')
if build_idx2 != -1:
    before2 = text2[:build_idx2]
    after2 = text2[build_idx2:]
    after2 = after2.replace('context.read<ProductDetailProvider>()', 'context.watch<ProductDetailProvider>()')
    text2 = before2 + after2

with open(path2, 'w', encoding='utf-8') as f:
    f.write(text2)

print("Fixed")
