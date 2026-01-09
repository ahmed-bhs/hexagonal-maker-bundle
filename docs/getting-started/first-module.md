# First Module Tutorial

Complete step-by-step guide to build your first hexagonal module from scratch.

---

## What We'll Build

A **Blog Post** module with:
- CRUD operations (Create, Read, Update, Delete, List)
- Pure domain entities
- CQRS commands and queries
- Web controllers and forms
- Complete tests

**Time:** 15-20 minutes

---

## Prerequisites

- Hexagonal Maker Bundle installed
- Doctrine configured with YAML mappings
- Database created

[See installation guide →](installation.md)

---

## Step 1: Plan the Module

### Module Structure

```
src/Blog/Post/
├── Domain/              # Business logic
├── Application/         # Use cases
├── Infrastructure/      # Technical implementations
└── UI/                  # Controllers and forms
```

### Bounded Context

- **Context:** Blog
- **Module:** Post
- **Aggregate Root:** Post entity

---

## Step 2: Generate Domain Layer

### 2.1 Create Post Entity

```bash
bin/console make:hexagonal:entity blog/post Post
```

**Generated:**
```
src/Blog/Post/Domain/Model/Post.php
src/Blog/Post/Infrastructure/Persistence/Doctrine/Orm/Mapping/Post.orm.yml
```

**Edit the entity:**

```php
<?php
// src/Blog/Post/Domain/Model/Post.php

declare(strict_types=1);

namespace App\Blog\Post\Domain\Model;

final class Post
{
    private string $id;
    private string $title;
    private string $content;
    private string $status;  // draft, published
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $publishedAt;

    public function __construct(
        string $id,
        string $title,
        string $content
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
        $this->status = 'draft';
        $this->createdAt = new \DateTimeImmutable();
        $this->publishedAt = null;
    }

    // Business method
    public function publish(): void
    {
        if ($this->status === 'published') {
            throw new \DomainException('Post is already published');
        }

        $this->status = 'published';
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function update(string $title, string $content): void
    {
        $this->title = $title;
        $this->content = $content;
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getStatus(): string { return $this->status; }
    public function isPublished(): bool { return $this->status === 'published'; }
}
```

### 2.2 Complete Doctrine Mapping

```yaml
# src/Blog/Post/Infrastructure/Persistence/Doctrine/Orm/Mapping/Post.orm.yml

App\Blog\Post\Domain\Model\Post:
    type: entity
    repositoryClass: App\Blog\Post\Infrastructure\Persistence\Doctrine\DoctrinePostRepository
    table: post

    id:
        id:
            type: string
            length: 36

    fields:
        title:
            type: string
            length: 255

        content:
            type: text

        status:
            type: string
            length: 20
            options:
                default: 'draft'

        createdAt:
            type: datetime_immutable
            column: created_at

        publishedAt:
            type: datetime_immutable
            column: published_at
            nullable: true
```

### 2.3 Add Doctrine Mapping Configuration

Edit `config/packages/doctrine.yaml`:

```yaml
doctrine:
    orm:
        mappings:
            BlogPost:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/Blog/Post/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\Blog\Post\Domain\Model'
                alias: BlogPost
```

---

## Step 3: Generate Repository

```bash
bin/console make:hexagonal:repository blog/post Post
```

**Generated:**
- `Domain/Port/PostRepositoryInterface.php` (Interface)
- `Infrastructure/Persistence/Doctrine/DoctrinePostRepository.php` (Implementation)

**Complete the repository interface:**

```php
<?php
// src/Blog/Post/Domain/Port/PostRepositoryInterface.php

namespace App\Blog\Post\Domain\Port;

use App\Blog\Post\Domain\Model\Post;

interface PostRepositoryInterface
{
    public function save(Post $post): void;
    public function findById(string $id): ?Post;
    public function findAll(): array;
    public function delete(Post $post): void;
}
```

**Implement repository methods:**

