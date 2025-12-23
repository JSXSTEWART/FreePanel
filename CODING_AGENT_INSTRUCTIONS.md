# The Perfect Coding Agent Instructions for FreePanel

A comprehensive guide for AI coding agents working on the FreePanel project. This document synthesizes all essential knowledge, patterns, and best practices to enable efficient, high-quality contributions.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Project Architecture](#project-architecture)
3. [Code Patterns & Conventions](#code-patterns--conventions)
4. [Development Workflow](#development-workflow)
5. [Testing Strategy](#testing-strategy)
6. [Security Guidelines](#security-guidelines)
7. [Common Tasks & Examples](#common-tasks--examples)
8. [Troubleshooting](#troubleshooting)
9. [Quality Checklist](#quality-checklist)

---

## Quick Start

### Project Overview

FreePanel is a **modern, open-source web hosting control panel** built with:
- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: React 18 + TypeScript + Tailwind CSS
- **Database**: MySQL/MariaDB (SQLite for testing)
- **Cache/Queue**: Redis
- **Authentication**: Laravel Sanctum (JWT) + OAuth 2.0

### Core Functionality

The application **orchestrates system services** on Linux servers:
- **Web Servers**: Apache/Nginx virtual hosts with SSL
- **Mail**: Dovecot (IMAP/POP3) + Exim (SMTP)
- **DNS**: BIND zone management
- **FTP**: Pure-FTPd file transfer
- **Database**: MySQL/MariaDB management

### Instant Setup

```bash
# Clone and install
git clone https://github.com/JSXSTEWART/FreePanel.git
cd FreePanel

# Backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Frontend
npm install
cd frontend && npm install && cd ..

# Start development servers
php artisan serve                    # Terminal 1: Backend (port 8000)
cd frontend && npm run dev           # Terminal 2: Frontend (port 5173)
```

### Essential Commands Reference

```bash
# Linting & Formatting
composer lint                        # Check PHP code style
./vendor/bin/pint                   # Auto-fix PHP code style
cd frontend && npm run lint         # Check TypeScript/React
cd frontend && npm run format       # Auto-fix frontend code

# Testing
composer test                        # Run PHPUnit tests
php artisan test --filter=MyTest    # Run specific test
cd frontend && npm run test:run     # Run Vitest tests

# Building
cd frontend && npm run build        # Production build

# FreePanel-Specific
php artisan freepanel:create-admin  # Create admin user
php artisan freepanel:check-quotas  # Check user quotas
php artisan freepanel:renew-ssl --dry-run  # Test SSL renewal
```

---

## Project Architecture

### Directory Structure

```
FreePanel/
├── app/                           # Backend application
│   ├── Console/Commands/          # Artisan CLI commands
│   ├── Http/
│   │   ├── Controllers/Api/V1/    # API controllers (versioned)
│   │   │   ├── Admin/            # Admin-only endpoints
│   │   │   └── User/             # User endpoints
│   │   └── Middleware/           # Auth, audit, quota, throttle
│   ├── Models/                    # Eloquent ORM models
│   ├── Policies/                  # Authorization logic
│   └── Services/                  # Business logic layer
│       ├── Email/                # Dovecot/Exim integration
│       ├── WebServer/            # Apache/Nginx integration
│       ├── Dns/                  # BIND integration
│       ├── Database/             # MySQL/MariaDB management
│       ├── Ftp/                  # Pure-FTPd integration
│       └── Apps/                 # App installer (WordPress, etc.)
├── config/                        # Laravel config files
│   └── freepanel.php             # FreePanel-specific config
├── database/
│   ├── migrations/               # Database schema
│   ├── factories/                # Test data factories
│   └── seeders/                  # Database seeders
├── frontend/                      # React frontend
│   └── src/
│       ├── api/                  # API client functions
│       ├── components/           # Reusable UI components
│       ├── hooks/                # Custom React hooks
│       ├── pages/                # Page components
│       ├── routes/               # Route definitions
│       ├── store/                # Redux state management
│       └── types/                # TypeScript type definitions
├── routes/
│   └── api.php                   # API route definitions
├── system/                        # System integration scripts
│   └── scripts/
│       ├── install.sh            # Fresh server installer
│       ├── update.sh             # Update script
│       └── status.sh             # Status checker
├── tests/
│   ├── Feature/                  # Integration tests
│   └── Unit/                     # Unit tests
└── public/                        # Web root
```

### Architectural Patterns

#### 1. Service Layer Pattern

**Key Principle**: Business logic lives in **Services**, not Controllers.

```php
// ❌ BAD: Logic in controller
class DomainController {
    public function store(Request $request) {
        // Writing Apache configs directly in controller
        file_put_contents('/etc/apache2/sites-available/'.$domain, $config);
    }
}

// ✅ GOOD: Logic in service
class DomainController {
    public function __construct(private WebServerInterface $webServer) {}
    
    public function store(Request $request) {
        $this->webServer->createVirtualHost($validated);
    }
}
```

**Service Interface Pattern**:
- All services implement interfaces (e.g., `WebServerInterface`, `EmailInterface`)
- Concrete implementations: `ApacheService`, `NginxService`, `DovecotService`, etc.
- Inject interfaces in constructors for testability and swappability

#### 2. API Versioning

All API endpoints are versioned: `/api/v1/*`

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        // User endpoints
        Route::apiResource('domains', DomainController::class);
        
        // Admin endpoints
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::apiResource('packages', PackageController::class);
        });
    });
});
```

#### 3. Middleware Stack

**Common middleware combinations**:
- `auth:sanctum` - JWT token authentication
- `role:admin` or `role:admin,reseller` - Role-based access
- `audit` - Logs all administrative actions
- `quota:domains` - Enforces resource quotas
- `throttle:auth` - Rate limiting

```php
// Example middleware usage
Route::middleware(['auth:sanctum', 'role:admin', 'audit'])
    ->delete('/api/v1/admin/accounts/{id}', [AccountController::class, 'destroy']);
```

#### 4. Authorization with Policies

Use Laravel Policies for resource authorization:

```php
// In controller
public function update(Request $request, Domain $domain) {
    $this->authorize('update', $domain);  // Checks DomainPolicy
    // ...
}

// In policy (app/Policies/DomainPolicy.php)
public function update(User $user, Domain $domain) {
    return $user->id === $domain->user_id || $user->hasRole('admin');
}
```

---

## Code Patterns & Conventions

### PHP Backend

#### Style Guide

- **PSR-12** coding standard enforced by Laravel Pint
- **4-space indentation**
- **Single quotes** for strings (except when interpolation needed)
- **No unused imports** (Pint removes automatically)
- **Type hints everywhere** (parameters, return types, properties)

```php
// ✅ Good example
class DomainService implements DomainServiceInterface
{
    public function __construct(
        private WebServerInterface $webServer,
        private DnsInterface $dnsService,
    ) {}

    public function createDomain(array $data): Domain
    {
        $domain = Domain::create([
            'name' => $data['name'],
            'user_id' => $data['user_id'],
        ]);

        $this->webServer->createVirtualHost($domain);
        $this->dnsService->createZone($domain);

        return $domain;
    }
}
```

#### Eloquent Model Conventions

```php
class Domain extends Model
{
    // ✅ Always define fillable or guarded
    protected $fillable = ['name', 'user_id', 'document_root', 'ssl_enabled'];

    // ✅ Cast attributes to correct types
    protected $casts = [
        'ssl_enabled' => 'boolean',
        'created_at' => 'datetime',
    ];

    // ✅ Define relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sslCertificate(): HasOne
    {
        return $this->hasOne(SslCertificate::class);
    }
}
```

#### Controller Best Practices

```php
class DomainController extends Controller
{
    public function __construct(
        private DomainServiceInterface $domainService
    ) {}

    public function store(Request $request): JsonResponse
    {
        // ✅ Validate early
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:domains',
            'document_root' => 'sometimes|string',
        ]);

        // ✅ Authorize before action
        $this->authorize('create', Domain::class);

        // ✅ Use service layer
        $domain = $this->domainService->createDomain($validated);

        // ✅ Return consistent JSON responses
        return response()->json([
            'message' => 'Domain created successfully',
            'data' => $domain,
        ], 201);
    }
}
```

#### Error Handling

```php
// ✅ Use Laravel's exception handling
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WebServerService
{
    public function createVirtualHost(array $data): void
    {
        if (!$this->validateDomainName($data['name'])) {
            throw ValidationException::withMessages([
                'name' => ['Invalid domain name format'],
            ]);
        }

        if (!$this->writeConfigFile($data)) {
            throw new HttpException(500, 'Failed to create virtual host configuration');
        }
    }
}
```

### TypeScript/React Frontend

#### Style Guide

- **2-space indentation**
- **Single quotes** (except JSX attributes)
- **No semicolons**
- **Strict TypeScript** (`"strict": true`)
- **ES5 trailing commas**

```typescript
// ✅ Good example
interface Domain {
  id: number
  name: string
  sslEnabled: boolean
  createdAt: string
}

