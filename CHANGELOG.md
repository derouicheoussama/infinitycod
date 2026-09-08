# Changelog

## 1.0.0 — 2026-09-08

Version initiale d'InfinityCod — Paiement à la livraison (COD Algérie).

### Ajouté
- **Formulaire COD one-page** responsive mobile-first (tokens CSS, dark mode auto, RTL arabe, barre collante mobile, champs tactiles ≥ 48 px) : shortcode `[infinitycod_form]`, bloc Gutenberg, compatible Elementor, insertion automatique sur fiches produit.
- **Géo Algérie** : 58 wilayas + 1541 communes officielles (noms FR/AR), tarifs domicile/stopdesk en cascade commune → wilaya → défaut, override par commune, livraison gratuite par palier de quantité.
- **Anti-fraude Shield** : honeypot, timestamp signé HMAC (temps minimum de remplissage), empreinte navigateur, limitation par IP/heure, blacklist téléphones/IP, détection de doublons, score de risque 0-100 affiché dans le dashboard.
- **Commandes** : création d'une vraie commande WooCommerce (produit, remise en frais négatif, livraison, adresse DZ, paiement COD) + ligne COD interne avec flux dédié (en attente → confirmée → expédiée → livrée/retournée/échouée/annulée), statuts synchronisés avec WooCommerce.
- **Dashboard COD** : tableur filtrable (statut, wilaya, recherche, période), actions rapides et groupées (confirmer, expédier, annuler, blacklister…), vue détail, export CSV Excel, tableau responsive en cartes sous 782 px.
- **Transporteurs intégrés (Premium)** : Yalidine, ZR Express, Maystro, réseau Ecotrack (Noest, E-COM, DHD, instance personnalisée) — création de colis en 1 clic, suivi synchronisé toutes les heures, import des bureaux Stopdesk Yalidine.
- **WhatsApp automatique (Premium)** : messages commande reçue / expédiée, relance automatique des paniers abandonnés (cron 15 min), passerelles Cloud API / UltraMsg / wa.me, journal des envois, page paniers abandonnés avec relance manuelle en un clic.
- **Offres par quantité (Premium)** : paliers globaux et par produit (métabox), remises recalculées côté serveur uniquement.
- **Statistiques P&L (Premium)** : CA encaissé/confirmé, taux de confirmation/retour/livraison, panier moyen, graphique journalier (Chart.js local), répartitions par wilaya / transporteur / produit.
- **Licences** : protocole compatible serveur Infinity Coder (factexpert.online), vérification hebdomadaire, tolérance hors-ligne, clé de développement.
- REST API publique `/wp-json/infinitycod/v1/` (communes, stopdesks, devis, soumission, paniers abandonnés), tarification exclusivement côté serveur.
- Internationalisation FR (défaut) / AR / EN, fichier POT (313 chaînes), outil de génération.
- Outils développeur : `npm run check` (lint PHP + smoke autoloader), `npm run build` (zip), `npm run pot`.

## 1.1.0 — 2026-09-08

### Corrigé
- **CSS du formulaire jamais chargé** : les assets étaient enregistrés mais enqueued pendant le rendu du corps de page, après `wp_head` — le formulaire s'affichait en HTML brut. Chargement anticipé (fiche produit ou shortcode détecté tôt) + fallback écriture inline du lien CSS. C'est la cause du formulaire « brut » constaté sur l'installation de test.

### Ajouté
- **Design du formulaire refondu** : en-tête avec icône et sous-titre, layout 2 colonnes sur desktop (champs / récapitulatif sticky), icônes intégrées dans les champs nom et téléphone, cartes de mode de livraison, animations d'erreur, bandeau d'accent dégradé.
- **5 thèmes de formulaire** : Moderne, Élégant, Sunset, Océan, Minimal (sélecteur visuel dans les réglages, synchronisation automatique de la couleur d'accent).
- **Personnalisation des champs** : libellés éditables (nom, téléphone, wilaya, commune, note) + bascules d'affichage (sélecteur quantité, livraison Stopdesk, champ note, paliers d'offres, bandeau de réassurance).
- **Page « À propos »** : version, statut de licence, statut système (PHP, WordPress, WooCommerce, thème actif, tables, transporteurs connectés), bouton « Vérifier les mises à jour ».
- **Mises à jour à distance** : API standard WordPress (notifications + mise à jour en 1 clic) branchée sur factexpert.online, téléchargement gated par licence ; documentation serveur dans docs/SERVEUR-MISES-A-JOUR.md.
- Compatibilité thèmes renforcée : resets !important ciblés (hauteurs, couleurs, boutons, selects) contre les styles agressifs d'Astra/Flatsome/WoodMart/Divi.

