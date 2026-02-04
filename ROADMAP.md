# Echo Framework Improvement Roadmap

This document tracks the improvement work for the Echo PHP framework, divided into 4 phases. Each phase should be completed before moving to the next. Run `/roadmap` to work on tasks.

**Created:** 2026-02-03
**Last Review:** Pending Phase 4 completion

---

## Progress Overview

| Phase | Description | Status | Progress |
|-------|-------------|--------|----------|
| 1 | Security Fixes | Complete | 5/5 |
| 2 | Critical Bugs | Complete | 5/5 |
| 3 | Testing | Complete | 6/6 |
| 4 | Features | Complete | 5/5 |

---

## Phase 1: Security Fixes

**Priority:** CRITICAL
**Goal:** Address security vulnerabilities before any production use

### Task 1.1: Secure Session Cookie Settings
- [x] **Complete**

**File:** `src/Framework/Session/Session.php`

**Problem:** Session cookies lack security flags, making them vulnerable to XSS, CSRF, and session fixation attacks.

**Changes Required:**
```php
// Add after line 9 (after gc settings):
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
```

**Verification:**
- Check browser dev tools shows `HttpOnly`, `Secure`, `SameSite=Strict` on session cookie
- Verify sessions still work normally

---

### Task 1.2: Session Regeneration on Login
- [x] **Complete**

**File:** `app/Providers/SignInService.php`

**Problem:** Session ID is not regenerated after login, allowing session fixation attacks.

**Changes Required:**
```php
public function signIn(string $email_address, string $password): bool
{
    $user = User::where("email", $email_address)->get();

    if ($user && password_verify($password, $user->password)) {
        session_regenerate_id(true);  // ADD THIS LINE
        session()->set("user_uuid", $user->uuid);
        return true;
    }
    return false;
}
```

**Verification:**
- Login and note session ID (from cookie)
- Session ID should change after successful login
- Old session ID should be invalid

---

### Task 1.3: Fix IP Spoofing Vulnerability
- [x] **Complete**

**File:** `src/Framework/Http/Request.php`

**Problem:** `getClientIp()` trusts user-controlled headers (`X-Forwarded-For`, `X-Client-IP`), allowing bypass of rate limiting and IP blacklists.

**Changes Required:**
Replace `getClientIp()` method (lines 69-77):
```php
public function getClientIp(array $trustedProxies = []): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Only trust proxy headers if request comes from a known proxy
    if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Take the first (client) IP from the chain
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            return $ips[0];
        }
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
    }

    return $remoteAddr;
}
```

**Also update:** `config/security.php` to add trusted proxy configuration:
```php
'trusted_proxies' => explode(',', env('TRUSTED_PROXIES') ?? ''),
```

**Verification:**
- Set `X-Forwarded-For: 1.2.3.4` header manually
- Without trusted proxy config, should return actual `REMOTE_ADDR`
- Rate limiting should use real IP

---

### Task 1.4: Fix CSRF Token Generation
- [x] **Complete**

**File:** `src/Framework/Http/Middleware/CSRF.php`

**Problem:** Uses MD5 which reduces entropy from 256 bits to 128 bits.

**Changes Required:**
Replace `generateToken()` method (around line 51):
```php
private function generateToken(): string
{
    return bin2hex(random_bytes(32));
}
```

**Verification:**
- Generated tokens should be 64 characters (hex encoded 32 bytes)
- CSRF protection should still work normally

---

### Task 1.5: Add Security Headers
- [x] **Complete**

**File:** `src/Framework/Http/Response.php`

**Problem:** No security headers sent by default.

**Changes Required:**
Update the `send()` method to add headers before output:
```php
public function send(): void
{
    http_response_code($this->status);

    // Security headers
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-XSS-Protection: 1; mode=block");

    // Custom headers
    foreach ($this->headers as $name => $value) {
        header("$name: $value");
    }

    echo $this->content;
}
```

**Verification:**
- Check response headers in browser dev tools
- All 4 security headers should be present

---

## Phase 2: Critical Bugs

**Priority:** HIGH
**Goal:** Fix bugs that cause incorrect behavior or performance issues

### Task 2.1: Fix N+1 Query Problem in Model
- [x] **Complete**

**File:** `src/Framework/Database/Model.php`

**Problem:** `get()` method fetches rows, then calls `find()` for EACH row, causing N+1 queries.

**Changes Required:**

Add a new `hydrate()` method:
```php
protected static function hydrate(object $data): static
{
    $model = new static();
    $model->attributes = (array) $data;
    return $model;
}
```