export const DomainList: React.FC = () => {
  const [domains, setDomains] = useState<Domain[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchDomains()
  }, [])

  const fetchDomains = async (): Promise<void> => {
    try {
      setLoading(true)
      const response = await api.get<{ data: Domain[] }>('/api/v1/domains')
      setDomains(response.data.data)
    } catch (error) {
      console.error('Failed to fetch domains:', error)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="domain-list">
      {loading ? <Spinner /> : <DomainTable domains={domains} />}
    </div>
  )
}
```

#### Component Patterns

```typescript
// ✅ Functional components with hooks
// ✅ Props interface defined
// ✅ Event handlers use arrow functions

interface ButtonProps {
  label: string
  onClick: () => void
  variant?: 'primary' | 'secondary'
  disabled?: boolean
}

export const Button: React.FC<ButtonProps> = ({
  label,
  onClick,
  variant = 'primary',
  disabled = false,
}) => {
  return (
    <button
      className={`btn btn-${variant}`}
      onClick={onClick}
      disabled={disabled}
    >
      {label}
    </button>
  )
}
```

#### API Client Pattern

```typescript
// src/api/domains.ts
import { api } from './client'

export interface CreateDomainRequest {
  name: string
  documentRoot?: string
}

export const domainApi = {
  list: async (): Promise<Domain[]> => {
    const response = await api.get<{ data: Domain[] }>('/api/v1/domains')
    return response.data.data
  },

  create: async (data: CreateDomainRequest): Promise<Domain> => {
    const response = await api.post<{ data: Domain }>('/api/v1/domains', data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await api.delete(`/api/v1/domains/${id}`)
  },
}
```

### Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| PHP Classes | PascalCase | `DomainController`, `WebServerInterface` |
| PHP Methods | camelCase | `createVirtualHost()`, `renewSslCertificate()` |
| PHP Variables | camelCase | `$userData`, `$sslCertificate` |
| Database Tables | snake_case (plural) | `domains`, `ssl_certificates`, `email_accounts` |
| Database Columns | snake_case | `user_id`, `created_at`, `ssl_enabled` |
| TypeScript Interfaces | PascalCase | `Domain`, `CreateDomainRequest` |
| TypeScript Variables | camelCase | `domainList`, `isLoading` |
| React Components | PascalCase | `DomainList`, `SslCertificateForm` |
| CSS Classes | kebab-case | `domain-list`, `ssl-badge` |

---

## Development Workflow

### 1. Branch Strategy

```bash
# Create feature branch
git checkout -b feature/domain-wildcard-support
git checkout -b fix/ssl-renewal-bug
git checkout -b docs/update-api-examples
```

**Branch prefixes**:
- `feature/*` - New features
- `fix/*` - Bug fixes
- `docs/*` - Documentation
- `refactor/*` - Code refactoring
- `test/*` - Test additions
- `chore/*` - Maintenance (dependencies, configs)

### 2. Making Changes

**Step-by-step workflow**:

```bash
# 1. Make changes to files
vim app/Services/WebServer/ApacheService.php

# 2. Run linters (auto-fixes code style)
./vendor/bin/pint                    # PHP
cd frontend && npm run format        # Frontend

# 3. Run tests
composer test                        # Backend
cd frontend && npm run test:run     # Frontend

# 4. Check for issues
composer lint                        # Verify PHP style
cd frontend && npm run lint         # Check TypeScript

# 5. Commit (pre-commit hooks run automatically)
git add .
git commit -m "feat(domains): add wildcard domain support"

# 6. Push
git push -u origin feature/domain-wildcard-support
```

### 3. Commit Message Format

**Follow Conventional Commits**:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types**:
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation only
- `style` - Code style (formatting, no logic change)
- `refactor` - Code change that neither fixes a bug nor adds a feature
- `test` - Adding or updating tests
- `chore` - Build process, dependencies, configs

**Examples**:
```
feat(auth): add OAuth2 authentication support
fix(ssl): resolve certificate renewal timeout
docs(readme): update installation instructions for Ubuntu
refactor(domains): extract validation logic to service
test(api): add integration tests for domain creation
chore(deps): upgrade Laravel to 11.31
```

### 4. Pre-commit Hooks

**Automatic checks** (via Husky + lint-staged):
- PHP files: Laravel Pint formatting
- TypeScript/JavaScript: ESLint + Prettier
- JSON/Markdown: Prettier

**Bypass (emergency only)**:
```bash
git commit --no-verify -m "urgent: fix production bug"
```

### 5. Pull Request Checklist

Before opening a PR:

- [ ] Code follows style guide (linters pass)
- [ ] Tests added/updated for changes
- [ ] All tests pass locally
- [ ] Documentation updated (if needed)
- [ ] No console.log() or dd() debug statements
- [ ] No commented-out code blocks
- [ ] Commit messages follow conventions
- [ ] PR description explains what/why

---

## Testing Strategy

### PHP Testing (PHPUnit)

#### Test Structure

```php
// tests/Feature/DomainManagementTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DomainManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function user_can_create_domain(): void
    {
        // Arrange
        $domainData = [
            'name' => 'example.com',
            'document_root' => '/home/user/public_html',
        ];

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/domains', $domainData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'document_root'],
            ]);

        $this->assertDatabaseHas('domains', [
            'name' => 'example.com',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_cannot_create_duplicate_domain(): void
    {
        Domain::factory()->create(['name' => 'example.com']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/domains', ['name' => 'example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
```

#### Testing Patterns

**1. Use Factories**:
```php
// database/factories/DomainFactory.php
Domain::factory()->create(['name' => 'test.com']);
Domain::factory()->count(5)->create();
```

**2. Mock External Services**:
```php
// Mock system service calls
$mockWebServer = Mockery::mock(WebServerInterface::class);
$mockWebServer->shouldReceive('createVirtualHost')->once();
$this->app->instance(WebServerInterface::class, $mockWebServer);
```

**3. Test Authorization**:
```php
/** @test */
public function user_cannot_delete_other_users_domain(): void
{
    $otherUser = User::factory()->create();
    $domain = Domain::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/domains/{$domain->id}");

    $response->assertStatus(403);
}
```

#### Running Tests

```bash
# All tests
composer test

# Specific suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Specific test file
php artisan test tests/Feature/DomainManagementTest.php

# Single test method
php artisan test --filter=test_user_can_create_domain

# With coverage
php artisan test --coverage
```

### Frontend Testing (Vitest)

```typescript
// src/components/__tests__/DomainList.test.tsx
import { describe, it, expect, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { DomainList } from '../DomainList'
import * as domainApi from '../../api/domains'

describe('DomainList', () => {
  it('displays loading state initially', () => {
    vi.spyOn(domainApi, 'list').mockResolvedValue([])
    
    render(<DomainList />)
    
    expect(screen.getByText(/loading/i)).toBeInTheDocument()
  })

  it('displays domains after loading', async () => {
    const mockDomains = [
      { id: 1, name: 'example.com', sslEnabled: true },
      { id: 2, name: 'test.com', sslEnabled: false },
    ]
    
    vi.spyOn(domainApi, 'list').mockResolvedValue(mockDomains)
    
    render(<DomainList />)
    
    await waitFor(() => {
      expect(screen.getByText('example.com')).toBeInTheDocument()
      expect(screen.getByText('test.com')).toBeInTheDocument()
    })
  })
})
```

---

## Security Guidelines

### Input Validation & Sanitization

#### Backend

```php
// ✅ Always validate input
$validated = $request->validate([
    'name' => 'required|string|max:255|regex:/^[a-z0-9.-]+$/',
    'email' => 'required|email|unique:users',
    'port' => 'required|integer|min:1|max:65535',
]);

// ✅ Use Eloquent query builder (prevents SQL injection)
Domain::where('user_id', $userId)->get();

// ❌ NEVER use raw SQL with user input
DB::select("SELECT * FROM domains WHERE name = '{$request->name}'");

// ✅ If raw SQL needed, use bindings
DB::select('SELECT * FROM domains WHERE name = ?', [$request->name]);
```

#### Frontend

```typescript
// ✅ Sanitize user input before display
import DOMPurify from 'dompurify'

const sanitizedHtml = DOMPurify.sanitize(userInput)

// ✅ Use textContent instead of innerHTML when possible
element.textContent = userInput  // Safe
element.innerHTML = userInput    // Dangerous!
```

### Authentication & Authorization

```php
// ✅ Require authentication
Route::middleware(['auth:sanctum'])->group(function () {
    // Protected routes
});

// ✅ Enforce role-based access
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Admin-only routes
});