### Ajouté (1.1.0, suite)
- **Mises à jour depuis GitHub** : source prioritaire GitHub Releases du dépôt `derouicheoussama/infinitycod` (fallback factexpert.online). Dépôt privé supporté : token GitHub configurable dans Réglages → Avancé, téléchargement authentifié en deux temps (gestion de la redirection signée S3). Workflow GitHub Actions de release automatique (lint → build zip → release) déclenché par les tags `v*`.

## 1.2.0 — 2026-09-08

### Ajouté
- **Mises à jour encore plus directes** : vérification horaire dédiée (cron `infinitycod_update_check`) en plus du cycle natif WordPress de 12 h — une release GitHub apparaît chez les clients en quelques heures maximum. Nouvelle option **« Mise à jour automatique »** : le plugin s'installe tout seul dès qu'une version est publiée (filtre `auto_update_plugin`).
- **Interface admin** : badge rouge de commandes en attente sur le menu InfinityCod (option désactivable), crédit Infinity Coder en pied de page sur les écrans du plugin, page À propos affichant la dernière version publiée et sa source (GitHub / factexpert.online).
- **Nouveaux réglages formulaire** : indication du champ téléphone, largeur du formulaire (400-900 px), titre et texte de succès personnalisés (variable {num}).

## 1.3.0 — 2026-09-08

### Corrigé
- **Réglages non enregistrés** (bug critique) : les handlers admin_post des pages Réglages et À propos étaient enregistrés dans leur constructeur, appelé uniquement au rendu — jamais quand admin-post.php recevait le POST. Les pages sont maintenant instanciées dès le chargement du plugin. C'était la cause exacte du rapport « les réglages ne s'enregistrent pas ».
- **Icônes des champs nom/téléphone trop grandes** : `background-size` passait sans `!important` et était écrasé par les styles des thèmes.

### Ajouté
- **Paiement en ligne Chargily Pay v2 (CIB / Edahabia)** : choix COD / paiement immédiat dans le formulaire, création de checkout (API pay.chargily.net, mode test/production), redirection client, confirmation par vérification API au retour + webhook signé HMAC-SHA256 (`/wp-json/infinitycod/v1/chargily/webhook`), commande marquée **Payée 💳** (flag + WooCommerce payment_complete + statut COD confirmé), page de retour personnalisable. Colonnes DB `payment/checkout_id/paid/paid_at` (migration automatique). Réglages → onglet Paiement.

## 1.3.1 — 2026-09-08

### Corrigé
- **Mises à jour non reçues** (cause identifiée) : le dépôt des sources est PRIVÉ — l'API GitHub renvoie 404 aux boutiques sans token, donc aucune version n'était jamais détectée. Le plugin consulte maintenant en priorité un **dépôt public dédié aux releases** (`derouicheoussama/infinitycod-releases`, zips uniquement, aucune source) : les clients reçoivent les mises à jour **sans aucun token**. Le dépôt privé + token reste utilisable en repli.
- **Diagnostic visible** : notice admin « mises à jour indisponibles » quand le dépôt est privé sans token, avec lien direct vers la configuration.
- Nouveau script `node tools/publish-releases.js` : publie le zip de la version courante sur le dépôt public en une commande.

## 1.4.0 — 2026-09-08

### Modifié
- **Suppression du repli factexpert.online** dans les mises à jour : la seule source est GitHub Releases (dépôt public des releases). Rien à voir avec votre plugin n'y transite plus.
- **Publication 100 % automatique** : un simple `git push --tags` construit le zip et le publie sur le dépôt public des releases (secret RELEASES_TOKEN configuré via CI, script tools/set-releases-secret.js). Plus aucune commande manuelle.
- **Mise à jour automatique activée par défaut** chez les clients (désactivable dans Réglages → Avancé).
- Le serveur d'activation des licences est désormais un champ modifiable (Réglages → Avancé) au lieu d'être figé dans le code.

## 1.5.0 — 2026-09-08

