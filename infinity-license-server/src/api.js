import fs from 'node:fs';
import path from 'node:path';
import { db } from './db.js';
import { config } from './config.js';
import {
	json, now, nowTs, uid, sha256, esc, signPayload, privateKeyPem, publicKeyPem,
	normalizeDomain, classifyEnvironment, rateLimit, apiLog, securityEvent,
	generateLicenseKey, encrypt, decrypt, keyPreview, fmtDate,
} from './core.js';

const LICENSE_STATUSES = ['ACTIVE', 'EXPIRED', 'SUSPENDED', 'REVOKED', 'PENDING'];

function fail(res, ip, route, code, message, httpCode = 400, licenseId = 0) {
	json(res, httpCode, { success: false, error: { code, message } });
	apiLog(route, ip, httpCode, licenseId, code);
}

function apiError(code, message) {
	return { code, message };
}

function findLicense(body) {
	// Compatibilité InfinityCOD : key_hash (SHA-256 de la clé) OU license_key.
	if (body.key_hash) {
		return db.prepare('SELECT * FROM licenses WHERE key_hash = ?').get(String(body.key_hash).trim().toLowerCase());
	}
	if (body.license_key) {
		return db.prepare('SELECT * FROM licenses WHERE key_hash = ?').get(sha256(String(body.license_key).trim().toUpperCase()));
	}
	return null;
}

function licenseState(lic) {
	// Expiration dynamique : le statut stocké ne ment jamais sur le temps.
	if (lic.status === 'ACTIVE' && lic.expires_at && lic.expires_at !== '' && lic.expires_at < now()) {
		return 'EXPIRED';
	}
	return lic.status;
}

function productOf(lic) { return db.prepare('SELECT * FROM products WHERE id = ?').get(lic.product_id); }

function licensePayload(lic) {
	const used = db.prepare("SELECT COUNT(*) c FROM installations WHERE license_id = ? AND status != 'INACTIVE'").get(lic.id).c;
	return {
		id: lic.id,
		key_preview: lic.key_preview,
		status: licenseState(lic),
		created_at: lic.created_at,
		starts_at: lic.starts_at,
		expires_at: lic.expires_at || '',
		activation_limit: lic.activation_limit,
		activations_used: used,
		updates_until: lic.updates_until || '',
		allowed_versions: lic.allowed_versions || '',
	};
}

function clientCompat(lic, product) {
	// Format historique consommé par InfinityCOD (LicenseManager v4).
	return {
		status: licenseState(lic) === 'ACTIVE' ? 'ACTIVE' : licenseState(lic),
		client: (db.prepare('SELECT name FROM customers WHERE id = ?').get(lic.customer_id) || {}).name || '',
		expires_at: lic.expires_at || '',
		product: product ? product.slug : '',
		version: product ? product.version : '',
	};
}

/* ---------------- Endpoints ---------------- */

export async function handleApi(req, res, url, body, ip) {
	const route = url.pathname;

	// Santé.
	if (route === '/health') {
		const t = db.prepare('SELECT COUNT(*) c FROM products').get().c;
		return json(res, 200, { status: 'ok', database: t >= 0 ? 'ok' : 'error', app: 'infinity-license', time: now() });
	}
	if (route === '/api/v1/public-key') {
		return json(res, 200, { algorithm: 'ed25519', public_key: publicKeyPem() });
	}

	try {
		switch (route) {
			case '/api/v1/license/activate': return await activate(req, res, body, ip);
			case '/api/v1/license/validate': return validate(req, res, body, ip);
			case '/api/v1/license/deactivate': return deactivate(req, res, body, ip);
			case '/api/v1/license/heartbeat': return heartbeat(req, res, body, ip);
			case '/api/v1/license/check-update': return checkUpdate(req, res, url, body, ip);
			case '/api/v1/license/check-version': return checkVersion(req, res, url, ip);
			default:
				if (route.startsWith('/api/v1/download/')) return download(req, res, url, ip);
				return fail(res, ip, route, 'NOT_FOUND', 'Unknown API route.', 404);
		}
	} catch (e) {
		apiLog(route, ip, 500, 0, e.message);
		return json(res, 500, { success: false, error: { code: 'SERVER_UNAVAILABLE', message: 'Internal error.' } });
	}
}

