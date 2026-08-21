#!/usr/bin/env node
/**
 * Draggo wiring audit (Schicht 1). Static cross-reference: every StyleSchema
 * option key must be (a) whitelisted in InputSanitizer (else silent data loss),
 * and (b) emit CSS somewhere (compiler/listener/generator/controllers) else it's
 * a dead switch. Also flags sanitizer keys with no control (orphans).
 *
 * Heuristic (regex over source) — flags SUSPECTS to verify, not proof.
 * Run: node tools/wiring-audit.mjs
 */
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => { try { return readFileSync(join(ROOT, p), 'utf8'); } catch { return ''; } };

const schemaSrc = read('src/Control/StyleSchema.php');
const sanitizerSrc = read('src/Security/InputSanitizer.php');
const compilerSrc = read('src/Layout/LayoutStyleCompiler.php');
const containerSrc = read('src/EventListener/ContainerLayoutListener.php');
const cssGenSrc = read('src/Css/CssGenerator.php');
const defaultsSrc = read('src/Token/DefaultsStore.php');
const editorSrc = read('Resources/public/draggo-editor.js');

// Keys that are layout-structural / handled outside the style chain — ignore.
const IGNORE = new Set([
  'responsive', 'row', 'col', 'customCss', 'cssClass', 'cssId',
  // grid/structure handled by GridPresets / listeners, not compile()
  'gridPreset', 'gridCustom', 'gridTablet', 'gridMobile', 'width', 'gap',
  'display', 'flexDirection', 'flexJustify', 'flexAlign', 'flexWrap', 'gridColumns',
  'alignX', 'alignY', 'align', 'minHeight',
  // visibility + media handled by dedicated emitters
  'hideDesktop', 'hideTablet', 'hideMobile',
]);

// 1. Schema control keys (Control::type('key', ...)) — EXCLUDE Control::group
// (its first arg is a German group title, not a key).
const schemaKeys = new Set();
for (const m of schemaSrc.matchAll(/Control::(\w+)\(\s*'([a-zA-Z0-9_]+)'/g)) {
  if (m[1] !== 'group') schemaKeys.add(m[2]);
}

// Broad quoted-identifier capture (catches foreach lists, $eff('key'), maps).
const quotedIdents = (src) => {
  const s = new Set();
  for (const m of src.matchAll(/'([a-zA-Z][a-zA-Z0-9_]+)'/g)) s.add(m[1]);
  return s;
};

// 2. Sanitizer whitelist keys (any quoted identifier in the file).
const sanitizerKeys = quotedIdents(sanitizerSrc);

// 3. Keys that produce CSS (compiler/listener/generator/defaults) — broad.
const cssKeys = quotedIdents(compilerSrc + containerSrc + cssGenSrc + defaultsSrc);
// Logo/img sizing uses dynamic keys ($p.'Width' etc.) — mark as handled.
['logo', 'img'].forEach((p) => ['Width', 'MaxWidth', 'Height', 'Fit', 'Radius'].forEach((s) => cssKeys.add(p + s)));

// 4. Editor live-preview keys (applyBoxStyle/applyBg/applyOverlay/applyMediaBg).
const editorPreviewKeys = new Set();
const previewBlock = (() => {
  const idx = editorSrc.indexOf('applyBoxStyle(');
  const idx2 = editorSrc.indexOf('applyBg(');
  const start = Math.min(...[idx, idx2].filter((n) => n >= 0));
  return editorSrc.slice(start, start + 8000);
})();
for (const m of previewBlock.matchAll(/\bl\.([a-zA-Z0-9_]+)/g)) editorPreviewKeys.add(m[1]);

// ── Cross-reference ──
const notSanitized = [...schemaKeys].filter((k) => !IGNORE.has(k) && !sanitizerKeys.has(k)).sort();
const noCss = [...schemaKeys].filter((k) => !IGNORE.has(k) && !cssKeys.has(k)).sort();
const orphanSanitized = [...sanitizerKeys].filter((k) => !IGNORE.has(k) && !schemaKeys.has(k) && !/^(nav|sub|btn|box|acc|tab|car|ga|cta|map|alert|ov|bg|grad|pos|transform|anim|sticky|logo|img|hover|font|text|line|letter|word|border|spacer|loop|hs|prl|prt|vp)/.test(k)).sort();

// 5. Visual heuristic: element-root classes with display:flex/grid that do NOT
// consume --bld-justify → element `align` likely can't centre their box/items.
// (Inner-part classes like *-ico/*-link/*-head are excluded — only roots.)
const frontendCss = read('Resources/public/draggo-frontend.css');
const INNER = /-(ico|icon|link|head|body|inner|num|prefix|suffix|item|track|slide|dots|prev|next|btn|toggle|close|backdrop|bar|copy|tip|dot|point|frame|ov|img|media|title|text|author|amount|period|price|feature|features|name|row|unit|lbl|stage|list|sep|active|teaser|more|words|before|after|date|ph|load|consent|panel|nav|face|front|back)$/;
const alignResistant = [];
for (const m of frontendCss.matchAll(/^\.(draggo-[a-z0-9]+)\s*\{([^}]*)\}/gm)) {
  const cls = m[1], body = m[2];
  if (INNER.test(cls)) continue;
  if (/display:\s*(flex|grid|inline-flex)/.test(body) && !/--bld-justify/.test(body)) {
    alignResistant.push(cls + '  {' + body.replace(/\s+/g, ' ').trim().slice(0, 60) + '…}');
  }
}

