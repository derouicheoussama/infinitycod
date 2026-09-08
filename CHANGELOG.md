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
