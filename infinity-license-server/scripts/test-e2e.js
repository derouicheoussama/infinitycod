/**
 * Test end-to-end — scénario complet du cycle de vie d'une licence.
 * Démarre son propre serveur sur un port de test, exécute le scénario, puis s'arrête.
 * Usage : node scripts/test-e2e.js
 */
process.env.PORT = '8799';
process.env.DB_PATH = fileURLToPath(new URL('../storage/test-e2e.db', import.meta.url));
process.env.APP_URL = 'http://127.0.0.1:8799';

import { fileURLToPath } from 'node:url';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const DB = process.env.DB_PATH;
for (const f of [DB, DB + '-wal', DB + '-shm']) { if (fs.existsSync(f)) fs.unlinkSync(f); }

const BASE = 'http://127.0.0.1:8799';
let cookie = '';
let passed = 0, failed = 0;
let lastStatus = 0;

async function check(name, cond, extra = '') {
	if (cond) { passed++; console.log(`  ✓ ${name}`); }
	else { failed++; console.log(`  ✗ ${name} ${extra}`); }
}

async function req(method, p, body, form) {
	const headers = {};
	if (cookie) headers.Cookie = cookie;
	let payload;
	if (form) { headers['Content-Type'] = 'application/x-www-form-urlencoded'; payload = new URLSearchParams(body).toString(); }
	else if (body) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
	const res = await fetch(BASE + p, { method, headers, body: payload, redirect: 'manual' });
	const setCookie = res.headers.get('set-cookie');
	if (setCookie) cookie = setCookie.split(';')[0];
	const text = await res.text();
	lastStatus = res.status;
	let json = null;
	try { json = JSON.parse(text); } catch { }
	return { res, text, json, status: res.status };
}
async function postForm(p, body) { return req('POST', p, body, true); }
async function get(p) { return req('GET', p); }