// ✅ Use policies for resource authorization
$this->authorize('update', $domain);
```

### Sensitive Data

```php
// ✅ Never log sensitive data
Log::info('User login', ['user_id' => $user->id]);  // Good
Log::info('User login', ['password' => $password]); // BAD!

// ✅ Hash passwords
$user->password = bcrypt($request->password);

// ✅ Use .env for secrets (never commit .env)
$apiKey = env('THIRD_PARTY_API_KEY');
```

### System Command Execution

```php
// ✅ Validate and sanitize before executing system commands
class WebServerService
{
    private function reloadApache(): void
    {
        // ✅ Use absolute paths
        // ✅ No user input in command
        $result = Process::run(['/usr/bin/systemctl', 'reload', 'apache2']);
        
        if ($result->failed()) {
            throw new \RuntimeException('Failed to reload Apache');
        }
    }

    // ❌ NEVER do this
    private function badExample(string $domain): void
    {
        exec("apachectl restart {$domain}");  // Command injection vulnerability!
    }
}
```

### Rate Limiting

```php
// ✅ Apply throttling to sensitive endpoints
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/auth/login', [LoginController::class, 'login']);
});

// ✅ Custom rate limits for API endpoints
Route::middleware(['throttle:60,1'])->group(function () {
    // 60 requests per minute
});
```

---

## Common Tasks & Examples

### Task 1: Add New API Endpoint

**Scenario**: Add endpoint to list email accounts for a domain.

```php
// 1. Create route (routes/api.php)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('domains/{domain}/emails', [EmailController::class, 'index']);
});

