import { fileURLToPath } from 'node:url';
import path from 'node:path';

process.env.PORT = '8799';
const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
process.env.DB_PATH = path.join(root, 'storage', 'probe.db');
process.env.APP_URL = 'http://127.0.0.1:8799';

await import('file:///' + path.join(root, 'server.js').split(path.sep).join('/'));
await new Promise((r) => setTimeout(r, 800));

let cookie = '';
const req = async (m, p, b, form) => {
	const h = {};
	if (cookie) h.Cookie = cookie;
	let pl;
	if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; pl = new URLSearchParams(b).toString(); }
	const r = await fetch('http://127.0.0.1:8799' + p, { method: m, headers: h, body: pl, redirect: 'manual' });
	const sc = r.headers.get('set-cookie');
	if (sc) cookie = sc.split(';')[0];
	return { status: r.status, text: await r.text() };
};

let r = await req('GET', '/setup');
console.log('setup GET:', r.status);
await req('POST', '/setup', { name: 'Root', email: 'root@probe.pro', password: 'SuperSecret123', confirm: 'SuperSecret123' }, true);
r = await req('GET', '/admin');
const csrf = (r.text.match(/name="csrf" value="([a-f0-9]+)"/) || [])[1];
console.log('csrf:', !!csrf);
await req('POST', '/admin/orders/create', { customer_name: 'C', customer_email: 'c@x.dz', product_id: '1', plan_id: '1', amount: '39', currency: 'USD', status: 'PAID', csrf }, true);
r = await req('GET', '/admin/orders');
const i = r.text.indexOf('generate_license');
console.log('generate_license idx:', i, '| ORD idx:', r.text.indexOf('ORD-'), '| PAID idx:', r.text.indexOf('>PAID<'));
if (i > -1) console.log('CTX:', JSON.stringify(r.text.slice(i - 260, i + 60)));
else {
	const ord = r.text.indexOf('ORD-');
	console.log('CTX ordre:', JSON.stringify(ord > -1 ? r.text.slice(ord - 100, ord + 500) : r.text.slice(0, 600)));
}
process.exit(0);
