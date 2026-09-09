import { db } from './db.js';
import { now } from './core.js';

/** Seed idempotent : produits, plans globaux, templates email. Exécuté à chaque démarrage. */
export function seed() {
	const count = db.prepare('SELECT COUNT(*) c FROM products').get().c;
	if (count === 0) {
		const ins = db.prepare(`INSERT INTO products(slug,name,tagline,description,features,logo_emoji,version,wp_min,php_min,status,github_owner,github_repo,docs_url,created_at)
			VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)`);
		const P = (slug, name, tagline, desc, features, emoji, repo) =>
			ins.run(slug, name, tagline, desc, JSON.stringify(features), emoji, '1.0.0', '6.0', '7.4', 'PUBLISHED', 'derouicheoussama', repo, `https://infinitycod.pro/docs/${slug}`, now());

		P('infinitycod', 'InfinityCOD', 'The complete WooCommerce COD solution.',
			'One-page cash-on-delivery checkout for WooCommerce: 58 wilayas & 1541 communes (Algeria) + Arab-market multi-country, per-region pricing, fraud shield, integrated carriers, WhatsApp automation, Meta Conversions API and full analytics.',
			['One-page COD form, mobile-first', '58 wilayas & 1541 communes (FR/AR)', 'Multi-country (Premium): MA, TN, EG, SA, AE', '19 currencies', 'Anti-fraud shield with risk score', 'Carriers: Yalidine, ZR, Maystro, Noest, E-COM, DHD', 'WhatsApp automation + abandoned carts', 'Meta / TikTok / Snapchat pixels + CAPI 2026', 'PayPal upgrade flow built-in'],
			'🛒', 'infinitycod');
		P('infinityinvoice', 'InfinityInvoice', 'Professional invoicing for WordPress.',
			'Generate, send and track professional invoices directly from WordPress.', ['PDF invoices', 'Multi-currency', 'Payment tracking'], '🧾', 'infinityinvoice');
		P('infinityvisitstat', 'InfinityVisitStat', 'Privacy-friendly visitor analytics.',
			'GDPR-friendly visitor statistics without cookies.', ['Realtime stats', 'No cookies', 'Lightweight'], '📈', 'infinityvisitstat');
		P('infinityadmincustomizer', 'InfinityAdminCustomizer', 'Beautiful WordPress admin, your brand.',
			'Customize the WordPress admin with your brand colors, logo and layout.', ['Brand colors', 'Custom login', 'Dark admin'], '🎨', 'infinityadmincustomizer');
		P('infinity404redirect', 'Infinity404Redirect', 'Smart 404 monitoring & redirects.',
			'Track 404 errors and create smart redirects in seconds.', ['404 logging', '301/302 redirects', 'Bulk import'], '🔀', 'infinity404redirect');
		P('infinityduplicator', 'InfinityDuplicator', 'Duplicate pages, posts & products instantly.',
			'One-click duplication with full metadata support.', ['1-click clone', 'Meta & media support', 'Bulk actions'], '📑', 'infinityduplicator');
	}

	const planCount = db.prepare('SELECT COUNT(*) c FROM plans').get().c;
	if (planCount === 0) {
		const ins = db.prepare(`INSERT INTO plans(product_id,name,price,currency,duration_days,sites_limit,updates_months,support,features,status,sort)
			VALUES(?,?,?,?,?,?,?,?,?,?,?)`);
		ins.run(null, 'Personal', 39, 'USD', 365, 1, 12, 'email', JSON.stringify(['1 site', '1 year of updates', 'Email support']), 'ACTIVE', 1);
		ins.run(null, 'Business', 79, 'USD', 365, 3, 12, 'priority', JSON.stringify(['3 sites', '1 year of updates', 'Priority support']), 'ACTIVE', 2);
		ins.run(null, 'Agency', 149, 'USD', 365, 10, 12, 'priority', JSON.stringify(['10 sites', '1 year of updates', 'Priority support']), 'ACTIVE', 3);
		ins.run(null, 'Lifetime', 299, 'USD', 0, 10, 1200, 'priority', JSON.stringify(['10 sites', 'Lifetime updates', 'Priority support']), 'ACTIVE', 4);
	}

	const tpl = db.prepare('SELECT COUNT(*) c FROM email_templates').get().c;
	if (tpl === 0) {
		const ins = db.prepare('INSERT INTO email_templates(slug,subject,body) VALUES(?,?,?)');
		ins.run('license_created', 'Your {{product_name}} license is ready',
			`Hello {{customer_name}},\n\nYour license is ready.\n\nProduct: {{product_name}}\nLicense: {{license_key}}\nExpires: {{expiration_date}}\nAllowed sites: {{activation_limit}}\n\nDownload: {{download_url}}\n\nThank you,\nInfinity License`);
		ins.run('license_expiring', 'Your {{product_name}} license expires soon',
			`Hello {{customer_name}},\n\nYour license {{license_key}} expires on {{expiration_date}}.\nRenew here: {{download_url}}`);
		ins.run('welcome', 'Welcome to Infinity License',
			`Hello {{customer_name}},\n\nWelcome! Your account is ready.`);
		ins.run('order_confirmation', 'Order {{order_reference}} confirmed',
			`Hello {{customer_name}},\n\nOrder {{order_reference}} is confirmed.\nProduct: {{product_name}}\nTotal: {{amount}} {{currency}}`);
	}
}
