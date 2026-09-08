# InfinityCod — Guide d'utilisation

## 1. Installation

1. Construire le zip : `node tools/build.js` → `dist/infinitycod.zip`
2. Dans WordPress : **Extensions → Ajouter → Téléverser** → sélectionner le zip → **Activer**
3. Prérequis : WooCommerce actif (sinon un avertissement s'affiche)
4. À l'activation : les 7 tables sont créées et les **58 wilayas + 1541 communes** sont importées automatiquement

## 2. Premiers réglages (5 minutes)

### Tarifs de livraison
**InfinityCod → Wilayas & Tarifs**
- Définissez les tarifs par défaut : Domicile (ex. 600 DA) / Stopdesk (ex. 350 DA)
- Optionnel : tarif spécifique par wilaya (champ vide = hérite du défaut)
- Optionnel : tarif par commune, désactiver une wilaya/commune, livraison gratuite à partir de X articles

### Formulaire
**InfinityCod → Réglages → Formulaire** : titre, texte du bouton, couleur de marque, thème clair/sombre/auto, barre collante mobile, quantité max.

Le formulaire s'insère **automatiquement sur chaque fiche produit** sous le résumé. Alternatives :
- Shortcode : `[infinitycod_form]` (produit courant) ou `[infinitycod_form id="123"]`
- Elementor / Divi : widget « Shortcode » avec le shortcode ci-dessus
- Bloc Gutenberg (shortcode)

### Anti-fraude (Shield)
**Réglages → Anti-fraude** : honeypot, temps minimum de remplissage, limite par IP/heure, blacklist téléphones, doublons. Le **score de risque** de chaque commande s'affiche dans le tableau (badge Faible/Moyen/Élevé).

## 3. Traiter les commandes

**InfinityCod → Commandes COD** : tableur filtrable (statut, wilaya, recherche nom/téléphone, période).
- Actions rapides par ligne : ✓ confirmer, 📦 expédier, ✕ annuler (clic sur 🔍 pour le détail complet)
- Actions groupées : cocher des lignes → Confirmer / Sans réponse / Annuler / Livrée / Retournée / Blacklister
- **Export CSV** compatible Excel (BOM UTF-8, séparateur `;`)

Chaque commande COD est aussi une **vraie commande WooCommerce** (statuts synchronisés, stock décrémenté).

## 4. Expédier via les transporteurs (Premium)

**InfinityCod → Transporteurs → Connexions API**
- Yalidine : API-ID + jeton (tableau de bord Yalidine → Menu → API)
- ZR Express / Maystro : clé API
- Noest, E-COM, DHD (réseau Ecotrack) : jeton + User GUID
- Bouton **Importer les bureaux Stopdesk** (Yalidine) : les bureaux remplissent automatiquement la liste « Bureau de retrait » du formulaire

**Transporteurs → Créer des colis** : les commandes confirmées y attendent ; choisissez le transporteur → **Créer le colis** → le numéro de suivi est enregistré, la commande passe en Expédiée. Le suivi se synchronise **toutes les heures** (statuts livré/retour/échec automatiquement mis à jour).

## 5. WhatsApp automatique (Premium)

**Réglages → WhatsApp** : choisir la passerelle :
- **wa.me** (gratuit) : les messages sont préparés, vous les ouvrez en un clic depuis « Paniers abandonnés »
- **WhatsApp Cloud API** (officiel Meta) : envoi 100 % automatique (token + phone ID)
- **UltraMsg** : alternative simple (instance + clé)

Messages configurables avec variables : `{nom}`, `{telephone}`, `{commande}`, `{total}`, `{suivi}`, `{produit}`.
- Commande reçue → message de remerciement automatique
- Colis créé → notification d'expédition avec suivi
- **Paniers abandonnés** : relance automatique (délai + nombre paramétrables) + relance manuelle en un clic

## 6. Licences

**Réglages → Licence** : saisissez votre clé (format `INFINITY-XXXX-…`).
- **Gratuit** : formulaire COD complet, 58 wilayas/1541 communes, tarifs, anti-fraude, dashboard commandes, export
- **Premium** : WhatsApp automatique, transporteurs intégrés, statistiques P&L, offres par quantité
- Clé de développement : `INFINITY-DEV` (tests)
- Vérification hebdomadaire, tolérance hors-ligne (statut UNKNOWN)

## 7. Statistiques (Premium)

**InfinityCod → Statistiques P&L** (7/30/90 jours) : CA encaissé, CA confirmé, taux de confirmation/retour/livraison, panier moyen, graphique journalier, répartitions par wilaya / transporteur / produit.

## 8. Offres par quantité (Premium)

Fiche produit → métabox **InfinityCod — Offres** : paliers au format `2=10, 3=15, 5=20` (quantité = remise %). Sans palier personnalisé, les paliers globaux s'appliquent (filtre `infinitycod_offers_global_tiers`). Les remises sont **toujours recalculées côté serveur** et appliquées dans la commande WooCommerce.

## 9. Assistance technique

- Vérifier la syntaxe PHP du plugin : `node tools/lint-php.js`
- Test d'autoloading : `node tools/smoke.js`
- Régénérer les traductions : `node tools/make-pot.js`
- Construire le zip : `node tools/build.js`

© Infinity Coder — Oussama Derouiche, 2026.
