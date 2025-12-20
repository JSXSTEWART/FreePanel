# Contributing to FreePanel

Thank you for your interest in contributing to FreePanel! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Development Setup](#development-setup)
- [Code Standards](#code-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Commit Messages](#commit-messages)

## Development Setup

### Prerequisites

- PHP 8.2+
- Node.js 20+
- Composer
- MySQL/MariaDB or SQLite (for development)
- Redis (optional, for caching/queues)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/JSXSTEWART/FreePanel.git
   cd FreePanel
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node dependencies (root + frontend):**
   ```bash
   npm install
   cd frontend && npm install && cd ..
   ```

4. **Set up environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```

6. **Start development servers:**
   ```bash
   # Terminal 1 - Backend
   php artisan serve

   # Terminal 2 - Frontend
   cd frontend && npm run dev
   ```

### Pre-commit Hooks

This project uses Husky for pre-commit hooks. After running `npm install`, hooks are automatically set up to:

- Run Laravel Pint on staged PHP files
- Run Prettier and ESLint on staged frontend files

## Code Standards

### PHP (Backend)

We use [Laravel Pint](https://laravel.com/docs/pint) with the Laravel preset for PHP code formatting.

**Key conventions:**
- PSR-12 coding style
- 4-space indentation
- Single quotes for strings
- Trailing commas in multiline arrays
- No unused imports

**Commands:**
```bash
# Check formatting
composer lint

# Fix formatting
composer lint:fix

# Run static analysis
composer analyse
```

### TypeScript/React (Frontend)

We use ESLint for linting and Prettier for formatting.

**Key conventions:**
- 2-space indentation
- Single quotes (except JSX)
- No semicolons
- ES5 trailing commas
- Strict TypeScript

**Commands:**
```bash
cd frontend

# Lint code
npm run lint

# Format code
npm run format

# Check formatting
npm run format:check
```

### Editor Configuration

An `.editorconfig` file is provided for consistent editor settings. Most editors support EditorConfig natively or via plugins.

## Testing

### PHP Tests

We use PHPUnit for backend testing. Tests are located in the `tests/` directory.

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/ApiHealthTest.php
```

### Frontend Tests

We use Vitest with React Testing Library for frontend testing.

```bash
cd frontend

# Run tests in watch mode
npm run test

# Run tests once
npm run test:run

# Run with coverage
npm run test:coverage
```

### Test Guidelines

1. **Write tests for new features** - All new functionality should have corresponding tests
2. **Update tests when modifying code** - If you change behavior, update the tests
3. **Use descriptive test names** - Tests should clearly describe what they verify
4. **Follow AAA pattern** - Arrange, Act, Assert
5. **Mock external dependencies** - Don't hit real APIs or databases in unit tests

## Pull Request Process

1. **Create a feature branch:**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following the code standards above

3. **Write/update tests** for your changes

4. **Run all checks locally:**
   ```bash
   # PHP
   composer lint
   composer analyse
   php artisan test

   # Frontend
   cd frontend
   npm run lint
   npm run format:check
   npm run test:run
   ```

5. **Commit your changes** with a descriptive message (see below)

6. **Push and create a PR:**
   ```bash
   git push -u origin feature/your-feature-name
   ```

7. **Fill out the PR template** with:
   - Summary of changes
   - Related issues (if any)
   - Testing performed
   - Screenshots (for UI changes)

### PR Requirements

- All CI checks must pass
- Code review approval required
- Tests must cover new functionality
- Documentation updated if needed

## Commit Messages

We follow conventional commit format:

```
type(scope): short description

[optional body]

[optional footer]
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Build process, dependencies, etc.

### Examples

```
feat(auth): add two-factor authentication support

fix(domains): resolve SSL certificate renewal issue

docs(readme): update installation instructions

test(api): add unit tests for user controller
```

## Architecture Overview

### Backend (Laravel)

```
app/
├── Console/Commands/     # Artisan commands
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/      # API controllers (versioned)
│   └── Middleware/       # Request middleware
├── Models/               # Eloquent models
├── Policies/             # Authorization policies
├── Providers/            # Service providers
└── Services/             # Business logic
    ├── Email/           # Email server integrations
    ├── WebServer/       # Apache/Nginx integrations
    ├── Dns/             # DNS server integrations
    └── ...
```

### Frontend (React)

```
frontend/src/
├── api/                  # API client functions
├── components/
│   ├── common/          # Reusable UI components
│   └── layout/          # Layout components
├── hooks/               # Custom React hooks
├── pages/               # Page components
├── routes/              # Route definitions
├── store/               # Redux store & slices
└── types/               # TypeScript types
```

## Questions?

If you have questions about contributing, please:

1. Check existing [issues](https://github.com/JSXSTEWART/FreePanel/issues)
2. Open a new issue with the `question` label
3. Review the [documentation](https://docs.freepanel.io)

Thank you for contributing to FreePanel!
