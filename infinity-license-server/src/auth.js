import { db } from './db.js';
import { config } from './config.js';
import { json, now, uid, hashPassword, verifyPassword, esc, audit } from './core.js';

const COOKIE = 'ils_session';

export function parseCookies(req) {
	const out = {};
	for (const part of String(req.headers.cookie || '').split(';')) {
		const i = part.indexOf('=');
		if (i > 0) out[part.slice(0, i).trim()] = decodeURIComponent(part.slice(i + 1).trim());
	}
	return out;
}

export function currentAdmin(req) {
	const token = parseCookies(req)[COOKIE];
	if (!token) return null;
	const row = db.prepare(`SELECT s.token, s.csrf, s.expires_at, a.* FROM sessions s JOIN admins a ON a.id = s.admin_id WHERE s.token = ? AND a.status = 'ACTIVE'`).get(token);
	if (!row) return null;
	if (row.expires_at < now()) {
		db.prepare('DELETE FROM sessions WHERE token = ?').run(token);
		return null;
	}
	// Session glissante : prolongée à chaque activité si moins de la moitié du TTL reste.
	const half = new Date(Date.now() + (config.SESSION_TTL_HOURS * 3600 * 1000) / 2).toISOString().replace('T', ' ').slice(0, 19);
	if (row.expires_at < half) {
		const extended = new Date(Date.now() + config.SESSION_TTL_HOURS * 3600 * 1000).toISOString().replace('T', ' ').slice(0, 19);
		db.prepare('UPDATE sessions SET expires_at = ? WHERE token = ?').run(extended, token);
	}
	return row;
}

export async function createSession(req, res, adminId, ip) {
	const token = uid(32);
	const csrf = uid(16);
	const expires = new Date(Date.now() + config.SESSION_TTL_HOURS * 3600 * 1000).toISOString().replace('T', ' ').slice(0, 19);
	db.prepare('INSERT INTO sessions(token,admin_id,csrf,created_at,expires_at,ip) VALUES(?,?,?,?,?,?)').run(token, adminId, csrf, now(), expires, ip);
	db.prepare('UPDATE admins SET last_login = ? WHERE id = ?').run(now(), adminId);
	// Secure dès que la requête est HTTPS (direct ou derrière un proxy TLS).
	const isHttps = req && ((req.socket && req.socket.encrypted) || req.headers['x-forwarded-proto'] === 'https');
	const secure = isHttps ? '; Secure' : '';
	res.setHeader('Set-Cookie', `${COOKIE}=${token}; HttpOnly; SameSite=Lax; Path=/; Max-Age=${config.SESSION_TTL_HOURS * 3600}${secure}`);
	return { token, csrf };
}

export function destroySession(req, res) {
	const token = parseCookies(req)[COOKIE];
	if (token) db.prepare('DELETE FROM sessions WHERE token = ?').run(token);
	res.setHeader('Set-Cookie', `${COOKIE}=; HttpOnly; SameSite=Lax; Path=/; Max-Age=0`);
}

export function checkCsrf(req, admin, body) {
	return admin && body && body.csrf === admin.csrf;
}

export function adminsExist() {
	return db.prepare('SELECT COUNT(*) c FROM admins').get().c > 0;
}

export function tooManyLogins(ip) {
	const since = Date.now() - 15 * 60 * 1000;
	const n = db.prepare('SELECT COUNT(*) c FROM login_attempts WHERE ip = ? AND ts > ?').get(ip, since).c;
	return n >= 10;
}
export function recordLoginAttempt(ip, email) {
	db.prepare('INSERT INTO login_attempts(ip,email,ts) VALUES(?,?,?)').run(ip, email || '', Date.now());
}

