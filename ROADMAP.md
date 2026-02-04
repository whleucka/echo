# Echo Framework Improvement Roadmap v2

This document tracks the second phase of improvement work for the Echo PHP framework, based on deep code audit findings.

**Created:** 2026-02-03
**Previous Roadmap:** ROADMAP.md (21/21 tasks complete)

---

## Progress Overview

| Phase | Description | Status | Progress |
|-------|-------------|--------|----------|
| 1 | Security & API | Complete | 5/5 |
| 2 | Profiler & Debug | Complete | 4/4 |
| 3 | DI & Architecture | Complete | 4/4 |
| 4 | HTMX Enhancements | Not Started | 0/4 |
| 5 | Admin & Audit | Not Started | 0/5 |

---

## Phase 1: Security & API

**Priority:** CRITICAL
**Goal:** Fix API security gaps and add missing middleware

### Task 1.1: Add CORS Middleware
- [x] **Complete**

**File to create:** `src/Framework/Http/Middleware/CORS.php`

**Problem:** No CORS support - cross-origin API requests fail.

**Implementation:**
```php
class CORS implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->preflightResponse();
        }

        $response = $next($request);

        // Add CORS headers to response
        return $this->addCorsHeaders($response);
    }

    private function addCorsHeaders(Response $response): Response
    {
        $allowed = config('cors.allowed_origins') ?? ['*'];
        $response->setHeader('Access-Control-Allow-Origin', implode(', ', $allowed));
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Max-Age', '3600');
        return $response;
    }
}
```

**Also create:** `config/cors.php` with configurable origins, methods, headers.

**Verification:**
- Preflight OPTIONS requests return 200 with proper headers
- Cross-origin JavaScript requests succeed

---

### Task 1.2: Add Bearer Token Authentication
- [x] **Complete**

**File to create:** `src/Framework/Http/Middleware/BearerAuth.php`

**Problem:** API only supports session auth, no stateless token support.

**Implementation:**
- Create `api_tokens` migration (token, user_id, expires_at, created_at)
- Parse `Authorization: Bearer <token>` header
- Validate token and attach user to request
- Return 401 JSON response if invalid

**Verification:**
- API routes work with valid bearer token
- Invalid/expired tokens return 401
- Session-based auth still works for web routes

---

### Task 1.3: Add Security Headers to JsonResponse
- [x] **Complete**

**File:** `src/Framework/Http/JsonResponse.php`

**Problem:** JSON responses don't include security headers that HTML responses have.

**Changes Required:**
Add to `send()` method:
```php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Cache-Control: no-store, no-cache, must-revalidate");
```

**Verification:**
- API responses include security headers
- Check with browser dev tools

---

### Task 1.4: Sanitize API Error Messages
- [x] **Complete**

**File:** `src/Framework/Http/Kernel.php`

**Problem:** SQL queries and stack traces leak in debug mode API errors.

**Changes Required:**
- Never expose raw SQL in error responses
- Log detailed errors server-side
- Return generic messages to client
- Add error codes for programmatic handling

**Error response format:**
```json
{
    "id": "...",
    "success": false,
    "status": 500,
    "error": {
        "code": "DATABASE_ERROR",
        "message": "An error occurred processing your request"
    }
}
```

**Verification:**
- Database errors don't leak SQL
- Stack traces not exposed
- Errors logged server-side

---

### Task 1.5: Add API Versioning Support
- [x] **Complete**

**Files:**
- `src/Framework/Http/Middleware/ApiVersion.php`
- Update Router to handle versioned routes

**Implementation:**
- Support header-based: `Accept: application/vnd.echo.v1+json`
- Support URL-based: `/api/v1/users`
- Add version to response headers

**Verification:**
- Routes can be versioned
- Old versions continue to work

---

## Phase 2: Profiler & Debug

**Priority:** HIGH
**Goal:** Add performance monitoring and debug tools

### Task 2.1: Create Query Profiler
- [x] **Complete**

**Files to create:**
- `src/Framework/Debug/QueryProfiler.php`
- `src/Framework/Debug/ProfiledConnection.php`

**Implementation:**
```php
class QueryProfiler
{
    private static array $queries = [];

    public static function log(string $sql, array $params, float $time): void
    {
        self::$queries[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => $time,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
    }

    public static function getQueries(): array
    public static function getTotalTime(): float
    public static function getSlowQueries(float $threshold = 0.1): array
}
```

**Wrap Connection class to measure query execution time.**

**Verification:**
- All queries are logged with timing
- Slow queries identified
- Works in debug mode only

---

### Task 2.2: Create Request Profiler
- [x] **Complete**

**File to create:** `src/Framework/Debug/RequestProfiler.php`

**Track:**
- Total request time
- Memory usage (peak and current)
- Query count and total query time
- Middleware execution time
- Controller execution time

