# Echo Framework Improvement Roadmap v3

This document tracks the third phase of improvement work for the Echo PHP framework, based on comprehensive code audit findings.

**Created:** 2026-02-04
**Updated:** 2026-02-05
**Test Coverage Phase Completed:** 2026-02-05
**Previous Roadmaps:** v1 (21/21 complete), v2 (22/22 complete)

## Recent Completions

### Test Coverage Phase 3 (2026-02-05)
- Added HTTP Kernel unit tests (19 tests, 46 assertions)
- Added Auth Middleware tests (unit + integration)
- Added BearerAuth Middleware tests (unit + integration)
- Added RequestLimit Middleware tests (unit + integration)
- Added HtmxResponse tests (32 tests, 72 assertions)
- Added AuditLogger tests (15 tests, 33 assertions)
- Total: 325 tests, 613 assertions passing

### Redis Integration (2026-02-05)
- Added Redis service to Docker stack
- Implemented `RedisManager` for connection pooling
- Created `CacheInterface` with Redis and File implementations
- Added `RateLimiter` interface with Redis and Session implementations
- Updated `Session` class to support Redis session handler
- Added `cache()` and `redis()` helper functions
- All features gracefully fall back if Redis unavailable

---

## Current Focus: Stability

> **Note:** New feature development (Phases 4 & 5) is on hold until the framework is stable.
> Current priorities are: **Security fixes**, **Bug fixes**, and **Performance optimizations**.

---

## Progress Overview

| Phase | Description | Status | Progress |
|-------|-------------|--------|----------|
| 1 | Critical Bug Fixes | **Complete** | 6/6 ✓ |
| 2 | Performance Optimizations | **Complete** | 8/8 ✓ |
| 3 | Test Coverage | **Complete** | 6/6 ✓ |
| 4 | AdminController Review | **Active** | 0/1 |
| 5 | AdminController Enhancements | On Hold | 0/6 |
| 6 | Framework Features | On Hold | 0/7 |

---

## Phase 1: Critical Bug Fixes

**Priority:** CRITICAL
**Goal:** Fix security vulnerabilities and critical bugs

### Task 1.1: Fix SQL Injection in Validation
- [x] **Complete**

**File:** `src/Framework/Http/Controller.php:108`

**Problem:** The `unique` validation rule interpolates table and field names directly into SQL:
```php
'unique' => count(db()->fetch("SELECT 1 FROM $rule_val WHERE $field = ?", [$request_value])) === 0,
```

**Fix:** Whitelist allowed table names or escape identifiers:
```php
'unique' => function() use ($rule_val, $field, $request_value) {
    $allowedTables = array_keys(config('validation.unique_tables') ?? []);
    if (!in_array($rule_val, $allowedTables)) {
        throw new \InvalidArgumentException("Invalid table for unique validation: $rule_val");
    }
    $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
    return count(db()->fetch("SELECT 1 FROM `$rule_val` WHERE `$safeField` = ?", [$request_value])) === 0;
},
```

---

### Task 1.2: Fix Unsafe Unserialize in Widget Cache
- [x] **Complete**

**File:** `src/Framework/Admin/Widget.php:106`

**Problem:** `unserialize()` without `allowed_classes` option enables PHP Object Injection:
```php
$data = unserialize($content);
```

**Fix:** Restrict allowed classes or use JSON:
```php
// Option 1: Restrict classes
$data = unserialize($content, ['allowed_classes' => false]);

// Option 2: Use JSON instead (recommended)
$data = json_decode($content, true);
// Update cacheStore() to use json_encode()
```

---

### Task 1.3: Fix Null Safety in Database Connection
- [x] **Complete**

**Files:**
- `src/Framework/Database/Connection.php:115` - `fetchAll()`
- `src/Framework/Database/Connection.php:120` - `lastInsertId()`

**Problem:** Methods call `$this->link` without null checks:
```php
public function fetchAll(string $sql, array $params = []): array
{
    return $this->execute($sql, $params)->fetchAll(); // Can be null
}
```

