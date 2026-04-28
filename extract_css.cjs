const fs = require('fs');
const path = require('path');

const pagesDir = path.join(__dirname, 'resources/js/Pages');
const appCssPath = path.join(__dirname, 'resources/css/app.css');

let combinedCss = '\n/* --- Extracted from Vue Components --- */\n';

function traverseDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            traverseDir(fullPath);
        } else if (fullPath.endsWith('.vue')) {
            processVueFile(fullPath);
        }
    }
}

function processVueFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
    let match;
    let hasStyles = false;
    
    while ((match = styleRegex.exec(content)) !== null) {
        hasStyles = true;
        const relativePath = path.relative(__dirname, filePath);
        combinedCss += `\n/* From: ${relativePath} */\n`;
        combinedCss += match[1].trim() + '\n';
    }
    
    if (hasStyles) {
        const newContent = content.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
        fs.writeFileSync(filePath, newContent, 'utf8');
        console.log(`Extracted styles from: ${filePath}`);
    }
}

traverseDir(pagesDir);

if (combinedCss.trim() !== '/* --- Extracted from Vue Components --- */') {
    fs.appendFileSync(appCssPath, combinedCss, 'utf8');
    console.log('Appended all styles to resources/css/app.css');
} else {
    console.log('No styles found to extract.');
}
