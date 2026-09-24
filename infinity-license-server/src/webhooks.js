/**
 * Webhooks entrants — Freemius (paiements) vers commandes + licences.
 *
 * Authentification : token secret dans l'URL (?token=…) — méthode recommandée
 * par Freemius pour les intégrations custom — complétée par la vérification
 * optionnelle de l'en-tête x-signature (HMAC-SHA256 hex du corps brut) quand
 * FREEMIUS_WEBHOOK_SECRET est configuré.
 *
 * Comportement :
 *   - idempotent (table webhook_events UNIQUE(provider,event_key), les
 *     re-livraisons Freemius répondent 200 sans créer de doublon) ;
 *   - paiement réussi → commande PAID + licence (nouvelle, ou prolongée en
 *     cas de renouvellement) + email au client ;
 *   - remboursement / annulation → notification admin, AUCUNE action
 *     automatique sur la licence (décision marchande) ;
 *   - événement non reconnu → journalisé, 200 (pas de re-livraison infinie).
 */
import crypto from 'node:crypto';
import { db, getSetting } from './db.js';
import { config } from './config.js';
import { json, now, sha256, apiLog, audit, notify, fmtDate, queueEmail } from './core.js';
import { createLicense } from './api.js';

/** Comparaison en temps constant de deux chaînes secrètes. */
function safeEqual(a, b) {
	const ha = crypto.createHash('sha256').update(String(a)).digest();
	const hb = crypto.createHash('sha256').update(String(b)).digest();
	return crypto.timingSafeEqual(ha, hb);
}

/** Trouve un premier champ dans un objet imbriqué (chemins « a.b.c »), tolérant. */
function dig(obj, paths) {
	for (const p of paths) {
		let cur = obj;
		for (const k of p.split('.')) {
			cur = cur && typeof cur === 'object' ? cur[k] : undefined;
		}
		if (cur !== undefined && cur !== null && cur !== '') return cur;
	}
	return undefined;
}

/** Classification grossière du type d'événement Freemius. */
function classify(type) {
	const t = String(type || '').toLowerCase().replace(/[\s_]+/g, '.');
	if (/^payment\.(success|completed|confirmed)/.test(t)) return 'PURCHASE';
	if (/^subscription\.(started|activated|renewed|renewal\.success|renewal|payment\.success)/.test(t)) return 'PURCHASE';
	if (/refund/.test(t)) return 'REFUND';
	if (/cancel/.test(t)) return 'CANCEL';
	if (/expire|suspend|failed/.test(t)) return 'LIFECYCLE';
	return 'OTHER';
}

/** Extrait les infos acheteur/achat des différentes formes de payload Freemius. */
function extractEvent(body) {
	const o = body.objects || {};
	return {
		type: String(dig(body, ['type', 'event.type', 'event_type', 'event_name']) || ''),
		eventKey: String(dig(body, ['id', 'event_id', 'uuid', 'event.id']) || ''),
		pluginId: dig(body, ['plugin_id', 'objects.plugin.id', 'plugin.id']),
		email: String(dig(body, ['objects.user.email', 'user.email', 'objects.license.user.email', 'email', 'user_email']) || '').toLowerCase().trim(),
		firstName: String(dig(body, ['objects.user.first_name', 'user.first_name', 'first_name']) || ''),
		lastName: String(dig(body, ['objects.user.last_name', 'user.last_name', 'last_name']) || ''),
		name: String(dig(body, ['objects.user.name', 'user.name', 'objects.user.full_name', 'full_name']) || ''),
		amount: Number(dig(body, ['objects.payment.gross', 'objects.payment.amount', 'payment.gross', 'amount', 'total']) || 0),
		currency: String(dig(body, ['objects.payment.currency', 'currency']) || 'USD').toUpperCase().slice(0, 3),
		transactionId: String(dig(body, ['objects.payment.transaction_id', 'objects.payment.id', 'payment.transaction_id', 'transaction_id', 'payment_id']) || ''),
		freemiusLicenseKey: String(dig(body, ['objects.license.secret_key', 'license.secret_key', 'secret_key']) || ''),
	};
}

/** Produit ciblé : mapping settings (freemius_plugin_id / freemius_product_slug), défaut InfinityCOD. */
function targetProduct(pluginId) {
	const expected = getSetting('freemius_plugin_id', '');
	const slug = getSetting('freemius_product_slug', 'infinitycod') || 'infinitycod';
	if (expected && pluginId && String(pluginId) !== String(expected)) {
		return { product: null, mismatch: true };
	}
	const product = db.prepare('SELECT * FROM products WHERE slug = ?').get(slug)
		|| db.prepare('SELECT * FROM products ORDER BY id LIMIT 1').get();
	return { product, mismatch: false };
}

