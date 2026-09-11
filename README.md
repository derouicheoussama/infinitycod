# InfinityCod — Le plugin COD #1 pour WooCommerce (Algérie & marché arabe)

> 🔒 **Protégé par le DMCA** — plugin protégé. Toute copie, distribution ou utilisation non autorisée fera l’objet d’une plainte DMCA.


> **Paiement à la livraison tout-en-un** : formulaire de commande express, géographie complète, anti-fraude, transporteurs, WhatsApp automatique, pixels publicitaires avec Conversions API, multi-devise et multi-pays.

**Développeur : [Derouiche Oussama](https://derouicheoussama.com) — Infinity Coder** · Site : [infinitycoder.app](https://infinitycoder.app)

---

## ⚡ Pourquoi InfinityCod

Le COD (paiement à la livraison) représente plus de 90 % du e-commerce en Algérie et une part majeure du marché arabe. Les tunnels de commande WooCommerce classiques font perdre 40 à 70 % des commandes. **InfinityCod remplace le panier par un formulaire one-page optimisé mobile-first**, conçu pour le client algérien et arabe : rapide, rassurant, anti-fraude, connecté à vos transporteurs et à vos publicités.

## 🧩 Fonctionnalités

### Formulaire COD (le cœur)
- **One-page mobile-first** : nom, téléphone, wilaya, commune, mode de livraison — commande en 20 secondes
- **9 thèmes** : Moderne, Élégant, Sunset, Océan, Minimal, Rose, Royal, Café, **Aqua**
- **Icônes sur tous les champs**, variantes produit en boutons-puces, auto-sélection intelligente
- **Prix live** dans l'en-tête (promos barrées), récapitulatif toujours visible, barre « Commander maintenant » collante sur mobile
- **Mode sombre** automatique + **RTL arabe complet avec police Cairo**
- 5 **écrans de remerciement** animés avec récapitulatif détaillé (Classique, Confettis, Minimal, Ticket, Célébration)
- Shortcode `[infinitycod_form]`, insertion automatique sur fiche produit, widget Elementor
- **Code promo** : réutilise vos coupons WooCommerce natifs, validation serveur

### Géographie & livraison
- **Algérie complète** : 58 wilayas + **1541 communes officielles** (FR + AR)
- **Multi-pays Premium** : Maroc, Tunisie, Égypte, Arabie Saoudite, Émirats (régions préchargées, ville saisie libre)
- Tarifs **domicile / stopdesk** par wilaya **et par commune**, livraison gratuite (montant ou quantité), délais, commande minimum, supplément poids
- Import des **bureaux stopdesk** Yalidine/ZR en 1 clic

### Anti-fraude Shield
- Honeypot, empreinte navigateur, limites IP/téléphone/email (heure & jour), temps minimum de remplissage, horaires d'ouverture
- **Score de risque** par commande (0-100) + drapeaux, blacklist manuelle et automatique

### Transporteurs (Premium)
- **Yalidine, ZR Express, Maystro, Noest, E-COM, DHD, Guepex**
- Création de colis depuis le dashboard, **suivi synchronisé** automatique (cron)

### WhatsApp (Premium)
- Messages automatiques : commande reçue, expédiée — passerelles **Cloud API / UltraMsg / wa.me**
- **Relance automatique des paniers abandonnés** (délai + nombre de relances)
- Bouton « Commander via WhatsApp » avec résumé pré-rempli

### 🎯 Tracking & publicité
- **Pixels Meta (Facebook/Instagram), TikTok, Snapchat**
- Funnel complet : `ViewContent → InitiateCheckout → Purchase` avec montants réels
- **Conversions API Meta conforme 2026** : déduplication par `event_id`, téléphone haché SHA-256, cookies `_fbp`/`_fbc`, `action_source`
- Code d'événements de test, consentement optionnel, portée produit ou sitewide

### Paiement en ligne
- **Chargily Pay v2** : **CIB** et **Edahabia** avec logos affichés au client
- COD + carte en parallèle, BaridiMob / CCP annoncés (bientôt)

### Pilotage
- Dashboard commandes : **modale complète** (détails, modification client/destination/quantité, tous les statuts, WhatsApp en 1 clic), export CSV, blacklist
- Paniers abandonnés + relances
- **Statistiques P&L** (Premium) : CA, taux de confirmation/retour par wilaya/transporteur/produit, marge

### Mises à jour & fiabilité
- **Mises à jour automatiques depuis GitHub** : 4 sources en cascade (miroir personnalisé → raw.githubusercontent → CDN jsDelivr → API GitHub)
- **Signature Ed25519** du manifeste + **SHA-256** du package vérifiés avant installation
- Rollback vers les sauvegardes locales, **Diagnostic complet** (15+ contrôles) et **diagnostic par source**
- Page **À propos** (nos plugins), écran de **bienvenue** à l'activation

### Marché arabe
- **Multi-devise** : DZD, MAD, TND, EGP, SAR, AED, QAR, KWD, JOD, IQD, LYD, OMR, BHD, MRU, SDG, SYP, YER, EUR, USD — appliquée au formulaire, aux commandes WooCommerce, aux pixels et aux dashboards
- **RTL natif** + police **Cairo** en arabe, interface admin traduite

## 📦 Installation

1. Téléchargez `infinitycod.zip` depuis les [Releases](https://github.com/derouicheoussama/infinitycod-releases/releases)
2. WordPress → **Extensions → Ajouter → Téléverser** → Activer
3. Suivez l'**écran de bienvenue** : wilayas & tarifs → formulaire → première commande

**Prérequis** : WordPress 6.0+, WooCommerce 6.0+, PHP 7.4+

## ⚙️ Mises à jour

Les mises à jour sont publiées sur le dépôt public [infinitycod-releases](https://github.com/derouicheoussama/infinitycod-releases/releases) et détectées automatiquement par WordPress (notification + mise à jour en 1 clic, ou entièrement automatique selon votre réglage). Intégrité vérifiée par SHA-256 + signature Ed25519. En cas de blocage hébergeur : miroirs CDN, **miroir personnalisé** (Réglages → Avancé), ou installation manuelle depuis **InfinityCod → Mises à jour**.

## 🛠️ Développement

```bash
node tools/build.js        # construit dist/infinitycod.zip + manifeste signé
npm run check              # lint, smoke, audits, attribution
php tools/test-activation.php  # harnais d'activation complet
```

## 🗺️ Roadmap

- Paiement BaridiMob / CCP (virement manuel guidé)
- Événements serveur TikTok Events API
- Plus de pays du marché arabe

## 📄 Licence

GPL-2.0-or-later — conforme aux directives wordpress.org.

## 👤 Auteur

**Derouiche Oussama** — Infinity Coder
- Site : [derouicheoussama.com](https://derouicheoussama.com) · [infinitycoder.app](https://infinitycoder.app)
- GitHub : [@derouicheoussama](https://github.com/derouicheoussama)

© Derouiche Oussama — Infinity Coder. Licence GPL-2.0-or-later (code), données géographiques open data.
