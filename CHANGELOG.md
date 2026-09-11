# Changelog

## 5.25.3 — 2026-09-11

Page Transporteurs : grille 2 colonnes, logos des sociétés de livraison, statuts fiables.

### Corrigé
- **Statut de connexion toujours « Non configuré »** : une variable fantôme ($carriers au lieu de $manager) forçait l'affichage « Non configuré » sur toutes les cartes, même pour un transporteur connecté. Le badge « ✓ Connecté » est de nouveau fiable.
- **Logo ZR Express jamais chargé** : le fichier s'appelait zr-express.svg alors que le code cherche zrexpress.svg — renommé.
- **Import des bureaux Stopdesk** : le bouton était toujours branché sur Yalidine, même sur les cartes d'autres transporteurs — désormais branché sur le transporteur de la carte.

### Amélioré
- **Grille 2 colonnes** : les cartes transporteurs s'affichent en 2×2 (une colonne en dessous de 1100 px).
- **Logos des sociétés de livraison redessinés** en vectoriel aux couleurs de marque (Yalidine bleu, ZR Express rouge, Maystro indigo, Noest navy/orange, E-Com bleu, DHD vert) — nets sur tous les écrans, et remplaçables par les fichiers officiels en déposant {code}.svg dans assets/front/img/carriers/.


## 5.25.2 — 2026-09-11

Arrondis 100 % harmonisés dans le formulaire et page Paniers abandonnés repensée.

### Modifié
- **Arrondis unifiés de bout en bout** : le message d'avertissement, la barre « livraison gratuite », le bloc « Total à payer » et le bouton « Appliquer » du code promo suivent désormais le même réglage « Arrondi des champs » que tous les autres champs — plus aucun élément du formulaire ne détone.

### Amélioré
- **Page Paniers abandonnés repensée** :
  - **KPI « Valeur en jeu »** : la somme des paniers ouverts s'affiche en tête — l'argent à aller chercher en un coup d'œil.
  - **Taux de récupération** sur la carte Récupérés.
  - **Filtres par statut** (Tous / En attente / Récupérés / Archivés) avec compteurs, façon WordPress.
  - **Statuts en français** avec badges colorés (En attente · Récupéré · Archivé) au lieu des clés techniques.
  - **Temps relatif** sur chaque ligne (« il y a 12 min ») en plus de la date.
  - **Téléphone cliquable** : le numéro ouvre directement WhatsApp avec le message de relance pré-rempli.
  - **Bannière d'activation** si la relance automatique est désactivée, avec lien direct vers le réglage.
  - **État vide soigné** avec explication du fonctionnement.


## 5.25.1 — 2026-09-11

Interface du formulaire affinée : en-tête épuré, champ Quantité redessiné, arrondis parfaitement uniformes.

