# Changelog – Webcooking_Altcha

## Unreleased

- Declare the Magento component and runtime library requirements explicitly in Composer.

## 1.0.0 – 2026-10-11
- Introduced opt-in ALTCHA proof-of-work protection for customer login, registration and password recovery, contact, newsletter, product review, admin login, and admin password recovery forms.
- Added store-scoped settings for proof-of-work difficulty, challenge expiry, widget theme, footer and logo visibility, plus per-form enablement under `Stores > Configuration > Security > ALTCHA`.
- Added signed `SHA-256` challenges bound to the form and store, strict expiry checks, replay protection with shared locking, and best-effort challenge endpoint rate limiting.
- Added automatic encrypted HMAC secret generation and rotation, with the `altcha:disable` CLI command as an anti-lockout escape hatch.
- Vendored the ALTCHA 3.2.1 Web Component and locale bundles for same-origin, Content Security Policy-compatible use without third-party requests.
- Added the public `ChallengeServiceInterface`, `SolutionVerifierInterface`, and `VerificationResultInterface` extension contracts.
- Added PHP `>=8.3`, Magento framework/module constraints, and the patched `altcha-org/altcha` `2.0.3` library dependency.
