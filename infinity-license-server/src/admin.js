import fs from 'node:fs';
import path from 'node:path';
import { db } from './db.js';
import { config } from './config.js';
import {
	json, now, nowTs, uid, sha256, esc, fmtDate, rateLimit, audit,
	generateLicenseKey, encrypt, keyPreview,
} from './core.js';
import { page, csrfField as cf, svgBars } from './views.js';
import { createLicense, revealLicenseKey } from './api.js';

/* ---------- Helpers ---------- */
function q(req, key, fallback = '') {
	return req.urlQuery.get(key) ?? fallback;
}
function paginate(total, perPage, current, base) {
	const pages = Math.max(1, Math.ceil(total / perPage));
	if (pages < 2) return '';
	let out = '<div class="pagination">';
	for (let p = 1; p <= Math.min(pages, 15); p++) out += `<a class="${p === current ? 'on' : ''}" href="${base}${base.includes('?') ? '&' : '?'}paged=${p}">${p}</a>`;
	if (pages > 15) out += `<span class="muted"> … ${pages} pages</span>`;
	return out + '</div>';
}
function statusBadge(s) { return `<span class="badge b-${esc(s)}">${esc(s)}</span>`; }

function table(headers, rows) {
	return `<div style="overflow-x:auto"><table class="tbl"><thead><tr>${headers.map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${
		rows.length ? rows.join('') : `<tr><td colspan="${headers.length}" class="muted">Nothing here yet.</td></tr>`
	}</tbody></table></div>`;
}

/* ---------- Overview ---------- */
export function adminOverview(req, res, admin) {
	const one = (sql) => db.prepare(sql).get().c;
	const kpi = {
		products: one('SELECT COUNT(*) c FROM products'),
		customers: one('SELECT COUNT(*) c FROM customers'),
		licensesActive: one("SELECT COUNT(*) c FROM licenses WHERE status='ACTIVE'"),
			licensesExpired: db.prepare("SELECT COUNT(*) c FROM licenses WHERE status='EXPIRED' OR (status='ACTIVE' AND expires_at != '' AND expires_at < ?)").get(now()).c,
		licensesSuspended: one("SELECT COUNT(*) c FROM licenses WHERE status='SUSPENDED'"),
		licensesRevoked: one("SELECT COUNT(*) c FROM licenses WHERE status='REVOKED'"),
		installations: one("SELECT COUNT(*) c FROM installations WHERE status='ACTIVE'"),
		activations: one('SELECT COUNT(*) c FROM activations'),
		revenue: db.prepare("SELECT COALESCE(SUM(amount),0) s FROM orders WHERE status='PAID'").get().s,
	};
	const days = 14;
	const chartRows = db.prepare(`SELECT substr(created_at,1,10) d, COUNT(*) c FROM licenses WHERE created_at >= ? GROUP BY d ORDER BY d`).get(
		new Date(Date.now() - days * 864e5).toISOString().slice(0, 10)
	) ? [] : [];
	const licChart = db.prepare(`SELECT substr(created_at,1,10) d, COUNT(*) c FROM licenses WHERE created_at >= datetime('now','-14 days') GROUP BY d ORDER BY d`).all();
	const chartData = [];
	for (let i = days - 1; i >= 0; i--) {
		const d = new Date(Date.now() - i * 864e5).toISOString().slice(0, 10);
		const found = licChart.find((r) => r.d === d);
		chartData.push([d.slice(5), found ? found.c : 0]);
	}
	const recent = db.prepare(`SELECT l.*, c.name customer, p.name product FROM licenses l JOIN customers c ON c.id=l.customer_id JOIN products p ON p.id=l.product_id ORDER BY l.id DESC LIMIT 8`).all();

	const content = `
<div class="grid">
	<div class="kpi"><div class="v">${kpi.products}</div><div class="l">Products</div></div>
	<div class="kpi"><div class="v">${kpi.customers}</div><div class="l">Customers</div></div>
	<div class="kpi"><div class="v">${kpi.licensesActive}</div><div class="l">Active licenses</div></div>
	<div class="kpi"><div class="v">${kpi.licensesExpired}</div><div class="l">Expired</div></div>
	<div class="kpi"><div class="v">${kpi.licensesSuspended}</div><div class="l">Suspended</div></div>
	<div class="kpi"><div class="v">${kpi.licensesRevoked}</div><div class="l">Revoked</div></div>
	<div class="kpi"><div class="v">${kpi.installations}</div><div class="l">Active installations</div></div>
	<div class="kpi"><div class="v">${kpi.activations}</div><div class="l">Activations total</div></div>
	<div class="kpi"><div class="v">$${Number(kpi.revenue).toFixed(2)}</div><div class="l">Revenue (paid orders)</div></div>
</div>
<div class="card"><h2>Licenses created — last 14 days</h2>${svgBars(chartData)}</div>
<div class="card"><h2>Latest licenses</h2>${table(['Key', 'Customer', 'Product', 'Status', 'Expires', ''],
	recent.map((l) => `<tr><td class="mono"><a href="/admin/licenses/${l.id}">${esc(l.key_preview)}</a></td><td>${esc(l.customer)}</td><td>${esc(l.product)}</td><td>${statusBadge(l.status)}</td><td>${fmtDate(l.expires_at)}</td><td><a class="btn sm" href="/admin/licenses/${l.id}">View</a></td></tr>`))}</div>`;
	page(req, admin, res, 200, 'Dashboard', content, '/admin');
}

