# Security Policy

The security of `phatnt93/php-upload-helper` is a paramount priority. This library handles untrusted file uploads and file system storage, so we treat all potential vulnerabilities with extreme seriousness.

---

## Supported Versions

Only the latest major version branch receives security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

---

## Reporting a Vulnerability

**Please DO NOT report security vulnerabilities via public GitHub issues, discussions, or pull requests.**

Instead, please report potential security vulnerabilities through one of the following private channels:

1. **GitHub Private Vulnerability Reporting (Preferred):**
   - Navigate to the [Security Advisories tab](https://github.com/phatnt93/php-upload-helper/security/advisories) of this repository.
   - Click **"Report a vulnerability"** to submit a private draft advisory.

2. **Email Security Contact:**
   - If GitHub Private Reporting is unavailable, send an email to:
     - `phatnt93@gmail.com`
     - CC: `thanhphat.uit@gmail.com`
   - Include `[SECURITY] php-upload-helper vulnerability report` in the subject line.

### What to Include in Your Report

To help us triage and resolve the issue quickly, please provide:
- A clear description of the vulnerability and its potential impact.
- Step-by-step reproduction instructions or a minimal Proof of Concept (PoC) script.
- The versions of `php-upload-helper` and PHP environment tested.
- Any suggested fixes or mitigations if you have them.

---

## Response & Disclosure Process

1. **Initial Response:** We will acknowledge receipt of your vulnerability report within **48 hours**.
2. **Investigation & Triage:** We will verify the issue, assess severity, and determine an appropriate fix.
3. **Patch & Release:** A fix will be developed in a private security fork, verified against the test matrix, and tagged in a patch release.
4. **Public Advisory:** A CVE and GitHub Security Advisory will be published simultaneously with the patch release, giving proper credit to the reporter (unless anonymity is requested).
