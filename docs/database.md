# Database / ORM

Echo provides an Active Record ORM (`Model`) for simple operations and a `QueryBuilder` for complex SQL.

## Defining Models

```php
use Echo\Framework\Database\Model;
use Echo\Framework\Audit\Auditable;

class User extends Model
{
    use Auditable;

    protected string $tableName = 'users';
    protected string $primaryKey = 'id';        // default
    protected bool $autoIncrement = true;        // default
    protected array $columns = ['*'];            // default
}
```

The `Auditable` trait marks the model for automatic audit logging via the event system.

## CRUD Operations

### Create

```php
$user = User::create([
    'email' => 'jane@example.com',
    'first_name' => 'Jane',
    'last_name' => 'Doe',
]);

// Bulk insert
User::createBulk([
    ['email' => 'a@example.com', 'first_name' => 'Alice'],
    ['email' => 'b@example.com', 'first_name' => 'Bob'],
]);

// Upsert at row level — find by $find columns or create with $find + $attributes
$tag = BlogTag::firstOrCreate(
    ['slug' => 'echo'],
    ['name' => 'Echo Framework'],
);

// Find and apply $attributes, or create. Returns the live model either way.
$counter = HitCounter::updateOrCreate(
    ['path' => '/posts/42'],
    ['hits' => 1, 'last_seen' => date('Y-m-d H:i:s')],
);
```

Both `firstOrCreate()` and `updateOrCreate()` require a non-empty `$find` (otherwise they would always create). All `$find` columns are validated as identifiers, then ANDed together to locate the row.

### Read

```php
$user = User::find('1');                                       // by primary key
$user = User::where('email', 'jane@example.com')->first();    // single result
$users = User::where('active', 1)->get();                     // collection (returns [])

// Operators: =, !=, >, >=, <, <=, is, not, like
$users = User::where('dob', '>=', '1990-01-01')->get();
$users = User::where('name', 'like', '%jane%')->get();

// Unfiltered chain — use query() instead of where('id', '>', 0) hacks
$users = User::query()->orderBy('created_at', 'DESC')->get(10);
$stats = User::query()->select(['role', 'COUNT(*) as count'])->groupBy('role')->getRaw();
```

Static factories that start a chain: `query()`, `where()`, `whereIn()`, `find()`. Everything else (`andWhere`, `whereRaw`, `orderBy`, `orderByRaw`, `groupBy`, `groupByRaw`, `having`, `havingRaw`, `paginate`, `with`, ...) is instance-only and mutates the builder.

#### Or-Fail variants

`findOrFail()` and `firstOrFail()` throw `Echo\Framework\Database\ModelNotFoundException` when no row matches — use these in controller paths where a missing row is a 404, not a recoverable nil. The exception carries the model class and (for `findOrFail`) the lookup id so the handler can render a meaningful response without re-running the query.

```php
$user = User::findOrFail($id);                                  // throws on miss
$user = User::where('email', $email)->firstOrFail();            // throws on miss

try {
    $user = User::findOrFail($id);
} catch (ModelNotFoundException $e) {
    // $e->modelClass, $e->id
}
```

#### Existence checks

`exists()` short-circuits via `LIMIT 1` and is cheaper than `count() > 0` for "is there at least one?" questions. `doesntExist()` is the inverse.

```php
if (User::where('email', $email)->exists()) { /* ... */ }
if (BlogPost::where('slug', $slug)->doesntExist()) { /* 404 */ }
```

#### Scalar reads

`value(string $column)` returns a single column from the first matching row without hydrating the full model. `pluck(string $column)` returns a flat positionally-indexed array of one column's values across all matching rows. `keyBy(string $column)` returns hydrated models indexed by a column.

```php
$email = User::find($id)->value('email');               // null if no row
$ids   = User::where('active', 1)->pluck('id');         // [1, 4, 7, ...]
$byId  = User::whereIn('id', $ids)->keyBy('id');        // ['1' => User, '4' => User, ...]
```

`keyBy()` skips rows whose key column is null and lets later rows overwrite duplicates.

### Update

```php
// Mass assignment
$user->update(['first_name' => 'Alice', 'last_name' => 'Smith']);

// Property assignment
$user->first_name = 'Alice';
$user->save();
```

#### Atomic increment / decrement / touch

