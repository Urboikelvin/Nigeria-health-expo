// Builds the plain-HTML version of the site into ./deploy for cPanel / any static host.
// Usage: npm run build:static   (or: node scripts/build-static.mjs)
import { cpSync, rmSync, mkdirSync, readFileSync, writeFileSync, existsSync } from "node:fs";

const OUT = "deploy";
if (!existsSync("public/expo.html")) {
  console.error("Run this from the project root (public/expo.html not found).");
  process.exit(1);
}

rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });

cpSync("public/images", `${OUT}/images`, { recursive: true });
cpSync("public/favicon.ico", `${OUT}/favicon.ico`);
cpSync("public/robots.txt", `${OUT}/robots.txt`);
cpSync("public/send.php", `${OUT}/send.php`); // form handler (needs PHP on the host)

// expo.html becomes the home page. Image paths are made relative so the site also
// works on the temporary address (http://SERVER-IP/~username/), not only at the domain root.
let html = readFileSync("public/expo.html", "utf8");
html = html.replaceAll('src="/images/', 'src="images/').replaceAll('href="/favicon.ico"', 'href="favicon.ico"');
if (!html.includes('rel="icon"')) {
  html = html.replace("</head>", '<link rel="icon" href="favicon.ico">\n</head>');
}
writeFileSync(`${OUT}/index.html`, html);

// Apache (cPanel) settings. The HTTPS redirect is commented out: enable it only AFTER
// the SSL certificate is active, otherwise visitors get a broken site.
writeFileSync(
  `${OUT}/.htaccess`,
  `# Redirect http -> https (uncomment once SSL is installed)
# RewriteEngine On
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [L,R=301]

# Cache images for a month
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg "access plus 1 month"
  ExpiresByType image/x-icon "access plus 1 month"
</IfModule>
`,
);

console.log(`Done. Upload the CONTENTS of the "${OUT}" folder to public_html.`);
