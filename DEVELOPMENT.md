# Development Workflow Guide

This guide provides a complete reference for the FreePanel development workflow, including environment setup, code quality tools, testing, and CI/CD processes.

## Table of Contents

1. [Environment Setup](#environment-setup)
2. [Code Quality Tooling](#code-quality-tooling)
3. [Testing Infrastructure](#testing-infrastructure)
4. [Pre-commit Hooks](#pre-commit-hooks)
5. [CI/CD Pipeline](#cicd-pipeline)
6. [Development Workflow](#development-workflow)
7. [Release Process](#release-process)

---

## 1. Environment Setup

### Prerequisites

- **PHP**: 8.2 or higher
- **Composer**: Latest stable version
- **Node.js**: 20.x or higher
- **npm**: Latest stable version
- **Git**: For version control
- **SQLite**: For testing (or MySQL/MariaDB for production)

### Clone & Install

```bash
# Clone the repository
git clone https://github.com/JSXSTEWART/FreePanel.git
cd FreePanel

# Install backend dependencies
composer install

# Install root dependencies (husky, lint-staged)
npm install

# Install frontend dependencies
cd frontend && npm install && cd ..

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create database and run migrations
touch database/database.sqlite
php artisan migrate

# Start development servers
# Terminal 1 - Backend
php artisan serve

# Terminal 2 - Frontend
cd frontend && npm run dev
```

---

## 2. Code Quality Tooling

### Backend (PHP)

#### Laravel Pint (Code Style)

Pint is a zero-dependency PHP code style fixer built on top of PHP-CS-Fixer.

**Configuration**: `pint.json`

```bash
# Check code style
composer lint
# or
./vendor/bin/pint --test

# Auto-fix code style
./vendor/bin/pint
```

#### PHPStan (Static Analysis)

**Note**: PHPStan/Larastan is not currently in the lock file. To add it:

```bash
composer require --dev larastan/larastan
```

After adding, run:

```bash
composer analyse
# or
./vendor/bin/phpstan analyse --memory-limit=2G
```

**Configuration**: `phpstan.neon`

### Frontend (TypeScript/React)

#### ESLint (Code Quality)

**Configuration**: `frontend/eslint.config.js`

```bash
cd frontend

# Check for issues
npm run lint

# Auto-fix issues
npm run lint -- --fix
```

#### Prettier (Code Formatting)

**Configuration**: `frontend/.prettierrc`

```bash
cd frontend

# Check formatting
npm run format:check

# Auto-format files
npm run format
```

#### TypeScript (Type Checking)

**Configuration**: `frontend/tsconfig.json`

```bash
cd frontend

# Type check
npx tsc --noEmit
```

---

## 3. Testing Infrastructure

### Backend (PHPUnit)

**Configuration**: `phpunit.xml`

```bash
# Run all tests
composer test
# or
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite Feature
./vendor/bin/phpunit --testsuite Unit

# Run specific test file
./vendor/bin/phpunit tests/Feature/ApiHealthTest.php

# Run with coverage (requires Xdebug)
./vendor/bin/phpunit --coverage-html coverage
```

**Test Patterns**:
- Feature tests: `tests/Feature/*Test.php`
- Unit tests: `tests/Unit/*Test.php`
- Factories: `database/factories/`
- Seeders: `database/seeders/`

### Frontend (Vitest)

**Configuration**: `frontend/vitest.config.ts`

```bash
cd frontend

# Run tests in watch mode
npm test
# or
npm run test

# Run tests once
npm run test:run

# Run with UI
npm run test:ui

# Run with coverage
npm run test:coverage
```

**Test Patterns**:
- Test files: `src/**/__tests__/*.test.{ts,tsx}`
- Setup: `src/test/setup.ts`

---

## 4. Pre-commit Hooks

Pre-commit hooks automatically run code quality checks before each commit.

**Tools**:
- **Husky**: Git hooks manager
- **lint-staged**: Run linters on staged files

**Configuration**: `package.json` (lint-staged section)

### What Runs on Commit

1. **PHP Files**: Pint code style fixer
2. **TypeScript/JavaScript**: ESLint + Prettier
3. **JSON/CSS/Markdown**: Prettier

### Manual Hook Testing

```bash
# Test pre-commit hook
npm run prepare  # Install husky
git add .
git commit -m "test commit"
```

### Bypass Hooks (Not Recommended)

```bash
git commit --no-verify -m "urgent fix"
```

---

## 5. CI/CD Pipeline

**Configuration**: `.github/workflows/ci.yml`

### Jobs Overview

The CI pipeline consists of 5 parallel/sequential jobs:

1. **lint-php**: PHP code quality checks
   - Runs Pint code style checker
   - Fast feedback on PHP style issues

2. **test-php**: Backend tests
   - Depends on: `lint-php`
   - Runs PHPUnit test suite
   - Uses SQLite in-memory database

3. **lint-frontend**: Frontend code quality checks
   - Runs ESLint
   - Checks Prettier formatting
   - Runs TypeScript type checking

4. **test-frontend**: Frontend tests
   - Depends on: `lint-frontend`
   - Runs Vitest test suite

5. **build-frontend**: Production build
   - Depends on: `test-frontend`
   - Builds optimized production bundle
   - Validates build process

### Trigger Events

- **Push** to `main` branch
- **Pull Request** to `main` branch

### Local CI Validation

Run the same checks locally before pushing:

```bash
# Full validation (from project root)
composer lint && \
composer test && \
cd frontend && \
npm run lint && \
npm run format:check && \
npm run test:run && \
npm run build:prod
```

---

## 6. Development Workflow

### Feature Branch → PR → Merge Process

#### 1. Create Feature Branch

```bash
git checkout -b feature/my-new-feature
```

#### 2. Make Changes

Write your code following the project conventions:
- Follow existing code style
- Write tests for new features
- Update documentation as needed

#### 3. Local Validation

```bash
# Backend checks
composer lint
composer test

# Frontend checks
cd frontend
npm run lint
npm run format:check
npm run test:run
npm run build:prod
cd ..
```

#### 4. Commit Changes

```bash
git add .
git commit -m "feat: add new feature"
# Pre-commit hooks will run automatically
```

#### 5. Push to Remote

```bash
git push origin feature/my-new-feature
```

#### 6. Create Pull Request

- Go to GitHub
- Create PR from your feature branch to `main`
- Fill in PR template
- Wait for CI checks to pass
- Request review

#### 7. Address Review Comments

```bash
# Make changes
git add .
git commit -m "fix: address review comments"
git push origin feature/my-new-feature
```

#### 8. Merge

Once approved and CI passes:
- Squash and merge (recommended for feature branches)
- Or merge commit (for multi-commit features)

### Branch Naming Conventions

- `feature/*` - New features
- `fix/*` - Bug fixes
- `docs/*` - Documentation updates
- `refactor/*` - Code refactoring
- `test/*` - Test additions/fixes
- `chore/*` - Maintenance tasks

### Commit Message Format

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Test changes
- `chore`: Maintenance

**Examples**:
```
feat(auth): add OAuth authentication
fix(database): resolve connection timeout
docs(readme): update installation instructions
test(api): add health check tests
```

---

## 7. Release Process

### Pre-release Checks

```bash
# 1. Ensure all tests pass
composer test
cd frontend && npm run test:run && cd ..

# 2. Ensure code quality
composer lint
cd frontend && npm run lint && npm run format:check && cd ..

# 3. Build frontend
cd frontend && npm run build:prod && cd ..

# 4. Update version numbers
# - composer.json
# - package.json
# - frontend/package.json

# 5. Update CHANGELOG.md
```

### Tagging

```bash
# Create annotated tag
git tag -a v1.0.0 -m "Release version 1.0.0"

# Push tag to remote
git push origin v1.0.0
```

### Deployment

Deployment steps depend on your hosting environment. See `system/scripts/install.sh` for server setup.

**Important**: Always test deployment scripts in a disposable VM first!

---

## Quick Reference Commands

### Backend
```bash
composer lint        # Check PHP formatting
composer test        # Run PHPUnit tests
```

### Frontend
```bash
npm run lint         # ESLint
npm run format       # Prettier auto-fix
npm run format:check # Check formatting
npm run test:run     # Vitest
npm run build:prod   # Production build
```

### Full Local Validation
```bash
composer lint && \
composer test && \
cd frontend && \
npm run lint && \
npm run format:check && \
npm run test:run && \
npm run build:prod
```

---

## Troubleshooting

### Common Issues

#### "composer.lock out of date"
```bash
composer update --lock
```

#### "Node modules missing"
```bash
cd frontend && npm install && cd ..
npm install
```

#### "Pre-commit hook fails"
```bash
# Fix issues reported by hooks
./vendor/bin/pint  # Auto-fix PHP
cd frontend && npm run format  # Auto-fix frontend
```

#### "Tests fail locally but pass in CI"
```bash
# Ensure clean state
composer install --prefer-dist
cd frontend && npm ci && cd ..
rm -f database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh
```

---

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [React Documentation](https://react.dev)
- [Vitest Documentation](https://vitest.dev)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)

---

**Last Updated**: 2025-12-20

For questions or issues, please open a GitHub issue or contact the maintainers.
