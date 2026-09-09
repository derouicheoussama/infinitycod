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
/* --- Sidebar premium --- */
.app{display:grid;grid-template-columns:244px 1fr;min-height:100vh}
.side{background:var(--navy);color:#cfe0f2;padding:20px 0 12px;position:sticky;top:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column}
.side .brand{padding:0 20px 16px;font-weight:800;color:#fff;display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:10px}
.side .brand .mark{font-size:26px;background:linear-gradient(135deg,#4da3e8,#1877c2);-webkit-background-clip:text;background-clip:text;color:transparent;width:38px;height:38px;border-radius:11px;background-color:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center}
.side .brand strong{display:block;font-size:15px;line-height:1.1}
.side .brand small{display:block;font-size:10.5px;font-weight:600;color:#7f9ab8;letter-spacing:.05em}
.side ul{list-style:none;margin:0;padding:0;flex:1}
.side li.sep{padding:14px 20px 5px;font-size:10px;letter-spacing:.14em;color:#6f8aa8;text-transform:uppercase;font-weight:700}
.side li a{display:flex;align-items:center;gap:9px;margin:1px 10px;padding:8px 12px;color:#c3d6ea;font-weight:600;font-size:13.5px;border-radius:8px;position:relative;transition:background .15s,color .15s}
.side li a:hover{background:rgba(255,255,255,.07);color:#fff;text-decoration:none}
.side li a.on{background:linear-gradient(90deg,var(--blue),#2a86cf);color:#fff;box-shadow:0 3px 10px rgba(24,119,194,.35)}
.side li a.on::before{content:'';position:absolute;left:-10px;top:6px;bottom:6px;width:3px;border-radius:99px;background:#fff}
.side-foot{padding:10px 20px 0;border-top:1px solid rgba(255,255,255,.08)}
.theme-btn{margin-left:auto;background:none;border:0;cursor:pointer;font-size:15px;opacity:.8}
.theme-btn:hover{opacity:1}

/* --- Topbar --- */
.main{padding:20px 26px}
.top{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.headings h1{margin:0;font-size:20px}
.crumbs{font-size:12px;color:var(--muted);margin-top:2px}
.top-actions{display:flex;gap:8px;align-items:center}
.gsearch input{min-width:260px}

/* --- Bell dropdown --- */
.belldd{position:relative}
.bell{list-style:none;text-decoration:none!important;font-size:17px;cursor:pointer;padding:6px 8px;border-radius:8px;border:1.5px solid var(--line)}
.bell:hover{background:rgba(24,119,194,.08)}
.bell .cnt{position:absolute;top:-6px;right:-7px;background:var(--bad);color:#fff;font-size:10px;font-weight:800;padding:1px 5px;border-radius:99px}
.belldd .dd{position:absolute;right:0;top:calc(100% + 8px);width:320px;max-height:380px;overflow-y:auto;background:var(--card);border:1px solid var(--line);border-radius:12px;box-shadow:0 16px 44px rgba(10,30,50,.18);z-index:60;padding:6px}
.dd-h{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:8px 10px;border-bottom:1px solid var(--line)}
.nd{padding:9px 10px;font-size:12.5px;border-bottom:1px solid var(--line);color:var(--ink)}
.nd.un{background:rgba(24,119,194,.07);font-weight:600}
.nd time{display:block;font-size:10.5px;color:var(--muted);margin-top:2px}
.nd.empty{color:var(--muted);text-align:center}
.belldd a{display:block;text-align:center;padding:7px;font-size:12px;font-weight:700}
.belldd[open] summary{background:rgba(24,119,194,.08)}

/* --- Sidebar mobile --- */
.burger{display:none;background:none;border:1.5px solid var(--line);border-radius:8px;padding:7px 10px;cursor:pointer;font-size:15px;color:var(--ink)}
.side-toggle-cell{display:none}
#side-overlay{display:none;position:fixed;inset:0;background:rgba(6,18,32,.5);z-index:40}
@media (max-width:900px){
	.burger{display:inline-block}
	.side-toggle-cell{display:block}
	.app{grid-template-columns:1fr}
	.side{position:fixed;left:0;top:0;bottom:0;z-index:50;width:252px;transform:translateX(-105%);transition:transform .22s ease;height:100vh}
	body.side-open .side{transform:none}
	body.side-open #side-overlay{display:block}
	.main{padding:14px}
	.top h1{font-size:18px}
	.gsearch input{min-width:0;width:140px}
	.tbl th,.tbl td{padding:8px}
	.btn{min-height:38px}
	.grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
	.kpi{padding:12px}
	.kpi-ico{width:36px;height:36px;font-size:16px}
}
@media (max-width:520px){
	.top form{flex:1}
	.card{padding:13px}
}
`;

export const ASSET_ETAGS = {};
export function assetEtag(name, content) {
	if (!ASSET_ETAGS[name]) ASSET_ETAGS[name] = '"' + crypto.createHash('sha1').update(content).digest('hex').slice(0, 16) + '"';
	return ASSET_ETAGS[name];
}
export function adminCss() { return CSS + ADMIN_CSS; }
