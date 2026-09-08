/**
 * Signe dist/update.json (Ed25519 détaché) → dist/update.json.sig.
 *
 * Clé privée (base64, 64 octets) lue depuis :
 *   1. la variable d'environnement INFINITYCOD_SIGNING_KEY (CI/GitHub Secret) ;
 *   2. à défaut, .tools/signing-private.key (machine du développeur).
 *
 * La clé privée ne doit JAMAIS être commitée (§119).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const nacl = require('tweetnacl');

const dist = path.join(__dirname, '..', 'dist');
const manifestPath = path.join(dist, 'update.json');
const sigPath = path.join(dist, 'update.json.sig');

const raw = fs.readFileSync(manifestPath); // octets exacts = ce que le plugin vérifie.

let keyB64 = process.env.INFINITYCOD_SIGNING_KEY || '';
if (!keyB64) {
  const localKey = path.join(__dirname, '..', '.tools', 'signing-private.key');
  if (fs.existsSync(localKey)) {
    keyB64 = fs.readFileSync(localKey, 'utf8').trim();
  }
}
if (!keyB64) {
  console.error('✗ Aucune clé de signature (INFINITYCOD_SIGNING_KEY ou .tools/signing-private.key). Signature ignorée.');
  process.exit(2);
}

const secretKey = new Uint8Array(Buffer.from(keyB64, 'base64'));
if (secretKey.length !== nacl.sign.secretKeyLength) {
  console.error('✗ Clé de signature invalide (longueur ' + secretKey.length + ').');
  process.exit(1);
}

const signature = nacl.sign.detached(new Uint8Array(raw), secretKey);
fs.writeFileSync(sigPath, Buffer.from(signature).toString('base64'));

console.log('✓ dist/update.json.sig créé (signature Ed25519 du manifest).');