**Fix:** Add null safety:
```php
public function fetchAll(string $sql, array $params = []): array
{
    $stmt = $this->execute($sql, $params);
    if ($stmt === null) {
        throw new \RuntimeException('Database connection not established');
    }
    return $stmt->fetchAll();
}

public function lastInsertId(): string
{
    if ($this->link === null) {
        throw new \RuntimeException('Database connection not established');
    }
    return $this->link->lastInsertId();
}
```

---

### Task 1.4: Fix JSON Response Field Swap in RequestLimit
- [x] **Complete**

**File:** `src/Framework/Http/Middleware/RequestLimit.php:70`

**Problem:** `success` and `status` fields are swapped:
```php
return new JsonResponse([
    "success" => 429,  // Should be false
    "status" => false, // Should be 429
], 429)
```

**Fix:**
```php
return new JsonResponse([
    "success" => false,
    "status" => 429,
    "error" => [
        "code" => "RATE_LIMIT_EXCEEDED",
        "message" => "Too many requests"
    ]
], 429)
```

---

### Task 1.5: Fix XSS in Auth Middleware Redirect
- [x] **Complete**

**File:** `src/Framework/Http/Middleware/Auth.php:24`

**Problem:** JavaScript redirect with potentially unescaped URL:
```php
$res = new HttpResponse("<script>window.location.href = '$route';</script>", 401);
```

**Fix:** Use proper redirect response:
```php
$res = new HttpResponse('', 302);
$res->setHeader('Location', $route);
return $res;
```

---

### Task 1.6: Add CSV Injection Protection
- [x] **Complete**

**File:** `src/Framework/Http/AdminController.php:586-612`

**Problem:** CSV export doesn't escape formula characters (`=`, `+`, `-`, `@`).

**Fix:** Sanitize cell values:
```php
private function sanitizeCsvValue(mixed $value): string
{
    $value = (string) $value;
    if (preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value; // Prefix with single quote
    }
    return $value;
}
```

---

## Phase 2: Performance Optimizations

**Priority:** HIGH
**Goal:** Address critical performance bottlenecks

### Task 2.1: Add Missing Database Indexes
- [x] **Complete**

**Problem:** Several high-frequency queries lack indexes.

**Create migration:** `migrations/XXXX_add_performance_indexes.php`
```sql
-- api_tokens: Used in bearer auth validation
ALTER TABLE api_tokens ADD INDEX idx_token_revoked (token, revoked);

-- audits: Used in activity feeds and queries
ALTER TABLE audits ADD INDEX idx_created_at (created_at);

-- sessions: High-frequency inserts and queries
ALTER TABLE sessions ADD INDEX idx_user_id (user_id);
ALTER TABLE sessions ADD INDEX idx_created_at (created_at);

-- users: UUID lookups on every request
ALTER TABLE users ADD INDEX idx_uuid (uuid);
```

---

### Task 2.2: Optimize Session Class
- [x] **Complete**

**File:** `src/Framework/Session/Session.php`

**Problem:** `session_start()` and `session_write_close()` called on every get/set operation.

**Fix:** Start session once and write at request end:
```php
class Session
{
    private bool $started = false;
    private array $data = [];

    private function ensureStarted(): void
    {
        if (!$this->started) {
            @session_start();
            $this->data = $_SESSION ?? [];
            $this->started = true;
        }
    }

    public function get(string $key): mixed
    {
        $this->ensureStarted();
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $this->data[$key] = $value;
        $_SESSION[$key] = $value;
    }

    public function __destruct()
    {
        if ($this->started) {
            session_write_close();
        }
    }
}
```

---

### Task 2.3: Cache Debug Flag in Connection
- [x] **Complete**

**File:** `src/Framework/Database/Connection.php`

**Problem:** `config('app.debug')` and `class_exists()` called on every query.

