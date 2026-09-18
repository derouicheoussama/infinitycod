# InfinityCod — Documentation

**InfinityCod — Cash on Delivery for WooCommerce (Algeria)**
Complete COD checkout for WooCommerce: one-page order form, 58 wilayas & 1541 communes, home/stopdesk rates, anti-fraud, carrier integrations, WhatsApp automation, quantity offers and P&L analytics.

- Author: Derouiche Oussama ([derouicheoussama.com](https://www.derouicheoussama.com))
- Version: see `infinitycod.php` header
- Requires: WordPress 6.0+, WooCommerce 6.0+, PHP 7.4+

---

## 1. Installation

### Automatic
1. Download `infinitycod.zip` (this package's `infinitycod.zip`).
2. In your WordPress admin: **Plugins → Add New → Upload Plugin**.
3. Select the zip, click **Install Now**, then **Activate**.
4. WooCommerce must be installed and activated (the plugin will prompt you).

### Manual (FTP)
1. Unzip the package.
2. Upload the `infinitycod` folder to `/wp-content/plugins/`.
3. Activate **InfinityCod — Cash on Delivery (COD Algérie)** in Plugins.

---

## 2. Quick start (5 minutes)

1. **Wilayas & Tarifs** — import the 58 wilayas (one click), set your home/stopdesk prices per wilaya or use default prices.
2. **Transporteurs** — connect Yalidine, ZR Express, Maystro, Noest or E-COM with your API keys (optional — orders work without carriers).
3. **Formulaire** — personalize the checkout: colors, fields, offers, WhatsApp button. The live preview updates instantly.
4. Add `[infinitycod_form]` to any page, or the form appears automatically on product pages (toggle in Settings → Formulaire).

---

## 3. Configuration

### Formulaire (Checkout)
- Checkout Builder: enable/disable/reorder fields (name, phone, wilaya, commune, address, email, note…)
- Colors & accent, presets, dark mode, RTL (Arabic)
- Offers by quantity (tiers), free-shipping threshold, urgency timer
- Social proof, visitor counter, stock badge

### Paiement en ligne
- **CIB / Edahabia** via Chargily Pay (Algeria)
- **Visa / Mastercard** via Stripe Checkout
- **PayPal** (Orders v2)
Each gateway: paste your API credentials in Réglages → Paiement. Customers choose COD or online payment on the form.

### WhatsApp automatique
- Cloud API, Ultramsg or TextMeBot gateways
- Messages: order received, shipped, delivery confirmation, abandoned-cart reminder
- One-click customer confirmation link included

### Transporteurs
- Yalidine, ZR Express, Maystro, Noest, E-COM, DHD (Ecotrack-compatible)
- Create parcels from the Orders screen, tracking auto-synced hourly

### Anti-fraude
- Honeypot, signed timestamps, IP/phone/email rate limits, local + community blacklist, fraud score with detailed flags, delivery-history risk per phone number

### Anti-leak
- Pixel leak guard (server-side purchase event only via CAPI when enabled)
- Phone masking in the orders list
- Export journal with traceability watermark + mass-export email alerts
- Plugin file integrity monitoring

### Fidélité
- Points earned on delivered orders, automatic discount at checkout for returning customers (threshold and rates configurable)

### SEO & GEO
- Product JSON-LD with ratings, shipping cost & transit time
- LocalBusiness with served areas, FAQPage from your FAQ
- Wilaya landing pages (`/livraison-alger/`…)
- `/llms.txt` for AI search engines

---

## 4. FAQ

**Does the order form require the WooCommerce cart?**
No — the one-page form creates a real WooCommerce order directly (COD), bypassing the cart.

**Can customers pay online?**
Yes — CIB/Edahabia (Chargily), Visa/Mastercard (Stripe) and PayPal. Customers choose COD or online payment on the same form.

**Is Arabic/RTL supported?**
Yes — full RTL with Arabic wilaya/commune names.

**How do updates work?**
The plugin checks for updates hourly and shows them in wp-admin. An optional email alert notifies you when a new version is released. Updates are signed and SHA-256 verified.

**Does the plugin collect data?**
No. Update checks contact the author's public release server (version metadata only, no personal data). Pixels (Meta/TikTok/Google) are the merchant's own, with a leak-guard option and consent support.

---

## 5. Support

- Support: https://www.derouicheoussama.com
- Changelog: `CHANGELOG.md` in this package, and the plugin's Mises à jour screen.

---

## 6. Changelog

See `CHANGELOG.md` at the root of this package.