/* ---------- Products ---------- */
export function adminProducts(req, res, admin, url, body) {
	if (req.method === 'POST' && body.do === 'create') {
		db.prepare(`INSERT INTO products(slug,name,tagline,description,features,logo_emoji,version,wp_min,php_min,status,github_owner,github_repo,docs_url,created_at)
			VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)`)
			.run(String(body.slug || '').toLowerCase().replace(/[^a-z0-9-]/g, ''), body.name, body.tagline || '', body.description || '',
				JSON.stringify(String(body.features || '').split('\n').map((x) => x.trim()).filter(Boolean)), body.logo_emoji || '🧩',
				body.version || '1.0.0', body.wp_min || '6.0', body.php_min || '7.4', body.status || 'PUBLISHED',
				body.github_owner || '', body.github_repo || '', body.docs_url || '', now());
		audit(admin, 'product.created', body.slug, '', ip(req));
		return redirect(res, '/admin/products');
	}
	if (req.method === 'POST' && body.do === 'update') {
		db.prepare(`UPDATE products SET name=?,tagline=?,description=?,features=?,logo_emoji=?,version=?,wp_min=?,php_min=?,status=?,github_owner=?,github_repo=?,docs_url=? WHERE id=?`)
			.run(body.name, body.tagline || '', body.description || '',
				JSON.stringify(String(body.features || '').split('\n').map((x) => x.trim()).filter(Boolean)), body.logo_emoji || '🧩',
				body.version || '1.0.0', body.wp_min || '6.0', body.php_min || '7.4', body.status || 'PUBLISHED',
				body.github_owner || '', body.github_repo || '', body.docs_url || '', Number(body.id));
		audit(admin, 'product.edited', body.slug || body.id, '', ip(req));
		return redirect(res, '/admin/products');
	}

	const editId = Number(q(req, 'edit') || 0);
	const editing = editId ? db.prepare('SELECT * FROM products WHERE id = ?').get(editId) : null;
	const rows = db.prepare('SELECT * FROM products ORDER BY id').all().map((p) => `<tr>
		<td>${esc(p.logo_emoji)}</td><td class="mono">${esc(p.slug)}</td><td><strong>${esc(p.name)}</strong></td><td>v${esc(p.version)}</td>
		<td>${statusBadge(p.status)}</td><td>${esc(p.github_owner ? p.github_owner + '/' + p.github_repo : '—')}</td>
		<td><a class="btn sm" href="/admin/products?edit=${p.id}">Edit</a> <a class="btn sm" href="/products/${esc(p.slug)}">Public</a></td></tr>`);

	const form = editing
		? `<div class="card"><h2>Edit product — ${esc(editing.name)}</h2>
	<form method="post">${cf(admin)}<input type="hidden" name="do" value="update"><input type="hidden" name="id" value="${editing.id}"><input type="hidden" name="slug" value="${esc(editing.slug)}">
	<div class="grid"><div><label>Name</label><input name="name" required value="${esc(editing.name)}"></div><div><label>Emoji logo</label><input name="logo_emoji" value="${esc(editing.logo_emoji)}"></div>
	<div><label>Version</label><input name="version" value="${esc(editing.version)}"></div><div><label>Status</label><select name="status"><option ${editing.status === 'PUBLISHED' ? 'selected' : ''}>PUBLISHED</option><option ${editing.status === 'DRAFT' ? 'selected' : ''}>DRAFT</option></select></div></div>
	<label>Tagline</label><input name="tagline" style="width:100%" value="${esc(editing.tagline)}">
	<label>Description</label><textarea name="description" rows="3" style="width:100%">${esc(editing.description)}</textarea>
	<label>Features (one per line)</label><textarea name="features" rows="5" style="width:100%">${esc(JSON.parse(editing.features || '[]').join('\n'))}</textarea>
	<div class="grid"><div><label>WP min</label><input name="wp_min" value="${esc(editing.wp_min)}"></div><div><label>PHP min</label><input name="php_min" value="${esc(editing.php_min)}"></div>
	<div><label>GitHub owner</label><input name="github_owner" value="${esc(editing.github_owner)}"></div><div><label>GitHub repo</label><input name="github_repo" value="${esc(editing.github_repo)}"></div>
	<div><label>Docs URL</label><input name="docs_url" value="${esc(editing.docs_url)}"></div></div>
	<p><button class="btn primary">Save product</button> <a class="btn" href="/admin/products">Cancel</a></p></form></div>`
		: `<div class="card"><h2>Create product</h2>
	<form method="post">${cf(admin)}<input type="hidden" name="do" value="create">
	<div class="grid"><div><label>Slug *</label><input name="slug" required placeholder="infinitycod"></div><div><label>Name *</label><input name="name" required></div>
	<div><label>Emoji logo</label><input name="logo_emoji" value="🧩"></div><div><label>Version</label><input name="version" value="1.0.0"></div></div>
	<label>Tagline</label><input name="tagline" style="width:100%">
	<label>Features (one per line)</label><textarea name="features" rows="4" style="width:100%"></textarea>
	<p><button class="btn primary">Create product</button></p></form></div>`;

	page(req, admin, res, 200, 'Products', form + table(['', 'Slug', 'Name', 'Version', 'Status', 'GitHub', ''], rows), '/admin/products');
}

