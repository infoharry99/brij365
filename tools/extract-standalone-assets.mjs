import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';

const laravelRoot = path.resolve(import.meta.dirname, '..');
const sourceHtml = path.resolve(laravelRoot, '..', 'Builder360 ERP (Standalone) (1).html');
const cssPath = path.join(laravelRoot, 'resources', 'css', 'app.css');
const jsPath = path.join(laravelRoot, 'resources', 'js', 'app.jsx');
const legacyDir = path.join(laravelRoot, 'resources', 'js', 'legacy');

const html = fs.readFileSync(sourceHtml, 'utf8');

function readScriptPayload(type) {
  const tag = `<script type="${type}">`;
  const start = html.indexOf(tag);
  if (start === -1) {
    throw new Error(`Missing ${type} script payload in standalone HTML`);
  }
  const contentStart = start + tag.length;
  const end = html.indexOf('</script>', contentStart);
  return html.slice(contentStart, end);
}

function assetBytes(entry) {
  const bytes = Buffer.from(entry.data, 'base64');
  return entry.compressed ? zlib.gunzipSync(bytes) : bytes;
}

function safeName(index, id) {
  return `${String(index).padStart(3, '0')}-${id.replace(/[^a-z0-9_-]/gi, '-')}.jsx`;
}

const manifest = JSON.parse(readScriptPayload('__bundler/manifest'));
const template = JSON.parse(readScriptPayload('__bundler/template'));

fs.rmSync(legacyDir, { recursive: true, force: true });
fs.mkdirSync(legacyDir, { recursive: true });

let css = Array.from(template.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g))
  .map((match) => match[1].trim())
  .filter(Boolean)
  .join('\n\n');

css = css.replace(/url\(["']?([0-9a-f-]{20,})["']?\)/gi, (full, assetId) => {
  const entry = manifest[assetId];
  if (!entry) {
    return full;
  }
  const mime = entry.mime || 'application/octet-stream';
  return `url("data:${mime};base64,${assetBytes(entry).toString('base64')}")`;
});

fs.writeFileSync(
  cssPath,
  `/* Builder360 ERP-CRM UI extracted from the standalone prototype for Laravel Blade + Vite. */\n${css}\n`,
  'utf8',
);

const scripts = Array.from(template.matchAll(/<script\s+type="text\/babel"(?:\s+src="([^"]+)")?\s*>([\s\S]*?)<\/script>/g));
const imports = [];

scripts.forEach((script, index) => {
  const [, src, inlineBody] = script;
  const id = src || `inline-${index}`;
  const source = src ? assetBytes(manifest[src]).toString('utf8') : inlineBody.trim();
  if (!source) {
    return;
  }

  const fileName = safeName(imports.length, id);
  const body = [
    'const React = window.React;',
    'const ReactDOM = window.ReactDOM;',
    '',
    source,
    '',
  ].join('\n');

  fs.writeFileSync(path.join(legacyDir, fileName), body, 'utf8');
  imports.push(fileName);
});

const importLines = imports.map((file) => `  await import('./legacy/${file}');`).join('\n');

fs.writeFileSync(
  jsPath,
  `import React from 'react';\nimport { createRoot } from 'react-dom/client';\n\nwindow.React = React;\nwindow.ReactDOM = { createRoot };\n\nasync function bootBuilder360() {\n${importLines}\n\n  if (typeof window.__BOOT__ !== 'function') {\n    throw new Error('Builder360 boot function was not registered.');\n  }\n\n  window.__BOOT__();\n}\n\nbootBuilder360().catch((error) => {\n  console.error('[Builder360] Laravel/Vite boot failed', error);\n  const root = document.getElementById('root');\n  if (root) {\n    root.innerHTML = '<div style="padding:24px;font-family:system-ui,sans-serif;color:#991b1b"><strong>Builder360 failed to load.</strong><br/>Check the browser console for details.</div>';\n  }\n});\n`,
  'utf8',
);

console.log(JSON.stringify({
  sourceHtml,
  cssPath,
  jsPath,
  legacyScripts: imports.length,
}, null, 2));
