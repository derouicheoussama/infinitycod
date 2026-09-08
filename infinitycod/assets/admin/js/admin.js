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
})();