**Verification:**
- Request metrics captured
- Performance bottlenecks identifiable

---

### Task 2.3: Create Debug Toolbar
- [x] **Complete**

**Files to create:**
- `src/Framework/Debug/DebugBar.php`
- `templates/debug/toolbar.html.twig`
- `public/css/debug-toolbar.css`
- `public/js/debug-toolbar.js`

**Features:**
- Collapsible bar at bottom of page
- Tabs: Request, Queries, Timeline, Memory
- Query list with execution time and EXPLAIN
- Request/Response headers
- Session data viewer
- Only shows when `config('app.debug') === true`

**Verification:**
- Toolbar renders on HTML pages in debug mode
- Does not appear in production
- Does not affect API responses

---

### Task 2.4: Add Logging Service
- [x] **Complete**

**Files to create:**
- `src/Framework/Log/Logger.php`
- `src/Framework/Log/LogLevel.php`

**Implementation:**
- PSR-3 compatible logging
- File-based with rotation
- Log levels: debug, info, warning, error, critical
- Structured JSON format option
- Context support

**Verification:**
- Logs written to `storage/logs/`
- Daily rotation works
- Log levels respected

---

## Phase 3: DI & Architecture

**Priority:** HIGH
**Goal:** Improve dependency injection and code organization

### Task 3.1: Add Interface Bindings to Container
- [x] **Complete**

**File:** `config/container.php`

**Problem:** Interfaces not bound, using concrete classes directly.

**Add bindings:**
```php
use Echo\Interface\Database\Connection as ConnectionInterface;
use Echo\Interface\Database\Driver as DriverInterface;
use Echo\Interface\Http\Request as RequestInterface;
use Echo\Interface\Http\Response as ResponseInterface;

return [
    // Interface bindings
    ConnectionInterface::class => DI\get(Connection::class),
    DriverInterface::class => DI\get(config('db.driver') === 'mysql' ? MySQL::class : MariaDB::class),
    RequestInterface::class => DI\get(Request::class),

    // Existing concrete bindings...
];
```

**Verification:**
- Can resolve by interface
- Swapping implementations works

---

### Task 3.2: Register Middleware in Container
- [x] **Complete**

**Files:**
- `config/container.php`
- `src/Framework/Http/Middleware.php`

**Problem:** Middleware instantiated via `new $class`, bypasses DI.

**Changes:**
- Register all middleware in container
- Resolve middleware through container in pipeline
- Enable constructor injection in middleware

**Verification:**
- Middleware can have dependencies injected
- All existing middleware still works

---

### Task 3.3: Replace db() Singleton with DI
- [x] **Complete**

**Files:**
- `app/Helpers/Functions.php`
- `config/container.php`

**Problem:** `db()` uses manual singleton pattern, hard to test.

**Changes:**
- Register Connection in container as singleton
- Update `db()` helper to resolve from container
- Remove Connection singleton pattern

**Verification:**
- Database still works
- Can mock in tests

---

### Task 3.4: Create Service Provider Pattern
- [x] **Complete**

**Files to create:**
- `src/Framework/Support/ServiceProvider.php`
- `app/Providers/` directory structure

**Implementation:**
```php
abstract class ServiceProvider
{
    abstract public function register(): void;
    public function boot(): void {}
}

// Example:
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->set(Connection::class, ...);
    }
}
```

**Verification:**
- Providers loaded on boot
- Services organized by domain

---

## Phase 4: HTMX Enhancements

**Priority:** MEDIUM
**Goal:** Improve HTMX integration and developer experience

### Task 4.1: Add HTMX Request Helper Methods
- [ ] **Complete**

**File:** `src/Framework/Http/Request.php`

**Add methods:**
```php
public function getHtmxTrigger(): ?string      // HX-Trigger header
public function getHtmxTarget(): ?string       // HX-Target header
public function getHtmxCurrentUrl(): ?string   // HX-Current-URL header
public function isHtmxBoosted(): bool          // HX-Boosted header
public function getHtmxPrompt(): ?string       // HX-Prompt header
```

**Verification:**
- All HTMX headers accessible
- Existing `isHTMX()` still works

---

### Task 4.2: Create HTMX Response Builder
- [ ] **Complete**

**File to create:** `src/Framework/Http/HtmxResponse.php`

**Implementation:**
```php
class HtmxResponse
{
    public function redirect(string $url): self
    public function trigger(string|array $events): self
    public function retarget(string $selector): self
    public function reswap(string $strategy): self
    public function reselect(string $selector): self
    public function refresh(): self
    public function pushUrl(string $url): self
    public function replaceUrl(string $url): self

    public function toResponse(string $content, int $status = 200): Response
}
```

**Usage:**
```php
return (new HtmxResponse())
    ->trigger('rowUpdated')
    ->retarget('#table-body')
    ->reswap('innerHTML')
    ->toResponse($html);
```

