# -*- coding: utf-8 -*-
"""Patch 5.36.0 : réglages Stripe/PayPal, libellés formulaire, logos, neutralisation GitHub."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []


def patch(rel, pairs):
    p = ROOT + BS + rel.replace("/", BS)
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    for old, new in pairs:
        n = d.count(old)
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


# ---------- 1) DEFAULTS ----------
patch("includes/core/class-settings.php", [
    (
        "\t\t\t// Anti-leak : confidentialité des clients et des exports.",
        "\t\t\t// Passerelle de paiement en ligne.\n"
        "\t\t\t'stripe_currency'      => 'usd',  // Devise de facturation Stripe.\n"
        "\t\t\t'stripe_rate'          => 1,      // Taux DZD -> devise Stripe.\n"
        "\t\t\t'paypal_currency'      => 'USD', // Devise de facturation PayPal.\n"
        "\t\t\t'paypal_rate'          => 1,      // Taux DZD -> devise PayPal.\n\n"
        "\t\t\t// Anti-leak : confidentialité des clients et des exports.",
    ),
])

print("\n".join("OK  " + o for o in ok))
