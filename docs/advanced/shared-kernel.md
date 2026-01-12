# Shared Kernel

The Shared Kernel is a strategic pattern from Domain-Driven Design (DDD) that contains code shared across multiple bounded contexts (modules) in your application.

{: .note }
The Shared Kernel should be small, well-defined, and changed only with careful coordination, as modifications impact all modules that depend on it.

---

## What is the Shared Kernel?

In a modular hexagonal architecture, each module (bounded context) should be as independent as possible. However, some concepts are truly generic and used across multiple modules. The **Shared Kernel** is where you place these common building blocks.

### Purpose

The Shared Kernel serves to:

1. **Prevent Code Duplication** - Avoid reimplementing the same value objects or utilities in every module
2. **Ensure Consistency** - Guarantee that common concepts (like Email, Money, UUID) behave identically everywhere
3. **Maintain Independence** - Allow modules to share code without creating tight coupling between them
4. **Express Ubiquitous Language** - Centralize domain concepts that transcend individual bounded contexts

---

## Directory Structure

```
src/
├── Module/                    # Your bounded contexts
│   ├── User/
│   │   └── Account/
│   ├── Blog/
│   │   └── Post/
│   └── Order/
│       └── Checkout/
└── Shared/                    # Shared Kernel
    ├── Domain/
    │   ├── ValueObject/       # Generic value objects
    │   │   ├── Email.php
    │   │   ├── Money.php
    │   │   ├── Uuid.php
    │   │   └── PhoneNumber.php
    │   ├── Exception/         # Base exceptions
    │   │   ├── DomainException.php
    │   │   └── ValidationException.php
    │   └── Event/             # Base event classes
    │       └── DomainEvent.php
    ├── Application/
    │   └── Service/           # Generic application services
    │       └── Clock.php      # Time abstraction
    └── Infrastructure/
        ├── Persistence/       # Generic persistence utilities
        │   └── Doctrine/
        │       └── Type/      # Custom Doctrine types
        └── Messaging/         # Shared messaging infrastructure
```

---

## What Belongs in Shared Kernel?

### Good Candidates for Shared Kernel

**Generic Value Objects**
- `Email` - Used by User, Newsletter, Support modules
- `Money` - Used by Order, Invoice, Payment modules
- `Uuid` - Used across all modules for entity IDs
- `PhoneNumber` - Used by User, Shipping, Contact modules
- `Address` - Used by User, Order, Shipping modules (if truly generic)

**Base Domain Concepts**
- Abstract base exceptions (`DomainException`)
- Domain event interfaces
- Common enums (Country, Currency, Language)
- Measurement units (Weight, Distance)

**Infrastructure Utilities**
- Clock interface for testable time
- Common Doctrine custom types
- Shared event bus configuration

### What Should NOT be in Shared

**Context-Specific Logic**
- `UserEmail` (includes user-specific validation like "no admin emails") → Keep in User module
- `OrderTotal` (includes tax calculation logic) → Keep in Order module
- `ProductPrice` (includes pricing rules) → Keep in Product module

**Business Rules**
- Any validation that differs by context
- Behavior specific to one domain

**Premature Abstractions**
- Code used by only 1-2 modules (wait until 3+)
- "Might be shared someday" code

---

## Examples

### Example 1: Shared Email Value Object

```php
<?php
// src/Shared/Domain/ValueObject/Email.php

namespace App\Shared\Domain\ValueObject;

final readonly class Email
{
    public function __construct(private string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                "'{$value}' is not a valid email address"
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

**Used across multiple modules:**

```php
// User module - User entity
namespace App\User\Account\Domain\Model;

use App\Shared\Domain\ValueObject\Email;

final class User
{
    public function __construct(
        private UserId $id,
        private Email $email,  // ← Shared Email
        private string $name
    ) {}
}

// Newsletter module - Subscriber entity
namespace App\Newsletter\Domain\Model;

use App\Shared\Domain\ValueObject\Email;

final class Subscriber
{
    public function __construct(
        private SubscriberId $id,
        private Email $email,  // ← Same shared Email
        private bool $active
    ) {}
}

// Support module - Ticket entity
namespace App\Support\Ticket\Domain\Model;

use App\Shared\Domain\ValueObject\Email;

final class Ticket
{
    public function __construct(
        private TicketId $id,
        private Email $customerEmail,  // ← Same shared Email
        private string $subject
    ) {}
}
```

### Example 2: Shared Money Value Object

```php
<?php
// src/Shared/Domain/ValueObject/Money.php

namespace App\Shared\Domain\ValueObject;

final readonly class Money
{
    public function __construct(
        private int $amount,      // Store as cents/minor units
        private Currency $currency
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new \InvalidArgumentException(
                'Cannot operate on different currencies'
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }
}

// src/Shared/Domain/ValueObject/Currency.php
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
}
```

**Used in Order and Invoice modules:**

```php
// Order module
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Currency;

final class Order
{
    private Money $total;

    public function calculateTotal(): void
    {
        $this->total = new Money(0, Currency::USD);
        foreach ($this->items as $item) {
            $this->total = $this->total->add($item->getPrice());
        }
    }
}

// Invoice module
use App\Shared\Domain\ValueObject\Money;

final class Invoice
{
    public function __construct(
        private InvoiceId $id,
        private Money $amount,     // ← Shared Money
        private Money $taxAmount   // ← Shared Money
    ) {}
}
```

### Example 3: Shared UUID Value Object

```php
<?php
// src/Shared/Domain/ValueObject/Uuid.php

namespace App\Shared\Domain\ValueObject;

use Symfony\Component\Uid\Uuid as SymfonyUuid;

