/**
 * Génère les clés VAPID + un abonné de test et écrit le fichier PHP
 * d'injection dans l'option WordPress (à exécuter ensuite via wp-cli).
 *
 * Usage : node tools/dev/seed-vapid.js   → écrit testbed/seed-vapid.php
 * Puis  : wp-cli eval-file testbed/seed-vapid.php
 */
'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..');

function b64url(b) {
  return Buffer.from(b).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

// 1. Paire VAPID (P-256).
const vapid = crypto.generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
const vPubRaw = vapid.publicKey.export({ type: 'spki', format: 'der' }).slice(-65);
const vPrivPem = vapid.privateKey.export({ type: 'pkcs8', format: 'pem' });

// 2. Faux abonné de test (pour valider le chiffrement ECDH côté plugin).
const sub = crypto.generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
const subPubRaw = sub.publicKey.export({ type: 'spki', format: 'der' }).slice(-65);
const auth = crypto.randomBytes(16);

// 3. Fichier PHP d'injection.
const privPemPhp = vPrivPem.replace(/'/g, "\\'").replace(/\r?\n/g, '\\n');
const php = `<?php
update_option( 'icod_webpush_vapid', array(
	'public_raw'  => '${vPubRaw.toString('base64')}',
	'public_b64u' => '${b64url(vPubRaw)}',
	'public_pem'  => '',
	'private_pem' => '${privPemPhp}',
	'created_at'  => gmdate( 'c' ),
), true );
update_option( 'icod_webpush_subs', array(
	array(
		'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-subscription-local',
		'keys'     => array(
			'p256dh' => '${b64url(subPubRaw)}',
			'auth'   => '${b64url(auth)}',
		),
		'device'   => 'test-local',
		'time'     => time(),
	),
), true );
echo 'vapid + abonne stockes';
`;

const outFile = path.join(ROOT, 'testbed', 'seed-vapid.php');
fs.writeFileSync(outFile, php);
console.log('✓ écrit : ' + outFile);
console.log('  Exécutez ensuite : wp-cli eval-file testbed/seed-vapid.php');