`increment()`, `decrement()`, and `touch()` issue a single `UPDATE` keyed by the row's primary key, avoiding the read-modify-write race that a plain `save()` would have. They **do not fire** `ModelUpdating`/`ModelUpdated` events — capturing old/new state would defeat the atomicity. Call `update()` or `save()` if you need events.

```php
$post->increment('view_count');             // SET view_count = view_count + 1
$post->decrement('credits', 5);             // SET credits = credits - 5
$user->touch();                              // SET updated_at = NOW()
```

All three return `false` when called on an unpersisted model (no id).

#### Change tracking

The model captures attribute state on load and exposes the diff. Useful in observers / event handlers / audit code that needs to react only to actual changes.

```php
$user = User::find(1);
$user->email = 'new@x.com';
$user->isDirty();              // true (any change)
$user->isDirty('email');       // true (specific column)
$user->isDirty('name');        // false
$user->getDirty();             // ['email' => 'new@x.com']
$user->getOriginal('email');   // 'old@x.com'
$user->isClean();              // false
```

`fresh()` returns a *new* instance with re-loaded data, leaving the current instance untouched. Use `refresh()` instead when you want to mutate the current instance in place.

```php
$reloaded = $user->fresh();    // new instance, same row
$user->refresh();              // mutate $user with reloaded data
```

#### Serialization

`toArray()` returns the model's attributes with any **already-loaded** relations folded in (it never triggers lazy loading). `toJson()` wraps `json_encode` over `toArray()`. The model also implements `JsonSerializable`, so `json_encode($model)` works directly.

```php
$post = Post::find(1)->load('author', 'tags');
$post->toArray();              // ['id' => 1, ..., 'author' => [...], 'tags' => [...]]
$post->toJson();
json_encode($post);            // uses jsonSerialize() under the hood
```

### Delete

```php
$user->delete();
```

## WHERE Clauses

```php
// Chaining
$users = User::where('status', 'active')
    ->andWhere('role', 'admin')
    ->get();

// OR
$users = User::where('role', 'admin')
    ->orWhere('role', 'superadmin')
    ->get();

// Null checks (chain after where() or other static entry point)
$users = User::where('active', 1)->whereNull('deleted_at')->get();
$users = User::where('active', 1)->whereNotNull('verified_at')->get();

// Between (inclusive) and its inverse
$users = User::where('active', 1)->whereBetween('created_at', '2025-01-01', '2025-12-31')->get();
$users = User::where('active', 1)->whereNotBetween('score', 0, 50)->get();

// Date / time function helpers (MySQL DATE/YEAR/MONTH/DAY/TIME)
$today = User::query()->whereDate('created_at', '2026-05-22')->get();
$ytd   = User::query()->whereDate('created_at', '>=', '2026-01-01')->get();
$thisMonth = User::query()->whereYear('created_at', 2026)
                          ->whereMonth('created_at', 5)
                          ->get();
$morning = User::query()->whereTime('created_at', '<', '12:00:00')->get();

// Raw WHERE
$users = User::where('active', 1)->whereRaw('YEAR(created_at) = ?', [2025])->get();

// WHERE IN — static factory or chained
$users = User::whereIn('id', [1, 2, 3])->get();
$users = User::where('active', 1)->andWhereIn('role', ['admin', 'editor'])->get();
$users = User::where('active', 1)->andWhereNotIn('role', ['banned'])->get();

// Empty array edge cases (Model-level, mirrors QueryBuilder)
User::whereIn('id', [])->get();                      // WHERE 0 = 1  → []
User::where('active', 1)->andWhereNotIn('id', [])    // WHERE active = ? AND 1 = 1
    ->get();
```

## Ordering, Grouping & Limiting

```php
$users = User::where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->get(10);                          // limit to 10

$first = User::orderBy('id', 'ASC')->first();
$last = User::orderBy('id', 'ASC')->last();     // reverses to get last

// latest() / oldest() — sugar for the common timestamp-ordering pattern
$recent = User::query()->latest()->get(10);                  // ORDER BY created_at DESC
$first  = BlogPost::where('status', 'published')->oldest('published_at')->first();

// Group by with custom select
$stats = User::where('active', 1)
    ->select(['role', 'COUNT(*) as count'])
    ->groupBy('role')
    ->getRaw();                         // returns raw arrays, not models
```

### Raw expressions in ORDER BY / GROUP BY

`orderBy` and `groupBy` only accept plain identifiers (validated against
`^[a-zA-Z_][\w.]*$`). For function calls, `COALESCE`, `RAND`, `FIELD`, `DATE`,
etc., use the raw variants — they skip identifier validation, so the input
must be trusted SQL.

