# Routing

Echo uses PHP 8 attributes on controller methods to define routes. Controllers in `app/Http/Controllers/` are auto-discovered.

## Route Attributes

Available route types: `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`.

```php
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Route\{Get, Post, Put, Delete};

class ProductController extends Controller
{
    #[Get("/products", "products.index")]
    public function index(): string
    {
        return $this->render('products/index.html.twig');
    }

    #[Get("/products/{id}", "products.show")]
    public function show(string $id): string
    {
        $product = Product::find($id);
        return $this->render('products/show.html.twig', ['product' => $product]);
    }

    #[Post("/products", "products.store")]
    public function store(): string
    {
        $data = $this->validate([
            'name' => 'required',
            'price' => 'required|numeric',
        ]);
        Product::create($data);
        return redirect('/products');
    }

    #[Put("/products/{id}", "products.update")]
    public function update(string $id): string { /* ... */ }

    #[Delete("/products/{id}", "products.destroy")]
    public function destroy(string $id): string { /* ... */ }
}
```

Each attribute takes:
- `path` — the URL pattern (supports `{param}` placeholders)
- `name` — a unique route name for URL generation
- `middleware` (optional) — array of middleware names

## Route Groups

Use the `#[Group]` attribute on a controller class to apply a shared prefix, name prefix, and middleware:

```php
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/admin/products", namePrefix: "admin.products", middleware: ["auth"])]
class AdminProductController extends Controller
{
    #[Get("/", "index")]       // path: /admin/products, name: admin.products.index
    public function index(): string { /* ... */ }

    #[Get("/{id}", "show")]    // path: /admin/products/{id}, name: admin.products.show
    public function show(string $id): string { /* ... */ }
}
```

## Subdomain Routing

Routes can be constrained to specific subdomains using the `subdomain` parameter:

```php
// All routes match only api.example.com
#[Group(pathPrefix: '/v1', namePrefix: 'api', subdomain: 'api', middleware: ['api'])]
abstract class ApiController extends Controller { }

// Wildcard subdomain for multi-tenancy — captures subdomain as first param
#[Group(subdomain: '{tenant}')]
class TenantController extends Controller
{
    #[Get('/dashboard', 'dashboard')]
    public function dashboard(string $tenant): string
    {
        return $this->render('tenant/dashboard.html.twig', ['tenant' => $tenant]);
    }
}
```

For local Docker testing, add subdomains to `/etc/hosts`:

```bash
127.0.0.1 localhost api.localhost tenant1.localhost
```

## URL Generation

Use the `uri()` helper for path-only URLs, or `url()` for full URLs (with scheme/host when crossing subdomains):

```php
$path = uri('products.show', $id);    // "/products/42"
$full = url('api.status');            // "http://api.localhost/v1/status"
```

## Route Caching

Routes are cached for performance. Use console commands to manage:

```bash
./bin/console route:list     # list all registered routes
./bin/console route:cache    # cache routes
./bin/console route:clear    # clear route cache
```

## Middleware

Middleware is applied per-route or per-group via the `middleware` parameter. Middleware classes are defined in `app/Http/Kernel.php` as `$middlewareLayers`.

```php
// On a single route
#[Get("/dashboard", "dashboard", middleware: ["auth"])]
public function dashboard(): string { /* ... */ }

// On a group (applies to all routes in the controller)
#[Group(pathPrefix: "/admin", middleware: ["auth"])]
class AdminController extends Controller { /* ... */ }
```
