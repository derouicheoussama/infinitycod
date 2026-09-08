/**
 * Génère languages/infinitycod.pot à partir des chaînes traduisibles.
 * Usage : node tools/make-pot.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', 'infinitycod');
const out = path.join(root, 'languages', 'infinitycod.pot');

// Fonctions à 2 arguments (texte, domaine) + _n (singulier, pluriel, domaine).
const patterns = [
  /__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinitycod'/g,
  /esc_html__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinitycod'/g,
  /esc_attr__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinitycod'/g,
  /esc_html_e\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinitycod'/g,
  /esc_attr_e\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinitycod'/g,
  /_e\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinitycod'/g,
];

const plurals = /_n\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'\s*,[^,]+,\s*'infinitycod'/g;

const entries = new Map(); // msgid -> { refs: [] , plural? }

function unescape(s) {
  return s.replace(/\\'/g, "'").replace(/\\"/g, '"');
}

function add(msgid, ref, plural) {
  const key = msgid + (plural ? '\u0000' + plural : '');
  if (!entries.has(key)) entries.set(key, { msgid, plural, refs: [] });
  entries.get(key).refs.push(ref);
}

(function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) { walk(full); continue; }
    if (!entry.name.endsWith('.php')) continue;

    const src = fs.readFileSync(full, 'utf8');
    const rel = path.relative(root, full).replace(/\\/g, '/');
    const lines = src.split('\n');

    // Analyse ligne par ligne pour garder une référence approximative.
    lines.forEach((line, i) => {
      for (const re of patterns) {
        re.lastIndex = 0;
        let m;
        while ((m = re.exec(line))) {
          add(unescape(m[1]), rel + ':' + (i + 1));
        }
      }
      plurals.lastIndex = 0;
      let mp;
      while ((mp = plurals.exec(line))) {
        add(unescape(mp[1]), rel + ':' + (i + 1), unescape(mp[2]));
      }
    });
  }
})(root);

const date = new Date().toISOString().slice(0, 10);
let pot = `# Copyright (C) 2026 Infinity Coder
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: InfinityCod 1.0.0\\n"
"Report-Msgid-Bugs-To: https://infinitycoder.app\\n"
"POT-Creation-Date: ${date} 10:00+0100\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\\n"
"X-Domain: infinitycod\\n"
`;

for (const { msgid, plural, refs } of entries.values()) {
  pot += '\n' + refs.slice(0, 4).map(r => '#: ' + r).join('\n') + '\n';
  const id = msgid.replace(/"/g, '\\"');
  if (plural) {
    pot += `msgid "${id}"\nmsgid_plural "${plural.replace(/"/g, '\\"')}"\nmsgstr[0] ""\nmsgstr[1] ""\n`;
  } else {
    pot += `msgid "${id}"\nmsgstr ""\n`;
  }
}

fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, pot);
console.log(`✓ ${out} (${entries.size} chaînes)`);
