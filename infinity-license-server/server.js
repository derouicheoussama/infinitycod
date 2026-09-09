// Infinity License Server — point d'entrée (HTTP natif, zéro dépendance).
import http from 'node:http';
import path from 'node:path';
import zlib from 'node:zlib';
import { config, ensureStorage } from './src/config.js';
import { seed } from './src/seed.js';
import { handleApi } from './src/api.js';
import { handleAuthRoutes, currentAdmin, adminsExist } from './src/auth.js';
import * as admin from './src/admin.js';
import { landingPage, productsPage, productPage, checkoutPage, checkoutDone } from './src/views.js';
import { db } from './src/db.js';
import { json, esc } from './src/core.js';
import { adminCss, ADMIN_JS, assetEtag } from './src/assets.js';

ensureStorage();
seed();

const PORT = config.PORT;

/* ---------- Headers de sécurité + compression ---------- */
function secureHeaders(res) {
	res.setHeader('X-Frame-Options', 'DENY');
	res.setHeader('X-Content-Type-Options', 'nosniff');
	res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
	res.setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
	res.setHeader('Content-Security-Policy',
		"default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.paypal.com");
}

import { secureSend as sendHtml } from './src/views.js';

function sendAsset(req, res, name, content, type) {
	secureHeaders(res);
	const etag = assetEtag(name, content);
	if (req.headers['if-none-match'] === etag) {
		res.writeHead(304, { ETag: etag });
		return res.end();
	}
	res.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'public, max-age=3600', ETag: etag });
	res.end(content);
}

/* ---------- Parsing helpers ---------- */
function parseBody(req) {
	return new Promise((resolve) => {
		let data = '';
		const ct = req.headers['content-type'] || '';
		req.on('data', (c) => { data += c; if (data.length > 60 * 1024 * 1024) req.destroy(); });
		req.on('end', () => {
			if (ct.includes('application/json')) {
				try { resolve(JSON.parse(data || '{}')); } catch { resolve({}); }
			} else if (ct.includes('multipart/form-data')) {
				// Multipart minimal : ne gère que l'upload ZIP des releases (champ zip_file) via base64 côté admin —
				// ici on extrait les champs textuels et le fichier en base64.
				const m = data.match(/name="zip_file".*?\r\n\r\n([\s\S]*?)\r\n--/);
				const out = { __multipart: true };
				for (const mm of data.matchAll(/name="([^"]+)"\r\n\r\n([\s\S]*?)\r\n--/g)) {
					out[mm[1]] = mm[2];
				}
				if (m) out.zip_b64 = Buffer.from(m[1], 'binary').toString('base64');
				resolve(out);
			} else {
				const out = {};
				for (const [k, v] of new URLSearchParams(data)) out[k] = v;
				resolve(out);
			}
		});
	});
}

/* ---------- Server ---------- */
const server = http.createServer(async (req, res) => {
	try {
		await handle(req, res);
	} catch (e) {
		console.error('[error]', req.method, req.url, '→', e.stack || e.message);
		try { json(res, 500, { success: false, error: { code: 'SERVER_UNAVAILABLE', message: 'Internal error.' } }); } catch { /* headers déjà envoyés */ }
	}
});

function redirect302(res, loc) { res.writeHead(302, { Location: loc }); res.end(); }

