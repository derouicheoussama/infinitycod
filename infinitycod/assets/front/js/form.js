/**
 * InfinityCod — Formulaire COD front (vanilla JS, ~14 Ko).
 * Cascade wilaya→commune→bureau, prix temps réel (serveur), anti-fraude passif,
 * barre collante mobile, suivi des paniers abandonnés.
 */
(function () {
	'use strict';

	if (typeof icodFront === 'undefined') { return; }

	var I18N = icodFront.i18n;

	/* ---------- Utilitaires ---------- */

	function el(root, selector) { return root.querySelector(selector); }
	function els(root, selector) { return Array.prototype.slice.call(root.querySelectorAll(selector)); }

	function money(amount) {
		var n = Math.round((Number(amount) || 0) * 100) / 100;
		var formatted;
		try {
			formatted = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }).format(n);
		} catch (e) {
			formatted = String(n);
		}
		return formatted + ' ' + I18N.da;
	}

	function debounce(fn, delay) {
		var timer = null;
		return function () {
			var args = arguments, ctx = this;
			clearTimeout(timer);
			timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
		};
	}

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

	/* ---------- Formulaire ---------- */

	function initForm(root) {
		var form = el(root, '.icod-form');
		if (!form) { return; }

		var state = {
			productId: parseInt(root.getAttribute('data-product'), 10) || 0,
			variations: JSON.parse(root.getAttribute('data-variations') || '[]'),
			unitPrice: parseFloat(root.getAttribute('data-unit-price')) || 0,
			qtyMax: parseInt(root.getAttribute('data-qty-max'), 10) || 20,
			variationId: 0,
			quote: null,
			communesCache: {}
		};

		var nameInput = el(form, '[data-icod-field="name"]');
		var phoneInput = el(form, '[data-icod-field="phone"]');
		var wilayaSelect = el(form, '.icod-wilaya');
		var communeSelect = el(form, '.icod-commune');
		var deskSelect = el(form, '.icod-desk');
		var deskWrap = el(form, '.icod-stopdesk-wrap');
		var qtyInput = el(root, '.icod-qty-input');
		var msgBox = el(root, '[data-icod-msg]');
		var submitBtn = el(form, '.icod-submit');
		var fpInput = el(form, '.icod-fp');

		fpInput.value = fingerprint();

		/* --- Variations : résolution de l'ID selon les attributs choisis --- */
		function currentAttributes() {
			var attrs = {};
			els(form, '.icod-attr').forEach(function (select) {
				if (select.value) { attrs[select.getAttribute('data-taxonomy')] = select.value; }
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
			refreshQuote();
		}

		els(form, '.icod-attr').forEach(function (select) {
			select.addEventListener('change', resolveVariation);
		});

		/* --- Cascade wilaya → communes --- */
		function loadCommunes(wilayaCode) {
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

		wilayaSelect.addEventListener('change', function () {
			communeSelect.innerHTML = '<option value="">' + I18N.chooseCommune + '</option>';
			communeSelect.disabled = true;
			deskSelect.innerHTML = '<option value="">' + I18N.chooseDesk + '</option>';
			if (wilayaSelect.value) { loadCommunes(wilayaSelect.value); }
			refreshQuote();
		});

		communeSelect.addEventListener('change', function () {
			refreshQuote();
			if (currentMode() === 'desk') { loadDesks(); }
		});

		function currentMode() {
			var radio = el(form, 'input[name="icod_mode"]:checked');
			return radio ? radio.value : 'home';
		}

		/* --- Bureaux stopdesk --- */
		function loadDesks() {
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
				deskWrap.classList.toggle('icod-hidden', !isDesk);
				if (isDesk) { loadDesks(); }
				refreshQuote();
			});
		});

		/* --- Quantité --- */
		els(root, '.icod-qty-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var step = parseInt(btn.getAttribute('data-step'), 10) || 1;
				var value = (parseInt(qtyInput.value, 10) || 1) + step;
				value = Math.max(1, Math.min(state.qtyMax, value));
				qtyInput.value = value;
				refreshQuote();
			});
		});
		if (qtyInput) {
			qtyInput.addEventListener('change', function () {
				var value = parseInt(qtyInput.value, 10) || 1;
				qtyInput.value = Math.max(1, Math.min(state.qtyMax, value));
				refreshQuote();
			});
		}

		/* --- Prix temps réel : toujours recalculé par le serveur --- */
		var refreshQuote = debounce(function () {
			var wilaya = wilayaSelect.value;
			if (!wilaya) { return; }

			var payload = {
				product_id: state.productId,
				variation_id: state.variationId,
				quantity: qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1,
				wilaya: wilaya,
				commune: communeSelect.value || '',
				mode: currentMode()
			};

			api('quote', payload).then(function (json) {
				if (!json || !json.unit) { return; }
				state.quote = json;
				state.unitPrice = json.unit;

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
				if (shippingEl) { shippingEl.textContent = json.free ? I18N.free : money(json.shipping); }
				if (totalEl) { totalEl.textContent = money(json.total); }

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

				updateSticky(json.total);
				trackAbandoned();
			});
		}, 250);

		/* --- Barre collante mobile --- */
		var sticky = null;
		if (root.getAttribute('data-sticky') === '1') {
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

		function validate() {
			var errors = [];

			if (state.variations.length && !state.variationId) {
				errors.push({ field: els(form, '.icod-attr')[0], message: I18N.errorAttrs });
			}
			var name = nameInput.value.trim();
			if (name.length < 2 || !/^[\p{L}\s'\-]+$/u.test(name)) {
				errors.push({ field: nameInput, message: I18N.errorName });
			}
			if (!/^(\+?213|0)[567][0-9]{8}$/.test(phoneInput.value.replace(/[\s\-\.]/g, ''))) {
				errors.push({ field: phoneInput, message: I18N.errorPhone });
			}
			if (!wilayaSelect.value) {
				errors.push({ field: wilayaSelect, message: I18N.errorWilaya });
			}
			if (!communeSelect.value || communeSelect.disabled) {
				errors.push({ field: communeSelect, message: I18N.errorCommune });
			}
			if (currentMode() === 'desk' && !deskSelect.value) {
				errors.push({ field: deskSelect, message: I18N.errorDesk });
			}

			[nameInput, phoneInput, wilayaSelect, communeSelect, deskSelect].forEach(function (input) {
				if (input) { markInvalid(input, false); }
			});
			els(form, '.icod-attr').forEach(function (s) { markInvalid(s, false); });

			if (errors.length) {
				errors.forEach(function (error) { if (error.field) { markInvalid(error.field, true); } });
			}
			return errors;
		}

		function showMsg(text, kind) {
			msgBox.textContent = text;
			msgBox.className = 'icod-msg is-' + (kind || 'error');
			msgBox.classList.remove('icod-hidden');
			msgBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		function hideMsg() { msgBox.classList.add('icod-hidden'); }

		[nameInput, phoneInput].forEach(function (input) {
			input.addEventListener('input', debounce(trackAbandoned, 900));
		});

		/* --- Suivi paniers abandonnés (léger, anonyme tant que vide) --- */
		function trackAbandoned() {
			var wilaya = wilayaSelect.value;
			var commune = communeSelect.value;
			var phone = phoneInput.value.trim();
			var name = nameInput.value.trim();

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
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			hideMsg();

			var errors = validate();
			if (errors.length) {
				showMsg(errors[0].message, 'error');
				return;
			}

			submitBtn.disabled = true;
			var originalLabel = submitBtn.textContent;
			submitBtn.textContent = I18N.sending;

			var arOption = communeSelect.options[communeSelect.selectedIndex];
			var communeFr = communeSelect.value;
			var communeAr = arOption ? (arOption.getAttribute('data-ar') || '') : '';

			var payRadio = el(form, 'input[name="icod_payment"]:checked');

			api('submit', {
				product_id: state.productId,
				variation_id: state.variationId,
				quantity: qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1,
				name: nameInput.value.trim(),
				phone: phoneInput.value.trim(),
				wilaya: wilayaSelect.value,
				commune: communeFr,
				commune_ar: communeAr,
				mode: currentMode(),
				stopdesk: currentMode() === 'desk' ? deskSelect.value : '',
				payment: payRadio ? payRadio.value : 'cod',
				note: (el(form, '.icod-note') || { value: '' }).value.trim(),
				honeypot: el(form, '.icod-hp').value,
				ts: el(form, '[name="icod_ts"]').value,
				sig: el(form, '[name="icod_sig"]').value,
				fingerprint: fpInput.value
			}).then(function (json) {
				submitBtn.disabled = false;
				submitBtn.textContent = originalLabel;

				if (!json) {
					showMsg(I18N.error, 'error');
					return;
				}
				if (!json.ok) {
					if (json.code === 'blocked') {
						showMsg(I18N.blocked, 'error');
					} else {
						showMsg(json.message || I18N.error, 'error');
						submitBtn.disabled = false;   // le bouton reste actif pour réessayer
						submitBtn.textContent = originalLabel;
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
				form.closest('.icod-card').classList.add('icod-hidden');
				success.hidden = false;
				success.classList.remove('icod-hidden');
				if (sticky) { sticky.classList.remove('is-visible'); }
				success.scrollIntoView({ behavior: 'smooth', block: 'center' });

				var restart = el(root, '[data-icod-restart]');
				if (restart) {
					restart.addEventListener('click', function (e) {
						e.preventDefault();
						window.location.reload();
					});
				}
			}).catch(function () {
				submitBtn.disabled = false;
				submitBtn.textContent = originalLabel;
				showMsg(I18N.error, 'error');
			});
		});

		refreshQuote();
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
