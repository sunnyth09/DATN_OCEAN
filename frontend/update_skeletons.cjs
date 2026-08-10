const fs = require('fs');
const path = require('path');

const targets = [
  'd:/source_code/laravel/qs_project/frontend/src/features/shop/pages/admin/AdminReview.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/shop/pages/admin/AdminReturnRequests.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/shop/pages/admin/AdminPostComments.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/hr/pages/AdminWorkShifts.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/support/pages/AdminTicketList.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/content/pages/admin/AdminPostCategory.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/admin/pages/AdminWalletWithdrawals.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/admin/pages/AdminWalletDeposits.vue'
];

for (const file of targets) {
  if (!fs.existsSync(file)) {
    console.log(`File not found: ${file}`);
    continue;
  }
  
  let content = fs.readFileSync(file, 'utf8');
  let modified = false;

  // Ensure import is there
  if (!content.includes('AdminTableSkeleton.vue')) {
    content = content.replace(
      /(<script setup>[\s\S]*?)(import [^\n]+;)/,
      '$1$2\nimport AdminTableSkeleton from \'@/components/AdminTableSkeleton.vue\';'
    );
    modified = true;
  }

  // Find the exact loading div block using a more robust matching if it spans multiple lines
  // We'll look for `<div v-if="...loading..." class="loading-state">` and its closing `</div>`
  const regex = /<div\s+v-if="([^"]*loading[^"]*)"\s+class="loading-state[^"]*"\s*>([\s\S]*?)<\/div>/i;
  let match = content.match(regex);
  
  if (match) {
    const loadingVar = match[1];
    const replacement = `<AdminTableSkeleton v-if="${loadingVar}" :columns="6" :rows="5" />`;
    content = content.replace(regex, replacement);
    
    // Also, we need to add v-else to the next table-container if it's there
    // But be careful not to replace it if it already has v-else
    if (!content.includes('<div v-else class="table-container')) {
        content = content.replace(/<div class="table-container/, '<div v-else class="table-container');
    }
    
    modified = true;
  }
  
  if (modified) {
    fs.writeFileSync(file, content, 'utf8');
    console.log(`Updated ${file}`);
  }
}