**Fix:** Cache in constructor:
```php
class Connection
{
    private bool $debugEnabled;
    private bool $profilerAvailable;

    public function __construct()
    {
        // ... existing code ...
        $this->debugEnabled = config('app.debug') ?? false;
        $this->profilerAvailable = class_exists('Echo\Framework\Debug\Profiler');
    }

    public function execute(string $sql, array $params = []): mixed
    {
        // ... existing code ...
        if ($this->debugEnabled && $this->profilerAvailable) {
            \Echo\Framework\Debug\Profiler::getInstance()->queries()?->log($sql, $params, $startTime);
        }
        return $stmt;
    }
}
```

---

### Task 2.4: Pre-compile Route Patterns
- [x] **Complete**

**File:** `src/Framework/Routing/RouteCache.php`

**Problem:** Regex patterns compiled on every route dispatch.

**Fix:** Store compiled patterns in cache:
```php
public static function compile(array $routes): array
{
    $compiled = [];
    foreach ($routes as $route => $methods) {
        $pattern = preg_replace('/\{(\w+)\}/', '([A-Za-z0-9_.-]+)', $route);
        $compiled[$route] = [
            'pattern' => "#^$pattern$#",
            'methods' => $methods,
            'param_names' => self::extractParamNames($route),
        ];
    }
    return $compiled;
}
```

---

### Task 2.5: Lazy Load Widgets
- [x] **Complete**

**File:** `src/Framework/Admin/WidgetRegistry.php:39-45`

**Problem:** All widgets instantiated even if not rendered.

**Fix:** Lazy instantiation:
```php
private static array $instances = [];

public static function get(string $id): ?Widget
{
    if (!isset(self::$widgets[$id])) {
        return null;
    }
    if (!isset(self::$instances[$id])) {
        self::$instances[$id] = new self::$widgets[$id]();
    }
    return self::$instances[$id];
}
```

---

### Task 2.6: Add Session Table Cleanup
- [x] **Complete**

**Problem:** Sessions table grows unbounded.

**Create command:** `src/Framework/Console/Commands/SessionCleanupCommand.php`
```php
class SessionCleanupCommand extends Command
{
    public function execute(): void
    {
        $days = $this->getOption('days') ?? 30;
        $deleted = db()->execute(
            "DELETE FROM sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        )->rowCount();
        $this->writeLine("Deleted $deleted old session records");
    }
}
```

**Add to scheduler:** Run daily

---

### Task 2.7: Optimize Config Loading
- [x] **Complete**

**File:** `app/Helpers/Functions.php:174-207`

**Problem:** Config key parsing on every access.

**Fix:** Cache full key paths:
```php
function config(string $name): mixed
{
    static $cache = [];

    // Return cached value if exists
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    // ... existing loading logic ...

    // Cache the full key path
    $cache[$name] = $value;
    return $value;
}
```

---

### Task 2.8: Add Eager Loading to Model
- [x] **Complete** (basic implementation)

**File:** `src/Framework/Database/Model.php`

**Problem:** Relationships execute N+1 queries.

**Implementation:**
```php
protected static array $eagerLoad = [];

public static function with(string ...$relations): static
{
    static::$eagerLoad = $relations;
    return new static();
}

public static function get(): ?array
{
    $results = parent::get();
    if (!empty(static::$eagerLoad) && $results) {
        foreach (static::$eagerLoad as $relation) {
            static::loadRelation($results, $relation);
        }
    }
    static::$eagerLoad = [];
    return $results;
}
```

---

## Phase 3: Test Coverage

**Priority:** HIGH
**Status:** COMPLETE
**Goal:** Increase test coverage for critical components

### Task 3.1: HTTP Kernel Tests
- [x] **Complete**

**Created:** `tests/Http/KernelTest.php`

**Test cases (19 tests, 46 assertions):**
- 404 detection when route is null
- API route detection and response structure
- Error sanitization in debug vs production mode
- Controller method extraction from route
- Request ID inclusion in API responses
- Middleware layer ordering
- User UUID retrieval from session

