#!/usr/bin/env node
/**
 * Scan anti-régression : chaque appel $this->methode( doit exister dans la
 * classe courante. Détecte la classe de bug « Call to undefined method »
 * (fatal 500 sur toutes les requêtes REST — bug ns() de la 5.4.x/5.5.0).
 */
'use strict';
const fs = require('fs');
const path = require('path');

function walk(dir, out) {
	for (const f of fs.readdirSync(dir)) {
		const p = path.join(dir, f);
		const st = fs.statSync(p);
		if (st.isDirectory()) { walk(p, out); } else if (f.endsWith('.php')) { out.push(p); }
	}
	return out;
}

const files = walk(path.join(__dirname, '..', 'infinitycod', 'includes'), []);

// Passe 1 : méthodes déclarées par classe (résolution d'héritage interne).
const classMethods = new Map(); // classe -> Set(méthodes)
const classParent = new Map();  // classe -> parent
const fileClass = new Map();    // fichier -> classe

for (const file of files) {
	const src = fs.readFileSync(file, 'utf8');
	const cls = src.match(/class\s+(\w+)(?:\s+extends\s+(\w+))?/);
	if (!cls) { continue; }
	const declared = new Set();
	for (const m of src.matchAll(/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g)) { declared.add(m[1]); }
	if (!classMethods.has(cls[1])) { classMethods.set(cls[1], new Set()); }
	for (const n of declared) { classMethods.get(cls[1]).add(n); }
	if (cls[2]) { classParent.set(cls[1], cls[2]); }
	fileClass.set(file, cls[1]);
}

// Classes de bases externes (WordPress, Elementor…) : méthodes supposées valides.
const externalBases = new Set(['Widget_Base', 'WP_List_Table', 'Walker', 'Exception']);

function hasMethod(cls, method, depth = 0) {
	if (!cls || depth > 8) { return true; }
	if (classMethods.has(cls) && classMethods.get(cls).has(method)) { return true; }
	if (externalBases.has(cls)) { return true; }
	return hasMethod(classParent.get(cls), method, depth + 1);
}

// Passe 2 : vérification des appels $this->().
let bad = 0;
for (const file of files) {
	const clsName = fileClass.get(file);
	if (!clsName) { continue; }
	const src = fs.readFileSync(file, 'utf8');
	for (const m of src.matchAll(/\$this->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g)) {
		const name = m[1];
		if (!hasMethod(clsName, name)) {
			console.log('FAIL ' + path.relative(process.cwd(), file).replace(/\\/g, '/') + ' : $this->' + name + '() introuvable depuis ' + clsName);
			bad++;
		}
	}
}

console.log(bad === 0 ? 'OK : tous les appels $this->() resolus (' + files.length + ' fichiers, heritage inclus)' : bad + ' appel(s) casse(s)');
process.exit(bad === 0 ? 0 : 1);
