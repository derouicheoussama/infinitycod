/**
 * ∞ Infinity Coder — Tableau de bord (dashboard bleu).
 * Outils de la page Livraison : activation globale + tarifs appliqués à toutes les wilayas.
 * Copyright © 2026 Derouiche Oussama — https://www.derouicheoussama.com
 */
(function () {
	'use strict';

	/* Tout activer / désactiver */
	var bulkToggle = document.getElementById('inf-bulk-toggle');
	if (bulkToggle) {
		bulkToggle.addEventListener('click', function () {
			var boxes = document.querySelectorAll('.inf-wilaya-active');
			if (!boxes.length) { return; }
			var newState = !boxes[0].checked;
			boxes.forEach(function (b) { b.checked = newState; });
		});
	}

	/* Appliquer un tarif à toutes les wilayas */
	var bulkApply = document.getElementById('inf-bulk-apply');
	if (bulkApply) {
		bulkApply.addEventListener('click', function () {
			var home = document.getElementById('inf-bulk-home');
			var desk = document.getElementById('inf-bulk-desk');
			document.querySelectorAll('.inf-table--shipping tbody tr').forEach(function (row) {
				if (home && home.value !== '') { row.querySelector('input[name$="[home]"]').value = parseInt(home.value, 10) || 0; }
				if (desk && desk.value !== '') { row.querySelector('input[name$="[desk]"]').value = parseInt(desk.value, 10) || 0; }
			});
		});
	}

	/* Vérification de licence (AJAX) */
	var checkBtn = document.getElementById('inf-check-license');
	if (checkBtn && window.infAdminData) {
		checkBtn.addEventListener('click', function () {
			var spinner = document.getElementById('inf-license-spinner');
			var result = document.getElementById('inf-license-result');
			checkBtn.disabled = true;
			if (spinner) { spinner.classList.add('is-active'); }
			fetch(window.infAdminData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams({
					action: 'inf_check_license',
					nonce: window.infAdminData.nonce
				})
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					result.textContent = json && json.data && json.data.message ? json.data.message : '';
					result.classList.toggle('is-ok', !!(json && json.success));
					result.classList.toggle('is-ko', !(json && json.success));
				})
				.finally(function () {
					checkBtn.disabled = false;
					if (spinner) { spinner.classList.remove('is-active'); }
				});
		});
	}
})();
