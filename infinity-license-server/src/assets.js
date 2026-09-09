import crypto from 'node:crypto';
import { CSS } from './views.js';

/** JS statique du dashboard : confirmations, sidebar mobile, aucun inline. */
export const ADMIN_JS = `
(function () {
	'use strict';
	// Confirmations déclaratives (remplace onclick inline, compatible CSP stricte).
	document.addEventListener('submit', function (e) {
		var btn = e.target.querySelector('[data-confirm]');
		if (btn && !window.confirm(btn.getAttribute('data-confirm'))) { e.preventDefault(); }
	}, true);

	// Sidebar mobile.
	var toggle = document.getElementById('side-toggle');
	if (toggle) {
		toggle.addEventListener('click', function () {
			document.body.classList.toggle('side-open');
		});
	}
	var overlay = document.getElementById('side-overlay');
	if (overlay) {
		overlay.addEventListener('click', function () {
			document.body.classList.remove('side-open');
		});
	}

	// Dark mode persistant.
	var themeBtn = document.getElementById('theme-toggle');
	if (themeBtn) {
		var apply = function (t) {
			document.documentElement.setAttribute('data-theme', t);
			try { localStorage.setItem('ils-theme', t); } catch (e) {}
		};
		var saved = 'light';
		try { saved = localStorage.getItem('ils-theme') || 'light'; } catch (e) {}
		apply(saved);
		themeBtn.addEventListener('click', function () {
			apply(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
		});
	}
})();
`;

/** CSS additionnel spécifique au shell admin (responsive sidebar, topbar, bell). */
export const ADMIN_CSS = `
.burger{display:none;background:none;border:1.5px solid var(--line);border-radius:8px;padding:7px 10px;cursor:pointer;font-size:15px;color:var(--ink)}
.bell{position:relative;text-decoration:none!important;font-size:17px;line-height:1}
.bell .cnt{position:absolute;top:-7px;right:-9px;background:var(--bad);color:#fff;font-size:10px;font-weight:800;padding:1px 5px;border-radius:99px}
.side-toggle-cell{display:none}
#side-overlay{display:none;position:fixed;inset:0;background:rgba(6,18,32,.5);z-index:40}
@media (max-width:900px){
	.burger{display:inline-block}
	.side-toggle-cell{display:block}
	.app{grid-template-columns:1fr}
	.side{position:fixed;left:0;top:0;bottom:0;z-index:50;width:250px;transform:translateX(-105%);transition:transform .22s ease;height:100vh}
	body.side-open .side{transform:none}
	body.side-open #side-overlay{display:block}
	.main{padding:14px}
	.top h1{font-size:18px}
	.top input[name=q]{min-width:0;width:150px}
	.tbl th,.tbl td{padding:8px}
	.btn{min-height:38px}
	.grid{grid-template-columns:repeat(auto-fit,minmax(160px,1fr))}
}
@media (max-width:520px){
	.top form{flex:1}
	.top input[name=q]{flex:1;width:auto}
	.card{padding:14px}
}
`;

export const ASSET_ETAGS = {};
export function assetEtag(name, content) {
	if (!ASSET_ETAGS[name]) ASSET_ETAGS[name] = '"' + crypto.createHash('sha1').update(content).digest('hex').slice(0, 16) + '"';
	return ASSET_ETAGS[name];
}
export function adminCss() { return CSS + ADMIN_CSS; }
