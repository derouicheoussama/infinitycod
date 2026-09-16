# -*- coding: utf-8 -*-
"""Harnais : section SEO & GEO 5.35.0."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\tools\test-form-engine.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = 'echo "\\n=== BILAN : {$pass} OK, {$fail} échec(s) ===\\n";'
assert d.count(anchor) == 1

section = r'''
/* ---------- 5.35.0 : SEO & GEO ---------- */

echo "\n53) 5.35.0 — SEO enrichi & GEO (moteurs génératifs)\n";
$seo_src = file_get_contents( $plugin_dir . 'includes/seo/class-seo-manager.php' );
check( 'product : shippingDetails + délai dans le JSON-LD', false !== strpos( $seo_src, 'OfferShippingDetails' ) && false !== strpos( $seo_src, 'delivery_estimate' ) );
check( 'product : AggregateRating (étoiles SERP)', false !== strpos( $seo_src, 'aggregateRating' ) );
check( 'local : LocalBusiness + areaServed (wilayas actives)', false !== strpos( $seo_src, 'LocalBusiness' ) && false !== strpos( $seo_src, 'areaServed' ) );
check( 'faq : FAQPage JSON-LD + format question|réponse', false !== strpos( $seo_src, 'FAQPage' ) && false !== strpos( $seo_src, 'faq_pairs' ) );
check( 'landing : canonical + robots + JSON-LD Service', false !== strpos( $seo_src, 'rel="canonical"' ) && false !== strpos( $seo_src, "'@type'      => 'Service'" ) );
check( 'GEO : route /llms.txt structurée', false !== strpos( $seo_src, 'icod_llms' ) && false !== strpos( $seo_src, 'seo_llms_enabled' ) );
check( 'réglages : carte SEO & GEO complète', false !== strpos( $set_page, "'seo_local_enabled'" ) && false !== strpos( $set_page, "'seo_faq'" ) && false !== strpos( $set_page, "'seo_llms_enabled'" ) );
'''

d = d.replace(anchor, section + "\n" + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK section harnais SEO/GEO")