/** Plan : montant identique → plan ; sinon setting freemius_plan_id ; sinon 1er plan actif du produit. */
function targetPlan(productId, amount) {
	if (amount > 0) {
		const byPrice = db.prepare("SELECT * FROM plans WHERE status='ACTIVE' AND price = ? ORDER BY sort LIMIT 1").get(amount);
		if (byPrice) return byPrice;
	}
	const settingId = Number(getSetting('freemius_plan_id', '0'));
	if (settingId) {
		const bySetting = db.prepare('SELECT * FROM plans WHERE id = ?').get(settingId);
		if (bySetting) return bySetting;
	}
	return db.prepare("SELECT * FROM plans WHERE status='ACTIVE' AND (product_id = ? OR product_id IS NULL) ORDER BY sort LIMIT 1").get(productId) || null;
}

function upsertCustomer(ev) {
	const email = ev.email;
	let customer = db.prepare('SELECT * FROM customers WHERE email = ?').get(email);
	if (!customer) {
		const name = [ev.firstName, ev.lastName].filter(Boolean).join(' ') || ev.name || email.split('@')[0];
		db.prepare('INSERT INTO customers(name,email,phone,company,country,created_at,last_activity) VALUES(?,?,?,?,?,?,?)')
			.run(name, email, '', '', '', now(), now());
		customer = db.prepare('SELECT * FROM customers WHERE email = ?').get(email);
	}
	return customer;
}

/** Crée la commande PAID + la licence (ou prolonge en renouvellement) + email. */
function fulfillPurchase(ev, provider) {
	const { product, mismatch } = targetProduct(ev.pluginId);
	if (mismatch) {
		return { ok: false, code: 'PLUGIN_MISMATCH', message: `plugin_id ${ev.pluginId} ne correspond pas au réglage freemius_plugin_id` };
	}
	if (!product) return { ok: false, code: 'NO_PRODUCT', message: 'aucun produit configuré' };
	if (!ev.email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(ev.email)) {
		return { ok: false, code: 'NO_EMAIL', message: 'email acheteur introuvable dans le payload' };
	}
	const customer = upsertCustomer(ev);
	const plan = targetPlan(product.id, ev.amount);
	const days = plan ? plan.duration_days : 365;
	const reference = 'FS-' + crypto.randomBytes(3).toString('hex').toUpperCase();
	db.prepare(`INSERT INTO orders(reference,customer_id,product_id,plan_id,amount,currency,status,payment_method,payment_ref,created_at,paid_at)
		VALUES(?,?,?,?,?,?,?,?,?,?,?)`)
		.run(reference, customer.id, product.id, plan ? plan.id : null, ev.amount || (plan ? plan.price : 0), ev.currency || 'USD',
			'PAID', 'freemius', ev.transactionId, now(), now());
	const order = db.prepare('SELECT * FROM orders WHERE reference = ?').get(reference);

	// Renouvellement : prolonger la licence existante du client pour ce produit.
	const existing = db.prepare('SELECT * FROM licenses WHERE customer_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1')
		.get(customer.id, product.id);
	let lic, renewed = false;
	const base = existing && existing.expires_at && existing.expires_at > now() ? existing.expires_at : now();
	const newExpiry = days > 0 ? new Date(new Date(base.replace(' ', 'T') + 'Z').getTime() + days * 864e5).toISOString().replace('T', ' ').slice(0, 19) : '';
	if (existing && days > 0) {
		db.prepare('UPDATE licenses SET expires_at = ?, status = ?, plan_id = COALESCE(?, plan_id) WHERE id = ?')
			.run(newExpiry, 'ACTIVE', plan ? plan.id : null, existing.id);
		lic = { id: existing.id, key: '', preview: existing.key_preview };
		renewed = true;
	} else {
		lic = createLicense({
			customer_id: customer.id, product_id: product.id, plan_id: plan ? plan.id : null,
			expires_at: newExpiry,
			activation_limit: plan ? plan.sites_limit : 1,
			updates_until: plan ? new Date(Date.now() + plan.updates_months * 30 * 864e5).toISOString().slice(0, 10) : '',
			notes: `Freemius ${ev.transactionId}`,
		});
	}
	db.prepare('UPDATE orders SET license_id = ? WHERE id = ?').run(lic.id, order.id);
	const fullKey = lic.key || '';
	queueLicenseEmail(customer, product, plan, fullKey || lic.preview, renewed, newExpiry);
	audit(null, provider + (renewed ? '.renewal' : '.purchase'), reference, `${product.slug} → ${lic.preview} (${ev.email})`);
	return { ok: true, order: reference, license_preview: lic.preview, renewed, expiry: newExpiry };
}

function queueLicenseEmail(customer, product, plan, licenseKey, renewed, expiry) {
	queueEmail(customer.email, renewed ? 'license_renewed' : 'license_created', {
		customer_name: customer.name, product_name: product.name, license_key: licenseKey,
		expiration_date: expiry ? fmtDate(expiry) : 'Never',
		activation_limit: plan ? plan.sites_limit : 1,
		download_url: config.APP_URL + '/products/' + product.slug,
	});
}

