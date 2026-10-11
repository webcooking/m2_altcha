# Vendored ALTCHA widget

- Package: `altcha` (npm), version **3.2.1** (exact pin)
- Source: https://registry.npmjs.org/altcha/-/altcha-3.2.1.tgz
- Tarball sha256: `af4da2c0f119bd18bc7ccc8e5439833babf0c9916f63ea7815074cb84171c6d0`
- Vendored on: 2026-07-16
- License: MIT (see LICENSE.txt in this directory)
- Bundle variant: `dist/external` — chosen for strict-CSP compatibility:
  no Blob/data: workers (workers must be registered from same-origin URLs),
  no injected inline styles (CSS shipped as a separate file).

## Files

All JS files are stored with a `.min.js` name (even where upstream ships them
unminified) so their deployed static-content names are identical whether
Magento JS minification is enabled or not — the worker and i18n URLs are
resolved browser-side and bypass Magento's min-sign logic.

| File | Origin in package | sha256 |
|---|---|---|
| `altcha.min.js` | `dist/external/altcha.min.js` | `a8d1a3801f4574b5a86a73980c4bc53507f5d60031fb16033c5ef6ff00459cf6` |
| `workers/sha.min.js` | `dist/workers/sha.js` | `47f99223e06444e4b11bd74ed4ebe68e3b13a3ba99f778d013111e6c6cb43074` |
| `../../css/altcha.css` | `dist/external/altcha.css` | `03622256effb4e962d0c1da9a59684cf237f541f290703934c07a97dabcbad47` |
| `i18n/*.min.js` | `dist/i18n/*.js` (ESM only) | (per-locale, self-registering into `globalThis.$altcha.i18n`) |

## Verified element contract (3.2.1, from shipped code)

HTML attributes: `auto`, `challenge` (URL, or inline JSON when the value starts
with `{`), `configuration` (JSON of Configuration overrides, e.g. `hideFooter`,
`hideLogo`, `humanInteractionSignature`), `display`, `language`, `name`
(hidden input name, default `altcha`), `theme`, `type`, `workers`.

The `external` variant does NOT bundle worker code: the host page must register
worker factories in `globalThis.$altcha.algorithms` (done by `../altcha-loader.min.js`).
i18n is registry-based: each `i18n/<locale>.min.js` registers itself into
`globalThis.$altcha.i18n`; the widget resolves via its `language` attribute,
then `<html lang>`, then `navigator.languages`.

Payload posted in the hidden input: base64 of
`{"challenge":{"parameters":{...},"signature":"..."},"solution":{"counter":n,"derivedKey":"...","time":n}}`
— matches `altcha-org/altcha` PHP library v2 (`AltchaOrg\Altcha`).

## Upgrading

1. Download the new tarball, verify its sha256 against the npm registry.
2. Replace the files above, update this manifest (versions + hashes).
3. Re-run the interop test suite (unit + browser E2E) before releasing —
   the element contract above must be re-verified against the new release.