function tooMany(res, ip, route) {
	json(res, 429, { success: false, error: { code: 'RATE_LIMITED', message: 'Too many requests. Slow down.' } });
	apiLog(route, ip, 429);
}

/* --- ACTIVATE --- */
async function activate(req, res, body, ip) {
	const rl = rateLimit(`activate:${ip}`, 30, 3600);
	if (!rl.ok) return tooMany(res, ip, '/api/v1/license/activate');

	const lic = findLicense(body);
	if (!lic) {
		securityEvent('INVALID_LICENSE', 'MEDIUM', 'Activation with unknown key', 0, '', ip);
		return fail(res, ip, '/api/v1/license/activate', 'INVALID_LICENSE', 'This license key does not exist.', 404);
	}

	const domain = normalizeDomain(body.domain || (body.site_url ? new URL(body.site_url).hostname : ''));
	if (!domain) return fail(res, ip, '/api/v1/license/activate', 'DOMAIN_NOT_ALLOWED', 'Missing domain.');

	const product = productOf(lic);
	const requestedProduct = String(body.product_id || body.product || '').trim();
	if (requestedProduct && product && product.slug !== requestedProduct && String(product.id) !== requestedProduct) {
		securityEvent('PRODUCT_NOT_ALLOWED', 'HIGH', `License ${lic.key_preview} used on product ${requestedProduct}`, lic.id, '', ip);
		return fail(res, ip, '/api/v1/license/activate', 'PRODUCT_NOT_ALLOWED', 'This license is not valid for this product.', 403, lic.id);
	}

	const state = licenseState(lic);
	if (state === 'SUSPENDED') return fail(res, ip, '/api/v1/license/activate', 'LICENSE_SUSPENDED', 'This license is suspended.', 403, lic.id);
	if (state === 'REVOKED') return fail(res, ip, '/api/v1/license/activate', 'LICENSE_REVOKED', 'This license has been revoked.', 403, lic.id);
	if (state === 'EXPIRED') return fail(res, ip, '/api/v1/license/activate', 'LICENSE_EXPIRED', 'The license has expired.', 403, lic.id);

	const environment = classifyEnvironment(domain);
	const countsStaging = String(db.prepare("SELECT value FROM settings WHERE key='staging_counts'").get()?.value ?? '0') === '1';
	const counts = environment === 'PRODUCTION' || countsStaging;

	let install = db.prepare('SELECT * FROM installations WHERE installation_id = ?').get(String(body.installation_id || ''));
	if (!install) {
		install = db.prepare('SELECT * FROM installations WHERE license_id = ? AND domain = ?').get(lic.id, domain);
	}
	if (install && install.license_id !== lic.id) {
		securityEvent('DOMAIN_CONFLICT', 'HIGH', `Installation ${install.installation_id} (${domain}) activated with a second license`, lic.id, install.installation_id, ip);
	}

	if (!install) {
		if (counts) {
			const used = db.prepare("SELECT COUNT(*) c FROM installations WHERE license_id = ? AND status != 'INACTIVE' AND environment = 'PRODUCTION'").get(lic.id).c;
			if (used >= lic.activation_limit) {
				securityEvent('ACTIVATION_LIMIT', 'MEDIUM', `Limit reached for ${lic.key_preview} (${domain})`, lic.id, '', ip);
				return fail(res, ip, '/api/v1/license/activate', 'ACTIVATION_LIMIT_REACHED', 'The activation limit for this license has been reached.', 403, lic.id);
			}
		}
		const installId = String(body.installation_id || uid(16));
		db.prepare(`INSERT INTO installations(installation_id,license_id,domain,site_url,plugin_version,wp_version,php_version,environment,status,risk,first_seen,last_seen,ip)
			VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)`)
			.run(installId, lic.id, domain, String(body.site_url || ''), String(body.plugin_version || ''), String(body.wordpress_version || ''), String(body.php_version || ''),
				environment, 'ACTIVE', 'LOW', now(), now(), ip);
		install = db.prepare('SELECT * FROM installations WHERE installation_id = ?').get(installId);
		securityEvent('INSTALLATION_REGISTERED', 'LOW', `New installation ${domain} (${environment})`, lic.id, installId, ip);
	} else if (install.status === 'BLOCKED') {
		securityEvent('BLOCKED_ATTEMPT', 'HIGH', `Blocked installation attempted activation: ${install.domain}`, lic.id, install.installation_id, ip);
		return fail(res, ip, '/api/v1/license/activate', 'DOMAIN_NOT_ALLOWED', 'This installation has been blocked by the vendor.', 403, lic.id);
	} else {
		db.prepare('UPDATE installations SET plugin_version=?, wp_version=?, php_version=?, last_seen=?, ip=? WHERE id=?')
			.run(String(body.plugin_version || install.plugin_version), String(body.wordpress_version || install.wp_version), String(body.php_version || install.php_version), now(), ip, install.id);
	}

	db.prepare('INSERT INTO activations(license_id,installation_id,event,detail,created_at,ip) VALUES(?,?,?,?,?,?)')
		.run(lic.id, install.installation_id, 'ACTIVATE', `${domain} (${environment})`, now(), ip);

	db.prepare('UPDATE licenses SET starts_at = CASE WHEN starts_at = ? THEN ? ELSE starts_at END WHERE id = ?').run('', now(), lic.id);

	const payload = {
		success: true, status: 'active', route: 'activate', at: now(),
		license: licensePayload(lic), installation: { id: install.installation_id, domain: install.domain, environment },
		product: product ? { slug: product.slug, name: product.name, version: product.version } : null,
	};
	payload.compat = clientCompat(lic, product);
	const signed = signPayload(payload);
	apiLog('/api/v1/license/activate', ip, 200, lic.id, domain);
	json(res, 200, { ...JSON.parse(signed.body), signature: signed.signature, algorithm: signed.algorithm });
}

