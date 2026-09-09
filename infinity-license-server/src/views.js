import { esc } from './core.js';

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
.kpi{background:var(--card);border-radius:var(--radius);box-shadow:0 1px 3px rgba(10,30,50,.08);padding:16px}
.kpi .v{font-size:26px;font-weight:800;color:var(--blue)}
.kpi .l{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
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
export function layout(req, admin, title, content, active = '') {
	const menu = [
		['Dashboard', '/admin', '📊'],
		['STORE', null, ''],
		['Products', '/admin/products', '🧩'], ['Plans', '/admin/plans', '🏷️'], ['Orders', '/admin/orders', '🧾'], ['Customers', '/admin/customers', '👥'],
		['LICENSES', null, ''],
		['Licenses', '/admin/licenses', '🔑'], ['Installations', '/admin/installations', '🖥️'], ['Activations', '/admin/activations', '⚡'],
		['UPDATES', null, ''],
		['Releases', '/admin/releases', '📦'],
		['SECURITY', null, ''],
		['Security Events', '/admin/security', '🛡️'], ['API Logs', '/admin/api-logs', '📡'], ['Audit Logs', '/admin/audit-logs', '📝'],
		['COMMUNICATION', null, ''],
		['Email Templates', '/admin/emails', '✉️'], ['Email Logs', '/admin/email-logs', '📨'],
		['SYSTEM', null, ''],
		['Settings', '/admin/settings', '⚙️'], ['Administrators', '/admin/admins', '👤'],
	];
	const nav = menu.map(([label, href, icon]) => {
		if (!href) return `<li class="sep">${esc(label)}</li>`;
		return `<li><a href="${href}" class="${active === href ? 'on' : ''}">${icon} ${esc(label)}</a></li>`;
	}).join('');
	const notifs = admin ? String(admin.notifications_count || 0) : '';
	return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${esc(title)} — Infinity License</title><style>${CSS}
.app{display:grid;grid-template-columns:236px 1fr;min-height:100vh}
.side{background:var(--navy);color:#cfe0f2;padding:18px 0;position:sticky;top:0;height:100vh;overflow-y:auto}
.side .brand{padding:0 20px 14px;font-weight:800;color:#fff;font-size:16px;display:flex;gap:9px;align-items:center}
.side .brand .dot{width:12px;height:12px;border-radius:50%;background:conic-gradient(var(--blue2),#fff,var(--blue2))}
.side ul{list-style:none;margin:0;padding:0}
.side li.sep{padding:14px 20px 5px;font-size:10.5px;letter-spacing:.12em;color:#6f8aa8;text-transform:uppercase}
.side li a{display:block;padding:8px 20px;color:#cfe0f2;font-weight:600;font-size:13.5px}
.side li a:hover{background:rgba(255,255,255,.06);text-decoration:none}
.side li a.on{background:var(--blue);color:#fff;border-radius:0}
.main{padding:22px 26px}
.top{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.top h1{margin:0;font-size:21px}
.top form{display:flex;gap:8px}
.top input[name=q]{min-width:260px}
@media(max-width:900px){.app{grid-template-columns:1fr}.side{position:relative;height:auto}.side ul{display:flex;flex-wrap:wrap;padding:0 10px}.side li.sep{width:100%}}
</style></head><body data-theme="light">
<div class="app">
<aside class="side"><div class="brand"><span class="dot"></span> Infinity License</div><ul>${nav}</ul></aside>
<main class="main">
<div class="top">
<h1>${esc(title)}</h1>
<form action="/admin/search" method="get"><input name="q" placeholder="Search license, email, domain, order…" value=""><button class="btn sm">Search</button></form>
<div><span class="badge b-ok">● ${esc(admin.role)}</span> <form method="post" action="/logout" style="display:inline">${csrfField(admin)}<button class="btn sm">Logout</button></form></div>
</div>
${content}
</main></div></body></html>`;
}

export function csrfField(admin) {
	return `<input type="hidden" name="csrf" value="${esc(admin?.csrf || '')}" />`;
}

export function page(req, admin, res, code, title, content, active = '') {
	res.writeHead(code, { 'Content-Type': 'text/html; charset=utf-8' });
	res.end(layout(req, admin, title, content, active));
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
export function publicShell(res, code, title, content) {
	res.writeHead(code, { 'Content-Type': 'text/html; charset=utf-8' });
	res.end(`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
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

export function landingPage(res, products, plans) {
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

	publicShell(res, 200, 'Professional WordPress Plugins', `
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

export function productsPage(res, products) {
	const cards = products.map((p) => `
	<a class="prod" href="/products/${esc(p.slug)}" style="color:inherit;text-decoration:none">
		<div class="emoji">${esc(p.logo_emoji)}</div><h3>${esc(p.name)} <span class="badge b-ok">v${esc(p.version)}</span></h3>
		<p class="muted" style="margin:0">${esc(p.tagline)}</p></a>`).join('');
	publicShell(res, 200, 'Products', `<section><h2 class="sec-title">Products</h2><p class="sec-sub">The Infinity suite.</p><div class="grid-products">${cards}</div></section>`);
}

export function productPage(res, product, plans, releases) {
	const features = JSON.parse(product.features || '[]').map((f) => `<li>✅ ${esc(f)}</li>`).join('');
	const rel = releases.map((r) => `<tr><td class="mono">v${esc(r.version)}</td><td><span class="badge b-${r.status}">${r.status}</span> <span class="badge">${r.channel}</span></td><td>${esc(r.created_at)}</td><td>${r.downloads}</td></tr>`).join('');
	publicShell(res, 200, product.name, `
<div class="hero"><h1>${esc(product.logo_emoji)} ${esc(product.name)}</h1><p>${esc(product.tagline)}</p>
<div class="cta"><a class="cta-primary" href="/checkout?product=${esc(product.slug)}">Buy Now</a><a class="cta-ghost" href="${esc(product.docs_url || '#')}">Documentation</a></div></div>
<section><div style="max-width:860px;margin:0 auto">
<h2>Features</h2><ul style="line-height:2">${features}</ul>
<h2>Requirements</h2><p class="muted">WordPress ${esc(product.wp_min)}+ · PHP ${esc(product.php_min)}+ · Current version v${esc(product.version)}</p>
<h2>Changelog</h2><table class="tbl"><tr><th>Version</th><th>Status</th><th>Date</th><th>Downloads</th></tr>${rel || '<tr><td colspan=4 class=muted>No releases yet.</td></tr>'}</table>
<h2>Pricing</h2><div class="plans">${plans.map((pl) => `<div class="plan"><h3>${esc(pl.name)}</h3><div class="price">$${pl.price}</div><ul><li>${pl.sites_limit} site(s)</li><li>${pl.duration_days === 0 ? 'Lifetime' : pl.duration_days + ' days'}</li></ul><a class="btn primary" href="/checkout?plan=${pl.id}&product=${esc(product.slug)}">Buy now</a></div>`).join('')}</div>
</div></section>`);
}

export function checkoutPage(res, plan, product, err = '', values = {}) {
	publicShell(res, err ? 400 : 200, 'Checkout', `
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

export function checkoutDone(res, order, licenseKey, email) {
	publicShell(res, 200, 'Order received', `
<section style="max-width:640px;margin:0 auto;text-align:center">
<h2 class="sec-title">🎉 Order ${esc(order.reference)} placed</h2>
<p class="sec-sub">Your license key has been generated${licenseKey ? ' and is shown below' : ' and will be emailed to ' + esc(email) + ' once payment is validated'}.</p>
${licenseKey ? `<div class="card"><div class="mono" style="font-size:20px;font-weight:800;letter-spacing:.06em">${esc(licenseKey)}</div></div>` : ''}
<p><a class="btn primary" href="/products">Back to products</a></p></section>`);
}
