/* Supprime toute référence factexpert : serveur de licences → infinitycoder.app. */
'use strict';
const fs = require('fs');

function patch(path, pairs) {
  let s = fs.readFileSync(path, 'utf8');
  let changed = false;
  for (const [from, to] of pairs) {
    if (s.includes(from)) {
      s = s.split(from).join(to);
      changed = true;
    }
  }
  if (changed) fs.writeFileSync(path, s);
  console.log((changed ? '✓' : '·') + ' ' + path);
}

patch('infinitycod/includes/license/class-license-manager.php', [
  [' * (protocole compatible avec le serveur de licences factexpert.online).', ' * (protocole du serveur de licences Infinity Coder).'],
  ['https://factexpert.online/api.php', 'https://infinitycoder.app/api.php'],
]);

patch('infinitycod/includes/core/class-settings.php', [
  ["'license_server'        => 'https://factexpert.online/api.php', // API d'activation des licences.", "'license_server'        => 'https://infinitycoder.app/api.php', // API d'activation des licences."],
]);

patch('README.md', [
  ['- Serveur de licences : factexpert.online (partagé avec FactExpert)', "- Licences : activation via infinitycoder.app (clé INFINITY-DEV pour le développement)"],
]);
