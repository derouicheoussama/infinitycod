# Infinity License Server

Serveur centralisé de gestion de licences pour les plugins WordPress **Infinity** (InfinityCOD, InfinityInvoice, InfinityVisitStat, InfinityAdminCustomizer, Infinity404Redirect, InfinityDuplicator…).

**Zéro dépendance npm** — Node 24+ avec SQLite natif. `node server.js` et c'est en ligne.

**Auteur : Derouiche Oussama — Infinity Coder**

---

## Démarrage rapide

```bash
node server.js
# → http://127.0.0.1:8787        (landing + catalogue + pricing)
# → http://127.0.0.1:8787/setup  (création du super admin — 1re visite uniquement)
# → http://127.0.0.1:8787/admin  (dashboard)
# → http://127.0.0.1:8787/health (monitoring)
```

Tests bout en bout (scénario complet du cycle de vie, 24 assertions) :

```bash
npm test
```

## Ce que fait le serveur

| Système | Détail |
|---|---|
| **Licences** | Clés `INFC-XXXX-XXXX-XXXX-XXXX` générées cryptographiquement (stockage chiffré AES-256-GCM + hash SHA-256 pour les recherches), statuts ACTIVE/EXPIRED/SUSPENDED/REVOKED/PENDING, limites d'activations, `updates_until`, versions autorisées (`1.x`), régénération audité |
| **API v1** | `/api/v1/license/activate|validate|deactivate|heartbeat|check-update|check-version` + téléchargements à jetons temporaires. Réponses sensibles **signées Ed25519** (clé privée serveur uniquement). Erreurs normalisées (`INVALID_LICENSE`, `ACTIVATION_LIMIT_REACHED`, `RATE_LIMITED`…) |
| **Compatibilité InfinityCOD** | L'endpoint `activate` accepte `key_hash` (le hash SHA-256 qu'InfinityCOD envoie déjà) et répond avec les champs historiques (`status`, `client`, `expires_at`) — il suffit de pointer `license_server` vers ce serveur |
| **Installations** | `installation_id`, domaine normalisé (www, protocole, ports), environnement PRODUCTION/STAGING/LOCAL, blocage/déblocage à distance, timeline d'événements |
| **Sécurité** | Rate limiting par IP+route, détection d'installations suspectes (conflits de domaine, limites atteintes, tentatives bloquées), journal API + **audit log** de toutes les actions admin, sessions expirantes, CSRF, anti brute-force (10 essais / 15 min), mots de passe scrypt |
| **Boutique** | Landing Ocean Blue (clair/sombre), catalogue produits, plans paramétrables (durée, sites, updates), **checkout manuel** → commande → génération de licence → email |
| **Commandes manuelles** | Orders → Create → status PAID → **Generate license** (la clé part par email) |
| **Releases & updates** | Upload de ZIP par release (stable/beta/dev), entitlement de mise à jour séparé de l'expiration, URLs de téléchargement temporaires signées |
| **Emails** | Templates éditables avec variables `{{…}}`, file + journal complets |
| **Analytics** | Compteurs et graphiques calculés **depuis la base** (aucun chiffre en dur), exports CSV |
| **RGPD** | Champs minimaux, suppression/anonymisation documentée |

## Configuration (`.env`)

Copier `.env.example` → `.env`. Variables : `PORT`, `APP_URL`, `DB_PATH`, `STORAGE_PATH`, `APP_KEY`, `SESSION_SECRET`, `SMTP_*`, `GITHUB_TOKEN`. Les secrets ne quittent jamais le serveur ; `.env` est gitignoré.

## Documentation

- [`docs/API.md`](docs/API.md) — endpoints, requêtes/réponses, codes d'erreur
- [`sdk/InfinityLicenseClient.php`](sdk/InfinityLicenseClient.php) — client PHP prêt à l'emploi (grace period, cache, vérification de signature)
- `Dockerfile` + `docker-compose.yml` — déploiement VPS (volume `storage/` persistant)

## Intégrer un plugin (InfinityCOD ou futur)

```php
require_once 'sdk/InfinityLicenseClient.php';
$client = new InfinityLicenseClient('https://infinitycod.pro', 'infinitycod', $key, $install_id, 72);
$state  = $client->state();          // active / expired / grace_expired …
$client->heartbeat( INFINITYCOD_VERSION );
```

Ou sans SDK : `POST {server}/api/v1/license/activate` avec `license_key|key_hash`, `product`, `domain`, `installation_id`.

## Tests

```bash
npm test   # 24 assertions : setup, commande, licence, activation signée,
           # heartbeat, validation, suspension, reprise, expiration, rate limit…
```

---

© Derouiche Oussama — Infinity Coder. Licence GPL-2.0-or-later.