/* --- VALIDATE --- */
function validate(req, res, body, ip) {
	const rl = rateLimit(`validate:${ip}`, 120, 3600);
	if (!rl.ok) return tooMany(res, ip, '/api/v1/license/validate');

	const lic = findLicense(body);
	if (!lic) return fail(res, ip, '/api/v1/license/validate', 'INVALID_LICENSE', 'This license key does not exist.', 404, 0);

	const state = licenseState(lic);
	const product = productOf(lic);
	const payload = {
		success: state === 'ACTIVE', status: state.toLowerCase(), at: now(),
		license: licensePayload(lic), compat: clientCompat(lic, product),
	};
	if (state !== 'ACTIVE') {
		const codes = { SUSPENDED: 'LICENSE_SUSPENDED', REVOKED: 'LICENSE_REVOKED', EXPIRED: 'LICENSE_EXPIRED', PENDING: 'INVALID_LICENSE' };
		securityEvent(`VALIDATE_${state}`, 'LOW', `${lic.key_preview} validated as ${state}`, lic.id, '', ip);
		payload.error = apiError(codes[state] || 'INVALID_LICENSE', `The license is ${state.toLowerCase()}.`);
	}
	const signed = signPayload(payload);
	apiLog('/api/v1/license/validate', ip, 200, lic.id, state);
	json(res, 200, { ...JSON.parse(signed.body), signature: signed.signature, algorithm: signed.algorithm });
}

/* --- DEACTIVATE --- */
function deactivate(req, res, body, ip) {
	const rl = rateLimit(`deactivate:${ip}`, 60, 3600);
	if (!rl.ok) return tooMany(res, ip, '/api/v1/license/deactivate');

	const lic = findLicense(body);
	if (!lic) return fail(res, ip, '/api/v1/license/deactivate', 'INVALID_LICENSE', 'This license key does not exist.', 404);

	const installId = String(body.installation_id || '');
	const domain = normalizeDomain(body.domain || '');
	let install = null;
	if (installId) install = db.prepare('SELECT * FROM installations WHERE installation_id = ? AND license_id = ?').get(installId, lic.id);
	if (!install && domain) install = db.prepare('SELECT * FROM installations WHERE license_id = ? AND domain = ?').get(lic.id, domain);
	if (!install) return fail(res, ip, '/api/v1/license/deactivate', 'INSTALLATION_NOT_FOUND', 'No matching installation for this license.', 404, lic.id);

	db.prepare("UPDATE installations SET status = 'INACTIVE' WHERE id = ?").run(install.id);
	db.prepare('INSERT INTO activations(license_id,installation_id,event,detail,created_at,ip) VALUES(?,?,?,?,?,?)')
		.run(lic.id, install.installation_id, 'DEACTIVATE', install.domain, now(), ip);
	apiLog('/api/v1/license/deactivate', ip, 200, lic.id, install.domain);
	json(res, 200, { success: true, status: 'deactivated', installation_id: install.installation_id });
}

