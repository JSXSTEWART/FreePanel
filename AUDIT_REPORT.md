# FreePanel Dependency and Quality Audit Report

**Date**: December 16, 2025
**Branch**: `claude/audit-dependencies-mj81e8v5yof3rlw6-lGrLH`

## Executive Summary

This audit analyzed the FreePanel codebase for outdated packages, security vulnerabilities, missing configurations, and code quality issues. Overall, the codebase is well-structured with good security practices in place, but several improvements are recommended.

---

## 1. Security Vulnerabilities

### 1.1 npm Security Audit

**Status**: 2 moderate severity vulnerabilities found

```
Package: esbuild (<=0.24.2)
Severity: MODERATE
Issue: esbuild enables any website to send any requests to the
       development server and read the response
Advisory: https://github.com/advisories/GHSA-67mh-4wv8-2f99
Affected: vite 0.11.0 - 6.1.6
Fix: npm audit fix --force (upgrades to vite@7.3.0 - breaking change)
```

**Recommendation**: Update Vite to version 7.x when ready for a breaking change update, or wait for a non-breaking patch.

### 1.2 Composer Security Audit

**Status**: No vulnerabilities detected (composer.json is valid)

The PHP dependencies use secure, well-maintained packages with appropriate version constraints.

---

## 2. Outdated Dependencies

### 2.1 Frontend (npm) - Outdated Packages

| Package | Current | Wanted | Latest | Notes |
|---------|---------|--------|--------|-------|
| `react` | ^18.2.0 | 18.3.1 | 19.2.3 | Major version available |
| `react-dom` | ^18.2.0 | 18.3.1 | 19.2.3 | Major version available |
| `react-router-dom` | ^6.21.3 | 6.30.2 | 7.10.1 | Major version available |
| `recharts` | ^2.12.0 | 2.15.4 | 3.6.0 | Major version available |
| `@headlessui/react` | ^2.0.0 | 2.2.9 | 2.2.9 | Minor update |
| `@heroicons/react` | ^2.1.1 | 2.2.0 | 2.2.0 | Minor update |
| `@reduxjs/toolkit` | ^2.0.1 | 2.11.2 | 2.11.2 | Minor update |
| `axios` | ^1.6.5 | 1.13.2 | 1.13.2 | Minor update |
| `react-hot-toast` | ^2.4.1 | 2.6.0 | 2.6.0 | Minor update |
| `react-redux` | ^9.1.0 | 9.2.0 | 9.2.0 | Minor update |
| `clsx` | ^2.1.0 | 2.1.1 | 2.1.1 | Patch update |

**Priority Recommendations**:

1. **Safe Updates** (non-breaking): Update minor/patch versions
   ```bash
   cd frontend
   npm update
   ```

2. **Evaluate Major Updates**: React 19, React Router 7, and Recharts 3 have breaking changes. Evaluate migration guides before upgrading.

### 2.2 Backend (Composer) - Dependencies

The composer.json uses flexible version constraints (`^x.y`) which is appropriate:

| Package | Constraint | Status |
|---------|-----------|--------|
| `laravel/framework` | ^11.0 | Current |
| `laravel/sanctum` | ^4.0 | Current |
| `spatie/laravel-permission` | ^6.4 | Current |
| `pragmarx/google2fa-laravel` | ^2.2 | Current |
| `guzzlehttp/guzzle` | ^7.8 | Current |
| `predis/predis` | ^2.2 | Current |

**Note**: Actual installed versions cannot be verified because `composer.lock` is missing from the repository.

---

## 3. Missing Files

### 3.1 Missing Lock Files

| File | Status | Impact |
|------|--------|--------|
| `composer.lock` | **MISSING** | Dependency versions may vary between installs |
| `frontend/package-lock.json` | Present | Good |

**Critical Recommendation**: Generate and commit `composer.lock`:
```bash
composer install
git add composer.lock
git commit -m "Add composer.lock for reproducible builds"
```

### 3.2 Missing Test Suite

**Issue**: No `tests/` directory exists despite README documentation mentioning `php artisan test`

**Recommendation**: Create test directory structure:
```
tests/
├── Feature/
│   ├── Auth/
│   ├── Api/
│   └── Admin/
├── Unit/
│   ├── Services/
│   └── Models/
├── TestCase.php
└── CreatesApplication.php
```

### 3.3 Missing Configuration Files

| File | Status | Recommendation |
|------|--------|----------------|
| `config/cors.php` | Missing | Add CORS configuration for API |
| `frontend/eslint.config.js` | Missing | ESLint 9.x requires new config format |

---

## 4. Configuration Issues

### 4.1 ESLint Configuration

**Issue**: ESLint 9.x is installed but requires `eslint.config.js` (flat config format). The project may have `.eslintrc.*` which is deprecated.

