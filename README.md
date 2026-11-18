# Webcooking_Altcha — ALTCHA CAPTCHA for Magento 2

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Self-hosted, privacy-friendly **[ALTCHA](https://altcha.org/)** proof-of-work
CAPTCHA for Magento 2 — a GDPR-friendly alternative to Google reCAPTCHA. No
third-party calls, no cookies, no tracking:
the visitor's browser solves a small cryptographic puzzle in a Web Worker, and
your server verifies it.

## Protected forms (v1)

| Storefront (Luma) | Admin (native theme) |
|---|---|
| Customer login | Admin login |
| Customer registration | Admin forgot password |
| Customer forgot password | |
| Contact form | |
| Newsletter subscription | |
| Product review | |

Adding another form is one DI virtual type + one event entry + one system.xml
field + one layout file — see `etc/di.xml` and `etc/frontend/events.xml`.

The supplied layouts target the Luma storefront and Magento's native admin
theme. They do not wire the widget into Hyvä, the Luma AJAX login popup,
checkout/PayPal/wishlist/send-friend flows, or REST/GraphQL APIs. ALTCHA
Sentinel integration is not included.

## Requirements

- PHP `>=8.3`
- Magento packages: `magento/framework ^103.0`, `magento/module-backend ^102.0`,
  `magento/module-config ^101.2`, `magento/module-contact ^100.4`,
  `magento/module-customer ^103.0`, `magento/module-newsletter ^100.4`,
  `magento/module-review ^100.4`, `magento/module-store ^101.1`, and
  `magento/module-user ^101.2`
- The module loads after `Magento_Backend`, `Magento_Config`, `Magento_Contact`,
  `Magento_Customer`, `Magento_Newsletter`, `Magento_Review`, `Magento_Store`,
  and `Magento_User`.
- `altcha-org/altcha` **2.0.3**
- **HTTPS storefront and admin** — the widget requires a secure context (Web Crypto)
- Evergreen browsers (Web Components + Web Crypto). Visitors without JavaScript
  or with legacy browsers see an explanatory message and cannot submit protected
  forms (no fail-open: that would be a trivial bypass).
- **Production, multi-node**: a shared cache backend (Redis/Valkey) is required
  for replay protection to be effective across nodes.

## Installation

```bash
composer require webcooking/altcha
bin/magento module:enable Webcooking_Altcha
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy   # production mode
```

An HMAC secret is generated automatically (encrypted) during `setup:upgrade`.

## Configuration

**Stores → Configuration → Security → ALTCHA**

- **General**: master switch (off by default), HMAC secret (generated during
  installation; clear + save to rotate), proof-of-work difficulty (easy /
  balanced / hard; default balanced), challenge expiry (60–3600 s; default 600).
- **Appearance**: hide ALTCHA footer link (off by default), hide its logo (off
  by default), and choose the theme (auto / light / dark; default auto).
- **Storefront Forms / Admin Forms**: one toggle per form. Everything is **off
  by default** — nothing is blocked until you opt in.

### Migrating from Google reCAPTCHA

If reCAPTCHA is enabled for a form, disable it there **before** enabling ALTCHA
for the same form (the admin panel shows a warning when both are active —
otherwise visitors face two CAPTCHAs or blocked submissions):

| ALTCHA form | reCAPTCHA setting (`type_for`) |
|---|---|
| customer_login | Storefront → Login |
| customer_create | Storefront → Create New Customer Account |
| customer_forgotpassword | Storefront → Forgot Password |
| contact | Storefront → Contact Us |
| newsletter | Storefront → Newsletter Subscription |
| product_review | Storefront → Product Review |
| admin_login | Admin Panel → Login |
| admin_forgotpassword | Admin Panel → Forgot Password |

The same applies to Magento's **built-in CAPTCHA** (Stores → Configuration →
Customers → CAPTCHA, storefront and admin): disable it for forms you protect
with ALTCHA, or visitors/admins will face two challenges.

### Notes & caveats

- **Admin login page left open**: the admin challenge is embedded in the page at
  render time; if the login page stays open longer than the challenge expiry
  (default 600 s), verification fails until the page is reloaded.
- **Custom newsletter template**: enabling newsletter protection switches the
  subscribe block to `Webcooking_Altcha::newsletter/subscribe.phtml` (the widget
  must live inside the `<form>`). If your theme customizes
  `Magento_Newsletter::subscribe.phtml`, re-apply your customizations by
  overriding the module's template in your theme.

## Admin lockout — escape hatches

Admin login protection is opt-in and fails open if the secret is missing, but if
you ever lock yourself out:

