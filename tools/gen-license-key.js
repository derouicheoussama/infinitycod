/**
 * Générateur de clés de licence signées Ed25519 (hors ligne, sans serveur).
 *
 * Produit des clés du format ICOD1.<payload>.<signature> que le plugin
 * vérifie localement avec la clé publique embarquée — parfait pour les
 * clés de test du développeur et les ventes traitées manuellement.
 *
 * La clé privée vient de .tools/signing-private.key (la même que celle des
 * manifests de mise à jour) — ne la partage JAMAIS.
 *
 * Usage :
 *   node tools/gen-license-key.js --client "Nom du client" --email "a@b.c" --days 365 [--type pro|test]
 *
 * Sans --days : clé perpétuelle. --type test apparaît comme « test » dans
 * le message d'activation (utile pour distinguer tes clés de test).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const nacl = require('tweetnacl');

function arg(name) {
  const i = process.argv.indexOf('--' + name);
  return i !== -1 ? process.argv[i + 1] : null;
}

const client = arg('client');
if (!client) {
  console.error('Usage : node tools/gen-license-key.js --client "Nom" [--email a@b.c] [--days 365] [--type pro|test]');
  process.exit(1);
}

const email = arg('email') || '';
const days = parseInt(arg('days'), 10) || 0;
const type = (arg('type') || 'pro').replace(/[^a-z]/gi, '').slice(0, 10) || 'pro';

const keyFile = path.join(__dirname, '..', '.tools', 'signing-private.key');
if (!fs.existsSync(keyFile)) {
  console.error('✗ clé privée introuvable : ' + keyFile);
  process.exit(1);
}
const secretKey = new Uint8Array(Buffer.from(fs.readFileSync(keyFile, 'utf8').trim(), 'base64'));
if (secretKey.length !== nacl.sign.secretKeyLength) {
  console.error('✗ clé privée invalide (longueur ' + secretKey.length + ')');
  process.exit(1);
}

function b64url(buf) {
  return Buffer.from(buf).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

const expires = days > 0 ? new Date(Date.now() + days * 864e5).toISOString().slice(0, 10) : null;
const payload = { client, email, type };
if (expires) { payload.expires = expires; }

const msg = Buffer.from(JSON.stringify(payload), 'utf8');
const sig = nacl.sign.detached(new Uint8Array(msg), secretKey);

const key = 'ICOD1.' + b64url(msg) + '.' + b64url(sig);

// Auto-vérification avec la clé publique EMBARQUÉE dans le plugin.
const EMBEDDED = 'beDoIoaR5hZvEA2U93fiu80Bzg2uz78MT0n0EydFEKk=';
const ok = nacl.sign.detached.verify(new Uint8Array(msg), sig, new Uint8Array(Buffer.from(EMBEDDED, 'base64')));
if (!ok) {
  console.error('✗ auto-vérification échouée — la clé produite serait rejetée.');
  process.exit(1);
}

console.log('✓ Clé générée pour : ' + client + (email ? ' <' + email + '>' : ''));
console.log('  Type     : ' + type);
console.log('  Expire   : ' + (expires || 'jamais (perpétuelle)'));
console.log('');
console.log('Clé à coller dans Réglages → Licence :');
console.log('');
console.log(key);
