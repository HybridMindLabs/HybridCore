# Security Policy

## Reporting a Vulnerability

**Do not report security vulnerabilities through public GitHub issues, pull requests, or discussions.**

If you discover a security vulnerability in HybridCore, please report it responsibly by contacting the core team privately:

- Open a [GitHub Security Advisory](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing/privately-reporting-a-security-vulnerability) using the private reporting feature on this repository.
- Alternatively, contact the maintainers directly via the email listed in the repository profile.

Please include as much detail as possible:

- Description of the vulnerability
- Steps to reproduce
- Potential impact assessment
- Any suggested fix (optional)

We will acknowledge your report within 72 hours and work with you on a responsible disclosure timeline.

---

## What Should Not Be Disclosed Publicly

The following must never be disclosed publicly before a fix is released:

- Authentication bypass vulnerabilities
- Privilege escalation vulnerabilities (e.g. gaining admin from a regular user account)
- Remote code execution (RCE) vulnerabilities
- SQL injection vulnerabilities
- Cross-site scripting (XSS) vulnerabilities that bypass admin-only trust boundaries
- Installer security flaws that could allow re-installation by an attacker
- Extension/theme loading vulnerabilities that allow arbitrary code execution
- Marketplace/licensing bypass vulnerabilities

---

## Security Expectations for Extensions

HybridCore extensions are installed by administrators and run with the same trust level as core application code. Extension developers are expected to:

- Validate and sanitise all user input before processing.
- Never store plaintext passwords or secrets in extension settings.
- Never expose internal data to unauthenticated routes without explicit authorisation.
- Follow the same SQL injection prevention practices as the core (Eloquent query builder, never raw interpolated queries).
- Not bundle obfuscated code that hides behaviour from reviewers.
- Declare all routes, permissions and database tables in their manifest so they can be audited.

HybridCore does not currently perform automated security scanning of third-party extensions. Administrators install extensions at their own risk.

---

## Security Expectations for Themes

Themes control the public-facing UI only. Theme developers are expected to:

- Never include PHP logic that executes arbitrary code.
- Never include JavaScript that exfiltrates user data.
- Not embed third-party scripts not declared in the theme manifest.
- Render user-generated content (UGC) with proper escaping at all times.

---

## Security Expectations for Marketplace Packages

Once the marketplace is launched, all submitted packages (extensions and themes) will be subject to:

- Manual review before public listing.
- Automated static analysis scans.
- Cryptographic signing — packages must be signed by the marketplace and verified on install.
- Removal from the marketplace if a security issue is confirmed, with automated revocation pushed to all installs.

---

## Supported Versions

| Version | Supported |
|---|---|
| 0.2.x | Security patches provided |
| < 0.2.0 | Not supported |

Security patches are applied to the latest released minor version. Keep your
installation up to date via `php artisan hybridcore:update` (see
[DEPLOYMENT.md](DEPLOYMENT.md#9-upgrading)).
