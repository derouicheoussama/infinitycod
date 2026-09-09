import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import crypto from 'node:crypto';

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const ENV_PATH = path.join(ROOT, '.env');
const env = {};

// Parseur .env minimal (sans dépendance).
if (fs.existsSync(ENV_PATH)) {
	for (const line of fs.readFileSync(ENV_PATH, 'utf8').split('\n')) {
		const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
		if (m && !line.trim().startsWith('#')) env[m[1]] = m[2].replace(/^["']|["']$/g, '');
	}
}

export const config = {
	PORT: Number(env.PORT || process.env.PORT || 8787),
	APP_URL: env.APP_URL || process.env.APP_URL || `http://127.0.0.1:${env.PORT || process.env.PORT || 8787}`,
	API_URL: env.API_URL || process.env.API_URL || '',
	DB_PATH: env.DB_PATH || process.env.DB_PATH || path.join(ROOT, 'storage', 'infinity-license.db'),
	STORAGE_PATH: env.STORAGE_PATH || process.env.STORAGE_PATH || path.join(ROOT, 'storage'),
	APP_KEY: env.APP_KEY || '', // AES-256 key (base64). Généré automatiquement si absent.
	JWT_SECRET: env.JWT_SECRET || env.SESSION_SECRET || '',
	SESSION_TTL_HOURS: Number(env.SESSION_TTL_HOURS || 72),
	GRACE_NOTE: env.DEFAULT_GRACE_HOURS || 72,
	SMTP_HOST: env.SMTP_HOST || '',
	SMTP_PORT: Number(env.SMTP_PORT || 587),
	SMTP_USER: env.SMTP_USER || '',
	SMTP_PASSWORD: env.SMTP_PASSWORD || '',
	MAIL_FROM: env.MAIL_FROM || 'licenses@infinitycod.pro',
	GITHUB_TOKEN: env.GITHUB_TOKEN || '',
	BRAND: { name: 'Infinity License', accent: '#1877c2', navy: '#0b2239' },
};

export const ROOT_PATH = ROOT;

// APP_KEY : génération automatique persistante si absente.
if (!config.APP_KEY) {
	const keyFile = path.join(config.STORAGE_PATH, '.app_key');
	if (fs.existsSync(keyFile)) {
		config.APP_KEY = fs.readFileSync(keyFile, 'utf8').trim();
	} else {
		config.APP_KEY = crypto.randomBytes(32).toString('base64');
		fs.mkdirSync(config.STORAGE_PATH, { recursive: true });
		fs.writeFileSync(keyFile, config.APP_KEY, { mode: 0o600 });
	}
}
if (!config.JWT_SECRET) config.JWT_SECRET = config.APP_KEY;

export function ensureStorage() {
	for (const dir of ['keys', 'releases', 'logs', 'backups']) {
		fs.mkdirSync(path.join(config.STORAGE_PATH, dir), { recursive: true });
	}
}