/* ---------------- Route unique : POST /api/webhooks/freemius ---------------- */
export async function handleFreemiusWebhook(req, res, url, rawBody, ip) {
	const route = '/api/webhooks/freemius';
	if (!config.FREEMIUS_WEBHOOK_TOKEN) {
		json(res, 503, { success: false, error: { code: 'WEBHOOK_DISABLED', message: 'FREEMIUS_WEBHOOK_TOKEN non configuré.' } });
		return apiLog(route, ip, 503, 0, 'disabled');
	}
	const provided = url.searchParams.get('token') || req.headers['x-webhook-token'] || '';
	if (!provided || !safeEqual(provided, config.FREEMIUS_WEBHOOK_TOKEN)) {
		notify('security', 'Webhook Freemius rejeté : token invalide');
		json(res, 401, { success: false, error: { code: 'UNAUTHORIZED', message: 'Token invalide.' } });
		return apiLog(route, ip, 401, 0, 'bad_token');
	}
	// x-signature : si le client l'envoie ET qu'un secret est configuré, elle doit être valide.
	const sig = String(req.headers['x-signature'] || '').toLowerCase().replace(/^sha256=/, '');
	if (sig && config.FREEMIUS_WEBHOOK_SECRET) {
		const expected = crypto.createHmac('sha256', config.FREEMIUS_WEBHOOK_SECRET).update(String(rawBody)).digest('hex');
		if (!safeEqual(sig, expected)) {
			notify('security', 'Webhook Freemius rejeté : signature x-signature invalide');
			json(res, 401, { success: false, error: { code: 'BAD_SIGNATURE', message: 'Signature invalide.' } });
			return apiLog(route, ip, 401, 0, 'bad_signature');
		}
	}

	let body = {};
	try { body = JSON.parse(rawBody || '{}'); } catch { /* payload non JSON */ }
	if (!body || typeof body !== 'object' || Array.isArray(body)) body = {};
	const ev = extractEvent(body);
	const eventKey = ev.eventKey || sha256(rawBody).slice(0, 40);
	const kind = classify(ev.type);

	// Idempotence : une re-livraison ne reproduit jamais un effet.
	const inserted = db.prepare('INSERT OR IGNORE INTO webhook_events(provider,event_key,type,status,detail,created_at) VALUES(?,?,?,?,?,?)')
		.run('freemius', eventKey, ev.type, 'PROCESSING', String(rawBody).slice(0, 400), now());
	if (Number(inserted.changes) === 0) {
		json(res, 200, { success: true, dedupe: true });
		return apiLog(route, ip, 200, 0, 'duplicate');
	}

	try {
		if (kind === 'PURCHASE') {
			const result = fulfillPurchase(ev, 'freemius');
			if (!result.ok) {
				db.prepare('UPDATE webhook_events SET status=?, detail=? WHERE provider=? AND event_key=?').run('ERROR', result.code, 'freemius', eventKey);
				notify('order', `Webhook Freemius non traité (${result.code}) : ${result.message} — événement ${eventKey}`);
				json(res, 200, { success: false, code: result.code, message: result.message });
				return apiLog(route, ip, 200, 0, result.code);
			}
			db.prepare('UPDATE webhook_events SET status=?, detail=? WHERE provider=? AND event_key=?')
				.run('FULFILLED', `${result.order} → ${result.license_preview}${result.renewed ? ' (renouvellement)' : ''}`, 'freemius', eventKey);
			notify('order', `Freemius : ${result.renewed ? 'renouvellement' : 'achat'} ${result.order} — licence ${result.license_preview} pour ${ev.email}`);
			json(res, 200, { success: true, order: result.order, license: result.license_preview, renewed: result.renewed });
			return apiLog(route, ip, 200, 0, `${result.order} ${result.license_preview}`);
		}

		// REFUND / CANCEL / LIFECYCLE / OTHER : visibilité admin, aucune action automatique.
		db.prepare('UPDATE webhook_events SET status=?, detail=? WHERE provider=? AND event_key=?').run('LOGGED', ev.email || '-', 'freemius', eventKey);
		if (kind === 'REFUND' || kind === 'CANCEL') {
			notify('order', `Freemius ${ev.type} : ${ev.email || 'client inconnu'} — vérifier la licence manuellement (action automatique désactivée)`);
		}
		json(res, 200, { success: true, logged: kind.toLowerCase() });
		apiLog(route, ip, 200, 0, `logged:${ev.type || 'unknown'}`);
	} catch (e) {
		db.prepare('UPDATE webhook_events SET status=?, detail=? WHERE provider=? AND event_key=?').run('ERROR', String(e.message).slice(0, 300), 'freemius', eventKey);
		notify('security', `Webhook Freemius en erreur : ${e.message} (événement ${eventKey})`);
		json(res, 500, { success: false, error: { code: 'WEBHOOK_ERROR', message: 'Erreur de traitement.' } });
		apiLog(route, ip, 500, 0, String(e.message).slice(0, 120));
	}
}
