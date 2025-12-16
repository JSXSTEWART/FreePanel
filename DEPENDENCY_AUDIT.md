# FreePanel Dependency Audit Report

**Date:** December 16, 2025
**Auditor:** Claude Code

## Executive Summary

This audit analyzed all dependencies in the FreePanel project for outdated packages, security vulnerabilities, and unnecessary bloat. The project uses a Laravel 11 backend with a React 18 frontend.

### Risk Summary
| Category | Count | Severity |
|----------|-------|----------|
| Security Vulnerabilities | 1 | Moderate |
| Major Version Updates Available | 4 | Low |
| Minor Updates Available | 10+ | Low |
| Bloat/Unnecessary Dependencies | 0 | None |

---

## 1. Security Vulnerabilities

### 🔴 CRITICAL FIX REQUIRED

#### esbuild CORS Vulnerability (GHSA-67mh-4wv8-2f99)
- **Package:** `esbuild` (transitive dependency via `vite`)
- **Severity:** Moderate (CVSS 5.3)
- **Current Version:** ≤0.24.2
- **Fixed Version:** 0.25.0+
- **Impact:** Any website can send requests to the development server and read responses, potentially exposing source code
- **Reference:** [GitHub Advisory](https://github.com/advisories/GHSA-67mh-4wv8-2f99)

**Remediation:**
```json
// Add to frontend/package.json
{
  "overrides": {
    "esbuild": "^0.25.0"
  }
}
```

Or upgrade Vite to version 6.2.0+ (preferably latest 6.x that includes fixed esbuild).

> ⚠️ **Note:** Do NOT run `npm audit fix --force` as it installs an unmaintained old Vite version.

---

## 2. Outdated Packages

### Frontend (npm) - High Priority Updates

| Package | Current | Latest | Breaking Changes | Recommendation |
|---------|---------|--------|------------------|----------------|
| `react` | ^18.2.0 | 19.2.3 | Yes | Stay on 18.x for now |
| `react-dom` | ^18.2.0 | 19.2.3 | Yes | Stay on 18.x for now |
| `react-router-dom` | ^6.21.3 | 7.10.1 | Yes | Stay on 6.x for now |
| `recharts` | ^2.12.0 | 3.6.0 | Yes | Stay on 2.x for now |
| `vite` | ^5.0.12 | 6.3.0+ | Yes | **Update to 6.2.0+ for security fix** |

### Frontend (npm) - Safe Minor Updates

These updates are backwards-compatible and recommended:

| Package | Current | Latest | Action |
|---------|---------|--------|--------|
| `@headlessui/react` | ^2.0.0 | 2.2.9 | ✅ Safe to update |
| `@heroicons/react` | ^2.1.1 | 2.2.0 | ✅ Safe to update |
| `@reduxjs/toolkit` | ^2.0.1 | 2.11.2 | ✅ Safe to update |
| `axios` | ^1.6.5 | 1.13.2 | ✅ Safe to update |
| `clsx` | ^2.1.0 | 2.1.1 | ✅ Safe to update |
| `react-hot-toast` | ^2.4.1 | 2.6.0 | ✅ Safe to update |
| `react-redux` | ^9.1.0 | 9.2.0 | ✅ Safe to update |
| `@typescript-eslint/*` | ^6.19.0 | 8.x | ⚠️ Major update, review needed |
| `eslint` | ^8.56.0 | 9.x | ⚠️ Major update, review needed |

### Backend (Composer) - Current Status

| Package | Current | Status | Notes |
|---------|---------|--------|-------|
| `laravel/framework` | ^11.0 | Supported | Bug fixes until Sep 2025, security until Mar 2026 |
| `laravel/sanctum` | ^4.0 | Current | Latest for Laravel 11 |
| `spatie/laravel-permission` | ^6.4 | Current | Well-maintained |
| `guzzlehttp/guzzle` | ^7.8 | Current | Latest stable |
| `predis/predis` | ^2.2 | Current | Latest stable |
| `pragmarx/google2fa-laravel` | ^2.2 | Current | Stable |
| `phpunit/phpunit` | ^11.0 | Current | Latest for PHP 8.2+ |

> **Note:** Laravel 12 is now available. Consider upgrading when ready, but Laravel 11 remains fully supported until March 2026.

---

## 3. Bloat Analysis

### Frontend Dependencies
All dependencies appear necessary and well-justified:

| Package | Purpose | Verdict |
|---------|---------|---------|
| `react`, `react-dom` | Core UI framework | ✅ Required |
| `react-router-dom` | Client-side routing | ✅ Required |
| `@reduxjs/toolkit`, `react-redux` | State management | ✅ Required for complex state |
| `axios` | HTTP client | ✅ Required for API calls |
| `@headlessui/react` | Accessible UI primitives | ✅ Good choice |
| `@heroicons/react` | Icon library | ✅ Lightweight, matches Tailwind |
| `recharts` | Data visualization | ✅ Required for dashboards |
| `react-hot-toast` | Toast notifications | ✅ Lightweight (3.5kb) |
| `clsx` | Class name utility | ✅ Tiny utility (228B) |

### Backend Dependencies
All dependencies appear necessary:

| Package | Purpose | Verdict |
|---------|---------|---------|
| `laravel/sanctum` | API authentication | ✅ Required |
| `spatie/laravel-permission` | RBAC/permissions | ✅ Industry standard |
| `guzzlehttp/guzzle` | HTTP client | ✅ Required for external APIs |
| `pragmarx/google2fa-laravel` | 2FA implementation | ✅ Required for security |
| `bacon/bacon-qr-code` | QR code generation | ✅ Required for 2FA setup |
| `league/flysystem-sftp-v3` | SFTP operations | ✅ Required for file management |
| `predis/predis` | Redis client | ✅ Required for caching/queues |

---

## 4. Recommendations

### Immediate Actions (Priority: High)

1. **Fix esbuild vulnerability** by adding overrides to `frontend/package.json`:
   ```json
   {
     "overrides": {
       "esbuild": "^0.25.0"
     }
   }
   ```
   Then run `npm install` to apply.

2. **Update Vite** to version 6.2.0+ to get the patched esbuild:
   ```bash
   cd frontend && npm install vite@^6.2.0
   ```

### Short-term Actions (Priority: Medium)

3. **Apply safe minor updates:**
   ```bash
   cd frontend && npm update
   ```

4. **Lock dependency versions** in production by committing `package-lock.json` (already done).

### Long-term Actions (Priority: Low)

5. **React 19 Migration** - Wait for ecosystem stability (Q2 2025)
   - Many breaking changes (removed propTypes, ref as prop, etc.)
   - Recommend upgrading to 18.3.x first to get deprecation warnings
   - Reference: [React 19 Upgrade Guide](https://react.dev/blog/2024/04/25/react-19-upgrade-guide)

6. **React Router v7 Migration** - Evaluate when ready
   - Major API changes
   - Reference: [React Router Docs](https://reactrouter.com/)

7. **Laravel 12 Migration** - Consider when convenient
   - Laravel 11 supported until March 2026
   - Reference: [Laravel Release Notes](https://laravel.com/docs/12.x/releases)

8. **ESLint 9 Migration** - Requires flat config migration
   - Breaking change from eslintrc to flat config
   - Can wait until typescript-eslint fully supports v9

---

## 5. Updated package.json (Recommended)

### frontend/package.json changes:

```diff
{
  "dependencies": {
-   "@headlessui/react": "^2.0.0",
+   "@headlessui/react": "^2.2.0",
-   "@heroicons/react": "^2.1.1",
+   "@heroicons/react": "^2.2.0",
-   "@reduxjs/toolkit": "^2.0.1",
+   "@reduxjs/toolkit": "^2.11.0",
-   "axios": "^1.6.5",
+   "axios": "^1.13.0",
    "clsx": "^2.1.0",
    "react": "^18.2.0",
    "react-dom": "^18.2.0",
-   "react-hot-toast": "^2.4.1",
+   "react-hot-toast": "^2.6.0",
-   "react-redux": "^9.1.0",
+   "react-redux": "^9.2.0",
-   "react-router-dom": "^6.21.3",
+   "react-router-dom": "^6.30.0",
-   "recharts": "^2.12.0"
+   "recharts": "^2.15.0"
  },
  "devDependencies": {
-   "vite": "^5.0.12"
+   "vite": "^6.2.0"
  },
+ "overrides": {
+   "esbuild": "^0.25.0"
+ }
}
```

---

## Sources

- [Laravel Release Notes](https://laravel.com/docs/11.x/releases)
- [Laravel End of Life](https://endoflife.date/laravel)
- [React 19 Upgrade Guide](https://react.dev/blog/2024/04/25/react-19-upgrade-guide)
- [esbuild GHSA-67mh-4wv8-2f99](https://github.com/advisories/GHSA-67mh-4wv8-2f99)
- [Vite GitHub Issue #19428](https://github.com/vitejs/vite/issues/19428)

---

*Report generated by Claude Code on December 16, 2025*
