/**
 * Écriture zip minimale sans dépendance (deflate via zlib natif de Node).
 * Conforme PKZip : en-têtes locaux + répertoire central + EOCD.
 * Les noms d'entrées utilisent toujours '/'.
 */
'use strict';

const fs = require('fs');
const zlib = require('zlib');

/* DOS epoch (1980-01-01) → date/heure zip. */
function dosDateTime(date) {
  const year = Math.max(1980, date.getFullYear());
  const time = ((date.getHours() << 11) | (date.getMinutes() << 5) | (date.getSeconds() >> 1)) & 0xffff;
  const day = (((year - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate()) & 0xffff;
  return { time, day };
}

function crc32(buf) {
  let table = crc32.table;
  if (!table) {
    table = crc32.table = new Int32Array(256);
    for (let n = 0; n < 256; n++) {
      let c = n;
      for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
      table[n] = c;
    }
  }
  let crc = -1;
  for (let i = 0; i < buf.length; i++) crc = (crc >>> 8) ^ table[(crc ^ buf[i]) & 0xff];
  return (crc ^ -1) >>> 0;
}

/**
 * Crée l'archive.
 *
 * @param {string} outPath       Chemin du zip.
 * @param {Array<{name: string, data: Buffer}>} entries Entrées ('/' uniquement).
 */
function makeZip(outPath, entries) {
  const chunks = [];
  const central = [];
  let offset = 0;
  const stamp = dosDateTime(new Date());

  for (const entry of entries) {
    const nameBuf = Buffer.from(entry.name, 'utf8');
    const data = entry.data;
    const crc = crc32(data);
    const deflated = zlib.deflateRawSync(data, { level: 9 });

    // Méthode 8 (deflate) sauf si ça grossit (alors méthode 0, stockée).
    const useDeflate = deflated.length < data.length;
    const payload = useDeflate ? deflated : data;
    const method = useDeflate ? 8 : 0;

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);        // signature
    local.writeUInt16LE(20, 4);                // version min
    local.writeUInt16LE(0x0800, 6);            // flags : UTF-8
    local.writeUInt16LE(method, 8);
    local.writeUInt16LE(stamp.time, 10);
    local.writeUInt16LE(stamp.day, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(payload.length, 18);   // compressé
    local.writeUInt32LE(data.length, 22);      // original
    local.writeUInt16LE(nameBuf.length, 26);
    local.writeUInt16LE(0, 28);                // longueur extra locale

    chunks.push(local, nameBuf, payload);

    const centralHeader = Buffer.alloc(46);
    centralHeader.writeUInt32LE(0x02014b50, 0);
    centralHeader.writeUInt16LE(0x031e, 4);    // version créée (UNIX, 3.30)
    centralHeader.writeUInt16LE(20, 6);        // version min
    centralHeader.writeUInt16LE(0x0800, 8);    // flags UTF-8
    centralHeader.writeUInt16LE(method, 10);
    centralHeader.writeUInt16LE(stamp.time, 12);
    centralHeader.writeUInt16LE(stamp.day, 14);
    centralHeader.writeUInt32LE(crc, 16);
    centralHeader.writeUInt32LE(payload.length, 20);
    centralHeader.writeUInt32LE(data.length, 24);
    centralHeader.writeUInt16LE(nameBuf.length, 28);
    centralHeader.writeUInt16LE(0, 30);        // extra
    centralHeader.writeUInt16LE(0, 32);        // commentaire
    centralHeader.writeUInt16LE(0, 34);        // disque
    centralHeader.writeUInt16LE(0, 36);        // attributs internes
    centralHeader.writeUInt32LE(0o100644 * 0x10000, 38); // attributs externes (fichier 0644), hors int32 signé
    centralHeader.writeUInt32LE(offset, 42);   // position de l'en-tête local

    central.push(centralHeader, nameBuf);
    offset += local.length + nameBuf.length + payload.length;
  }

  const centralBuf = Buffer.concat(central);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(0, 4);
  eocd.writeUInt16LE(0, 6);
  eocd.writeUInt16LE(entries.length, 8);
  eocd.writeUInt16LE(entries.length, 10);
  eocd.writeUInt32LE(centralBuf.length, 12);
  eocd.writeUInt32LE(offset, 16);
  eocd.writeUInt16LE(0, 20);

  fs.writeFileSync(outPath, Buffer.concat([...chunks, centralBuf, eocd]));
}

module.exports = { makeZip };
