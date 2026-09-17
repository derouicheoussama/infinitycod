/**
 * ∞ Infinity Coder — Scripts frontend (COD, navigation, compte à rebours).
 * Copyright © 2026 Derouiche Oussama — https://www.derouicheoussama.com
 */
(function () {
	'use strict';

	/* ------------------------------------------------------------
	 * Navigation mobile
	 * ---------------------------------------------------------- */
	var burger = document.getElementById('inf-burger');
	var nav = document.getElementById('inf-nav');
	if (burger && nav) {
		burger.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			burger.classList.toggle('is-open', open);
			burger.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* Ombre du header au défilement */
	var header = document.getElementById('inf-header');
	if (header) {
		var onScroll = function () {
			header.classList.toggle('is-stuck', window.scrollY > 8);
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	/* ------------------------------------------------------------
	 * Formulaire COD
	 * ---------------------------------------------------------- */
	function fmt(value) {
		return Number(value).toLocaleString('fr-FR').replace(/,/g, ' ');
	}

	document.querySelectorAll('.inf-cod').forEach(function (cod) {
		var form = cod.querySelector('.inf-cod__form');
		if (!form) {
			return;
		}
		var unitPrice = parseFloat(cod.getAttribute('data-unit-price')) || 0;
		var wilayaSel = form.querySelector('.inf-cod__wilaya');
		var qtyInput = form.querySelector('.inf-cod__qty');
		var feeEl = form.querySelector('.inf-cod__fee');
		var subEl = form.querySelector('.inf-cod__subtotal');
		var totalEl = form.querySelector('.inf-cod__total');
		var resultEl = form.querySelector('.inf-cod__result');
		var submitBtn = form.querySelector('.inf-cod__submit');
		var freeShip = (window.infData && parseInt(window.infData.freeShipping, 10)) || 0;
		var currency = window.infData ? window.infData.currency : 'DA';

		function getFee() {
			if (!wilayaSel || !wilayaSel.value) {
				return null;
			}
			var opt = wilayaSel.options[wilayaSel.selectedIndex];
			var delivery = form.querySelector('input[name="inf_delivery"]:checked');
			var fee = opt ? parseInt(delivery && delivery.value === 'desk' ? opt.getAttribute('data-desk') : opt.getAttribute('data-home'), 10) : 0;
			return isNaN(fee) ? 0 : fee;
		}

		function render() {
			var qty = qtyInput ? Math.max(1, parseInt(qtyInput.value, 10) || 1) : 1;
			var sub = unitPrice * qty;
			var fee = getFee();
			if (fee === null) {
				fee = 0;
			}
			if (feeEl) {
				feeEl.textContent = wilayaSel && wilayaSel.value
					? (freeShip > 0 && sub >= freeShip ? (window.infData.freeLabel || 'Livraison gratuite') : fmt(fee) + ' ' + currency)
					: '—';
			}
			var total = sub + (freeShip > 0 && sub >= freeShip ? 0 : fee);
			if (subEl) { subEl.textContent = fmt(sub) + ' ' + currency; }
			if (totalEl) { totalEl.textContent = fmt(total) + ' ' + currency; }
		}

		form.querySelectorAll('[data-qty]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var delta = parseInt(btn.getAttribute('data-qty'), 10);
				var val = Math.max(1, Math.min(99, (parseInt(qtyInput.value, 10) || 1) + delta));
				qtyInput.value = val;
				render();
			});
		});

		[wilayaSel, qtyInput].forEach(function (el) {
			if (el) { el.addEventListener('change', render); }
		});
		form.querySelectorAll('input[name="inf_delivery"]').forEach(function (radio) {
			radio.addEventListener('change', render);
		});
		render();

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			/* Validation légère côté client */
			form.querySelectorAll('.inf-field').forEach(function (f) { f.classList.remove('has-error'); });
			resultEl.classList.remove('is-error', 'is-success');
			resultEl.textContent = '';

			var data = new FormData(form);
			if (submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('is-loading'); }

			fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (res) { return res.json(); })
				.then(function (json) {
					var msg = json && json.data && json.data.message ? json.data.message : 'Erreur';
					if (json && json.success) {
						resultEl.classList.add('is-success');
						resultEl.innerHTML = msg + '<br><small>' + (json.data.total ? (window.infData.totalLabel || '') + ' : ' + json.data.total : '') + '</small>';
						form.querySelectorAll('input[type="text"], input[type="tel"], textarea').forEach(function (i) { i.value = ''; });
						qtyInput.value = 1;
						render();
					} else {
						resultEl.classList.add('is-error');
						resultEl.textContent = msg;
						if (json && json.data && json.data.errors) {
							Object.keys(json.data.errors).forEach(function (name) {
								var field = form.querySelector('[name="' + name + '"]');
								if (field) {
									var wrap = field.closest('.inf-field');
									if (wrap) {
										wrap.classList.add('has-error');
										var err = wrap.querySelector('.inf-field__error');
										if (err) { err.textContent = json.data.errors[name]; }
									}
								}
							});
						}
					}
				})
				.catch(function () {
					resultEl.classList.add('is-error');
					resultEl.textContent = window.infData && window.infData.errorLabel ? window.infData.errorLabel : 'Connexion impossible. Réessayez.';
				})
				.finally(function () {
					if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('is-loading'); }
					resultEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				});
		});
	});

	/* ------------------------------------------------------------
	 * Compte à rebours (Deal du jour — InfinityMarket)
	 * data-countdown="YYYY-MM-DDTHH:MM:SS" sur l'élément conteneur.
	 * ---------------------------------------------------------- */
	document.querySelectorAll('[data-countdown]').forEach(function (box) {
		var deadline = new Date(box.getAttribute('data-countdown').replace(' ', 'T')).getTime();
		if (isNaN(deadline)) { return; }
		var slots = {
			d: box.querySelector('[data-cd="d"]'),
			h: box.querySelector('[data-cd="h"]'),
			m: box.querySelector('[data-cd="m"]'),
			s: box.querySelector('[data-cd="s"]')
		};
		function pad(n) { return (n < 10 ? '0' : '') + n; }
		function tick() {
			var diff = deadline - Date.now();
			if (diff <= 0) {
				box.classList.add('is-expired');
				return;
			}
			var d = Math.floor(diff / 864e5);
			var h = Math.floor((diff % 864e5) / 36e5);
			var m = Math.floor((diff % 36e5) / 6e4);
			var s = Math.floor((diff % 6e4) / 1000);
			if (slots.d) { slots.d.textContent = pad(d); }
			if (slots.h) { slots.h.textContent = pad(h); }
			if (slots.m) { slots.m.textContent = pad(m); }
			if (slots.s) { slots.s.textContent = pad(s); }
			requestAnimationFrame(function () {});
			setTimeout(tick, 1000);
		}
		tick();
	});

	/* ------------------------------------------------------------
	 * Accordéon FAQ (fers <details> : flèche animée en CSS)
	 * ---------------------------------------------------------- */
	document.querySelectorAll('.inf-faq details').forEach(function (d) {
		d.addEventListener('toggle', function () {
			d.classList.toggle('is-open', d.open);
		});
	});

	/* ------------------------------------------------------------
	 * Slider d'accueil (v2 pro)
	 * ---------------------------------------------------------- */
	document.querySelectorAll('[data-slider]').forEach(function (slider) {
		var track = slider.querySelector('.inf-slider__track');
		var slides = slider.querySelectorAll('.inf-slide');
		var dotsBox = slider.querySelector('.inf-slider__dots');
		if (!track || slides.length < 2) { return; }

		var index = 0;
		var timer = null;

		function go(i) {
			index = (i + slides.length) % slides.length;
			track.style.transform = 'translateX(-' + index * 100 + '%)';
			if (dotsBox) {
				dotsBox.querySelectorAll('button').forEach(function (d, j) {
					d.classList.toggle('is-active', j === index);
				});
			}
		}
		function restart() {
			clearInterval(timer);
			timer = setInterval(function () { go(index + 1); }, 6000);
		}

		slides.forEach(function (_, i) {
			if (dotsBox) {
				var dot = document.createElement('button');
				dot.setAttribute('aria-label', 'Diapositive ' + (i + 1));
				dot.addEventListener('click', function () { go(i); restart(); });
				dotsBox.appendChild(dot);
			}
		});
		slider.querySelectorAll('.inf-slider__arrow').forEach(function (arrow) {
			arrow.addEventListener('click', function () {
				go(index + parseInt(arrow.getAttribute('data-dir'), 10));
				restart();
			});
		});
		slider.addEventListener('mouseenter', function () { clearInterval(timer); });
		slider.addEventListener('mouseleave', restart);

		go(0);
		restart();
	});

	/* ------------------------------------------------------------
	 * Mega menu « Tous les rayons »
	 * ---------------------------------------------------------- */
	document.querySelectorAll('.inf-mega').forEach(function (mega) {
		var btn = mega.querySelector('.inf-mega__btn');
		if (!btn) { return; }
		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			var open = mega.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		document.addEventListener('click', function (e) {
			if (!mega.contains(e.target)) {
				mega.classList.remove('is-open');
				btn.setAttribute('aria-expanded', 'false');
			}
		});
	});

	/* ------------------------------------------------------------
	 * Barre d'outils mobile
	 * ---------------------------------------------------------- */
	document.querySelectorAll('[data-inf-nav]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (burger) {
				burger.click();
			} else if (nav) {
				nav.classList.toggle('is-open');
			}
			var open = nav && nav.classList.contains('is-open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	});

	document.querySelectorAll('[data-inf-search]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = document.querySelector('.inf-search input[type="search"]');
			if (input) {
				window.scrollTo({ top: 0, behavior: 'smooth' });
				setTimeout(function () { input.focus(); }, 350);
			}
		});
	});

	/* Boutons « Commander » qui défilent vers le formulaire */
	document.querySelectorAll('a[href="#inf-cod"]').forEach(function (link) {
		link.addEventListener('click', function (e) {
			var target = document.getElementById('inf-cod');
			if (target) {
				e.preventDefault();
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	});
})();