### Ajouté
- **Onglet « Commande »** dans les réglages : message de remerciement personnalisé (titre + texte, variable {num}), **redirection personnalisée après la commande** (url + délai 3-60 s, pendant lequel le client voit le remerciement et les upsells), **upsell** : jusqu'à 3 produits suggérés (photo, prix, bouton Commander) affichés sur l'écran de succès.
- Écran de succès enrichi : numéro de commande + montant total affichés.
- **UX admin** : checklist de configuration avec progression sur le tableau de bord (transporteur, WhatsApp, paiement, première commande), mini-cartes KPI (en attente / confirmées / expédiées / livrées) au-dessus du tableur des commandes, bouton « Enregistrer » toujours visible (sticky) sur les longues pages de réglages.

## 1.5.1 — 2026-09-08

### Corrigé
- **Fatal error sur la page Réglages (v1.5.0)** : la méthode `tab_order()` était appelée par le switch d'onglets mais jamais définie (insertion de patch partielle). Le harnais de test couvre désormais **les 7 onglets de réglages** — ce type de régression ne peut plus passer inaperçu.

### Ajouté
- **Intégration professionnelle dans l'écran Extensions** : liens « Réglages » et « Commandes » directement sur la ligne du plugin, méta « Site web » et « Nouveautés », et fiche **« Voir les détails »** complète (description détaillée, installation pas à pas, FAQ, changelog) comme une fiche WordPress.org.
- **Accueil d'installation pro** : après l'activation, redirection automatique vers le tableau de bord InfinityCod avec la checklist de configuration.

## 1.6.0 — 2026-09-08

### Corrigé
- **Fatal error dans le moteur de mises à jour** (v1.5.1) : la méthode interne `remote()` avait été perdue lors d'une réécriture — `inject_update()` plantait sur plugin-install.php. Le harnais exécute maintenant `inject_update()` et `plugin_info()` directement, ce fatal ne peut plus repasser.

### Ajouté
- **Position du formulaire sur la fiche produit** (Réglages → Formulaire) : avant/après résumé, après le prix, après la description courte, avant/après le bouton ajouter au panier, fin de fiche.
- **Commande via WhatsApp** (Réglages → WhatsApp) : bouton optionnel dans le formulaire — la commande est créée normalement puis WhatsApp s'ouvre pré-rempli vers votre numéro avec le résumé (template personnalisable : {num}, {nom}, {telephone}, {produit}, {total}, {wilaya}, {commune}).
- **Restrictions anti-abus étendues** (Réglages → Anti-fraude) : horaires d'ouverture des commandes (ex. 9h-22h), limites 24 h par **IP**, par **téléphone** et par **email** (nouveau champ email facultatif activable, intégré à la commande WooCommerce et à la blacklist).

## 1.7.0 — 2026-09-08

### Modifié
- **Suppression totale de factexpert.online** : aucune référence restante dans le code, la documentation ou le README. Le serveur d'activation des licences pointe par défaut vers `infinitycoder.app` et reste modifiable dans Réglages → Avancé.
- **Interface admin repensée** : onglets avec accent vert, cartes à en-tête accentué et ombre douce, **interrupteurs modernes** (style iOS, RTL inclus), focus verts InfinityCod sur tous les champs, bouton « Enregistrer » repensé, onglets défilables sur mobile.
- **Nouveau réglage** : icône de l'en-tête du formulaire (emoji, par défaut 🛒).

### Qualité
- **Audits automatiques intégrés** (`npm run check`) : chaque champ de réglages est vérifié côté sauvegarde ET valeurs par défaut (73 champs), chaque clé `Settings::get()` doit avoir un défaut (80 clés), scan anti-corruption de namespaces et de variables échappées sur tous les fichiers PHP.

## 1.7.1 — 2026-09-08

### Corrigé
- **Champ « Serveur de licences » resté sur factexpert.online** : la valeur était sauvegardée dans la base du site, donc le nouveau défaut ne s'affichait pas. Migration automatique à la mise à jour : tout champ pointant vers factexpert.online est réécrit vers `https://infinitycoder.app/api.php`, avec défense au niveau de la lecture (impossible que le plugin l'utilise, même avec une ancienne valeur en base).
- **Migrations de base de données jamais exécutées sur les installations mises à jour** (bug critique latant) : le hook `maybe_upgrade` n'était enregistré que pendant la requête d'activation. Il tourne maintenant à chaque requête admin — la colonne `email` manquante (1.6.0) et toutes les futures migrations s'appliquent automatiquement.

