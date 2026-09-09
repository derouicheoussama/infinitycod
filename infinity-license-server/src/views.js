import zlib from 'node:zlib';
import { db } from './db.js';
import { esc, fmtDate } from './core.js';

export function secureHeaders(res) {
	res.setHeader('X-Frame-Options', 'DENY');
	res.setHeader('X-Content-Type-Options', 'nosniff');
	res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
	res.setHeader("Permissions-Policy", 'camera=(), microphone=(), geolocation=()');
	res.setHeader('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.paypal.com");
}

export function secureSend(req, res, code, html) {
	secureHeaders(res);
	const accept = String(req.headers['accept-encoding'] || '');
	if (html.length > 600 && accept.includes('gzip')) {
		res.writeHead(code, { 'Content-Type': 'text/html; charset=utf-8', 'Content-Encoding': 'gzip' });
		return res.end(zlib.gzipSync(Buffer.from(html, 'utf8')));
	}
	res.writeHead(code, { 'Content-Type': 'text/html; charset=utf-8' });
	res.end(html);
}

/* ---------- Design system Ocean Blue (inline, zéro dépendance) ---------- */
export const CSS = `
:root{--navy:#0b2239;--blue:#1877c2;--blue2:#4da3e8;--bg:#eef3f9;--card:#ffffff;--ink:#16283c;--muted:#5f7488;--line:#dbe5ef;--ok:#0e7a4f;--warn:#996800;--bad:#b32d2e;--radius:12px}
[data-theme="dark"]{--bg:#0d1b2a;--card:#12263a;--ink:#e8eef5;--muted:#93a7bc;--line:#22374e}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font-family:system-ui,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--ink);font-size:14.5px}
a{color:var(--blue);text-decoration:none}
a:hover{text-decoration:underline}
h1,h2,h3{color:var(--ink)}
.badge{display:inline-block;padding:2px 10px;border-radius:99px;font-size:11.5px;font-weight:700}
.b-ACTIVE,.b-PAID,.b-PUBLISHED,.b-ok{background:#e0f2e9;color:var(--ok)}
.b-EXPIRED,.b-pending,.b-PENDING{background:#fdf3e0;color:var(--warn)}
.b-SUSPENDED,.b-warn{background:#fdf3e0;color:var(--warn)}
.b-REVOKED,.b-FAILED,.b-BLOCKED,.b-bad,.b-CANCELLED,.b-REFUNDED{background:#fbe4e2;color:var(--bad)}
.b-LOW,.b-INACTIVE,.b-mute{background:#e6ebf1;color:var(--muted)}
.b-MEDIUM{background:#fdf3e0;color:#996800}
.b-HIGH,.b-CRITICAL{background:#fbe4e2;color:var(--bad)}
table.tbl{width:100%;border-collapse:collapse;background:var(--card);border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 3px rgba(10,30,50,.08)}
.tbl th{font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);text-align:left;padding:10px 12px;border-bottom:2px solid var(--line)}
.tbl td{padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:middle}
.tbl tr:hover td{background:rgba(24,119,194,.05)}
.card{background:var(--card);border-radius:var(--radius);box-shadow:0 1px 3px rgba(10,30,50,.08);padding:18px 20px;margin-bottom:18px}
.card h2{margin:0 0 12px;font-size:16px}
.btn{display:inline-block;padding:8px 16px;border-radius:8px;border:1.5px solid var(--blue);background:transparent;color:var(--blue);font-weight:700;font-size:13px;cursor:pointer;text-decoration:none!important}
.btn:hover{background:rgba(24,119,194,.08)}
.btn.primary{background:var(--blue);color:#fff}
.btn.primary:hover{filter:brightness(1.1)}
.btn.danger{border-color:var(--bad);color:var(--bad)}
.btn.danger:hover{background:#fbe4e2}
.btn.sm{padding:4px 10px;font-size:12px}
input,select,textarea{font:inherit;padding:9px 11px;border:1.5px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink)}
input:focus,select:focus,textarea:focus{outline:2px solid var(--blue);border-color:var(--blue)}
label{display:block;font-size:12.5px;font-weight:600;color:var(--muted);margin:10px 0 4px}
.grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
.kpi{background:var(--card);border-radius:14px;box-shadow:0 1px 3px rgba(10,30,50,.08);padding:16px;display:flex;gap:12px;align-items:center;border:1px solid transparent;transition:transform .15s,box-shadow .15s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(10,30,50,.10)}
.kpi-ico{flex:none;width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:19px;background:color-mix(in srgb,var(--blue) 14%,transparent)}
.kpi-c-ok{--k:#0e7a4f}.kpi-c-ok .kpi-ico{background:color-mix(in srgb,#0e7a4f 14%,transparent)}
.kpi-c-warn{--k:#996800}.kpi-c-warn .kpi-ico{background:color-mix(in srgb,#996800 16%,transparent)}
.kpi-c-bad{--k:#b32d2e}.kpi-c-bad .kpi-ico{background:color-mix(in srgb,#b32d2e 13%,transparent)}
.kpi .v{font-size:24px;font-weight:800;color:var(--k,var(--blue));line-height:1.1}
.kpi .l{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700}
.card h2{display:flex;align-items:center;gap:8px}
.tbl tbody tr{transition:background .12s}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:color-mix(in srgb,var(--muted) 40%,transparent);border-radius:99px}
::-webkit-scrollbar-track{background:transparent}
.notice{padding:10px 14px;border-radius:9px;margin-bottom:14px;font-weight:600}
.notice.ok{background:#e0f2e9;color:var(--ok)}
.notice.err{background:#fbe4e2;color:var(--bad)}
.muted{color:var(--muted)}
code,.mono{font-family:ui-monospace,Consolas,monospace;font-size:.95em}
.toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px}
.pagination{display:flex;gap:6px;margin:14px 0;flex-wrap:wrap}
.pagination a{padding:5px 11px;border-radius:7px;border:1px solid var(--line);color:var(--blue);font-weight:600}
.pagination a.on{background:var(--blue);color:#fff;border-color:var(--blue)}
`;

/* ---------- Layout dashboard ---------- */
/* Le CSS de base (CSS) + ADMIN_CSS sont servis via /assets/admin.css (cache navigateur).
   La structure responsive (sidebar off-canvas, burger, bell) vit dans ADMIN_CSS. */
export function layout(req, admin, title, content, active = '') {
	const menu = [
		['Dashboard', '/admin', '📊'],
		['STORE', null],
		['Products', '/admin/products', '🧩'], ['Plans', '/admin/plans', '🏷️'], ['Orders', '/admin/orders', '🧾'], ['Customers', '/admin/customers', '👥'],
		['LICENSES', null],
		['Licenses', '/admin/licenses', '🔑'], ['Installations', '/admin/installations', '🖥️'], ['Activations', '/admin/activations', '⚡'],
		['UPDATES', null],
		['Releases', '/admin/releases', '📦'],
		['SECURITY', null],
		['Security Events', '/admin/security', '🛡️'], ['API Logs', '/admin/api-logs', '📡'], ['Audit Logs', '/admin/audit-logs', '📝'],
		['COMMUNICATION', null],
		['Email Templates', '/admin/emails', '✉️'], ['Email Logs', '/admin/email-logs', '📨'],
		['SYSTEM', null],
		['Settings', '/admin/settings', '⚙️'], ['Administrators', '/admin/admins', '👤'],
	];
	const nav = menu.map(([label, href, icon]) => {
		if (!href) return `<li class="sep">${esc(label)}</li>`;
		return `<li><a href="${href}" class="${active === href ? 'on' : ''}">${icon} <span>${esc(label)}</span></a></li>`;
	}).join('');
	const unread = Number(admin.unread || 0);
	const notifs = db.prepare('SELECT * FROM notifications ORDER BY id DESC LIMIT 6').all();
	const notifItems = notifs.length
		? notifs.map((n) => `<div class="nd ${n.read ? '' : 'un'}"><div>${esc(n.message)}</div><time>${esc(fmtDate(n.ts))}</time></div>`).join('')
		: '<div class="nd empty">Aucune notification.</div>';
	const crumbs = active && active !== '/admin'
		? `<div class="crumbs"><a href="/admin">Dashboard</a> <span>/</span> <strong>${esc(title)}</strong></div>`
		: '';
	return `<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${esc(title)} — Infinity License</title><link rel="stylesheet" href="/assets/admin.css"></head><body>
<div id="side-overlay"></div>
<div class="app">
<aside class="side">
	<div class="brand"><span class="mark">∞</span><div><strong>Infinity</strong><small>License Server</small></div></div>
	<ul>${nav}</ul>
	<div class="side-foot"><span class="badge b-ok">● ${esc(admin.role)}</span></div>
</aside>
<main class="main">
<header class="top">
	<div class="side-toggle-cell"><button id="side-toggle" class="burger" aria-label="Menu">☰</button></div>
	<div class="headings"><h1>${esc(title)}</h1>${crumbs}</div>
	<form action="/admin/search" method="get" class="gsearch"><input name="q" placeholder="⌕  License, email, domaine, commande…"><button class="btn sm">Search</button></form>
	<div class="top-actions">
		<details class="belldd"><summary class="bell" title="Notifications">🔔${unread ? `<span class="cnt">${unread}</span>` : ''}</summary>
			<div class="dd"><div class="dd-h">Notifications</div>${notifItems}<a href="/admin/notifications">Tout voir →</a></div>
		</details>
		<button id="theme-toggle" class="theme-btn" title="Light / Dark">🌗</button>
		<form method="post" action="/logout" style="display:inline">${csrfField(admin)}<button class="btn sm" title="Déconnexion">⏻</button></form>
	</div>
</header>
${content}
</main></div>
<script src="/assets/admin.js" defer></script>
</body></html>`;
}

export function csrfField(admin) {
	return `<input type="hidden" name="csrf" value="${esc(admin?.csrf || '')}" />`;
}

export function page(req, admin, res, code, title, content, active = '') {
	secureSend(req, res, code, layout(req, admin, title, content, active));
}

/* ---------- Aperçu SVG (line/bar) sans dépendance ---------- */
export function svgBars(data, { width = 640, height = 160, color = '#1877c2' } = {}) {
	if (!data.length) return '<div class="muted">No data yet.</div>';
	const max = Math.max(...data.map((d) => d[1]), 1);
	const bw = Math.min(28, (width - 20) / data.length - 6);
	let out = `<svg viewBox="0 0 ${width} ${height}" style="width:100%;max-width:${width}px">`;
	data.forEach(([label, v], i) => {
		const h = Math.round((v / max) * (height - 42));
		const x = 12 + i * ((width - 20) / data.length);
		out += `<rect x="${x}" y="${height - 24 - h}" width="${bw}" height="${h}" rx="4" fill="${color}" opacity="0.9"><title>${esc(label)}: ${v}</title></rect>`;
		if (data.length <= 16) out += `<text x="${x + bw / 2}" y="${height - 8}" font-size="9" fill="var(--muted)" text-anchor="middle">${esc(label.slice(-5))}</text>`;
	});
	return out + '</svg>';
}

/* ---------- Pages publiques ---------- */
export function publicShell(req, res, code, title, content) {
	secureSend(req, res, code,`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${esc(title)} — Infinity License</title><style>${CSS}
.nav{background:var(--navy);color:#fff;padding:14px 6vw;display:flex;gap:22px;align-items:center;flex-wrap:wrap}
.nav .brand{font-weight:800;font-size:17px;display:flex;gap:9px;align-items:center;margin-right:auto}
.nav .brand .dot{width:12px;height:12px;border-radius:50%;background:conic-gradient(#4da3e8,#fff,#4da3e8)}
.nav a{color:#d7e6f5;font-weight:600}
.hero{background:linear-gradient(135deg,var(--navy) 0%,#123a5e 55%,var(--blue) 130%);color:#fff;padding:76px 6vw;text-align:center}
.hero h1{color:#fff;font-size:clamp(28px,5vw,46px);margin:0 0 14px;max-width:820px;margin-inline:auto}
.hero p{font-size:17px;opacity:.9;max-width:640px;margin:0 auto 28px}
.hero .cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.hero .cta a{padding:13px 26px;border-radius:10px;font-weight:700}
.cta-primary{background:#fff;color:var(--navy)}
.cta-ghost{border:1.5px solid rgba(255,255,255,.6);color:#fff}
section{padding:60px 6vw}
.grid-products{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(250px,1fr))}
.prod{background:var(--card);border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(10,30,50,.07);transition:transform .15s}
.prod:hover{transform:translateY(-3px)}
.prod .emoji{font-size:34px}
.prod h3{margin:8px 0 4px}
.sec-title{text-align:center;font-size:30px;margin:0 0 8px}
.sec-sub{text-align:center;color:var(--muted);max-width:560px;margin:0 auto 34px}
.plans{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));max-width:1050px;margin:0 auto}
.plan{background:var(--card);border-radius:14px;padding:26px;text-align:center;box-shadow:0 2px 10px rgba(10,30,50,.07);position:relative}
.plan.star{border:2px solid var(--blue)}
.plan .badge{position:absolute;top:-11px;left:50%;transform:translateX(-50%);background:var(--blue);color:#fff;padding:3px 12px;border-radius:99px;font-size:11px}
.plan .price{font-size:36px;font-weight:800}
.plan ul{list-style:none;padding:0;margin:14px 0;color:var(--muted);font-size:13.5px}
.plan li{padding:4px 0}
footer{background:var(--navy);color:#9db7d2;padding:30px 6vw;text-align:center;font-size:13px}
</style></head><body>
<nav class="nav"><span class="brand"><span class="dot"></span> Infinity License</span>
<a href="/">Home</a><a href="/products">Products</a><a href="/#pricing">Pricing</a><a href="/#faq">FAQ</a><a href="/login">Login</a></nav>
${content}
<footer>© ${new Date().getFullYear()} Infinity License — Derouiche Oussama · Infinity Coder</footer></body></html>`);
}

export function landingPage(req, res, products, plans) {
	const prodCards = products.map((p) => `
	<a class="prod" href="/products/${esc(p.slug)}" style="color:inherit;text-decoration:none">
		<div class="emoji">${esc(p.logo_emoji)}</div><h3>${esc(p.name)}</h3>
		<p class="muted" style="margin:0">${esc(p.tagline)}</p>
		<p style="margin:10px 0 0;font-weight:700;color:var(--blue)">Discover →</p>
	</a>`).join('');
	const planCards = plans.map((pl, i) => `
	<div class="plan ${i === 1 ? 'star' : ''}">${i === 1 ? '<span class="badge">Most popular</span>' : ''}
		<h3>${esc(pl.name)}</h3><div class="price">${pl.price === 0 ? 'Free' : '$' + pl.price}</div>
		<ul><li>${pl.sites_limit >= 999 ? 'Unlimited' : pl.sites_limit} site(s)</li>
		<li>${pl.duration_days === 0 ? 'Lifetime' : pl.duration_days + ' days'} license</li>
		<li>${pl.updates_months >= 999 ? 'Lifetime' : pl.updates_months + ' months'} updates</li>
		<li>${esc(pl.support)} support</li></ul>
		<a class="btn primary" href="/checkout?plan=${pl.id}">Buy now</a>
	</div>`).join('');

	publicShell(req, res, 200, 'Professional WordPress Plugins', `
<div class="hero">
	<h1>Professional WordPress Plugins for Modern Businesses</h1>
	<p>InfinityCOD and a growing suite of premium plugins built for cash-on-delivery commerce across the Arab market. One license server, one dashboard, zero friction.</p>
	<div class="cta"><a class="cta-primary" href="/products">View Products</a><a class="cta-ghost" href="/#pricing">Buy Now</a><a class="cta-ghost" href="/#faq">Documentation</a><a class="cta-ghost" href="/login">Login</a></div>
</div>
<section id="products"><h2 class="sec-title">Products</h2><p class="sec-sub">Every plugin ships with a real license, real updates, real support.</p>
<div class="grid-products">${prodCards}</div></section>
<section id="pricing" style="background:var(--bg)"><h2 class="sec-title">Simple pricing</h2><p class="sec-sub">Pick a plan. Activate on your sites. Done.</p>
<div class="plans">${planCards}</div></section>
<section id="faq" style="background:var(--card)"><h2 class="sec-title">FAQ</h2>
<div class="card" style="max-width:760px;margin:0 auto 12px"><h3>How does activation work?</h3><p class="muted" style="margin:0">Install the plugin, paste your license key, done. The plugin validates securely against this server and keeps working during offline periods.</p></div>
<div class="card" style="max-width:760px;margin:0 auto 12px"><h3>What happens when my license expires?</h3><p class="muted" style="margin:0">Your plugin keeps working. Updates stop until you renew.</p></div>
<div class="card" style="max-width:760px;margin:0 auto"><h3>Can I move my license to another site?</h3><p class="muted" style="margin:0">Deactivate from the old site, activate on the new one — or contact support.</p></div>
</section>`);
}

export function productsPage(req, res, products) {
	const cards = products.map((p) => `
	<a class="prod" href="/products/${esc(p.slug)}" style="color:inherit;text-decoration:none">
		<div class="emoji">${esc(p.logo_emoji)}</div><h3>${esc(p.name)} <span class="badge b-ok">v${esc(p.version)}</span></h3>
		<p class="muted" style="margin:0">${esc(p.tagline)}</p></a>`).join('');
	publicShell(req, res, 200, 'Products', `<section><h2 class="sec-title">Products</h2><p class="sec-sub">The Infinity suite.</p><div class="grid-products">${cards}</div></section>`);
}

export function productPage(req, res, product, plans, releases) {
	const features = JSON.parse(product.features || '[]').map((f) => `<li>✅ ${esc(f)}</li>`).join('');
	const rel = releases.map((r) => `<tr><td class="mono">v${esc(r.version)}</td><td><span class="badge b-${r.status}">${r.status}</span> <span class="badge">${r.channel}</span></td><td>${esc(r.created_at)}</td><td>${r.downloads}</td></tr>`).join('');
	publicShell(req, res, 200, product.name, `
<div class="hero"><h1>${esc(product.logo_emoji)} ${esc(product.name)}</h1><p>${esc(product.tagline)}</p>
<div class="cta"><a class="cta-primary" href="/checkout?product=${esc(product.slug)}">Buy Now</a><a class="cta-ghost" href="${esc(product.docs_url || '#')}">Documentation</a></div></div>
<section><div style="max-width:860px;margin:0 auto">
<h2>Features</h2><ul style="line-height:2">${features}</ul>
<h2>Requirements</h2><p class="muted">WordPress ${esc(product.wp_min)}+ · PHP ${esc(product.php_min)}+ · Current version v${esc(product.version)}</p>
<h2>Changelog</h2><table class="tbl"><tr><th>Version</th><th>Status</th><th>Date</th><th>Downloads</th></tr>${rel || '<tr><td colspan=4 class=muted>No releases yet.</td></tr>'}</table>
<h2>Pricing</h2><div class="plans">${plans.map((pl) => `<div class="plan"><h3>${esc(pl.name)}</h3><div class="price">$${pl.price}</div><ul><li>${pl.sites_limit} site(s)</li><li>${pl.duration_days === 0 ? 'Lifetime' : pl.duration_days + ' days'}</li></ul><a class="btn primary" href="/checkout?plan=${pl.id}&product=${esc(product.slug)}">Buy now</a></div>`).join('')}</div>
</div></section>`);
}

export function checkoutPage(req, res, plan, product, err = '', values = {}) {
	publicShell(req, res, err ? 400 : 200, 'Checkout', `
<section style="max-width:640px;margin:0 auto">
<h2 class="sec-title">Checkout</h2><p class="sec-sub">${esc(product?.name || '')} — ${esc(plan.name)} · $${plan.price} (${esc(plan.currency)}) · ${plan.sites_limit} site(s)</p>
<div class="card">
${err ? `<div class="notice err">${esc(err)}</div>` : ''}
<form method="post" action="/checkout">
<input type="hidden" name="plan_id" value="${plan.id}">
<input type="hidden" name="product_id" value="${product ? product.id : ''}">
<div class="grid"><div><label>First name *</label><input name="first_name" required value="${esc(values.first_name || '')}"></div>
<div><label>Last name *</label><input name="last_name" required value="${esc(values.last_name || '')}"></div></div>
<label>Email *</label><input name="email" type="email" required value="${esc(values.email || '')}">
<div class="grid"><div><label>Company</label><input name="company" value="${esc(values.company || '')}"></div>
<div><label>Phone</label><input name="phone" value="${esc(values.phone || '')}"></div></div>
<label>Country</label><input name="country" value="${esc(values.country || '')}">
<label>Payment method</label><select name="payment_method"><option value="manual">Manual payment (validated by vendor)</option></select>
<button class="btn primary" style="width:100%;margin-top:18px;padding:13px">Place order — $${plan.price} ${esc(plan.currency)}</button>
<p class="muted" style="font-size:12px">Manual orders are validated by the vendor; your license key is emailed right after validation.</p>
</form></div></section>`);
}

export function checkoutDone(req, res, order, licenseKey, email) {
	publicShell(req, res, 200, 'Order received', `
<section style="max-width:640px;margin:0 auto;text-align:center">
<h2 class="sec-title">🎉 Order ${esc(order.reference)} placed</h2>
<p class="sec-sub">Your license key has been generated${licenseKey ? ' and is shown below' : ' and will be emailed to ' + esc(email) + ' once payment is validated'}.</p>
${licenseKey ? `<div class="card"><div class="mono" style="font-size:20px;font-weight:800;letter-spacing:.06em">${esc(licenseKey)}</div></div>` : ''}
<p><a class="btn primary" href="/products">Back to products</a></p></section>`);
}

/* ---------- Graphique aire avec dégradé ---------- */
export function svgArea(data, { width = 680, height = 180, color = '#1877c2', label } = {}) {
	if (!data.length) return '<div class="muted">No data yet.</div>';
	const max = Math.max(...data.map((d) => d[1]), 1);
	const step = (width - 24) / Math.max(1, data.length - 1);
	const pts = data.map((d, i) => [12 + i * step, height - 30 - Math.round((d[1] / max) * (height - 50))]);
	const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0] + ' ' + p[1]).join(' ');
	const area = line + ` L${pts[pts.length - 1][0]} ${height - 26} L${pts[0][0]} ${height - 26} Z`;
	const gid = 'g' + Math.random().toString(36).slice(2, 8);
	let out = `<svg viewBox="0 0 ${width} ${height}" style="width:100%;max-width:${width}px">`;
	out += `<defs><linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${color}" stop-opacity=".35"/><stop offset="1" stop-color="${color}" stop-opacity=".03"/></linearGradient></defs>`;
	for (let gy = height - 26; gy > 14; gy -= (height - 56) / 3) out += `<line x1="12" x2="${width - 12}" y1="${gy}" y2="${gy}" stroke="var(--line)" stroke-dasharray="3 5"/>`;
	out += `<path d="${area}" fill="url(#${gid})"/><path d="${line}" fill="none" stroke="${color}" stroke-width="2.4" stroke-linejoin="round"/>`;
	pts.forEach((p, i) => { out += `<circle cx="${p[0]}" cy="${p[1]}" r="2.6" fill="${color}"><title>${esc(data[i][0])}: ${data[i][1]}</title></circle>`; });
	if (label) out += `<text x="12" y="14" font-size="11" fill="var(--muted)">${esc(label)} — max ${max}</text>`;
	return out + '</svg>';
}
