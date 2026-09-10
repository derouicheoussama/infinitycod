/**
 * InfinityCod — Formulaire COD front
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @license GPL-2.0-or-later
 * @link https://derouicheoussama.com (vanilla JS).
 * Cascade wilaya→commune→bureau, prix temps réel (serveur), champs pilotés
 * par le Checkout Builder (data-req), captcha (math / reCAPTCHA v3),
 * barre collante mobile, suivi des paniers abandonnés.
 *
 * Tolérant aux champs désactivés : chaque champ du Checkout Builder peut
 * être absent du DOM — tous les accès sont null-safe.
 */
(function () {
	'use strict';

	if (typeof icodFront === 'undefined') { return; }

	var I18N = icodFront.i18n;

	/* ---------- Utilitaires ---------- */

	function el(root, selector) { return root ? root.querySelector(selector) : null; }
	function els(root, selector) { return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : []; }

	function money(amount) {
		var n = Math.round((Number(amount) || 0) * 100) / 100;
		var formatted;
		try {
			formatted = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }).format(n);
		} catch (e) {
			formatted = String(n);
		}
		var label = I18N.da || 'DA';
		var pos = icodFront.currencyPosition || 'right';
		return 'left' === pos ? label + ' ' + formatted : formatted + ' ' + label;
	}

	function debounce(fn, delay) {
		var timer = null;
		return function () {
			var args = arguments, ctx = this;
			clearTimeout(timer);
			timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
		};
	}

	/* Détection du pays du visiteur, sans appel réseau :
	   1. région de la langue navigateur (ar-DZ, fr_MA…) ;
	   2. fuseau horaire (Africa/Casablanca → MA). */
	var TZ_COUNTRY = {
		algiers: 'DZ', casablanca: 'MA', tunis: 'TN', cairo: 'EG',
		riyadh: 'SA', dubai: 'AE', 'abu_dhabi': 'AE', kuwait: 'KW',
		baghdad: 'IQ', tripoli: 'LY', khartoum: 'SD', doha: 'QA',
		muscat: 'OM', manama: 'BH', amman: 'JO', damascus: 'SY',
		sanaa: 'YE', nouakchott: 'MR'
	};

	function detectVisitorCountry() {
		try {
			var lang = navigator.language || (navigator.languages && navigator.languages[0]) || '';
			var m = String(lang).match(/[-_]([A-Za-z]{2})\b/);
			if (m) { return m[1].toUpperCase(); }
		} catch (e) { /* ignoré */ }
		try {
			var tz = (Intl.DateTimeFormat().resolvedOptions().timeZone) || '';
			var city = String(tz).split('/').pop().toLowerCase().replace(/_/g, '_');
			return TZ_COUNTRY[city] || '';
		} catch (e) { return ''; }
	}

	/* Exemples de numéro local par pays (placeholder du champ téléphone). */
	var PHONE_HINTS = {
		DZ: '0555 12 34 56', MA: '0612 34 56 78', TN: '20 123 456',
		EG: '0100 123 4567', SA: '0501 234 567', AE: '050 123 4567',
		KW: '5123 4567', QA: '3312 3456', OM: '7123 4567',
		BH: '3312 3456', JO: '07 9123 4567', LB: '71 123 456',
		IQ: '07XX XXX XXXX', LY: '09X XXX XXXX', SD: '09X XXX XXXX',
		SY: '09XX XXX XXX', YE: '07XX XXX XXX', MR: '2X XX XX XX'
	};

	function api(path, body) {
		var options = { method: body ? 'POST' : 'GET', credentials: 'same-origin' };
		if (body) {
			options.headers = { 'Content-Type': 'application/json' };
			options.body = JSON.stringify(body);
		}
		return fetch(icodFront.restUrl + path, options).then(function (res) {
			return res.json().catch(function () { return {}; });
		});
	}

	/* Empreinte anti-fraude : caractéristiques du navigateur, aucune donnée personnelle. */
	function fingerprint() {
		try {
			var parts = [
				navigator.userAgent.length,
				navigator.language,
				screen.width + 'x' + screen.height,
				screen.colorDepth,
				String(new Date().getTimezoneOffset()),
				(navigator.hardwareConcurrency || 0),
				'ontouchstart' in window ? 1 : 0
			].join('|');
			var hash = 5381;
			for (var i = 0; i < parts.length; i++) {
				hash = ((hash << 5) + hash + parts.charCodeAt(i)) >>> 0;
			}
			return String(hash);
		} catch (e) { return ''; }
	}

	/* ---------- Compte à rebours d'urgence (evergreen par session) ---------- */

	function initTimer(root, productId) {
		var timer = el(root, '[data-timer]');
		if (!timer) { return; }

		var minutes = parseInt(timer.getAttribute('data-timer'), 10) || 120;
		var labelEl = el(timer, '[data-timer-text]');
		var template = labelEl ? (labelEl.getAttribute('data-timer-text') || '') : '';
		var key = 'icod_timer_' + productId;
		var start = 0;

		try {
			start = parseInt(window.sessionStorage.getItem(key), 10) || 0;
			if (!start || start > Date.now() || (Date.now() - start) > minutes * 60000) {
				start = Date.now();
				window.sessionStorage.setItem(key, String(start));
			}
		} catch (e) { start = Date.now(); }

		function pad(n) { return (n < 10 ? '0' : '') + n; }

		function tick() {
			var elapsed = Math.floor((Date.now() - start) / 1000);
			var remaining = minutes * 60 - elapsed;
			if (remaining <= 0) { /* Boucle : nouvelle session de compte à rebours. */
				start = Date.now();
				try { window.sessionStorage.setItem(key, String(start)); } catch (e2) { /* ignoré */ }
				remaining = minutes * 60;
			}
			if (labelEl) {
				var clock = pad(Math.floor(remaining / 60)) + ':' + pad(remaining % 60);
				labelEl.textContent = template ? template.replace('{time}', clock) : '⏳ ' + clock;
			}
		}
		tick();
		window.setInterval(tick, 1000);
	}

	/* ---------- Formulaire ---------- */

	function initForm(root) {
		var form = el(root, '.icod-form');
		if (!form) { return; }

		var state = {
			productId: parseInt(root.getAttribute('data-product'), 10) || 0,
			variations: JSON.parse(root.getAttribute('data-variations') || '[]'),
			unitPrice: parseFloat(root.getAttribute('data-unit-price')) || 0,
			qtyMin: parseInt(root.getAttribute('data-qty-min'), 10) || 1,
			qtyMax: parseInt(root.getAttribute('data-qty-max'), 10) || 20,
			variationId: 0,
			quote: null,
			coupon: '',
			communesCache: {}
		};

		var nameInput = el(form, '[data-icod-field="name"]');
		var phoneInput = el(form, '[data-icod-field="phone"]');
		var wilayaSelect = el(form, '[data-icod-field="wilaya"]');
		var communeSelect = el(form, '[data-icod-field="commune"]');
		var addressInput = el(form, '[name="icod_address"]');
		var deskSelect = el(form, '.icod-desk');
		var deskWrap = el(form, '.icod-stopdesk-wrap');
		var qtyInput = el(root, '.icod-qty-input');
		var msgBox = el(root, '[data-icod-msg]');
		var submitBtn = el(form, '.icod-submit');
		var fpInput = el(form, '.icod-fp');

		if (fpInput) { fpInput.value = fingerprint(); }

		function currentQty() { return qtyInput ? (parseInt(qtyInput.value, 10) || state.qtyMin) : state.qtyMin; }

		/* --- Affichage permanent du prix (en-tête + récap) --- */
		function updateHeadPrice() {
			var live = el(root, '[data-head-price-live]');
			if (live) { live.textContent = money(state.unitPrice); }
			var regular = parseFloat(root.getAttribute('data-regular-price')) || 0;
			var regularEl = el(root, '[data-head-price-regular]');
			if (regularEl) {
				var show = regular > state.unitPrice + 0.001;
				regularEl.classList.toggle('icod-hidden', !show);
				if (show) { regularEl.textContent = money(regular); }
			}
		}

		function updateQtyBadge() {
			var qtyEl = el(root, '[data-summary-qty]');
			if (qtyEl) { qtyEl.textContent = '×' + currentQty(); }
		}

		function updateLocalTotals() {
			var unitEl = el(root, '[data-summary-unit]');
			if (unitEl) { unitEl.textContent = money(state.unitPrice); }
			/* Sans devis serveur, sous-total et total suivent localement la
			   quantité (jamais de montant périmé ni de tiret vide). */
			if (state.quote) { return; }
			var local = money(state.unitPrice * currentQty());
			var subtotalEl = el(root, '[data-summary-subtotal]');
			if (subtotalEl) { subtotalEl.textContent = local; }
			var totalEl = el(root, '[data-summary-total]');
			if (totalEl) { totalEl.textContent = local; }
		}

		/* Placeholder téléphone adapté au pays de livraison. */
		function applyPhoneHint(countryCode) {
			var hint = PHONE_HINTS[countryCode] || PHONE_HINTS[icodFront.defaultCountry] || PHONE_HINTS.DZ;
			if (phoneInput) { phoneInput.setAttribute('placeholder', hint); }
		}

		/* Barre de progression : compte les champs réellement présents. */
		var progressFields = [];
		if (nameInput) { progressFields.push(function () { return nameInput.value.trim().length > 1; }); }
		if (phoneInput) { progressFields.push(function () { return phoneInput.value.replace(/\D/g, '').length >= 9; }); }
		if (wilayaSelect) { progressFields.push(function () { return !!wilayaSelect.value; }); }
		if (communeSelect) {
			progressFields.push(function () { return !!communeSelect.value || (communeText && !communeText.classList.contains('icod-hidden') && !!communeText.value.trim()); });
		}
		if (addressInput) { progressFields.push(function () { return !!addressInput.value.trim(); }); }

		function updateProgress() {
			var fill = el(root, '[data-progress-fill]');
			if (!fill || !progressFields.length) { return; }
			var done = 0;
			progressFields.forEach(function (check) { if (check()) { done++; } });
			fill.style.width = Math.round(done / progressFields.length * 100) + '%';
			fill.style.background = done >= progressFields.length ? 'var(--icod-success,#0e7a4f)' : '';
		}
		['input', 'change'].forEach(function (evt) {
			root.addEventListener(evt, updateProgress, { passive: true });
		});
		updateProgress();

		/* --- Variations : résolution de l'ID selon les attributs choisis --- */
		function currentAttributes() {
			var attrs = {};
			els(form, '.icod-attr').forEach(function (input) {
				var value = ('radio' === input.type || 'checkbox' === input.type) ? (input.checked ? input.value : '') : input.value;
				if (value) { attrs[input.getAttribute('data-taxonomy')] = value; }
			});
			return attrs;
		}

		function resolveVariation() {
			if (!state.variations.length) { return; }
			var chosen = currentAttributes();
			var keys = Object.keys(chosen);
			state.variationId = 0;
			state.unitPrice = parseFloat(root.getAttribute('data-unit-price')) || state.unitPrice;

			state.variations.forEach(function (variation) {
				var match = keys.every(function (key) {
					var v = variation.attributes[key];
					return v === '' || v === undefined || v === chosen[key] || v === String(chosen[key]);
				});
				if (match && keys.length) {
					state.variationId = variation.id;
					state.unitPrice = variation.price;
				}
			});
			updateHeadPrice();
			updateLocalTotals();
			refreshQuote();
		}

		els(form, '.icod-attr').forEach(function (select) {
			select.addEventListener('change', resolveVariation);
		});

		/* --- Cascade wilaya → communes --- */
		function loadCommunes(wilayaCode) {
			if (!communeSelect) { return; }
			/* Hors Algérie (code pays-région) : ville en saisie libre. */
			if (/^[A-Z]{2}-/.test(wilayaCode)) {
				setCommuneFree(true);
				return;
			}
			setCommuneFree(false);

			if (state.communesCache[wilayaCode]) {
				fillCommunes(state.communesCache[wilayaCode]);
				return;
			}
			communeSelect.disabled = true;
			communeSelect.innerHTML = '<option value="">' + I18N.loading + '</option>';

			api('communes?wilaya=' + encodeURIComponent(wilayaCode)).then(function (json) {
				var list = (json && json.communes) || [];
				state.communesCache[wilayaCode] = list;
				fillCommunes(list);
			});
		}

		function fillCommunes(list) {
			communeSelect.innerHTML = '';
			var placeholder = document.createElement('option');
			placeholder.value = '';
			placeholder.textContent = I18N.chooseCommune;
			communeSelect.appendChild(placeholder);

			list.forEach(function (commune) {
				var option = document.createElement('option');
				option.value = commune.fr;
				option.textContent = icodFront.rtl && commune.ar ? commune.ar : commune.fr;
				option.setAttribute('data-ar', commune.ar || '');
				communeSelect.appendChild(option);
			});
			communeSelect.disabled = false;
		}

		/* Commune libre : pays sans communes en base (hors Algérie). */
		var communeText = el(root, '.icod-commune-text');
		var communeFree = false;

		function setCommuneFree(free) {
			communeFree = free;
			if (!communeText || !communeSelect) { return; }
			communeSelect.classList.toggle('icod-hidden', free);
			communeText.classList.toggle('icod-hidden', !free);
			if (free) { communeSelect.disabled = true; }
		}

		/* Sélecteur de pays (multi-pays Premium) : filtre les wilayas. */
		var countrySelect = el(root, '.icod-country');
		if (countrySelect && wilayaSelect) {
			countrySelect.addEventListener('change', function () {
				var cc = countrySelect.value;
				els(wilayaSelect, 'option').forEach(function (option) {
					if (!option.value) { return; }
					var match = (option.getAttribute('data-country') || 'DZ') === cc;
					option.classList.toggle('icod-hidden', !match);
					option.hidden = !match;
				});
				wilayaSelect.value = '';
				if (communeSelect) {
					communeSelect.innerHTML = '<option value="">' + I18N.chooseCommune + '</option>';
					communeSelect.disabled = true;
				}
				setCommuneFree(false);
				applyPhoneHint(cc);
				refreshQuote();
			});

			/* Détection du pays du visiteur : fuseau horaire puis langue du
			   navigateur. Ne remplace jamais un choix déjà fait. */
			var detected = detectVisitorCountry();
			if (detected && detected !== countrySelect.value) {
				var matchOption = els(countrySelect, 'option').some(function (option) {
					return option.value === detected;
				});
				if (matchOption) {
					countrySelect.value = detected;
					if (typeof window.Event === 'function') {
						countrySelect.dispatchEvent(new window.Event('change', { bubbles: false }));
					}
				}
			}
			applyPhoneHint(countrySelect.value || icodFront.defaultCountry);
		} else {
			applyPhoneHint(icodFront.defaultCountry);
		}

		if (wilayaSelect) {
			wilayaSelect.addEventListener('change', function () {
				if (communeSelect) {
					communeSelect.innerHTML = '<option value="">' + I18N.chooseCommune + '</option>';
					communeSelect.disabled = true;
				}
				if (deskSelect) { deskSelect.innerHTML = '<option value="">' + I18N.chooseDesk + '</option>'; }
				if (wilayaSelect.value) { loadCommunes(wilayaSelect.value); }
				refreshQuote();
			});
		}

		if (communeSelect) {
			communeSelect.addEventListener('change', function () {
				refreshQuote();
				if (currentMode() === 'desk') { loadDesks(); }
			});
		}
		if (communeText) {
			communeText.addEventListener('change', refreshQuote);
		}

		function currentMode() {
			var radio = el(form, 'input[name="icod_mode"]:checked');
			return radio ? radio.value : 'home';
		}

		/* --- Bureaux stopdesk --- */
		function loadDesks() {
			if (!deskSelect || !wilayaSelect) { return; }
			var wilaya = wilayaSelect.value;
			if (!wilaya) { return; }
			deskSelect.innerHTML = '<option value="">' + I18N.loading + '</option>';

			api('stopdesks?wilaya=' + encodeURIComponent(wilaya)).then(function (json) {
				var list = (json && json.stopdesks) || [];
				deskSelect.innerHTML = '';
				var placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.textContent = list.length ? I18N.chooseDesk : I18N.noDesks;
				deskSelect.appendChild(placeholder);

				list.forEach(function (desk) {
					var option = document.createElement('option');
					option.value = desk.name;
					option.textContent = desk.name + (desk.commune ? ' — ' + desk.commune : '');
					deskSelect.appendChild(option);
				});
			});
		}

		els(form, '.icod-mode-radio').forEach(function (radio) {
			radio.addEventListener('change', function () {
				var isDesk = currentMode() === 'desk';
				if (deskWrap) { deskWrap.classList.toggle('icod-hidden', !isDesk); }
				if (isDesk) { loadDesks(); }
				refreshQuote();
			});
		});

		/* --- Quantité --- */
		function clampQty(value) {
			return Math.max(state.qtyMin, Math.min(state.qtyMax, value));
		}
		function onQtyChanged() {
			updateQtyBadge();
			/* Sans wilaya choisie, pas de devis serveur : le récap reste
			   cohérent localement (jamais de montant périmé affiché). */
			if (!wilayaSelect || !wilayaSelect.value) {
				state.quote = null;
				updateLocalTotals();
			}
			refreshQuote();
		}
		els(root, '.icod-qty-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!qtyInput) { return; }
				var step = parseInt(btn.getAttribute('data-step'), 10) || 1;
				qtyInput.value = clampQty((parseInt(qtyInput.value, 10) || state.qtyMin) + step);
				onQtyChanged();
			});
		});
		if (qtyInput) {
			qtyInput.addEventListener('change', function () {
				qtyInput.value = clampQty(parseInt(qtyInput.value, 10) || state.qtyMin);
				onQtyChanged();
			});
		}

		/* --- Note repliable + récapitulatif repliable --- */
		var noteToggle = el(root, '[data-note-toggle]');
		if (noteToggle) {
			noteToggle.addEventListener('click', function (e) {
				e.preventDefault();
				/* Le bloc entier (label + zone) se déplie : jamais de libellé vide. */
				var area = el(root, '.icod-note');
				var wrap = area ? (area.closest('.icod-field') || area) : null;
				if (wrap) { wrap.classList.toggle('icod-hidden'); if (!wrap.classList.contains('icod-hidden') && area) { area.focus(); } }
				noteToggle.classList.toggle('open');
			});
		}
		var sumHead = el(root, '[data-summary-toggle]');
		if (sumHead) {
			sumHead.addEventListener('click', function () {
				var body = sumHead.nextElementSibling;
				if (body) { body.classList.toggle('icod-hidden'); sumHead.classList.toggle('closed'); }
			});
		}

		/* --- Code promo : appliquer / retirer --- */
		var couponInput = el(root, '[data-icod-coupon-input]');
		var couponApply = el(root, '[data-icod-coupon-apply]');
		if (couponInput && couponApply) {
			var applyCoupon = function () {
				state.coupon = couponInput.value.trim();
				refreshQuote();
			};
			couponApply.addEventListener('click', applyCoupon);
			couponInput.addEventListener('keydown', function (event) {
				if (event.key === 'Enter') { event.preventDefault(); applyCoupon(); }
			});
		}

		/* --- Prix temps réel : toujours recalculé par le serveur --- */
		var refreshQuote = debounce(function () {
			var wilaya = wilayaSelect ? wilayaSelect.value : '';
			if (wilayaSelect && !wilaya) { return; }

			var payload = {
				product_id: state.productId,
				variation_id: state.variationId,
				quantity: currentQty(),
				wilaya: wilaya,
				commune: (communeSelect && !communeFree) ? communeSelect.value : (communeText ? communeText.value.trim() : ''),
				mode: currentMode(),
				coupon: state.coupon
			};

			api('quote', payload).then(function (json) {
				if (!json || !json.unit) { return; }
				state.quote = json;
				state.unitPrice = json.unit;
				updateHeadPrice();
				updateQtyBadge();

				var homeEl = el(form, '[data-price-home]');
				var deskEl = el(form, '[data-price-desk]');
				if (homeEl) { homeEl.textContent = json.free ? I18N.free : money(json.price_home); }
				if (deskEl) { deskEl.textContent = json.free ? I18N.free : money(json.price_desk); }

				var subtotalEl = el(root, '[data-summary-subtotal]');
				var shippingEl = el(root, '[data-summary-shipping]');
				var totalEl = el(root, '[data-summary-total]');
				var discountRow = el(root, '[data-summary-discount-row]');
				var discountEl = el(root, '[data-summary-discount]');
				var discountLabel = el(root, '[data-summary-discount-label]');

				if (subtotalEl) { subtotalEl.textContent = money(json.subtotal); }
				if (shippingEl) { shippingEl.textContent = (json.shipping < 0) ? '—' : (json.free ? I18N.free : money(json.shipping)); }
				if (totalEl) { totalEl.textContent = money(json.total); }

				/* Délai de livraison de la wilaya (Wilayas & Tarifs). */
				var estEl = el(root, '[data-delivery-estimate]');
				if (estEl) {
					var est = String(json.estimate || '').trim();
					if (est) { estEl.textContent = '⏱ ' + est; estEl.classList.remove('icod-hidden'); }
					else { estEl.classList.add('icod-hidden'); }
				}

				/* Commande minimum de la wilaya : avertissement immédiat
				   (la validation finale reste côté serveur). */
				var minEl = el(root, '[data-min-order-warn]');
				if (minEl) {
					var minAmt = parseFloat(json.min_order) || 0;
					if (minAmt > 0 && json.subtotal < minAmt) {
						minEl.textContent = (I18N.minOrder || 'Commande minimum : {min}.').replace('{min}', money(minAmt));
						minEl.classList.remove('icod-hidden');
					} else {
						minEl.classList.add('icod-hidden');
					}
				}

				if (discountRow && discountEl) {
					var show = json.discount > 0;
					discountRow.classList.toggle('icod-hidden', !show);
					if (show) {
						discountEl.textContent = '−' + money(json.discount);
						if (discountLabel && json.discount_pct) {
							discountLabel.textContent = 'Remise −' + json.discount_pct + '%';
						}
					}
				}

				// Code promo : ligne de remise + message de validation.
				var couponRow = el(root, '[data-summary-coupon-row]');
				var couponEl = el(root, '[data-summary-coupon]');
				var couponLabel = el(root, '[data-summary-coupon-label]');
				var couponMsg = el(root, '[data-coupon-msg]');
				if (couponRow && couponEl) {
					var info = json.coupon || {};
					var hasCode = !!state.coupon;
					var okCoupon = hasCode && info.valid;
					couponRow.classList.toggle('icod-hidden', !okCoupon);
					if (okCoupon) {
						if (couponLabel) { couponLabel.textContent = (I18N.coupon || 'Code promo') + ' ' + state.coupon.toUpperCase() + (info.label ? ' ' + info.label : ''); }
						couponEl.textContent = '−' + money(info.amount);
					}
					if (couponMsg) {
						if (hasCode && okCoupon) {
							couponMsg.textContent = '✓ ' + (I18N.couponOk || 'Code promo appliqué');
							couponMsg.className = 'icod-coupon-msg icod-coupon-ok';
						} else if (hasCode) {
							var reasons = {
								not_found: I18N.couponBad || 'Code promo invalide',
								expired: I18N.couponExpired || 'Code promo expiré',
								used_up: I18N.couponUsed || 'Code promo déjà utilisé',
								min_spend: I18N.couponMin || 'Montant minimum non atteint'
							};
							couponMsg.textContent = (info.error && reasons[info.error]) ? reasons[info.error] : (I18N.couponBad || 'Code promo invalide');
							couponMsg.className = 'icod-coupon-msg icod-coupon-ko';
							state.coupon = '';
						} else {
							couponMsg.className = 'icod-coupon-msg icod-hidden';
						}
					}
				}

				// Barre « Ajoutez encore X DA pour la livraison gratuite ».
				var freebar = el(root, '[data-icod-freebar]');
				if (freebar) {
					var remaining = parseFloat(json.free_remaining) || 0;
					if (remaining > 0 && I18N.freeBar) {
						freebar.classList.remove('icod-hidden');
						var textEl = freebar.querySelector('[data-icod-freebar-text]');
						var fillEl = freebar.querySelector('[data-icod-freebar-fill]');
						if (textEl) { textEl.textContent = I18N.freeBar.replace('{reste}', money(remaining)); }
						if (fillEl && I18N.freeTarget) { fillEl.style.width = Math.min(100, (json.subtotal / I18N.freeTarget) * 100) + '%'; }
					} else {
						freebar.classList.add('icod-hidden');
					}
				}

				updateSticky(json.total);
				trackAbandoned();
			});
		}, 250);

		/* --- Barre collante mobile --- */
		var sticky = null;
		if (root.getAttribute('data-sticky') === '1' && submitBtn) {
			sticky = document.createElement('div');
			sticky.className = 'icod-sticky';
			sticky.innerHTML =
				'<div class="icod-sticky-total"><span>' +
				escapeHtml(el(root, '.icod-summary-total span') ? el(root, '.icod-summary-total span').textContent : '') +
				'</span><strong data-icod-sticky-total>—</strong></div>' +
				'<button type="button" class="icod-submit" data-icod-sticky-btn>' + escapeHtml(submitBtn.textContent) + '</button>';
			root.appendChild(sticky);
			root.classList.add('has-sticky');

			sticky.addEventListener('click', function (event) {
				if (event.target.closest('[data-icod-sticky-btn]')) {
					event.preventDefault();
					submitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
					window.setTimeout(function () { submitBtn.click(); }, 450);
				}
			});

			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (entries) {
					var visible = entries[0].isIntersecting;
					sticky.classList.toggle('is-visible', !visible);
				}, { threshold: 0.6 }).observe(submitBtn);
			}
		}

		function updateSticky(total) {
			if (!sticky) { return; }
			var stickyTotal = el(sticky, '[data-icod-sticky-total]');
			if (stickyTotal) { stickyTotal.textContent = money(total); }
		}

		function escapeHtml(text) {
			var div = document.createElement('div');
			div.textContent = String(text || '');
			return div.innerHTML;
		}

		/* --- Validation --- */
		function markInvalid(input, invalid) {
			input.classList.toggle('icod-invalid', !!invalid);
		}

		/* Validation pilotée par les attributs data-req posés par le rendu
		   PHP (Checkout Builder) : un champ requis absent → erreur ; un champ
		   non requis présent mais mal formaté → erreur format. */
		function validate() {
			var errors = [];

			if (state.variations.length && !state.variationId) {
				errors.push({ field: el(form, '.icod-attr'), message: I18N.errorAttrs });
			}

			/* Champs data-req vides (hors selects gérés spécifiquement). */
			els(form, '[data-req="1"]').forEach(function (input) {
				var isEmpty;
				if ('checkbox' === input.type) {
					isEmpty = !input.checked;
				} else if (input === communeSelect) {
					isEmpty = !communeFree && !input.value;
				} else {
					isEmpty = !String(input.value || '').trim();
				}
				if (isEmpty && input !== wilayaSelect && input !== communeSelect) {
					errors.push({ field: input, message: requiredMessage(input) });
				}
			});

			/* Formats spécifiques (seulement si le champ existe). */
			if (nameInput && nameInput.value.trim()) {
				var name = nameInput.value.trim();
				if (name.length < 2 || !/^[\p{L}\s'\-]+$/u.test(name)) {
					errors.push({ field: nameInput, message: I18N.errorName });
				}
			}
			if (phoneInput && phoneInput.value.trim()) {
				if (!/^(\+?213|0)[567][0-9]{8}$/.test(phoneInput.value.replace(/[\s\-.]/g, ''))) {
					errors.push({ field: phoneInput, message: I18N.errorPhone });
				}
			}
			var emailInput = el(form, '[name="icod_email"]');
			if (emailInput && emailInput.value.trim() && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(emailInput.value.trim())) {
				errors.push({ field: emailInput, message: I18N.errorEmailFormat });
			}

			/* Sélecteurs géo requis mais vides. */
			if (wilayaSelect && '1' === wilayaSelect.getAttribute('data-req') && !wilayaSelect.value) {
				errors.push({ field: wilayaSelect, message: I18N.errorWilaya });
			}
			if (communeSelect && '1' === communeSelect.getAttribute('data-req')) {
				if (!communeFree && !communeSelect.value) {
					errors.push({ field: communeSelect, message: I18N.errorCommune });
				}
				if (communeFree && communeText && !communeText.value.trim()) {
					errors.push({ field: communeText, message: I18N.errorCommune });
				}
			}
			if (currentMode() === 'desk' && deskSelect && !deskSelect.value) {
				errors.push({ field: deskSelect, message: I18N.errorDesk });
			}

			var marked = [nameInput, phoneInput, wilayaSelect, communeSelect, addressInput, deskSelect];
			marked.forEach(function (input) { if (input) { markInvalid(input, false); } });
			els(form, '.icod-attr').forEach(function (s) { markInvalid(s, false); });
			els(form, '[data-req="1"]').forEach(function (input) { if (-1 === marked.indexOf(input)) { markInvalid(input, false); } });

			if (errors.length) {
				errors.forEach(function (error) { if (error.field) { markInvalid(error.field, true); } });
			}
			return errors;
		}

		function requiredMessage(input) {
			if (input === nameInput) { return I18N.errorName; }
			if (input === phoneInput) { return I18N.errorPhone; }
			if (input === addressInput) { return I18N.errorAddress || I18N.error; }
			if (input.name === 'icod_captcha') { return I18N.errorCaptcha || I18N.error; }
			return input.getAttribute('data-msg') || I18N.error;
		}

		function showMsg(text, kind) {
			if (!msgBox) { return; }
			msgBox.textContent = text;
			msgBox.className = 'icod-msg is-' + (kind || 'error');
			msgBox.classList.remove('icod-hidden');
			msgBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		function hideMsg() { if (msgBox) { msgBox.classList.add('icod-hidden'); } }

		if (nameInput && phoneInput) {
			[nameInput, phoneInput].forEach(function (input) {
				input.addEventListener('input', debounce(trackAbandoned, 900));
			});
		}

		/* --- Suivi paniers abandonnés (léger, anonyme tant que vide) --- */
		function trackAbandoned() {
			var wilaya = wilayaSelect ? wilayaSelect.value : '';
			var commune = communeSelect ? communeSelect.value : '';
			var phone = phoneInput ? phoneInput.value.trim() : '';
			var name = nameInput ? nameInput.value.trim() : '';

			if (!phone && !name) { return; }

			var fields = [name, phone, wilaya, commune].filter(Boolean).length;
			var progress = Math.round((fields / 4) * 100);

			api('abandoned', {
				product_id: state.productId,
				name: name,
				phone: phone,
				wilaya: wilaya,
				commune: commune,
				progress: progress,
				total: state.quote ? state.quote.total : 0
			}).catch(function () {});
		}

		/* --- Soumission --- */
		var waBtn = el(form, '[data-icod-wa]');

		function buildPayload(viaWhatsApp, captchaToken) {
			var arOption = (communeSelect && !communeFree) ? communeSelect.options[communeSelect.selectedIndex] : null;
			var payRadio = el(form, 'input[name="icod_payment"]:checked');
			var emailInput = el(form, '[name="icod_email"]');
			var captchaInput = el(form, '[name="icod_captcha"]');
			var capTokenInput = el(form, '[name="icod_cap_token"]');

			var payload = {
				product_id: state.productId,
				variation_id: state.variationId,
				quantity: currentQty(),
				name: nameInput ? nameInput.value.trim() : '',
				phone: phoneInput ? phoneInput.value.trim() : '',
				email: emailInput ? emailInput.value.trim() : '',
				address: addressInput ? addressInput.value.trim() : '',
				wilaya: wilayaSelect ? wilayaSelect.value : '',
				commune: (communeSelect && !communeFree) ? communeSelect.value : (communeText ? communeText.value.trim() : ''),
				commune_ar: arOption ? (arOption.getAttribute('data-ar') || '') : '',
				mode: currentMode(),
				coupon: state.coupon,
				cfields: (function () {
					var o = {};
					els(form, '[name^="cf_"]').forEach(function (input) {
						o[input.name.slice(3)] = 'checkbox' === input.type ? (input.checked ? '1' : '') : input.value;
					});
					return o;
				})(),
				stopdesk: currentMode() === 'desk' && deskSelect ? deskSelect.value : '',
				payment: payRadio ? payRadio.value : 'cod',
				via_whatsapp: viaWhatsApp ? 1 : 0,
				note: (el(form, '.icod-note') || { value: '' }).value.trim(),
				honeypot: (el(form, '.icod-hp') || { value: '' }).value,
				ts: (el(form, '[name="icod_ts"]') || { value: '' }).value,
				sig: (el(form, '[name="icod_sig"]') || { value: '' }).value,
				fingerprint: fpInput ? fpInput.value : '',
				icod_captcha: captchaToken || (captchaInput ? captchaInput.value : ''),
				icod_cap_token: capTokenInput ? capTokenInput.value : ''
			};
			return payload;
		}

		function doSubmit(viaWhatsApp) {
			hideMsg();

			var errors = validate();
			if (errors.length) {
				showMsg(errors[0].message, 'error');
				return;
			}

			submitBtn.disabled = true;
			var originalLabel = submitBtn.textContent;
			submitBtn.textContent = I18N.sending;
			if (waBtn) { waBtn.disabled = true; }

			function send(captchaToken) {
				api('submit', buildPayload(viaWhatsApp, captchaToken)).then(handleResponse).catch(function () {
					submitBtn.disabled = false;
					submitBtn.textContent = originalLabel;
					if (waBtn) { waBtn.disabled = false; }
					showMsg(I18N.error, 'error');
				});
			}

			function handleResponse(json) {
				submitBtn.disabled = false;
				submitBtn.textContent = originalLabel;
				if (waBtn) { waBtn.disabled = false; }

				if (!json) {
					showMsg(I18N.error, 'error');
					return;
				}
				if (!json.ok) {
					if (json.code === 'blocked') {
						showMsg(I18N.blocked, 'error');
					} else {
						showMsg(json.message || I18N.error, 'error');
					}
					return;
				}

				// Paiement en ligne : redirection vers le checkout Chargily.
				if (json.redirect) {
					submitBtn.textContent = '…';
					window.location.href = json.redirect;
					return;
				}

				var success = el(root, '[data-icod-success]');
				var successText = el(root, '[data-icod-success-text]');
				var successTitle = el(root, '[data-icod-success-title]');
				if (successText) {
					successText.textContent = I18N.successText.replace('{num}', json.order_id);
				}
				if (successTitle && I18N.successTitle) {
					successTitle.textContent = I18N.successTitle.replace('{num}', json.order_id);
				}

				// Récapitulatif détaillé dans l'écran de remerciement.
				var sdNum = el(root, '[data-sd-num]');
				if (sdNum) { sdNum.textContent = '#' + json.order_id; }
				var sdProduct = el(root, '[data-sd-product]');
				if (sdProduct) {
					var productName = '';
					var headTitle = el(root, '.icod-summary-product-name');
					if (headTitle) { productName = headTitle.textContent; }
					sdProduct.textContent = productName + ' ×' + currentQty();
				}
				var sdMode = el(root, '[data-sd-mode]');
				if (sdMode) {
					var deskName = currentMode() === 'desk' && deskSelect ? deskSelect.value : '';
					sdMode.textContent = (currentMode() === 'desk' ? '🏢 Bureau' : '🏠 Domicile') + (deskName ? ' — ' + deskName : '');
				}
				var sdTotal = el(root, '[data-sd-total]');
				if (sdTotal) { sdTotal.textContent = money(json.total || state.unitPrice * currentQty()); }

				// Pixels : événement Purchase (Meta/TikTok/Snapchat, dédupliqué avec la CAPI).
				if (window.icodFirePurchase) {
					window.icodFirePurchase(json.order_id, json.total, '', phoneInput ? phoneInput.value.trim() : '');
				}

				var card = form.closest('.icod-card');
				if (card) { card.classList.add('icod-hidden'); }
				if (success) {
					success.hidden = false;
					success.classList.remove('icod-hidden');
				}

				// Montant de la commande.
				var meta = el(root, '[data-icod-success-meta]');
				if (meta && json.total) {
					meta.hidden = false;
					meta.textContent = '#' + json.order_id + ' · ' + money(json.total);
				}

				// Upsell : cartes produits suggérées.
				var upsell = el(root, '[data-icod-upsell]');
				if (upsell && upsell.querySelector('.icod-upsell-item')) {
					upsell.classList.remove('icod-hidden');
					var restartBtn = el(root, '[data-icod-restart]');
					if (restartBtn) { restartBtn.classList.add('icod-hidden'); }
				}

				// Redirection personnalisée après commande.
				var redirectUrl = root.getAttribute('data-redirect');
				var redirectDelay = parseInt(root.getAttribute('data-redirect-delay'), 10) || 0;
				if (redirectUrl) {
					var note = el(root, '[data-icod-redirect-note]');
					if (note) {
						note.classList.remove('icod-hidden');
						note.textContent = I18N.redirecting.replace('{s}', redirectDelay);
					}
					window.setTimeout(function () { window.location.href = redirectUrl; }, redirectDelay * 1000);
				}

				if (sticky) { sticky.classList.remove('is-visible'); }
				if (success) { success.scrollIntoView({ behavior: 'smooth', block: 'center' }); }

				// Commande via WhatsApp : ouvrir la conversation pré-remplie.
				if (json.wa_url) {
					window.open(json.wa_url, '_blank');
				}

				var restart = el(root, '[data-icod-restart]');
				if (restart) {
					restart.addEventListener('click', function (e) {
						e.preventDefault();
						window.location.reload();
					});
				}
			}

			/* Captcha : reCAPTCHA v3 → token avant envoi ; math → valeur saisie. */
			if ('recaptcha_v3' === root.getAttribute('data-captcha') && root.getAttribute('data-recaptcha-key') && window.grecaptcha) {
				var siteKey = root.getAttribute('data-recaptcha-key');
				window.grecaptcha.ready(function () {
					window.grecaptcha.execute(siteKey, { action: 'icod_submit' }).then(send).catch(function () {
						send('');
					});
				});
			} else {
				send('');
			}
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			doSubmit(false);
		});

		if (waBtn) {
			waBtn.addEventListener('click', function () {
				doSubmit(true);
			});
		}

		initTimer(root, state.productId);

		refreshQuote();
		updateHeadPrice();
		updateQtyBadge();
		updateLocalTotals();
		updateProgress();
	}

	/* ---------- Démarrage ---------- */
	function boot() {
		els(document, '.icod-root').forEach(initForm);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