---

### Task 3.2: Auth Middleware Tests
- [x] **Complete**

**Created:** `tests/Http/Middleware/AuthTest.php` (unit) + `AuthIntegrationTest.php`

**Test cases (17 tests):**
- Auth requirement logic patterns
- Passes when user exists
- Passes when auth not in middleware
- Redirect to sign-in when unauthenticated
- Next handler receives request correctly

---

### Task 3.3: BearerAuth Middleware Tests
- [x] **Complete**

**Created:** `tests/Http/Middleware/BearerAuthTest.php` (unit) + `BearerAuthIntegrationTest.php`

**Test cases (30 tests, 36 assertions):**
- Middleware activation for api/bearer routes
- Bearer header format parsing
- Token hashing with SHA256
- Token expiration checking
- Token revocation validation
- 401 response format
- Session auth bypass

---

### Task 3.4: RequestLimit Middleware Tests
- [x] **Complete**

**Created:** `tests/Http/Middleware/RequestLimitTest.php` (unit) + `RequestLimitIntegrationTest.php`

**Test cases (23 tests, 43 assertions):**
- Rate limit disabled when max_requests is 0
- API routes use fixed limits
- Rate limit key generation by IP
- 429 response format (JSON for API, text for web)
- HTMX request detection
- Multiple requests within limit pass

---

### Task 3.5: HtmxResponse Tests
- [x] **Complete**

**Created:** `tests/Http/HtmxResponseTest.php`

**Test cases (32 tests, 72 assertions):**
- `redirect()` sets HX-Redirect header
- `location()` with simple URL and options
- `trigger()` single/multiple events
- `triggerAfterSettle()` and `triggerAfterSwap()`
- `retarget()`, `reswap()`, `reselect()` headers
- `refresh()`, `pushUrl()`, `replaceUrl()` headers
- Fluent interface chaining
- Static `make()` factory

---

### Task 3.6: AuditLogger Tests
- [x] **Complete**

**Created:** `tests/Audit/AuditLoggerTest.php`

**Test cases (15 tests, 33 assertions):**
- Context user ID, IP, user agent set correctly
- Sensitive fields filtered (password, token, api_key, etc.)
- Case-insensitive and partial match filtering
- Custom sensitive field addition
- Empty array handling

---

## Phase 4: AdminController Review

**Priority:** HIGH
**Status:** Queued (after Phase 3)
**Goal:** Code review and refactoring of AdminController

### Task 4.1: AdminController Architecture Review
- [ ] **Pending**

**File:** `src/Framework/Http/AdminController.php` (~1200 lines)

**Current Issues:**
The AdminController uses array properties for configuration which can become unwieldy:

```php
$this->table_columns = [...];
$this->table_joins = [...];
$this->table_format = [...];
$this->form_columns = [...];
$this->form_controls = [...];
$this->form_dropdowns = [...];
$this->filter_dropdowns = [...];
$this->validation_rules = [...];
```

**Review Areas:**

1. **Array Configuration Pattern**
   - Evaluate current approach of setting arrays in constructor
   - Consider alternatives: fluent builder, config classes, attributes
   - Assess developer experience and discoverability

2. **Code Organization**
   - AdminController is ~1200 lines - consider splitting
   - Separate concerns: table rendering, form handling, filtering, exports
   - Extract into traits or service classes where appropriate

3. **Performance**
   - Review query building efficiency
   - Check for N+1 queries in table rendering
   - Evaluate caching opportunities for dropdown options

4. **Type Safety**
   - Add proper type hints throughout
   - Consider typed configuration DTOs instead of arrays
   - Improve IDE autocompletion support

5. **Extensibility**
   - Review override points for customization
   - Ensure child controllers can easily extend behavior
   - Document extension patterns

**Alternative Approaches to Evaluate:**