/** HTML des pages d'authentification (setup + login), rendues par views. */
export async function handleAuthRoutes(req, res, url, body, ip, renderHtml) {
	const pathn = url.pathname;

	if (pathn === '/setup' && !adminsExist()) {
		if (req.method === 'GET') return renderHtml(res, 200, setupPage('', ''));
		const { name, email, password, confirm } = body;
		const errs = [];
		if (!name || String(name).length < 2) errs.push('Name is required.');
		if (!isEmail(email)) errs.push('Valid email required.');
		if (!strongPassword(password)) errs.push('Password: 10+ chars with upper, lower, digit.');
		if (password !== confirm) errs.push('Passwords do not match.');
		if (errs.length) return renderHtml(res, 400, setupPage(esc(String(email || '')), errs.join(' ')));
		db.prepare('INSERT INTO admins(email,name,password_hash,role,created_at) VALUES(?,?,?,?,?)')
			.run(String(email).toLowerCase(), String(name), hashPassword(password), 'super_admin', now());
		const admin = db.prepare('SELECT * FROM admins WHERE email = ?').get(String(email).toLowerCase());
		audit(admin, 'admin.created', email, 'First super admin', ip);
		await createSession(req, res, admin.id, ip);
		res.writeHead(302, { Location: '/admin' });
		return res.end();
	}

	if (pathn === '/login') {
		if (adminsExist() && req.method === 'GET') {
			const admin = currentAdmin(req);
			if (admin) { res.writeHead(302, { Location: '/admin' }); return res.end(); }
			return renderHtml(res, 200, loginPage(''));
		}
		if (req.method === 'GET') { res.writeHead(302, { Location: '/setup' }); return res.end(); }

		if (tooManyLogins(ip)) {
			return renderHtml(res, 429, loginPage('Too many attempts. Try again in 15 minutes.'));
		}
		const { email, password } = body;
		const admin = db.prepare('SELECT * FROM admins WHERE email = ?').get(String(email || '').toLowerCase());
		if (!admin || admin.status !== 'ACTIVE' || !verifyPassword(String(password || ''), admin.password_hash)) {
			recordLoginAttempt(ip, email);
			securityLite('ADMIN_LOGIN_FAILED', `Failed login for ${email}`, ip);
			return renderHtml(res, 401, loginPage('Invalid credentials.'));
		}
		await createSession(req, res, admin.id, ip);
		audit(admin, 'admin.login', admin.email, '', ip);
		res.writeHead(302, { Location: '/admin' });
		return res.end();
	}

	if (pathn === '/logout' && req.method === 'POST') {
		const admin = currentAdmin(req);
		if (admin) audit(admin, 'admin.logout', admin.email, '', ip);
		destroySession(req, res);
		res.writeHead(302, { Location: '/login' });
		return res.end();
	}

	return false;
}


function isEmail(s) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(s || '')); }
function strongPassword(s) { s = String(s || ''); return s.length >= 10 && /[A-Z]/.test(s) && /[a-z]/.test(s) && /\d/.test(s); }

function securityLite(type, detail, ip) {
	// Import léger évité : écriture directe.
	db.prepare('INSERT INTO security_events(ts,type,severity,license_id,installation_id,detail,ip) VALUES(?,?,?,?,?,?,?)')
		.run(now(), type, 'MEDIUM', 0, '', detail, ip);
}

function shellAuth(title, content, extra = '') {
	return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${title} — Infinity License</title><style>
:root{--navy:#0b2239;--blue:#1877c2;--bg:#f2f6fb}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,Segoe UI,sans-serif;background:linear-gradient(135deg,var(--navy) 0%,#123a5e 60%,var(--blue) 130%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:16px;box-shadow:0 24px 70px rgba(4,18,38,.45);padding:38px 40px;width:100%;max-width:420px}
h1{margin:0 0 4px;font-size:22px;color:var(--navy)}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:22px;font-weight:800;color:var(--navy);font-size:18px}
.brand .dot{width:14px;height:14px;border-radius:50%;background:conic-gradient(var(--blue),#4da3e8,var(--blue))}
label{display:block;font-size:13px;font-weight:600;color:#33465c;margin:14px 0 5px}
input{width:100%;padding:11px 13px;border:1.5px solid #c6d4e2;border-radius:9px;font-size:15px}
input:focus{outline:2px solid var(--blue);border-color:var(--blue)}
button{width:100%;margin-top:20px;padding:12px;border:0;border-radius:9px;background:var(--blue);color:#fff;font-size:15px;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.08)}
.err{background:#fdecec;color:#a02020;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:8px}
.hint{font-size:12px;color:#7b8da1;margin-top:6px}
</style></head><body><div class="card">
<div class="brand"><span class="dot"></span> Infinity License</div>
${content}${extra}
</div></body></html>`;
}

function setupPage(email, err) {
	return shellAuth('Setup', `
<h1>Create your super admin</h1>
<p class="hint">First run: this page locks itself after the first administrator is created.</p>
${err ? `<div class="err">${esc(err)}</div>` : ''}
<form method="post" action="/setup">
<label>Full name</label><input name="name" required value="">
<label>Email</label><input name="email" type="email" required value="${email}">
<label>Password</label><input name="password" type="password" required>
<div class="hint">10+ characters, upper + lower + digit.</div>
<label>Confirm password</label><input name="confirm" type="password" required>
<button>Create admin & sign in</button>
</form>`);
}

function loginPage(err) {
	return shellAuth('Sign in', `
<h1>Sign in</h1>
${err ? `<div class="err">${esc(err)}</div>` : ''}
<form method="post" action="/login">
<label>Email</label><input name="email" type="email" required>
<label>Password</label><input name="password" type="password" required>
<button>Sign in</button>
</form>`);
}