abstract readonly class Uuid
{
    protected function __construct(private string $value)
    {
        if (!SymfonyUuid::isValid($value)) {
            throw new \InvalidArgumentException("Invalid UUID: {$value}");
        }
    }

    public static function generate(): static
    {
        return new static(SymfonyUuid::v4()->toRfc4122());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

**Each module extends it with their own typed ID:**

```php
// User module
namespace App\User\Account\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

final readonly class UserId extends Uuid {}

// Order module
namespace App\Order\Checkout\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

final readonly class OrderId extends Uuid {}

// Blog module
namespace App\Blog\Post\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

final readonly class PostId extends Uuid {}
```

**Why extend instead of direct use?**
- **Type Safety** - `UserId` ≠ `OrderId` at compile time
- **Clarity** - Intent is explicit in method signatures
- **Flexibility** - Each module can add specific behavior later

---

## When to Move Code to Shared

### The "Rule of Three"

**Wait until 3+ modules need it:**

```
Module A needs Email → Keep in Module A
Module B also needs Email → Duplicate or extract to Shared? → Wait
Module C also needs Email → Now extract to Shared!
```

### Decision Checklist

Ask these questions before moving code to Shared:

1. **Is it truly generic?**
   - Yes: `Email` - Same validation everywhere
   - No: `UserEmail` - Might have user-specific rules

2. **Does it have zero business logic?**
   - Yes: `PhoneNumber` - Just format and validation
   - No: `CustomerDiscount` - Contains pricing rules

3. **Will all modules use it the same way?**
   - Yes: `Uuid` - Identity concept is universal
   - No: `Status` - Each module has different status workflows

4. **Is it stable?**
   - Yes: `Money` - Well-established pattern
   - No: `Notification` - Still evolving per module needs

If you answer "Yes" to all → Move to Shared
If you answer "No" to any → Keep in module

---

## Anti-Patterns to Avoid

### Shared Becoming a "Junk Drawer"

**Bad:**
```
Shared/
├── Utils/
│   ├── StringHelper.php
│   ├── ArrayHelper.php
│   └── MiscFunctions.php  ← Avoid!
```

**Good:**
```
Shared/
├── Domain/
│   └── ValueObject/
│       ├── Email.php       ← Clear purpose
│       └── Money.php       ← Clear purpose
```

### Premature Extraction

**Bad:**
```php
// After first use in User module
// "This might be shared someday..."
mv User/ValueObject/Email.php Shared/ValueObject/Email.php  ← Too early!
```

**Good:**
```php
// After 3rd module needs it
// "Now it's proven to be generic"
mv User/ValueObject/Email.php Shared/ValueObject/Email.php  ← Right time!
```

### Business Logic in Shared

**Bad:**
```php
// Shared/Domain/ValueObject/Price.php
final class Price
{
    public function applyDiscount(): self
    {
        // Discount logic belongs in Order or Product module!
        if ($this->customer->isPremium()) {
            return $this->multiply(0.9);
        }
    }
}
```

**Good:**
```php
// Shared/Domain/ValueObject/Money.php
final readonly class Money
{
    // Pure value object - no business rules
    public function multiply(float $factor): self
    {
        return new self((int)($this->amount * $factor), $this->currency);
    }
}

// Order/Domain/Service/PricingService.php
final class PricingService
{
    // Business logic stays in module
    public function applyDiscount(Money $price, Customer $customer): Money
    {
        if ($customer->isPremium()) {
            return $price->multiply(0.9);
        }
        return $price;
    }
}
```

---

## Generating Shared Code

### Generate Shared Value Objects

```bash
# Generate in Shared namespace
bin/console make:hexagonal:value-object shared Email
```

This creates:
```
src/Shared/Domain/ValueObject/Email.php
```

### Generate Shared Exceptions

```bash
bin/console make:hexagonal:exception shared ValidationException
```

This creates:
```
src/Shared/Domain/Exception/ValidationException.php
```

---

## Managing Shared Kernel Changes

### Coordination is Key

Changes to Shared affect all modules. Follow these rules:

1. **Backward Compatibility** - Don't break existing modules
2. **Team Agreement** - Discuss changes with all module owners
3. **Version Carefully** - Consider Shared as an internal "library"
4. **Test Thoroughly** - Changes impact multiple contexts

### Safe Change Example

**Before:**
```php
final readonly class Email
{
    public function getValue(): string
    {
        return $this->value;
    }
}
```

**After (backward compatible):**
```php
final readonly class Email
{
    public function getValue(): string
    {
        return $this->value;
    }

    // New method - doesn't break existing code
    public function getDomain(): string
    {
        return explode('@', $this->value)[1];
    }
}
```

### Breaking Change (Avoid!)

**Bad:**
```php
final readonly class Email
{
    // Renamed - breaks all modules!
    public function value(): string  // was getValue()
    {
        return $this->value;
    }
}
```

---

## Best Practices

1. **Keep it Small** - Shared Kernel should be minimal
2. **Wait for Patterns** - Don't prematurely extract
3. **No Business Logic** - Only pure, generic concepts
4. **Document Well** - Clear docs on what belongs in Shared
5. **Version Changes** - Treat Shared as a contract
6. **Test Coverage** - High test coverage for Shared code

---

## Summary

The Shared Kernel is for:
- Generic value objects (Email, Money, UUID)
- Common base exceptions
- Infrastructure utilities
- Concepts used by 3+ modules

The Shared Kernel is NOT for:
- Business logic specific to one context
- Code used by only 1-2 modules
- Unstable or evolving concepts
- Context-specific variations

**Key principle:** When in doubt, keep it in the module. Extract to Shared only when the need is proven and clear.
