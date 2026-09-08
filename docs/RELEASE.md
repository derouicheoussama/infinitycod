# Procédure de release — InfinityCod

## Rituel complet (publier v2.1.0 par exemple)

```bash
git checkout main && git pull

# 1. Version unique (met à jour header + constante + package.json)
node tools/version.js 2.1.0

# 2. CHANGELOG.md : section "## 2.1.0" (Added/Improved/Fixed/Security)

# 3. Commit + tag
git add -A
git commit -m "Release 2.1.0"
git tag v2.1.0
git push origin main
git push origin v2.1.0
```

GitHub Actions enchaîne automatiquement :
`test` (version gate tag=header, lint, autoloader, audits, scan secrets)
→ `build` (zip + sha256 + update.json)
→ `release` (release interne privée + publication sur le dépôt public `infinitycod-releases`).

Les clients reçoivent la mise à jour en moins d'une heure (vérification horaire),
avec vérification compatibilité + SHA-256 + sauvegarde automatique avant installation.

## Vérifier une publication

- CI : onglet Actions du dépôt privé (workflow `Release InfinityCod` → success)
- Public : https://github.com/derouicheoussama/infinitycod-releases/releases
- Artefacts attendus par release : `infinitycod.zip`, `infinitycod.zip.sha256`, `update.json`

## Hotfix rapide

```bash
node tools/version.js 2.1.1
# fix + commit
git tag v2.1.1 && git push origin main --tags
```

## Rollback chez un client

**InfinityCod → Mises à jour → Rollback** : restaure la sauvegarde locale
(fichiers + réglages) créée automatiquement avant chaque mise à jour.
La base de données reste compatible : les migrations sont non destructives.

## Prérequis one-shot de l'environnement

- Secret `RELEASES_TOKEN` sur le dépôt privé (publication croisée vers le dépôt public)
- Dépôt public `derouicheoussama/infinitycod-releases` existant

---

Developer : **Derouiche Oussama** — https://derouicheoussama.com — © Derouiche Oussama