```php
// Option A: Fluent Builder
class ProductsController extends AdminController
{
    protected function configure(): void
    {
        $this->table()
            ->columns(['ID' => 'id', 'Name' => 'name'])
            ->searchable(['Name'])
            ->sortable(['ID', 'Name', 'Created']);
            
        $this->form()
            ->field('name')->input()->required()
            ->field('price')->number()->required()
            ->field('category_id')->dropdown($categories);
    }
}

// Option B: Attribute-based (like routes)
#[AdminModule(table: 'products')]
#[TableColumn('ID', 'id')]
#[TableColumn('Name', 'name', searchable: true)]
#[FormField('name', type: 'input', required: true)]
class ProductsController extends AdminController { }

// Option C: Configuration DTOs
class ProductsController extends AdminController
{
    protected function tableConfig(): TableConfig
    {
        return new TableConfig(
            columns: [
                new Column('ID', 'id'),
                new Column('Name', 'name', searchable: true),
            ],
            joins: [...],
        );
    }
}
```

**Deliverables:**
- Code review document with findings
- Recommendations for refactoring approach
- Migration path if breaking changes needed
- Updated documentation

---

## Phase 5: AdminController Enhancements (ON HOLD)

**Priority:** DEFERRED
**Status:** On hold pending Phase 4 review
**Goal:** Improve admin panel functionality

### Task 5.1: Advanced Bulk Operations
- [ ] **Pending**

**Enhancement:** Extend bulk actions beyond delete.

**Implementation:**
```php
protected array $bulk_actions = [
    ['value' => 'delete', 'label' => 'Delete Selected'],
    ['value' => 'activate', 'label' => 'Activate Selected'],
    ['value' => 'deactivate', 'label' => 'Deactivate Selected'],
];

protected function handleBulkAction(string $action, array $ids): void
{
    match ($action) {
        'delete' => $this->bulkDelete($ids),
        'activate' => $this->bulkUpdate($ids, ['status' => 'active']),
        'deactivate' => $this->bulkUpdate($ids, ['status' => 'inactive']),
        default => throw new \InvalidArgumentException("Unknown action: $action"),
    };
}

protected function bulkUpdate(array $ids, array $data): int
{
    // Implementation with audit logging
}
```

---

### Task 4.2: CSV/JSON Import
- [ ] **Pending**

**Create:** `src/Framework/Admin/ImportService.php`

**Features:**
- Upload CSV/JSON file
- Preview data before import
- Column mapping UI
- Validation with error report
- Duplicate detection (skip/update/merge options)
- Import audit trail

**Controller method:**
```php
#[Post("/import", "import")]
public function import(Request $request): string
{
    $file = $request->files()->get('import_file');
    $importer = new ImportService($this->model);
    $result = $importer->preview($file);
    // Return preview template or process import
}
```

---

### Task 4.3: Inline Editing
- [ ] **Pending**

**Files:**
- `src/Framework/Http/AdminController.php`
- `templates/admin/table.html.twig`

**Implementation:**
```php
protected array $inline_editable = ['status', 'title', 'name'];

#[Patch("/inline/{id}", "inline_update")]
public function inlineUpdate(int $id, Request $request): string
{
    $field = $request->post()->get('field');
    $value = $request->post()->get('value');

    if (!in_array($field, $this->inline_editable)) {
        return new JsonResponse(['error' => 'Field not editable'], 400);
    }

    $model = $this->model::find($id);
    $model->$field = $value;
    $model->save();

    return (new HtmxResponse())
        ->trigger('rowUpdated')
        ->toResponse($this->formatValue($field, $value));
}
```

**Template changes:** Add `contenteditable` or click-to-edit behavior.

---

### Task 4.4: Column Visibility Preferences
- [ ] **Pending**

