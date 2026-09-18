# -*- coding: utf-8 -*-
"""Insère la carte Go Pro sur le dashboard + CSS premium."""
import io

p = 'includes/admin/pages/class-dashboard-page.php'
with io.open(p, 'r', encoding='utf-8', newline='') as f:
    d = f.read()

anchor = "\t\t\t</div>\n\t\t\t<div class=\"icod-kpi-grid\">"
assert d.count(anchor) == 1
insert = (
    "\t\t\t</div>\n"
    "\t\t\t<?php echo \\InfinityCod\\License\\LicenseManager::go_pro_card(); ?>\n"
    "\t\t\t<div class=\"icod-kpi-grid\">"
)
d = d.replace(anchor, insert, 1)
with io.open(p, 'w', encoding='utf-8', newline='') as f:
    f.write(d)
print('OK dashboard Go Pro card')

# CSS premium.
p = 'assets/admin/css/admin.css'
with io.open(p, 'r', encoding='utf-8', newline='') as f:
    d = f.read()
css = '''

/* ============================================================
   Carte Go Pro — roadmap Premium (dashboard)
   ============================================================ */
.icod-gopro{position:relative;overflow:hidden;background:linear-gradient(135deg,#1a1d21 0%,#243447 55%,#1d3a2f 100%);border:1px solid #3a4a5f;border-radius:14px;padding:26px 30px;margin:18px 0 6px;color:#fff}
.icod-gopro::before{content:'\\2605';position:absolute;right:-14px;top:-34px;font-size:150px;line-height:1;color:#f7b600;opacity:.07;pointer-events:none}
.icod-gopro h2{color:#fff;font-size:1.3rem;margin:0 0 8px}
.icod-gopro h2 .star{color:#f7b600;margin-right:4px}
.icod-gopro .icod-gopro-star{position:absolute;top:18px;right:22px;color:#f7b600;font-size:20px;opacity:.9}
.icod-gopro p{color:#c3cdd9;font-size:.92rem;line-height:1.65;margin:0 0 14px;max-width:720px}
.icod-gopro .feat{display:grid;grid-template-columns:1fr 1fr;gap:7px 22px;margin:0 0 18px}
.icod-gopro .feat span{color:#dfe6ee;font-size:.86rem}
.icod-gopro .feat .check{color:#34d399;font-weight:800;margin-right:6px}
.icod-gopro .btns{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.icod-gopro .btns form{margin:0}
.icod-gopro .icod-gopro-btn-primary{background:linear-gradient(135deg,#0e7a4f,#0b6e43)!important;border:0!important;color:#fff!important;font-weight:700;padding:9px 20px!important;border-radius:9px!important;text-shadow:none}
.icod-gopro .icod-gopro-btn-primary:hover{filter:brightness(1.12)}
.icod-gopro .icod-gopro-btn-gold{background:linear-gradient(135deg,#f7b600,#e8a200)!important;border:0!important;color:#1a1d21!important;font-weight:800;padding:9px 20px!important;border-radius:9px!important}
.icod-gopro .icod-gopro-btn-gold:hover{filter:brightness(1.08)}
@media (max-width:782px){.icod-gopro{padding:20px}.icod-gopro .feat{grid-template-columns:1fr}}
@media (prefers-reduced-motion:no-preference){.icod-gopro{animation:icod-gopro-in .35s ease-out}}
@keyframes icod-gopro-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
'''
d = d.rstrip() + '\n' + css
with io.open(p, 'w', encoding='utf-8', newline='') as f:
    f.write(d)
print('OK CSS Go Pro')