Update `get()` method (around line 140):
```php
public function get(int $limit = 0): null|array|static
{
    $results = $this->qb
        ->select($this->columns)
        ->from($this->table_name)
        ->where($this->where)
        ->orWhere($this->or_where)
        ->orderBy($this->order_by)
        ->limit($limit)
        ->params($this->params)
        ->execute()
        ->fetchAll(PDO::FETCH_OBJ);

    if (!$results) {
        return null;
    }

    if (count($results) === 1) {
        return static::hydrate($results[0]);
    }

    return array_map(fn($row) => static::hydrate($row), $results);
}
```

Update `first()` and `last()` similarly to use `hydrate()`.

**Verification:**
- Enable query logging
- `User::where('active', 1)->get()` with 100 users should be 1 query, not 101
- Model attributes should still be accessible

---

### Task 2.2: Add Config Caching
- [x] **Complete**

**File:** `app/Helpers/Functions.php`

**Problem:** Config files are re-read from disk on every `config()` call.

**Changes Required:**
Replace the `config()` function (around line 157):
```php
function config(string $name): mixed
{
    static $cache = [];

    $name_split = explode(".", $name);
    $file = strtolower($name_split[0]);

    // Load and cache config file
    if (!isset($cache[$file])) {
        $config_target = __DIR__ . "/../../config/" . $file . ".php";
        $cache[$file] = is_file($config_target) ? require $config_target : [];
    }

    // Return full config if no nested key
    if (count($name_split) === 1) {
        return $cache[$file];
    }

    // Traverse nested keys
    $value = $cache[$file];
    for ($i = 1; $i < count($name_split); $i++) {
        $key = $name_split[$i];
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return null;
        }
        $value = $value[$key];
    }

    // Handle string booleans from env
    if ($value === "true") return true;
    if ($value === "false") return false;

    return $value;
}
```

**Verification:**
- Config values should still work normally
- Performance improvement (not measurable without profiling, but fewer file reads)

---

### Task 2.3: Fix Dotenv Loading
- [x] **Complete**

**File:** `app/Helpers/Functions.php`

**Problem:** `env()` function reloads `.env` file on every call.

**Changes Required:**
Replace the `env()` function (around line 119):
```php
function env(string $name): mixed
{
    static $loaded = false;

    if (!$loaded) {
        $root = __DIR__ . "/../../";
        if (file_exists($root . '.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable($root);
            $dotenv->safeLoad();
        }
        $loaded = true;
    }

    return $_ENV[$name] ?? $_SERVER[$name] ?? null;
}
```

**Note:** Also remove the duplicate Dotenv loading in `app/Application.php` if present.

**Verification:**
- Environment variables should still work
- `.env` file read only once per request

---

### Task 2.4: Add Migration Transaction Safety
- [x] **Complete**

**File:** `src/Framework/Console/Commands/Migrate.php`

**Problem:** Migrations run without transactions - partial failures leave schema corrupted.

**Changes Required:**
Update `migrationUp()` method (around line 105):
```php
private function migrationUp(string $file_path)
{
    $exists = $this->migrationHashExists(md5($file_path));
    if ($exists) {
        return;
    }

    $migration = $this->getMigration($file_path);

    db()->beginTransaction();
    try {
        $sql = $migration->up();
        $result = db()->execute($sql);

        if ($result) {
            $this->insertMigration($file_path);
            db()->commit();
        } else {
            db()->rollback();
            throw new \Exception("Migration failed: " . basename($file_path));
        }
    } catch (\Exception $e) {
        db()->rollback();
        throw $e;
    }
}
```

Update `migrationDown()` similarly.

**Verification:**
- Create a migration that intentionally fails halfway
- Database should remain unchanged after failure
- Successful migrations should still work

---

### Task 2.5: Fix Request getAttribute Null Check
- [x] **Complete**

**File:** `src/Framework/Http/Request.php`

**Problem:** `getAttribute()` throws error when accessing non-existent attribute.

**Changes Required:**
Update `getAttribute()` method (around line 56):
```php
public function getAttribute(string $name): mixed
{
    return $this->attributes[$name] ?? null;
}
```

**Verification:**
- `$request->getAttribute('nonexistent')` should return `null`, not throw error

---

## Phase 3: Testing

**Priority:** HIGH
**Goal:** Achieve meaningful test coverage on critical paths

### Task 3.1: Create Test Infrastructure
- [x] **Complete**

