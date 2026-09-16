# -*- coding: utf-8 -*-
"""Patch 5.37.0 : correction des 14 erreurs Plugin Check (nouveau code 5.33-5.35)."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []


def patch(rel, pairs):
    p = ROOT + BS + rel.replace("/", BS)
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    for old, new in pairs:
        n = d.count(old)
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


# 1) Carriers : commentaire traducteurs (Réseau : %s).
patch("includes/admin/pages/class-carriers-page.php", [
    (
        "\t\t\t\t\t<span class=\"icod-carrier-net\"><?php printf( esc_html__( 'Réseau : %s', 'infinitycod' ),",
        "\t\t\t\t\t<span class=\"icod-carrier-net\"><?php\n"
        "\t\t\t\t\t/* translators: %s : réseau d'expédition. */\n"
        "\t\t\t\t\tprintf( esc_html__( 'Réseau : %s', 'infinitycod' ),",
    ),
])

# 2) Anti-leak : littéral unique pour le corps de l'alerte.
patch("includes/core/class-anti-leak.php", [
    (
        "\t\t\t__( 'Type : %1$s' . \"\\n\" . 'Trace : %2$s' . \"\\n\" . 'IP : %3$s' . \"\\n\" . 'Date : %4$s', 'infinitycod' ),",
        "\t\t\t__( 'Type : %1$s — Trace : %2$s — IP : %3$s — Date : %4$s', 'infinitycod' ),",
    ),
])

# 3) SEO : commentaires traducteurs sur les 3 printf de la landing + échappement llms.
patch("includes/seo/class-seo-manager.php", [
    (
        "\t<h1><?php printf( esc_html__( 'Livraison à %s', 'infinitycod' ), esc_html( $name ) ); ?>",
        "\t<?php /* translators: %s : wilaya. */ ?>\n"
        "\t<h1><?php printf( esc_html__( 'Livraison à %s', 'infinitycod' ), esc_html( $name ) ); ?>",
    ),
    (
        "\t<p><?php printf( esc_html__( 'Commandez en ligne et payez à la livraison partout à %1$s et dans les %2$d wilayas d’Algérie.', 'infinitycod' ), esc_html( $name ), 58 ); ?></p>",
        "\t<?php /* translators: 1 : wilaya, 2 : nombre de wilayas. */ ?>\n"
        "\t<p><?php printf( esc_html__( 'Commandez en ligne et payez à la livraison partout à %1$s et dans les %2$d wilayas d’Algérie.', 'infinitycod' ), esc_html( $name ), 58 ); ?></p>",
    ),
    (
        "\t<p>⏱ <?php printf( esc_html__( 'Délai estimé à %1$s : %2$s', 'infinitycod' ), esc_html( $name ), esc_html( $days ) ); ?></p>",
        "\t<?php /* translators: 1 : wilaya, 2 : délai. */ ?>\n"
        "\t<p>⏱ <?php printf( esc_html__( 'Délai estimé à %1$s : %2$s', 'infinitycod' ), esc_html( $name ), esc_html( $days ) ); ?></p>",
    ),
    (
        "\t\techo implode( \"\\n\", $lines );",
        "\t\techo esc_html( implode( \"\\n\", $lines ) );",
    ),
])

# 4) AbTest : wp_rand.
patch("includes/orders/class-ab-test.php", [
    (
        "\t\t$variant = ( 0 === mt_rand( 0, 1 ) ) ? 'A' : 'B';",
        "\t\t$variant = ( 0 === wp_rand( 0, 1 ) ) ? 'A' : 'B';",
    ),
])

# 5) Shield : liste communautaire opt-in (ignore documenté).
patch("includes/anti-fraud/class-shield.php", [
    (
        "\t\t\t$raw     = wp_remote_get( 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/main/community-blacklist.json', array( 'timeout' => 6 ) );",
        "\t\t\t$raw     = wp_remote_get( 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/main/community-blacklist.json', array( 'timeout' => 6 ) ); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- liste communautaire opt-in hébergée sur le dépôt officiel des releases.",
    ),
])

# 6) Settings export : construire le JSON dans une variable puis un echo unique.
patch("includes/admin/class-admin-manager.php", [
    (
        "\t\t$settings = get_option( 'infinitycod_settings', array() );\n"
        "\t\t$settings = is_array( $settings ) ? $settings : array();\n"
        "\t\tunset( $settings['webhook_secret'] ); // Secret régénérable : jamais exporté.\n"
        "\t\techo (string) wp_json_encode(\n"
        "\t\t\tarray(\n"
        "\t\t\t\t'plugin'    => 'infinitycod',\n"
        "\t\t\t\t'version'   => INFINITYCOD_VERSION,\n"
        "\t\t\t\t'exported'  => gmdate( 'c' ),\n"
        "\t\t\t\t'site'      => home_url(),\n"
        "\t\t\t\t'settings'  => $settings,\n"
        "\t\t\t),\n"
        "\t\t\tJSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE\n"
        "\t\t);",
        "\t\t$settings = get_option( 'infinitycod_settings', array() );\n"
        "\t\t$settings = is_array( $settings ) ? $settings : array();\n"
        "\t\tunset( $settings['webhook_secret'] ); // Secret régénérable : jamais exporté.\n\n"
        "\t\t// Téléchargement de données : l'écho brut est volontaire (en-tête JSON ci-dessus).\n"
        "\t\t$json_out = wp_json_encode(\n"
        "\t\t\tarray(\n"
        "\t\t\t\t'plugin'    => 'infinitycod',\n"
        "\t\t\t\t'version'   => INFINITYCOD_VERSION,\n"
        "\t\t\t\t'exported'  => gmdate( 'c' ),\n"
        "\t\t\t\t'site'      => home_url(),\n"
        "\t\t\t\t'settings'  => $settings,\n"
        "\t\t\t),\n"
        "\t\t\tJSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE\n"
        "\t\t);\n"
        "\t\techo $json_out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- téléchargement JSON.",
    ),
])

print("\n".join("OK  " + o for o in ok))
