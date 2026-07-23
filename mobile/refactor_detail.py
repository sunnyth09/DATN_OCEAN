import os, re

path = 'lib/screens/product_detail_screen.dart'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

if 'package:provider/provider.dart' not in text:
    text = text.replace('import \'package:flutter/material.dart\';', 'import \'package:flutter/material.dart\';\nimport \'package:provider/provider.dart\';\nimport \'../providers/product_detail_provider.dart\';')
elif '../providers/product_detail_provider.dart' not in text:
    text = text.replace('import \'package:provider/provider.dart\';', 'import \'package:provider/provider.dart\';\nimport \'../providers/product_detail_provider.dart\';')

# Variables
vars_to_remove = [
    r'int _currentImageIndex = 0;\s*',
    r'String selectedColor = \'\';\s*',
    r'String selectedSize = \'\';\s*',
    r'List<dynamic> comments = \[\];\s*',
    r'bool isLoadingComments = true;\s*',
    r'Map<String, dynamic> _product = \{\};\s*',
    r'bool isLoadingDetails = true;\s*',
    r'List<dynamic> relatedProducts = \[\];\s*'
]
for v in vars_to_remove:
    text = re.sub(v, '', text)

# Methods
methods_to_remove = [
    r'Future<void> fetchProductDetails\(\).*?^  }',
    r'Future<void> fetchComments\(\).*?^  }',
    r'Future<void> fetchRelatedProducts\(\).*?^  }',
]
for m in methods_to_remove:
    text = re.sub(m, '', text, flags=re.MULTILINE | re.DOTALL)

# Build method
text = text.replace('Widget build(BuildContext context) {', 'Widget build(BuildContext context) {\n    final provider = context.watch<ProductDetailProvider>();\n    final _product = provider.product;\n    final selectedColor = provider.selectedColor;\n    final selectedSize = provider.selectedSize;')

# Replacements
replacements = {
    'isLoadingDetails': 'provider.isLoadingDetails',
    'isLoadingComments': 'provider.isLoadingComments',
    'comments': 'provider.comments',
    'relatedProducts': 'provider.relatedProducts',
}
def replace_word(word, replacement, txt):
    return re.sub(r'\b' + word + r'\b', replacement, txt)

for k, v in replacements.items():
    text = replace_word(k, v, text)

# selectColor / selectSize in UI
text = re.sub(r'setState\(\(\) \{\s*selectedColor = (.*?);\s*\}\);', r'context.read<ProductDetailProvider>().selectColor(\1);', text, flags=re.MULTILINE)
text = re.sub(r'setState\(\(\) \{\s*selectedSize = (.*?);\s*\}\);', r'context.read<ProductDetailProvider>().selectSize(\1);', text, flags=re.MULTILINE)

# initState
init_state_pattern = r'void initState\(\) \{.*?fetchRelatedProducts\(\);.*?\}'
new_init = '''void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProductDetailProvider>().fetchProductData(widget.product);
    });
  }'''
text = re.sub(init_state_pattern, new_init, text, flags=re.MULTILINE | re.DOTALL)

with open('lib/screens/product_detail_screen_new.dart', 'w', encoding='utf-8') as f:
    f.write(text)
print("Done")