const out = [];
out.push('# Draggo Wiring-Audit (Schicht 1)\n');
out.push(`Schema-Keys: ${schemaKeys.size} | Sanitizer-Keys: ${sanitizerKeys.size} | CSS-Keys: ${cssKeys.size} | Editor-Preview-Keys: ${editorPreviewKeys.size}\n`);

out.push('\n## ⛔ KRITISCH — Control vorhanden, aber NICHT sanitisiert (stiller Datenverlust):');
out.push(notSanitized.length ? notSanitized.map((k) => '  - ' + k).join('\n') : '  (keine)');

out.push('\n## ⚠ Control vorhanden, aber erzeugt scheinbar KEIN CSS (toter Schalter?) — verifizieren:');
out.push(noCss.length ? noCss.map((k) => '  - ' + k).join('\n') : '  (keine)');

out.push('\n## ℹ Sanitisierte Keys ohne offensichtliches Control-Präfix (mögliche Altlast) — verifizieren:');
out.push(orphanSanitized.length ? orphanSanitized.map((k) => '  - ' + k).join('\n') : '  (keine)');

out.push('\n## 🎯 Element-Roots flex/grid OHNE --bld-justify (Element-Ausrichtung zentriert evtl. nicht) — verifizieren:');
out.push(alignResistant.length ? alignResistant.map((k) => '  - ' + k).join('\n') : '  (keine)');

// ── Schicht 3b: visual review checklist in blocks of 10 ──
const ceDir = join(ROOT, 'src/Controller/ContentElement');
const types = new Set();
for (const f of readdirSync(ceDir)) {
  if (!f.endsWith('.php')) continue;
  const m = read('src/Controller/ContentElement/' + f).match(/type:\s*'(draggo_[a-z_]+)'/);
  if (m && !['draggo_row_start', 'draggo_col', 'draggo_row_stop'].includes(m[1])) types.add(m[1]);
}
const list = [...types].sort();
out.push('\n## ✅ Visueller Review (Schicht 3) — in 10er-Blöcken abhaken (Desktop/Tablet/Mobil):');
out.push('   Pro Element: [ ] rendert  [ ] jede Option im Editor da  [ ] wirkt im FE  [ ] Ausrichtung ok  [ ] responsive ok  [ ] Editor=FE 1:1');
list.forEach((t, i) => {
  if (i % 10 === 0) out.push('\n   ── Block ' + (i / 10 + 1) + ' ──');
  out.push('   [ ] ' + String(i + 1).padStart(2, '0') + '  ' + t);
});
out.push('\n   (Demo-Seite erzeugen: vendor/bin/contao-console contao:draggo:demo <artikelId> --reset)');

console.log(out.join('\n'));
