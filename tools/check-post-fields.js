#!/usr/bin/env node
/**
 * Scan anti-régression : noms des champs POST des écrans admin.
 * Détecte les champs rendus mais jamais lus (réglage mort) et les champs
 * lus mais jamais rendus (valeur toujours vide) — type de bug « livraison
 * gratuite par montant ne s'active jamais » (5.6.x).
 */
'use strict';
const fs = require('fs');
const path = require('path');

function walk(d, o) {
	for (const f of fs.readdirSync(d)) {
		const p = path.join(d, f);
		const st = fs.statSync(p);
		if (st.isDirectory()) { walk(p, o); } else if (f.endsWith('.php')) { o.push(p); }
	}
	return o;
}

const files = walk(path.join(__dirname, '..', 'infinitycod', 'includes', 'admin'), []);
const rendered = new Set();
const read = new Set();
// Clés légitimes : champs type="file" lus via $_FILES, tableaux dynamiques
// (icod_wilaya[code][home]) rendus sous forme name="icod_wilaya[…]".
const exceptions = new Set(['icod_license_key', 'icod_rates_csv_file', 'icod_offers_nonce', 'icod_carrier', 'icod_zip', 'icod_wilaya']);

for (const f of files) {
	const src = fs.readFileSync(f, 'utf8');
	for (const m of src.matchAll(/name="(icod_[a-z_]+)(\[|")/g)) { rendered.add(m[1]); }
	for (const m of src.matchAll(/\$_POST\['(icod_[a-z_]+)'\]/g)) { read.add(m[1]); }
	for (const m of src.matchAll(/\$_FILES\['(icod_[a-z_]+)'\]/g)) { read.add(m[1]); }
}

let bad = 0;
for (const r of [...rendered].sort()) {
	if (!read.has(r)) { console.log('FAIL rendu ' + r + ' mais jamais lu'); bad++; }
}
for (const r of [...read].sort()) {
	if (!rendered.has(r) && !exceptions.has(r)) { console.log('FAIL lu ' + r + ' mais jamais rendu'); bad++; }
}

console.log(bad === 0
	? 'OK : tous les champs POST des écrans admin correspondent (' + rendered.size + ' rendus / ' + read.size + ' lus)'
	: bad + ' mismatch(es)');
process.exit(bad === 0 ? 0 : 1);
