const fs = require('fs');
const path = require('path');

const sectionsPath = path.join(__dirname, 'frontend/src/features/shop/pages/Home/sections');
const files = fs.readdirSync(sectionsPath).filter(f => f.endsWith('.vue'));

for (const file of files) {
    const compPath = path.join(sectionsPath, file);
    let compContent = fs.readFileSync(compPath, 'utf8');
    
    // Replace dangling "============= */" right after <style scoped>
    compContent = compContent.replace(/<style scoped>\s*={10,}\s*\*\//g, '<style scoped>');
    fs.writeFileSync(compPath, compContent);
    console.log(`Cleaned ${file}`);
}
