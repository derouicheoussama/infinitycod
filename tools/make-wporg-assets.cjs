'use strict';
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const out = path.join(__dirname, '..', 'infinitycod', 'assets');

function crc32(buf) {
  let table = crc32.table;
  if (!table) {
    table = crc32.table = [];
    for (let n = 0; n < 256; n++) {
      let c = n;
      for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
      table[n] = c >>> 0;
    }
  }
  let crc = 0xffffffff;
  for (let i = 0; i < buf.length; i++) crc = table[(crc ^ buf[i]) & 0xff] ^ (crc >>> 8);
  return (crc ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));
  return Buffer.concat([len, body, crc]);
}
function png(width, height, pixelFn) {
  const raw = Buffer.alloc(height * (width * 3 + 1));
  for (let y = 0; y < height; y++) {
    const row = y * (width * 3 + 1);
    raw[row] = 0;
    for (let x = 0; x < width; x++) {
      const [r, g, b] = pixelFn(x, y);
      const o = row + 1 + x * 3;
      raw[o] = r; raw[o + 1] = g; raw[o + 2] = b;
    }
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8; ihdr[9] = 2;
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}
const mix = (a, b, t) => Math.round(a + (b - a) * t);
const base = (w, h) => (x, y) => {
  const t = Math.min(1, (x / w + y / h) / 2);
  return [mix(0x18, 0x0e, t), mix(0x77, 0x7a, t), mix(0xc2, 0x4f, t)];
};

function banner(W, H, name) {
  const R = Math.round(H * 0.12);
  const cardX = Math.round(W * 0.62), cardY = Math.round(H * 0.18);
  const cw = Math.round(W * 0.30), ch = Math.round(H * 0.64);
  const ocx = Math.round(W * 0.70), ocy = Math.round(H * 0.30), orad = Math.round(H * 0.09);
  fs.writeFileSync(path.join(out, name), png(W, H, (x, y) => {
    const [r, g, b] = base(W, H)(x, y);
    if (x >= cardX && x < cardX + cw && y >= cardY && y < cardY + ch) {
      const inX = Math.min(x - cardX, cardX + cw - x), inY = Math.min(y - cardY, cardY + ch - y);
      if (inX >= R || inY >= R || (inX * inX + inY * inY) <= R * R) return [255, 255, 255];
    }
    if ((x - ocx) ** 2 + (y - ocy) ** 2 <= orad * orad) return [0xf7, 0x9f, 0x00];
    return [r, g, b];
  }));
}
function icon(S, name) {
  const m = Math.round(S * 0.16), R = Math.round(S * 0.22);
  fs.writeFileSync(path.join(out, name), png(S, S, (x, y) => {
    const [r, g, b] = base(S, S)(x, y);
    if (x >= m && x < S - m && y >= m && y < S - m) {
      const inX = Math.min(x - m, S - m - x), inY = Math.min(y - m, S - m - y);
      if (inX >= R || inY >= R || (inX * inX + inY * inY) <= R * R) return [255, 255, 255];
    }
    return [r, g, b];
  }));
}

banner(772, 250, 'banner-772x250.png');
banner(1544, 500, 'banner-1544x500.png');
icon(128, 'icon-128x128.png');
icon(256, 'icon-256x256.png');
console.log('OK assets wporg generes');
