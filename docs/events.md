# Event System

Echo includes a PSR-14 inspired event system for decoupling cross-cutting concerns like logging, auditing, and notifications.

## Creating Events

Use the `make:event` command or create manually:

```bash
./bin/console make:event OrderPlaced
# Creates: app/Events/OrderPlaced.php
```

```php
namespace App\Events;

use Echo\Framework\Event\Event;

class OrderPlaced extends Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $customerEmail,
        public readonly float $total,
    ) {}
}
```

Events extend `Event` which implements `EventInterface`. The event name defaults to the fully-qualified class name.

## Creating Listeners

```bash
./bin/console make:listener SendOrderConfirmation --event=OrderPlaced
# Creates: app/Listeners/SendOrderConfirmation.php
```

```php
namespace App\Listeners;

use App\Events\OrderPlaced;
use Echo\Framework\Event\EventInterface;
use Echo\Framework\Event\ListenerInterface;

class SendOrderConfirmation implements ListenerInterface
{
    public function handle(EventInterface $event): void
    {
        if (!$event instanceof OrderPlaced) {
            return;
        }

        mailer()->send(
            Mailable::create()
                ->to($event->customerEmail)
                ->subject('Order Confirmed')
                ->template('emails/order-confirmed.html.twig', [
                    'orderId' => $event->orderId,
                    'total' => $event->total,
                ])
        );
    }
}
```

## Registering Listeners

Map events to listeners in `app/Providers/EventServiceProvider.php`:

```php
namespace App\Providers;

use Echo\Framework\Event\EventServiceProvider as BaseEventServiceProvider;
use App\Events\OrderPlaced;
use App\Listeners\SendOrderConfirmation;

class EventServiceProvider extends BaseEventServiceProvider
{
    protected array $listen = [
        OrderPlaced::class => [
            SendOrderConfirmation::class,
        ],
    ];
}
```

Multiple listeners can be registered for the same event. They execute in registration order by default, or by priority.

## Dispatching Events

Use the `event()` helper anywhere in your application:

```php
event(new OrderPlaced(
    orderId: $order->id,
    customerEmail: $order->email,
    total: $order->total
));
```

The helper returns the event instance, which may have been modified by listeners.

## Listener Priority

Lower priority values execute first:

```php
$dispatcher = container()->get(EventDispatcherInterface::class);

$dispatcher->listen(OrderPlaced::class, AuditListener::class, priority: 1);    // first
$dispatcher->listen(OrderPlaced::class, EmailListener::class, priority: 10);   // second
```

## Stopping Propagation

Listeners can stop further listeners from executing:

```php
public function handle(EventInterface $event): void
{
    if ($this->shouldBlock($event)) {
        $event->stopPropagation();    // remaining listeners are skipped
    }
}
```

## Built-in Events

### Model Lifecycle Events

Dispatched automatically by the ORM during CRUD operations:

| Event | When | Properties |
|---|---|---|
| `ModelCreating` | Before insert | `modelClass`, `attributes` |
| `ModelCreated` | After insert | `model`, `attributes` |
| `ModelUpdating` | Before update | `model`, `oldAttributes`, `newAttributes` |
| `ModelUpdated` | After update | `model`, `oldAttributes`, `newAttributes` |
| `ModelDeleting` | Before delete | `model`, `attributes` |
| `ModelDeleted` | After delete | `modelClass`, `modelId`, `attributes` |

"Before" events (`ModelCreating`, `ModelUpdating`, `ModelDeleting`) can be cancelled via `stopPropagation()` to prevent the operation.

### HTTP Events

| Event | When | Properties |
|---|---|---|
| `RequestReceived` | HTTP request received | `request` |
| `ResponseSending` | Before response is sent | `request`, `response` |

### Auth Events

| Event | When | Properties |
|---|---|---|
| `UserSignedIn` | After successful login | `user`, `ip` |
| `SignInFailed` | After failed login | `email`, `ip`, `reason` |
| `UserSignedOut` | After logout | `userId`, `email`, `ip` |
| `UserRegistered` | After registration | `user`, `ip` |
| `PasswordResetRequested` | Reset token generated | `user`, `ip` |
| `PasswordResetCompleted` | Password changed | `user`, `ip` |

## Default Event Registration

The framework ships with these listener registrations:

```php
protected array $listen = [
    // Audit logging for model changes
    ModelCreated::class => [AuditListener::class],
    ModelUpdated::class => [AuditListener::class],
    ModelDeleted::class => [AuditListener::class],

    // HTTP activity logging
    RequestReceived::class => [ActivityListener::class],

    // Auth event logging
    UserSignedIn::class => [AuthListener::class],
    SignInFailed::class => [AuthListener::class],
    UserSignedOut::class => [AuthListener::class],
    UserRegistered::class => [AuthListener::class],
    PasswordResetRequested::class => [AuthListener::class],
    PasswordResetCompleted::class => [AuthListener::class],
];
```

## Architecture

- **`EventInterface`** — contract for all events (`getName()`, `isPropagationStopped()`)
- **`Event`** — base class with `stopPropagation()` support
- **`ListenerInterface`** — contract for listeners (`handle(EventInterface)`)
- **`EventDispatcherInterface`** — dispatcher contract (`dispatch()`, `listen()`, `hasListeners()`, `getListeners()`, `forget()`)
- **`EventDispatcher`** — implementation with priority sorting and lazy DI resolution
- **`EventServiceProvider`** — base provider for registering event-listener mappings
