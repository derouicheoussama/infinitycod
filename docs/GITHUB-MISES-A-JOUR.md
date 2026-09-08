# Publier les mises à jour depuis GitHub

Le dépôt privé **derouicheoussama/infinitycod** est la source officielle des mises à jour du plugin.

## Chaîne de publication (automatique)

```
git tag v1.2.0 && git push origin v1.2.0
        │
        ▼  GitHub Actions (.github/workflows/release.yml)
   lint PHP + smoke autoloader
        │
        ▼
   node tools/build.js  →  dist/infinitycod.zip
        │
        ▼
   Release GitHub v1.2.0 + zip attaché
        │
        ▼  (toutes les boutiques clientes, toutes les 12 h)
   WordPress affiche « Mise à jour disponible » → installation en 1 clic
```

## Publier une version

```bash
# 1. Mettre à jour la version dans infinitycod/infinitycod.php
#    (en-tête "Version:" + constante INFINITYCOD_VERSION)

# 2. Committer puis taguer
git add -A
git commit -m "1.2.0 — description"
git tag v1.2.0
git push origin main --tags
```

Le workflow GitHub Actions fait le reste : lint, build du zip, création de la release avec les release notes automatiques (issues/PRs + CHANGELOG si inclus dans le message du tag).

## Côté client (boutique WordPress)

Le plugin interroge `https://api.github.com/repos/derouicheoussama/infinitycod/releases/latest` toutes les 12 h (transient WP, forçable via **InfinityCod → À propos → Vérifier les mises à jour**).

- **Dépôt public** : rien à configurer.
- **Dépôt privé** (configuré ainsi) : renseignez un **token GitHub lecture seule** dans *Réglages → Avancé → Token GitHub* sur chaque boutique cliente. Classic token avec scope `repo`. Le token sert à lire la release ET télécharger le zip (l'upgrader passe par un téléchargement authentifié en deux temps pour gérer la redirection signée S3 de GitHub).
- **Repli** : si GitHub est indisponible, le plugin tente `factexpert.online/updates/infinitycod.json` (voir SERVEUR-MISES-A-JOUR.md).

## Installation initiale d'une boutique cliente

1. Téléverser `infinitycod.zip` (ou le zip de la release GitHub) : Extensions → Ajouter
2. *Réglages → Avancé* : coller le token GitHub (dépôt privé)
3. Les mises à jour arrivent ensuite automatiquement

## Sécurité du token

- Créez un token **fine-grained** (lecture seule, dépôt infinitycod uniquement) plutôt qu'un classic `repo` quand c'est possible.
- Le token est stocké dans les réglages du site client (option sans autoload exposée publiquement ? Non : option autoload off pour le token). Chaque client a donc accès en théorie au contenu du dépôt — c'est inhérent à un dépôt privé partagé. Si vous voulez isoler davantage, créez un dépôt `releases` public contenant UNIQUEMENT les zips (pas les sources) et configurez-le dans *Réglages → Avancé*.
