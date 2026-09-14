# -*- coding: utf-8 -*-
# Insere les toggles avances (son commande + rapport hebdo) apres le label menu_badge.
import io
import os

BS = chr(92)
P = 'infinitycod/includes/admin/pages/class-settings-page.php'


def read(path):
    return io.open(path, encoding='utf-8').read()


def write(path, src):
    io.open(path, 'w', encoding='utf-8', newline=chr(10)).write(src)


lines = read(P).split(chr(10))

has_toggles = any('icod[order_sound]' in l for l in lines)
has_badge = any('name="icod[menu_badge]"' in l for l in lines)
print('etat : order_sound present =', has_toggles, '| menu_badge present =', has_badge)

if has_toggles:
    print('deja presents — rien a faire')
elif not has_badge:
    raise SystemExit('menu_badge introuvable — abandon')

out = []
i = 0
inserted = False
while i < len(lines):
    l = lines[i]
    out.append(l)

    if (not inserted) and 'name="icod[menu_badge]"' in l:
        # copie jusqu au </label> qui ferme le label menu_badge.
        j = i + 1
        while j < len(lines):
            out.append(lines[j])
            if lines[j].strip() == '</label>':
                j += 1
                break
            j += 1
        # insere les 2 nouveaux toggles.
        indent = chr(9) * 4
        out.append(indent + '<label class="icod-toggle">')
        out.append(indent + chr(9) + '<input type="checkbox" name="icod[order_sound]" value="1" <?php checked( (int) Settings::get( ' + chr(39) + 'order_sound' + chr(39) + ' ), 1 ); ?> />')
        out.append(indent + chr(9) + '<span><?php echo esc_html( \'Bip + notification \u00e0 chaque nouvelle commande COD (partout dans l\u2019admin)\' ); ?></span>')
        out.append(indent + '</label>')
        out.append(indent + '<label class="icod-toggle">')
        out.append(indent + chr(9) + '<input type="checkbox" name="icod[weekly_report]" value="1" <?php checked( (int) Settings::get( ' + chr(39) + 'weekly_report' + chr(39) + ' ), 1 ); ?> />')
        out.append(indent + chr(9) + '<span><?php echo esc_html( \'Rapport hebdomadaire par email (CA, commandes, paniers r\u00e9cup\u00e9r\u00e9s)\' ); ?></span>')
        out.append(indent + '</label>')
        inserted = True
        i = j
        continue
    i += 1

assert inserted, 'point d insertion non atteint'
write(P, chr(10).join(out))
print('toggles avances inseres')
