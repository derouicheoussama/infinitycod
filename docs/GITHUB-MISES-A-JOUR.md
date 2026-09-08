# Publier les mises à jour depuis GitHub

## Architecture des dépôts

| Dépôt | Visibilité | Contenu | Rôle |
|---|---|---|---|
| `derouicheoussama/infinitycod` | **Privé** | Code source complet | Développement, historique, releases internes (CI) |
| `derouicheoussama/infinitycod-releases` | **Public** | Zips des releases uniquement | Source des mises à jour des boutiques clientes — **aucun token requis** |

Le plugin (v1.3.1+) interroge `https://api.github.com/repos/derouicheoussama/infinitycod-releases/releases/latest` toutes les heures. Le dépôt étant public, tout fonctionne sans token côté clients.

## Rituel de publication (3 commandes, 100 % automatique)

```bash
# 1. Mettre à jour la version dans infinitycod/infinitycod.php
#    (en-tête "Version:" + constante INFINITYCOD_VERSION)
# 2. Commit
git add -A && git commit -m "1.4.0 — description des changements"

# 3. Tag : c'est terminé !
git tag v1.4.0 && git push origin main --tags
```

La CI fait le reste sans aucune intervention : lint PHP, construction du zip, release dans le dépôt privé des sources, **et publication du zip sur le dépôt public** `infinitycod-releases` (secret `RELEASES_TOKEN` configuré une fois pour toutes via `tools/set-releases-secret.js`). Les boutiques clientes détectent la nouvelle version en moins d'une heure et se mettent à jour automatiquement.

`tools/publish-releases.js` reste disponible en secours pour publier manuellement depuis votre machine.

## Côté client (boutique WordPress)

- **Installation initiale** : téléverser le zip une fois (Extensions → Ajouter).
- Ensuite : vérification horaire, notification « Mise à jour disponible », **installation automatique par défaut** (option désactivable dans Réglages → Avancé).
- **Option** « Mise à jour automatique » (Réglages → Avancé) : le plugin s'installe tout seul, sans clic.
- **Diagnostic** : si les mises à jour sont indisponibles (ex. dépôt public mal configuré), une notice s'affiche dans l'admin avec un lien vers les réglages.

## Paramètres associés (Réglages → Avancé)

| Champ | Défaut | Rôle |
|---|---|---|
| Dépôt PUBLIC des releases | `derouicheoussama/infinitycod-releases` | Interrogé par tous les clients, sans token |
| Dépôt des sources | `derouicheoussama/infinitycod` | Repli : utilisé avec un token si le dépôt public est vide |
| Token GitHub | vide | Seulement pour le repli dépôt privé |
| Mise à jour automatique | désactivée | Installation automatique des nouvelles versions |

