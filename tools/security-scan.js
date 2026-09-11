/* Scanner de sécurité permanent (style CodeRabbit) : échoue sur toute faille.
   Couverture : fonctions dangereuses, XSS superglobales, secrets dans le HTML,
   gardes ABSPATH, nonce CSRF (admin-post + AJAX), routes REST, SQL non préparé,
   redirections non sûres, AJAX public. Usage : node tools/security-scan.js */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = 'infinitycod';
const findings = [];
const files = [];

(function walk(dir) {
	for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
		const f = path.join(dir, e.name).split(path.sep).join('/');
		if (e.isDirectory()) { walk(f); continue; }
		if (!e.name.endsWith('.php')) continue;
		files.push(f);
	}
})(ROOT);
for (const rootFile of ['infinitycod/infinitycod.php', 'infinitycod/uninstall.php']) {
	if (!files.includes(rootFile)) { files.push(rootFile); }
}

function rel(f) { return f.replace(/\\/g, '/'); }
function scan(regex, apply) {
	for (const f of files) {
		const src = fs.readFileSync(f, 'utf8');
		const lines = src.split('\n');
		lines.forEach((line, i) => {
			if (regex.test(line)) {
				const add = apply ? apply(line, f, i + 1) : true;
				if (add) { findings.push(rel(f) + ':' + (i + 1) + ' — ' + line.trim().slice(0, 110)); }
			}
		});
	}
}

// 1. Fonctions dangereuses interdites dans le code livré.
scan(/\beval\s*\(/);
scan(/\bunserialize\s*\((?![^)]*allowed_classes)/); // durci : objets interdits
scan(/\bshell_exec\s*\(|\bpassthru\s*\(|\bsystem\s*\(/);
scan(/\bexec\s*\(/);

// 2. XSS : écho direct de superglobales (jamais échappé à la source).
scan(/echo\s+\$_(GET|POST|REQUEST|SERVER|FILES|COOKIE)\b/);

// 3. Secrets affichés dans le HTML (input password rempli depuis Settings).
scan(/type="password"[^>]*value="<\?php/);

// 4. Redirections non sûres.
scan(/(?<![_a-zA-Z])wp_redirect\s*\(/);

// 5. AJAX public (les endpoints publics passent par REST + protections).
scan(/wp_ajax_nopriv/);

// 6. SQL : superglobales interpolées dans une requête $wpdb.
scan(/\$wpdb->(query|get_var|get_results|get_row|get_col)\s*\([^)]*\$_(GET|POST|REQUEST|COOKIE)/);

// 7. Garde ABSPATH sur chaque fichier du plugin (hors stubs index.php).
for (const f of files) {
	if (rel(f).endsWith('/index.php')) continue;
		// uninstall.php utilise WP_UNINSTALL_PLUGIN (ABSPATH n'y est pas defini).
		const fileSrc = fs.readFileSync(f, 'utf8');
		const guard = rel(f).endsWith('uninstall.php') ? /WP_UNINSTALL_PLUGIN/ : /ABSPATH/;
		if (!guard.test(fileSrc)) { findings.push(rel(f) + ' — garde ABSPATH absente'); }
}

// 8. CSRF : chaque fichier à handlers admin_post doit vérifier un nonce.
for (const f of files) {
	const src = fs.readFileSync(f, 'utf8');
	const relf = rel(f);
	if (/add_action\(\s*'admin_post_/.test(src) && !/check_admin_referer\(|wp_verify_nonce\(/.test(src)) {
		findings.push(relf + ' — handler admin_post sans vérification de nonce');
	}
	if (/wp_ajax_/.test(src) && !/check_ajax_referer\(|wp_verify_nonce\(/.test(src)) {
		findings.push(relf + ' — handler AJAX sans vérification de nonce');
	}
}

// 9. REST : chaque route déclarée doit définir un permission_callback.
const routesSrc = fs.readFileSync(path.join(ROOT, 'includes/rest/class-routes.php'), 'utf8');
const nRoutes = (routesSrc.match(/register_rest_route\(/g) || []).length;
const nPerms = (routesSrc.match(/permission_callback/g) || []).length;
if (nRoutes !== nPerms) {
	findings.push('includes/rest/class-routes.php — ' + (nRoutes - nPerms) + ' route(s) sans permission_callback');
}

if (findings.length) {
	console.error('\u2717 Scan s\u00e9curit\u00e9 : ' + findings.length + ' constat(s) :');
	findings.forEach((x) => console.error('  ' + x));
	process.exit(1);
}
console.log('\u2713 S\u00e9curit\u00e9 : ' + files.length + ' fichiers scann\u00e9s \u2014 injection, XSS, CSRF, secrets, routes REST : rien \u00e0 signaler.');