// 2. Update controller (app/Http/Controllers/Api/V1/User/EmailController.php)
public function index(Domain $domain): JsonResponse
{
    $this->authorize('view', $domain);  // Check policy
    
    $emails = $domain->emailAccounts()
        ->select('id', 'email', 'quota_mb', 'created_at')
        ->paginate(20);
    
    return response()->json($emails);
}

// 3. Add policy method (app/Policies/DomainPolicy.php)
public function view(User $user, Domain $domain): bool
{
    return $user->id === $domain->user_id || $user->hasRole(['admin', 'reseller']);
}

// 4. Write test (tests/Feature/EmailManagementTest.php)
/** @test */
public function user_can_list_their_domain_emails(): void
{
    $domain = Domain::factory()->create(['user_id' => $this->user->id]);
    EmailAccount::factory()->count(3)->create(['domain_id' => $domain->id]);
    
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/domains/{$domain->id}/emails");
    
    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
}
```

### Task 2: Add Service Integration

**Scenario**: Add support for a new web server (Caddy).

```php
// 1. Create interface (if not exists)
// app/Services/WebServer/WebServerInterface.php
interface WebServerInterface
{
    public function createVirtualHost(array $data): void;
    public function deleteVirtualHost(string $domain): void;
    public function reloadServer(): void;
}