```php
<?php
// src/Blog/Post/Infrastructure/Persistence/Doctrine/DoctrinePostRepository.php

namespace App\Blog\Post\Infrastructure\Persistence\Doctrine;

use App\Blog\Post\Domain\Model\Post;
use App\Blog\Post\Domain\Port\PostRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePostRepository implements PostRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(Post $post): void
    {
        $this->entityManager->persist($post);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?Post
    {
        return $this->entityManager->find(Post::class, $id);
    }

    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(Post::class)
            ->findAll();
    }

    public function delete(Post $post): void
    {
        $this->entityManager->remove($post);
        $this->entityManager->flush();
    }
}
```

---

## Step 4: Generate Use Cases (CQRS)

### 4.1 Create Post Command

```bash
bin/console make:hexagonal:command blog/post create --factory
```

Complete the command and handler logic following the CQRS pattern.

### 4.2 Update Post Command

```bash
bin/console make:hexagonal:command blog/post update
```

### 4.3 Delete Post Command

```bash
bin/console make:hexagonal:command blog/post delete
```

### 4.4 Find Post Query

```bash
bin/console make:hexagonal:query blog/post find-by-id
```

### 4.5 List Posts Query

```bash
bin/console make:hexagonal:query blog/post list-all
```

---

## Step 5: Generate UI Layer

### 5.1 Controllers

```bash
# Create post
bin/console make:hexagonal:controller blog/post CreatePost /posts/new

# Edit post
bin/console make:hexagonal:controller blog/post EditPost /posts/{id}/edit

# Show post
bin/console make:hexagonal:controller blog/post ShowPost /posts/{id}

# List posts
bin/console make:hexagonal:controller blog/post ListPosts /posts

# Delete post
bin/console make:hexagonal:controller blog/post DeletePost /posts/{id}/delete
```

### 5.2 Forms

```bash
bin/console make:hexagonal:form blog/post Post
```

---

## Step 6: Create Database Schema

```bash
# Validate mapping
bin/console doctrine:schema:validate

# Generate migration
bin/console doctrine:migrations:diff

# Execute migration
bin/console doctrine:migrations:migrate
```

---

## Step 7: Test Your Module

### Manual Testing via CLI

Create a test CLI command:

```bash
bin/console make:hexagonal:cli-command blog/post CreatePost app:post:create
```

Run:

```bash
bin/console app:post:create --title="My First Post" --content="Hello World"
```

---

## Alternative: Use CRUD Maker ⚡

Instead of steps 4-5, generate everything at once:

```bash
bin/console make:hexagonal:crud blog/post Post --with-tests
```

This generates:
- Entity + Repository
- 5 Use Cases (Create, Update, Delete, Get, List)
- 5 Controllers + Form
- All tests

---

## Next Steps

Congratulations! You've built a complete hexagonal module. Now:

1. [**Add tests**](../examples/testing.md) - Unit and integration tests
2. [**Add validation**](#validation) - Input DTOs with constraints
3. [**Add domain events**](#domain-events) - Event-driven architecture
4. [**Explore all makers**](../makers/commands.md) - Learn advanced features

---

## Common Patterns

### Validation

Use Input DTOs with Symfony Validator:

```bash
bin/console make:hexagonal:input blog/post CreatePostInput
```

```php
<?php

namespace App\Blog\Post\Application\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePostInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $title,

        #[Assert\NotBlank]
        #[Assert\Length(min: 10)]
        public string $content,
    ) {
    }
}
```

### Domain Events

Generate domain event:

```bash
bin/console make:hexagonal:domain-event blog/post PostPublished --with-subscriber
```

Dispatch from entity:

```php
public function publish(): void
{
    $this->status = 'published';
    $this->publishedAt = new \DateTimeImmutable();

    // Dispatch event
    $this->recordEvent(new PostPublishedEvent($this->id, $this->title));
}
```

### Async Processing

Generate message handler for async tasks:

```bash
bin/console make:hexagonal:message-handler blog/post SendNotification
```

Configure routing in `messenger.yaml`:

```yaml
framework:
    messenger:
        routing:
            'App\Blog\Post\Application\Message\SendNotificationMessage': async
```

---

## Troubleshooting

See [Installation Guide - Troubleshooting](installation.md#troubleshooting)
