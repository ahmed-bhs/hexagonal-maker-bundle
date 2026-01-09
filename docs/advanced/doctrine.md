# Doctrine Integration

Advanced Doctrine integration patterns for hexagonal architecture.

---

## Pure Domain with YAML Mapping

Keep domain entities pure (no annotations) using YAML mapping.

### Entity (Pure PHP)

```php
<?php
// src/Blog/Post/Domain/Model/Post.php

namespace App\Blog\Post\Domain\Model;

final class Post
{
    private string $id;
    private string $title;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
        $this->createdAt = new \DateTimeImmutable();
    }
}
```

### YAML Mapping (Infrastructure)

```yaml
# src/Blog/Post/Infrastructure/Persistence/Doctrine/Orm/Mapping/Post.orm.yml

App\Blog\Post\Domain\Model\Post:
    type: entity
    table: post

    id:
        id:
            type: string
            length: 36

    fields:
        title:
            type: string
            length: 255

        createdAt:
            type: datetime_immutable
            column: created_at
```

---

## Value Objects as Embeddables

```yaml
# Email.orm.yml
App\Domain\ValueObject\Email:
    type: embeddable
    fields:
        value:
            type: string
            length: 180

# User.orm.yml
embedded:
    email:
        class: App\Domain\ValueObject\Email
        columnPrefix: email_
```

---

## Gedmo Extensions

Use Gedmo with YAML to keep domain pure:

```yaml
fields:
    createdAt:
        type: datetime_immutable
        gedmo:
            timestampable:
                on: create

    slug:
        type: string
        gedmo:
            slug:
                fields: [title]
```

---

For complete Gedmo guide, see [README.md](../../README.md#doctrine-extensions).
