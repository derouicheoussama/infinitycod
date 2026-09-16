# -*- coding: utf-8 -*-
"""Patch 5.36.0 ter : neutralise les mentions GitHub visibles par le marchand."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []


def patch_multi(rel, pairs):
    p = ROOT + BS + rel.replace("/", BS)
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    for old, new, expect in pairs:
        n = d.count(old)
        assert n == expect, rel + " :: " + old[:60] + " -> " + str(n) + " (attendu " + str(expect) + ")"
        d = d.replace(old, new)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


patch_multi("includes/admin/pages/class-diagnostics-page.php", [
    ("serveur injoignable (réseau restreint ou limite de débit GitHub)",
     "serveur injoignable (réseau restreint ou limite de débit)", 1),
    ("quota API GitHub atteint (403) — les miroirs CDN prennent le relais",
     "quota de l'API atteint (403) — les miroirs CDN prennent le relais", 1),
    ("Updater — GitHub (primaire)", "Updater — serveur de mises à jour", 2),
    ("'github'          => __( 'GitHub API', 'infinitycod' )",
     "'github'          => __( 'API des mises à jour', 'infinitycod' )", 1),
    ("'atom'            => __( 'GitHub (flux atom)', 'infinitycod' )",
     "'atom'            => __( 'Flux atom des mises à jour', 'infinitycod' )", 1),
    ("'mirror-raw'      => __( 'Miroir GitHub brut', 'infinitycod' )",
     "'mirror-raw'      => __( 'Miroir CDN', 'infinitycod' )", 1),
])

patch_multi("includes/admin/pages/class-updates-page.php", [
    ("la release n’a pas pu être récupérée depuis GitHub. Utilisez l’installation manuelle par zip ci-dessous.",
     "la release n’a pas pu être récupérée depuis le serveur de mises à jour. Utilisez l’installation manuelle par zip ci-dessous.", 1),
    ("Votre site vérifie GitHub toutes les heures. Vous serez notifié dès qu\’une nouvelle version sort.",
     "Votre site vérifie les mises à jour toutes les heures. Vous serez notifié dès qu\’une nouvelle version sort.", 1),
    ("'github'          => __( '· via GitHub API', 'infinitycod' )",
     "'github'          => __( '· via l’API des mises à jour', 'infinitycod' )", 1),
    ("'atom'            => __( '· via GitHub (flux atom)', 'infinitycod' )",
     "'atom'            => __( '· via le flux atom', 'infinitycod' )", 1),
    ("'mirror-raw'      => __( '· via le miroir GitHub brut', 'infinitycod' )",
     "'mirror-raw'      => __( '· via le miroir CDN', 'infinitycod' )", 1),
    ("Inconnue (GitHub injoignable ou aucune release publiée)",
     "Inconnue (serveur injoignable ou aucune release publiée)", 1),
    ("Installer la dernière release GitHub même si le plugin semble à jour ?",
     "Réinstaller la dernière version même si le plugin semble à jour ?", 1),
    ("Télécharge la dernière release depuis GitHub et remplace les fichiers du plugin (réglages et données conservés).",
     "Télécharge la dernière version depuis le serveur de mises à jour et remplace les fichiers du plugin (réglages et données conservés).", 1),
    ("Si votre hébergeur bloque GitHub ou que la détection échoue : téléchargez infinitycod.zip depuis la page des releases, puis téléversez-le ici.",
     "Si votre hébergeur bloque le serveur de mises à jour ou que la détection échoue : téléchargez infinitycod.zip depuis la page des releases, puis téléversez-le ici.", 1),
])

patch_multi("includes/admin/pages/class-about-page.php", [
    ("$source = 'GitHub';", "$source = __( 'Serveur officiel', 'infinitycod' );", 1),
])

print("\n".join("OK  " + o for o in ok))
