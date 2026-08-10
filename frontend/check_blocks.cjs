const fs = require('fs');

const files = [
  'd:/source_code/laravel/qs_project/frontend/src/features/hr/pages/AdminStaff.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/hr/pages/AdminAttendanceList.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/support/pages/AdminContact.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/content/pages/admin/AdminPostCategory.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/admin/pages/AdminStats.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/shop/pages/admin/AdminUserRewards.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/courts/pages/admin/AdminCourtReports.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/admin/pages/AdminHome.vue'
];

files.forEach(file => {
  const content = fs.readFileSync(file, 'utf8');
  let match = content.match(/<([a-z]+)[^>]*v-if=\"[a-zA-Z]*loading[a-zA-Z]*\"[^>]*>[\s\S]*?<\/\1>/i);
  let matchTr = content.match(/<tr[^>]*v-if=\"[^\"]*load[^\"]*\"[^>]*>[\s\S]*?<\/tr>/i);
  console.log('--- ' + file.split('/').pop() + ' ---');
  if (match) console.log(match[0].substring(0, 150).replace(/\n/g, ' '));
  else if (matchTr) console.log(matchTr[0].substring(0, 150).replace(/\n/g, ' '));
  else console.log('NO LOADING BLOCK FOUND');
});
