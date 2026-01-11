---
layout: default
title: Testing Guide
parent: Examples
nav_order: 3
---

# Testing Examples

Examples of testing hexagonal architecture with generated tests.

---

## Generate Tests

```bash
# With command
bin/console make:hexagonal:command blog/post create --with-tests

# Standalone
bin/console make:hexagonal:use-case-test blog/post CreatePost
bin/console make:hexagonal:controller-test blog/post CreatePost /posts/new
```

---

## Unit Test Example

```php
<?php

namespace Tests\Blog\Post\Application\Create;

use PHPUnit\Framework\TestCase;

final class CreatePostHandlerTest extends TestCase
{
    public function testCreatesPost(): void
    {
        $repository = $this->createMock(PostRepositoryInterface::class);
        $repository->expects($this->once())->method('save');

        $handler = new CreatePostHandler($repository);
        $command = new CreatePostCommand('Title', 'Content');

        $handler($command);
    }
}
```

---

## Integration Test Example

```php
<?php

namespace Tests\Blog\Post\Application\Create;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreatePostIntegrationTest extends KernelTestCase
{
    public function testCreatesPostInDatabase(): void
    {
        self::bootKernel();

        $commandBus = static::getContainer()->get(MessageBusInterface::class);
        $command = new CreatePostCommand('Title', 'Content');

        $commandBus->dispatch($command);

        $repository = static::getContainer()->get(PostRepositoryInterface::class);
        $posts = $repository->findAll();

        $this->assertCount(1, $posts);
    }
}
```

---

## Run Tests

```bash
vendor/bin/phpunit
```

---

See generated test files for complete examples.
