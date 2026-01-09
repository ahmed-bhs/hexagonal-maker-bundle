# FAQ - Frequently Asked Questions

Quick answers to common questions about Hexagonal Maker Bundle.

---

## Installation & Setup

### How do I install the bundle?

```bash
composer require ahmed-bhs/hexagonal-maker-bundle --dev
```

The bundle auto-registers with Symfony Flex.

### What are the requirements?

- PHP 8.1+
- Symfony 6.4+ or 7.x
- Doctrine ORM (for persistence)

### Do I need to configure anything?

Yes! You must configure Doctrine YAML mappings for each module. See [Installation Guide](../getting-started/installation.md).

---

## Usage

### How do I generate my first module?

```bash
bin/console make:hexagonal:entity user/account User
bin/console make:hexagonal:repository user/account User
bin/console make:hexagonal:command user/account create
```

[See Quick Start →](../getting-started/quick-start.md)

### Can I generate a complete CRUD module?

Yes! Use the CRUD maker:

```bash
bin/console make:hexagonal:crud blog/post Post --with-tests
```

This generates 30+ files including all layers, use cases, controllers, and tests.

### How many maker commands are available?

**19 specialized makers** covering all hexagonal layers:

- Domain (Entity, ValueObject, Exception)
- Application (Command, Query, UseCase)
- Infrastructure (Repository, MessageHandler)
- UI (Controller, Form, CLI Command)
- Tests (UseCase Test, Controller Test)
- Rapid (CRUD)

[See all commands →](../makers/commands.md)

---

## Architecture

### Why use hexagonal architecture?

Benefits:

- **Pure Domain** - Business logic independent of frameworks
- **Easy Testing** - Unit tests run 1000x faster (no database)
- **Technology Freedom** - Swap databases/frameworks easily
- **Maintainability** - Clear separation of concerns

[Read full guide →](../WHY-HEXAGONAL.md)

### What's the difference between hexagonal and clean architecture?

They're very similar:
- **Hexagonal** (Alistair Cockburn) - Focuses on ports & adapters
- **Clean** (Uncle Bob) - Emphasizes dependency rule

Both achieve the same goal: domain independence.

### Why YAML mapping instead of annotations?

**Pure domain principle:**

Annotations pollute domain entities with infrastructure concerns (Doctrine). YAML keeps domain 100% pure PHP.

```php
// ✅ Pure domain - no dependencies
final class User
{
    private string $id;
    private string $email;
}
```

vs

```php
// ❌ Domain depends on Doctrine
#[ORM\Entity]
final class User
{
    #[ORM\Id]
    private string $id;
}
```

---

## CQRS

### What is CQRS?

**C**ommand **Q**uery **R**esponsibility **S**egregation

- **Commands** - Change state (Create, Update, Delete)
- **Queries** - Read data (Find, List)

Benefits:
- Clear separation of write/read logic
- Easier to optimize queries
- Better scalability

### When should I use Commands vs UseCases?

**Commands (CQRS):**
- When using Symfony Messenger
- For async processing
- When you want message-based architecture

**UseCases:**
- Simple synchronous operations
- Direct service calls
- When you don't need messaging

**Both are valid!** Choose based on your needs.

### How do Commands work with Messenger?

Commands are dispatched via Symfony Messenger:

```php
// 1. Define command (DTO)
final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}

// 2. Handle with #[AsMessageHandler]
#[AsMessageHandler]
final readonly class CreateUserCommandHandler
{
    public function __invoke(CreateUserCommand $command): void
    {
        // Business logic here
    }
}

// 3. Dispatch from controller
$this->messageBus->dispatch(new CreateUserCommand($email, $password));
```

---

## Doctrine Integration

### Where do I configure YAML mappings?

In `config/packages/doctrine.yaml`:

```yaml
doctrine:
    orm:
        mappings:
            UserAccount:
                type: yml
                dir: '%kernel.project_dir%/src/User/Account/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\User\Account\Domain\Model'
```

Add one mapping per module.

### Can I use annotations instead of YAML?

Technically yes, but **not recommended**. It breaks the pure domain principle.

### How do I handle Value Objects in Doctrine?

Use `embedded` in YAML mapping:

```yaml
# Email.orm.yml
App\Domain\ValueObject\Email:
    type: embeddable
    fields:
        value:
            type: string
            length: 180

# User.orm.yml
App\Domain\Model\User:
    embedded:
        email:
            class: App\Domain\ValueObject\Email
            columnPrefix: email_
```

### Can I use Gedmo extensions (Timestampable, Sluggable)?

Yes! Configure them in YAML to keep domain pure:

```yaml
fields:
    createdAt:
        type: datetime_immutable
        gedmo:
            timestampable:
                on: create
```

