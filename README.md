# Plugins COD Algérien — InfinityCod

Dépôt des plugins WordPress **InfinityCod** pour le e-commerce COD (paiement à la livraison) en Algérie.

Produit principal : `infinitycod/` — **InfinityCod — Paiement à la livraison (COD Algérie)**, extension WooCommerce tout-en-un qui dépasse les solutions existantes (Yaxii Smart Form, COD Formulaires…).

## Fonctionnalités

| Module | Description |
|---|---|
| Formulaire COD | Formulaire de commande one-page sur la fiche produit (shortcode, bloc Gutenberg, Elementor) — mobile-first, RTL, dark mode |
| Géo Algérie | 58 wilayas + 1541 communes officielles (FR/AR), tarifs domicile/stopdesk par wilaya avec override par commune |
| Anti-fraude Shield | Honeypot, temps minimum, fingerprint, limitation par IP, blacklist téléphones, score de risque, détection de doublons |
| Dashboard COD | Tableur de commandes responsive, filtres, actions groupées, export |
| Transporteurs | Création de colis + suivi synchronisé : Yalidine, ZR Express, Maystro, Noest (Ecotrack), Guepex, E-COM, DHD |
| WhatsApp auto | Messages automatiques (commande reçue, expédiée) + relance automatique des paniers abandonnés |
| Offres | Remises par quantité globales et par produit, livraison gratuite à partir de X |
| Stats P&L | CA, taux de confirmation/retour par wilaya, produit, transporteur, marge nette |

## Prérequis

- PHP 7.4+ (compatible 8.3)
- WordPress 6.0+
- WooCommerce 6.0+

## Développement

```bash
# Validation syntaxe de tous les fichiers PHP
find infinitycod -name '*.php' -exec php -l {} \;

# Construire le zip de diffusion (nécessite Node)
node tools/build.js
```

Le zip produit `dist/infinitycod.zip` installable depuis wp-admin → Extensions → Ajouter → Téléverser.

## Structure

```
infinitycod/          Plugin zip-able
tools/                Scripts (build)
docs/                 Documentation produit
```

## Liens

- Site : https://infinitycoder.app
- Licences : activation via infinitycoder.app (clé INFINITY-DEV pour le développement)

© Infinity Coder — Oussama Derouiche. Licence GPL-2.0-or-later (code), données géographiques open data.