// 2. Create implementation
// app/Services/WebServer/CaddyService.php
class CaddyService implements WebServerInterface
{
    public function createVirtualHost(array $data): void
    {
        $caddyfile = $this->generateCaddyfile($data);
        file_put_contents("/etc/caddy/sites/{$data['domain']}", $caddyfile);
        $this->reloadServer();
    }
    
    public function deleteVirtualHost(string $domain): void
    {
        unlink("/etc/caddy/sites/{$domain}");
        $this->reloadServer();
    }
    
    public function reloadServer(): void
    {
        Process::run(['systemctl', 'reload', 'caddy']);
    }
    
    private function generateCaddyfile(array $data): string
    {
        return "{$data['domain']} {\n    root * {$data['root']}\n    php_fastcgi unix//run/php/php-fpm.sock\n}\n";
    }
}

// 3. Register in service provider
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(WebServerInterface::class, function ($app) {
        $server = config('freepanel.web_server', 'apache');
        
        return match($server) {
            'apache' => new ApacheService(),
            'nginx' => new NginxService(),
            'caddy' => new CaddyService(),
            default => throw new \RuntimeException("Unsupported web server: {$server}"),
        };
    });
}

// 4. Add config option
// config/freepanel.php
return [
    'web_server' => env('WEB_SERVER', 'apache'),  // apache, nginx, or caddy
];

