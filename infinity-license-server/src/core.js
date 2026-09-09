import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { db } from './db.js';
import { config, ensureStorage } from './config.js';

ensureStorage();

/* ---------- Général ---------- */
export const now = () => new Date().toISOString().replace('T', ' ').slice(0, 19);
export const nowTs = () => Math.floor(Date.now() / 1000);
export const uid = (n = 12) => crypto.randomBytes(n).toString('hex');

export function esc(s) {
	return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
export function json(res, code, obj) {
	res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8' });
	res.end(JSON.stringify(obj));
}
export function fmtDate(iso) {
	return iso ? String(iso).slice(0, 16).replace('T', ' ') : '—';
}

/* ---------- Hachage & chiffrement ---------- */
export const sha256 = (s) => crypto.createHash('sha256').update(String(s)).digest('hex');

export function hashPassword(password) {
	const salt = crypto.randomBytes(16).toString('hex');
	const hash = crypto.scryptSync(password, salt, 64).toString('hex');
	return `scrypt$${salt}$${hash}`;
}
export function verifyPassword(password, stored) {
	try {
		const [algo, salt, hash] = String(stored).split('$');
		if (algo !== 'scrypt') return false;
		return crypto.timingSafeEqual(Buffer.from(hash, 'hex'), crypto.scryptSync(password, salt, 64));
	} catch { return false; }
}

const AES_KEY = crypto.createHash('sha256').update(config.APP_KEY).digest();
export function encrypt(plain) {
	const iv = crypto.randomBytes(12);
	const cipher = crypto.createCipheriv('aes-256-gcm', AES_KEY, iv);
	const enc = Buffer.concat([cipher.update(String(plain), 'utf8'), cipher.final()]);
	return `${iv.toString('base64')}:${cipher.getAuthTag().toString('base64')}:${enc.toString('base64')}`;
}
export function decrypt(payload) {
	try {
		const [ivB64, tagB64, dataB64] = String(payload).split(':');
		const decipher = crypto.createDecipheriv('aes-256-gcm', AES_KEY, Buffer.from(ivB64, 'base64'));
		decipher.setAuthTag(Buffer.from(tagB64, 'base64'));
		return Buffer.concat([decipher.update(Buffer.from(dataB64, 'base64')), decipher.final()]).toString('utf8');
	} catch { return ''; }
}

/* ---------- Clés de licence (cryptographiquement sûres) ---------- */
const KEY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I,O,0,1 (anti-confusion)
export function generateLicenseKey(prefix = 'INFC') {
	const group = () => {
		const bytes = crypto.randomBytes(4);
		let out = '';
		for (let i = 0; i < 4; i++) out += KEY_ALPHABET[bytes[i] % KEY_ALPHABET.length];
		return out;
	};
	return `${prefix}-${group()}-${group()}-${group()}-${group()}`;
}
export function keyPreview(key) {
	const parts = String(key).split('-');
	if (parts.length < 2) return key.slice(0, 9) + '…';
	return `${parts[0]}-${parts[1]}-••••-••••-${parts[parts.length - 1]}`;
}

/* ---------- Signatures Ed25519 ---------- */
const KEY_DIR = path.join(config.STORAGE_PATH, 'keys');

export function ensureKeys() {
	fs.mkdirSync(KEY_DIR, { recursive: true });
	const priv = path.join(KEY_DIR, 'license_private.pem');
	const pub = path.join(KEY_DIR, 'license_public.pem');
	if (!fs.existsSync(priv)) {
		const { publicKey, privateKey } = crypto.generateKeyPairSync('ed25519');
		fs.writeFileSync(priv, privateKey.export({ type: 'pkcs8', format: 'pem' }), { mode: 0o600 });
		fs.writeFileSync(pub, publicKey.export({ type: 'spki', format: 'pem' }));
	}
	return { priv, pub };
}
export function privateKeyPem() { ensureKeys(); return fs.readFileSync(path.join(KEY_DIR, 'license_private.pem'), 'utf8'); }
export function publicKeyPem() { ensureKeys(); return fs.readFileSync(path.join(KEY_DIR, 'license_public.pem'), 'utf8'); }

/** Signe un payload JSON (réponses sensibles de l'API licence). */
export function signPayload(payload) {
	const body = JSON.stringify(payload);
	const signature = crypto.sign(null, Buffer.from(body), privateKeyPem()).toString('base64');
	return { body, signature, algorithm: 'ed25519' };
}

/* ---------- Domaines ---------- */
export function normalizeDomain(input) {
	let d = String(input || '').trim().toLowerCase();
	d = d.replace(/^https?:\/\//, '').replace(/\/.*$/, '').replace(/:\d+$/, '');
	d = d.replace(/^www\./, '');
	return d;
}
export function classifyEnvironment(domain) {
	const d = normalizeDomain(domain);
	if (/^(localhost|127\.0\.0\.1|\[::1\])$/.test(d)) return 'LOCAL';
	if (/\.(local|test|example|invalid|dev)$/.test(d)) return 'LOCAL';
	if (/(^|\.)(staging|dev|preprod|stg|demo)[.-]/.test(d) || /(staging|stg)\./.test(d)) return 'STAGING';
	return 'PRODUCTION';
}

/* ---------- Rate limiting (fenêtre glissante en DB) ---------- */
export function rateLimit(bucket, limit, windowSec) {
	const win = Math.floor(nowTs() / windowSec) * windowSec;
	const key = `${bucket}:${win}`;
	const row = db.prepare('SELECT count FROM rate_limits WHERE key = ?').get(key);
	const count = row ? row.count : 0;
	if (count >= limit) return { ok: false, retryAfter: windowSec };
	db.prepare('INSERT INTO rate_limits(key,count,window_start) VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET count = count + 1').run(key, win);
	// Purge légère des vieilles fenêtres (1 % de chances).
	if (Math.random() < 0.01) db.prepare('DELETE FROM rate_limits WHERE window_start < ?').run(nowTs() - 3600);
	return { ok: true };
}

/* ---------- Logs ---------- */
export function apiLog(route, ip, status, licenseId = 0, detail = '') {
	db.prepare('INSERT INTO api_logs(ts,route,ip,status,license_id,detail) VALUES(?,?,?,?,?,?)').run(now(), route, ip || '', status, licenseId, String(detail).slice(0, 500));
}
export function audit(adminRow, action, target = '', detail = '', ip = '') {
	db.prepare('INSERT INTO audit_logs(ts,admin_id,admin_email,action,target,detail,ip) VALUES(?,?,?,?,?,?,?)')
		.run(now(), adminRow?.id || 0, adminRow?.email || 'system', action, target, String(detail).slice(0, 500), ip);
	db.prepare('INSERT INTO notifications(ts,type,message) VALUES(?,?,?)').run(now(), 'audit', `${action} ${target}`.trim());
}
export function securityEvent(type, severity, detail, licenseId = 0, installationId = '', ip = '') {
	db.prepare('INSERT INTO security_events(ts,type,severity,license_id,installation_id,detail,ip) VALUES(?,?,?,?,?,?,?)')
		.run(now(), type, severity, licenseId, installationId, String(detail).slice(0, 500), ip || '');
	db.prepare('INSERT INTO notifications(ts,type,message) VALUES(?,?,?)').run(now(), 'security', `${type}: ${String(detail).slice(0, 120)}`);
}
export function notify(type, message) {
	db.prepare('INSERT INTO notifications(ts,type,message) VALUES(?,?,?)').run(now(), type, message);
}

/* ---------- Emails (file + templates + transport log par défaut) ---------- */
export function renderTemplate(tpl, vars = {}) {
	return String(tpl).replace(/\{\{(\w+)\}\}/g, (_, k) => vars[k] ?? '');
}
export function queueEmail(toEmail, templateSlug, vars = {}) {
	const tpl = db.prepare('SELECT * FROM email_templates WHERE slug = ?').get(templateSlug);
	const subject = renderTemplate(tpl?.subject || templateSlug, vars);
	const body = renderTemplate(tpl?.body || '', vars);
	db.prepare('INSERT INTO email_logs(ts,to_email,template,subject,body,status) VALUES(?,?,?,?,?,?)')
		.run(now(), toEmail, templateSlug, subject, body, config.SMTP_HOST ? 'QUEUED' : 'LOGGED');
	// Transport : SMTP configuré = envoi réel (implémenté dans emails-smtp), sinon journal local complet.
	if (config.SMTP_HOST) {
		// Le transport SMTP sortant est branché côté worker ; la file email_logs reste la source de vérité.
		db.prepare("UPDATE email_logs SET status = 'QUEUED_SMTP' WHERE id = (SELECT MAX(id) FROM email_logs)").run();
	}
	return { subject, body };
}

