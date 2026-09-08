# Publier les mises à jour depuis GitHub

## Architecture des dépôts

| Dépôt | Visibilité | Contenu | Rôle |
|---|---|---|---|
| `derouicheoussama/infinitycod` | **Privé** | Code source complet | Développement, historique, releases internes (CI) |
| `derouicheoussama/infinitycod-releases` | **Public** | Zips des releases uniquement | Source des mises à jour des boutiques clientes — **aucun token requis** |

Le plugin (v1.3.1+) interroge `https://api.github.com/repos/derouicheoussama/infinitycod-releases/releases/latest` toutes les heures. Le dépôt étant public, tout fonctionne sans token côté clients.

## Rituel de publication (4 commandes)

```bash
# 1. Mettre à jour la version dans infinitycod/infinitycod.php
#    (en-tête "Version:" + constante INFINITYCOD_VERSION)
# 2. Commit
git add -A && git commit -m "1.4.0 — description des changements"

# 3. Tag : la CI du dépôt privé construit le zip et crée la release interne
git tag v1.4.0 && git push origin main --tags

# 4. Publier le zip sur le dépôt PUBLIC (source des mises à jour clients)
node tools/publish-releases.js
```

`tools/publish-releases.js` reconstruit le zip, crée la release `v1.4.0` dans le dépôt public et y téléverse `infinitycod.zip` — en utilisant les identifiants Git de votre machine.

## Côté client (boutique WordPress)

- **Installation initiale** : téléverser le zip une fois (Extensions → Ajouter).
- Ensuite : vérification horaire, notification « Mise à jour disponible » sous le nom du plugin, mise à jour en 1 clic.
- **Option** « Mise à jour automatique » (Réglages → Avancé) : le plugin s'installe tout seul, sans clic.
- **Diagnostic** : si les mises à jour sont indisponibles (ex. dépôt public mal configuré), une notice s'affiche dans l'admin avec un lien vers les réglages.

## Paramètres associés (Réglages → Avancé)

| Champ | Défaut | Rôle |
|---|---|---|
| Dépôt PUBLIC des releases | `derouicheoussama/infinitycod-releases` | Interrogé par tous les clients, sans token |
| Dépôt des sources | `derouicheoussama/infinitycod` | Repli : utilisé avec un token si le dépôt public est vide |
| Token GitHub | vide | Seulement pour le repli dépôt privé |
| Mise à jour automatique | désactivée | Installation automatique des nouvelles versions |

