'use strict';
const fs = require('fs');
const files = [
	'infinitycod/includes/admin/pages/class-abandoned-page.php',
	'infinitycod/includes/admin/pages/class-carriers-page.php',
	'infinitycod/includes/admin/pages/class-dashboard-page.php',
	'infinitycod/includes/admin/pages/class-orders-page.php',
	'infinitycod/includes/admin/pages/class-stats-page.php',
];
const BROKEN = 'InfinityCodCoreSettings::';
const GOOD = '\\InfinityCod\\Core\\Settings::';
for (const f of files) {
	let s = fs.readFileSync(f, 'utf8');
	const n = s.split(BROKEN).length - 1;
	if (n > 0) {
		s = s.split(BROKEN).join(GOOD);
		fs.writeFileSync(f, s);
	}
	console.log(f.split('\\').pop() + ' : ' + n + ' corrigé(s)');
}
