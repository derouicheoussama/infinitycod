=== InfinityMarket ===
∞ Infinity Coder — Copyright © 2026 Derouiche Oussama
Site : https://www.derouicheoussama.com
Tags : e-commerce, blog, one-column, two-columns, custom-logo, custom-menu, featured-images, translation-ready, block-styles, wide-blocks
Requires at least : 6.0
Tested up to : 6.7
Requires PHP : 7.4
Stable tag : 2.0.0
License : GPLv2 or later
License URI : http://www.gnu.org/licenses/gpl-2.0.html

Thème e-commerce pour le marché algérien : formulaire de commande « Paiement à la
livraison » (COD) avec les 58 wilayas, tableau de bord admin bleu Infinity, mises à
jour automatiques, 100 % responsive, compatible WooCommerce.

== Description ==

InfinityMarket est un thème e-commerce conçu pour le marché algérien :

* Formulaire COD (paiement à la livraison) : nom, téléphone, wilaya (58), commune,
  type de livraison (domicile / bureau Stopdesk), quantité, total calculé en direct.
* Commandes stockées dans l'admin : statuts (nouveau, confirmé, expédié, livré, annulé),
  e-mail de notification, export CSV.
* Tableau de bord admin bleu Infinity : statistiques, réglages boutique, frais de
  livraison par wilaya, licence, à propos.
* Mises à jour automatiques via manifeste JSON (GitHub ou votre serveur) + clé de licence.
* Sécurité : nonce, honeypot, anti-temps, limite par IP, prix signé (HMAC), calcul serveur.
* Responsive mobile-first, prêt pour l'arabe (RTL), compatible WooCommerce.

Shortcode du formulaire : [infinity_cod product="Nom du produit" price="2500" old_price="3500" image="URL"]

== Installation ==

1. Apparence → Thèmes → Ajouter → Téléverser un thème → activer.
2. Menu InfinityMarket dans l'admin : réglez téléphone, WhatsApp, réseaux sociaux.
3. Livraison : ajustez les frais des 58 wilayas (domicile / bureau).
4. (Optionnel) Installez WooCommerce pour le panier et la grille boutique.

== Frequently Asked Questions ==

= Le formulaire fonctionne-t-il sans WooCommerce ? =
Oui. Le formulaire COD est autonome (shortcode ou fiche produit WooCommerce).

= Comment recevoir les mises à jour ? =
La page Licence & Mises à jour explique le canal : pointez INF_UPDATES_API (constante
de functions.php) vers un manifeste JSON hébergé (GitHub raw recommandé).

== Changelog ==

= 2.0.0 =
* Refonte professionnelle du design :
  - En-tête 3 niveaux : bandeau services, grande recherche par catégorie,
    blocs compte/panier (avec total), menu « Tous les rayons » (mega panel).
  - Page d'accueil : slider éditable (3 diapositives) + cartes d'offres
    latérales avec compte à rebours « vente flash », catégories populaires
    rondes avec photos, sections « Meilleures offres » / « Nouveautés »,
    articles de blog, témoignages.
  - Cartes produit enrichies : badges (promo %, Top, Nouveau), note étoiles,
    état de stock, actions rapides au survol (panier / voir), zoom image.
  - Pied de page riche : 4 colonnes (marque, rayons, informations, contact),
    badges de paiement (COD, CIB, EDAHABIA).
  - Barre d'outils mobile fixe : Accueil, Rayons, Recherche, Panier, Contact.
* Réglages : slider (3 diapositives) et vente flash configurables dans le
  tableau de bord.

= 1.1.0 =
* Personnalisation complète (Personnaliser → ∞ Apparence + Réglages) : palettes de
  couleurs, couleur d'accent personnalisée, polices français/arabe (Cairo, Tajawal,
  Noto Kufi, Amiri…), taille du texte, couleur et image de fond, largeur du site,
  arrondis des blocs, style du menu (dégradé/uni/bleu nuit), pied de page clair/sombre.
* Bouton WhatsApp flottant (activable/désactivable).
* Responsive renforcé : cibles tactiles 44 px, ajustements 380-640 px, texte auto iOS.
* Correctif : la sauvegarde d'une page de réglages n'efface plus les champs des autres pages.

= 1.0.0 =
* Lancement : dashboard bleu Infinity, moteur COD 58 wilayas, mises à jour
  automatiques, WooCommerce, RTL, responsive.

== Credits ==

* Conçu et développé par Derouiche Oussama — ∞ Infinity Coder, https://www.derouicheoussama.com
* Basé sur les standards WordPress. GPL v2 ou ultérieure.