[See Gedmo guide →](../advanced/doctrine.md#gedmo-extensions)

---

## Testing

### How do I test use cases?

Generate tests with `--with-tests`:

```bash
bin/console make:hexagonal:command blog/post create --with-tests
```

Or use the test maker:

```bash
bin/console make:hexagonal:use-case-test blog/post CreatePost
```

### Unit tests vs Integration tests?

**Unit Tests:**
- Test business logic in isolation
- Use mocks for dependencies
- Run extremely fast (milliseconds)

**Integration Tests:**
- Test full stack with real database
- Use real Symfony container
- Slower but more realistic

**Both are generated!**

### How do I run tests?

```bash
# All tests
vendor/bin/phpunit

# Specific test
vendor/bin/phpunit tests/Blog/Post/Application/CreatePost/CreatePostTest.php

# With coverage
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage
```

---

## Customization

### Can I customize generated templates?

Yes! Create custom templates in `config/skeleton/`:

```yaml
# config/packages/hexagonal_maker.yaml
hexagonal_maker:
    skeleton_dir: '%kernel.project_dir%/config/skeleton'
```

[See templates guide →](../advanced/templates.md)

### Can I change the root namespace?

Yes, in configuration:

```yaml
hexagonal_maker:
    root_namespace: 'MyApp'
```

### Can I organize modules differently?

Yes! Use the module path parameter:

```bash
# Default: src/Blog/Post/
bin/console make:hexagonal:entity blog/post Post

# Custom: src/Module/Blog/Post/
bin/console make:hexagonal:entity module/blog/post Post

# Flat: src/BlogPost/
bin/console make:hexagonal:entity blog-post Post
```

---

## Advanced

### How do I implement Domain Events?

```bash
# Generate event + subscriber
bin/console make:hexagonal:domain-event order/payment OrderPlaced --with-subscriber
```

Dispatch from entity:

```php
class Order
{
    private array $events = [];

    public function place(): void
    {
        $this->status = 'placed';
        $this->events[] = new OrderPlacedEvent($this->id);
    }

    public function pullDomainEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
}
```

### Can I use async processing?

Yes! Generate message handlers:

```bash
bin/console make:hexagonal:message-handler user/account SendWelcomeEmail --with-message
```

Configure in `messenger.yaml`:

```yaml
framework:
    messenger:
        routing:
            'App\User\Account\Application\Message\*': async
```

### How do I implement multi-tenancy?

Use Shared Kernel for tenant-aware repositories:

```php
// Shared/Domain/ValueObject/TenantId.php
final readonly class TenantId
{
    public function __construct(public string $value) {}
}

// Each repository filters by tenant
interface UserRepositoryInterface
{
    public function findByTenant(TenantId $tenantId): array;
}
```

---

## Comparison

### vs Symfony Maker Bundle?

| Feature | Symfony Maker | Hexagonal Maker |
|---------|--------------|-----------------|
| **Architecture** | Traditional layers | Hexagonal/DDD |
| **Domain Purity** | ❌ Coupled to Doctrine | ✅ Pure PHP |
| **CQRS** | ❌ No | ✅ Yes |
| **Modular** | ❌ Single namespace | ✅ Modules/Contexts |
| **Testing** | Basic | ✅ Unit + Integration |

**Use both!** Hexagonal Maker extends Symfony Maker.

### vs Manual Architecture?

| Aspect | Manual | Hexagonal Maker |
|--------|--------|-----------------|
| **Speed** | Hours per module | Minutes |
| **Consistency** | Varies by developer | Enforced patterns |
| **Best Practices** | Requires expertise | Built-in |
| **Learning Curve** | Steep | Guided |

Hexagonal Maker = **Manual quality at automated speed**

---

## Troubleshooting

### Generated files have wrong namespace

**Solution:** Check configuration in `hexagonal_maker.yaml`:

```yaml
hexagonal_maker:
    root_namespace: 'App'  # Must match your composer.json
```

### Doctrine can't find my entity

**Error:** `Class "X" is not a valid entity or mapped super class`

**Solution:** Add YAML mapping in `doctrine.yaml` for your module.

### Repository autowiring fails

**Error:** `Cannot autowire... no such service exists`

**Solution:** Add interface binding in `services.yaml`:

```yaml
services:
    App\Blog\Post\Domain\Port\PostRepositoryInterface:
        class: App\Blog\Post\Infrastructure\Persistence\Doctrine\DoctrinePostRepository
```

### Tests can't find classes

**Solution:** Regenerate autoload:

```bash
composer dump-autoload
```

---

## Contributing

### How can I contribute?

See [Contributing Guide](../contributing/overview.md). Ways to help:

- Report bugs
- Request features
- Submit PRs
- Improve documentation
- Share examples

### Where do I report bugs?

[GitHub Issues](https://github.com/ahmed-bhs/hexagonal-maker-bundle/issues)

---

## License

### Can I use it commercially?

Yes! MIT License - free for commercial use.

### Can I modify the generated code?

Yes! Generated code is yours. No restrictions.

---

**Still have questions?** [Open a discussion](https://github.com/ahmed-bhs/hexagonal-maker-bundle/discussions)