/* ---------- Plans ---------- */
export function adminPlans(req, res, admin, url, body) {
	if (req.method === 'POST') {
		if (body.do === 'create') {
			db.prepare('INSERT INTO plans(product_id,name,price,currency,duration_days,sites_limit,updates_months,support,features,status,sort) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
				.run(Number(body.product_id) || null, body.name, Number(body.price) || 0, body.currency || 'USD', Number(body.duration_days) || 365,
					Number(body.sites_limit) || 1, Number(body.updates_months) || 12, body.support || 'email', '[]', 'ACTIVE', Number(body.sort) || 0);
			audit(admin, 'plan.created', body.name, '', ip(req));
		} else if (body.do === 'toggle') {
			db.prepare("UPDATE plans SET status = CASE WHEN status='ACTIVE' THEN 'INACTIVE' ELSE 'ACTIVE' END WHERE id = ?").run(Number(body.id));
			audit(admin, 'plan.toggled', body.id, '', ip(req));
		}
		return redirect(res, '/admin/plans');
	}
	const products = db.prepare('SELECT * FROM products').all();
	const prodName = (id) => (products.find((p) => p.id === id) || {}).name || 'All products';
	const rows = db.prepare('SELECT * FROM plans ORDER BY sort, id').all().map((pl) => `<tr>
		<td><strong>${esc(pl.name)}</strong></td><td>${esc(prodName(pl.product_id))}</td><td>$${pl.price}</td><td>${pl.duration_days === 0 ? 'Lifetime' : pl.duration_days + 'd'}</td>
		<td>${pl.sites_limit}</td><td>${pl.updates_months}mo</td><td>${statusBadge(pl.status)}</td>
		<td><form method="post" style="display:inline">${cf(admin)}<input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="${pl.id}"><button class="btn sm">Toggle</button></form></td></tr>`);
	const options = products.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
	page(req, admin, res, 200, 'Plans', `
<div class="card"><h2>Create plan</h2><form method="post">${cf(admin)}<input type="hidden" name="do" value="create">
<div class="grid"><div><label>Product</label><select name="product_id"><option value="">All products</option>${options}</select></div>
<div><label>Name *</label><input name="name" required></div><div><label>Price</label><input name="price" type="number" step="0.01" value="39"></div>
<div><label>Currency</label><select name="currency"><option>USD</option><option>EUR</option></select></div>
<div><label>Duration (days, 0=lifetime)</label><input name="duration_days" type="number" value="365"></div>
<div><label>Sites limit</label><input name="sites_limit" type="number" value="1"></div>
<div><label>Updates (months)</label><input name="updates_months" type="number" value="12"></div>
<div><label>Support</label><select name="support"><option>email</option><option>priority</option></select></div></div>
<p><button class="btn primary">Create plan</button></p></form></div>
` + table(['Plan', 'Product', 'Price', 'Duration', 'Sites', 'Updates', 'Status', ''], rows), '/admin/plans');
}

/* ---------- Licenses ---------- */
export function adminLicenses(req, res, admin, url, body) {
	const pathn = url.pathname;
	const mDetail = pathn.match(/^\/admin\/licenses\/(\d+)$/);
	if (mDetail) {
		// Actions sur une licence (POST) : traitées avant le rendu détail.
		if (req.method === 'POST' && body.do && body.do !== 'reveal' && body.id) {
			return applyLicenseAction(req, res, admin, body);
		}
		return licenseDetail(req, res, admin, Number(mDetail[1]), body);
	}

	if (req.method === 'POST' && body.do) {
		return applyLicenseAction(req, res, admin, body);
	}

	/* Actions applicables à une licence (suspend/resume/revoke/extend/limit/regenerate). */
	function applyLicenseAction(req, res, admin, body) {
		const id = Number(body.id || 0);
		const lic = db.prepare('SELECT * FROM licenses WHERE id = ?').get(id);
		if (!lic) return redirect(res, '/admin/licenses');
		const reason = String(body.reason || '');
		switch (body.do) {
			case 'suspend': db.prepare("UPDATE licenses SET status='SUSPENDED' WHERE id=?").run(id); audit(admin, 'license.suspended', lic.key_preview, reason, ip(req)); break;
			case 'resume': db.prepare("UPDATE licenses SET status='ACTIVE' WHERE id=?").run(id); audit(admin, 'license.resumed', lic.key_preview, '', ip(req)); break;
			case 'revoke': db.prepare("UPDATE licenses SET status='REVOKED' WHERE id=?").run(id); audit(admin, 'license.revoked', lic.key_preview, reason, ip(req)); break;
			case 'extend': {
				const days = Number(body.days || 0);
				const base = lic.expires_at && lic.expires_at > now() ? new Date(lic.expires_at) : new Date();
				base.setDate(base.getDate() + days);
				db.prepare("UPDATE licenses SET expires_at=?, status=CASE WHEN status='EXPIRED' THEN 'ACTIVE' ELSE status END WHERE id=?").run(base.toISOString().replace('T', ' ').slice(0, 19), id);
				audit(admin, 'license.extended', lic.key_preview, `${days}d`, ip(req));
				break;
			}
			case 'limit': db.prepare('UPDATE licenses SET activation_limit=? WHERE id=?').run(Math.max(1, Number(body.activation_limit) || 1), id); audit(admin, 'license.limit_changed', lic.key_preview, body.activation_limit, ip(req)); break;
			case 'regenerate': {
				const plain = generateLicenseKey('INFC');
				db.prepare('UPDATE licenses SET key_hash=?, key_enc=?, key_preview=? WHERE id=?').run(sha256(plain), encrypt(plain), keyPreview(plain), id);
				audit(admin, 'license.regenerated', lic.key_preview, '', ip(req));
				return redirect(res, `/admin/licenses/${id}?newkey=` + encodeURIComponent(plain));
			}
		}
		return redirect(res, body.back || `/admin/licenses/${id}`);
	}

	// Create license (manual).
	if (url.pathname === '/admin/licenses/create' && req.method === 'POST') {
		const customer = ensureCustomer(body.customer_email, body.customer_name);
		const plan = Number(body.plan_id) ? db.prepare('SELECT * FROM plans WHERE id=?').get(Number(body.plan_id)) : null;
		const days = plan ? plan.duration_days : Number(body.days || 365);
		const exp = days > 0 ? new Date(Date.now() + days * 864e5).toISOString().replace('T', ' ').slice(0, 19) : '';
		const lic = createLicense({
			customer_id: customer.id, product_id: Number(body.product_id), plan_id: plan ? plan.id : null,
			expires_at: exp, activation_limit: plan ? plan.sites_limit : 1,
			updates_until: plan ? new Date(Date.now() + plan.updates_months * 30 * 864e5).toISOString().slice(0, 10) : '',
		});
		audit(admin, 'license.created', lic.preview, `customer ${customer.email}`, ip(req));
		return redirect(res, `/admin/licenses/${lic.id}?newkey=` + encodeURIComponent(lic.key));
	}
	if (url.pathname === '/admin/licenses/create' && req.method === 'GET') {
		const products = db.prepare('SELECT * FROM products').all();
		const plans = db.prepare("SELECT * FROM plans WHERE status='ACTIVE'").all();
		return page(req, admin, res, 200, 'Create license', `
<div class="card"><h2>Create license manually</h2><form method="post" action="/admin/licenses/create">${cf(admin)}
<div class="grid"><div><label>Customer name *</label><input name="customer_name" required></div><div><label>Customer email *</label><input name="customer_email" type="email" required></div>
<div><label>Product *</label><select name="product_id">${products.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></div>
<div><label>Plan</label><select name="plan_id"><option value="">Custom</option>${plans.map((pl) => `<option value="${pl.id}">${esc(pl.name)} ($${pl.price} · ${pl.sites_limit} site(s))</option>`).join('')}</select></div>
<div><label>Days (if custom)</label><input name="days" type="number" value="365"></div></div>
<p><button class="btn primary">Generate license</button></p></form></div>`, '/admin/licenses');
	}

	// List.
	const search = q(req, 'q');
	const status = q(req, 'status');
	const paged = Math.max(1, Number(q(req, 'paged') || 1));
	const per = 25;
	let where = '1=1';
	const params = [];
	if (search) { where += ' AND (l.key_preview LIKE ? OR c.email LIKE ? OR c.name LIKE ?)'; const like = `%${search}%`; params.push(like, like, like); }
	if (status) { where += ' AND l.status = ?'; params.push(status); }
	const total = db.prepare(`SELECT COUNT(*) c FROM licenses l JOIN customers c ON c.id=l.customer_id WHERE ${where}`).get(...params).c;
	const rows = db.prepare(`SELECT l.*, c.name customer, c.email email, p.name product FROM licenses l JOIN customers c ON c.id=l.customer_id JOIN products p ON p.id=l.product_id WHERE ${where} ORDER BY l.id DESC LIMIT ? OFFSET ?`)
		.all(...params, per, (paged - 1) * per)
		.map((l) => `<tr><td class="mono"><a href="/admin/licenses/${l.id}">${esc(l.key_preview)}</a></td><td>${esc(l.customer)}<div class="muted" style="font-size:11px">${esc(l.email)}</div></td>
		<td>${esc(l.product)}</td><td>${statusBadge(l.status)}</td><td>${fmtDate(l.expires_at)}</td><td><a class="btn sm" href="/admin/licenses/${l.id}">View</a></td></tr>`);

	page(req, admin, res, 200, 'Licenses', `
<div class="toolbar">
<form method="get" class="toolbar" style="margin:0"><input name="q" placeholder="Key, email, name…" value="${esc(search)}"><button class="btn sm">Filter</button>
<select name="status" onchange="this.form.submit()"><option value="">All statuses</option>${['ACTIVE','EXPIRED','SUSPENDED','REVOKED','PENDING'].map((s) => `<option ${status === s ? 'selected' : ''}>${s}</option>`).join('')}</select></form>
<a class="btn primary" href="/admin/licenses/create">＋ Create license</a>
<a class="btn" href="/admin/licenses/export?format=csv&q=${encodeURIComponent(search)}">Export CSV</a>
</div>` + table(['Key', 'Customer', 'Product', 'Status', 'Expires', ''], rows) + paginate(total, per, paged, `/admin/licenses?q=${encodeURIComponent(search)}&status=${status}`), '/admin/licenses');
}

function licenseDetail(req, res, admin, id, body) {
	const lic = db.prepare(`SELECT l.*, c.name customer, c.email email, p.name product, pl.name plan FROM licenses l JOIN customers c ON c.id=l.customer_id JOIN products p ON p.id=l.product_id LEFT JOIN plans pl ON pl.id=l.plan_id WHERE l.id=?`).get(id);
	if (!lic) return redirect(res, '/admin/licenses');
	const used = db.prepare("SELECT COUNT(*) c FROM installations WHERE license_id=? AND status != 'INACTIVE'").get(id).c;
	const installs = db.prepare('SELECT * FROM installations WHERE license_id=? ORDER BY last_seen DESC').all(id);
	const history = db.prepare('SELECT * FROM activations WHERE license_id=? ORDER BY id DESC LIMIT 30').all(id);
	const newKey = q(req, 'newkey');

	if (req.method === 'POST' && body.do === 'reveal') {
		audit(admin, 'license.key_revealed', lic.key_preview, '', ip(req));
	}

	const revealKey = body.do === 'reveal' ? revealLicenseKey(id) : '';
	const actions = `
<div class="card"><h2>Actions</h2><form method="post">${cf(admin)}<input type="hidden" name="id" value="${id}">
<div class="toolbar">
<button name="do" value="suspend" class="btn sm danger" onclick="return confirm('Suspend this license?')">Suspend</button>
<button name="do" value="resume" class="btn sm">Resume</button>
<button name="do" value="revoke" class="btn sm danger" onclick="return confirm('REVOKE this license? This is serious.')">Revoke</button>
</div>
<div class="grid"><div><label>Extend (days)</label><div class="toolbar" style="margin:0"><input name="days" type="number" value="365"><button name="do" value="extend" class="btn sm">Extend</button></div></div>
<div><label>Activation limit</label><div class="toolbar" style="margin:0"><input name="activation_limit" type="number" value="${lic.activation_limit}"><button name="do" value="limit" class="btn sm">Save</button></div></div></div>
<p><button name="do" value="regenerate" class="btn danger" onclick="return confirm('Regenerate the key? The old key stops working immediately.')">Regenerate key</button>
<button name="do" value="reveal" class="btn">Reveal full key (audited)</button></p></form></div>`;

	page(req, admin, res, 200, `License ${lic.key_preview}`, `
${newKey ? `<div class="notice ok">New key (shown once): <span class="mono" style="font-size:16px">${esc(newKey)}</span></div>` : ''}
${revealKey ? `<div class="notice ok">Full key: <span class="mono" style="font-size:16px">${esc(revealKey)}</span></div>` : ''}
<div class="grid">
<div class="card"><h2>🔑 ${esc(lic.key_preview)}</h2>
<p>Status: ${statusBadge(lic.status)} · Plan: ${esc(lic.plan || 'Custom')}</p>
<p>Customer: <a href="/admin/customers/${lic.customer_id}"><strong>${esc(lic.customer)}</strong></a> (${esc(lic.email)})</p>
<p>Product: ${esc(lic.product)}</p>
<p>Created: ${fmtDate(lic.created_at)} · Expires: <strong>${lic.expires_at ? fmtDate(lic.expires_at) : 'Never'}</strong></p>
<p>Activations: <strong>${used} / ${lic.activation_limit}</strong> · Updates until: <strong>${lic.updates_until ? fmtDate(lic.updates_until) : '—'}</strong></p>
${lic.allowed_versions ? `<p>Allowed versions: <span class="mono">${esc(lic.allowed_versions)}</span></p>` : ''}
</div>
${actions}
</div>
<div class="card"><h2>Installations (${installs.length})</h2>${table(['Installation', 'Domain', 'Env', 'Version', 'Status', 'Last seen', ''], installs.map((i) => `<tr>
<td class="mono"><a href="/admin/installations/${esc(i.installation_id)}">${esc(i.installation_id.slice(0, 12))}…</a></td><td>${esc(i.domain)}</td><td>${i.environment}</td><td>${esc(i.plugin_version)}</td>
<td>${statusBadge(i.status)}</td><td>${fmtDate(i.last_seen)}</td><td><a class="btn sm" href="/admin/installations/${esc(i.installation_id)}">View</a></td></tr>`))}</div>
<div class="card"><h2>History</h2>${table(['Event', 'Detail', 'Date', 'IP'], history.map((h) => `<tr><td><strong>${esc(h.event)}</strong></td><td>${esc(h.detail)}</td><td>${fmtDate(h.created_at)}</td><td class="mono">${esc(h.ip)}</td></tr>`))}</div>`, '/admin/licenses');
}

/* ---------- Installations ---------- */
export function adminInstallations(req, res, admin, url, body) {
	const m = url.pathname.match(/^\/admin\/installations\/([^/]+)$/);
	if (m) {
		const install = db.prepare('SELECT * FROM installations WHERE installation_id = ?').get(decodeURIComponent(m[1]));
		if (!install) return redirect(res, '/admin/installations');
		const lic = db.prepare('SELECT * FROM licenses WHERE id = ?').get(install.license_id);
		if (req.method === 'POST') {
			const map = { block: 'BLOCKED', unblock: 'ACTIVE', disable: 'INACTIVE', reactivate: 'ACTIVE' };
			if (map[body.do]) {
				db.prepare('UPDATE installations SET status = ? WHERE id = ?').run(map[body.do], install.id);
				audit(admin, 'installation.' + body.do, install.domain, '', ip(req));
			} else if (body.do === 'delete') {
				db.prepare('DELETE FROM installations WHERE id = ?').run(install.id);
				audit(admin, 'installation.deleted', install.domain, '', ip(req));
				return redirect(res, '/admin/installations');
			}
			return redirect(res, `/admin/installations/${encodeURIComponent(install.installation_id)}`);
		}
		const timeline = db.prepare('SELECT * FROM activations WHERE installation_id = ? ORDER BY id DESC LIMIT 30').all(install.installation_id);
		return page(req, admin, res, 200, `Installation ${install.domain}`, `
<div class="grid"><div class="card"><h2>🖥️ ${esc(install.domain)}</h2>
<p>Status: ${statusBadge(install.status)} · Environment: ${install.environment} · Risk: ${statusBadge(install.risk)}</p>
<p>Product: ${esc((db.prepare('SELECT name FROM products WHERE id=?').get((db.prepare('SELECT product_id FROM licenses WHERE id=?').get(install.license_id) || {}).product_id) || {}).name || '—')}</p>
<p>License: <a href="/admin/licenses/${install.license_id}" class="mono">${esc((lic || {}).key_preview || '')}</a></p>
<p>Plugin ${esc(install.plugin_version)} · WP ${esc(install.wp_version)} · PHP ${esc(install.php_version)}</p>
<p>First seen ${fmtDate(install.first_seen)} · Last seen ${fmtDate(install.last_seen)} · IP <span class="mono">${esc(install.ip)}</span></p>
<p>Installation ID: <span class="mono">${esc(install.installation_id)}</span></p></div>
<div class="card"><h2>Remote control</h2><form method="post">${cf(admin)}
<div class="toolbar">
<button name="do" value="block" class="btn sm danger" onclick="return confirm('Block this installation?')">Block</button>
<button name="do" value="unblock" class="btn sm">Unblock</button>
<button name="do" value="disable" class="btn sm">Disable</button>
<button name="do" value="reactivate" class="btn sm">Reactivate</button>
<button name="do" value="delete" class="btn sm danger" onclick="return confirm('Delete installation record?')">Delete</button>
</div></form></div></div>
<div class="card"><h2>Timeline</h2>${table(['Event', 'Detail', 'Date', 'IP'], timeline.map((t) => `<tr><td><strong>${esc(t.event)}</strong></td><td>${esc(t.detail)}</td><td>${fmtDate(t.created_at)}</td><td class="mono">${esc(t.ip)}</td></tr>`))}</div>`, '/admin/installations');
	}

	const search = q(req, 'q');
	const filter = q(req, 'f');
	let where = '1=1';
	const params = [];
	if (search) { where += ' AND (i.domain LIKE ? OR i.installation_id LIKE ?)'; const like = `%${search}%`; params.push(like, like); }
	if (filter === 'blocked') where += " AND i.status='BLOCKED'";
	if (filter === 'inactive') where += " AND i.status='INACTIVE'";
	if (filter === 'staging') where += " AND i.environment != 'PRODUCTION'";
	const rows = db.prepare(`SELECT i.*, l.key_preview FROM installations i JOIN licenses l ON l.id=i.license_id WHERE ${where} ORDER BY i.last_seen DESC LIMIT 100`).all(...params)
		.map((i) => `<tr><td class="mono"><a href="/admin/installations/${encodeURIComponent(i.installation_id)}">${esc(i.installation_id.slice(0, 12))}…</a></td>
		<td><strong>${esc(i.domain)}</strong></td><td class="mono">${esc(i.key_preview)}</td><td>${esc(i.plugin_version)}</td><td>${i.environment}</td>
		<td>${statusBadge(i.status)}</td><td>${fmtDate(i.last_seen)}</td></tr>`);
	page(req, admin, res, 200, 'Installations', `
<div class="toolbar"><form class="toolbar" method="get" style="margin:0"><input name="q" placeholder="Domain, installation id…" value="${esc(search)}"><button class="btn sm">Search</button>
<select name="f" onchange="this.form.submit()"><option value="">All</option><option ${filter === 'blocked' ? 'selected' : ''} value="blocked">Blocked</option><option ${filter === 'inactive' ? 'selected' : ''} value="inactive">Inactive</option><option ${filter === 'staging' ? 'selected' : ''} value="staging">Staging/Local</option></select></form></div>
` + table(['Installation', 'Domain', 'License', 'Version', 'Env', 'Status', 'Last seen'], rows), '/admin/installations');
}

/* ---------- Orders ---------- */
export function adminOrders(req, res, admin, url, body) {
	if (url.pathname === '/admin/orders/create' && req.method === 'POST') {
		const customer = ensureCustomer(body.customer_email, body.customer_name, body.country, body.company);
		const ref = 'ORD-' + uid(4).toUpperCase();
		db.prepare(`INSERT INTO orders(reference,customer_id,product_id,plan_id,amount,currency,status,payment_method,payment_ref,note,created_at,paid_at)
			VALUES(?,?,?,?,?,?,?,?,?,?,?,?)`)
			.run(ref, customer.id, Number(body.product_id), Number(body.plan_id) || null, Number(body.amount) || 0, body.currency || 'USD',
				body.status === 'PAID' ? 'PAID' : 'PENDING', body.payment_method || 'manual', body.payment_ref || '', body.note || '', now(), body.status === 'PAID' ? now() : '');
		audit(admin, 'order.created', ref, `${body.amount} ${body.currency}`, ip(req));
		return redirect(res, '/admin/orders');
	}
	if (req.method === 'POST' && body.do === 'mark_paid') {
		const order = db.prepare('SELECT * FROM orders WHERE id = ?').get(Number(body.id));
		if (order && order.status !== 'PAID') {
			db.prepare("UPDATE orders SET status='PAID', paid_at=? WHERE id=?").run(now(), order.id);
			audit(admin, 'order.paid', order.reference, '', ip(req));
		}
		return redirect(res, '/admin/orders');
	}
	if (req.method === 'POST' && body.do === 'generate_license') {
		const order = db.prepare('SELECT * FROM orders WHERE id = ?').get(Number(body.id));
		if (order && order.status === 'PAID' && !order.license_id) {
			const plan = order.plan_id ? db.prepare('SELECT * FROM plans WHERE id=?').get(order.plan_id) : null;
			const days = plan ? plan.duration_days : 365;
			const lic = createLicense({
				customer_id: order.customer_id, product_id: order.product_id, plan_id: order.plan_id,
				expires_at: days > 0 ? new Date(Date.now() + days * 864e5).toISOString().replace('T', ' ').slice(0, 19) : '',
				activation_limit: plan ? plan.sites_limit : 1,
				updates_until: plan ? new Date(Date.now() + plan.updates_months * 30 * 864e5).toISOString().slice(0, 10) : '',
			});
			db.prepare('UPDATE orders SET license_id = ? WHERE id = ?').run(lic.id, order.id);
			const customer = db.prepare('SELECT * FROM customers WHERE id = ?').get(order.customer_id);
			const product = db.prepare('SELECT * FROM products WHERE id = ?').get(order.product_id);
			import('./core.js').then(({ queueEmail }) => {
				queueEmail(customer.email, 'license_created', {
					customer_name: customer.name, product_name: product.name, license_key: lic.key,
					expiration_date: plan && plan.duration_days === 0 ? 'Never' : fmtDate(new Date(Date.now() + days * 864e5).toISOString().slice(0, 19)),
					activation_limit: plan ? plan.sites_limit : 1, download_url: config.APP_URL + '/products/' + product.slug,
				});
			});
			audit(admin, 'license.generated_from_order', order.reference, lic.preview, ip(req));
		}
		return redirect(res, '/admin/orders');
	}

	const createForm = `<div class="card"><h2>Create manual order</h2><form method="post" action="/admin/orders/create">${cf(admin)}
<div class="grid"><div><label>Customer name *</label><input name="customer_name" required></div><div><label>Customer email *</label><input name="customer_email" type="email" required></div>
<div><label>Product *</label><select name="product_id">${db.prepare('SELECT * FROM products').all().map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></div>
<div><label>Plan</label><select name="plan_id"><option value="">Custom</option>${db.prepare("SELECT * FROM plans WHERE status='ACTIVE'").all().map((pl) => `<option value="${pl.id}">${esc(pl.name)} ($${pl.price})</option>`).join('')}</select></div>
<div><label>Amount *</label><input name="amount" type="number" step="0.01" required value="39"></div><div><label>Currency</label><select name="currency"><option>USD</option><option>EUR</option></select></div>
<div><label>Payment status</label><select name="status"><option>PAID</option><option>PENDING</option></select></div>
<div><label>Payment ref</label><input name="payment_ref" placeholder="PayPal TXN…"></div></div>
<p><button class="btn primary">Create order</button></p></form></div>`;

	const rows = db.prepare(`SELECT o.*, c.name customer, c.email email, p.name product, pl.name plan, l.key_preview FROM orders o
		JOIN customers c ON c.id=o.customer_id JOIN products p ON p.id=o.product_id LEFT JOIN plans pl ON pl.id=o.plan_id
		LEFT JOIN licenses l ON l.id=o.license_id ORDER BY o.id DESC LIMIT 100`).all()
		.map((o) => `<tr><td class="mono">${esc(o.reference)}</td><td>${esc(o.customer)}<div class="muted" style="font-size:11px">${esc(o.email)}</div></td>
		<td>${esc(o.product)}</td><td>${esc(o.plan || '—')}</td><td><strong>$${o.amount}</strong> ${esc(o.currency)}</td><td>${statusBadge(o.status)}</td>
		<td>${o.license_id ? `<a class="mono" href="/admin/licenses/${o.license_id}">${esc(o.key_preview)}</a>` : `
		<form method="post" style="display:inline">${cf(admin)}<input type="hidden" name="id" value="${o.id}">
		${o.status === 'PAID' ? `<button name="do" value="generate_license" class="btn sm primary">Generate license</button>` : `<button name="do" value="mark_paid" class="btn sm">Mark paid</button>`}</form>`}</td></tr>`);
	page(req, admin, res, 200, 'Orders', createForm + table(['Ref', 'Customer', 'Product', 'Plan', 'Amount', 'Status', 'License'], rows), '/admin/orders');
}

/* ---------- Customers ---------- */
export function adminCustomers(req, res, admin, url, body) {
	const m = url.pathname.match(/^\/admin\/customers\/(\d+)$/);
	if (m) {
		const c = db.prepare('SELECT * FROM customers WHERE id = ?').get(Number(m[1]));
		if (!c) return redirect(res, '/admin/customers');
		const licenses = db.prepare('SELECT * FROM licenses WHERE customer_id = ? ORDER BY id DESC').all(c.id);
		const orders = db.prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC').all(c.id);
		if (req.method === 'POST' && body.do === 'update') {
			db.prepare('UPDATE customers SET name=?, email=?, phone=?, company=?, country=? WHERE id=?')
				.run(body.name, String(body.email).toLowerCase(), body.phone || '', body.company || '', body.country || '', c.id);
			audit(admin, 'customer.edited', c.email, '', ip(req));
			return redirect(res, `/admin/customers/${c.id}`);
		}
		page(req, admin, res, 200, `Customer ${c.name}`, `
<div class="card"><form method="post">${cf(admin)}<input type="hidden" name="do" value="update">
<div class="grid"><div><label>Name</label><input name="name" value="${esc(c.name)}"></div><div><label>Email</label><input name="email" type="email" value="${esc(c.email)}"></div>
<div><label>Phone</label><input name="phone" value="${esc(c.phone)}"></div><div><label>Company</label><input name="company" value="${esc(c.company)}"></div>
<div><label>Country</label><input name="country" value="${esc(c.country)}"></div></div>
<p><button class="btn primary">Save customer</button></p></form>
<p class="muted">Created ${fmtDate(c.created_at)} · Last activity ${fmtDate(c.last_activity)}</p></div>
<div class="card"><h2>Licenses (${licenses.length})</h2>${table(['Key', 'Status', 'Expires', ''], licenses.map((l) => `<tr><td class="mono"><a href="/admin/licenses/${l.id}">${esc(l.key_preview)}</a></td><td>${statusBadge(l.status)}</td><td>${fmtDate(l.expires_at)}</td><td><a class="btn sm" href="/admin/licenses/${l.id}">View</a></td></tr>`))}</div>
<div class="card"><h2>Orders (${orders.length})</h2>${table(['Ref', 'Amount', 'Status', 'Date'], orders.map((o) => `<tr><td class="mono">${esc(o.reference)}</td><td>$${o.amount}</td><td>${statusBadge(o.status)}</td><td>${fmtDate(o.created_at)}</td></tr>`))}</div>`, '/admin/customers');
		return;
	}

	const rows = db.prepare(`SELECT c.*, (SELECT COUNT(*) FROM licenses l WHERE l.customer_id=c.id) nl FROM customers c ORDER BY c.id DESC LIMIT 200`).all()
		.map((c) => `<tr><td><strong>${esc(c.name)}</strong></td><td>${esc(c.email)}</td><td>${esc(c.country)}</td><td>${c.nl}</td><td>${statusBadge(c.status)}</td><td>${fmtDate(c.created_at)}</td>
		<td><a class="btn sm" href="/admin/customers/${c.id}">View</a></td></tr>`);
	page(req, admin, res, 200, 'Customers', table(['Name', 'Email', 'Country', 'Licenses', 'Status', 'Created', ''], rows), '/admin/customers');
}

/* ---------- Activations ---------- */
export function adminActivations(req, res, admin) {
	const rows = db.prepare(`SELECT a.*, l.key_preview FROM activations a JOIN licenses l ON l.id=a.license_id ORDER BY a.id DESC LIMIT 200`).all()
		.map((a) => `<tr><td><strong>${esc(a.event)}</strong></td><td class="mono">${esc(a.key_preview)}</td><td>${esc(a.detail)}</td><td>${fmtDate(a.created_at)}</td><td class="mono">${esc(a.ip)}</td></tr>`);
	page(req, admin, res, 200, 'Activations', table(['Event', 'License', 'Detail', 'Date', 'IP'], rows), '/admin/activations');
}

/* ---------- Releases ---------- */
export function adminReleases(req, res, admin, url, body) {
	if (req.method === 'POST' && body.do === 'create') {
		const zip = body.zip_b64 || '';
		let filePath = '';
		if (zip) {
			const buf = Buffer.from(zip, 'base64');
			if (buf.length > 50 * 1024 * 1024) { return redirect(res, '/admin/releases'); }
			const safe = `${String(body.product_id)}-${String(body.version).replace(/[^0-9a-zA-Z.\-]/g, '')}.zip`;
			fs.writeFileSync(path.join(config.STORAGE_PATH, 'releases', safe), buf);
			filePath = safe;
		}
		db.prepare(`INSERT INTO releases(product_id,version,channel,file_path,changelog,wp_min,php_min,status,created_at)
			VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(product_id,version,channel) DO NOTHING`)
			.run(Number(body.product_id), String(body.version), body.channel || 'stable', filePath, body.changelog || '', body.wp_min || '6.0', body.php_min || '7.4', 'PUBLISHED', now());
		audit(admin, 'release.published', body.version, '', ip(req));
		return redirect(res, '/admin/releases');
	}
	const rows = db.prepare(`SELECT r.*, p.name product FROM releases r JOIN products p ON p.id=r.product_id ORDER BY r.id DESC LIMIT 100`).all()
		.map((r) => `<tr><td><strong>${esc(r.product)}</strong></td><td class="mono">v${esc(r.version)}</td><td><span class="badge">${r.channel}</span></td><td>${statusBadge(r.status)}</td>
		<td>${r.file_path ? '✅ ' + Math.round((fs.statSync(path.join(config.STORAGE_PATH, 'releases', r.file_path)).size || 0) / 1024) + ' Ko' : '<span class="muted">no file</span>'}</td>
		<td>${r.downloads}</td><td>${fmtDate(r.created_at)}</td></tr>`);
	const options = db.prepare('SELECT * FROM products').all().map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
	page(req, admin, res, 200, 'Releases', `
<div class="card"><h2>Publish release</h2><form method="post" enctype="multipart/form-data">${cf(admin)}<input type="hidden" name="do" value="create">
<div class="grid"><div><label>Product *</label><select name="product_id">${options}</select></div>
<div><label>Version *</label><input name="version" required placeholder="1.0.1"></div>
<div><label>Channel</label><select name="channel"><option>stable</option><option>beta</option><option>dev</option></select></div>
<div><label>ZIP file</label><input type="file" name="zip_file" accept=".zip"></div>
<div><label>WP min</label><input name="wp_min" value="6.0"></div><div><label>PHP min</label><input name="php_min" value="7.4"></div></div>
<label>Changelog</label><textarea name="changelog" rows="3" style="width:100%"></textarea>
<p><button class="btn primary">Publish release</button></p></form></div>
` + table(['Product', 'Version', 'Channel', 'Status', 'Package', 'Downloads', 'Date'], rows), '/admin/releases');
}

/* ---------- Security, logs, emails, settings, admins, search ---------- */
export function adminSecurity(req, res, admin) {
	const events = db.prepare('SELECT * FROM security_events ORDER BY id DESC LIMIT 200').all();
	page(req, admin, res, 200, 'Security Events', table(['Severity', 'Type', 'Detail', 'License', 'Date', 'IP'], events.map((e) => `<tr>
	<td>${statusBadge(e.severity)}</td><td><strong>${esc(e.type)}</strong></td><td>${esc(e.detail)}</td>
	<td>${e.license_id ? `<a class="mono" href="/admin/licenses/${e.license_id}">#${e.license_id}</a>` : '—'}</td><td>${fmtDate(e.ts)}</td><td class="mono">${esc(e.ip)}</td></tr>`)), '/admin/security');
}
export function adminApiLogs(req, res, admin) {
	const rows = db.prepare('SELECT * FROM api_logs ORDER BY id DESC LIMIT 300').all();
	page(req, admin, res, 200, 'API Logs', table(['Date', 'Route', 'Status', 'License', 'Detail', 'IP'], rows.map((r) => `<tr><td>${fmtDate(r.ts)}</td><td class="mono">${esc(r.route)}</td><td>${r.status}</td><td>${r.license_id || '—'}</td><td>${esc(r.detail)}</td><td class="mono">${esc(r.ip)}</td></tr>`)), '/admin/api-logs');
}
export function adminAuditLogs(req, res, admin) {
	const rows = db.prepare('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 300').all();
	page(req, admin, res, 200, 'Audit Logs', table(['Date', 'Admin', 'Action', 'Target', 'Detail', 'IP'], rows.map((r) => `<tr><td>${fmtDate(r.ts)}</td><td>${esc(r.admin_email)}</td><td><strong>${esc(r.action)}</strong></td><td>${esc(r.target)}</td><td>${esc(r.detail)}</td><td class="mono">${esc(r.ip)}</td></tr>`)), '/admin/audit-logs');
}
export function adminEmails(req, res, admin, url, body) {
	if (req.method === 'POST' && body.do === 'save') {
		db.prepare('UPDATE email_templates SET subject=?, body=? WHERE id=?').run(body.subject, body.body, Number(body.id));
		audit(admin, 'email_template.edited', body.slug || body.id, '', ip(req));
		return redirect(res, '/admin/emails');
	}
	const editId = Number(url.searchParams.get('edit') || 0);
	const editing = editId ? db.prepare('SELECT * FROM email_templates WHERE id=?').get(editId) : null;
	const rows = db.prepare('SELECT * FROM email_templates').all().map((t) => `<tr><td class="mono">${esc(t.slug)}</td><td>${esc(t.subject)}</td><td><a class="btn sm" href="/admin/emails?edit=${t.id}">Edit</a></td></tr>`);
	const editor = editing ? `<div class="card"><h2>Edit template — ${esc(editing.slug)}</h2>
	<form method="post">${cf(admin)}<input type="hidden" name="do" value="save"><input type="hidden" name="id" value="${editing.id}"><input type="hidden" name="slug" value="${esc(editing.slug)}">
	<label>Subject (variables: {{customer_name}}, {{license_key}}, {{product_name}}, {{expiration_date}}…)</label><input name="subject" style="width:100%" value="${esc(editing.subject)}">
	<label>Body</label><textarea name="body" rows="9" style="width:100%">${esc(editing.body)}</textarea>
	<p><button class="btn primary">Save template</button> <a class="btn" href="/admin/emails">Cancel</a></p></form></div>` : '';
	page(req, admin, res, 200, 'Email Templates', editor + table(['Slug', 'Subject', ''], rows), '/admin/emails');
}
export function adminEmailLogs(req, res, admin) {
	const rows = db.prepare('SELECT * FROM email_logs ORDER BY id DESC LIMIT 200').all();
	page(req, admin, res, 200, 'Email Logs', table(['Date', 'To', 'Template', 'Subject', 'Status'], rows.map((r) => `<tr><td>${fmtDate(r.ts)}</td><td>${esc(r.to_email)}</td><td class="mono">${esc(r.template)}</td><td>${esc(r.subject)}</td><td>${statusBadge(r.status === 'LOGGED' ? 'INACTIVE' : 'ACTIVE')}</td></tr>`)), '/admin/email-logs');
}
export function adminSettings(req, res, admin, url, body) {
	const { allSettings, setSetting } = requireSettings();
	if (req.method === 'POST') {
		for (const key of ['staging_counts', 'grace_hours', 'site_name', 'support_email', 'terms_url', 'privacy_url']) {
			if (key in body) setSetting(key, body[key]);
		}
		audit(admin, 'settings.changed', '', JSON.stringify(Object.keys(body).filter((k) => k !== 'csrf' && k !== 'do')), ip(req));
		return redirect(res, '/admin/settings');
	}
	const s = allSettings();
	page(req, admin, res, 200, 'Settings', `
<div class="card"><h2>General</h2><form method="post">${cf(admin)}
<div class="grid"><div><label>Site name</label><input name="site_name" value="${esc(s.site_name || 'Infinity License')}"></div>
<div><label>Support email</label><input name="support_email" value="${esc(s.support_email || '')}"></div>
<div><label>Grace period (hours, offline tolerance)</label><input name="grace_hours" type="number" value="${esc(s.grace_hours || '72')}"></div></div>
<label><input type="checkbox" name="staging_counts" value="1" ${s.staging_counts === '1' ? 'checked' : ''}> Staging/local environments count toward activation limits</label>
<label>Terms URL</label><input name="terms_url" style="width:100%" value="${esc(s.terms_url || '')}">
<label>Privacy URL</label><input name="privacy_url" style="width:100%" value="${esc(s.privacy_url || '')}">
<p><button class="btn primary">Save settings</button></p></form>
<p class="muted">API base: <span class="mono">${config.APP_URL}/api/v1</span> · Public key: <a href="/api/v1/public-key">/api/v1/public-key</a> · Health: <a href="/health">/health</a></p></div>`, '/admin/settings');
}
export function adminAdmins(req, res, admin, url, body) {
	if (req.method === 'POST' && body.do === 'create' && admin.role === 'super_admin') {
		const { hashPassword } = requireCore();
		db.prepare('INSERT INTO admins(email,name,password_hash,role,created_at) VALUES(?,?,?,?,?)')
			.run(String(body.email).toLowerCase(), body.name || body.email, hashPassword(body.password), body.role || 'support', now());
		audit(admin, 'admin.created', body.email, body.role, ip(req));
		return redirect(res, '/admin/admins');
	}
	if (req.method === 'POST' && body.do === 'toggle' && admin.role === 'super_admin') {
		db.prepare("UPDATE admins SET status = CASE WHEN status='ACTIVE' THEN 'SUSPENDED' ELSE 'ACTIVE' END WHERE id = ? AND id != ?").run(Number(body.id), admin.id);
		audit(admin, 'admin.toggled', body.id, '', ip(req));
		return redirect(res, '/admin/admins');
	}
	const rows = db.prepare('SELECT * FROM admins ORDER BY id').all().map((a) => `<tr><td>${esc(a.name)}</td><td>${esc(a.email)}</td><td><span class="badge">${a.role}</span></td><td>${statusBadge(a.status)}</td><td>${fmtDate(a.last_login)}</td>
	<td>${a.id !== admin.id && admin.role === 'super_admin' ? `<form method="post" style="display:inline">${cf(admin)}<input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="${a.id}"><button class="btn sm">Toggle</button></form>` : ''}</td></tr>`);
	const form = admin.role === 'super_admin' ? `<div class="card"><h2>Add administrator</h2><form method="post">${cf(admin)}<input type="hidden" name="do" value="create">
	<div class="grid"><div><label>Name</label><input name="name" required></div><div><label>Email</label><input name="email" type="email" required></div>
	<div><label>Password</label><input name="password" type="password" required></div><div><label>Role</label><select name="role"><option>support</option><option>viewer</option><option>admin</option></select></div></div>
	<p><button class="btn primary">Add admin</button></p></form></div>` : '';
	page(req, admin, res, 200, 'Administrators', form + table(['Name', 'Email', 'Role', 'Status', 'Last login', ''], rows), '/admin/admins');
}
export function adminSearch(req, res, admin, url) {
	const q = (url.searchParams.get('q') || '').trim();
	const like = `%${q}%`;
	const licenses = db.prepare(`SELECT l.id, l.key_preview, l.status FROM licenses l JOIN customers c ON c.id=l.customer_id WHERE l.key_preview LIKE ? OR c.email LIKE ? OR c.name LIKE ? LIMIT 10`).all(like, like, like);
	const domains = db.prepare(`SELECT installation_id, domain, license_id FROM installations WHERE domain LIKE ? OR installation_id LIKE ? LIMIT 10`).all(like, like);
	const orders = db.prepare(`SELECT id, reference, status FROM orders WHERE reference LIKE ? LIMIT 10`).all(like);
	const customers = db.prepare(`SELECT id, name, email FROM customers WHERE name LIKE ? OR email LIKE ? LIMIT 10`).all(like, like);
	page(req, admin, res, 200, `Search: ${q}`, `
<div class="card"><h2>Licenses</h2>${table(['Key', 'Status', ''], licenses.map((l) => `<tr><td class="mono"><a href="/admin/licenses/${l.id}">${esc(l.key_preview)}</a></td><td>${statusBadge(l.status)}</td><td><a class="btn sm" href="/admin/licenses/${l.id}">View</a></td></tr>`))}</div>
<div class="card"><h2>Installations / Domains</h2>${table(['Domain', 'Installation', ''], domains.map((d) => `<tr><td><strong>${esc(d.domain)}</strong></td><td class="mono">${esc(d.installation_id.slice(0, 14))}…</td><td><a class="btn sm" href="/admin/installations/${encodeURIComponent(d.installation_id)}">View</a></td></tr>`))}</div>
<div class="card"><h2>Orders</h2>${table(['Ref', 'Status', ''], orders.map((o) => `<tr><td class="mono">${esc(o.reference)}</td><td>${statusBadge(o.status)}</td><td></td></tr>`))}</div>
<div class="card"><h2>Customers</h2>${table(['Name', 'Email', ''], customers.map((c) => `<tr><td>${esc(c.name)}</td><td>${esc(c.email)}</td><td><a class="btn sm" href="/admin/customers/${c.id}">View</a></td></tr>`))}</div>`, '/admin');
}
export function adminExport(req, res, admin, url) {
	const what = url.pathname.split('/').pop();
	const map = {
		licenses: 'SELECT key_preview, status, expires_at, activation_limit, created_at FROM licenses',
		customers: 'SELECT name, email, phone, country, created_at FROM customers',
		orders: 'SELECT reference, amount, currency, status, payment_method, created_at FROM orders',
	};
	if (!map[what]) return json(res, 404, { error: 'unknown export' });
	const rows = db.prepare(map[what]).all();
	const header = rows.length ? Object.keys(rows[0]) : [];
	const csv = [header.join(','), ...rows.map((r) => header.map((h) => `"${String(r[h] ?? '').replace(/"/g, '""')}"`).join(','))].join('\n');
	res.writeHead(200, { 'Content-Type': 'text/csv; charset=utf-8', 'Content-Disposition': `attachment; filename="${what}.csv"` });
	res.end(csv);
}

/* ---------- Utils ---------- */
function redirect(res, location) { res.writeHead(302, { Location: location }); res.end(); }
function ip(req) { return req.socket.remoteAddress || ''; }
function ensureCustomer(email, name, country = '', company = '') {
	email = String(email || '').toLowerCase().trim();
	let c = db.prepare('SELECT * FROM customers WHERE email = ?').get(email);
	if (!c) {
		const fullName = String(name || email).trim();
		db.prepare('INSERT INTO customers(name,email,country,company,created_at,last_activity) VALUES(?,?,?,?,?,?)')
			.run(fullName, email, country || '', company || '', now(), now());
		c = db.prepare('SELECT * FROM customers WHERE email = ?').get(email);
	}
	return c;
}
import { allSettings as _allSettings, setSetting as _setSetting } from './db.js';
function requireSettings() { return { allSettings: _allSettings, setSetting: _setSetting }; }
import * as coreMod from './core.js';
function requireCore() { return coreMod; }
