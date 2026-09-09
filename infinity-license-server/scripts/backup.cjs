// Backup : copie la base + clés + réglages vers storage/backups/<horodatage>/
const fs = require('fs');
const path = require('path');
const root = path.dirname(__dirname);
const src = path.join(root, 'storage');
const dest = path.join(src, 'backups', new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19));
fs.mkdirSync(dest, { recursive: true });
for (const f of ['infinity-license.db', 'keys/license_private.pem', 'keys/license_public.pem', '.app_key']) {
  const s = path.join(src, f);
  if (fs.existsSync(s)) fs.copyFileSync(s, path.join(dest, path.basename(f)));
}
console.log('Backup créé :', dest);