# InfinityCod 2.0.0 — Commercial Edition

Transformation en produit commercial professionnel : chaîne de release sécurisée, rollback réel, diagnostics, logs, migrations versionnées.

## 2.0.0 — 2026-09-08

### Added
- **Update Center** (InfinityCod → Mises à jour) : version installée vs disponible, canal stable/beta, compatibilité, intégrité, mise à jour 1 clic, **rollback réel** depuis les sauvegardes locales, historique des mises à jour.
- **Diagnostics** (InfinityCod → Diagnostics) : 15 contrôles PASS/WARNING/FAIL (PHP, WordPress, WooCommerce, MySQL, mémoire, REST, WP-Cron, filesystem, tables, migrations, licence, updater) + rapport téléchargeable sans secrets.
- **Sécurité des mises à jour** : vérification **SHA-256** du package avant installation (manifest update.json), **blocage de compatibilité** PHP/WordPress avec message explicite, **sauvegarde automatique** de la version courante (fichiers + réglages) avant mise à jour, rétention 3 sauvegardes.
- **Système de logs** : catégories license/update/security/migration/api/error/diagnostic, fichiers mensuels protégés (uploads/infinitycod-logs/), nettoyage cron 60 jours, aucun secret journalisé.
- **Migrations versionnées** : registry idempotent (option infinitycod_migrations), exécutées une seule fois, loggées.
- **CI/CD pro** : workflow CI (push/PR : lint, autoloader, audits, scan secrets, build test), workflow Release durci (jobs test → build → release, gate version tag=header via tools/version.js), Dependabot, CODEOWNERS.
- **Anti CSV-injection** sur l'export des commandes.

### Fixed
- Migrations de base de données jamais exécutées après activation (hook mal enregistré) — réparation + colonne email ajoutée automatiquement sur les installations concernées.

### Security
- Audit complet : ABSPATH sur tous les fichiers, 0 eval/exec/system/unserialize, 0 AJAX nopriv, capability manage_woocommerce sur tous les handlers, $wpdb->prepare systématique.

## 2.1.0 — 2026-09-08

### Added
- **Signature cryptographique Ed25519 du manifest de mise à jour** : la CI signe `update.json` (clé privée en GitHub Secret — jamais dans le plugin), le plugin vérifie la signature via sodium/sodium_compat avant d'accepter un SHA-256. Manifest falsifié = mise à jour non proposée + log sécurité.
- **Attribution professionnelle complète** (§107-129) : headers auteur/copyright sur les 42 fichiers PHP, attribution JS/CSS, header plugin `Author: Derouiche Oussama` + `https://derouicheoussama.com`, constantes centralisées, section Developer (À propos, README, docs), contrôle `tools/check-attribution.js` intégré à CI et `npm run check`.

## 2.1.1 — 2026-09-08

### Added
- **Repli de mise à jour sur infinitycoder.app** : si l'API GitHub est injoignable depuis l'hébergeur du client (réseau restreint ou rate limit), le plugin consulte automatiquement `https://infinitycoder.app/updates/infinitycod.json` (même format que le manifest du build). Déposez simplement `dist/update.json` sur ce domaine pour activer le repli.
- **Diagnostics enrichis** : le contrôle updater affiche maintenant la source primaire (GitHub) ET le repli (infinitycoder.app) séparément, la **raison précise** d'un échec (rate limit, dépôt privé, signature invalide…), plus un bouton **« Tester la connexion »** qui enregistre le résultat du test.

### Improved
- Message d'aide dans Réglages → Avancé pour le repli manifest.

## 2.1.2 — 2026-09-08

### Fixed
- **Aucune mise à jour détectée (cause racine)** : l'URL de l'API GitHub encodait le slash du dépôt (`owner%2Frepo`) via rawurlencode — l'API répondait 404 en permanence, même avec un dépôt public et des releases publiées. L'URL est maintenant construite directement (dépôt déjà validé par regex) et un test anti-régression couvre ce cas dans le harnais.
- Après cette correction, le WARNING « Updater injoignable » dans Diagnostics disparaît et les mises à jour sont détectées depuis GitHub.