**Create:** `migrations/XXXX_create_user_preferences.php`
```sql
CREATE TABLE user_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    preference_key VARCHAR(255) NOT NULL,
    preference_value JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user_key (user_id, preference_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Features:**
- Toggle column visibility
- Persist per user per module
- Restore default option

---

### Task 4.5: Advanced Search Operators
- [ ] **Pending**

**File:** `src/Framework/Http/AdminController.php`

**Enhancement:** Support operators in search box.

**Syntax examples:**
- `status:active` - Field equals value
- `price:>100` - Greater than
- `created:<2024-01-01` - Less than (date)
- `name:~john` - Contains (LIKE)

**Implementation:**
```php
protected function parseSearchQuery(string $query): array
{
    $conditions = [];
    $parts = preg_split('/\s+/', $query);

    foreach ($parts as $part) {
        if (preg_match('/^(\w+):(>|<|>=|<=|~)?(.+)$/', $part, $matches)) {
            $field = $matches[1];
            $operator = $matches[2] ?: '=';
            $value = $matches[3];

            $conditions[] = match ($operator) {
                '~' => [$field, 'LIKE', "%$value%"],
                '>' => [$field, '>', $value],
                '<' => [$field, '<', $value],
                default => [$field, '=', $value],
            };
        } else {
            // Default text search across searchable columns
            $conditions[] = ['_text', 'LIKE', "%$part%"];
        }
    }

    return $conditions;
}
```

---

### Task 4.6: Export Format Options
- [ ] **Pending**

**File:** `src/Framework/Http/AdminController.php`

**Enhancement:** Support multiple export formats and options.

**Features:**
- CSV with delimiter options (comma, semicolon, tab)
- JSON export
- Excel export (via PhpSpreadsheet)
- Configurable encoding (UTF-8, UTF-8-BOM)
- Selected columns only

**Implementation:**
```php
#[Get("/export/{format}", "export")]
public function export(string $format, Request $request): Response
{
    $columns = $request->get()->get('columns', array_keys($this->table_columns));

    return match ($format) {
        'csv' => $this->exportCsv($columns, $request->get()->get('delimiter', ',')),
        'json' => $this->exportJson($columns),
        'xlsx' => $this->exportExcel($columns),
        default => throw new \InvalidArgumentException("Unknown format: $format"),
    };
}
```

---

## Phase 6: Framework Features (ON HOLD)

**Priority:** DEFERRED
**Status:** On hold pending stability work
**Goal:** Add missing framework capabilities

### Task 6.1: Event/Listener System
- [ ] **Pending**

**Create files:**
- `src/Framework/Events/Event.php`
- `src/Framework/Events/Dispatcher.php`
- `src/Framework/Events/Listener.php`
- `config/events.php`

**Implementation:**
```php
// Event base class
abstract class Event
{
    public readonly \DateTimeImmutable $timestamp;

    public function __construct()
    {
        $this->timestamp = new \DateTimeImmutable();
    }
}

// Dispatcher
class Dispatcher
{
    use Singleton;

    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(Event $event): void
    {
        $class = get_class($event);
        foreach ($this->listeners[$class] ?? [] as $listener) {
            $listener($event);
        }
    }
}

// Helper function
function event(Event $event): void
{
    Dispatcher::getInstance()->dispatch($event);
}
```

**Built-in events:** `UserCreated`, `UserUpdated`, `ModelSaved`, `AuditLogged`

---

### Task 6.2: Queue/Job System
- [ ] **Pending**

**Create files:**
- `src/Framework/Queue/Queue.php`
- `src/Framework/Queue/Job.php`
- `src/Framework/Queue/Drivers/DatabaseQueue.php`
- `src/Framework/Console/Commands/QueueWorkCommand.php`
- `migrations/XXXX_create_jobs_table.php`

**Migration:**
```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) DEFAULT 'default',
    payload JSON NOT NULL,
    attempts TINYINT UNSIGNED DEFAULT 0,
    reserved_at TIMESTAMP NULL,
    available_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_queue_available (queue, available_at)
);

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255),
    payload JSON NOT NULL,
    exception TEXT,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Usage:**
