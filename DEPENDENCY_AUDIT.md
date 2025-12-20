# FreePanel Dependency Audit Report

**Date:** December 20, 2025
**Auditor:** Automated Analysis

## Executive Summary

This report analyzes the FreePanel project dependencies for outdated packages, security vulnerabilities, and unnecessary bloat.

| Category | Status |
|----------|--------|
| Security Vulnerabilities (npm) | ✅ 0 vulnerabilities |
| Security Vulnerabilities (Composer) | ✅ No advisories found |
| Outdated Packages | ⚠️ Several major updates available |
| Missing Dependencies | ❌ Critical packages missing |
| JSON Syntax Errors | ✅ Fixed |
| Duplicate Dependencies | ✅ Fixed |

---

## 1. Issues Fixed During Audit

### 1.1 JSON Syntax Errors (FIXED)
The following trailing comma issues were found and corrected:
- `composer.json:59` - Trailing comma after lint script
- `frontend/package.json:12` - Trailing comma after scripts block
- `frontend/package.json:40` - Trailing comma in devDependencies
- `package.json:4` - Trailing comma in root package.json

### 1.2 Duplicate Dependencies (FIXED)
- `composer.json` had `larastan/larastan` listed twice in require-dev (removed duplicate)

---

## 2. Security Analysis

### 2.1 NPM Packages
```
npm audit: found 0 vulnerabilities
```
**Status:** ✅ No security issues detected

### 2.2 Composer Packages
```
composer audit: No security vulnerability advisories found
```
**Status:** ✅ No security issues detected

---

## 3. Outdated Packages

### 3.1 PHP/Composer (Major Updates Available)

| Package | Current | Latest | Type |
|---------|---------|--------|------|
| `laravel/framework` | 11.47.0 | **12.43.1** | Major |
| `larastan/larastan` | 2.11.2 | **3.8.1** | Major |
| `phpunit/phpunit` | 11.5.46 | **12.5.4** | Major |
| `predis/predis` | 2.4.1 | **3.3.0** | Major |

**Recommendations:**
- **Laravel 12**: Consider upgrading to Laravel 12 for improved performance and new features. Review the [Laravel 12 upgrade guide](https://laravel.com/docs/12.x/upgrade) before proceeding.
- **PHPUnit 12**: Major version upgrade, check compatibility with your test suite.
- **Larastan 3**: Breaking changes likely, review changelog.
- **Predis 3**: Review breaking changes before upgrading.

### 3.2 NPM Packages

#### Deprecated Packages (Upgrade Recommended)
| Package | Issue | Solution |
|---------|-------|----------|
| `eslint@8.56.0` | EOL - No longer supported | Upgrade to ESLint 9.x with flat config |
| `@humanwhocodes/config-array` | Deprecated | Migrates automatically with ESLint 9 |
| `@humanwhocodes/object-schema` | Deprecated | Migrates automatically with ESLint 9 |
| `rimraf@3.0.2` | Deprecated | Upgrade to rimraf v4+ |
| `glob@7.2.3` | Deprecated | Upgrade to glob v9+ |
| `inflight@1.0.6` | Memory leak, unsupported | Will be removed with glob upgrade |

#### Major Version Upgrades Available
| Package | Current | Latest | Notes |
|---------|---------|--------|-------|
| `react` | 18.2.0 | **19.2.3** | React 19 with new features |
| `react-dom` | 18.2.0 | **19.2.3** | Companion upgrade with React |
| `eslint` | 8.56.0 | **9.x** | New flat config system |
| `@typescript-eslint/*` | 6.19.0 | **8.x** | Breaking changes |

---

## 4. Missing Dependencies (CRITICAL)

The following packages are **used in the codebase but not listed in package.json**:

### 4.1 Required for Build
| Package | Used In | Purpose |
|---------|---------|---------|
| `typescript` | Build script, all `.ts/.tsx` files | TypeScript compiler |
| `tailwindcss` | `postcss.config.js`, `tailwind.config.js`, `src/index.css` | CSS framework |

### 4.2 Required for Testing
| Package | Used In | Purpose |
|---------|---------|---------|
| `vitest` | `src/hooks/__tests__/*.test.{ts,tsx}` | Test runner |
| `@testing-library/react` | Test files | React component testing |

**Action Required:** These dependencies must be added to `package.json`:

```bash
npm install --save-dev typescript tailwindcss vitest @testing-library/react
```

---

## 5. Recommendations

### 5.1 Immediate Actions (Priority: High)

1. **Add Missing Dependencies**
   ```bash
   cd frontend
   npm install --save-dev typescript@^5.0.0 tailwindcss@^3.4.0 vitest@^2.0.0 @testing-library/react@^14.0.0
   ```

2. **ESLint Migration to v9 (Flat Config)**
   - ESLint 8 is EOL and no longer receives security updates
   - Migrate to ESLint 9 with flat config using the [official migration guide](https://eslint.org/docs/latest/use/migrate-to-9.0.0)
   ```bash
   npx @eslint/migrate-config .eslintrc.js
   ```

### 5.2 Short-Term Actions (Priority: Medium)

3. **Upgrade React to v19**
   - React 19.2.3 includes significant performance improvements
   - Review [React 19 Upgrade Guide](https://react.dev/blog/2024/04/25/react-19-upgrade-guide)
   ```bash
   npm install react@^19.0.0 react-dom@^19.0.0 @types/react@^19.0.0 @types/react-dom@^19.0.0
   ```

4. **Update TypeScript-ESLint to v8**
   - Required for ESLint 9 compatibility
   ```bash
   npm install --save-dev @typescript-eslint/eslint-plugin@^8.0.0 @typescript-eslint/parser@^8.0.0
   ```

### 5.3 Long-Term Actions (Priority: Low)

5. **Laravel 12 Upgrade**
   - Significant framework upgrade, requires thorough testing
   - Review all deprecated methods and breaking changes

6. **PHPUnit 12 Upgrade**
   - May require test refactoring

---

## 6. Bloat Analysis

### 6.1 Dependency Count
- **Frontend:** 23 direct dependencies (357 total with transitive)
- **Backend:** 11 production + 8 dev dependencies

### 6.2 Potential Optimizations

| Observation | Recommendation |
|-------------|----------------|
| `jsdom` in devDependencies | Useful for testing, keep if using vitest |
| `recharts` (large bundle) | Consider lighter alternatives like `lightweight-charts` if only basic charts needed |
| Duplicate test files | `useAuth.test.tsx` and `useAuth.test.ts` exist - consolidate |

### 6.3 Build Optimization Suggestions
- Consider enabling Vite's `build.minify: 'terser'` for smaller bundles
- Enable tree-shaking for recharts: `import { LineChart } from 'recharts'` (not `import * as Recharts`)

---

## 7. Summary of Changes Made

During this audit, the following changes were made to fix issues:

1. ✅ Fixed trailing comma in `composer.json`
2. ✅ Fixed trailing commas in `frontend/package.json`
3. ✅ Fixed trailing comma in root `package.json`
4. ✅ Removed duplicate `larastan/larastan` entry in `composer.json`
5. ✅ Updated `composer.lock` to sync with `composer.json`

---

## 8. Next Steps

1. Review this report with the development team
2. Create issues/tickets for recommended upgrades
3. Prioritize adding missing dependencies (blocking for builds/tests)
4. Plan ESLint migration to v9 (security concern)
5. Evaluate React 19 upgrade timeline
6. Schedule Laravel 12 upgrade after thorough testing plan

---

*This report was generated automatically. Always test thoroughly after making dependency changes.*
