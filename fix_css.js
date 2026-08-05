const fs = require('fs');
const path = require('path');

const homePath = path.join(__dirname, 'frontend/src/features/shop/pages/Home/Home.vue');
const sectionsPath = path.join(__dirname, 'frontend/src/features/shop/pages/Home/sections');
let homeContent = fs.readFileSync(homePath, 'utf8');

const styleMatch = homeContent.match(/<style>\s*([\s\S]*?)<\/style>/);
if (!styleMatch) {
    console.log('Style not found');
    process.exit(1);
}
let cssContent = styleMatch[1];

const blocks = [
    { name: 'HERO', file: 'HeroSection.vue', start: '1. HERO', end: '2. BENEFITS BAR' },
    { name: 'BENEFITS BAR', file: 'BenefitsBar.vue', start: '2. BENEFITS BAR', end: '3. FLASH SALE' },
    { name: 'FLASH SALE', file: 'FlashSaleSection.vue', start: '3. FLASH SALE', end: '4. CATEGORIES' },
    { name: 'CATEGORIES', file: 'CategoriesSection.vue', start: '4. CATEGORIES', end: '5. EQUIPMENT SECTION' },
    { name: 'EQUIPMENT SECTION', file: 'FeaturedProductsSection.vue', start: '5. EQUIPMENT SECTION', end: '6. BEST SELLERS TABS' },
    { name: 'BEST SELLERS TABS', file: 'BannerSection.vue', start: '6. BEST SELLERS TABS', end: '7. PROMO BANNERS' },
    { name: 'PROMO BANNERS', file: 'NewArrivalsSection.vue', start: '7. PROMO BANNERS', end: '8. BRAND MARQUEE' },
    { name: 'BRAND MARQUEE', file: 'TestimonialsSection.vue', start: '8. BRAND MARQUEE', end: '9. TESTIMONIALS' },
    { name: 'TESTIMONIALS', file: 'BlogSection.vue', start: '9. TESTIMONIALS', end: '10. COMMUNITY' },
    { name: 'COMMUNITY', file: 'CommunitySection.vue', start: '10. COMMUNITY', end: 'SKELETON' }
];

for (const block of blocks) {
    // We look for /* ======... \n NUMBER. TITLE 
    // Since the actual CSS has: /* ============================================\n     1. HERO
    const regex = new RegExp(`\\/\\* ={10,}\\s*\\n\\s*${block.start}\\s*([\\s\\S]*?)(?=\\/\\* ={10,}\\s*\\n\\s*${block.end})`);
    const match = cssContent.match(regex);
    if (match) {
        const componentCss = match[1].trim();
        const compPath = path.join(sectionsPath, block.file);
        if (fs.existsSync(compPath)) {
            let compContent = fs.readFileSync(compPath, 'utf8');
            if (compContent.includes('<style scoped>')) {
                compContent = compContent.replace('</style>', `\n${componentCss}\n</style>`);
            } else {
                compContent += `\n\n<style scoped>\n${componentCss}\n</style>\n`;
            }
            fs.writeFileSync(compPath, compContent);
            console.log(`Updated ${block.file}`);
        }
    } else {
        console.log(`Block not found: ${block.name}`);
    }
}

// Extract base CSS (from start to HERO) and RESPONSIVE / NEW UX/UI
const baseRegex = /^([\s\S]*?)(?=\/\* ={10,}\s*\n\s*1\. HERO)/;
const baseMatch = cssContent.match(baseRegex);
let remainingCss = baseMatch ? baseMatch[1] : '';

const respRegex = /\/\* ={10,}\s*\n\s*SKELETON\s*([\\s\\S]*)$/;
const respMatch = cssContent.match(respRegex);
if (respMatch) {
    remainingCss += '\n/* ============================================\n     SKELETON\n' + respMatch[1];
}

homeContent = homeContent.replace(/<style>\s*([\s\S]*?)<\/style>/, `<style scoped>\n${remainingCss}\n</style>`);
fs.writeFileSync(homePath, homeContent);
console.log('Updated Home.vue with remaining scoped CSS');