async function main() {
	console.log('\n=== Infinity License Server — E2E (§51) ===\n');
	// Serveur DANS ce process (la sandbox interdit les spawns fils).
	await import('file:///' + path.join(ROOT, 'server.js').split(path.sep).join('/'));
	await new Promise((r) => setTimeout(r, 800));

	try {
		// 1. Setup super admin.
		let r = await get('/setup');
		check('Setup page accessible', r.res.status === 200);
		r = await postForm('/setup', { name: 'Root', email: 'root@infinitycod.pro', password: 'SuperSecret123', confirm: 'SuperSecret123' });
		check('Super admin created + signed in', r.res.status === 302);

		// 2. Dashboard with real counters.
		r = await get('/admin');
		check('Dashboard renders (logged in)', r.res.status === 200 && r.text.includes('Active licenses'));

		const CSRF = await getCsrf();
		// 3. Manual order PAID (customer auto-created).
		const products = (await req('GET', '/products')).text;
		check('Public products page lists InfinityCOD', products.includes('InfinityCOD'));
		r = await postForm('/admin/orders/create', {
			customer_name: 'Test Client', customer_email: 'client@test.dz', product_id: '1', plan_id: '1', amount: '39', currency: 'USD', status: 'PAID', payment_ref: 'PAYPAL-TEST-1', csrf: CSRF,
		});
		check('Manual PAID order created', r.res.status === 302);

		// 4. Find order id + generate license.
		r = await get('/admin/orders');
		const orderRow = r.text.match(/ORD-[A-Z0-9]+/);
		check('Order visible in dashboard', !!orderRow);
		const orderId = r.text.match(/name="id" value="(\d+)">\s*<button name="do" value="generate_license"/);
		check('Generate-license button present for PAID order', !!orderId);

		r = await postForm('/admin/orders', { csrf: (await getCsrf()), do: 'generate_license', id: orderId ? orderId[1] : '1' });
		r = await get('/admin/orders');
		const licLink = r.text.match(/\/admin\/licenses\/(\d+)"/);
		check('License generated from order', !!licLink);

		// 5. Reveal full key (audited).
		r = await postForm(`/admin/licenses/${licLink[1]}`, { csrf: (await getCsrf()), do: 'reveal' });
		const keyMatch = r.text.match(/Full key: <span class="mono"[^>]*>(INFC-[A-Z0-9-]+)</);
		check('Full key revealed to admin', !!keyMatch);
		const LICENSE_KEY = keyMatch[1];

		// 6. Activate from simulated WordPress.
		const installId = crypto.randomBytes(8).toString('hex');
		r = await req('POST', '/api/v1/license/activate', {
			license_key: LICENSE_KEY, product_id: 'infinitycod', domain: 'https://www.boutique-test.dz',
			site_url: 'https://www.boutique-test.dz', plugin_version: '4.4.0', wordpress_version: '6.7', php_version: '8.2',
			installation_id: installId,
		});
		check('API activate → success + signed', r.json?.success === true && !!r.json?.signature);
		check('InfinityCOD compat: status ACTIVE', r.json?.compat?.status === 'ACTIVE');
		check('Domain normalized (www stripped)', r.json?.installation?.domain === 'boutique-test.dz');

		// 7. Re-activate same installation: allowed, no new row.
		r = await req('POST', '/api/v1/license/activate', { license_key: LICENSE_KEY, installation_id: installId, domain: 'boutique-test.dz' });
		check('Re-activation of same installation OK', r.json?.success === true);

		// 8. Heartbeat.
		r = await req('POST', '/api/v1/license/heartbeat', { license_key: LICENSE_KEY, installation_id: installId, plugin_version: '4.5.0' });
		check('Heartbeat OK', r.json?.success === true);

		// 9. Validate.
		r = await req('POST', '/api/v1/license/validate', { license_key: LICENSE_KEY });
		check('Validate → active', r.json?.status === 'active');

		// 10. Invalid key rejected.
		r = await req('POST', '/api/v1/license/validate', { license_key: 'INFC-FAKE-FAKE-FAKE-FAKE' });
		check('Invalid license → 404 INVALID_LICENSE', r.res.status === 404 && r.json?.error?.code === 'INVALID_LICENSE');

		// 11. Suspension from dashboard → validate reflects it.
		r = await postForm(`/admin/licenses/${licLink[1]}`, { csrf: (await getCsrf()), id: licLink[1], do: 'suspend', reason: 'e2e test' });
		r = await req('POST', '/api/v1/license/validate', { license_key: LICENSE_KEY });
		check('Suspended license → validate not active', r.json?.status !== 'active');
		check('Suspension logged in security events', (await get('/admin/security')).text.includes('VALIDATE_SUSPENDED'));

		// 12. Resume.
		r = await postForm(`/admin/licenses/${licLink[1]}`, { csrf: (await getCsrf()), id: licLink[1], do: 'resume' });
		r = await req('POST', '/api/v1/license/validate', { license_key: LICENSE_KEY });
		check('Resumed license → active', r.json?.status === 'active');

		// 13. Update entitlement (no release published yet).
		r = await req('GET', `/api/v1/license/check-update?license_key=${encodeURIComponent(LICENSE_KEY)}&product=infinitycod&version=4.4.0`);
		check('Check-update: no update available', r.json?.update_available === false);

		// 14. Expiration : manipulation directe de la date pour simuler le temps.
		r = await postForm(`/admin/licenses/${licLink[1]}`, { csrf: (await getCsrf()), id: licLink[1], do: 'extend', days: '-400' });
		r = await req('POST', '/api/v1/license/validate', { license_key: LICENSE_KEY });
		check('Expired license → validate returns expired', r.json?.status === 'expired' && r.json?.error?.code === 'LICENSE_EXPIRED');

		// 15. Rate limiting.
		let limited = false;
		for (let i = 0; i < 130; i++) {
			const rr = await req('POST', '/api/v1/license/validate', { license_key: 'INFC-ZZZ-ZZZ-ZZZ-ZZZ' });
			if (rr.res.status === 429) { limited = true; break; }
		}
		check('Rate limiting kicks in (429)', limited);

		// 16. Public key endpoint.
		r = await get('/api/v1/public-key');
		check('Public key endpoint (Ed25519 PEM)', r.json?.public_key?.includes('BEGIN PUBLIC KEY'));

		// 17. Health.
		r = await get('/health');
		check('Health endpoint OK', r.json?.status === 'ok');

	} catch (e) {
		failed++;
		console.log('  ✗ EXCEPTION:', e.message);
	} finally {
	}

	console.log(`\n=== RÉSULTAT : ${passed} OK / ${failed} ÉCHEC(S) ===\n`);
	process.exit(failed ? 1 : 0);
}

async function getCsrf() {
	// Le CSRF est extrait de n'importe quelle page admin.
	const r = await get('/admin');
	const m = r.text.match(/name="csrf" value="([a-f0-9]+)"/);
	return m ? m[1] : '';
}

main();
