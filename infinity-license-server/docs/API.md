# API — Infinity License Server (v1)

Base : `{APP_URL}/api/v1` — JSON UTF-8. Sans authentification admin (les plugins clients ne doivent jamais accéder aux endpoints d'administration).

Réponses sensibles incluent une signature Ed25519 (`signature`, base64) vérifiable avec `GET /api/v1/public-key`.

## POST /api/v1/license/activate

```json
{
  "license_key": "INFC-XXXX-XXXX-XXXX-XXXX",
  "product": "infinitycod",
  "domain": "https://www.boutique.dz",
  "site_url": "https://www.boutique.dz",
  "plugin_version": "4.4.0",
  "wordpress_version": "6.7",
  "php_version": "8.2",
  "installation_id": "opc…"
}
```

`license_key` peut être remplacé par `key_hash` (SHA-256 hex de la clé — format envoyé par InfinityCOD).

Réponse 200 :

```json
{
  "success": true, "status": "active", "at": "2026-09-09 12:00:00",
  "license": { "id": 1, "status": "ACTIVE", "expires_at": "2027-09-09 00:00:00", "activation_limit": 3, "activations_used": 1, "updates_until": "2027-09-09" },
  "installation": { "id": "…", "domain": "boutique.dz", "environment": "PRODUCTION" },
  "compat": { "status": "ACTIVE", "client": "Nom du client", "expires_at": "…" },
  "signature": "…", "algorithm": "ed25519"
}
```

## POST /api/v1/license/validate
Mêmes identifiants. Retourne `status` (`active`, `expired`, `suspended`, `revoked`) + `error.code` si non active.

## POST /api/v1/license/deactivate
Libère un slot d'activation (`installation_id` ou `domain`).

## POST /api/v1/license/heartbeat
Met à jour `last_seen` et la version du plugin. Réponse signée avec l'état courant.

## GET /api/v1/license/check-update?license_key=…&product=infinitycod&version=4.4.0&channel=stable
Retourne `update_available`, `download_allowed`, `latest`, `changelog` et une `download_url` **temporaire** (1 h) si autorisé. Entitlement contrôlé : licence active + `updates_until` + `allowed_versions` (ex. `1.x`).

## GET /api/v1/download/{token}
Téléchargement du ZIP (jeton à usage limité, expirant). Le chemin de stockage n'est jamais exposé.

## Codes d'erreur
`INVALID_LICENSE`, `LICENSE_EXPIRED`, `LICENSE_SUSPENDED`, `LICENSE_REVOKED`, `ACTIVATION_LIMIT_REACHED`, `DOMAIN_NOT_ALLOWED`, `PRODUCT_NOT_ALLOWED`, `UPDATE_NOT_ALLOWED`, `RATE_LIMITED` (HTTP 429), `SERVER_UNAVAILABLE` (HTTP 500).

## Rate limits (par IP)
activate 30/h · validate 120/h · heartbeat 240/h · deactivate 60/h · check-update 120/h.

## Webhook entrant — Freemius : POST /api/webhooks/freemius?token=…

Reçoit les événements Freemius et transforme un paiement réussi en commande
payée + licence + email au client. **Activé uniquement si `FREEMIUS_WEBHOOK_TOKEN`
est configuré** (variable d'environnement) ; le token passé en query
(`?token=…`) ou en en-tête `X-Webhook-Token` est comparé en temps constant —
401 sinon. Si `FREEMIUS_WEBHOOK_SECRET` est configuré et que la requête porte
un en-tête `x-signature` (HMAC-SHA256 hex du corps brut), la signature est
vérifiée (401 si invalide).

Comportement par type d'événement :

| Événement (exemples) | Effet |
|---|---|
| `payment.success`, `subscription.started`, `subscription.renewed` | Commande `FS-XXXXXX` PAID + licence (`INFC-…`) + email `license_created`. Si le client possède déjà une licence du produit → **prolongation** de 1 an (`renewed: true`, email `license_renewed`), jamais de doublon |
| `payment.refunded`, `subscription.cancelled` | Notification admin uniquement — **aucune action automatique** sur la licence (décision marchande) |
| Autres | Journalisés (`webhook_events`, `api_logs`) |

- **Idempotent** : `UNIQUE(provider, event_key)` — une re-livraison répond
  `{"success":true,"dedupe":true}` sans reproduire d'effet.
- Réponse achat : `{"success":true,"order":"FS-…","license":"INFC-…-…-…-…","renewed":false}`.
- Mapping produit/plan : réglages `freemius_plugin_id` (sécurité : événement
  d'un autre plugin_id refusé), `freemius_product_slug` (défaut `infinitycod`),
  `freemius_plan_id` ; le plan est sinon déduit du montant, sinon premier plan actif.
- L'email acheteur est lu dans les formes de payload Freemius courantes
  (`objects.user.email`, `user.email`, `email`…). Payload sans email exploitable
  → `{"success":false,"code":"NO_EMAIL"}` + notification (HTTP 200 : une
  re-livraison ne réparera pas un payload invalide).

Configurer le webhook : Freemius → Settings → Webhooks → URL
`https://votre-serveur/api/webhooks/freemius?token=<FREEMIUS_WEBHOOK_TOKEN>`.
