/* Harnais : stubs pour Updates/Diagnostics + inclusion logger dans la liste des modules. */
'use strict';
const fs = require('fs');
const p = 'tools/test-activation.php';
let s = fs.readFileSync(p, 'utf8');

function must(needle) {
  if (!s.includes(needle)) {
    console.error('ANCRE INTROUVABLE : ' + needle);
    process.exit(1);
  }
}

// 1. Stubs manquants (ajoutés s'ils n'existent pas déjà).
const stubs = [
  "function wp_doing_ajax() { return false; }",
  "function wp_doing_cron() { return false; }",
  "function wp_get_upload_dir() { return array( 'basedir' => sys_get_temp_dir(), 'baseurl' => 'https://example.test/uploads' ); }",
  "function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=x'; }",
  "function self_admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }",
  "function date_i18n( $f, $t = null ) { return date( $f, $t ?: time() ); }",
  "function esc_js( $s ) { return (string) $s; }",
  "function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }",
];
for (const stub of stubs) {
  const name = (stub.match(/function ([a-z_0-9]+)/) || [])[1];
  if (!s.includes('function ' + name + '(')) {
    s = s.replace('/* ---------- WooCommerce stub minimal ---------- */', stub + '\n\n/* ---------- WooCommerce stub minimal ---------- */');
    console.log('+ stub ' + name);
  }
}

// 2. wpdb : db_version + get_col.
if (!s.includes('public function db_version')) {
  s = s.replace('	public function esc_like(', "	public function db_version() { return '8.0.36'; }\n	public function get_col( $q = null, $x = 0 ) { $GLOBALS['__wpdb_log'][] = $q; return array( 'email' ); }\n	public function esc_like(");
  console.log('+ wpdb db_version/get_col');
}

// 3. Logger dans la liste des modules testés.
s = s.replace("array( 'geo', 'rates', 'shield', 'orders', 'form', 'payment', 'rest',", "array( 'i18n', 'logger', 'geo', 'rates', 'shield', 'orders', 'form', 'payment', 'rest',");

// 4. Pages updates/diagnostics dans la liste des rendus.
s = s.replace("'render_settings', 'render_about'", "'render_settings', 'render_updates', 'render_diagnostics', 'render_about'");

// 5. Le harnais doit appeler Activator::maybe_upgrade via le hook admin_init :
//    déjà couvert par le test de migration (9).

fs.writeFileSync(p, s);
console.log('✓ harnais étendu');
