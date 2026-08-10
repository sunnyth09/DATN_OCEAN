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
    continue;
  }
  
  let content = fs.readFileSync(file, 'utf8');
  
  // Find <AdminTableSkeleton ... /> followed by <p>...</p> </div> or <span>...</span> </div>
  const regex = /(<AdminTableSkeleton[^>]*>)\s*(?:<p>[^<]*<\/p>|<span>[^<]*<\/span>|\s*Đang[^<]*)\s*<\/div>/g;
  
  if (regex.test(content)) {
    content = content.replace(regex, '$1');
    fs.writeFileSync(file, content, 'utf8');
    console.log(`Fixed ${file}`);
  }
}