**Files to create:**
- `tests/TestCase.php` - Base test class
- `tests/bootstrap.php` - Test bootstrap
- `phpunit.xml` - PHPUnit configuration (if not exists)

**TestCase.php:**
```php
<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Common setup
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Common cleanup
    }
}
```

**bootstrap.php:**
```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Set test environment
$_ENV['APP_ENV'] = 'testing';
```

**Verification:**
- `composer test` runs without bootstrap errors

---

### Task 3.2: Add Session Tests
- [x] **Complete**

**File to create:** `tests/Session/SessionTest.php`

**Test cases:**
- `testSetAndGetValue()` - Store and retrieve values
- `testHasReturnsTrueForExistingKey()`
- `testHasReturnsFalseForMissingKey()`
- `testDeleteRemovesKey()`
- `testDestroyRemovesAllData()`
- `testAllReturnsAllSessionData()`

**Verification:**
- All session tests pass

---

### Task 3.3: Add Flash Message Tests
- [x] **Complete**

**File to create:** `tests/Session/FlashTest.php`

**Test cases:**
- `testAddStoresMessage()`
- `testGetRetrievesAndClearsMessages()`
- `testDestroyRemovesAllFlashMessages()`
- `testDuplicateMessagesAreIgnored()`
- `testMultipleMessageTypes()`

**Verification:**
- All flash tests pass

---

### Task 3.4: Add Validation Rules Tests
- [x] **Complete**

**File to create:** `tests/Http/ValidationRulesTest.php`

**Test cases for each rule:**
- `required` - empty vs non-empty
- `email` - valid and invalid formats
- `uuid` - valid and invalid UUIDs
- `url` - valid and invalid URLs
- `ip`, `ipv4`, `ipv6` - various formats
- `min_length`, `max_length` - boundary cases
- `numeric`, `integer`, `float` - type checking
- `boolean` - truthy/falsy values
- `regex` - pattern matching
- `unique` - database uniqueness (mocked)
- `match` - field matching

**Verification:**
- All validation tests pass
- Edge cases covered

---

### Task 3.5: Add CSRF Middleware Tests
- [x] **Complete**

**File to create:** `tests/Http/Middleware/CSRFTest.php`

**Test cases:**
- `testGetRequestsBypassCSRF()`
- `testPostRequestRequiresToken()`
- `testValidTokenPasses()`
- `testInvalidTokenFails()`
- `testMissingTokenFails()`
- `testApiRoutesCanBypassCSRF()`

**Verification:**
- All CSRF tests pass

---

### Task 3.6: Add Model CRUD Tests
- [x] **Complete**

**File to create:** `tests/Database/ModelCrudTest.php`

**Test cases:**
- `testCreateReturnsModelInstance()`
- `testFindReturnsModelById()`
- `testFindReturnsNullForMissing()`
- `testUpdateModifiesAttributes()`
- `testDeleteRemovesRecord()`
- `testWhereBuildsCorrectQuery()`
- `testGetReturnsArray()`
- `testFirstReturnsSingleModel()`

**Note:** These may need database mocking or a test database.

**Verification:**
- All model tests pass

---

## Phase 4: Features

**Priority:** MEDIUM
**Goal:** Add commonly needed features

### Task 4.1: Add Schema::alter() for Migrations
- [x] **Complete**

**File:** `src/Framework/Database/Schema.php`

**Add new method:**
```php
public static function alter(string $table, callable $callback): string
{
    $blueprint = new Blueprint($table);
    $callback($blueprint);
    return $blueprint->buildAlter();
}
```

**File:** `src/Framework/Database/Blueprint.php`

**Add methods:**
```php
public function addColumn(string $type, string $name, ...$args): static
{
    // Mark as ALTER ADD
    $this->alterMode = 'add';
    return $this->$type($name, ...$args);
}

public function dropColumn(string $name): static
{
    $this->alterDrops[] = $name;
    return $this;
}

public function buildAlter(): string
{
    $statements = [];

    foreach ($this->definitions as $def) {
        $statements[] = "ADD COLUMN $def";
    }

    foreach ($this->alterDrops as $col) {
        $statements[] = "DROP COLUMN $col";
    }

    return sprintf(
        "ALTER TABLE %s %s",
        $this->table,
        implode(", ", $statements)
    );
}
```

**Verification:**
- Can create migration that adds column to existing table
- Can create migration that drops column

---

### Task 4.2: Add Migration Rollback Command
- [x] **Complete**

**File:** `src/Framework/Console/Commands/Migrate.php`

