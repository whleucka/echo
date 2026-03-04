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
$users = User::where('active', 1)->get();                     // collection

// Operators: =, !=, >, >=, <, <=, is, not, like
$users = User::where('dob', '>=', '1990-01-01')->get();
$users = User::where('name', 'like', '%jane%')->get();
```

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

// Null checks
$users = User::whereNull('deleted_at')->get();
$users = User::whereNotNull('verified_at')->get();

// Between
$users = User::whereBetween('created_at', '2025-01-01', '2025-12-31')->get();

// Raw WHERE
$users = User::whereRaw('YEAR(created_at) = ?', [2025])->get();
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

## Aggregates

```php
$count = User::where('active', 1)->count();
$total = User::countAll();                      // all rows
$maxId = User::where('role', 'admin')->max('id');
$maxId = User::maxAll('id');                    // across all rows
```

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

### Eager Loading

```php
// Eager load during query (avoids N+1)
$users = User::with('posts', 'profile')
    ->where('active', 1)
    ->get();

// Access eager-loaded relations
foreach ($users as $user) {
    $posts = $user->getRelation('posts');
}

// Lazy eager load on existing query
$users = User::where('active', 1)->load('posts')->get();
```

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
// ['query' => 'SELECT * FROM users WHERE active = ? ORDER BY name ASC LIMIT 1', 'params' => [1]]
```

## QueryBuilder

For complex queries (JOINs, subqueries, raw SQL), use the `qb()` helper:

```php
// SELECT
$rows = qb()::select(['users.*', 'COUNT(posts.id) as post_count'])
    ->from('users')
    ->where(['users.active = ?', 'posts.published = ?'], 1, 1)
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

### QueryBuilder Methods

| Method | Description |
|---|---|
| `select(array $columns)` | Start SELECT query |
| `insert(array $data)` | Start INSERT query |
| `update(array $data)` | Start UPDATE query |
| `delete()` | Start DELETE query |
| `from(string $table)` | Table for SELECT/DELETE |
| `into(string $table)` | Table for INSERT |
| `table(string $table)` | Table for UPDATE |
| `where(array $clauses, ...$params)` | WHERE conditions |
| `orWhere(array $clauses, ...$params)` | OR WHERE conditions |
| `having(array $clauses, ...$params)` | HAVING clause |
| `groupBy(array $columns)` | GROUP BY |
| `orderBy(array $clauses)` | ORDER BY |
| `limit(int $n)` | LIMIT |
| `offset(int $n)` | OFFSET |
| `execute()` | Execute and return PDOStatement |
| `dump()` | Get `['query' => ..., 'params' => ...]` |