// 5. Write tests
// tests/Unit/Services/CaddyServiceTest.php
```

### Task 3: Add Frontend Component

**Scenario**: Create domain creation form.

```typescript
// 1. Define types (src/types/domain.ts)
export interface CreateDomainFormData {
  name: string
  documentRoot?: string
  sslEnabled: boolean
}

// 2. Create component (src/components/forms/CreateDomainForm.tsx)
import { useState } from 'react'
import { domainApi } from '../../api/domains'

export const CreateDomainForm: React.FC = () => {
  const [formData, setFormData] = useState<CreateDomainFormData>({
    name: '',
    sslEnabled: true,
  })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent): Promise<void> => {
    e.preventDefault()
    setLoading(true)
    setError(null)

    try {
      await domainApi.create(formData)
      // Success handling (e.g., redirect, show message)
    } catch (err) {
      setError('Failed to create domain')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="create-domain-form">
      <div className="form-group">
        <label htmlFor="name">Domain Name</label>
        <input
          id="name"
          type="text"
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
          required
        />
      </div>

      <div className="form-group">
        <label>
          <input
            type="checkbox"
            checked={formData.sslEnabled}
            onChange={(e) => setFormData({ ...formData, sslEnabled: e.target.checked })}
          />
          Enable SSL
        </label>
      </div>

      {error && <div className="error">{error}</div>}

      <button type="submit" disabled={loading}>
        {loading ? 'Creating...' : 'Create Domain'}
      </button>
    </form>
  )
}

// 3. Write tests (src/components/forms/__tests__/CreateDomainForm.test.tsx)
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { CreateDomainForm } from '../CreateDomainForm'
import * as domainApi from '../../../api/domains'

describe('CreateDomainForm', () => {
  it('submits form data', async () => {
    const createSpy = vi.spyOn(domainApi, 'create').mockResolvedValue({})
    
    render(<CreateDomainForm />)
    
    fireEvent.change(screen.getByLabelText(/domain name/i), {
      target: { value: 'example.com' },
    })
    fireEvent.click(screen.getByRole('button', { name: /create domain/i }))
    
    await waitFor(() => {
      expect(createSpy).toHaveBeenCalledWith({
        name: 'example.com',
        sslEnabled: true,
      })
    })
  })
})
```

### Task 4: Add Artisan Command

**Scenario**: Create command to check SSL certificate expiration.

```php
// 1. Generate command
php artisan make:command CheckSslExpiration

// 2. Implement command (app/Console/Commands/CheckSslExpiration.php)
namespace App\Console\Commands;

