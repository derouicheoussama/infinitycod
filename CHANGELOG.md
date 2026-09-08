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
