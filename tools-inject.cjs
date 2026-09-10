const fs = require('fs');
let s = fs.readFileSync('infinitycod/includes/admin/class-admin-manager.php', 'utf8');
if (s.includes('force_inject_update')) { console.log('déjà présent'); process.exit(0); }
s = s.replace(
	"add_filter( 'plugin_action_links_'",
	"add_action( 'admin_init', array( $this, 'force_inject_update' ) );\n\t\tadd_filter( 'plugin_action_links_'"
);
s = s.replace(
	/(\n)\}/\n/,''
);
// Trouver la dernière fermeture de classe et insérer avant
const lastBrace = s.lastIndexOf('\n}');
if (lastBrace === -1) { console.log('braces introuvable'); process.exit(1); }
const method = [
	'',
	'\t/**',
	'\t * Injecte la mise à jour dans la transient WordPress native (barre jaune).',
	'\t */',
	'\tpublic function force_inject_update() {',
	"\t\tif ( ! current_user_can( 'update_plugins' ) ) { return; }",
	"\t\t$latest = '';",
	"\t\t$pkg = '';",
	"\t\tforeach ( array( 'icod_update_gh', 'icod_update_atom', 'icod_update_mirror' ) as $key ) {",
	"\t\t\t$cached = get_transient( $key );",
	"\t\t\tif ( is_array( $cached ) && ! empty( $cached['version'] ) ) { $latest = $cached['version']; $pkg = $cached['download_url'] ?? ''; break; }",
	"\t\t}",
	"\t\t$push = get_option( 'infinitycod_gh_push', array() );",
	"\t\tif ( is_array( $push ) && ! empty( $push['version'] ) && version_compare( $push['version'], $latest, '>' ) ) {",
	"\t\t\t$latest = $push['version'];",
	"\t\t\t$pkg = 'https://github.com/derouicheoussama/infinitycod-releases/releases/download/v' . $latest . '/infinitycod.zip';",
	"\t\t}",
	"\t\tif ( '' === $latest || version_compare( INFINITYCOD_VERSION, $latest, '>=' ) ) { return; }",
	"\t\t$current = get_site_transient( 'update_plugins' );",
	"\t\tif ( ! is_object( $current ) ) { $current = new \\stdClass(); }",
	"\t\tif ( ! isset( $current->response ) ) { $current->response = array(); }",
	"\t\tif ( ! isset( $current->checked ) ) { $current->checked = array(); }",
	"\t\t$current->checked[ INFINITYCOD_BASENAME ] = INFINITYCOD_VERSION;",
	"\t\t$current->response[ INFINITYCOD_BASENAME ] = (object) array(",
	"\t\t\t'slug' => 'infinitycod',",
	"\t\t\t'plugin' => INFINITYCOD_BASENAME,",
	"\t\t\t'new_version' => $latest,",
	"\t\t\t'url' => 'https://infinitycod.pro',",
	"\t\t\t'package' => $pkg,",
	"\t\t);",
	"\t\tset_site_transient( 'update_plugins', $current );",
	"\t}",
].join('\n');
s = s.slice(0, lastBrace) + method + '\n' + s.slice(lastBrace);
fs.writeFileSync('infinitycod/includes/admin/class-admin-manager.php', s);
console.log('force_inject_update ajouté');
