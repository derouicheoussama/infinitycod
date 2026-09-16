=== InfinityCod — Paiement à la livraison (COD Algérie) ===
Contributors: derouicheoussama
Tags: cod, algeria, woocommerce, delivery, checkout
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 5.34.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

All-in-one Cash on Delivery checkout for WooCommerce in Algeria: one-page form, 58 wilayas, carriers, WhatsApp, online payments.

== Description ==

InfinityCod turns your WooCommerce store into a Cash on Delivery selling machine for Algeria.

= One-page COD form =
A mobile-first one-page order form that replaces the classic WooCommerce checkout. The customer fills in name, phone, wilaya and municipality — that's it.

* 8 visual themes (Modern, Elegant, Sunset, Ocean, Minimal, Rose, Royal, Café)
* Automatic dark mode
* Full Arabic RTL support
* Adjustable form position on the product page
* Product thumbnail and live price

= Algeria geography =
* 58 official wilayas (French and Arabic names)
* 1541 municipalities
* Home and stopdesk rates per wilaya and per municipality
* Free shipping by amount or quantity

= Anti-fraud Shield =
* Risk score 0-100 per order
* Phone, IP and email blacklists
* Duplicate detection
* IP and browser fingerprint limits
* Order time restrictions

= Built-in carriers =
Yalidine, ZR Express, Maystro, Noest Express, E-COM Delivery, DHD Livraison.
One-click parcel creation and automatic tracking.

= WhatsApp automation =
Order, shipping and abandoned cart recovery notifications via WhatsApp Cloud API, UltraMsg or wa.me links.

= Online payment =
CIB and Edahabia cards via Chargily Pay (optional, free).

= P&L statistics =
Revenue, confirmation/return/delivery rates, average cart, charts per wilaya, carrier and product.

= Quantity offers =
Discount tiers such as "2 = -10%, 3 = -15%", global or per product.

== Installation ==

1. Upload `infinitycod.zip` via Plugins → Add New → Upload
2. Activate the plugin
3. The 58 wilayas and 1541 municipalities are imported automatically
4. Go to InfinityCod → Wilayas & Rates to set your prices
5. The form appears by itself on your product pages

== Frequently Asked Questions ==

= Does it work with my theme? =
Yes: styles are isolated and tested with Astra, Flatsome, WoodMart, Divi, OceanWP, GeneratePress and the default themes.

= How do I receive updates? =
Updates are automatic. No configuration needed.

= Can I disable the form on some pages? =
Yes, use the `[infinitycod_form]` shortcode for full control.

= Is the online payment secure? =
Yes, the customer pays on the secure Chargily page. Confirmation is verified by API and by a signed webhook.

== Screenshots ==

1. One-page COD form — desktop view
2. Automatic dark mode
3. E-commerce style / advanced customization

== Changelog ==

= 5.34.0 =
* Performance : index composite (statut + date) sur les commandes — listes instantanées même avec des dizaines de milliers de lignes.
* Performance : cache 3 minutes des KPI du tableau de bord et des statistiques, invalidé à chaque mouvement de commande.
* Design : animations douces (modale, toasts), survols et focus clavier visibles, barres de défilement fines, respect du mode « mouvement réduit » côté admin et formulaire.

= 5.33.0 =
* Nouveau : module Anti-leak — confidentialité des clients et des exports.
* Garde pixel : l’achat n’est plus envoyé au navigateur (Advanced Matching Meta/TikTok coupé), seule la Conversions API serveur signe la conversion — les campagnes optimisent sans exposer les acheteurs.
* Masquage des téléphones dans la liste Commandes (option).
* Journal des exports avec filigrane de traçabilité (colonne Trace) et alerte e-mail dès un export massif (seuil réglable).
* Vérification d’intégrité des fichiers du plugin avec avis admin en cas de copie modifiée/piratée.

= 5.32.0 =
* UX Commandes : avatar client, numéro de commande, temps relatif sur la date, bouton WhatsApp direct sur chaque ligne.
* UX modale détails : chronologie visuelle de la commande (créée → confirmée → expédiée → livrée) et impression du bordereau individuel.

