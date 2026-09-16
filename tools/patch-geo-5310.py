# -*- coding: utf-8 -*-
"""Patch Géo : colonnes paliers/retour, zones régionales, duplication, CSV étendu."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
ok = []

# ============ admin-manager.php ============
p = ROOT + r"\includes\admin\class-admin-manager.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

# 1) handle_wilayas_save : nouvelles clés + enregistrement des zones.
old = (
    "\t\t\t$data[ $code ] = array(\n"
    "\t\t\t\t'home'   => isset( $row['home'] ) && '' !== $row['home'] ? (float) $row['home'] : -1,\n"
    "\t\t\t\t'desk'   => isset( $row['desk'] ) && '' !== $row['desk'] ? (float) $row['desk'] : -1,\n"
    "\t\t\t\t'active' => empty( $row['active'] ) ? 0 : 1,\n"
    "\t\t\t\t'free'   => empty( $row['free'] ) ? 0 : 1,\n"
    "\t\t\t\t'min'    => isset( $row['min'] ) && '' !== $row['min'] ? (float) $row['min'] : 0,\n"
    "\t\t\t\t'days'   => isset( $row['days'] ) ? sanitize_text_field( (string) $row['days'] ) : '',\n"
    "\t\t\t);\n"
    "\t\t}"
)
new = (
    "\t\t\t$data[ $code ] = array(\n"
    "\t\t\t\t'home'   => isset( $row['home'] ) && '' !== $row['home'] ? (float) $row['home'] : -1,\n"
    "\t\t\t\t'desk'   => isset( $row['desk'] ) && '' !== $row['desk'] ? (float) $row['desk'] : -1,\n"
    "\t\t\t\t'active' => empty( $row['active'] ) ? 0 : 1,\n"
    "\t\t\t\t'free'   => empty( $row['free'] ) ? 0 : 1,\n"
    "\t\t\t\t'min'    => isset( $row['min'] ) && '' !== $row['min'] ? (float) $row['min'] : 0,\n"
    "\t\t\t\t'days'   => isset( $row['days'] ) ? sanitize_text_field( (string) $row['days'] ) : '',\n"
    "\t\t\t\t'w5'     => isset( $row['w5'] ) && '' !== $row['w5'] ? (float) $row['w5'] : -1,\n"
    "\t\t\t\t'w10'    => isset( $row['w10'] ) && '' !== $row['w10'] ? (float) $row['w10'] : -1,\n"
    "\t\t\t\t'w_over' => isset( $row['w_over'] ) && '' !== $row['w_over'] ? (float) $row['w_over'] : 0,\n"
    "\t\t\t\t'return_fee' => isset( $row['return_fee'] ) && '' !== $row['return_fee'] ? (float) $row['return_fee'] : 0,\n"
    "\t\t\t);\n"
    "\t\t}\n"
    "\n"
    "\t\t// Zones régionales (tarifs de repli par groupe de wilayas).\n"
    "\t\tif ( isset( $_POST['icod_zones'] ) && is_array( $_POST['icod_zones'] ) ) {\n"
    "\t\t\t\\InfinityCod\\Shipping\\Zones::save( wp_unslash( $_POST['icod_zones'] ) );\n"
    "\t\t}"
)
assert d.count(old) == 1, "wilayas_save data : %d" % d.count(old)
d = d.replace(old, new, 1)
ok.append("handle_wilayas_save étendu + zones")

# 2) Import CSV : colonnes optionnelles paliers/retour (indices 6-9).
old_imp = (
    "\t\t\t$data = array(\n"
    "\t\t\t\t'home'   => isset( $row[2] ) && '' !== $row[2] ? (float) str_replace( ',', '.', $row[2] ) : -1,\n"
    "\t\t\t\t'desk'   => isset( $row[3] ) && '' !== $row[3] ? (float) str_replace( ',', '.', $row[3] ) : -1,\n"
    "\t\t\t\t'active' => isset( $row[4] ) ? (int) (bool) $row[4] : 1,\n"
    "\t\t\t\t'free'   => isset( $row[5] ) ? (int) (bool) $row[5] : 0,\n"
    "\t\t\t);"
)
new_imp = (
    "\t\t\t$data = array(\n"
    "\t\t\t\t'home'   => isset( $row[2] ) && '' !== $row[2] ? (float) str_replace( ',', '.', $row[2] ) : -1,\n"
    "\t\t\t\t'desk'   => isset( $row[3] ) && '' !== $row[3] ? (float) str_replace( ',', '.', $row[3] ) : -1,\n"
    "\t\t\t\t'active' => isset( $row[4] ) ? (int) (bool) $row[4] : 1,\n"
    "\t\t\t\t'free'   => isset( $row[5] ) ? (int) (bool) $row[5] : 0,\n"
    "\t\t\t\t'w5'     => isset( $row[6] ) && '' !== $row[6] ? (float) str_replace( ',', '.', $row[6] ) : -1,\n"
    "\t\t\t\t'w10'    => isset( $row[7] ) && '' !== $row[7] ? (float) str_replace( ',', '.', $row[7] ) : -1,\n"
    "\t\t\t\t'w_over' => isset( $row[8] ) && '' !== $row[8] ? (float) str_replace( ',', '.', $row[8] ) : 0,\n"
    "\t\t\t\t'return_fee' => isset( $row[9] ) && '' !== $row[9] ? (float) str_replace( ',', '.', $row[9] ) : 0,\n"
    "\t\t\t);"
)
assert d.count(old_imp) == 1, "import csv : %d" % d.count(old_imp)
d = d.replace(old_imp, new_imp, 1)
ok.append("import CSV étendu")

# 3) Export CSV : SELECT + en-tête + cellules étendus.
old_exp_sel = "$rows  = $wpdb->get_results( \"SELECT code, name_fr, price_home, price_desk, active, free_shipping FROM {$table} ORDER BY code ASC\", ARRAY_A );"
new_exp_sel = "$rows  = $wpdb->get_results( \"SELECT code, name_fr, price_home, price_desk, active, free_shipping, delivery_days, min_order, w5, w10, w_over, return_fee FROM {$table} ORDER BY code ASC\", ARRAY_A );"
assert d.count(old_exp_sel) == 1, "export select : %d" % d.count(old_exp_sel)
d = d.replace(old_exp_sel, new_exp_sel, 1)

old_exp_head = "fputcsv( $out, array( 'code', 'wilaya', 'domicile', 'stopdesk', 'active', 'gratuite' ), ';' );"
new_exp_head = "fputcsv( $out, array( 'code', 'wilaya', 'domicile', 'stopdesk', 'active', 'gratuite', 'delai', 'min', 'poids_1_5', 'poids_5_10', 'poids_supp_kg', 'frais_retour' ), ';' );"
assert d.count(old_exp_head) == 1, "export head : %d" % d.count(old_exp_head)
d = d.replace(old_exp_head, new_exp_head, 1)

old_exp_cells = (
    "\t\t\t\t$row['active'] ? '1' : '0',\n"
    "\t\t\t\t$row['free_shipping'] ? '1' : '0',\n"
    "\t\t\t);"
)
new_exp_cells = (
    "\t\t\t\t$row['active'] ? '1' : '0',\n"
    "\t\t\t\t$row['free_shipping'] ? '1' : '0',\n"
    "\t\t\t\t(string) $row['delivery_days'],\n"
    "\t\t\t\t(float) $row['min_order'] > 0 ? $row['min_order'] : '',\n"
    "\t\t\t\t(float) $row['w5'] < 0 ? '' : $row['w5'],\n"
    "\t\t\t\t(float) $row['w10'] < 0 ? '' : $row['w10'],\n"
    "\t\t\t\t(float) $row['w_over'] > 0 ? $row['w_over'] : '',\n"
    "\t\t\t\t(float) $row['return_fee'] > 0 ? $row['return_fee'] : '',\n"
    "\t\t\t);"
)
assert d.count(old_exp_cells) == 1, "export cells : %d" % d.count(old_exp_cells)
d = d.replace(old_exp_cells, new_exp_cells, 1)
ok.append("export CSV étendu")

with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)

# ============ geo-page.php ============
p = ROOT + r"\includes\admin\pages\class-geo-page.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

# 4) En-têtes du tableau.
old_th = (
    "\t\t\t\t\t\t\t\t<th class=\"icod-col-check\"><?php esc_html_e( 'Active', 'infinitycod' ); ?></th>\n"
    "\t\t\t\t\t\t\t\t<th class=\"icod-col-check\"><?php esc_html_e( 'Gratuite', 'infinitycod' ); ?></th>"
)
new_th = old_th + (
    "\n\t\t\t\t\t\t\t\t<th><?php esc_html_e( '1–5 kg', 'infinitycod' ); ?></th>\n"
    "\t\t\t\t\t\t\t\t<th><?php esc_html_e( '5–10 kg', 'infinitycod' ); ?></th>\n"
    "\t\t\t\t\t\t\t\t<th><?php esc_html_e( '>10 kg (/kg)', 'infinitycod' ); ?></th>\n"
    "\t\t\t\t\t\t\t\t<th><?php esc_html_e( 'Retour (DA)', 'infinitycod' ); ?></th>\n"
    "\t\t\t\t\t\t\t\t<th class=\"icod-col-check\"><?php esc_html_e( 'Copier', 'infinitycod' ); ?></th>"
)
assert d.count(old_th) == 1, "thead : %d" % d.count(old_th)
d = d.replace(old_th, new_th, 1)

# 5) Cellules du tableau.
old_row = """									<td class="icod-col-check"><input type="checkbox" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][free]" <?php checked( ! empty( $w['free_shipping'] ) ); ?> /></td>
								</tr>"""
new_row = """									<td class="icod-col-check"><input type="checkbox" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][free]" <?php checked( ! empty( $w['free_shipping'] ) ); ?> /></td>
									<td><input type="number" step="0.01" min="0" style="width:74px" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][w5]" value="<?php echo esc_attr( (float) $w['w5'] >= 0 ? $w['w5'] : '' ); ?>" placeholder="—" /></td>
									<td><input type="number" step="0.01" min="0" style="width:74px" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][w10]" value="<?php echo esc_attr( (float) $w['w10'] >= 0 ? $w['w10'] : '' ); ?>" placeholder="—" /></td>
									<td><input type="number" step="0.01" min="0" style="width:64px" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][w_over]" value="<?php echo esc_attr( (float) $w['w_over'] > 0 ? $w['w_over'] : '' ); ?>" placeholder="0" /></td>
									<td><input type="number" step="0.01" min="0" style="width:74px" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][return_fee]" value="<?php echo esc_attr( (float) $w['return_fee'] > 0 ? $w['return_fee'] : '' ); ?>" placeholder="0" /></td>
									<td class="icod-col-check"><button type="button" class="button icod-dup" data-code="<?php echo esc_attr( $w['code'] ); ?>" title="<?php esc_attr_e( 'Copier domicile/stopdesk de cette wilaya vers toutes les lignes vides', 'infinitycod' ); ?>">📋</button></td>
								</tr>"""
assert d.count(old_row) == 1, "row cells : %d" % d.count(old_row)
d = d.replace(old_row, new_row, 1)

# 6) Carte Zones régionales (dans le formulaire principal) avant le submit.
old_submit = """			<p class="icod-submit">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Enregistrer les tarifs', 'infinitycod' ); ?></button>
			</p>
		</form>"""
new_submit = """			<div class="icod-card icod-zones-card">
				<h2><?php esc_html_e( 'Zones régionales (tarifs de repli)', 'infinitycod' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Une wilaya sans tarif personnalisé hérite du tarif de sa zone. Codes séparés par des virgules. Vide = aucune zone.', 'infinitycod' ); ?></p>
				<div id="icod-zones-rows">
					<?php foreach ( \\InfinityCod\\Shipping\\Zones::all() as $i => $zone ) : ?>
						<div class="icod-zone-row">
							<input type="text" name="icod_zones[<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $zone['name'] ); ?>" placeholder="Centre" />
							<input type="text" name="icod_zones[<?php echo esc_attr( $i ); ?>][codes]" value="<?php echo esc_attr( implode( ',', $zone['codes'] ) ); ?>" placeholder="16,35,44" dir="ltr" />
							<input type="number" step="0.01" min="0" name="icod_zones[<?php echo esc_attr( $i ); ?>][home]" value="<?php echo esc_attr( $zone['home'] > 0 ? $zone['home'] : '' ); ?>" placeholder="Domicile" />
							<input type="number" step="0.01" min="0" name="icod_zones[<?php echo esc_attr( $i ); ?>][desk]" value="<?php echo esc_attr( $zone['desk'] > 0 ? $zone['desk'] : '' ); ?>" placeholder="Stopdesk" />
							<button type="button" class="button icod-zone-del">✕</button>
						</div>
					<?php endforeach; ?>
				</div>
				<p>
					<button type="button" class="button" id="icod-zone-add"><?php esc_html_e( '+ Ajouter une zone', 'infinitycod' ); ?></button>
					<button type="button" class="button" id="icod-zone-preset"><?php esc_html_e( 'Grille nationale (Centre/Est/Ouest/Sud)', 'infinitycod' ); ?></button>
				</p>
			</div>

			<p class="icod-submit">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Enregistrer les tarifs', 'infinitycod' ); ?></button>
			</p>
		</form>

		<script>
		(function () {
			var rows = document.getElementById('icod-zones-rows');
			function addZone(z) {
				var i = rows.querySelectorAll('.icod-zone-row').length;
				var div = document.createElement('div');
				div.className = 'icod-zone-row';
				div.innerHTML = '<input type="text" name="icod_zones[' + i + '][name]" placeholder="Centre" />'
					+ '<input type="text" name="icod_zones[' + i + '][codes]" placeholder="16,35,44" dir="ltr" />'
					+ '<input type="number" step="0.01" min="0" name="icod_zones[' + i + '][home]" placeholder="Domicile" />'
					+ '<input type="number" step="0.01" min="0" name="icod_zones[' + i + '][desk]" placeholder="Stopdesk" />'
					+ '<button type="button" class="button icod-zone-del">✕</button>';
				if (z) {
					div.querySelector('[name*="[name]"]').value = z.name || '';
					div.querySelector('[name*="[codes]"]').value = z.codes || '';
				}
				rows.appendChild(div);
			}
			if (rows) {
				rows.addEventListener('click', function (e) {
					if (e.target.classList.contains('icod-zone-del')) { e.target.closest('.icod-zone-row').remove(); }
				});
				var add = document.getElementById('icod-zone-add');
				if (add) { add.addEventListener('click', function () { addZone(null); }); }
				var preset = document.getElementById('icod-zone-preset');
				if (preset) {
					preset.addEventListener('click', function () {
						var list = [
							{ name: 'Centre', codes: '16,35,44,09,06,42,26' },
							{ name: 'Est', codes: '25,05,24,23,36,43,18,41,21,04' },
							{ name: 'Ouest', codes: '31,22,13,27,29,48,14,02,03,46,38' },
							{ name: 'Sud (Hauts Plateaux & Grand Sud)', codes: '47,32,39,30,51,52,55,56,57,58,33,34,45,07,40,17,54,53,28,37,08,49,12,15,19,20,11,10,01,50' }
						];
						rows.innerHTML = '';
						list.forEach(addZone);
					});
				}
			}
			/* Duplication : copie domicile/stopdesk vers les lignes vides. */
			document.querySelectorAll('.icod-dup').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var code = btn.getAttribute('data-code');
					var src = document.querySelector('tr [name$="][' + code + '][home]"]');
					if (!src) { return; }
					var srcHome = src.value, srcDesk = src.closest('tr').querySelector('[name$="[desk]"]').value;
					if (srcHome === '') { return; }
					var n = 0;
					document.querySelectorAll('.icod-wilayas-table tbody tr').forEach(function (tr) {
						var home = tr.querySelector('[name$="[home]"]');
						if (!home || home.value !== '' || tr.contains(btn)) { return; }
						home.value = srcHome;
						var desk = tr.querySelector('[name$="[desk]"]');
						if (desk && desk.value === '') { desk.value = srcDesk; }
						n++;
					});
					btn.textContent = '+' + n;
					setTimeout(function () { btn.textContent = '📋'; }, 1500);
				});
			});
		})();
		</script>"""
assert d.count(old_submit) == 1, "submit zone : %d" % d.count(old_submit)
d = d.replace(old_submit, new_submit, 1)
ok.append("geo-page : colonnes + zones + duplication + JS")

with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)

print("\n".join("OK  " + o for o in ok))