**Solution**: Create `frontend/eslint.config.js`:
```javascript
import js from '@eslint/js';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';

export default [
  js.configs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',
      'react-refresh/only-export-components': 'warn',
    },
  },
];
```

### 4.2 API Rate Limiting

**Current State**: Only `/api/v1/auth/*` endpoints have rate limiting (`throttle:auth`)

**Recommendation**: Add rate limiting to all API endpoints:

```php
// In routes/api.php, update the protected routes group:
Route::prefix('v1')->middleware(['auth:sanctum', 'audit', 'throttle:api'])->group(function () {
    // ... existing routes
});
```

### 4.3 Sanctum Token Expiration

**Current State**: `config/sanctum.php` has `'expiration' => null` (tokens never expire)

**Recommendation**: Set appropriate token expiration:
```php
'expiration' => 60 * 24, // 24 hours in minutes
```

---

## 5. Security Assessment

### 5.1 Positive Findings

| Area | Status | Notes |
|------|--------|-------|
| Input Validation | Good | Controllers use Laravel validation |
| Shell Command Escaping | Good | Proper use of `escapeshellarg()` |
| SQL Injection Prevention | Good | Parameterized queries + `validateName()` |
| Password Hashing | Good | Uses bcrypt via Laravel's `Hash::make()` |
| Authentication | Good | Laravel Sanctum with proper token management |
| Authorization | Good | Role-based middleware (`CheckRole`) |
| Audit Logging | Good | `AuditLog` middleware captures all actions |
| HTTPS Enforcement | Good | Forced in production via `AppServiceProvider` |

### 5.2 Areas for Improvement

| Area | Priority | Recommendation |
|------|----------|----------------|
| Rate Limiting | Medium | Add to all API endpoints |
| Token Expiration | Medium | Set Sanctum token expiration |
| CORS Configuration | Medium | Add explicit CORS config |
| CSP Headers | Low | Consider Content Security Policy |
| Password Policy | Low | Currently min 8 chars, consider complexity rules |

---

## 6. Code Quality Assessment

### 6.1 Architecture

**Rating**: Excellent

- Clean separation of concerns (Controllers, Services, Models)
- Interface-based service architecture allowing swappable implementations
- Proper use of Laravel's dependency injection
- Event-driven architecture for side effects
- Well-organized middleware chain

### 6.2 Frontend Architecture

**Rating**: Good

- TypeScript with strict mode enabled
- Redux Toolkit for state management
- Proper separation of API, components, and pages
- Path aliasing configured (`@/*`)

### 6.3 Documentation

**Rating**: Good

- Comprehensive README with installation instructions
- Clear environment requirements documented
- API endpoint documentation (basic)

---

## 7. Recommended Action Items

### High Priority

1. **Generate and commit `composer.lock`**
   ```bash
   composer install
   git add composer.lock
   git commit -m "Add composer.lock for reproducible builds"
   ```

2. **Fix ESLint configuration** - Create `eslint.config.js` for ESLint 9.x

3. **Add API rate limiting** - Apply `throttle:api` to all endpoints

### Medium Priority

4. **Update npm packages** (non-breaking updates)
   ```bash
   cd frontend && npm update
   ```

5. **Set Sanctum token expiration** in `config/sanctum.php`

6. **Add CORS configuration** file

### Low Priority

7. **Create test suite** - Add PHPUnit and Jest tests

8. **Evaluate major version upgrades** for React 19, React Router 7

9. **Add security headers** (CSP, HSTS in production)

---

## 8. Dependency Analysis Summary

### Backend Dependencies (8 production, 7 dev)

All production dependencies are well-maintained and appropriate for the project:

- `laravel/framework` - Core framework
- `laravel/sanctum` - API authentication
- `spatie/laravel-permission` - RBAC
- `pragmarx/google2fa-laravel` - 2FA
- `guzzlehttp/guzzle` - HTTP client
- `bacon/bacon-qr-code` - QR generation
- `league/flysystem-sftp-v3` - SFTP
- `predis/predis` - Redis client

**No unnecessary bloat detected** in backend dependencies.

### Frontend Dependencies (11 production, 12 dev)

All dependencies serve clear purposes:

- Core: react, react-dom, react-router-dom
- State: @reduxjs/toolkit, react-redux
- UI: @headlessui/react, @heroicons/react, tailwindcss
- Utils: axios, clsx, react-hot-toast
- Charts: recharts

**No unnecessary bloat detected** in frontend dependencies.

---

## Conclusion

The FreePanel codebase demonstrates good software engineering practices with a clean architecture and reasonable security measures. The main areas requiring attention are:

1. **Missing `composer.lock`** - Critical for reproducible builds
2. **ESLint 9.x migration** - Needs new config format
3. **API rate limiting** - Should be applied globally
4. **Minor npm updates** - Safe to apply

The security posture is solid with proper input validation, shell command escaping, and authentication/authorization mechanisms in place.