```php
// COALESCE in ORDER BY (common for "fall back to created_at when null")
User::where('status', 'published')
    ->orderByRaw('COALESCE(published_at, created_at) DESC')
    ->get();

// Bindings work like whereRaw — they're appended positionally to params
User::where('status', 'active')
    ->orderByRaw('FIELD(role, ?, ?)', ['admin', 'user'])
    ->get();

// GROUP BY a derived value
$counts = Post::query()
    ->select(['YEAR(created_at) as yr', 'COUNT(*) as cnt'])
    ->groupByRaw('YEAR(created_at)')
    ->getRaw();
```

**Param ordering note.** Model collects bindings in the order methods are
called, while SQL is assembled `WHERE` → `HAVING` → `ORDER BY`. To keep
placeholders aligned, chain in that order: `where()` → `having()` →
`orderByRaw()`.

### HAVING

`having()` filters grouped rows. Each clause is wrapped in parens and joined
with `AND`, matching the `where()` convention. Replacements are appended
positionally to params.

```php
// Roles with more than 5 active users
User::where('active', 1)
    ->select(['role', 'COUNT(*) as cnt'])
    ->groupBy('role')
    ->having(['COUNT(*) > ?'], 5)
    ->getRaw();

// Multiple clauses, multiple bindings
User::where('active', 1)
    ->groupBy('role')
    ->having(['COUNT(*) > ?', 'AVG(score) >= ?'], 5, 80)
    ->getRaw();

// Free-form raw clause
User::where('active', 1)
    ->groupBy('role')
    ->havingRaw('COUNT(*) > AVG(score)')
    ->getRaw();
```

## Aggregates

```php
$count = User::where('active', 1)->count();
$total = User::countAll();                      // all rows

$maxId = User::where('role', 'admin')->max('id');
$maxId = User::maxAll('id');                    // across all rows

$minId = User::where('active', 1)->min('id');
$minId = User::minAll('id');

$totalScore = Track::where('artist', $name)->sum('play_count');
$totalScore = Track::sumAll('play_count');

$avgDuration = Track::where('genre', 'jazz')->avg('duration_ms');
$avgDuration = Track::avgAll('duration_ms');
```

`min/max/sum/avg/count` honor the current `where` chain; their `*All` static counterparts run against the unfiltered table. `sum` and `avg` return numeric strings from MySQL — cast at the call site if you need `int`/`float`.

## Pagination

`paginate(int $perPage, int $page = 1)` returns a single page of hydrated
models along with metadata, doing the `COUNT` and the `SELECT` for you. It
respects `where`, `orWhere`, `orderBy`/`orderByRaw`, and any `with()` eager
loading.

```php
$result = BlogPost::where('status', 'published')
    ->orderByRaw('COALESCE(published_at, created_at) DESC')
    ->with('author')
    ->paginate(perPage: 10, page: $page);

// Returned shape (plain array, Twig-friendly):
// [
//     'data'     => BlogPost[],   // the page of hydrated models
//     'total'    => int,          // total matching rows (ignores LIMIT/OFFSET)
//     'page'     => int,          // current page (clamped to >= 1)
//     'perPage'  => int,
//     'lastPage' => int,          // ceil(total / perPage); always >= 1
// ]
```

**Constraints.**
- `perPage` must be `>= 1` — otherwise throws `InvalidArgumentException`.
- `page < 1` is silently clamped to 1.
- `paginate()` does **not** support `groupBy()` or `having()` — it would need a
  subquery to count grouped rows correctly. For grouped pagination, compute
  the count manually and use `getRaw()` with `limit()`/`offset()` (exposed on
  QueryBuilder).

### Chunking

`chunk(int $size, callable $callback)` processes the query result in batches without loading the full set into memory. Each batch is passed as an array of hydrated models, plus the 1-based page number. Return `false` from the callback to stop iteration early.

Requires an explicit `orderBy()` — without one, the database is free to return rows in any order, making `LIMIT/OFFSET` unsafe across pages. Like `paginate()`, does not support `groupBy()`/`having()`.

```php
Track::query()
    ->orderBy('id')
    ->chunk(500, function (array $tracks, int $page) {
        foreach ($tracks as $track) {
            // ... process row
        }
        // return false; // would stop after this batch
    });

// Eager loading carries over to each batch
Post::query()
    ->orderBy('id')
    ->with('author')
    ->chunk(100, fn($posts) => syncBatch($posts));
```