/* --- HEARTBEAT --- */
function heartbeat(req, res, body, ip) {
	const rl = rateLimit(`heartbeat:${ip}`, 240, 3600);
	if (!rl.ok) return tooMany(res, ip, '/api/v1/license/heartbeat');

	const lic = findLicense(body);
	if (!lic) return fail(res, ip, '/api/v1/license/heartbeat', 'INVALID_LICENSE', 'This license key does not exist.', 404, 0);

	const installId = String(body.installation_id || '');
	if (installId) {
		db.prepare('UPDATE installations SET last_seen = ?, plugin_version = COALESCE(NULLIF(?, \'\'), plugin_version) WHERE installation_id = ? AND license_id = ?')
			.run(now(), String(body.plugin_version || ''), installId, lic.id);
	}
	const payload = { success: true, status: licenseState(lic).toLowerCase(), at: now(), license: licensePayload(lic), compat: clientCompat(lic, productOf(lic)) };
	const signed = signPayload(payload);
	apiLog('/api/v1/license/heartbeat', ip, 200, lic.id, installId);
	json(res, 200, { ...JSON.parse(signed.body), signature: signed.signature, algorithm: signed.algorithm });
}

/* --- UPDATES --- */
function versionAllowed(lic, releaseVersion) {
	const rule = String(lic.allowed_versions || '').trim();
	if (!rule) return true;
	if (lic.updates_until && lic.updates_until < now()) return false;
	for (const pattern of rule.split(',').map((x) => x.trim()).filter(Boolean)) {
		const rx = new RegExp('^' + pattern.replace(/\./g, '\\.').replace(/x/g, '\\d+') + '$');
		if (rx.test(releaseVersion)) return true;
	}
	return false;
}

function checkUpdate(req, res, url, body, ip) {
	const rl = rateLimit(`update:${ip}`, 120, 3600);
	if (!rl.ok) return tooMany(res, ip, '/api/v1/license/check-update');

	const q = url.searchParams;
	const lic = findLicense({
		license_key: q.get('license_key') || body.license_key,
		key_hash: q.get('key_hash') || body.key_hash,
	});
	const slug = q.get('product') || body.product || '';
	const product = db.prepare('SELECT * FROM products WHERE slug = ? OR id = ?').get(slug, Number(slug) || -1);
	const current = String(q.get('version') || body.version || '0.0.0');
	const channel = q.get('channel') || body.channel || 'stable';

	if (!lic) return fail(res, ip, '/api/v1/license/check-update', 'INVALID_LICENSE', 'This license key does not exist.', 404);
	if (!product) return fail(res, ip, '/api/v1/license/check-update', 'PRODUCT_NOT_ALLOWED', 'Unknown product.', 404, lic.id);

	const release = db.prepare("SELECT * FROM releases WHERE product_id = ? AND channel = ? AND status = 'PUBLISHED' ORDER BY id DESC LIMIT 1").get(product.id, channel);
	if (!release || release.version === current) {
		return json(res, 200, { success: true, update_available: false, current_version: current });
	}
	if (licenseState(lic) !== 'ACTIVE') {
		return json(res, 200, { success: true, update_available: true, download_allowed: false, error: apiError('LICENSE_EXPIRED', 'Renew your license to download updates.'), latest: release.version });
	}
	if (lic.updates_until && lic.updates_until < now()) {
		return json(res, 200, { success: true, update_available: true, download_allowed: false, error: apiError('UPDATE_NOT_ALLOWED', 'Your update period has ended.'), latest: release.version });
	}
	if (!versionAllowed(lic, release.version)) {
		return json(res, 200, { success: true, update_available: true, download_allowed: false, error: apiError('UPDATE_NOT_ALLOWED', `Version ${release.version} is not covered by this license (${lic.allowed_versions}).`), latest: release.version });
	}

	const token = uid(24);
	db.prepare('INSERT INTO download_tokens(token,release_id,license_id,expires_at) VALUES(?,?,?,?)')
		.run(token, release.id, lic.id, nowTs() + 3600);
	apiLog('/api/v1/license/check-update', ip, 200, lic.id, `${release.version}`);
	json(res, 200, {
		success: true, update_available: true, download_allowed: true, latest: release.version,
		changelog: release.changelog, requires_wp: release.wp_min, requires_php: release.php_min,
		download_url: `${config.APP_URL}/api/v1/download/${token}`, token_expires_in: 3600,
	});
}

