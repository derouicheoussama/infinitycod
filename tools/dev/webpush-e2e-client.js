#!/usr/bin/env node
/**
 * Client Web Push de test — génère un abonnement P-256 (p256dh/auth),
 * puis après un envoi serveur : déchiffre l'enregistrement aes128gcm capturé
 * et vérifie le JWT VAPID ES256. Validation E2E du module Webpush d'InfinityCod.
 *
 * Usage (la commande passe par la variable d'environnement ICOD_E2E_CMD) :
 *   ICOD_E2E_CMD=gen    node tools/dev/webpush-e2e-client.js   → écrit tools/dev/vapid-out/sub-e2e.json
 *   ICOD_E2E_CMD=verify node tools/dev/webpush-e2e-client.js   → lit push-capture.json et sub-e2e.json
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { webcrypto } = require('crypto');

const OUT_DIR = path.join(__dirname, 'vapid-out');
const SUB_FILE = path.join(OUT_DIR, 'sub-e2e.json');
const CAPTURE_FILE = path.join(OUT_DIR, 'push-capture.json');

function b64url(buf) {
  return Buffer.from(buf).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
function b64urlDecode(s) {
  let t = String(s).replace(/-/g, '+').replace(/_/g, '/');
  while (t.length % 4) t += '=';
  return Buffer.from(t, 'base64');
}

async function gen() {
  const kp = await webcrypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']);
  const rawPub = new Uint8Array(await webcrypto.subtle.exportKey('raw', kp.publicKey)); // 65 octets (0x04…)
  const privJwk = await webcrypto.subtle.exportKey('jwk', kp.privateKey);
  const auth = crypto.randomBytes(16);
  const sub = {
    endpoint: 'http://127.0.0.1:8093/push-capture-tmp.php',
    keys: { p256dh: b64url(rawPub), auth: b64url(auth) },
    privJwk,
  };
  fs.mkdirSync(OUT_DIR, { recursive: true });
  fs.writeFileSync(SUB_FILE, JSON.stringify(sub, null, 2));
  console.log('abonnement écrit :', SUB_FILE);
  console.log('endpoint :', sub.endpoint);
  console.log('p256dh :', sub.keys.p256dh);
}

/** Déchiffre un enregistrement aes128gcm (RFC 8291) avec la clé privée client. */
async function decryptRecord(privJwk, subPubRaw, authSecret, record) {
  const salt = record.subarray(0, 16);
  // rs (4 octets BE) puis idlen (1 octet) puis clé publique éphémère (65 octets) puis ciphertext.
  const idlen = record[20];
  const ephPubRaw = record.subarray(21, 21 + idlen);
  const ciphertext = record.subarray(21 + idlen);

  const privKey = await webcrypto.subtle.importKey('jwk', privJwk, { name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']);
  const ephPub = await webcrypto.subtle.importKey('raw', ephPubRaw, { name: 'ECDH', namedCurve: 'P-256' }, false, []);
  const shared = new Uint8Array(await webcrypto.subtle.deriveBits({ name: 'ECDH', public: ephPub }, privKey, 256));

  // RFC 8291 §5 : IKM = HKDF(auth_secret, ecdh, "WebPush: info" || 0x00 || u16(len(pubs)) || pub_sub || u8(len(ephs)) || pub_eph)
  const infoParts = [Buffer.from('WebPush: info', 'utf8'), Buffer.from([0x00]), Buffer.from([0x00, 65]), subPubRaw, Buffer.from([65]), ephPubRaw];
  const info = Buffer.concat(infoParts);
  const ikm = Buffer.from(crypto.hkdfSync('sha256', shared, Buffer.from(authSecret), info, 32));

  const cek = Buffer.from(crypto.hkdfSync('sha256', ikm, salt, Buffer.from('Content-Encoding: aes128gcm\0', 'utf8'), 16));
  const nonce = Buffer.from(crypto.hkdfSync('sha256', ikm, salt, Buffer.from('Content-Encoding: nonce\0', 'utf8'), 12));

  const tag = ciphertext.subarray(ciphertext.length - 16);
  const data = ciphertext.subarray(0, ciphertext.length - 16);
  const decipher = crypto.createDecipheriv('aes-128-gcm', cek, nonce);
  decipher.setAuthTag(tag);
  const plain = Buffer.concat([decipher.update(data), decipher.final()]);
  // Fin du plaintext : délimiteur de padding 0x02 (RFC 8291 §2).
  let end = plain.length - 1;
  while (end >= 0 && plain[end] === 0) end--;
  if (end < 0 || plain[end] !== 0x02) throw new Error('délimiteur de padding invalide');
  return plain.subarray(0, end);
}

/** Vérifie le JWT VAPID ES256 (signature + clé « k= »). */
async function verifyVapidJwt(authHeader, serverPubB64u) {
  const m = String(authHeader || '').match(/vapid t=([^,]+), k=([^,]+)/);
  if (!m) throw new Error('en-tête Authorization VAPID absent');
  const parts = m[1].split('.');
  const header = JSON.parse(b64urlDecode(parts[0]).toString('utf8'));
  const claims = JSON.parse(b64urlDecode(parts[1]).toString('utf8'));
  const sig = b64urlDecode(parts[2]);
  const spki = Buffer.concat([
    Buffer.from('3059301306072a8648ce3d020106082a8648ce3d030107034200', 'hex'),
    b64urlDecode(serverPubB64u),
  ]);
  const key = crypto.createPublicKey({ key: spki, format: 'der', type: 'spki' });
  const signedInput = [parts[0], parts[1]].join('.');
  const verifier = crypto.createVerify('SHA256');
  verifier.update(signedInput);
  const ok = verifier.verify(key, sig);
  return {
    header,
    claims,
    sigOk: ok,
    kMatches: b64urlDecode(m[2]).equals(b64urlDecode(serverPubB64u)),
    aud: claims.aud,
  };
}

async function verify() {
  const sub = JSON.parse(fs.readFileSync(SUB_FILE, 'utf8'));
  const cap = JSON.parse(fs.readFileSync(CAPTURE_FILE, 'utf8'));
  if (!cap.bodyB64) throw new Error('capture sans corps');
  const record = Buffer.from(cap.bodyB64, 'base64');
  const payload = await decryptRecord(sub.privJwk, b64urlDecode(sub.keys.p256dh), b64urlDecode(sub.keys.auth), record);
  const vapid = await verifyVapidJwt(cap.headers.authorization, process.env.ICOD_E2E_PUB || cap.serverPubB64u);

  console.log('--- résultat de la vérification E2E ---');
  console.log('Content-Encoding                    :', cap.headers['content-encoding']);
  console.log('taille enregistrement               :', record.length, 'octets (rs =', record.readUInt32BE(16), ')');
  console.log('payload déchiffré                   :', payload.toString('utf8'));
  console.log('JWT header                          :', JSON.stringify(vapid.header));
  console.log('JWT claims (aud, exp, sub)          :', JSON.stringify(vapid.claims));
  console.log('signature JWT ES256                 :', vapid.sigOk ? 'VALIDE' : 'INVALIDE');
  console.log('clé « k= » == clé publique VAPID    :', vapid.kMatches ? 'OUI' : 'NON');
  if (!vapid.sigOk || !vapid.kMatches) process.exit(1);
}

(async () => {
  const cmd = process.env.ICOD_E2E_CMD || 'gen';
  if (cmd === 'gen') return gen();
  if (cmd === 'verify') return verify();
  console.error('commande inconnue (gen|verify)');
  process.exit(1);
})().catch((e) => { console.error('ERREUR :', e.message); process.exit(1); });