Returns `true` if all batches were processed, `false` if the callback aborted.

## Conditional Query Building

`when($value, $callback, $default = null)` applies `$callback($this, $value)` only when `$value` is truthy — useful when controllers build queries from optional request filters. `unless()` is the inverse.

```php
$query = User::query()
    ->when($search, fn($q, $v) => $q->where('name', 'like', "%$v%"))
    ->when($role,   fn($q, $v) => $q->where('role', $v))
    ->when($status, fn($q, $v) => $q->where('status', $v),
                    fn($q)     => $q->where('status', 'active'));   // default branch

return $query->paginate(20, $page);
```

The callback can either return the chain (matches Laravel-style) or mutate `$this` in place and return nothing — both work.

## Relationships

Define relationships as methods on your model:

```php
class User extends Model
{
    protected string $tableName = 'users';

    public function posts(): array
    {
        return $this->hasMany(Post::class);         // users.id → posts.user_id
    }

    public function profile(): ?Profile
    {
        return $this->hasOne(Profile::class);       // users.id → profiles.user_id
    }
}

class Post extends Model
{
    protected string $tableName = 'posts';

    public function author(): ?User
    {
        return $this->belongsTo(User::class);       // posts.user_id → users.id
    }
}
```

Custom keys:

```php
$this->hasMany(Post::class, 'author_id', 'id');    // foreignKey, localKey
$this->belongsTo(User::class, 'author_id', 'id');  // foreignKey, ownerKey
```

### Lazy Access

Calling a relation method runs a query the first time and caches the result on the model. Subsequent calls reuse the cached value, including after eager loading populates the cache up front.

```php
$post = Post::find('42');
$post->author();   // 1 query
$post->author();   // cache hit, no query
```

### Eager Loading

`with()` is an instance method that flags relations for batched loading; `.get()` then fires a single `WHERE … IN (…)` per relation regardless of result set size.

```php
// Single-hop — 1 query for posts + 1 query for all authors batched
$posts = Post::query()->with('author')->get();
foreach ($posts as $post) {
    echo $post->author()->name;   // cache hit, no extra query
}

// Combine with WHERE / WHERE IN
$posts = Post::where('status', 'published')->with('author')->get();
$posts = Post::whereIn('id', [1, 2, 3])->with('author', 'tags')->get();
```

Eager loading is one query *per relation*, not per row. Loading three relations on 1,000 rows is 4 queries total.

### Nested Eager Loading

Dotted paths chain across multiple hops in a single declaration. Shared first segments are deduplicated — `with('author.profile', 'author.team')` runs one batched query for authors, then recurses with `[profile, team]` on those authors.

```php
$posts = Post::query()
    ->with('author.profile', 'tags')
    ->get();

foreach ($posts as $post) {
    $post->author()->profile()->bio;   // all cache hits
    foreach ($post->tags() as $tag) { /* ... */ }
}
```

Paths can go arbitrarily deep: `with('author.team.organization')` is three hops, four queries.

### load() — alias of with()

`load()` exists as a back-compat alias for `with()`. Identical behavior; pick whichever reads better at the call site.

```php
$posts = Post::query()->load('author')->get();   // same as with('author')
```

### Why with() is instance-only

There is **no** `Model::with(...)` static factory. This was a deliberate API choice — a static `with()` allowed the foot-gun `Model::with('x')->where(...)` where the subsequent static `where()` created a fresh builder and silently dropped the eager load. Chains must start with `query()`, `where()`, `whereIn()`, or `find()`, then add `with()` later. The broken spelling can no longer be written.

## Model Events

All CRUD operations dispatch events automatically:

| Operation | Before Event | After Event |
|---|---|---|
| `create()` | `ModelCreating` | `ModelCreated` |
| `save()`/`update()` | `ModelUpdating` | `ModelUpdated` |
| `delete()` | `ModelDeleting` | `ModelDeleted` |

"Before" events can be cancelled via `stopPropagation()` to prevent the operation. See [Events](events.md) for details.

## Query Debugging

```php
// Get SQL without executing
$info = User::where('active', 1)->orderBy('name')->sql();
// ['query' => 'SELECT * FROM users WHERE ...', 'params' => [1]]
```

## QueryBuilder

For complex queries (JOINs, subqueries, raw SQL), use the `qb()` helper:

