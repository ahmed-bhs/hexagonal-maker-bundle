# Shared Kernel

Organize shared code across bounded contexts.

---

## Structure

```
src/
├── Module/              # Bounded contexts
│   ├── User/
│   ├── Blog/
│   └── Order/
└── Shared/              # Shared kernel
    ├── Domain/
    │   ├── ValueObject/
    │   └── Exception/
    └── Infrastructure/
```

---

## Shared Value Objects

```php
<?php
// src/Shared/Domain/ValueObject/Email.php

namespace App\Shared\Domain\ValueObject;

final readonly class Email
{
    public function __construct(private string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email");
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
```

**Use in modules:**

```php
// User module
use App\Shared\Domain\ValueObject\Email;

class User
{
    public function __construct(private Email $email) {}
}

// Newsletter module
use App\Shared\Domain\ValueObject\Email;

class Subscriber
{
    public function __construct(private Email $email) {}
}
```

---

## When to Use Shared

**Move to Shared if:**
- Used by 3+ modules
- Generic concept (Email, Money, Uuid)
- No business logic specific to one context

**Keep in Module if:**
- Specific to one context
- Contains business rules
- Used by 1-2 modules only

---

For detailed guide, see [README.md](../../README.md#shared-kernel-structure).
