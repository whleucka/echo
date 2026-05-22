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
```

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

### Update

```php
// Mass assignment
$user->update(['first_name' => 'Alice', 'last_name' => 'Smith']);

// Property assignment
$user->first_name = 'Alice';
$user->save();
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

// Between
$users = User::where('active', 1)->whereBetween('created_at', '2025-01-01', '2025-12-31')->get();

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
```

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
