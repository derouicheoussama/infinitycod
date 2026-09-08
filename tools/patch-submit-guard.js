/* REST submit : try/catch Throwable → réponse JSON propre, jamais de page critique. */
'use strict';
const fs = require('fs');
const p = 'infinitycod/includes/rest/class-routes.php';
let s = fs.readFileSync(p, 'utf8');

const anchor = "\tpublic function submit( $request ) {\n\t\t$body = $this->body( $request );";
if (!s.includes(anchor)) { console.error('ancre submit introuvable'); process.exit(1); }

const replacement = [
  "\tpublic function submit( $request ) {",
  "\t\ttry {",
  "\t\t\treturn $this->do_submit( $request );",
  "\t\t} catch ( \\Throwable $e ) {",
  "\t\t\t\\InfinityCod\\Logging\\Logger::log( 'error', 'submit : ' . $e->getMessage() );",
  "\t\t\treturn new \\WP_Error(",
  "\t\t\t\t'icod_server_error',",
  "\t\t\t\t__( 'Une erreur technique est survenue lors de l\\'enregistrement. Votre commande n a pas été perdue si vous aviez payé — contactez-nous.', 'infinitycod' ),",
  "\t\t\t\tarray( 'status' => 500 )",
  "\t\t\t);",
  "\t\t}",
  "\t}",
  "",
  "\t/**",
  "\t * Corps effectif de la soumission.",
  "\t *",
  "\t * @param \\WP_REST_Request $request Requête.",
  "\t * @return \\WP_REST_Response|\\WP_Error",
  "\t */",
  "\tprivate function do_submit( $request ) {\n\t\t$body = $this->body( $request );"
].join('\n');

s = s.replace(anchor, () => replacement);
fs.writeFileSync(p, s);
console.log('✓ submit blindé');
