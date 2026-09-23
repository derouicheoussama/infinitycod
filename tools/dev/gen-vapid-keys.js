/**
 * Génère une paire VAPID (P-256) + un faux abonné de test, et écrit les
 * JSON dans tools/dev/vapid-out/ pour injection via wp-cli (PHP OpenSSL
 * Windows ne supporte pas la génération EC sur ce poste).
 *
 * Usage : node tools/dev/gen-vapid-keys.js
 */
'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

function b64url(b) {
  return Buffer.from(b).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

// 1. Paire VAPID (P-256).
const vapid = crypto.generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
const vPubRaw = vapid.publicKey.export({ type: 'spki', format: 'der' }).slice(-65);
const vPrivPem = vapid.privateKey.export({ type: 'pkcs8', format: 'pem' });

// 2. Faux abonné (valide le chemin de chiffrement ECDH côté plugin).
const sub = crypto.generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
const subPubRaw = sub.publicKey.export({ type: 'spki', format: 'der' }).slice(-65);
const auth = crypto.randomBytes(16);

// 3. Échappement PHP du PEM (guillemets simples + retours ligne).
const privPemPhp = vPrivPem.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r?\n/g, '\\n');

// 4. Dossier de sortie fixe du dépôt (jamais construit depuis une entrée).
const outDir = path.join(__dirname, 'vapid-out');
fs.mkdirSync(outDir, { recursive: true });

const out = {
  pubB64u: b64url(vPubRaw),
  pubRawB64: vPubRaw.toString('base64'),
  privPemPhp,
  sub: {
    p256dh: b64url(subPubRaw),
    auth: b64url(auth),
  },
};
fs.writeFileSync(path.join(outDir, 'keys.json'), JSON.stringify(out, null, 2));
fs.writeFileSync(path.join(outDir, 'subscriber.json'), JSON.stringify(out.sub, null, 2));
console.log('✓ écrit : ' + path.join(outDir, 'keys.json'));
console.log('  public_b64u : ' + out.pubB64u.slice(0, 24) + '…');