async function handle(req, res) {
	const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
	const ip = req.socket.remoteAddress || '';
	req.urlQuery = url.searchParams;

	// API publique (licence) + health : pas de session.
	if (url.pathname === '/health' || url.pathname.startsWith('/api/')) {
		let body = {};
		if (req.method === 'POST') body = await parseBody(req);
		return handleApi(req, res, url, body, ip);
	}

	let body = {};
	if (req.method === 'POST') body = await parseBody(req);

	// Auth publiques (setup / login / logout).
	const handled = await handleAuthRoutes(req, res, url, body, ip, (res2, code, html) => sendHtml(req, res2, code, html));
	if (handled) return;

	// Assets statiques mis en cache (CSS + JS du dashboard).
	if (url.pathname === '/assets/admin.css') return sendAsset(req, res, 'css', adminCss(), 'text/css; charset=utf-8');
	if (url.pathname === '/assets/admin.js') return sendAsset(req, res, 'js', ADMIN_JS, 'text/javascript; charset=utf-8');

	// Notifications : page dédiée + marquage lu (admin uniquement).
	if (url.pathname === '/admin/notifications') {
		const a0 = currentAdmin(req);
		if (!a0) { res.writeHead(302, { Location: '/login' }); return res.end(); }
		if (req.method === 'POST') {
			if (body.csrf !== a0.csrf) return json(res, 403, { error: 'CSRF' });
			db.prepare('UPDATE notifications SET read = 1').run();
			return redirect302(res, '/admin/notifications');
		}
		const rows = db.prepare('SELECT * FROM notifications ORDER BY id DESC LIMIT 100').all();
		const unread = db.prepare('SELECT COUNT(*) c FROM notifications WHERE read = 0').get().c;
		return sendHtml(req, res, 200, admin.adminNotificationsView(a0, rows, unread, body));
	}


	if (url.pathname === '/setup' && adminsExist()) { res.writeHead(302, { Location: '/login' }); return res.end(); }

	// Pages publiques du site vitrine.
	if (url.pathname === '/') {
		const products = db.prepare("SELECT * FROM products WHERE status='PUBLISHED' ORDER BY id").all();
		const plans = db.prepare("SELECT * FROM plans WHERE status='ACTIVE' ORDER BY sort").all();
		return landingPage(req, res, products, plans);
	}
	if (url.pathname === '/products') {
		const products = db.prepare("SELECT * FROM products WHERE status='PUBLISHED' ORDER BY id").all();
		return productsPage(req, res, products);
	}
	const prodMatch = url.pathname.match(/^\/products\/([a-z0-9-]+)$/);
	if (prodMatch) {
		const product = db.prepare('SELECT * FROM products WHERE slug = ? AND status = ?').get(prodMatch[1], 'PUBLISHED');
		if (!product) { res.writeHead(404); return res.end('Not found'); }
		const plans = db.prepare("SELECT * FROM plans WHERE status='ACTIVE' ORDER BY sort").all();
		const releases = db.prepare("SELECT * FROM releases WHERE product_id = ? AND status='PUBLISHED' ORDER BY id DESC LIMIT 10").all(product.id);
		return productPage(req, res, product, plans, releases);
	}
	if (url.pathname === '/checkout' && req.method === 'GET') {
		const planId = Number(url.searchParams.get('plan') || 0);
		const plan = db.prepare('SELECT * FROM plans WHERE id = ?').get(planId);
		if (!plan) { res.writeHead(404); return res.end('Plan not found'); }
		const product = db.prepare('SELECT * FROM products WHERE id = ?').get(plan.product_id || Number(url.searchParams.get('product') || 0) || (db.prepare('SELECT id FROM products LIMIT 1').get() || {}).id);
		return checkoutPage(req, res, plan, product);
	}
	if (url.pathname === '/checkout' && req.method === 'POST') {
		const plan = db.prepare('SELECT * FROM plans WHERE id = ?').get(Number(body.plan_id || 0));
		if (!plan) { res.writeHead(404); return res.end('Plan not found'); }
		const product = db.prepare('SELECT * FROM products WHERE id = ?').get(plan.product_id || Number(body.product_id || 0));
		if (!body.email || !body.first_name || !body.last_name) {
			return checkoutPage(req, res, plan, product, 'First name, last name and a valid email are required.', body);
		}
		const email = String(body.email).toLowerCase().trim();
		let customer = db.prepare('SELECT * FROM customers WHERE email = ?').get(email);
		if (!customer) {
			db.prepare('INSERT INTO customers(name,email,phone,company,country,created_at,last_activity) VALUES(?,?,?,?,?,?)')
				.run(`${body.first_name} ${body.last_name}`, email, body.phone || '', body.company || '', body.country || '', new Date().toISOString().replace('T', ' ').slice(0, 19), new Date().toISOString().replace('T', ' ').slice(0, 19));
			customer = db.prepare('SELECT * FROM customers WHERE email = ?').get(email);
		}
		const reference = 'ORD-' + Math.random().toString(36).slice(2, 8).toUpperCase();
		db.prepare(`INSERT INTO orders(reference,customer_id,product_id,plan_id,amount,currency,status,payment_method,created_at)
			VALUES(?,?,?,?,?,?,?,?,?)`)
			.run(reference, customer.id, product ? product.id : null, plan.id, plan.price, plan.currency, 'PENDING', body.payment_method || 'manual', new Date().toISOString().replace('T', ' ').slice(0, 19));
		const order = db.prepare('SELECT * FROM orders WHERE reference = ?').get(reference);
		db.prepare('INSERT INTO notifications(ts,type,message) VALUES(?,?,?)').run(new Date().toISOString().replace('T', ' ').slice(0, 19), 'order', `New order ${reference} ($${plan.price}) from ${email}`);
		return checkoutDone(req, res, order, '', email);
	}

	// Dashboard admin (protégé).
	if (url.pathname.startsWith('/admin')) {
		const p = url.pathname;
		const adminRow = currentAdmin(req);
		if (adminRow) {
			adminRow.unread = db.prepare('SELECT COUNT(*) c FROM notifications WHERE read = 0').get().c;
		}
		if (!adminRow) { res.writeHead(302, { Location: adminsExist() ? '/login' : '/setup' }); return res.end(); }

		// Rôles : support/viewer = lecture seule ; emails/settings/admins/sécurité = admin+.
		const WRITE_ROLES = ['super_admin', 'admin', 'sales'];
		const ADMIN_ROLES = ['super_admin', 'admin'];
		if (req.method === 'POST' && p !== '/logout' && !WRITE_ROLES.includes(adminRow.role)) {
			return json(res, 403, { error: 'Read-only role (' + adminRow.role + ').' });
		}
		if (req.method === 'POST' && ['/admin/settings', '/admin/admins'].includes(p) && !ADMIN_ROLES.includes(adminRow.role)) {
			return json(res, 403, { error: 'Administrators only.' });
		}
		if (req.method === 'POST' && ['/admin/security', '/admin/emails'].includes(p) && !ADMIN_ROLES.includes(adminRow.role)) {
			return json(res, 403, { error: 'Administrators only.' });
		}

		// CSRF sur tous les POST admin.
		if (req.method === 'POST' && body.csrf !== adminRow.csrf) {
			return json(res, 403, { error: 'CSRF token invalid' });
		}

		switch (p) {
			case '/admin': return admin.adminOverview(req, res, adminRow);
			case '/admin/products': return admin.adminProducts(req, res, adminRow, url, body);
			case '/admin/plans': return admin.adminPlans(req, res, adminRow, url, body);
			case '/admin/licenses': return admin.adminLicenses(req, res, adminRow, url, body);
			case '/admin/licenses/create': return admin.adminLicenses(req, res, adminRow, url, body);
			case '/admin/installations': return admin.adminInstallations(req, res, adminRow, url, body);
			case '/admin/orders': return admin.adminOrders(req, res, adminRow, url, body);
			case '/admin/orders/create': return admin.adminOrders(req, res, adminRow, url, body);
			case '/admin/customers': return admin.adminCustomers(req, res, adminRow, url, body);
			case '/admin/activations': return admin.adminActivations(req, res, adminRow);
			case '/admin/releases': return admin.adminReleases(req, res, adminRow, url, body);
			case '/admin/security': return admin.adminSecurity(req, res, adminRow);
			case '/admin/api-logs': return admin.adminApiLogs(req, res, adminRow);
			case '/admin/audit-logs': return admin.adminAuditLogs(req, res, adminRow);
			case '/admin/emails': return admin.adminEmails(req, res, adminRow, url, body);
			case '/admin/email-logs': return admin.adminEmailLogs(req, res, adminRow);
			case '/admin/settings': return admin.adminSettings(req, res, adminRow, url, body);
			case '/admin/admins': return admin.adminAdmins(req, res, adminRow, url, body);
			case '/admin/search': return admin.adminSearch(req, res, adminRow, url);
			default:
				if (p.startsWith('/admin/licenses/')) return admin.adminLicenses(req, res, adminRow, url, body);
				if (p.startsWith('/admin/installations/')) return admin.adminInstallations(req, res, adminRow, url, body);
				if (p.startsWith('/admin/customers/')) return admin.adminCustomers(req, res, adminRow, url, body);
				if (p.startsWith('/admin/export/')) return admin.adminExport(req, res, adminRow, url);
				res.writeHead(404); return res.end('Not found');
		}
	}

	res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
	res.end('<!doctype html><body style="font-family:system-ui;padding:60px;text-align:center"><h1>404</h1><p><a href="/">Back to home</a></p></body>');
}

server.listen(PORT, () => {
	console.log(`\n  Infinity License Server`);
	console.log(`  → App       : ${config.APP_URL}`);
	console.log(`  → Dashboard : ${config.APP_URL}${adminsExist() ? '/login' : '/setup'}`);
	console.log(`  → API       : ${config.APP_URL}/api/v1/license/activate`);
	console.log(`  → Health    : ${config.APP_URL}/health\n`);
});