```bash
bin/magento altcha:disable admin_login
# or disable everything:
bin/magento altcha:disable --all
# equivalent manual command:
bin/magento config:set altcha/backend_forms/admin_login 0 && bin/magento cache:clean config
```

Note: values locked in `env.php`/`config.php` (`config:set --lock-env`, config
dumps) override the database — if you locked an ALTCHA flag there, edit that
file; the CLI command cannot help.

## Content Security Policy

The module is designed to work under **enforced** CSP with Magento's default
policies, with **no whitelist required**:

- The vendored widget is the ALTCHA `external` bundle: no `blob:`/`data:`
  workers (the SHA-256 worker is a same-origin static file, allowed by
  `child-src`/`script-src 'self'`), no injected inline styles (CSS is a regular
  same-origin stylesheet), no inline scripts.
- Challenge fetches are same-origin (`connect-src 'self'`).

If you run hardened custom policies, keep `'self'` allowed for `script-src`,
`style-src`, `connect-src` and `child-src` (or `worker-src`).

## Security design

- **Patched protocol**: challenges are minted and verified with the official
  [`altcha-org/altcha`](https://packagist.org/packages/altcha-org/altcha) PHP
  library **2.0.3** (pinned), which includes the fix for challenge
  splicing/replay ([GHSA-6gvq-jcmp-8959](https://github.com/altcha-org/altcha-lib/security/advisories/GHSA-6gvq-jcmp-8959)
  / CVE-2025-68113): the HMAC signature covers a canonical serialization of
  every challenge parameter, including expiry.
- **Context binding**: every challenge is cryptographically bound to its form
  and store (`data: {form, store}` covered by the signature). A proof solved
  for the newsletter form cannot be replayed on the login form.
- **Strict expiry** server-side; **replay protection** via consumed-challenge
  cache, with the check-then-consume serialized behind a shared lock
  (`LockManagerInterface`) so concurrent identical submissions cannot both
  pass. Defense in depth on top of signature + expiry: a flushed cache or an
  unavailable lock backend only degrades to the remaining TTL window.
- **Fail-open only on missing secret** (never lock out humans on a
  misconfiguration, especially admins); every actual verification failure
  blocks the submission.
- **Rate-limited challenge endpoint** (60/min/IP, best-effort; behind a
  proxy/CDN configure real-IP restoration or the limit degrades gracefully).
  The proof-of-work asymmetry is the primary anti-abuse mechanism.
- The widget's `humanInteractionSignature` collector is disabled: it is only
  useful with ALTCHA's hosted spam filter and this module never sends it.

## Tests

- **Unit** (no Magento install needed beyond the framework classes):
  `src/Test/Unit/` — real round-trips through the pinned library, anti-splicing,
  context binding, replay, expiry, malformed payloads.
- **Integration** (run inside a Magento installation's `dev/tests/integration`):
  `src/Test/Integration/` — challenge endpoint (scopes, 404s, JSON contract);
  POST with missing / solved / replayed payloads for the storefront forms
  (customer login/create/forgot-password, contact, newsletter, product review)
  and admin login (blocking + store-0 binding); deterministic per-form wiring
  proof for all eight forms (event → observer → form key, both areas, covering
  admin forgot-password); layout injection per form (including the widget
  sitting inside the newsletter `<form>`); and the secret lifecycle (stored
  encrypted in `core_config_data`, read decrypted through the config metadata
  processor).

## Developer extension points

- `Webcooking\Altcha\Api\ChallengeServiceInterface` mints challenges for a form
  and store; `Webcooking\Altcha\Api\SolutionVerifierInterface` verifies widget
  payloads; `Webcooking\Altcha\Api\Data\VerificationResultInterface` exposes
  the result and stable error codes. Each has a preference in `etc/di.xml`.
- The `forms` argument of `Webcooking\Altcha\Model\Config` maps form keys to
  their configuration groups. Standard POST actions use configurable
  `ValidateOnPredispatch` virtual types with `formKey`, optional
  `redirectRoute`, and optional `requiredParam` arguments.
- Adding a protected form also requires its enable field, event observer, and
  layout injection so the widget and server-side validator use the same form
  key.

## Vendored widget

The ALTCHA web component **3.2.1** (MIT) is vendored under
`src/view/base/web/js/altcha/` with recorded SHA-256 provenance — see
[VENDORED.md](src/view/base/web/js/altcha/VENDORED.md). No CDN, no external
requests. Upgrades are deliberate: bump the pin, update hashes, re-run the
interop tests.

## License

MIT — see [LICENSE](LICENSE). © 2026 SAS WEB COOKING.
The vendored ALTCHA widget is © its authors, MIT licensed.