```php
// Dispatch job
Queue::push(new SendEmailJob($user, $template));

// Process jobs
php bin/console queue:work --queue=default --timeout=60
```

---

### Task 6.3: Mail Service
- [ ] **Pending**

**Create files:**
- `src/Framework/Mail/Mailer.php`
- `src/Framework/Mail/Mailable.php`
- `src/Framework/Mail/Drivers/SmtpDriver.php`
- `src/Framework/Mail/Drivers/LogDriver.php`
- `config/mail.php`

**Implementation:**
```php
abstract class Mailable
{
    public string $subject;
    public string $view;
    public array $data = [];
    public array $to = [];
    public array $cc = [];
    public array $attachments = [];

    abstract public function build(): self;
}

class Mailer
{
    public function send(Mailable $mail): bool;
    public function queue(Mailable $mail): void; // Uses Queue service
}

// Usage
Mail::send(new WelcomeEmail($user));
Mail::queue(new WeeklyReportEmail($data));
```

---

### Task 6.4: Authorization Gates
- [ ] **Pending**

**Create files:**
- `src/Framework/Auth/Gate.php`
- `src/Framework/Auth/Policy.php`

**Implementation:**
```php
class Gate
{
    use Singleton;

    private array $abilities = [];
    private array $policies = [];

    public function define(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    public function allows(string $ability, mixed ...$args): bool
    {
        $user = user();
        if (!$user) return false;

        if (isset($this->abilities[$ability])) {
            return $this->abilities[$ability]($user, ...$args);
        }
        return false;
    }

    public function denies(string $ability, mixed ...$args): bool
    {
        return !$this->allows($ability, ...$args);
    }
}

// Helper
function can(string $ability, mixed ...$args): bool
{
    return Gate::getInstance()->allows($ability, ...$args);
}
```

**Usage:**
```php
// Define in service provider
Gate::define('edit-user', fn(User $user, User $target) =>
    $user->id === $target->id || $user->hasPermission('admin')
);

// Check in controller
if (!can('edit-user', $targetUser)) {
    abort(403);
}
```

---

### Task 6.5: CLI Code Generators
- [ ] **Pending**

**Create commands:**
- `src/Framework/Console/Commands/MakeModelCommand.php`
- `src/Framework/Console/Commands/MakeControllerCommand.php`
- `src/Framework/Console/Commands/MakeMigrationCommand.php`
- `src/Framework/Console/Commands/MakeMiddlewareCommand.php`

**Usage:**
```bash
php bin/console make:model User --migration
php bin/console make:controller UserController --model=User
php bin/console make:migration create_posts_table
php bin/console make:middleware RateLimitMiddleware
```

**Templates stored in:** `src/Framework/Console/stubs/`

---

### Task 6.6: Convert to Enums
- [ ] **Pending**

**Convert these constant classes to enums:**

```php
// src/Framework/Logging/LogLevel.php
enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';

    public function priority(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert => 1,
            self::Critical => 2,
            self::Error => 3,
            self::Warning => 4,
            self::Notice => 5,
            self::Info => 6,
            self::Debug => 7,
        };
    }

    public function shouldLog(self $minLevel): bool
    {
        return $this->priority() <= $minLevel->priority();
    }
}

// src/Framework/Audit/AuditEvent.php
enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
}

// src/Framework/Http/HttpMethod.php
enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
    case Options = 'OPTIONS';
}
```

---

### Task 6.7: Development Helpers
- [ ] **Pending**

**File:** `app/Helpers/Functions.php`

**Add helpers:**
```php
/**
 * Dump and die
 */
function dd(mixed ...$vars): never
{
    foreach ($vars as $var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
    exit(1);
}

/**
 * Dump without dying
 */
function dump(mixed ...$vars): void
{
    foreach ($vars as $var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
}

/**
 * Abort with status code
 */
function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    throw new \Echo\Framework\Exceptions\HttpException($code, $message);
}

/**
 * Generate asset URL with cache busting
 */
function asset(string $path): string
{
    $fullPath = config('paths.public') . $path;
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    return $path . '?v=' . $version;
}

/**
 * Generate route URL by name
 */
function route(string $name, array $params = []): string
{
    return \Echo\Framework\Routing\Router::getInstance()->url($name, $params);
}
```

