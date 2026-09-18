/**
 * Vérifie qu'un update.json.sig correspond EXACTEMENT aux octets de son
 * update.json (clé publique embarquée dans le plugin = autorité).
 *
 * Usage : node tools/verify-update-sig.js [update.json] [update.json.sig]
 * (défaut : dist/update.json dist/update.json.sig)
 *
 * Exit 0 = paire cohérente · Exit 1 = incohérente (à bloquer en CI).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const nacl = require('tweetnacl');

const manifestPath = process.argv[2] || path.join(__dirname, '..', 'dist', 'update.json');
const sigPath = process.argv[3] || manifestPath + '.sig';

// Clé publique Ed25519 embarquée dans includes/license/class-updater.php —
// source unique de vérité côté clients.
const EMBEDDED = 'beDoIoaR5hZvEA2U93fiu80Bzg2uz78MT0n0EydFEKk=';

const raw = fs.readFileSync(manifestPath);
const sigB64 = fs.readFileSync(sigPath, 'utf8').trim();
const sig = Buffer.from(sigB64, 'base64');
if (sig.length !== nacl.sign.signatureLength) {
  console.error('✗ Signature invalide (longueur ' + sig.length + ' != ' + nacl.sign.signatureLength + ').');
  process.exit(1);
}
const ok = nacl.sign.detached.verify(new Uint8Array(raw), new Uint8Array(sig), new Uint8Array(Buffer.from(EMBEDDED, 'base64')));
if (!ok) {
  console.error('✗ ' + path.basename(sigPath) + ' ne correspond PAS aux octets de ' + path.basename(manifestPath) + ' (' + raw.length + ' octets).');
  console.error('  Paire désynchronisée : tout client verra « bad_signature » et ne recevra plus de mise à jour.');
  process.exit(1);
}
const version = (JSON.parse(raw.toString('utf8')).version || '?');
console.log('✓ Signature valide : ' + path.basename(sigPath) + ' ↔ ' + path.basename(manifestPath) + ' (v' + version + ', ' + raw.length + ' octets).');