```php
// SELECT with JOINs
$rows = qb()::select(['users.*', 'COUNT(posts.id) as post_count'])
    ->from('users')
    ->leftJoin('posts', 'posts.user_id = users.id')
    ->where(['users.active = ?'], 1)
    ->groupBy(['users.id'])
    ->orderBy(['post_count DESC'])
    ->limit(10)
    ->execute()
    ->fetchAll(PDO::FETCH_ASSOC);

// INSERT
qb()::insert(['name' => 'New Item', 'price' => 9.99])
    ->into('products')
    ->execute();

// UPDATE
qb()::update(['status' => 'inactive'])
    ->table('users')
    ->where(['last_login < ?'], '2024-01-01')
    ->execute();

// DELETE
qb()::delete()
    ->from('sessions')
    ->where(['expired_at < NOW()'])
    ->execute();
```

### JOINs

```php
// Typed joins
$qb->join('users u', 'u.id = orders.user_id');          // INNER JOIN
$qb->leftJoin('roles r', 'r.id = u.role_id');           // LEFT JOIN
$qb->rightJoin('payments p', 'p.order_id = orders.id'); // RIGHT JOIN
$qb->crossJoin('settings');                               // CROSS JOIN

// Raw SQL join (for complex join conditions)
$qb->joinRaw('LEFT JOIN users ON users.id = audits.user_id AND users.active = 1');

// Multiple joins
$rows = qb()::select(['o.id', 'u.name', 'p.amount'])
    ->from('orders o')
    ->join('users u', 'u.id = o.user_id')
    ->leftJoin('payments p', 'p.order_id = o.id')
    ->execute()
    ->fetchAll();
```

JOINs also work with UPDATE and DELETE queries (MySQL syntax).

### WHERE IN / NOT IN

```php
// Array of values
$qb->whereIn('status', ['active', 'pending']);       // WHERE status IN (?, ?)
$qb->whereNotIn('role', ['banned', 'suspended']);    // WHERE role NOT IN (?, ?)

// Subquery
$sub = qb()::select(['user_id'])->from('orders')->where(['total > ?'], 100);
$qb->whereIn('id', $sub);    // WHERE id IN (SELECT user_id FROM orders WHERE total > ?)

// Empty array edge cases
$qb->whereIn('id', []);      // WHERE 0 = 1 (always false)
$qb->whereNotIn('id', []);   // WHERE 1 = 1 (always true)
```

### DISTINCT

```php
$qb = qb()::select(['email'])->distinct()->from('users');
// SELECT DISTINCT email FROM users
```

### Raw Expressions

Use `QueryBuilder::raw()` to embed raw SQL where values would normally be parameterized:

```php
use Echo\Framework\Database\QueryBuilder;

// In SELECT
$qb = qb()::select([
    'id',
    QueryBuilder::raw("CONCAT(first_name, ' ', last_name) AS full_name"),
])->from('users');

// In INSERT (e.g. database functions)
qb()::insert([
    'name' => 'test',
    'created_at' => QueryBuilder::raw('NOW()'),
])->into('users')->execute();

// In UPDATE (e.g. increment)
qb()::update([
    'views' => QueryBuilder::raw('views + 1'),
])->table('posts')->where(['id = ?'], 42)->execute();
```

### Subqueries

```php
// Subquery in SELECT (correlated)
$sub = qb()::select(['COUNT(*)'])->from('orders')->where(['orders.user_id = users.id']);
$qb = qb()::select([
    'users.*',
    QueryBuilder::subquery($sub, 'order_count'),
])->from('users');
// SELECT users.*, (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) AS order_count FROM users

// Subquery in WHERE IN (see WHERE IN section above)
```

### Upsert (INSERT ... ON DUPLICATE KEY UPDATE)

```php
// Update specific columns with their inserted values
qb()::insert(['email' => 'a@b.com', 'name' => 'Test', 'login_count' => 1])
    ->into('users')
    ->onDuplicateKeyUpdate(['name', 'login_count'])
    ->execute();
// INSERT INTO ... ON DUPLICATE KEY UPDATE name = VALUES(name), login_count = VALUES(login_count)

// Custom update expression (e.g. increment)
qb()::insert(['email' => 'a@b.com', 'login_count' => 1])
    ->into('users')
    ->onDuplicateKeyUpdate([
        'login_count' => QueryBuilder::raw('login_count + 1'),
    ])
    ->execute();

// Update with a specific value
qb()::insert(['email' => 'a@b.com', 'name' => 'Test'])
    ->into('users')
    ->onDuplicateKeyUpdate(['name' => 'Updated Name'])
    ->execute();
```