---

## Completion Checklist

**Completed:**

- [x] Phase 1: Critical Bug Fixes (6/6) ✓
- [x] Phase 2: Performance Optimizations (8/8) ✓
- [x] Phase 3: Test Coverage (6/6) ✓
- [x] Redis Integration (Cache Service from Phase 5.2) ✓

**Active Phases (Stability Focus):**

- [ ] Phase 4: AdminController Review (0/1)

**Active Tasks:** 1 remaining

**On Hold (New Features):**

- [ ] Phase 5: AdminController Enhancements (0/6) - Deferred
- [ ] Phase 6: Framework Features (0/7) - Deferred (Cache complete)

**Deferred Tasks:** 13

---

## Quick Reference: Console Commands

```bash
# Run ./bin/console to see all available commands

# Route commands
./bin/console route:cache
./bin/console route:clear
./bin/console route:list

# Migration commands
./bin/console migrate:run
./bin/console migrate:status
./bin/console migrate:fresh
./bin/console migrate:rollback
./bin/console migrate:create <table>

# Database commands
./bin/console db:backup
./bin/console db:restore <file>
./bin/console db:list
./bin/console db:cleanup <keep>

# Session commands
./bin/console session:cleanup --days=30
./bin/console session:stats

# Audit commands
./bin/console audit:list
./bin/console audit:stats
./bin/console audit:purge --days=90

# Other commands
./bin/console admin:new <email> <password>
./bin/console storage:fix
./bin/console server
./bin/console version
```

---

## Priority Order Recommendation

**Current Focus: Stability First**

1. ~~**Phase 1 (Bug Fixes)**~~ - Complete ✓
2. ~~**Phase 2 (Performance)**~~ - Complete ✓
3. ~~**Phase 3 (Tests)**~~ - Complete ✓ (325 tests, 613 assertions)
4. **Phase 4 (AdminController Review)** - Code review and potential refactoring

**Deferred until stable:**
- Phase 5 (Admin Enhancements) - On hold
- Phase 6 (Framework Features) - On hold (Cache complete via Redis)

---

## Notes

### Performance Impact Summary

| Optimization | Expected Impact |
|--------------|-----------------|
| Database indexes | 10-100x faster queries |
| Session optimization | 50% fewer session operations |
| Config caching | 30% faster config access |
| Route pre-compilation | 40% faster routing |
| Widget lazy loading | 60% less memory on dashboard |
| Eager loading | Eliminate N+1 queries |

### Test Coverage Summary

**Phase 3 Completion (2026-02-05):**

| Component | Tests | Assertions | Status |
|-----------|-------|------------|--------|
| HTTP Kernel | 19 | 46 | ✓ Complete |
| Auth Middleware | 17 | 22 | ✓ Complete |
| BearerAuth Middleware | 30 | 36 | ✓ Complete |
| RequestLimit Middleware | 23 | 43 | ✓ Complete |
| HtmxResponse | 32 | 72 | ✓ Complete |
| AuditLogger | 15 | 33 | ✓ Complete |
| **Total (all tests)** | **325** | **613** | ✓ All passing |

Test files created:
- `tests/Http/KernelTest.php`
- `tests/Http/HtmxResponseTest.php`
- `tests/Http/Middleware/AuthTest.php`
- `tests/Http/Middleware/AuthIntegrationTest.php`
- `tests/Http/Middleware/BearerAuthTest.php`
- `tests/Http/Middleware/BearerAuthIntegrationTest.php`
- `tests/Http/Middleware/RequestLimitTest.php`
- `tests/Http/Middleware/RequestLimitIntegrationTest.php`
- `tests/Audit/AuditLoggerTest.php`