**Add new command option for rollback:**
- `php bin/console migrate:rollback` - Rollback last batch
- `php bin/console migrate:rollback --steps=3` - Rollback last 3 migrations

**Changes Required:**
- Add `batch` column to migrations table
- Track batch number on migrate up
- Implement rollback logic to find and reverse last batch

**Verification:**
- Run migrations, then rollback
- Database should return to previous state
- Migration record should be removed

---

### Task 4.3: Add Route Caching
- [x] **Complete**

**Files:**
- `src/Framework/Routing/RouteCache.php` (new)
- `src/Framework/Console/Commands/RouteCache.php` (new)

**Implementation:**
```php
// RouteCache.php
class RouteCache
{
    private string $cachePath;

    public function __construct()
    {
        $this->cachePath = config('paths.cache') . '/routes.php';
    }

    public function isCached(): bool
    {
        return file_exists($this->cachePath);
    }

    public function get(): array
    {
        return require $this->cachePath;
    }

    public function cache(array $routes): void
    {
        $content = '<?php return ' . var_export($routes, true) . ';';
        file_put_contents($this->cachePath, $content);
    }

    public function clear(): void
    {
        if ($this->isCached()) {
            unlink($this->cachePath);
        }
    }
}
```

**Commands:**
- `php bin/console route:cache` - Generate route cache
- `php bin/console route:clear` - Clear route cache

**Verification:**
- Routes work with and without cache
- Performance improvement with cache (fewer reflection calls)

---

### Task 4.4: Add Model Relationships
- [x] **Complete**

**File:** `src/Framework/Database/Model.php`

**Add relationship methods:**
```php
public function hasMany(string $related, string $foreignKey = null, string $localKey = null): array
{
    $foreignKey = $foreignKey ?? $this->getForeignKey();
    $localKey = $localKey ?? $this->primary_key;

    return $related::where($foreignKey, $this->$localKey)->get() ?? [];
}

public function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null): ?Model
{
    $foreignKey = $foreignKey ?? (new $related)->getForeignKey();
    $ownerKey = $ownerKey ?? (new $related)->primary_key;

    return $related::where($ownerKey, $this->$foreignKey)->first();
}

public function hasOne(string $related, string $foreignKey = null, string $localKey = null): ?Model
{
    $foreignKey = $foreignKey ?? $this->getForeignKey();
    $localKey = $localKey ?? $this->primary_key;

    return $related::where($foreignKey, $this->$localKey)->first();
}

protected function getForeignKey(): string
{
    $class = (new \ReflectionClass($this))->getShortName();
    return strtolower($class) . '_id';
}
```

**Usage example:**
```php
// In User model
public function permissions(): array
{
    return $this->hasMany(UserPermission::class, 'user_id');
}
```

**Verification:**
- Relationships return correct related models
- Foreign keys are inferred correctly

---

### Task 4.5: Add Bulk Insert Support
- [x] **Complete**

**File:** `src/Framework/Database/Model.php`

**Add method:**
```php
public static function insert(array $records): bool
{
    if (empty($records)) {
        return false;
    }

    $model = new static();
    $columns = array_keys($records[0]);
    $placeholders = [];
    $values = [];

    foreach ($records as $record) {
        $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        foreach ($columns as $col) {
            $values[] = $record[$col] ?? null;
        }
    }

    $sql = sprintf(
        "INSERT INTO %s (%s) VALUES %s",
        $model->table_name,
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    return db()->execute($sql, $values) !== null;
}
```

**Usage:**
```php
User::insert([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com'],
]);
// Single INSERT query with 3 rows
```

**Verification:**
- Single query inserts multiple rows
- All records are inserted correctly

---

## Completion Checklist

All phases complete:

- [x] Phase 1: Security Fixes (5/5)
- [x] Phase 2: Critical Bugs (5/5)
- [x] Phase 3: Testing (6/6)
- [x] Phase 4: Features (5/5)

**Total Tasks:** 21

---

## Next Steps

**Roadmap v1 Complete!** All 21 tasks finished.

See **[ROADMAP-v2.md](ROADMAP-v2.md)** for the next phase of improvements based on deep code audit:

- Phase 1: Security & API (CORS, Bearer Auth, Error Handling)
- Phase 2: Profiler & Debug (Query Timing, Debug Toolbar)
- Phase 3: DI & Architecture (Interface Bindings, Service Providers)
- Phase 4: HTMX Enhancements (Response Builder, Helpers)
- Phase 5: Admin & Audit (Audit Logging, Dashboard Expansion)

**Total v2 Tasks:** 22
