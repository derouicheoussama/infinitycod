import { DatabaseSync } from 'node:sqlite';
import fs from 'node:fs';
import { config, ensureStorage } from './config.js';

ensureStorage();
fs.mkdirSync(config.STORAGE_PATH, { recursive: true });

export const db = new DatabaseSync(config.DB_PATH);
db.exec('PRAGMA journal_mode = WAL;');
db.exec('PRAGMA foreign_keys = ON;');

const SCHEMA = `
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS admins (
	id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, name TEXT NOT NULL DEFAULT '',
	password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'super_admin', status TEXT NOT NULL DEFAULT 'ACTIVE',
	totp_secret TEXT DEFAULT '', created_at TEXT NOT NULL, last_login TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS sessions (
	token TEXT PRIMARY KEY, admin_id INTEGER NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
	csrf TEXT NOT NULL, created_at TEXT NOT NULL, expires_at TEXT NOT NULL, ip TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', ts INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS customers (
	id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
	phone TEXT DEFAULT '', company TEXT DEFAULT '', country TEXT DEFAULT '', status TEXT NOT NULL DEFAULT 'ACTIVE',
	created_at TEXT NOT NULL, last_activity TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS products (
	id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL, tagline TEXT DEFAULT '',
	description TEXT DEFAULT '', features TEXT NOT NULL DEFAULT '[]', logo_emoji TEXT DEFAULT '🧩',
	version TEXT NOT NULL DEFAULT '1.0.0', wp_min TEXT DEFAULT '6.0', php_min TEXT DEFAULT '7.4',
	status TEXT NOT NULL DEFAULT 'PUBLISHED', github_owner TEXT DEFAULT '', github_repo TEXT DEFAULT '',
	docs_url TEXT DEFAULT '', created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS plans (
	id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
	name TEXT NOT NULL, price REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'USD',
	duration_days INTEGER NOT NULL DEFAULT 365, sites_limit INTEGER NOT NULL DEFAULT 1,
	updates_months INTEGER NOT NULL DEFAULT 12, support TEXT DEFAULT 'email', features TEXT NOT NULL DEFAULT '[]',
	status TEXT NOT NULL DEFAULT 'ACTIVE', sort INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS licenses (
	id INTEGER PRIMARY KEY AUTOINCREMENT, key_hash TEXT NOT NULL UNIQUE, key_enc TEXT NOT NULL,
	key_preview TEXT NOT NULL, customer_id INTEGER NOT NULL REFERENCES customers(id), product_id INTEGER NOT NULL REFERENCES products(id),
	plan_id INTEGER REFERENCES plans(id), status TEXT NOT NULL DEFAULT 'ACTIVE',
	created_at TEXT NOT NULL, starts_at TEXT NOT NULL, expires_at TEXT DEFAULT '', activation_limit INTEGER NOT NULL DEFAULT 1,
	updates_until TEXT DEFAULT '', allowed_versions TEXT DEFAULT '', notes TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS installations (
	id INTEGER PRIMARY KEY AUTOINCREMENT, license_id INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
	installation_id TEXT NOT NULL UNIQUE, domain TEXT NOT NULL, site_url TEXT DEFAULT '',
	plugin_version TEXT DEFAULT '', wp_version TEXT DEFAULT '', php_version TEXT DEFAULT '',
	environment TEXT NOT NULL DEFAULT 'PRODUCTION', status TEXT NOT NULL DEFAULT 'ACTIVE',
	risk TEXT DEFAULT 'LOW', first_seen TEXT NOT NULL, last_seen TEXT NOT NULL, ip TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS activations (
	id INTEGER PRIMARY KEY AUTOINCREMENT, license_id INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
	installation_id TEXT DEFAULT '', event TEXT NOT NULL, detail TEXT DEFAULT '', created_at TEXT NOT NULL, ip TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS orders (
	id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT NOT NULL UNIQUE, customer_id INTEGER NOT NULL REFERENCES customers(id),
	product_id INTEGER NOT NULL REFERENCES products(id), plan_id INTEGER REFERENCES plans(id),
	amount REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'USD', status TEXT NOT NULL DEFAULT 'PENDING',
	payment_method TEXT DEFAULT 'manual', payment_ref TEXT DEFAULT '', license_id INTEGER DEFAULT 0,
	note TEXT DEFAULT '', created_at TEXT NOT NULL, paid_at TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS releases (
	id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
	version TEXT NOT NULL, channel TEXT NOT NULL DEFAULT 'stable', file_path TEXT DEFAULT '',
	changelog TEXT DEFAULT '', wp_min TEXT DEFAULT '6.0', php_min TEXT DEFAULT '7.4',
	status TEXT NOT NULL DEFAULT 'PUBLISHED', downloads INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL,
	UNIQUE(product_id, version, channel)
);
CREATE TABLE IF NOT EXISTS download_tokens (
	token TEXT PRIMARY KEY, release_id INTEGER NOT NULL REFERENCES releases(id) ON DELETE CASCADE,
	license_id INTEGER NOT NULL, expires_at INTEGER NOT NULL, used INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS api_logs (
	id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT NOT NULL, route TEXT NOT NULL, ip TEXT DEFAULT '',
	status INTEGER NOT NULL DEFAULT 200, license_id INTEGER DEFAULT 0, detail TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS audit_logs (
	id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT NOT NULL, admin_id INTEGER DEFAULT 0, admin_email TEXT DEFAULT '',
	action TEXT NOT NULL, target TEXT DEFAULT '', detail TEXT DEFAULT '', ip TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS security_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT NOT NULL, type TEXT NOT NULL, severity TEXT NOT NULL DEFAULT 'LOW',
	license_id INTEGER DEFAULT 0, installation_id TEXT DEFAULT '', detail TEXT DEFAULT '', ip TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS email_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, subject TEXT NOT NULL, body TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS email_logs (
	id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT NOT NULL, to_email TEXT NOT NULL, template TEXT DEFAULT '',
	subject TEXT DEFAULT '', body TEXT DEFAULT '', status TEXT NOT NULL DEFAULT 'QUEUED'
);
CREATE TABLE IF NOT EXISTS rate_limits (key TEXT PRIMARY KEY, count INTEGER NOT NULL DEFAULT 0, window_start INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS notifications (
	id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT NOT NULL, type TEXT NOT NULL, message TEXT NOT NULL, read INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_lic_customer ON licenses(customer_id);
CREATE INDEX IF NOT EXISTS idx_lic_product ON licenses(product_id);
CREATE INDEX IF NOT EXISTS idx_inst_license ON installations(license_id);
CREATE INDEX IF NOT EXISTS idx_inst_domain ON installations(domain);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_apilog_ts ON api_logs(ts);
CREATE INDEX IF NOT EXISTS idx_activ_license ON activations(license_id);
`;

db.exec(SCHEMA);

export function getSetting(key, fallback = '') {
	const row = db.prepare('SELECT value FROM settings WHERE key = ?').get(key);
	return row ? row.value : fallback;
}
export function setSetting(key, value) {
	db.prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value').run(key, String(value));
}
export function allSettings() {
	const out = {};
	for (const r of db.prepare('SELECT key,value FROM settings').all()) out[r.key] = r.value;
	return out;
}