use App\Models\SslCertificate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSslExpiration extends Command
{
    protected $signature = 'freepanel:check-ssl
                          {--days=30 : Check certificates expiring within N days}
                          {--notify : Send notifications for expiring certificates}';

    protected $description = 'Check SSL certificates for upcoming expiration';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $notify = $this->option('notify');

        $expiringCerts = SslCertificate::where('expires_at', '<=', Carbon::now()->addDays($days))
            ->where('expires_at', '>', Carbon::now())
            ->get();

        if ($expiringCerts->isEmpty()) {
            $this->info("No certificates expiring within {$days} days.");
            return self::SUCCESS;
        }

        $this->warn("Found {$expiringCerts->count()} certificate(s) expiring within {$days} days:");

        foreach ($expiringCerts as $cert) {
            $daysUntilExpiry = Carbon::now()->diffInDays($cert->expires_at);
            $this->line("  - {$cert->domain}: expires in {$daysUntilExpiry} days");
            
            if ($notify) {
                // Send notification logic here
            }
        }

        return self::SUCCESS;
    }
}

// 3. Register in Kernel for scheduling (app/Console/Kernel.php)
protected function schedule(Schedule $schedule): void
{
    $schedule->command('freepanel:check-ssl --notify')
        ->daily()
        ->at('08:00');
}

// 4. Test manually
php artisan freepanel:check-ssl --days=30
```

### Task 5: Add Database Migration

**Scenario**: Add two-factor authentication support.

```php
// 1. Create migration
php artisan make:migration add_two_factor_to_users_table

// 2. Implement migration (database/migrations/xxxx_add_two_factor_to_users_table.php)
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};

// 3. Run migration
php artisan migrate

// 4. Update model (app/Models/User.php)
protected $fillable = [
    'name',
    'email',
    'password',
    'two_factor_enabled',
];

protected $hidden = [
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
];

protected $casts = [
    'two_factor_enabled' => 'boolean',
    'two_factor_confirmed_at' => 'datetime',
];
```

---

## Troubleshooting

### Common Issues

#### 1. Permission Errors

**Symptom**: `Permission denied` errors when writing files.

**Solution**:
```bash
# For development (Linux)
sudo chown -R $USER:$USER /home/runner/work/FreePanel/FreePanel
chmod -R 755 /home/runner/work/FreePanel/FreePanel
chmod -R 775 storage bootstrap/cache

# For production (AlmaLinux/Rocky Linux)
sudo chown -R freepanel:freepanel /opt/freepanel
chmod -R 755 /opt/freepanel
chmod -R 775 /opt/freepanel/storage /opt/freepanel/bootstrap/cache
```

#### 2. Database Connection Failed

**Symptom**: `SQLSTATE[HY000] [2002] Connection refused`

**Solution**:
```bash
# Check .env configuration
cat .env | grep DB_

# For SQLite (development)
touch database/database.sqlite
php artisan migrate

# For MySQL (production)
systemctl status mariadb
mysql -u freepanel -p freepanel -e "SELECT 1"
```

#### 3. Frontend Build Failures

**Symptom**: TypeScript errors or build failures.

**Solution**:
```bash
# Clear node_modules and reinstall
cd frontend
rm -rf node_modules package-lock.json
npm install

# Check TypeScript configuration
npx tsc --noEmit

# Fix linting issues
npm run lint -- --fix
```

#### 4. Tests Failing

**Symptom**: Tests pass locally but fail in CI.

**Solution**:
```bash
# Ensure clean state
composer install --prefer-dist
php artisan config:clear
php artisan cache:clear

# Recreate test database
rm -f database/database.sqlite
touch database/database.sqlite
php artisan migrate --env=testing

# Run tests
composer test
```

#### 5. Pre-commit Hook Failures

**Symptom**: Commits rejected by pre-commit hooks.

**Solution**:
```bash
# Auto-fix PHP issues
./vendor/bin/pint

# Auto-fix frontend issues
cd frontend
npm run format
npm run lint -- --fix

# Reinstall hooks if broken
rm -rf .husky
npm run prepare
```

### Debugging Tips

```bash
# Backend debugging
php artisan tinker                   # Interactive shell
php artisan route:list              # List all routes
php artisan config:show             # Show all config
tail -f storage/logs/laravel.log    # Watch logs

