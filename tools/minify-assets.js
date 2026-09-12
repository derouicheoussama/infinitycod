/* Minification des assets du plugin : g\u00e8re les variantes .min.css / .min.js
   servies par asset_url(). S\u00e9curis\u00e9 et sans d\u00e9pendance :
   - CSS   : cha\u00eenes prot\u00e9g\u00e9es, commentaires retir\u00e9s, espaces compact\u00e9s ;
   - JS    : uniquement les lignes de commentaires enti\u00e8res et les lignes vides
             (aucune transformation risqu\u00e9e sur le code). */
'use strict';
const fs = require('fs');
const path = require('path');

const ASSETS = path.join(__dirname, '..', 'infinitycod', 'assets');
const FILES = [
	'front/css/form.css',
	'front/js/form.js',
	'admin/css/admin.css',
	'admin/js/admin.js',
];

function minifyCss(src) {
	// 1. Prot\u00e8ge les cha\u00eenes entre guillemets/apostrophes.
	const strings = [];
	let out = src.replace(/(["'])(?:\\.|(?!\1)[^\\\n])*\1/g, (m) => {
		strings.push(m);
		return '\u0000' + (strings.length - 1) + '\u0000';
	});
	// 2. Commentaires blocs.
	out = out.replace(/\/\*[\s\S]*?\*\//g, '');
	// 3. Espaces : compacte puis retire autour de la ponctuation.
	out = out.replace(/\s+/g, ' ');
	out = out.replace(/\s*([{}:;,>~])\s*/g, '$1');
	out = out.replace(/;\}/g, '}');
	// 4. Restaure les cha\u00eenes.
	out = out.replace(/\u0000(\d+)\u0000/g, (m, i) => strings[Number(i)]);
	return out.trim();
}

function minifyJs(src) {
	const lines = src.split('\n');
	const out = [];
	for (let line of lines) {
		const t = line.trim();
		if (t.startsWith('//')) { continue; }          // commentaire pleine ligne
		if (t === '' && (out.length === 0 || out[out.length - 1].trim() === '')) { continue; }
		out.push(line.replace(/\s+$/, ''));
	}
	return out.join('\n');
}

let savedTotal = 0;
for (const rel of FILES) {
	const srcPath = path.join(ASSETS, rel);
	const minPath = srcPath.replace(/\.(\w+)$/, '.min.$1');
	const src = fs.readFileSync(srcPath, 'utf8');
	const isCss = rel.endsWith('.css');
	const min = isCss ? minifyCss(src) : minifyJs(src);
	fs.writeFileSync(minPath, min);
	const gain = Math.round((1 - min.length / src.length) * 100);
	savedTotal += src.length - min.length;
	console.log(path.basename(minPath).padEnd(16) + (src.length / 1024).toFixed(1) + ' Ko \u2192 ' + (min.length / 1024).toFixed(1) + ' Ko (-' + gain + '%)');
}
console.log('\u00e9conomie totale : ' + Math.round(savedTotal / 1024) + ' Ko');