= 5.31.1 =
* Nouveau : vrais logos E-COM Delivery et DHD Livraison (remplacent les visuels provisoires) — priorité PNG dans l'affichage et nettoyage automatique des anciens fichiers à la mise à jour.
* Design : page Transporteurs repensée — boîte de logo normalisée, pastille de statut « Connecté / Non configuré », mention du réseau (API directe / Ecotrack).

= 5.31.0 =
* Nouveau : tarifs par paliers de poids par wilaya (1-5 kg, 5-10 kg, /kg au-delà) — calcul serveur et affichage formulaire.
* Nouveau : zones régionales (Centre/Est/Ouest/Sud en 1 clic) avec tarifs de repli pour les wilayas sans tarif personnalisé.
* Nouveau : duplication des tarifs d'une wilaya vers les lignes vides + import/export CSV étendus (paliers, délais, retours).
* Nouveau : wilaya suspendue — désactivable en un clic, affichée « indisponible » et bloquée côté serveur.
* Nouveau : A/B test natif du formulaire (variante B : titre/bouton/couleur) avec taux de conversion par variante.
* Nouveau : confirmation client en 1 clic depuis WhatsApp (lien signé, commande passée en confirmée).
* Nouveau : webhooks sortants signés HMAC (Google Sheets, Zapier, Make) + synthèse Telegram du rapport hebdo.
* Nouveau : blacklist communautaire opt-in + historique de retours par numéro dans l'anti-fraude.
* Nouveau : impression en lot des bordereaux de livraison depuis Commandes.
* Nouveau : export/import JSON de la configuration (backup, duplication boutique) + provision des frais de retour dans le P&L.

= 5.30.4 =
* Nouveau : menu latéral restructuré en 4 groupes (Pilotage, Vente & Livraison, Analyse, Système) avec séparateurs visuels non cliquables.
* Nouveau : badges de compteurs en direct — commandes en attente sur « Commandes », paniers ouverts sur « Paniers abandonnés » (caches 10 minutes).
* Design : item actif souligné par une barre d'accent, badges alignés, lisibilité accrue dans tous les schémas couleur de l'admin.

= 5.30.3 =
* Nouveau : anti-doublon de formulaire sur les fiches produit — quand le plugin gère la fiche, le moteur COD intégré aux thèmes Infinity (inf_cod) est automatiquement retiré : un seul formulaire de commande est généré.

= 5.30.2 =
* Fix : Plugin Check — commentaire traducteurs manquant (compteur de visiteurs), entrees $_SERVER nettoiees (IP, user-agent), nom du readme aligne sur l'en-tete du plugin.
* Fix : page Transporteurs — statut « Connecte » fiable et bouton « Importer les bureaux » lie au bon transporteur.

= 5.29.0 =
* Mini-stats dashboard widget
* Minified CSS/JS assets
* Full-width form position and 2-column desktop checkout

= 5.28.0 =
* Container queries: the form adapts to its real container width
* Screen simulator in the live preview (Mobile / Tablet / PC)
* Automatic cache purge after plugin updates

= 5.27.0 =
* Full-width form position (default)
* Section titles inside the form
* Migrations run without requiring an admin visit

== Upgrade Notice ==

= 5.29.0 =
Mini-stats dashboard widget and lighter assets.

== External services ==

* Updates: hourly check on github.com (public releases repository). No customer data is sent.
* Online payment (optional): Chargily Pay (chargily.com) — enabled only by the merchant.
* Google Sheets (optional): orders sent to the Apps Script URL configured by the merchant.
* WhatsApp / Discord / Telegram (optional): notifications configured by the merchant.

== Arbitrary section ==

* Developer: Derouiche Oussama — https://derouicheoussama.com
* Product: InfinityCod — https://infinitycoder.app
* Updates: GitHub Releases (derouicheoussama/infinitycod-releases)
