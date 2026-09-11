# Publication sur WordPress.org — guide complet

## 1. Soumettre le plugin (une seule fois)

1. Créez un compte sur https://wordpress.org (compte WordPress.org, pas votre site).
2. Allez sur https://wordpress.org/plugins/developers/add/ et soumettez le slug **infinitycod**.
   - Joignez le zip `dist/infinitycod.zip` (build : `node tools/build.js`).
   - Dans la description de soumission, précisez : formulaire COD, mise à jour via GitHub,
     service optionnel Chargily/Sheets désactivé par défaut.
3. L'équipe de revue répond en général sous 5 à 10 jours. Corrigez les points demandés si besoin.

## 2. Ce qui a déjà été préparé pour la revue

- **readme.txt** au format standard (Description, Installation, FAQ, Screenshots, Changelog,
  Upgrade Notice, Services externes disclosés).
- **Assets officiels** dans `infinitycod/assets/` : `banner-772x250.png`, `banner-1544x500.png`,
  `icon-128x128.png`, `icon-256x256.png`, `screenshot-1..3.png` (captures réelles du formulaire).
- **Defer à WordPress.org** : `class-updater.php` — si le plugin est hébergé sur .org
  (présence dans `update_plugins->no_update`), la mise à jour GitHub se désactive d'elle-même.
  Sur une installation auto-hébergée (GitHub), le comportement est inchangé.
- **Services externes disclosés** dans readme.txt (section Services externes) : GitHub,
  Chargily (optionnel), Google Sheets (optionnel), WhatsApp/Discord/Telegram (optionnels).
- **Licence GPL-2.0-or-later** déclarée partout (headers, readme).
- Aucun code obfusqué, aucun raccourcisseur d'URL, aucune donnée envoyée sans configuration
  explicite du marchand.

## 3. Déploiement SVN (chaque version)

```bash
# Une fois : checkout SVN
svn checkout http://plugins.svn.wordpress.org/infinitycod/ infinitycod-svn
cd infinitycod-svn

# À chaque release :
# 1. Copier les fichiers du plugin (sans .git, node_modules, dist, tools)
rsync -av --exclude='.git' --exclude='node_modules' --exclude='tools' \
  --exclude='dist' --exclude='.github' ../infinitycod/ trunk/
# 2. Assets dans le dossier assets/ du SVN
cp ../infinitycod/assets/banner-772x250.png ../assets/banner-772x250.png
cp ../infinitycod/assets/icon-128x128.png ../assets/icon-128x128.png
cp ../infinitycod/assets/screenshot-1.png ../assets/screenshot-1.png
# 3. Stable tag dans trunk/readme.txt = numéro de version
# 4. Commit
svn ci -m "vX.Y.Z"
# 5. Tag
svn mkdir tags/x.y.z && svn cp trunk/* tags/x.y.z/ && svn ci -m "tag x.y.z"
```

## 4. ⚠️ Double distribution (.org + GitHub auto-hébergé)

Le plugin contient SA propre mise à jour GitHub (system de mises à jour auto-hébergé).

- **Installations auto-hébergées** (clients directs) : la mise à jour GitHub fonctionne
  normalement, aucun changement.
- **Installations .org** : dès que WordPress.org sert le plugin (`no_update` dans le
  transient), la mise à jour GitHub se désactive d'elle-même — aucun conflit, c'est le
  pattern standard des plugins dual-hosted.

## 5. ⚠️ Verrou développeur et règles .org

Le verrou `INFINITYCOD_LOCK_FORM` est une **constante wp-config posée par le site**
(décision locale, jamais distante). Sur WordPress.org :

- Disclosez-la dans la description (« option développeur »).
- Elle est désactivée par défaut — elle n'affecte que les sites qui l'ajoutent
  explicitement dans wp-config.php.
- Ne la transformez JAMAIS en verrou distant piloté par votre serveur : c'est la
  principale cause de rejet/suppression sur .org (« remotely disabling code »).

## 6. Alternative réaliste : rester auto-hébergé + freemium

Publier sur .org apporte de la visibilité mais retire le contrôle (revue, pas de
verrou developpeur visible, mise à jour via SVN). L'alternative éprouvée :
rester auto-hébergé avec le système actuel (détection GitHub fiable) + une version
gratuite complète (stats incluses dès 5.14.0) comme aimant marketing, la licence
Premium unique débloquant le support et les connecteurs (transporteurs, WhatsApp auto).
