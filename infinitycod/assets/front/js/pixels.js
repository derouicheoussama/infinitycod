/**
 * InfinityCod — Pixels publicitaires (Meta / TikTok / Snapchat).
 *
 * Funnel COD : PageView → ViewContent → InitiateCheckout → Purchase.
 * Meta : event_id partagé navigateur/serveur (déduplication CAPI),
 * advanced matching du téléphone côté client (auto-haché par Meta).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @license GPL-2.0-or-later
 * @link https://derouicheoussama.com
 */
(function () {
	'use strict';

	if (typeof icodPixels === 'undefined') { return; }

	var CFG = icodPixels;

	/* Consentement (exigence optionnelle) : window.icodConsent (posé par un
	   CMP) ou cookie icod_consent=1. Sans consentement, aucun pixel ne charge. */
	if (CFG.consent) {
		var cookieOk = /(?:^|;\s*)icod_consent=1/.test(document.cookie);
		if (!window.icodConsent && !cookieOk) { return; }
	}

	/* Injecte un script externe de façon asynchrone. */
	function loadScript(src) {
		var s = document.createElement('script');
		s.async = true;
		s.src = src;
		document.head.appendChild(s);
	}

	/* ---------- Meta (Facebook) Pixel + CAPI ---------- */
	var fb = CFG.fb;
	if (fb && fb.id) {
		/* istanbul ignore next — base officielle Meta. */
		if (!window.fbq) {
			var _fbq = window.fbq = function () {
				_fbq.callMethod ? _fbq.callMethod.apply(_fbq, arguments) : _fbq.queue.push(arguments);
			};
			window._fbq = _fbq;
			_fbq.push = _fbq;
			_fbq.loaded = true;
			_fbq.version = '2.0';
			_fbq.queue = [];
			loadScript('https://connect.facebook.net/en_US/fbevents.js');
		}
		window.fbq('init', fb.id);
		window.fbq('track', 'PageView');
	}

	/* ---------- TikTok Pixel ---------- */
	var tt = CFG.tiktok;
	if (tt && tt.id && !window.ttq) {
		window.TiktokAnalyticsObject = 'ttq';
		var ttq = window.ttq = [];
		ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
		ttq.setAndDefer = function (obj, method) {
			obj[method] = function () { obj.push([method].concat(Array.prototype.slice.call(arguments, 0))); };
		};
		for (var i = 0; i < ttq.methods.length; i++) { ttq.setAndDefer(ttq, ttq.methods[i]); }
		ttq.load = function (id) {
			ttq._i = ttq._i || {};
			ttq._i[id] = [];
			ttq._t = ttq._t || {};
			ttq._t[id] = +new Date();
			ttq._o = ttq._o || {};
			loadScript('https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + id + '&lib=ttq');
		};
		ttq.load(tt.id);
		ttq.page();
	}

	/* ---------- Snapchat Pixel ---------- */
	var snap = CFG.snap;
	if (snap && snap.id && !window.snaptr) {
		var snaptr = window.snaptr = function () {
			snaptr.handleRequest ? snaptr.handleRequest.apply(snaptr, arguments) : snaptr.queue.push(arguments);
		};
		snaptr.queue = [];
		loadScript('https://sc-static.net/scevent.min.js');
		window.snaptr('init', snap.id);
		window.snaptr('track', 'PAGE_VIEW');
	}

	/* ---------- ViewContent (fiche produit) ---------- */
	var p = CFG.product || { id: 0, name: '', price: 0 };
	if (p.id) {
		if (fb && fb.id) {
			window.fbq('track', 'ViewContent', {
				content_ids: [String(p.id)],
				content_type: 'product',
				content_name: p.name,
				value: p.price,
				currency: CFG.currency
			});
		}
		if (tt && tt.id) {
			window.ttq.track('ViewContent', {
				content_id: String(p.id),
				content_type: 'product',
				content_name: p.name,
				quantity: 1,
				value: p.price,
				currency: CFG.currency
			});
		}
		if (snap && snap.id) {
			window.snaptr('track', 'VIEW_CONTENT', {
				item_ids: [String(p.id)],
				item_category: 'product',
				price: p.price,
				currency: CFG.currency
			});
		}
	}

	/* ---------- InitiateCheckout : première interaction avec le formulaire ---------- */
	var checkoutFired = false;
	function fireInitiateCheckout(root) {
		if (checkoutFired) { return; }
		checkoutFired = true;
		var qtyInput = root.querySelector('.icod-qty-input');
		var qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
		var value = p.price * qty;
		if (fb && fb.id) {
			window.fbq('track', 'InitiateCheckout', {
				content_ids: [String(p.id)],
				content_type: 'product',
				value: value,
				currency: CFG.currency,
				num_items: qty
			});
		}
		if (tt && tt.id) {
			window.ttq.track('InitiateCheckout', {
				content_id: String(p.id),
				quantity: qty,
				value: value,
				currency: CFG.currency
			});
		}
		if (snap && snap.id) {
			window.snaptr('track', 'ADD_CART', { item_ids: [String(p.id)], price: value, currency: CFG.currency });
		}
	}
	document.querySelectorAll('.icod-root').forEach(function (root) {
		['focusin', 'change'].forEach(function (evt) {
			root.addEventListener(evt, function () { fireInitiateCheckout(root); }, { once: false, passive: true });
		});
	});

	/* ---------- Purchase : appelé par form.js après confirmation ---------- */
	window.icodFirePurchase = function (orderId, total, productName, phone) {
		var eventId = 'icod-' + orderId;

		if (fb && fb.id) {
			/* Advanced matching : téléphone normalisé (213…), Meta le hache.
			   eventID en 3e argument = déduplication avec la Conversions API. */
			var match = {};
			if (phone) {
				var digits = String(phone).replace(/\D/g, '');
				if (digits.charAt(0) === '0') { digits = '213' + digits.slice(1); }
				match.ph = digits;
			}
			window.fbq('track', 'Purchase', {
				content_ids: [String(p.id)],
				content_type: 'product',
				content_name: productName || p.name,
				value: total,
				currency: CFG.currency,
				num_items: 1
			}, { eventID: eventId });
			if (match.ph) {
				window.fbq('init', fb.id, match);
			}
		}

		if (tt && tt.id) {
			window.ttq.track('CompletePayment', {
				content_id: String(p.id),
				content_type: 'product',
				content_name: productName || p.name,
				quantity: 1,
				value: total,
				currency: CFG.currency,
				event_id: eventId
			});
		}

		if (snap && snap.id) {
			window.snaptr('track', 'PURCHASE', {
				item_ids: [String(p.id)],
				transaction_id: eventId,
				price: total,
				currency: CFG.currency
			});
		}
	};
})();