# Frontend debugging
npm run dev                         # Hot reload development
console.log()                       # Browser console
React DevTools                      # Browser extension

# System integration debugging
sudo journalctl -u freepanel -f    # Watch service logs
sudo systemctl status freepanel    # Check service status
```

---

## Quality Checklist

Before submitting code, verify:

### Code Quality
- [ ] Code follows style guide (Pint/Prettier passes)
- [ ] No linter warnings or errors
- [ ] Type hints used throughout (PHP/TypeScript)
- [ ] No unused imports or variables
- [ ] Meaningful variable and function names
- [ ] No commented-out code blocks
- [ ] No debug statements (dd(), console.log(), var_dump())

### Testing
- [ ] New features have tests
- [ ] Modified features have updated tests
- [ ] All tests pass locally
- [ ] Test names clearly describe what they test
- [ ] Tests follow AAA pattern (Arrange, Act, Assert)

### Security
- [ ] Input validated before use
- [ ] User authorization checked (policies)
- [ ] No SQL injection vulnerabilities
- [ ] No command injection vulnerabilities
- [ ] Sensitive data not logged
- [ ] Secrets in .env (not hardcoded)

### Documentation
- [ ] Public methods have docblocks (if complex)
- [ ] README updated (if needed)
- [ ] API endpoints documented (if new)
- [ ] Configuration options documented

### Git
- [ ] Commit messages follow conventions
- [ ] Commits are atomic and focused
- [ ] No merge conflicts
- [ ] Branch is up to date with main

### Performance
- [ ] No N+1 query problems (use eager loading)
- [ ] Database queries optimized
- [ ] Large datasets paginated
- [ ] Heavy operations queued (if applicable)

---

## Additional Resources

### Laravel
- [Laravel 11 Documentation](https://laravel.com/docs/11.x)
- [Laravel Best Practices](https://github.com/alexeymezenin/laravel-best-practices)
- [Eloquent ORM](https://laravel.com/docs/11.x/eloquent)

### React/TypeScript
- [React Documentation](https://react.dev)
- [TypeScript Handbook](https://www.typescriptlang.org/docs/)
- [React Testing Library](https://testing-library.com/docs/react-testing-library/intro/)

### Testing
- [PHPUnit Documentation](https://docs.phpunit.de/)
- [Vitest Documentation](https://vitest.dev)
- [Laravel Testing](https://laravel.com/docs/11.x/testing)

### Security
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/11.x/security)

---

## Quick Command Reference

```bash
# Development
php artisan serve                    # Start backend (port 8000)
cd frontend && npm run dev           # Start frontend (port 5173)

# Code Quality
composer lint                        # Check PHP style
./vendor/bin/pint                   # Fix PHP style
cd frontend && npm run lint         # Check TypeScript
cd frontend && npm run format       # Fix TypeScript/React

# Testing
composer test                        # Run PHP tests
cd frontend && npm run test:run     # Run frontend tests
php artisan test --filter=MyTest    # Run specific test

# Database
php artisan migrate                  # Run migrations
php artisan migrate:fresh --seed    # Reset database
php artisan db:seed                 # Seed data

# Cache
php artisan cache:clear             # Clear app cache
php artisan config:clear            # Clear config cache
php artisan route:clear             # Clear route cache
php artisan view:clear              # Clear view cache

# FreePanel Specific
php artisan freepanel:create-admin  # Create admin user
php artisan freepanel:check-quotas  # Check user quotas
php artisan freepanel:renew-ssl     # Renew SSL certificates

# Production Build
cd frontend && npm run build        # Build frontend for production
composer install --optimize-autoloader --no-dev  # Install production dependencies
```

---

## Support

- **Issues**: [GitHub Issues](https://github.com/JSXSTEWART/FreePanel/issues)
- **Documentation**: [Project README](README.md)
- **Contributing**: [CONTRIBUTING.md](CONTRIBUTING.md)
- **Development Guide**: [DEVELOPMENT.md](DEVELOPMENT.md)

---

**Version**: 1.0.0  
**Last Updated**: 2025-12-23  
**Maintainer**: FreePanel Development Team
