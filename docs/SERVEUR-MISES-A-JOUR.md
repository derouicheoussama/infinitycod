# Serveur de mises à jour InfinityCod

Le plugin (v1.1.0+) vérifie les mises à jour via l'API standard WordPress. Il interroge :

```
https://factexpert.online/updates/infinitycod.json
```

## 1. Fichier JSON à déposer sur le serveur

Créez `updates/infinitycod.json` à la racine du site factexpert.online :

```json
{
  "name": "InfinityCod — Paiement à la livraison (COD Algérie)",
  "slug": "infinitycod",
  "version": "1.1.0",
  "requires": "6.0",
  "requires_php": "7.4",
  "tested": "6.7",
  "homepage": "https://infinitycoder.app/infinitycod",
  "download_url": "https://factexpert.online/updates/download/infinitycod-1.1.0.zip",
  "last_updated": "2026-09-08 12:00:00",
  "sections": {
    "description": "<p>Solution COD tout-en-un pour WooCommerce Algérie : formulaire rapide, 58 wilayas & 1541 communes, transporteurs intégrés, WhatsApp automatique, statistiques P&L.</p>",
    "changelog": "<h4>1.1.0</h4><ul><li>Design du formulaire refondu + 5 thèmes</li><li>Personnalisation des champs</li><li>Page À propos</li><li>Mises à jour à distance</li></ul>"
  }
}
```

- `version` : dernière version publiée. WordPress compare avec la version installée.
- `download_url` : lien du zip **avec racine `infinitycod/`** (généré par `node tools/build.js`).
- `sections.changelog` : HTML affiché dans le modal « Voir les détails » de WordPress.

## 2. Publication d'une nouvelle version

1. Modifier `infinitycod/infinitycod.php` (en-tête `Version:` + constante `INFINITYCOD_VERSION`)
2. Mettre à jour le `changelog` du JSON
3. `node tools/build.js` → `dist/infinitycod.zip`
4. Téléverser le zip sur le serveur au chemin `download_url`
5. Mettre à jour `updates/infinitycod.json` avec la nouvelle `version`

Chez les clients : notification dans l'écran Extensions + « Mise à jour disponible » sous le nom du plugin, installation en 1 clic.

## 3. Protéger les téléchargements par licence (optionnel)

Le plugin ajoute automatiquement `?key_hash=<sha256>` au `download_url` si une licence est active. Pour restreindre le téléchargement aux clients licenciés, servez le zip via un petit PHP :

```php
<?php
// updates/download/infinitycod.php
require __DIR__ . '/../../dbLicences.php'; // votre config existante
$hash = $_GET['key_hash'] ?? '';
// Vérifiez $hash contre votre base (même logique que server/api.php)
// Si valide : header('Content-Type: application/zip');
// readfile(__DIR__ . "/infinitycod-{$version}.zip");
```

et pointez `download_url` vers ce script.

## 4. Compatibilité avec le serveur de licences existant

Le client de licence InfinityCod (`includes/license/class-license-manager.php`) utilise le protocole existant de factexpert.online :

- `?action=activate` — paramètres : `machine` (sha256 du site), `key_hash` (sha256 de la clé), `install_id`, `product` (**`infinitycod`**), `version`
- Réponses gérées : `ACTIVE`, `UNKNOWN`, `INVALID`, `EXPIRED`, `REVOKED`, `SUSPENDED`, `MACHINE_CONFLICT`
- Heartbeat hebdomadaire automatique

Pensez à créer les licences avec **product = `infinitycod`** sur votre serveur (le champ `product` est vérifié côté serveur : « Produit différent » sinon).

## 5. Clé de test

La clé `INFINITY-DEV` active une licence de développement locale sans contacter le serveur (utile pour vos tests avant mise en vente).