### UNION / UNION ALL

```php
$q1 = qb()::select(['name', 'email'])->from('users')->where(['active = ?'], 1);
$q2 = qb()::select(['name', 'email'])->from('admins')->where(['active = ?'], 1);

// UNION (deduplicated)
$q1->union($q2)->execute();
// (SELECT ... FROM users WHERE active = ?) UNION (SELECT ... FROM admins WHERE active = ?)

// UNION ALL (keeps duplicates)
$q1->unionAll($q2)->execute();

// ORDER BY and LIMIT apply to the full union result
$q1->union($q2)->orderBy(['name ASC'])->limit(10)->execute();

// Multiple unions
$q3 = qb()::select(['name', 'email'])->from('guests');
$q1->union($q2)->unionAll($q3)->execute();
```

### Aggregate Helpers

Terminal methods that execute the query and return a scalar value:

```php
$count = qb()::select()->from('users')->where(['active = ?'], 1)->count();        // int
$total = qb()::select()->from('orders')->sum('total');        // float|int|null
$avg   = qb()::select()->from('orders')->avg('total');        // float|int|null
$min   = qb()::select()->from('orders')->min('created_at');   // mixed
$max   = qb()::select()->from('orders')->max('total');        // mixed

// With conditions
$revenue = qb()::select()
    ->from('orders')
    ->where(['status = ?'], 'completed')
    ->sum('total');
```

### QueryBuilder Methods Reference

| Method | Description |
|---|---|
| **Factory** | |
| `select(array $columns)` | Start SELECT query |
| `insert(array $data)` | Start INSERT query (values auto-bound) |
| `update(array $data)` | Start UPDATE query (values auto-bound) |
| `delete()` | Start DELETE query |
| `raw(string $sql, array $bindings)` | Create a raw SQL expression |
| `subquery(QueryBuilder $qb, string $alias)` | Create a subquery expression |
| **Table** | |
| `from(string $table)` | Table for SELECT/DELETE |
| `into(string $table)` | Table for INSERT |
| `table(string $table)` | Table for UPDATE |
| **JOINs** | |
| `join(string $table, string $on, string $type)` | Add a JOIN (default INNER) |
| `leftJoin(string $table, string $on)` | LEFT JOIN |
| `rightJoin(string $table, string $on)` | RIGHT JOIN |
| `crossJoin(string $table)` | CROSS JOIN (no ON) |
| `joinRaw(string $sql)` | Raw SQL JOIN clause |
| **WHERE** | |
| `where(array $clauses, ...$params)` | WHERE conditions (AND) |
| `orWhere(array $clauses, ...$params)` | OR WHERE conditions |
| `whereIn(string $col, array\|QB $values)` | WHERE IN |
| `whereNotIn(string $col, array\|QB $values)` | WHERE NOT IN |
| **Grouping & Ordering** | |
| `groupBy(array $columns)` | GROUP BY |
| `having(array $clauses, ...$params)` | HAVING clause |
| `orderBy(array $clauses)` | ORDER BY |
| `distinct()` | SELECT DISTINCT |
| **Pagination** | |
| `limit(int $n)` | LIMIT |
| `offset(int $n)` | OFFSET |
| **Upsert** | |
| `onDuplicateKeyUpdate(array $cols)` | ON DUPLICATE KEY UPDATE |
| **Union** | |
| `union(QueryBuilder $qb)` | UNION |
| `unionAll(QueryBuilder $qb)` | UNION ALL |
| **Execution** | |
| `params(array $params)` | Set WHERE clause parameters (SELECT/DELETE) |
| `execute()` | Execute, return PDOStatement |
| **Inspection** | |
| `getMode()` | Get query mode (select, insert, etc.) |
| `getQuery()` | Get the compiled SQL string |
| `getQueryParams()` | Get the bound parameter values |
| `dump()` | Get `['query' => ..., 'params' => ...]` |
| **Aggregates** (terminal) | |
| `count(string $col)` | COUNT, returns int |
| `sum(string $col)` | SUM, returns float\|int\|null |
| `avg(string $col)` | AVG, returns float\|int\|null |
| `min(string $col)` | MIN, returns mixed |
| `max(string $col)` | MAX, returns mixed |
