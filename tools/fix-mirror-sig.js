/**
 * Répare le .sig du miroir : signe les octets EXACTS d'update.json actuellement
 * servis sur raw.githubusercontent (déjà validés : version + sha256 conformes)
 * et écrit dist/update.json.sig à pousser sur infinitycod-releases@main/latest/.
 *
 * Usage : node tools/fix-mirror-sig.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const https = require('https');
const nacl = require('tweetnacl');

const dist = path.join(__dirname, '..', 'dist');
const manifestUrl = 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/main/latest/update.json';

function get(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'User-Agent': 'InfinityCod-Release/1.0' } }, (res) => {
      if (res.statusCode !== 200) {
        reject(new Error('HTTP ' + res.statusCode + ' pour ' + url));
        return;
      }
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => resolve(Buffer.concat(chunks)));
    }).on('error', reject);
  });
}

(async () => {
  const raw = await get(manifestUrl);
  const manifest = JSON.parse(raw.toString('utf8'));
  console.log('Manifest servi : v' + manifest.version + ', sha256=' + String(manifest.sha256).slice(0, 12) + '…');

  const keyB64 = fs.readFileSync(path.join(__dirname, '..', '.tools', 'signing-private.key'), 'utf8').trim();
  const secretKey = new Uint8Array(Buffer.from(keyB64, 'base64'));
  if (secretKey.length !== nacl.sign.secretKeyLength) {
    console.error('✗ Clé privée invalide (longueur ' + secretKey.length + ').');
    process.exit(1);
  }

  // Vérifie que la clé privée correspond à la clé publique embarquée dans le plugin.
  const pubFromPriv = Buffer.from(secretKey.subarray(nacl.sign.seedLength)).toString('base64');
  const EMBEDDED = 'beDoIoaR5hZvEA2U93fiu80Bzg2uz78MT0n0EydFEKk=';
  if (pubFromPriv !== EMBEDDED) {
    console.error('✗ La clé privée locale ne correspond PAS à la clé publique embarquée !');
    console.error('  dérivée : ' + pubFromPriv);
    process.exit(1);
  }

  const signature = nacl.sign.detached(new Uint8Array(raw), secretKey);
  const sigB64 = Buffer.from(signature).toString('base64');

  // Auto-vérification avec la clé publique embarquée.
  const ok = nacl.sign.detached.verify(new Uint8Array(raw), signature, new Uint8Array(Buffer.from(EMBEDDED, 'base64')));
  if (!ok) {
    console.error('✗ Auto-vérification échouée.');
    process.exit(1);
  }

  fs.writeFileSync(path.join(dist, 'update.json.sig'), sigB64 + '\n');
  fs.writeFileSync(path.join(dist, 'update.json'), raw);
  console.log('✓ dist/update.json.sig régénéré sur les octets servis (auto-vérifiée ✓).');
  console.log('  À pousser vers : infinitycod-releases@main/latest/update.json.sig');
})().catch((e) => {
  console.error('✗ ' + e.message);
  process.exit(1);
});
