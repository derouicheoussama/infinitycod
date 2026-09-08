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

	document.querySelectorAll('.icod-detail-toggle').forEach(function (toggle) {
		toggle.addEventListener('click', function (event) {
			event.preventDefault();
			var detailRow = toggle.closest('tr').nextElementSibling;
			var open = !detailRow.classList.contains('icod-hidden');
			detailRow.classList.toggle('icod-hidden', open);
			toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
		});
	});

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