function checkVersion(req, res, url, ip) {
	const slug = url.searchParams.get('product') || '';
	const product = db.prepare('SELECT * FROM products WHERE slug = ? OR id = ?').get(slug, Number(slug) || -1);
	if (!product) return fail(res, ip, '/api/v1/license/check-version', 'PRODUCT_NOT_ALLOWED', 'Unknown product.', 404);
	json(res, 200, { success: true, product: product.slug, version: product.version });
}

/* --- DOWNLOAD (URL signée/temporaire) --- */
function download(req, res, url, ip) {
	const token = url.pathname.split('/').pop();
	const row = db.prepare('SELECT * FROM download_tokens WHERE token = ?').get(token);
	if (!row) return fail(res, ip, 'download', 'DOWNLOAD_INVALID', 'Invalid download token.', 404);
	if (row.expires_at < nowTs() || row.used > 5) return fail(res, ip, 'download', 'DOWNLOAD_EXPIRED', 'This download link has expired.', 403);

	const release = db.prepare('SELECT * FROM releases WHERE id = ?').get(row.release_id);
	if (!release || !release.file_path) return fail(res, ip, 'download', 'DOWNLOAD_UNAVAILABLE', 'No package attached to this release.', 404);

	const file = path.join(config.STORAGE_PATH, 'releases', path.basename(release.file_path));
	if (!fs.existsSync(file)) return fail(res, ip, 'download', 'DOWNLOAD_UNAVAILABLE', 'Package file missing on server.', 404);

	db.prepare('UPDATE download_tokens SET used = used + 1 WHERE token = ?').run(token);
	db.prepare('UPDATE releases SET downloads = downloads + 1 WHERE id = ?').run(release.id);
	apiLog('download', ip, 200, row.license_id, `v${release.version}`);
	res.writeHead(200, {
		'Content-Type': 'application/zip',
		'Content-Disposition': `attachment; filename="${path.basename(file)}"`,
	});
	fs.createReadStream(file).pipe(res);
}

/* ---------------- Admin-side license creation ---------------- */
export function createLicense({ customer_id, product_id, plan_id = null, expires_at = '', activation_limit = 1, updates_until = '', allowed_versions = '', notes = '' }) {
	const plainKey = generateLicenseKey('INFC');
	const keyHash = sha256(plainKey.toUpperCase().trim());
	const info = db.prepare(`INSERT INTO licenses(key_hash,key_enc,key_preview,customer_id,product_id,plan_id,status,created_at,starts_at,expires_at,activation_limit,updates_until,allowed_versions,notes)
		VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)`)
		.run(keyHash, encrypt(plainKey), keyPreview(plainKey), customer_id, product_id, plan_id, 'ACTIVE', now(), now(), expires_at, activation_limit, updates_until, allowed_versions, notes);
	return { id: info.lastInsertRowid, key: plainKey, preview: keyPreview(plainKey) };
}

export function revealLicenseKey(licenseId) {
	const lic = db.prepare('SELECT key_enc FROM licenses WHERE id = ?').get(licenseId);
	return lic ? decrypt(lic.key_enc) : '';
}
