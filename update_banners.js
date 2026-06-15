const fs = require('fs');
const path = require('path');
const dir = 'h:/DATN_OCEAN/frontend/src/Pages/Client/Static';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.vue'));

const replacementCSS = `
.page-hero {
  background: linear-gradient(135deg, #e63b6f, #a0204e);
  color: #fff;
  border-radius: 16px;
  padding: 32px;
  margin: 24px auto 28px;
  max-width: 900px;
  width: calc(100% - 48px);
  position: relative;
  overflow: hidden;
  text-align: left;
}
.page-hero::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
}
.page-hero h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 8px; position: relative; z-index: 1; }
.hero-sub { opacity: 0.85; font-size: 0.95rem; max-width: 500px; margin: 0; position: relative; z-index: 1; line-height: 1.6; }
`;

for (let file of files) {
  const filePath = path.join(dir, file);
  let content = fs.readFileSync(filePath, 'utf8');
  
  let replaced = false;
  const regex1 = /\.page-hero\s*\{[^}]+\}\s*\.page-hero\s*h1\s*\{[^}]+\}\s*\.hero-sub\s*\{[^}]+\}/;
  if (regex1.test(content)) {
    content = content.replace(regex1, replacementCSS.trim());
    replaced = true;
  } else {
    const regex2 = /\.page-hero\s*\{[\s\S]*?\}\s*\.page-hero\s*h1\s*\{[\s\S]*?\}\s*\.hero-sub\s*\{[\s\S]*?\}/;
    if (regex2.test(content)) {
      content = content.replace(regex2, replacementCSS.trim());
      replaced = true;
    }
  }

  if (replaced) {
    fs.writeFileSync(filePath, content, 'utf8');
    console.log('Updated', file);
  } else {
    console.log('Pattern not found in', file);
  }
}