**Verification:**
- Fluent interface works
- Headers properly set

---

### Task 4.3: Add HTMX Controller Trait
- [ ] **Complete**

**File to create:** `src/Framework/Http/Traits/HtmxHelpers.php`

**Consolidate common patterns from AdminController:**
```php
trait HtmxHelpers
{
    protected function htmxTableRefresh(string $html): Response
    protected function htmxModalClose(): Response
    protected function htmxFormErrors(array $errors): Response
    protected function htmxPartial(string $template, array $data): Response
}
```

**Verification:**
- AdminController simplified
- Reusable in other controllers

---

### Task 4.4: Add Real-Time Dashboard Updates
- [ ] **Complete**

**Files:**
- Update `templates/admin/dashboard.html.twig`
- Add `hx-trigger="every 30s"` for auto-refresh
- Consider SSE endpoint for push updates

**Implementation:**
- Dashboard cards auto-refresh every 30 seconds
- Optional SSE for instant updates
- Visual indicator during refresh

**Verification:**
- Stats update without page reload
- No performance degradation

---

## Phase 5: Admin & Audit

**Priority:** MEDIUM
**Goal:** Expand admin dashboard and add audit logging

### Task 5.1: Create Audit Logging System
- [ ] **Complete**

**Files to create:**
- `migrations/XXXX_create_audits.php`
- `app/Models/Audit.php`
- `src/Framework/Audit/AuditLogger.php`

**Migration:**
```sql
CREATE TABLE audits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED,
    auditable_type VARCHAR(255) NOT NULL,  -- Model class
    auditable_id BIGINT UNSIGNED NOT NULL,  -- Record ID
    event VARCHAR(50) NOT NULL,             -- created, updated, deleted
    old_values JSON,                        -- Before state
    new_values JSON,                        -- After state
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditable (auditable_type, auditable_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);
```

**Integration:**
- Hook into Model create/update/delete
- Automatically capture changes
- Store diff (old vs new values)

**Verification:**
- All model changes logged
- Diff viewable

---

### Task 5.2: Create Audit Admin Module
- [ ] **Complete**

**Files to create:**
- `app/Http/Controllers/Admin/AuditController.php`
- `templates/admin/audit/` templates

**Features:**
- List all audit entries with filters
- Filter by user, model type, event, date range
- View detailed diff (side-by-side or inline)
- Search by record ID
- Export audit trail

**Verification:**
- Audit module appears in admin
- Changes viewable with diff

---

### Task 5.3: Expand Dashboard Analytics
- [ ] **Complete**

**File:** `app/Http/Controllers/Admin/DashboardController.php`

**Add real metrics:**
- System health (memory, disk, PHP version)
- Database stats (table sizes, slow queries)
- User activity heatmap (by hour/day)
- Popular pages/routes
- Error rate tracking
- Audit activity summary

**Verification:**
- Dashboard shows real data
- Metrics are accurate

---

### Task 5.4: Add Dashboard Widget System
- [ ] **Complete**

**Files to create:**
- `src/Framework/Admin/Widget.php`
- `src/Framework/Admin/WidgetRegistry.php`

**Implementation:**
- Widgets are self-contained components
- Configurable per-user (which widgets, order)
- Built-in widgets: Stats, Chart, Table, Activity Feed
- Easy to create custom widgets

**Verification:**
- Widgets render independently
- Layout customizable

---

### Task 5.5: Add System Health Monitoring
- [ ] **Complete**

**Files to create:**
- `app/Providers/SystemHealthService.php`
- `app/Http/Controllers/Admin/HealthController.php`

**Metrics:**
- PHP version and extensions
- Memory usage and limits
- Disk space
- Database connection status
- Cache status
- Queue status (if applicable)
- Last migration status
- Scheduled task status

**Verification:**
- Health endpoint returns status
- Admin can view system health

---

## Completion Checklist

When all phases are complete:

- [ ] Phase 1: Security & API (5/5)
- [x] Phase 2: Profiler & Debug (4/4)
- [x] Phase 3: DI & Architecture (4/4)
- [ ] Phase 4: HTMX Enhancements (4/4)
- [ ] Phase 5: Admin & Audit (5/5)

**Total Tasks:** 22

---

## Quick Reference: New Console Commands

After completion, these commands will be available:

```bash
# Route management (already exists)
php bin/console route cache
php bin/console route clear
php bin/console route list

# New commands (Phase 2+)
php bin/console debug:queries      # Show logged queries
php bin/console audit:list         # List recent audits
php bin/console health:check       # System health status
```

---

## Priority Order Recommendation

1. **Phase 1 (Security)** - Critical for production APIs
2. **Phase 2 (Profiler)** - Essential for debugging
3. **Phase 5 (Audit)** - Important for compliance
4. **Phase 3 (DI)** - Improves maintainability
5. **Phase 4 (HTMX)** - Quality of life improvements
