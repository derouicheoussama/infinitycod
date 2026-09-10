/**
 * InfinityCod — Interface admin
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @license GPL-2.0-or-later
 * @link https://derouicheoussama.com
 */
/**
 * InfinityCod — JS admin : recherche live + sauvegarde AJAX des communes.
 * Vanilla JS, aucune dépendance.
 */
(function () {
	'use strict';

	if (typeof icodAdmin === 'undefined') {
		return;
	}

	/**
	 * Recherche live dans un tableau : masque les lignes ne correspondant pas.
	 */
	function bindTableSearch(searchId, rowsSelector) {
		var input = document.getElementById(searchId);
		if (!input) {
			return;
		}

		input.addEventListener('input', function () {
			var term = input.value.trim().toLowerCase();
			var rows = document.querySelectorAll(rowsSelector);

			rows.forEach(function (row) {
				var haystack = (row.getAttribute('data-search') || '').toLowerCase();
				row.style.display = (!term || haystack.indexOf(term) !== -1) ? '' : 'none';
			});
		});
	}

	bindTableSearch('icod-wilaya-search', '.icod-wilayas-table tbody tr');
	bindTableSearch('icod-commune-search', '.icod-communes-table tbody tr');

	/**
	 * Sauvegarde immédiate (AJAX) des overrides de commune dès modification.
	 */
	var communesWrap = document.getElementById('icod-communes-wrap');
	if (communesWrap) {
		communesWrap.addEventListener('change', function (event) {
			var field = event.target.closest('.icod-commune-input');
			if (!field) {
				return;
			}

			var tr = field.closest('tr');
			var body = new window.FormData();
			body.append('action', 'icod_save_commune');
			body.append('nonce', icodAdmin.nonce);
			body.append('commune_id', tr.getAttribute('data-id'));
			body.append('field', field.getAttribute('data-field'));
			body.append('value', field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value);

			window.fetch(icodAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (response) { return response.json(); })
				.then(function (json) {
					if (!json || !json.success) {
						window.alert(icodAdmin.i18n.error);
						return;
					}
					tr.classList.add('icod-row-saved');
					window.setTimeout(function () { tr.classList.remove('icod-row-saved'); }, 900);
				})
				.catch(function () { window.alert(icodAdmin.i18n.error); });
		});
	}
	/**
	 * Tableau des commandes : tout sélectionner, détail dépliable,
	 * changements de statut rapides, blacklist.
	 */
	var checkAll = document.getElementById('icod-check-all');
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			document.querySelectorAll('#icod-orders-form input[name="ids[]"]').forEach(function (box) {
				box.checked = checkAll.checked;
			});
		});
	}

	function post(action, body) {
		var data = new window.FormData();
		data.append('action', action);
		data.append('nonce', icodAdmin.nonce);
		Object.keys(body).forEach(function (key) { data.append(key, body[key]); });
		return window.fetch(icodAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); });
	}

	document.querySelectorAll('.icod-quick').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.confirm(icodAdmin.i18n.confirm)) { return; }
			btn.disabled = true;

			post('icod_order_status', {
				id: btn.getAttribute('data-id'),
				status: btn.getAttribute('data-status')
			}).then(function (json) {
				if (!json || !json.success) {
					window.alert(icodAdmin.i18n.error);
					btn.disabled = false;
					return;
				}
				window.location.reload();
			}).catch(function () {
				window.alert(icodAdmin.i18n.error);
				btn.disabled = false;
			});
		});
	});

	/* ===== Modale commande : détails complets + édition + tous les statuts ===== */

	var modal = document.getElementById('icod-order-modal');
	var modalBody = document.getElementById('icod-modal-body');
	var wilayas = window.icodWilayas || [];

	function escHtml(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	function moneyAdmin(n) {
		var v = Math.round((Number(n) || 0) * 100) / 100;
		var s;
		try {
			s = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 }).format(v);
		} catch (e) {
			s = String(v);
		}
		return s + ' ' + (icodAdmin.currencyLabel || 'DA');
	}

	function closeModal() {
		if (!modal) { return; }
		modal.hidden = true;
		document.body.classList.remove('icod-modal-open');
	}

	if (modal) {
		modal.querySelectorAll('[data-icod-modal-close]').forEach(function (el) {
			el.addEventListener('click', closeModal);
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { closeModal(); }
		});
	}

	function openOrderModal(o) {
		if (!modal || !modalBody) { return; }

		var wa = 'https://wa.me/213' + String(o.phone || '').replace(/^0/, '');
		var risk = parseInt(o.fraud_score, 10) || 0;
		var riskLabel = risk >= 60 ? 'Élevé (' + risk + ')' : (risk >= 25 ? 'Moyen (' + risk + ')' : 'Faible');
		var riskClass = risk >= 60 ? 'icod-risk-high' : (risk >= 25 ? 'icod-risk-mid' : 'icod-risk-low');
		var modeLabel = 'desk' === o.mode ? '🏢 Au bureau' : '🏠 À domicile';

		/* Sélecteur de statuts : tous les statuts sauf le courant. */
		var statusBtns = '';
		Object.keys(o.statuses || {}).forEach(function (key) {
			if (key === o.status) { return; }
			statusBtns += '<button type="button" class="button button-small icod-m-status" data-id="' + o.id + '" data-status="' + escHtml(key) + '">' +
				escHtml(o.statuses[key]) + '</button> ';
		});

		/* Options wilayas pour l'édition. */
		var wilayaOpts = '<option value="">' + escHtml(o.wilaya_name || '—') + '</option>';
		(wilayas || []).forEach(function (w) {
			var sel = (String(w.code) === String(o.wilaya)) ? ' selected' : '';
			wilayaOpts += '<option value="' + escHtml(w.code) + '"' + sel + '>' + escHtml(w.code + ' — ' + w.name) + '</option>';
		});

		modalBody.innerHTML =
			'<div class="icod-m-head">' +
				'<h2 id="icod-modal-title">Commande #' + o.id + ' <span class="icod-sub">· ' + escHtml(o.created_at) + '</span></h2>' +
				'<span class="icod-status icod-status-' + escHtml(o.status) + '">' + escHtml((o.statuses || {})[o.status] || o.status) + '</span>' +
			'</div>' +

			'<div class="icod-m-grid">' +
				'<section class="icod-m-card">' +
					'<h4>👤 Coordonnées</h4>' +
					'<p><strong>' + escHtml(o.name) + '</strong><br />' +
					'<a href="tel:' + escHtml(o.phone) + '">' + escHtml(o.phone) + '</a><br />' +
					'<a class="button button-small" href="' + wa + '" target="_blank" rel="noopener">💬 WhatsApp</a></p>' +
					((o.email) ? '<p class="icod-sub">✉️ ' + escHtml(o.email) + '</p>' : '') +
				'</section>' +

				'<section class="icod-m-card">' +
					'<h4>🗺️ Destination</h4>' +
					'<p>' + escHtml((o.wilaya ? o.wilaya + ' — ' : '') + (o.wilaya_name || '')) + '<br />' +
					'<span class="icod-sub">' + escHtml(o.commune) + ' · ' + modeLabel + '</span></p>' +
					((o.stopdesk) ? '<p class="icod-sub">🏢 ' + escHtml(o.stopdesk) + '</p>' : '') +
				'</section>' +

				'<section class="icod-m-card">' +
					'<h4>📦 Produit</h4>' +
					'<p>' + escHtml(o.product || '—') + '<br /><span class="icod-sub">Quantité : ×' + parseInt(o.qty, 10) + '</span></p>' +
					((parseInt(o.paid, 10)) ? '<p><span class="icod-status icod-status-confirmed">💳 Payé en ligne</span> <span class="icod-sub">' + escHtml(o.paid_at) + '</span></p>' : '') +
				'</section>' +

				'<section class="icod-m-card">' +
					'<h4>💰 Montants</h4>' +
					'<p class="icod-m-lines">' +
						'<span>Sous-total <b>' + moneyAdmin(o.subtotal) + '</b></span>' +
						((o.discount > 0) ? '<span>Remise <b>−' + moneyAdmin(o.discount) + '</b></span>' : '') +
						'<span>Livraison <b>' + moneyAdmin(o.shipping) + '</b></span>' +
						'<span class="icod-m-total">Total <b>' + moneyAdmin(o.total) + '</b></span>' +
					'</p>' +
				'</section>' +

				'<section class="icod-m-card">' +
					'<h4>🛡️ Risque & suivi</h4>' +
					'<p><span class="icod-risk ' + riskClass + '">' + escHtml(riskLabel) + '</span>' +
					((o.fraud_flags) ? ' <span class="icod-sub">⚠️ ' + escHtml(o.fraud_flags) + '</span>' : '') + '</p>' +
					'<p class="icod-sub">' +
						((o.wc_order_id) ? 'WC #' + o.wc_order_id + ' · ' : '') +
						((o.carrier) ? escHtml(o.carrier) + (o.tracking ? ' · ' + escHtml(o.tracking) : '') + (o.carrier_status ? ' · ' + escHtml(o.carrier_status) : '') : 'aucun transporteur') +
						((o.ip) ? '<br />IP : ' + escHtml(o.ip) : '') +
					'</p>' +
					((o.note) ? '<p class="icod-sub">📝 ' + escHtml(o.note) + '</p>' : '') +
				'</section>' +

				'<section class="icod-m-card">' +
					'<h4>⚡ Actions</h4>' +
					'<p class="icod-m-statuses">' + statusBtns + '</p>' +
					((o.edit_url) ? '<p><a class="button button-small" href="' + escHtml(o.edit_url) + '" target="_blank" rel="noopener">Voir dans WooCommerce ↗</a></p>' : '') +
				'</section>' +
			'</div>' +

			'<form class="icod-m-edit" id="icod-m-edit-form">' +
				'<h4>✎ ' + escHtml(icodAdmin.i18n.edit) + '</h4>' +
				'<div class="icod-m-edit-grid">' +
					'<label><span>Nom</span><input type="text" name="customer_name" value="' + escHtml(o.name) + '" required /></label>' +
					'<label><span>Téléphone</span><input type="text" name="phone" value="' + escHtml(o.phone) + '" dir="ltr" required /></label>' +
					'<label><span>Wilaya</span><select name="wilaya_code">' + wilayaOpts + '</select></label>' +
					'<label><span>Commune</span><input type="text" name="commune" value="' + escHtml(o.commune) + '" /></label>' +
					'<label><span>Mode</span><select name="delivery_mode">' +
						'<option value="home"' + ('home' === o.mode ? ' selected' : '') + '>À domicile</option>' +
						'<option value="desk"' + ('desk' === o.mode ? ' selected' : '') + '>Au bureau (stopdesk)</option>' +
					'</select></label>' +
					'<label><span>Bureau (si stopdesk)</span><input type="text" name="stopdesk" value="' + escHtml(o.stopdesk) + '" /></label>' +
					'<label><span>Quantité</span><input type="number" name="quantity" value="' + parseInt(o.qty, 10) + '" min="1" max="999" /></label>' +
					'<label class="icod-m-full"><span>Note</span><textarea name="note" rows="2">' + escHtml(o.note) + '</textarea></label>' +
				'</div>' +
				'<p class="icod-m-actions">' +
					'<button type="submit" class="button button-primary">' + escHtml(icodAdmin.i18n.save) + '</button> ' +
					'<button type="button" class="button" data-icod-modal-close>Fermer</button>' +
				'</p>' +
			'</form>';

		modal.hidden = false;
		document.body.classList.add('icod-modal-open');

		/* Actions statut dans la modale. */
		modalBody.querySelectorAll('.icod-m-status').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!window.confirm(icodAdmin.i18n.confirm)) { return; }
				btn.disabled = true;
				post('icod_order_status', { id: btn.getAttribute('data-id'), status: btn.getAttribute('data-status') })
					.then(function (json) {
						if (!json || !json.success) { window.alert(icodAdmin.i18n.error); btn.disabled = false; return; }
						window.location.reload();
					}).catch(function () { window.alert(icodAdmin.i18n.error); btn.disabled = false; });
			});
		});

		/* Enregistrement de l'édition. */
		var editForm = document.getElementById('icod-m-edit-form');
		if (editForm) {
			editForm.addEventListener('submit', function (event) {
				event.preventDefault();
				var submitBtn = editForm.querySelector('button[type="submit"]');
				submitBtn.disabled = true;
				submitBtn.textContent = icodAdmin.i18n.saving;

				var body = { id: o.id };
				editForm.querySelectorAll('input, select, textarea').forEach(function (input) {
					if (input.name) { body[input.name] = input.value; }
				});

				post('icod_order_update', body).then(function (json) {
					if (!json || !json.success) {
						window.alert(icodAdmin.i18n.error);
						submitBtn.disabled = false;
						submitBtn.textContent = icodAdmin.i18n.save;
						return;
					}
					window.location.reload();
				}).catch(function () {
					window.alert(icodAdmin.i18n.error);
					submitBtn.disabled = false;
					submitBtn.textContent = icodAdmin.i18n.save;
				});
			});
		}
	}

	document.querySelectorAll('.icod-open').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var payload = btn.getAttribute('data-order');
			var order = null;
			try { order = JSON.parse(payload); } catch (e) { order = null; }
			if (order) { openOrderModal(order); }
		});
	});

	document.querySelectorAll('.icod-bl').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.confirm(icodAdmin.i18n.confirm)) { return; }
			btn.disabled = true;

			post('icod_order_blacklist', { phone: btn.getAttribute('data-phone') })
				.then(function (json) {
					if (!json || !json.success) {
						window.alert(icodAdmin.i18n.error);
						btn.disabled = false;
						return;
					}
					btn.textContent = '✓ ' + icodAdmin.i18n.saved;
				}).catch(function () {
					window.alert(icodAdmin.i18n.error);
					btn.disabled = false;
				});
		});
	});
	/* ===== Formulaire : synchronise la couleur d'accent avec le thème choisi ===== */

	document.querySelectorAll('.icod-preset input').forEach(function (radio) {
		radio.addEventListener('change', function () {
			var accent = document.querySelector('input[name="icod[accent_color]"]');
			if (accent && radio.getAttribute('data-accent')) {
				accent.value = radio.getAttribute('data-accent');
			}
		});
	});

	/* ===== Transporteurs ===== */

	// Test de connexion : envoie les champs saisis sans les enregistrer.
	document.querySelectorAll('.icod-test').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var card = btn.closest('.icod-carrier-card');
			var resultEl = document.querySelector('[data-result="' + btn.getAttribute('data-code') + '"]');
			btn.disabled = true;
			resultEl.textContent = '⏳';

			var body = { action: 'icod_carrier_test', nonce: icodAdmin.nonce, code: btn.getAttribute('data-code') };
			card.querySelectorAll('input').forEach(function (input) {
				if (input.name && input.type !== 'checkbox') {
					body[input.name.split('[').pop().replace(']', '')] = input.value;
				}
			});

			window.fetch(icodAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new window.URLSearchParams(body).toString()
			}).then(function (res) { return res.json(); }).then(function (json) {
				btn.disabled = false;
				var payload = (json && json.data) || {};
				resultEl.textContent = (json && json.success) ? ('✅ ' + payload.message) : ('❌ ' + payload.message);
			}).catch(function () {
				btn.disabled = false;
				resultEl.textContent = '❌ ' + icodAdmin.i18n.error;
			});
		});
	});

	// Import bureaux Yalidine.
	document.querySelectorAll('.icod-import-offices').forEach(function (btn) {
		btn.addEventListener('click', function () {
			btn.disabled = true;
			btn.textContent = '⏳ ' + icodAdmin.i18n.loading;

			post('icod_import_offices', {}).then(function (json) {
				btn.disabled = false;
				var payload = (json && json.data) || {};
				btn.textContent = (json && json.success) ? '✅ ' + payload.message : '❌ ' + payload.message;
			}).catch(function () {
				btn.disabled = false;
				btn.textContent = '❌ ' + icodAdmin.i18n.error;
			});
		});
	});

	// Création de colis.
	document.querySelectorAll('.icod-ship-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var cell = btn.closest('.icod-ship-cell');
			var select = cell ? cell.querySelector('.icod-ship-carrier') : null;
			var carrier = select ? select.value : '';
			if (!carrier) { return; }
			btn.disabled = true;

			post('icod_parcel_create', { id: btn.getAttribute('data-id'), carrier: carrier }).then(function (json) {
				var payload = (json && json.data) || {};
				if (json && json.success) {
					var tr = btn.closest('tr');
					tr.style.opacity = '.5';
					btn.textContent = '✅ ' + payload.tracking;
				} else {
					btn.disabled = false;
					window.alert(payload.message || icodAdmin.i18n.error);
				}
			}).catch(function () {
				btn.disabled = false;
				window.alert(icodAdmin.i18n.error);
			});
		});
	});

	// Synchronisation manuelle des suivis.
	var syncBtn = document.querySelector('.icod-sync-now');
	if (syncBtn) {
		syncBtn.addEventListener('click', function () {
			syncBtn.disabled = true;

			post('icod_sync_tracking', {}).then(function (json) {
				syncBtn.disabled = false;
				var payload = (json && json.data) || {};
				if (json && json.success) {
					window.location.reload();
				} else {
					window.alert(icodAdmin.i18n.error);
				}
			}).catch(function () {
				syncBtn.disabled = false;
				window.alert(icodAdmin.i18n.error);
			});
		});
	}
})();

/* ===== Anti double-soumission : boutons principaux désactivés au POST ===== */
document.querySelectorAll('form[action*="admin-post.php"]').forEach(function (form) {
	form.addEventListener('submit', function () {
		var button = form.querySelector('.button-primary, .button-hero');
		if (button && !button.disabled) {
			window.setTimeout(function () {
				button.disabled = true;
				button.classList.add('icod-saving');
			}, 0);
		}
	});
});

/* ===== Wilayas : filtre instantané ===== */
document.querySelectorAll('#icod-wilaya-search, #icod-commune-search').forEach(function (input) {
	input.addEventListener('input', function () {
		var q = input.value.toLowerCase();
		document.querySelectorAll('tr[data-search]').forEach(function (tr) {
			tr.style.display = tr.getAttribute('data-search').toLowerCase().indexOf(q) !== -1 ? '' : 'none';
		});
	});
});
