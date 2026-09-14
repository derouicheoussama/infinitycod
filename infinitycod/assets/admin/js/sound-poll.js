/* InfinityCod : poll des nouvelles commandes COD - bip + toast (admin). */
(function () {
	if (typeof icodPoll === 'undefined') { return; }
	var lastPending = null;
	function beep() {
		try {
			var Ctx = window.AudioContext || window.webkitAudioContext;
			if (!Ctx) { return; }
			var ctx = new Ctx();
			var osc = ctx.createOscillator();
			var gain = ctx.createGain();
			osc.connect(gain); gain.connect(ctx.destination);
			osc.type = 'sine'; osc.frequency.value = 880;
			gain.gain.setValueAtTime(0.25, ctx.currentTime);
			gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
			osc.start(); osc.stop(ctx.currentTime + 0.5);
		} catch (e) {}
	}
	function toast(txt) {
		var box = document.createElement('div');
		box.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:99999;background:#0e7a4f;color:#fff;padding:12px 18px;border-radius:10px;font:600 14px system-ui;box-shadow:0 8px 24px rgba(0,0,0,.25)';
		box.textContent = txt;
		document.body.appendChild(box);
		setTimeout(function () { box.remove(); }, 5000);
	}
	function poll() {
		fetch(icodPoll.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=icod_orders_poll&nonce=' + encodeURIComponent(icodPoll.nonce)
		}).then(function (r) { return r.json(); }).then(function (json) {
			if (!json || !json.success || !json.data) { return; }
			if (lastPending !== null && json.data.pending > lastPending) {
				beep();
				toast(json.data.pending + ' commande(s) en attente de confirmation');
			}
			lastPending = json.data.pending;
		}).catch(function () {});
	}
	poll();
	setInterval(poll, 45000);
})();