### Modifié
- **Icône panier de l'en-tête supprimée** : elle occupait un carré pour rien. Elle devient optionnelle — si vous voulez une icône, renseignez-la dans Réglages → Formulaire → « Icône » (vide par défaut = aucune).
- **Vignette produit agrandie** : 68 px sur desktop (au lieu de 56), 52 px sur mobile — le produit est bien visible dans l'en-tête.
- **Champ Quantité restylé en contrôle segmenté** : un seul bloc arrondi `− │ 1 │ +` avec séparateurs fins, à la couleur d'accent — fini les deux gros boutons ronds flottants. Le bloc utilise exactement le même arrondi que les autres champs.
- **Arrondis unifiés** : les boîtes « À domicile / Au bureau » et le champ code promo suivent désormais le même arrondi que tous les champs (réglage « Arrondi des champs » s'applique partout).


## 5.25.0 — 2026-09-11

Audit de sécurité complet du plugin (style revue de code automatisée) + scanner permanent.

### Sécurité
- **Secrets jamais renvoyés dans le HTML** : les 8 champs de clés secrètes (Chargily, reCAPTCHA v3, Conversions API Meta, Telegram, Cloud API WhatsApp, Ultramsg, TextMeBot, token GitHub) s'affichent désormais **vides** dans les réglages — plus aucune clé dans le code source de la page admin. Un champ laissé vide conserve la clé stockée ; il faut ressaisir la clé complète pour la remplacer.
- **`unserialize()` durci** : `allowed_classes = false` dans la migration des réglages legacy (aucune instanciation d'objet possible).

### Ajouté
- **Scanner de sécurité permanent** (`npm run check`) : 10 familles de contrôles sur les 76 fichiers PHP — fonctions dangereuses (eval, unserialize, exec…), XSS par superglobales, secrets rendus dans le HTML, gardes ABSPATH, nonces CSRF sur tous les handlers admin-post et AJAX, permission_callback sur chaque route REST, interpolation de superglobales dans les requêtes SQL, redirections non sûres, AJAX public. Le build échoue à la moindre régression.

### Verdict de l'audit
Injection SQL : aucune (requêtes préparées ou tables internes uniquement) · XSS : aucune (échappement systématique) · CSRF : nonces partout · Autorisations : capacités vérifiées sur tous les handlers · Upload : zip contrôlé (capacité, nonce, HTTPS, mime) · Routes REST publiques : rate-limit, honeypot, captcha, HMAC — rien à signaler.


## 5.24.3 — 2026-09-11

Stepper Quantité encore plus léger et discret.

### Modifié
- **Taille réduite** : boutons − / + ramenés à 26 px (au lieu de 30), saisie 34 px, padding de la pilule resserré — le stepper pèse désormais visuellement autant qu'un champ normal au lieu de dominer la ligne. Le libellé « Quantité » et le stepper restent alignés sur une seule ligne pleine largeur.


## 5.24.2 — 2026-09-11

Champ Quantité : blindage maximal contre les thèmes.

### Modifié
- **Champ Quantité blindé** : la ligne « Quantité + stepper » est verrouillée en `!important` (display flex, une seule ligne non repliable, libellé sans largeur imposée ni flottant, stepper non compressible) — les thèmes qui forcent `label{width:100%}` ou réécrivent le display du conteneur ne peuvent plus déformer le champ.


## 5.24.1 — 2026-09-11

Champ Quantité compact et bien placé : libellé et stepper sur une seule ligne.

### Modifié
- **Champ Quantité repensé** : le libellé « Quantité » et le stepper (− / +) partagent désormais **une seule ligne pleine largeur** — libellé à gauche, stepper à droite, alignés verticalement. Fini le libellé empilé au-dessus de la pilule qui faisait perdre une ligne entière sur mobile : le champ est plus compact, équilibré et reste parfaitement lisible.
- Vérification complète du plugin avant publication : 192 vérifications du moteur de formulaire, audit des 141 clés de réglages (chaque option enregistrée ET appliquée), audits anti-corruption, imports, attributions, harnais d'activation — tout au vert.


## 5.24.0 — 2026-09-11

Freemius : le passage à Premium se paie par carte bancaire, encaissé par Freemius (merchant of record).

### Ajouté
- **Canal de vente Freemius** : dans l'onglet Licence, nouvelle carte « Passer à Premium — paiement sécurisé par carte » qui ouvre le checkout hébergé Freemius. Le client paie par carte (conformité, facturation et TVA gérées par Freemius), reçoit sa clé par email et la colle dans le champ d'activation — Premium s'active immédiatement.
- **Configuration vendeur** : dans la carte « Vente Pro — configuration vendeur », activez Freemius et collez votre lien de checkout (dashboard Freemius → Plans → Checkout Link). Le canal PayPal direct reste disponible en parallèle — les deux cartes peuvent coexister ou l'un masquer l'autre selon vos activations.
- Solution adaptée aux vendeurs résidant en Algérie : le client paie chez Freemius, le vendeur reçoit un payout par virement bancaire (sans dépendre d'un PayPal récepteur).


## 5.23.0 — 2026-09-11

Notifications modernisées et micro-UX : plus aucune alerte bloquante, retours visuels clairs partout.

### Ajouté
- **Toasts empilés dans le dashboard** : les 21 `alert()` bloquants du JavaScript admin sont remplacés par des notifications non bloquantes en bas d'écran, typées (succès vert / erreur rouge / info), avec icône, fermeture automatique (6 s pour les erreurs, 2,6 s sinon), limite de 4 empilées et compatibilité lecteurs d'écran (`aria-live`).
- **Retours de succès** : commune enregistrée ✓, commande supprimée (rappel : restaurable dans la corbeille WooCommerce) ✓, et changement de statut confirmé par un toast qui **survit au rechargement** de la table.
- **Formulaire front — envoi visuel** : le bouton « Commander » affiche un spinner animé pendant l'envoi (état non cliquable), en plus du texte « Envoi en cours… ».
- **Formulaire front — erreur de validation** : au lieu de seulement afficher le message, la page **fait défiler jusqu'au premier champ invalide et le met en focus** — le client sait exactement quoi corriger, surtout sur mobile.


## 5.22.1 — 2026-09-11

« Ajouter au panier » et quantité du thème masqués dès l'installation.

### Modifié
- **Par défaut à l'installation (et à la mise à jour, si le réglage n'a jamais été touché)** : l'option « Masquer Ajouter au panier + quantité du thème » est désormais **activée d'origine** — le bouton « Ajouter au panier », le champ quantité et les barres d'achat collantes du thème disparaissent des fiches produit ; le formulaire COD, avec son propre sélecteur de quantité, est le seul chemin d'achat.
- La barre « add-to-cart » collante des thèmes (type Flatsome) est aussi couverte par le masquage.
- Le marchand peut réactiver le bouton du thème à tout moment : Réglages → Formulaire → décocher « Masquer Ajouter au panier… » (son choix explicite reste prioritaire).


## 5.22.0 — 2026-09-11

Essai Premium : 7 jours, tout inclus, un clic — sans carte bancaire.

### Ajouté
- **Essai gratuit Premium (7 jours)** : nouveau bouton « 🚀 Démarrer mon essai de 7 jours » dans l'onglet Licence. Toutes les fonctionnalités Premium sont débloquées immédiatement (WhatsApp automatique, transporteurs, relances paniers, commande WhatsApp, offres par quantité) — une seule fois par site, sans carte ni engagement.
- Pendant l'essai, le statut affiche « Essai Premium — X j restants » avec la date de fin ; la clé de licence reste activable à tout moment pour continuer sans interruption.
- Le badge de statut (menu, pages du plugin) reflète l'état de l'essai ; à son terme, retour automatique et propre à la version gratuite — aucune donnée n'est perdue.


## 5.21.4 — 2026-09-11

Nouveaux logos des cartes CIB et Edahabia embarqués directement dans le plugin.

### Modifié
- **Logos officiels des cartes** : les visuels génériques remplacés par des rendus vectoriels soignés aux couleurs de marque — badge carte blanche, swoosh doré et lettrage italique bleu pour **CIB** ; carte dorée dégradée avec le mot « EDAHABIA » et « الذهبية » pour **Edahabia**. Embarqués en SVG dans le plugin : nets sur tous les écrans, affichés directement dans le formulaire (bloc Méthode de paiement) et dans Réglages → Paiement, sans aucune configuration.
- Personnalisable à tout moment : téléversement dans Réglages → Paiement ou dépôt de fichiers dans `assets/front/img/pay/` — vos visuels restent prioritaires.


## 5.21.3 — 2026-09-11

Stepper Quantité compact et rapidité : dashboard allégé, scripts non bloquants.

### Modifié
- **Champ Quantité redimensionné** : pilule plus compacte (boutons 30 px au lieu de 36, saisie 38 px) — et le chiffre est désormais **exactement centré** quel que soit le thème : `padding` et `text-align` verrouillés contre les styles du thème qui décalaient le nombre.

### Rapidité
- **Badge « commandes en attente »** : le `COUNT(*)` qui s'exécutait sur CHAQUE page d'administration est mis en cache et invalidé à chaque création / changement de statut / suppression de commande (hooks du cycle de vie) + filet de sécurité 10 minutes — plus de requête de comptage inutile sur les boutiques à fort volume.
- **Scripts non bloquants** : `defer` appliqué au JS du formulaire, au JS admin et à Chart.js (page Statistiques) — le rendu de la page ne les attend plus.
- **reCAPTCHA v3** : pré-connexion au domaine Google quand le captcha est actif (~100-300 ms gagnées au premier chargement).
- **Vignette produit** du formulaire : décodage asynchrone.


## 5.21.2 — 2026-09-11

Audit complet « chaque réglage s'enregistre et s'applique » : deux bugs du compte à rebours détectés et corrigés, nouveau garde-fou permanent.

### Corrigé
- **Apparence du compte à rebours jamais appliquée** : la taille du texte, la couleur du fond et la couleur du texte (Apparence du compte à rebours) s'enregistraient mais n'étaient jamais rendues — la variable de style n'était jamais construite (notice PHP incluse). Désormais appliquées en inline sur le timer.
- **5 des 8 styles de compte à rebours ignorés** : Flip, Néon, Minimal, Bandeau et Encadré étaient reforcés en « Barre rayée » par une liste blanche périmée côté rendu (l'interface proposait bien les 8). Les 8 styles s'affichent maintenant réellement.
- Nettoyage : suppression des réglages morts `paypal_price_personal/business/agency` (remplacés depuis par le prix unique de la licence Premium).

### Ajouté
- **Audit d'application permanent** (`npm run check`) : chaque clé du schéma de réglages doit avoir un consommateur réel dans le plugin — une option qui s'enregistre sans jamais s'applique fait échouer la CI. 139 clés vérifiées.


## 5.21.1 — 2026-09-11

Correctifs visuels constatés sur une boutique cliente (mobile) : Quantité étirée par le thème, tirets « — » en mode de livraison, total de la barre collante vide.

### Corrigé
- **Champ Quantité déformé sur certains thèmes** : le thème peut forcer `input{width:100%}` et étirer la pilule sur toute la ligne. La pilule est désormais en `width:max-content` et la largeur de l'input est triple-bornée (44 px) — la forme reste intacte quel que soit le thème.
- **Cases « Mode de livraison »** : plus de tiret « — » qui ressemblait à un bug tant qu'aucune wilaya n'était choisie — la ligne de tarif reste masquée jusqu'à l'affichage du vrai prix (ou de « Gratuit »).
- **Barre collante mobile** : le total affiche dès le chargement le prix du produit × quantité (au lieu d'un « — » vide) ; il intègre la livraison dès que la wilaya est sélectionnée.


## 5.21.0 — 2026-09-11

Stepper quantité redessiné, arrondi des champs réglable au pixel, page À propos actualisée.

### Ajouté
- **Réglage « Arrondi des champs » (0-30 px)** dans Apparence du formulaire : contrôle dédié appliqué à tous les champs (nom, téléphone, e-mail, wilaya, commune, adresse, note) via la variable CSS `--icod-radius-fields`. Vide = arrondi du thème.
- **Page À propos enrichie** : bloc « Nouveautés récentes » (3 dernières versions du journal embarqué) et liste des fonctionnalités entièrement actualisée (Builder, codes promo, 8 styles de timer, export Excel, DMCA…).

### Modifié
- **Stepper Quantité redessiné** : pilule douce avec ombre légère, boutons − / + circulaires avec effet hover/appui, saisie centrale sans cadre, halo de focus à la couleur d'accent.

### Corrigé
- Import manquant `Settings` dans le gestionnaire des transporteurs (synchronisation automatique du suivi).
- L'arrondi des champs Nom complet / Téléphone est désormais identique à celui des autres champs (variable commune), et pilotable depuis les réglages.


## 5.20.2 — 2026-09-11

Conformité WordPress.org : readme.txt aux normes (Stable tag, Screenshots, Requires Plugins, Services externes disclosés) et assets officiels (bannière, icône, captures). Mise à jour GitHub désactivée d'elle-même si le plugin est hébergé sur le répertoire officiel WordPress.org (pattern dual-hosted standard).


## 5.20.1 — 2026-09-11

Page Mises à jour : affichage des détails réels (version, statut, empreinte) même quand le cache de détection est périmé.

### Corrigé
- **« Dernière version disponible : Inconnue »** alors que les sources répondent : la page lit les transients de détection qui peuvent contenir un marqueur « injoignable » périmé (rate-limit passager). Désormais, si aucune donnée n'est trouvée, les transients sont purgés et la détection est relancée une fois avant affichage.
- L'empreinte SHA-256 réelle du manifest est affichée dans la ligne « Intégrité du package ».


## 5.20.0 — 2026-09-11

Désactivation universelle d'« Ajouter au panier » + style visuel « clean animé » inspiré des meilleurs checkouts COD.

### Ajouté
- **Masquage universel d'« Ajouter au panier »** : en plus des hooks WooCommerce, un CSS injecté masque le bouton du thème (quelles que soient les classes utilisées) — le formulaire COD devient le seul chemin d'achat, comme Yaxii.
- **Bandeau de réassurance repensé** : grille de 4 avantages avec hover animé (2×2 sur mobile), style pilule.
- **Entrée animée des champs** : apparition en cascade (stagger) au chargement.
- **Offres par quantité** en pilules rouges animées.

### Modifié
- Champ Quantité : ombre douce et style pilule renforcé (blindage thèmes).


## 5.19.0 — 2026-09-11

Module complet de codes promo personnalisés + export Excel coloré des commandes.

### Ajouté
- **Codes promo InfinityCod** (menu 🎟️ Codes promo) : créez vos propres codes avec type de remise (pourcentage ou montant fixe), dates de début et de fin, sélection des produits concernés (vide = tous), minimum de commande et limite d'utilisations. Statut actif/inactif en un clic.
- **Statistiques et historique** : par code — utilisations, remise totale accordée, CA généré, et historique complet des commandes ayant utilisé chaque code (date, client, total, statut).
- Priorité : un code InfinityCod est validé avant tout coupon WooCommerce ; la validation reste 100 % côté serveur (dates, produits, minimum, limite).
- **Export Excel des commandes** (.xls) : en-têtes bleus, lignes zébrées, statuts colorés, totaux en vert — organisé en colonnes claires.

### Technique
- Nouvelle table icod_promos (migration DB 1.6.0 automatique) : code unique, remise, période, produits, minimum, limite, compteurs d'usage et de CA.
- Coupon::evaluate() accepte l'ID produit ; les codes InfinityCod sont prioritaires sur les coupons WooCommerce.


## 5.18.0 — 2026-09-11

Protection, synchronisation transporteurs et personnalisation paiement : tout ce qui était demandé dans cette itération.

### Ajouté
- **Protection contre l'utilisation sans autorisation** : garde serveur sur l'API de commandes (403 sans licence quand le verrou est actif) + bannière admin persistante « 🔒 InfinityCod est verrouillé ». Activation : define( 'INFINITYCOD_LOCK_FORM', true ); dans wp-config.php du site client.
- **Mention DMCA** : en-tête du plugin, README, readme.txt et pied de page admin (« 🔒 DMCA »).
- **Wilayas & Tarifs** : case « Synchronisation automatique du suivi transporteur (chaque heure) » — activée par défaut, le cron ne tourne plus si vous la décochez.
- **Onglet Paiement** : champs ID média pour les logos officiels CIB et Edahabia — vos logos remplacent les visuels intégrés dans le formulaire.
- **Formulaire** : signature « 🔒 Protégé par Infinity Coder » sous le formulaire (désactivable) et badge de stock en alerte rouge « Seulement X restants ! » quand le stock est ≤ 5.
- **Réglages → Options** : « Désactiver « Ajouter au panier » WooCommerce » — le formulaire COD devient le seul chemin d'achat sur la fiche produit.


## 5.17.0 — 2026-09-11

Protection contre l'utilisation sans autorisation + mention DMCA + purge du code (plugin plus léger).

### Ajouté
- **Verrou d'autorisation serveur** : quand le verrou développeur est actif (constante wp-config), l'API de création de commandes refuse toute soumission (403) sur les copies sans licence — le formulaire ET le backend sont protégés.
- **Bannière admin persistante** si le verrou est actif sans licence : « 🔒 InfinityCod est verrouillé — Activer ma licence ».
- Mention **DMCA** : en-tête du plugin, README et readme.txt.

### Modifié
- **Purge** : règles CSS mortes retirées du stylesheet front (styles admin dupliqués) — assets plus légers.
- Le pied de page admin affiche « 🔒 DMCA ».

### Note
- Le système de mise à jour GitHub n'a PAS été touché (non-régression vérifiée).


## 5.16.0 — 2026-09-11

Dashboard Commandes : modale Détails enrichie et animée, actions rapides et recherche fluide.

### Ajouté
- **Modale Détails enrichie et animée** : ouverture/fermeture fluides (fondu + zoom), cartes détaillées (coordonnées, destination, produit, montants avec code promo, note complète, risque/transporteur), bouton 📋 copier le téléphone avec notification toast, bouton 🚫 Blacklister et 🗑 Supprimer directement dans la modale.
- **Recherche fluide** : la recherche (nom, téléphone) filtre automatiquement après la saisie, sans cliquer.
- **Suppression groupée sécurisée** : confirmation avant mise à la corbeille.

### Modifié
- Le champ Quantité et les boîtes de livraison sont neutralisés contre les styles des thèmes (plus de cadres parasites).


## 5.15.0 — 2026-09-11

Modèles de formulaire en un clic, glisser-déposer fluide dans le Builder, champs et options de livraison compactés.

### CORRECTIF MAJEUR
- **Le Builder n'enregistrait pas réellement les champs personnalisés** : le formulaire envoyait un tableau natif alors que le code attendait du JSON — à chaque « Enregistrer », la configuration des champs était écrasée. Corrigé : le tableau natif est désormais traité correctement (c'était le bug derrière « les réglages champs ne s'appliquent pas »).

### Ajouté
- **Modèles de formulaire en 1 clic** : Simple (Nom, Téléphone, Wilaya, Commune), Ultra-rapide (Nom, Téléphone, Wilaya), Pro (+ Adresse obligatoire + Note). L'aperçu en direct se met à jour instantanément au choix du modèle.
- **Glisser-déposer fluide** dans le Checkout Builder : poignée ⠿ sur chaque ligne, réordonnancement à la souris avec renumérotation automatique (les flèches ▲▼ restent disponibles).

### Modifié
- **Mode de livraison compact** : boîtes moins hautes et moins larges, prix plus discret.
- **Champ Quantité** : stepper équilibré (56 px), blindé contre les styles des thèmes.


## 5.14.0 — 2026-09-11

Timer entièrement personnalisable, onglet Licence repensé, statuts du tracking, et statistiques P&L ouvertes à tous.

### Ajouté
- **Compte à rebours personnalisable** : position (haut ou bas, avant le bouton), taille du texte (12-22 px), couleurs du fond et du texte via palettes cliquables — en plus des 8 styles.
- **Onglet Tracking** : panneau de statut par plateforme (Meta Pixel, Conversions API, TikTok, Snapchat, GA4) avec voyant actif/inactif et diagnostic des éléments manquants.
- **Onglet Licence amélioré** : section « Statut de la licence » structurée avec barre visuelle du temps restant (vert/orange/rouge) et explications.

### Modifié
- **Statistiques P&L actives pour TOUS** : plus de verrou licence sur les statistiques — CA, taux de confirmation, répartitions accessibles à toutes les boutiques. La page Licence et les mentions ont été mises à jour en conséquence.


## 5.13.0 — 2026-09-11

Champs harmonisés moins hauts, 5e style « Flat design moderne », captcha déplacé en bas et navigation au clavier.

### Ajouté
- **Style « Flat design moderne »** (5e style) : champs remplis sans bordures ni ombres, en-tête clair à titre accent, bouton plat — le design tendance des checkout modernes.
- **Captcha déplacé EN BAS du formulaire**, juste avant le bouton « Confirmer » : le client remplit d'abord, le anti-bot intervient à la fin (meilleure conversion, bots en fin de parcours).
- **Touche Entrée = champ suivant** (et sur le dernier champ, soumission) — saisie rapide au clavier.
- **Champ rempli = liseré vert** : repérage visuel immédiat de ce qui est déjà saisi.
- **Compteur de caractères** sur la note (0 / 500).

### Modifié
- **Champs harmonisés** : même hauteur (46 px), même forme et même arrondi partout — inputs, selects, textarea dédié. Moins hauts qu'avant, plus denses.


## 5.12.1 — 2026-09-10

Correction mobile complète à partir des captures réelles d'un site client (computime.dz).

### Corrigé
- **En-tête mobile** : plus jamais d'empilement mot par mot — en-tête compact sur une ligne (photo 42 px, icône 38 px, titre limité à 2 lignes, sous-titre 2 lignes, badge prix compact), l'ancien prix masqué sur petit écran.
- **Barre collante** : total et bouton sur UNE seule ligne chacun (le bouton ne se replie plus sur deux lignes), texte nettoyé des espaces/éléments parasites, ellipsis de sécurité, paddings et ombre ajustés.
- **Champ Quantité** : la bordure imposée par certains thèmes est neutralisée, stepper limité à 180 px.
- Marges stock/timer/progression alignées sur le formulaire, bouton principal compact (48 px).

### Mesuré
- Aucun débordement à 390 px (vérification DOM élément par élément) ; compatible 320 px avec dégradation propre.


## 5.12.0 — 2026-09-10

8 styles de compte à rebours, personnalisation étendue (police, hauteur, largeur 1400) et licence Premium UNIQUE avec bouton PayPal direct.

### Ajouté
- **8 styles de compte à rebours** : Barre rayée, Pilule sombre, Ruban accent, Flip (horloge à volets), Néon (texte lumineux), Minimal (souligné), Bandeau plein (dégradé accent), Encadré (cadre accent).
- **Personnalisation étendue** (Réglages → Apparence) : taille de la police (13-20 px) et hauteur du bouton (48-80 px), en plus de l'arrondi, de l'espacement et de la largeur.
- **Largeur du formulaire jusqu'à 1400 px** pour les thèmes larges et les mises en page pleine largeur.
- **Champ Quantité élargi** (60 px) pour un meilleur confort tactile.
- **Licence Premium UNIQUE « tout inclus »** : la page Licence affiche désormais une seule carte Premium avec bouton PayPal direct vers le compte du vendeur (prix configurable dans la configuration vendeur). Les anciens prix multi-paliers restent compatibles mais ne sont plus affichés.


## 5.11.0 — 2026-09-10

Palettes de couleurs cliquables, icônes de bouton, HUD retiré, aperçu instantané et blindage thèmes.

### Ajouté
- **Palettes de couleurs cliquables** (fini les codes hex à taper) : Couleur d'accent (16 teintes), bouton, texte, bordures et fond (12 teintes chacune) — un clic sélectionne, ↺ revient au défaut du thème, l'aperçu se met à jour instantanément.
- **Icône du bouton « Confirmer la commande »** : 10 icônes au choix (🛒 🛍️ 💳 ✅ 🚀 📦 ⚡ ❤️ ou aucune) — Réglages → Contenu.
- **Blindage thèmes** : le formulaire neutralise les interférences des CSS tierces (reset ciblé des inputs/boutons/listes/images, accent-color natif, color-scheme clair/sombre) — compatible thèmes populaires et navigateurs nouvelle génération.
- Aperçu en direct encore plus réactif : 250 ms.

### Supprimé
- **HUD admin sur le formulaire** (demande marchands : il gênait) — les valeurs de contrôle restent visibles via l'aperçu en direct et les Diagnostics.


## 5.10.0 — 2026-09-10

4 styles de formulaire + 5 nouvelles options d'affichage + barre collante pleine largeur.

### Ajouté
- **Style du formulaire** (Réglages → Apparence) : **Classique** (doux, défaut), **Moderne** (épuré, étiquettes fines majuscules), **Tech** (angles nets, labels monospace, bouton majuscule), **E-commerce** (CTA puissant, récapitulatif accentué, prix mis en avant).
- **5 nouvelles options d'affichage** (section 4) : photo produit dans l'en-tête, badge de stock, barre de progression, champ code promo, masquer le formulaire avec message « Épuisé » si plus de stock.
- **Barre collante mobile repensée** : pleine largeur écran, bordure accent, bouton plus large — récapitulatif + total + bouton toujours visibles.


## 5.9.2 — 2026-09-10

Trois styles de compte à rebours au choix + aperçu en direct en colonne collante à droite de l'écran.

### Ajouté
- **3 styles de compte à rebours** (Réglages → Formulaire → section 6) : Barre rayée animée (défaut), Pilule sombre avec point pulsant, Ruban en bannières aux couleurs de l'accent.
- **Aperçu en direct repositionné** : sur les grands écrans (≥ 1660 px), l'onglet Formulaire passe en deux colonnes — les réglages à gauche, l'aperçu en direct en colonne collante À DROITE, face à vous, qui suit le défilement. Rafraîchissement accéléré à 400 ms pour un effet temps réel. Écrans plus étroits : disposition empilée classique.


## 5.9.1 — 2026-09-10

Sauvegarde INFAILLIBLE : chaque enregistrement est vérifié en base et disposé d'un repli automatique.

### Ajouté
- **Vérification après écriture** : après chaque « Enregistrer », le plugin RELIT la base et compare. Si les valeurs relues diffèrent (cache d'objets défectueux), un message rouge explicite s'affiche au lieu d'un faux succès.
- **Repli AJAX automatique** : l'enregistrement passe d'abord par admin-ajax (contourne un éventuel blocage de admin-post.php par un pare-feu) ; en cas d'échec, repli transparent sur la soumission native.
- En-têtes no-cache sur les écrans Réglages et Commandes.

### ⚠️ IMPORTANT — version installée
Le sondage direct du site de démonstration montre la 5.7.1 installée : les correctifs 5.7.2 → 5.9.1 (dont lecture directe en base et purge des caches) ne sont actifs qu'après installation de cette version. Mises à jour → Mettre à jour maintenant.


## 5.9.0 — 2026-09-10

CRUD commandes complet (le D de Delete manquant est là) et synchronisation WooCommerce intégrale à l'édition.

### Ajouté
- **Suppression de commandes** : bouton 🗑 sur chaque ligne et action groupée « → Supprimer » — la ligne COD est supprimée et la commande WooCommerce part à la CORBEILLE (récupérable, jamais de suppression définitive automatique). Confirmation + capability + hook infinitycod_order_deleted.
- **Action groupée « → Expédier »**.
- **Édition 100 % synchronisée avec WooCommerce** : modifier client, téléphone, commune, wilaya ou quantité depuis le dashboard met à jour la commande WooCommerce (adresse facturation + livraison, quantité de la ligne produit, note client, total) — avant, seul le total était synchronisé.

### Technique
- Audits anti-régression étendus : 99 tests harnais + scan des champs POST + scan des méthodes.


## 5.8.3 — 2026-09-10

Correctif pipeline : le harnais d'activation n'avait pas le stub du cache (échec CI v5.8.2 — relance automatique des tags précédents possible).

### Corrigé
- Stub wp_cache_delete manquant dans tools/test-activation.php (la CI Release échouait sur ce fatal — les révisions 5.8.2/5.8.3 du moteur de réglages sont validées).


## 5.8.2 — 2026-09-10

Changement FORCÉ : les réglages sont désormais lus directement en base de données, immunisés contre tout cache d'objets serveur.

### Corrigé
- **Lecture directe en base (bypass du cache d'objets)** : sur certains hébergeurs (LiteSpeed LSMCD, Memcached), le cache d'objets ne s'invalide pas — les réglages étaient bien écrits en base mais les pages continuaient de servir les anciennes valeurs. Toutes les lectures InfinityCod contournent désormais ce cache : une couleur, un champ ou un captcha enregistré s'applique immédiatement, garantie.
- Invalidation forcée du cache 'alloptions' après chaque écriture.
- Diagnostics : affichage de l'état du cache d'objets (externe/interne).

### Vérifié
- Scan complet du code : aucun appel PHP 8 (compatible PHP 7.4 intégral) — aucune erreur fatale de syntaxe possible.
- Harnais 93/93, non-régression Updater complète.


## 5.8.1 — 2026-09-10

« Un preset choisit la couleur de départ » fonctionne maintenant réellement, même sans JavaScript.

### Corrigé
- **Presets réellement appliqués** : tant que la couleur d'accent n'a pas été personnalisée (valeur d'origine), choisir un preset (Océan, Royal, Sunset…) et enregistrer change réellement la couleur de tout le formulaire — côté serveur, indépendamment du navigateur.
- Le clic sur un preset déclenche désormais le rafraîchissement immédiat de l'aperçu en direct (avant : le sélecteur changeait mais l'aperçu ne bougeait pas, d'où l'impression que rien ne fonctionnait).
- Dès que la couleur d'accent est personnalisée, elle redevient prioritaire sur le preset — exactement le comportement annoncé.


## 5.8.0 — 2026-09-10

L'interface Réglages est réorganisée en sections numérotées, et la cause n°1 du « rien ne change » est éliminée.

### Ajouté
- **Purge automatique des caches à chaque enregistrement** : LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, SG Optimizer, WP Fastest Cache et Autoptimize sont vidés dès que vous cliquez sur Enregistrer — plus besoin de vider manuellement pour voir le formulaire changer.
- **Bouton Enregistrer toujours visible** (barre collante en bas de page) avec rappel « appliqué dès l'enregistrement ».
- **Bandeau guide** en haut de la page : 1) Modifiez 2) Enregistrez (caches vidés) 3) Vérifiez l'aperçu.

### Modifié
- **Onglet Formulaire réorganisé en 8 sections numérotées** : Contenu → Champs → Apparence → Options → Captcha → Compte à rebours → Écran de remerciement → Libellés → Aperçu.
- **2 anomalies d'interface corrigées** : la case « Offres par quantité » avait disparu de l'onglet Formulaire (et repassait à zéro à chaque enregistrement — restaurée, une seule fois, avec son badge Premium) ; la case « Barre collante » était affichée en double ; des cases « Offres/Réassurance » inertes traînaient dans l'onglet Avancé (elles n'y sauvegardaient jamais) — supprimées.


## 5.7.2 — 2026-09-10

Deux réglages fantômes de Wilayas & Tarifs deviennent réellement fonctionnels + pied de page assagi.

### Ajouté
- **Colonnes « Délai (jours) » et « Min (DA) » de Wilayas & Tarifs enfin éditables** : les colonnes existaient dans le tableau mais leurs cellules de saisie avaient disparu. Saisie + sauvegarde + application réelle :
  - **Commande minimum par wilaya** : le serveur refuse la commande avec un message clair (« Commande minimum de X DA pour Alger ») si le sous-total est insuffisant ; le formulaire prévient le client dès le choix de la wilaya.
  - **Délai de livraison** : affiché dans le récapitulatif (« ⏱ 2-4 ») dès le choix de la wilaya.

### Modifié
- Crédit de pied de page raccourci et discret : « InfinityCod vX.Y · © Infinity Coder ».


## 5.7.1 — 2026-09-10

Audit automatisé complet des écrans d'administration : un dernier réglage mort détecté et réparé.

### Corrigé
- **« Livraison gratuite par montant » et « Supplément poids » (page Wilayas & Tarifs) ne pouvaient jamais s'activer** : les cases étaient rendues avec un nom différent de celui lu par le gestionnaire de sauvegarde — chaque enregistrement les remettait à zéro. Noms alignés, les six réglages (activation, seuil, message, tarif par kg, kg inclus) fonctionnent.

### Ajouté
- **Nouvel audit automatique** : correspondance champ rendu ↔ champ lu sur TOUS les écrans d'administration (intégré à npm run check) — cette classe de réglage mort ne peut plus revenir.


## 5.7.0 — 2026-09-10

Personnalisation avancée : chaque couleur et mesure remplie est réellement appliquée au formulaire.

### Ajouté
- **Couleur du bouton** (indépendante de l'accent, dégradé calculé automatiquement).
- **Couleur du texte**, **couleur des bordures**, **couleur de fond du formulaire**.
- **Arrondi des coins (0-40 px)** et **espacement intérieur (8-48 px)**.
- Principe : champ vide = valeurs du thème ; champ rempli = appliqué réellement via variables inline. Appliqué à l'aperçu en direct ET au formulaire public.

### Non-régression
- GitHub Updater re-testé intégralement (détection, injection, SHA-256 valide/invalide, compatibilité bloquante, dégradation réseau) : TOUS LES TESTS PASSENT — aucune modification du flux détection → téléchargement → installation.


## 5.6.3 — 2026-09-10

Vous ne savez jamais si le formulaire affiche VOS réglages ? Le HUD admin répond instantanément.

### Ajouté
- **HUD administrateur sur le formulaire** : les admins voient un petit bandeau « ⚙ Accent · Captcha · Timer · Thème » affichant les valeurs RÉELLEMENT rendues par le serveur, avec lien direct vers les réglages. Invisible pour les clients. Si le HUD n'apparaît pas pour vous, la page servie est une copie en cache (LiteSpeed…).
- **Diagnostics « persistance »** : test d'écriture → lecture immédiate qui détecte un cache d'objets défectueux faisant « disparaître » les sauvegardes ; affichage des réglages réellement stockés (accent, captcha, timer, thème) et de la date de dernière sauvegarde.

### Modifié
- Sous-menu « Commandes COD » renommé **« Commandes »**.

### Diagnostic du 10/09 (site de démonstration)
Le formulaire servait bien le moteur complet avec les valeurs par défaut : captcha/timer étaient simplement désactivés et la couleur n'avait pas été enregistrée — d'où le HUD et les tests de persistance ajoutés.


## 5.6.2 — 2026-09-10

Nettoyage commercial : le verrou de formulaire n'apparaît plus dans le dashboard du marchand.

### Modifié
- **« Verrouiller le formulaire sans licence » retiré de l'interface** : c'est un réglage de distribution destiné au développeur-vendeur (Infinity Coder), pas aux marchands clients. Il se pilote désormais depuis wp-config.php du site client : define( 'INFINITYCOD_LOCK_FORM', true ); (ou false). Les verrous déjà actifs restent en place.


## 5.6.1 — 2026-09-10

Badge de notification des commandes en attente + dernier réglage déconnecté branché.

### Ajouté
- **Badge rond rouge « commandes en attente »** sur le menu InfinityCod ET sur le sous-menu « Commandes COD » : le nombre de commandes à confirmer est visible d'un coup d'œil, mis à jour à chaque chargement de l'admin. Désactivable dans Réglages → Avancé.

### Corrigé
- Le réglage « Journal InfinityCod » (Réglages → Avancé) était ignoré : désactivé, aucune écriture de log n'a lieu désormais.
- Rappel automatique après chaque enregistrement : si le formulaire public ne change pas, vider le cache du plugin de cache (LiteSpeed, WP Rocket…).


## 5.6.0 — 2026-09-10

La couleur choisie dans le dashboard teinte DÉSORMAIS tout le formulaire, et le mode sombre fonctionne réellement.

### Ajouté
- **Couleur d'accent 100 % appliquée** : la couleur choisie (Réglages → Formulaire) pilote l'en-tête, le bouton, les focus, les puces de variantes, le récapitulatif, le total et les ombres — nuances calculées automatiquement (dégradé foncé + contraste du texte garanti). Plus aucun dégradé codé en dur.
- **Mode sombre fonctionnel** : « Sombre » force le thème ; « Automatique » suit la préférence du visiteur (réglage système). Tous les composants (champs, cartes, récapitulatif, code promo, réassurance, upsell, barre collante) sont repensés en variables de couleur.
- **Les 5 styles d'écran de remerciement** sont maintenant visuellement distincts (Classique, Confettis, Minimal, Ticket, Célébration).
- **Surcharge Elementor restaurée** : le choix de preset dans le widget Elementor pilote réellement la couleur et le style du widget (surcharge locale documentée du réglage global).


## 5.5.2 — 2026-09-10

L'onglet « Journal des modifications » de wp-admin affiche désormais la liste complète des ajouts et corrections.

### Ajouté
- **Journal des modifications embarqué** : le CHANGELOG.md voyage dans le plugin et alimente l'onglet même sans accès réseau (indépendant de GitHub et des miroirs). Les 8 dernières versions, converties en HTML propre et échappé.
- Le manifest update.json embarque désormais le journal de la dernière version : les miroirs CDN le servent aussi.


## 5.5.1 — 2026-09-10

Correctif urgent : l'API REST du plugin (communes, devis, soumission) renvoyait une erreur 500 sur les sites — aucune commande ne pouvait passer.

### Corrigé
- **Erreur fatale à l'enregistrement des routes REST** (appel de méthode introuvable) : communes ne se chargeaient plus au choix de la wilaya, le prix temps réel et la soumission de commande échouaient. Toutes les routes REST sont rétablies.
- **Captcha et compte à rebours déplacés à l'intérieur du formulaire** : leurs champs étaient rendus hors de la balise form et n'étaient jamais soumis.
- Nouvel audit automatique intégré : détection de tout appel de méthode inexistant (65 fichiers, héritage inclus) + tests d'enregistrement REST dans le harnais.


## 5.5.0 — 2026-09-09

Le dashboard pilote réellement le formulaire et le formulaire s'affiche sans aucun champ vide.

### Ajouté
- **Checkout Builder réellement appliqué** : ordre (boutons ▲▼), visibilité, caractère obligatoire et libellés de chaque champ pilotent le rendu ET la validation serveur (source unique : plan des champs). Un champ masqué disparaît du DOM et est ignoré côté serveur.
- **Champ Adresse** : affichable, rendu obligatoire si configuré, enregistré dans la commande WooCommerce (address_1 + meta + note interne).
- **Captcha au choix** : question mathématique (sans service externe) ou Google reCAPTCHA v3 (invisible, vérifié côté serveur). Désactivé = zéro script, zéro champ, zéro validation. Réparation majeure : activer le captcha bloquait toute commande (réponse jamais envoyée, lecture  au lieu du JSON, erreur fatale).
- **reCAPTCHA v3, compte à rebours d'urgence, GA4, notifications Discord/Telegram** : options désormais réellement fonctionnelles (avant : présentes sans aucun effet).
- **Aperçu en direct** dans Réglages → Formulaire : rendu par le vrai moteur avec le brouillon non enregistré.
- **Réinitialisation des réglages** (confirmation, jamais les commandes) + Diagnostics enrichies (réglages chargés, champs actifs, captcha, devise).
- Devise et sa position appliquées partout (19 devises), quantité minimale configurable, pays réel de la région livrée dans la commande.

### Corrigé
- Formulaire : suppression de TOUS les champs vides visibles (honeypot apparent, radios natifs du mode de livraison, label Note sans zone, tirets du récapitulatif, timer vide) + débordement mobile qui coupait téléphone et commune.
- Champs personnalisés jamais enregistrés (variable utilisée avant définition), police arabe Cairo jamais chargée.
- Limite « commandes/heure » qui se réglait par erreur à 1 au lieu du nombre choisi ; HTML admin réparé (4 zones) ; nonce CSRF sur le réordonnancement des champs.
- Migration automatique et idempotente des anciens réglages email/note vers le Checkout Builder.


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

## 2.1.3 — 2026-09-08

### Modifié
- **Mises à jour : GitHub direct, rien d'autre** — suppression du repli manifest sur domaine web. La source unique est le dépôt public des releases GitHub (`derouicheoussama/infinitycod-releases`), consulté sans token, toutes les heures. Aucun fichier à héberger ailleurs, aucun intermédiaire.
- Diagnostics simplifiés en conséquence (source GitHub + résultat du test de connexion).

## 2.2.0 — 2026-09-08

### Added
- **Livraison gratuite intelligente par montant** : seuil en DA + barre dynamique dans le formulaire « 🚚 Ajoutez encore {reste} DA pour la livraison gratuite ! » avec progression visuelle (dépasse le concurrent Mior Livraison Pro).
- **Supplément poids** : frais par kg au-delà d'un seuil inclus (produits lourds/encombrants), appliqué automatiquement au tarif de livraison.
- **Import / Export CSV des tarifs wilayas** en un clic depuis Wilayas & Tarifs (anti CSV-injection inclus).
- **Widget Elementor natif** « InfinityCod — Formulaire COD » avec contrôles (produit, titre, bouton, thème visuel) — s'ajoute aux shortcodes et à l'insertion automatique.
- **Compatibilités déclarées** : HPOS WooCommerce (custom order tables), `Requires Plugins: woocommerce`, WC/Elementor tested-up-to dans le header.

### Changed
- Les frais de livraison incluent désormais le supplément poids dans le devis serveur, la commande WooCommerce et la ligne COD.

## 2.2.1 — 2026-09-08

### Security
- **Blindage anti-fatal généralisé** : `inject_update`, `remote()`, la page Mises à jour et le test de connexion sont protégés par try/catch Throwable — tout bug futur du moteur de mise à jour est journalisé et dégrade proprement au lieu de crasher l'admin (règle §54 fail-safe, retour d'expérience de la v2.1.0).

## 2.3.0 — 2026-09-08

### Fixed
- **Commande impossible — erreur critique à la soumission** : appel à `wc_stock_management()`, une fonction qui n'existe pas dans WooCommerce. Le bloc est retiré — le stock est décrémenté nativement par WooCommerce lors des passages de statut (processing/completed). Cette fonction était présente depuis la première version du formulaire.
- **Référence `Settings` non importée dans la route submit** (fatal `InfinityCod\Rest\Settings`), détectée par le nouveau contrôle d'imports — corrigée.

### Added
- **Contrôle d'imports** (`tools/check-imports.js`, intégré à `npm run check`) : toute référence statique `Classe::` résolvant vers un mauvais namespace fait échouer la CI — plus aucun fatal de ce type ne peut être publié.
- **Endpoint submit blindé** : toute exception technique renvoie désormais une erreur JSON explicite (jamais la page « erreur critique ») et est journalisée.

## 2.4.0 — 2026-09-08

### Added
- **8 styles de formulaire** : Modern, Elegant, Sunset, Ocean, Minimal + **Rose, Royal, Café** (nouveaux).
- **Réglages avancés** : journal on/off, rétention des sauvegardes (1-10).

### Changed
- **Téléchargement des mises à jour refondu** (filtre `pre_http_request`) : le checksum SHA-256 est maintenant vérifié de façon fiable dans le flux WordPress natif, avec authentification correcte des dépôts privés (auth GitHub puis S3 sans credentials).
- Suppression du code mort et des patchs temporaires (tools/patch-*).

## 2.5.0 — 2026-09-08

### Fixed
- **« Adresse email invalide. » sur des emails valides** : le regex client-side avait perdu ses backslashes lors d'un patch (il excluait la lettre « s » !). Tout email contenant un « s » était rejeté. Regex restauré et protégé.
- Mises à jour : le correctif `%2F` (2.1.2) est confirmé — la détection fonctionne désormais directement depuis /wp-admin/plugins.php après mise à jour vers cette version.

### Improved
- **Miniature du produit dans l'en-tête du formulaire** (comme les meilleurs formulaires COD) — l'image renforce la confiance et réduit les abandons.

## 2.6.0 — 2026-09-08

### Fixed
- **Détection des mises à jour sur /wp-admin/plugins.php** : 3ᵉ voie de vérification via le feed Atom public `github.com/{repo}/releases.atom` — fonctionne même quand api.github.com est bloqué ou rate-limité par l'hébergeur. Chaîne : API GitHub → Atom public → (jamais de serveur intermédiaire).

### Improved
- **Interface admin repensée** : bannière de page en dégradé de marque, onglets pilules avec accent, cartes premium avec ombres douces, tableaux à en-têtes colorés, KPI en dégradé, hover states soignés — look SaaS premium cohérent sur toutes les pages.

### Added
- Badge de version dans le titre de chaque page admin.

## 2.6.1 — 2026-09-08

### Fixed
- **Harnais auto-suffisant en CI** : la fixture WordPress minimale est créée tout en haut du script d'activation — plus aucune dépendance au dossier .tools gitignoré (les runs échoués de l'historique venaient de là).

## 2.6.2 — 2026-09-09

### Fixed
- **Boutons des pages Mises à jour et Diagnostics morts** (redirection vers admin-post.php) : leurs handlers `admin_post` étaient enregistrés dans le constructeur des pages, appelé seulement au rendu — jamais quand admin-post.php recevait le POST. Toutes les pages à handlers sont maintenant instanciées au chargement du plugin.
- **Test anti-régression** : le harnais vérifie désormais que les 15 handlers `admin_post` et les 7 handlers `wp_ajax` sont enregistrés dès le boot — ce bug ne peut plus repasser.

## 2.7.0 — 2026-09-09

### Changed
- `create_tables()` : le require de `wp-admin/includes/upgrade.php` est conditionné à l'absence de `dbDelta` — le harnais de test n'a plus besoin de la fixture fake-wp (suggestion retenue du rapport CI).

## 2.8.0 — 2026-09-09

### Fixed
- **Sauvegarde par onglet isolée** : chaque onglet de réglages ne traite que SES propres toggles — sauvegarder depuis l'onglet WhatsApp ne désactive plus les toggles de l'onglet Formulaire, et vice versa. Chaque onglet déclare ses champs gérés via un système de scope.

### Improved
- Miniature du produit dans l'en-tête du formulaire COD (confiance visuelle).
- Regex email réparé (backslashes restaurés — rejetait tout email contenant un « s »).

## 2.8.1 — 2026-09-09

### Changed
- **Thème bleu InfinityCod** (#0078d4) appliqué à toute l'interface admin : bannières dégradées, onglets pilules, boutons, focus, toggle switches, tableaux, KPI, badges — palette bleu + dark navy + blanc de la charte graphique.
- Icônes 📊📦🛒🗺️🚚📈⚙️🔄🩺ℹ️ sur chaque entrée de menu admin.
- Animations d'entrée des cartes (fade-in décalé), hover effects sur les boutons et cartes, focus rings bleus.

# 3.0.0 — WordPress.org Ready

Version stable, sécurisée et conforme aux standards WordPress.org.

Toutes les fonctionnalités :
- Formulaire COD (8 thèmes, RTL, dark mode)
- 58 wilayas + 1541 communes
- Anti-fraude Shield
- Transporteurs (7 API)
- WhatsApp automatique
- Paiement CIB/Edahabia (Chargily)
- Statistiques P&L
- Offres quantité
- Mises à jour auto GitHub
- Diagnostics + Logs + Rollback
