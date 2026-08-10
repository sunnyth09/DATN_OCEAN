const fs = require('fs');

const targets = [
  'd:/source_code/laravel/qs_project/frontend/src/features/support/pages/AdminContact.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/shop/pages/admin/AdminUserRewards.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/shop/pages/admin/AdminRewards.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/hr/pages/AdminStaff.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/hr/pages/AdminAttendanceList.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/hr/pages/AdminFaceManagement.vue',
  'd:/source_code/laravel/qs_project/frontend/src/features/hr/pages/AdminWorkLocations.vue'
];

for (const file of targets) {
  if (!fs.existsSync(file)) {
    console.log(`Not found: ${file}`);
    continue;
  }
  
  let content = fs.readFileSync(file, 'utf8');
  let modified = false;

  // Add import if missing
  if (!content.includes('AdminTableSkeleton.vue')) {
    content = content.replace(
      /(<script setup>[\s\S]*?)(import [^\n]+;)/,
      '$1$2\nimport AdminTableSkeleton from \'@/components/AdminTableSkeleton.vue\';'
    );
    modified = true;
  }

  // Handle standard loading states that look like <div v-if="loading" class="loading-state">...</div>
  const regexDiv = /<div\s+v-if="([^"]*loading[^"]*)"\s+class="loading-state[^"]*"\s*>[\s\S]*?<\/div>/i;
  let matchDiv = content.match(regexDiv);
  if (matchDiv) {
    const loadingVar = matchDiv[1];
    const replacement = `<AdminTableSkeleton v-if="${loadingVar}" :columns="6" :rows="5" />`;
    // We only replace the exact match without nested divs if we use a safe regex
    // Since JS regex doesn't handle nested tags well, let's just do a string replace for the start tag and closing tag if they are simple
    content = content.replace(
        /<div\s+v-if="[^"]*loading[^"]*"\s+class="loading-state[^"]*"\s*>[\s\S]*?<\/div>/i,
        `<AdminTableSkeleton v-if="${loadingVar}" :columns="6" :rows="5" />`
    );
    
    // Add v-else to table container
    if (!content.includes('<div v-else class="table-container')) {
        content = content.replace(/<div class="table-container/, '<div v-else class="table-container');
    }
    // For HR pages it might be <div class="table-card"> or something
    if (!content.includes('<div v-else class="table-card')) {
        content = content.replace(/<div class="table-card/, '<div v-else class="table-card');
    }
    
    modified = true;
  }
  
  // Handle TR loading states
  const regexTr = /<tr\s+v-if="([^"]*loading[^"]*)"[^>]*>\s*<td[^>]*>[\s\S]*?<\/td>\s*<\/tr>/i;
  let matchTr = content.match(regexTr);
  if (matchTr) {
    const loadingVar = matchTr[1];
    // We need to pull the Skeleton outside the table
    // It's tricky to do via regex for tr. Let's just do this manually for TR if it occurs.
  }
  
  if (modified) {
    // Let's also run the fix for extra </p> just in case
    content = content.replace(/(<AdminTableSkeleton[^>]*>)\s*(?:<p>[^<]*<\/p>|<span>[^<]*<\/span>|\s*Đang[^<]*)\s*<\/div>/g, '$1');
    fs.writeFileSync(file, content, 'utf8');
    console.log(`Updated ${file}`);
  }
}
