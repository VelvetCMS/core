# Security Policy

## Reporting Security Vulnerabilities

**Do NOT open public issues for security vulnerabilities.**

We take security seriously and appreciate responsible disclosure. If you discover a security vulnerability in VelvetCMS Core, please report it privately:

### 📧 Contact

**Email:** security@anvyr.dev

**Please include:**
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Any suggested fixes (if you have them)

### 🕐 Response Time

- **Initial Response:** Within 36 hours
- **Status Update:** Within 7 days
- **Fix Timeline:** Depends on severity (critical issues prioritized)

### 🛡️ What to Expect

1. **Acknowledgment** - We'll confirm receipt of your report
2. **Investigation** - We'll verify and assess the vulnerability
3. **Fix Development** - We'll work on a patch
4. **Disclosure** - We'll coordinate public disclosure with you
5. **Credit** - We'll acknowledge your contribution (unless you prefer anonymity)

## Security Best Practices

When using VelvetCMS Core:

### For Development
- ✅ Keep `APP_DEBUG=false` in production
- ✅ Enable HTTPS in production
- ✅ Keep dependencies updated (`composer update`)
- ✅ Review file permissions (storage/ should be writable but not executable)

### For Deployment
- ✅ Use environment variables for sensitive config
- ✅ Disable directory listing in web server
- ✅ Be careful with any raw queries
- ✅ Enable CSRF protection on forms

### Known Security Features

VelvetCMS Core includes:

- **XSS Protection** - Auto-escaping template engine
- **CSRF Protection** - Token-based validation
- **SQL Injection Prevention** - Prepared statements only
- **Session Security** - Secure, httponly, samesite flags
- **Path Traversal Protection** - Sanitization in file operations

## Vulnerability Disclosure Policy

### Severity Levels

**Critical** (CVSS 9.0-10.0)
- Remote code execution
- Authentication bypass
- SQL injection in core

**High** (CVSS 7.0-8.9)
- XSS in core
- Privilege escalation
- Data exposure

**Medium** (CVSS 4.0-6.9)
- CSRF in sensitive operations
- Information disclosure
- Denial of service

**Low** (CVSS 0.1-3.9)
- Minor information leaks
- Low-impact issues

### Disclosure Timeline

- **Critical/High:** 7-14 days after fix release
- **Medium:** 30 days after fix release
- **Low:** 90 days after fix release

We aim to release patches quickly while giving users time to update.

## Security Updates

Security updates are released as:
- **Patch versions** (e.g., 1.0.1) for non-breaking fixes
- **Urgent patches** published immediately for critical issues
- **Security advisories** posted on GitHub Security tab

## Bug Bounty Program

We currently do not offer a formal bug bounty program, but we:
- Acknowledge security researchers publicly (with permission)
- Provide free commercial licenses for significant findings
- May offer rewards for critical vulnerabilities on a case-by-case basis

## Hall of Fame

We thank the following security researchers for their responsible disclosure:

<!-- This section will be updated as vulnerabilities are reported and fixed -->

*No vulnerabilities reported yet*

---

For general questions about security, email: security@anvyr.dev