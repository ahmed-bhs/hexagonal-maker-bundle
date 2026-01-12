---
layout: default
title: Dependency Injection Configuration
parent: Advanced Topics
nav_order: 15
lang: en
lang_ref: fr/advanced/injection-dependances-guide.md
---

# Dependency Injection Configuration Guide

## Table of Contents

1. [Overview](#overview)
2. [Symfony Autowiring Basics](#symfony-autowiring-basics)
3. [Binding Ports to Adapters](#binding-ports-to-adapters)
4. [Environment-Specific Bindings](#environment-specific-bindings)
5. [Tagged Services](#tagged-services)
6. [Service Decoration](#service-decoration)
7. [Complete Configuration Examples](#complete-configuration-examples)
8. [Troubleshooting](#troubleshooting)

---

## Overview

In hexagonal architecture, **Dependency Injection (DI)** is critical for binding ports (interfaces) to adapters (implementations). Symfony's DI container handles this wiring automatically with proper configuration.

### The Problem DI Solves

```php
// ❌ Without DI: Hard-coded dependency
class RegisterUserHandler
{
    public function __invoke(RegisterUserCommand $command): void
    {
        $repository = new DoctrineUserRepository(); // ❌ Tight coupling!
        // ...
    }
}

// ✅ With DI: Injected dependency
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users // ✅ Interface, not implementation
    ) {}

    public function __invoke(RegisterUserCommand $command): void
    {
        $this->users->save(...); // Uses whatever implementation is configured
    }
}
```

**DI Container Configuration:** Tells Symfony "when someone needs `UserRepositoryInterface`, give them `DoctrineUserRepository`".

---

## Symfony Autowiring Basics

### What is Autowiring?

**Autowiring:** Symfony automatically resolves constructor dependencies by looking at type hints.

```php
// Handler with type-hinted dependencies
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,      // Autowired
        private EmailSenderInterface $emailSender,   // Autowired
        private EventDispatcherInterface $events,    // Autowired
    ) {}
}
```

Symfony sees these type hints and automatically provides the correct services.

---

### Enabling Autowiring

In `config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true      # Enable autowiring
        autoconfigure: true # Automatically configure services (tags, etc.)
        public: false       # Services are private by default

    # Auto-register all classes in src/ as services
    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/*/Domain/Model/'      # Exclude entities
            - '../src/*/Domain/ValueObject/' # Exclude value objects
            - '../src/Kernel.php'
```

**How it works:**
1. Symfony scans `src/` directory
2. Registers each class as a service
3. Autowires constructor dependencies

---

## Binding Ports to Adapters

### The Core Configuration

**Problem:** Interface cannot be instantiated—Symfony needs to know which implementation to use.

```php
// Handler needs UserRepositoryInterface
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users // ❌ Interface, cannot be instantiated!
    ) {}
}
```

**Solution:** Bind interface to implementation in `services.yaml`.

---

### Method 1: Direct Binding (Simplest)

```yaml
services:
    # Bind interface → implementation
    App\User\Domain\Port\UserRepositoryInterface:
        class: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

**Explanation:**
- When someone needs `UserRepositoryInterface`
- Symfony provides `DoctrineUserRepository`

---

### Method 2: Alias (Recommended)

```yaml
services:
    # Implementation
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
        # Autowired by default

    # Alias: interface → implementation
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

**Benefits:**
- More explicit
- Easier to change implementations

---

### Method 3: Bind for All Services

```yaml
services:
    _defaults:
        bind:
            # Automatically bind this interface to this implementation
            # for ALL services
            App\User\Domain\Port\UserRepositoryInterface: '@App\User\Infrastructure\Persistence\DoctrineUserRepository'
```

**When to use:** Interface used in many places, avoid repeating configuration.

---

## Environment-Specific Bindings

### The Problem: Different Implementations for Different Environments

- **Development:** Use in-memory repository (fast tests)
- **Test:** Use in-memory repository
- **Production:** Use Doctrine repository (real database)

---

### Solution: Environment-Specific Configuration

#### 1. Main Configuration (`config/services.yaml`)

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Register all implementations
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
    App\User\Infrastructure\Persistence\InMemoryUserRepository:

    # Default binding (production)
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

---

#### 2. Test Configuration (`config/services_test.yaml`)

```yaml
services:
    # Override binding for test environment
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\InMemoryUserRepository
```

**Result:**
- `bin/phpunit` → uses `InMemoryUserRepository`
- Production → uses `DoctrineUserRepository`

---

#### 3. Development Configuration (`config/services_dev.yaml`)

```yaml
# Optional: use in-memory for fast dev feedback
services:
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\InMemoryUserRepository

    # Or enable debug logging
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        arguments:
            $decorated: '@.inner'
            $logger: '@logger'
```

---

### Complete Example: Email Sender

```yaml
# config/services.yaml (default: production)
services:
    # Production: real SMTP
    App\Notification\Domain\Port\EmailSenderInterface:
        alias: App\Notification\Infrastructure\Email\SymfonyEmailSender

# config/services_test.yaml
services:
    # Test: in-memory fake
    App\Notification\Domain\Port\EmailSenderInterface:
        alias: App\Notification\Infrastructure\Email\InMemoryEmailSender

# config/services_dev.yaml
services:
    # Dev: log emails instead of sending
    App\Notification\Domain\Port\EmailSenderInterface:
        alias: App\Notification\Infrastructure\Email\LoggingEmailSender
```

---

## Tagged Services

### The Problem: Multiple Implementations of Same Interface

**Example:** Multiple event subscribers for the same event.

```php
interface EventSubscriberInterface
{
    public function handle(DomainEvent $event): void;
}

class SendEmailSubscriber implements EventSubscriberInterface { /* ... */ }
class LogEventSubscriber implements EventSubscriberInterface { /* ... */ }
class UpdateCacheSubscriber implements EventSubscriberInterface { /* ... */ }
```

**Need:** Inject all implementations, not just one.

---

### Solution: Tagged Services

#### 1. Tag All Implementations

```yaml
services:
    # Tag each implementation
    App\Notification\Infrastructure\Event\SendEmailSubscriber:
        tags: ['app.event_subscriber']

    App\Notification\Infrastructure\Event\LogEventSubscriber:
        tags: ['app.event_subscriber']

    App\Notification\Infrastructure\Event\UpdateCacheSubscriber:
        tags: ['app.event_subscriber']
```

---

#### 2. Inject All Tagged Services

```yaml
services:
    # Event dispatcher receives all subscribers
    App\Shared\Infrastructure\Event\EventDispatcher:
        arguments:
            $subscribers: !tagged_iterator app.event_subscriber
```

---

#### 3. Use in Service

```php
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        #[TaggedIterator('app.event_subscriber')]
        private iterable $subscribers // All tagged services injected here
    ) {}

    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->subscribers as $subscriber) {
            $subscriber->handle($event);
        }
    }
}
```

---

### Auto-Tagging with Interfaces

**Automatic tagging:** Tag all classes implementing an interface.

```yaml
services:
    _instanceof:
        # Automatically tag all classes implementing EventSubscriberInterface
        App\Shared\Domain\Event\EventSubscriberInterface:
            tags: ['app.event_subscriber']
```

**Now you don't need to manually tag each implementation!**

---

### Complete Example: Query Bus with Multiple Handlers

```yaml
services:
    _instanceof:
        # Auto-tag all query handlers
        App\Shared\Application\Query\QueryHandlerInterface:
            tags: ['app.query_handler']

    # Query bus receives all handlers
    App\Shared\Infrastructure\Query\QueryBus:
        arguments:
            $handlers: !tagged_iterator app.query_handler
```

```php
class QueryBus
{
    public function __construct(
        #[TaggedIterator('app.query_handler')]
        private iterable $handlers
    ) {}

    public function dispatch(Query $query): mixed
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($query)) {
                return $handler->handle($query);
            }
        }

        throw new NoHandlerFoundException();
    }
}
```

---

## Service Decoration

### The Problem: Add Functionality Without Modifying Code

**Example:** Add caching to repository without changing repository code.

---

### Solution: Decorator Pattern with DI

#### 1. Create Decorator

```php
namespace App\User\Infrastructure\Persistence;

final readonly class CachedUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private UserRepositoryInterface $decorated, // Original repository
        private CacheInterface $cache,
    ) {}

    public function findById(UserId $id): ?User
    {
        return $this->cache->get(
            "user:{$id}",
            fn() => $this->decorated->findById($id) // Delegate to original
        );
    }

    public function save(User $user): void
    {
        $this->decorated->save($user);
        $this->cache->delete("user:{$user->getId()}"); // Invalidate cache
    }
}
```

---

#### 2. Configure Decoration

```yaml
services:
    # Original repository
    App\User\Infrastructure\Persistence\DoctrineUserRepository:

    # Decorator wraps original
    App\User\Infrastructure\Persistence\CachedUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        arguments:
            $decorated: '@.inner' # @.inner = the decorated service
```

**Result:**
- Anyone requesting `DoctrineUserRepository` gets `CachedUserRepository`
- `CachedUserRepository` wraps `DoctrineUserRepository`
- Caching added transparently

---

### Decoration Priority

**Multiple decorators:**

```yaml
services:
    App\User\Infrastructure\Persistence\DoctrineUserRepository:

    # First decorator: caching
    App\User\Infrastructure\Persistence\CachedUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        decoration_priority: 10 # Higher priority = outer layer
        arguments:
            $decorated: '@.inner'

    # Second decorator: logging
    App\User\Infrastructure\Persistence\LoggingUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        decoration_priority: 5 # Lower priority = inner layer
        arguments:
            $decorated: '@.inner'
```

**Call chain:**
```
Handler → LoggingUserRepository → CachedUserRepository → DoctrineUserRepository → Database
```

---

## Complete Configuration Examples

### Example 1: User Module

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Auto-register all services
    App\:
        resource: '../src/'
        exclude:
            - '../src/*/Domain/Model/'
            - '../src/*/Domain/ValueObject/'
            - '../src/Kernel.php'

    # Bind ports to adapters
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository

    App\User\Domain\Port\PasswordHasherInterface:
        alias: App\User\Infrastructure\Security\SymfonyPasswordHasher

    App\Shared\Domain\Port\EmailSenderInterface:
        alias: App\Shared\Infrastructure\Email\SymfonyEmailSender

    App\Shared\Domain\Port\EventDispatcherInterface:
        alias: App\Shared\Infrastructure\Event\SymfonyEventDispatcher
```

---

### Example 2: Order Module with CQRS

```yaml
services:
    # Write side
    App\Order\Domain\Port\OrderRepositoryInterface:
        alias: App\Order\Infrastructure\Persistence\DoctrineOrderRepository

    # Read side (CQRS)
    App\Order\Application\Query\OrderQueryInterface:
        alias: App\Order\Infrastructure\Query\SqlOrderQuery

    # External services
    App\Order\Domain\Port\PaymentProcessorInterface:
        alias: App\Order\Infrastructure\Payment\StripePaymentProcessor

    App\Order\Domain\Port\InventoryServiceInterface:
        alias: App\Order\Infrastructure\Inventory\HttpInventoryService
```

---

### Example 3: Multi-Tenant Application

```yaml
services:
    # Tenant resolver
    App\Shared\Infrastructure\Tenancy\TenantResolver:

    # Tenant-aware repository
    App\User\Infrastructure\Persistence\TenantAwareUserRepository:
        arguments:
            $tenantResolver: '@App\Shared\Infrastructure\Tenancy\TenantResolver'

    # Bind interface to tenant-aware implementation
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\TenantAwareUserRepository
```

---

### Example 4: Decorated Repository with Caching & Logging

```yaml
services:
    # Base repository
    App\Product\Infrastructure\Persistence\DoctrineProductRepository:

    # Decorator: caching
    App\Product\Infrastructure\Persistence\CachedProductRepository:
        decorates: App\Product\Infrastructure\Persistence\DoctrineProductRepository
        decoration_priority: 10
        arguments:
            $decorated: '@.inner'
            $cache: '@cache.app'

    # Decorator: logging
    App\Product\Infrastructure\Persistence\LoggingProductRepository:
        decorates: App\Product\Infrastructure\Persistence\DoctrineProductRepository
        decoration_priority: 5
        arguments:
            $decorated: '@.inner'
            $logger: '@logger'

    # Bind interface to base (decorators wrap it automatically)
    App\Product\Domain\Port\ProductRepositoryInterface:
        alias: App\Product\Infrastructure\Persistence\DoctrineProductRepository
```

**Call chain:** `Handler → Logging → Caching → Doctrine → DB`

---

## Troubleshooting

### Issue 1: "Cannot autowire service: argument is type-hinted with interface"

**Error:**
```
Cannot autowire service "App\User\Application\Handler\RegisterUserHandler":
argument "$users" of method "__construct()" is type-hinted with the interface
"App\User\Domain\Port\UserRepositoryInterface" but no implementation is registered.
```

**Solution:** Bind interface to implementation.

```yaml
services:
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

---

### Issue 2: "Service not found"

**Error:**
```
Service "App\User\Infrastructure\Persistence\DoctrineUserRepository" not found.
```

**Solution:** Check that directory is not excluded in `services.yaml`.

```yaml
services:
    App\:
        resource: '../src/'
        exclude:
            - '../src/*/Domain/Model/'  # ✅ Exclude entities
            # ❌ Don't exclude Infrastructure!
```

---

### Issue 3: "Circular reference detected"

**Error:**
```
Circular reference detected for service "App\User\Infrastructure\Persistence\DoctrineUserRepository".
```

**Solution:** Refactor to remove circular dependency or use setter injection.

```yaml
services:
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
        calls:
            - setLogger: ['@logger'] # Setter injection instead of constructor
```

---

### Issue 4: "Wrong implementation injected in tests"

**Problem:** Test uses production implementation instead of fake.

**Solution:** Create `config/services_test.yaml` and override binding.

```yaml
# config/services_test.yaml
services:
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\InMemoryUserRepository
```

---

### Issue 5: Tagged services not injected

**Error:** `$subscribers` is empty even though services are tagged.

**Solution:** Check tag name matches.

```yaml
services:
    # Tag definition
    _instanceof:
        App\Shared\Domain\Event\EventSubscriberInterface:
            tags: ['app.event_subscriber'] # Tag name

    # Injection (must match!)
    App\Shared\Infrastructure\Event\EventDispatcher:
        arguments:
            $subscribers: !tagged_iterator app.event_subscriber # Same tag name
```

---

## Best Practices

### 1. Use Aliases for Port Bindings

✅ **GOOD:**
```yaml
App\User\Domain\Port\UserRepositoryInterface:
    alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

❌ **AVOID:**
```yaml
App\User\Domain\Port\UserRepositoryInterface:
    class: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

**Reason:** Aliases are clearer and easier to override.

---

### 2. Environment-Specific Configuration Files

```
config/
├── services.yaml          # Default (production)
├── services_dev.yaml      # Development overrides
├── services_test.yaml     # Test overrides
└── services_prod.yaml     # Production-specific (optional)
```

---

### 3. Use `_instanceof` for Auto-Tagging

✅ **GOOD:**
```yaml
_instanceof:
    App\Shared\Application\Query\QueryHandlerInterface:
        tags: ['app.query_handler']
```

❌ **AVOID:**
```yaml
App\User\Application\Query\FindUserQueryHandler:
    tags: ['app.query_handler']

App\Order\Application\Query\FindOrderQueryHandler:
    tags: ['app.query_handler']

# ... manually tag each one
```

---

### 4. Exclude Non-Service Classes

```yaml
App\:
    resource: '../src/'
    exclude:
        - '../src/*/Domain/Model/'       # Entities
        - '../src/*/Domain/ValueObject/' # Value objects
        - '../src/*/Application/Command/' # DTOs
        - '../src/*/Application/Query/'   # DTOs
        - '../src/Kernel.php'
```

**Reason:** Only services should be registered, not data objects.

---

## Summary Cheat Sheet

| Task | Configuration |
|------|---------------|
| Bind port to adapter | `alias: App\...\Implementation` |
| Inject all tagged services | `!tagged_iterator tag_name` |
| Auto-tag by interface | `_instanceof: { Interface: tags: [...] }` |
| Override for test | Create `services_test.yaml` |
| Decorate service | `decorates: OriginalService` + `$decorated: '@.inner'` |
| Exclude directory | `exclude: ['../src/Path/']` |

---

**Next:** [Factory Pattern Implementation →](./factory-pattern-guide.md)
