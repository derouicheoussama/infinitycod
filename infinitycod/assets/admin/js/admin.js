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
})();
