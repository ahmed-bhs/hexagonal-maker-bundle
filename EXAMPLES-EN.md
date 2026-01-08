# Practical Examples - Hexagonal Architecture Maker Bundle

This document presents concrete examples of using the bundle to create a Symfony application following hexagonal architecture with complete Doctrine ORM configuration.

---

## Table of Contents

1. [Complete Example: Blog Article Management](#example-1-blog-article-management)
2. [Example: E-commerce Order Management](#example-2-e-commerce-order-management)
3. [Example: Reservation System](#example-3-reservation-system)
4. [Doctrine ORM Configuration](#doctrine-orm-configuration)
5. [Best Practices](#best-practices)

---

## Example 1: Blog Article Management

### 1.1 Context

Create a blog article management system with complete hexagonal architecture.

### 1.2 Structure Generation

```bash
# 1. Post Entity (Domain + Doctrine Mapping)
bin/console make:hexagonal:entity blog/post Post

# 2. Repository (Port + Doctrine Adapter)
bin/console make:hexagonal:repository blog/post Post

# 3. Business exception
bin/console make:hexagonal:exception blog/post InvalidPostDataException

# 4. Input DTO (with validation)
bin/console make:hexagonal:input blog/post CreatePostInput

# 5. Use Case
bin/console make:hexagonal:use-case blog/post CreatePost

# 6. Web Controller
bin/console make:hexagonal:controller blog/post CreatePost /posts/create

# 7. Symfony Form
bin/console make:hexagonal:form blog/post Post

# 8. CLI Command
bin/console make:hexagonal:cli-command blog/post CreatePost app:post:create
```

### 1.3 Post Entity (Domain - Pure PHP)

**File:** `src/Blog/Post/Domain/Model/Post.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\Domain\Model;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Domain Entity - PURE (no framework dependencies)
 */
final class Post
{
    private string $id;
    private string $title;
    private string $content;
    private ?DateTimeInterface $publishedAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $title,
        string $content,
        ?DateTimeInterface $publishedAt = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
        $this->publishedAt = $publishedAt;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getPublishedAt(): ?DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function publish(): void
    {
        if ($this->publishedAt !== null) {
            throw new \DomainException('Post is already published');
        }

        $this->publishedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isPublished(): bool
    {
        return $this->publishedAt !== null;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
```

### 1.4 Doctrine ORM Mapping (Infrastructure - YAML)

**File:** `src/Blog/Post/Infrastructure/Persistence/Doctrine/Orm/Mapping/Post.orm.yml`

```yaml
App\Blog\Post\Domain\Model\Post:
    type: entity
    repositoryClass: App\Blog\Post\Infrastructure\Persistence\Doctrine\DoctrinePostRepository
    table: post

    id:
        id:
            type: string
            length: 36
            # Alternative: type: uuid for Symfony UID

    fields:
        title:
            type: string
            length: 255
            nullable: false

        content:
            type: text
            nullable: false

        publishedAt:
            type: datetime_immutable
            column: published_at
            nullable: true

        createdAt:
            type: datetime_immutable
            column: created_at
            nullable: false

        updatedAt:
            type: datetime_immutable
            column: updated_at
            nullable: false
```

### 1.5 Repository Implementation (Infrastructure - Doctrine)

**File:** `src/Blog/Post/Infrastructure/Persistence/Doctrine/DoctrinePostRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\Infrastructure\Persistence\Doctrine;

use App\Blog\Post\Domain\Model\Post;
use App\Blog\Post\Domain\Port\PostRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Infrastructure Layer - Doctrine Repository Adapter
 *
 * @extends ServiceEntityRepository<Post>
 */
final class DoctrinePostRepository extends ServiceEntityRepository implements PostRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function save(Post $post): void
    {
        $this->getEntityManager()->persist($post);
        $this->getEntityManager()->flush();
    }

    public function findById(string $id): ?Post
    {
        return $this->find($id);
    }

    public function findAll(): array
    {
        return parent::findAll();
    }

    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.publishedAt IS NOT NULL')
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function remove(Post $post): void
    {
        $this->getEntityManager()->remove($post);
        $this->getEntityManager()->flush();
    }
}
```

### 1.6 Business Exception (Domain)

**File:** `src/Blog/Post/Domain/Exception/InvalidPostDataException.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\Domain\Exception;

use DomainException;

final class InvalidPostDataException extends DomainException
{
    public static function emptyTitle(): self
    {
        return new self('Post title cannot be empty');
    }

    public static function titleTooShort(int $minLength): self
    {
        return new self(sprintf('Post title must be at least %d characters', $minLength));
    }

    public static function emptyContent(): self
    {
        return new self('Post content cannot be empty');
    }

    public static function contentTooShort(int $minLength): self
    {
        return new self(sprintf('Post content must be at least %d characters', $minLength));
    }
}
```

### 1.7 Input DTO with Validation (Application)

**File:** `src/Blog/Post/Application/Input/CreatePostInput.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\Application\Input;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Application Layer - Input DTO
 */
final class CreatePostInput
{
    #[Assert\NotBlank(message: 'Title cannot be blank')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Title must be at least {{ limit }} characters',
        maxMessage: 'Title cannot be longer than {{ limit }} characters'
    )]
    public string $title;

    #[Assert\NotBlank(message: 'Content cannot be blank')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Content must be at least {{ limit }} characters'
    )]
    public string $content;

    #[Assert\Type(
        type: DateTimeInterface::class,
        message: 'The value {{ value }} is not a valid {{ type }}'
    )]
    public ?DateTimeInterface $publishedAt = null;

    public function __construct(
        string $title = '',
        string $content = '',
        ?DateTimeInterface $publishedAt = null
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->publishedAt = $publishedAt;
    }
}
```

### 1.8 Use Case (Application)

**Command:**

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\Application\Command;

final readonly class CreatePostCommand
{
    public function __construct(
        public string $title,
        public string $content,
        public ?DateTimeInterface $publishedAt = null
    ) {
    }
}
```

**Response:**

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\Application\Query;

use App\Blog\Post\Domain\Model\Post;

final readonly class CreatePostResponse
{
    public function __construct(
        public Post $post
    ) {
    }
}
```

**UseCase:**

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\Application\UseCase;

use App\Blog\Post\Application\Command\CreatePostCommand;
use App\Blog\Post\Application\Query\CreatePostResponse;
use App\Blog\Post\Domain\Exception\InvalidPostDataException;
use App\Blog\Post\Domain\Model\Post;
use App\Blog\Post\Domain\Port\PostRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Application Layer - Use Case
 */
final readonly class CreatePostUseCase
{
    public function __construct(
        private PostRepositoryInterface $repository,
    ) {
    }

    public function execute(CreatePostCommand $command): CreatePostResponse
    {
        // Business validation
        $this->validate($command);

        // Create domain entity
        $post = new Post(
            Uuid::v4()->toRfc4122(),
            $command->title,
            $command->content,
            $command->publishedAt
        );

        // Persist via port
        $this->repository->save($post);

        return new CreatePostResponse($post);
    }

    private function validate(CreatePostCommand $command): void
    {
        if (empty(trim($command->title))) {
            throw InvalidPostDataException::emptyTitle();
        }

        if (strlen($command->title) < 3) {
            throw InvalidPostDataException::titleTooShort(3);
        }

        if (empty(trim($command->content))) {
            throw InvalidPostDataException::emptyContent();
        }

        if (strlen($command->content) < 10) {
            throw InvalidPostDataException::contentTooShort(10);
        }
    }
}
```

### 1.9 Symfony Form (UI Layer)

**File:** `src/Blog/Post/UI/Http/Web/Form/PostType.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\UI\Http\Web\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter post title',
                    'class' => 'form-control',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Content',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter post content',
                    'class' => 'form-control',
                    'rows' => 10,
                ],
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Publish Date',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Create Post',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => true,
            ],
        ]);
    }
}
```

### 1.10 Web Controller (UI Layer)

**File:** `src/Blog/Post/UI/Http/Web/Controller/CreatePostController.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\UI\Http\Web\Controller;

use App\Blog\Post\Application\Command\CreatePostCommand;
use App\Blog\Post\Application\UseCase\CreatePostUseCase;
use App\Blog\Post\Domain\Exception\InvalidPostDataException;
use App\Blog\Post\UI\Http\Web\Form\PostType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * UI Layer - Web Controller (PRIMARY ADAPTER)
 */
#[Route('/posts/create', name: 'app.blog.post.create_post', methods: ['GET', 'POST'])]
final class CreatePostController extends AbstractController
{
    public function __construct(
        private readonly CreatePostUseCase $useCase,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(PostType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $command = new CreatePostCommand(
                title: $data['title'],
                content: $data['content'],
                publishedAt: $data['publishedAt'] ?? null
            );

            try {
                $response = $this->useCase->execute($command);

                $this->addFlash('success', sprintf(
                    'Post "%s" has been created successfully!',
                    $response->post->getTitle()
                ));

                return $this->redirectToRoute('app.blog.post.create_post');
            } catch (InvalidPostDataException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('blog/post/create_post.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
```

### 1.11 CLI Command (UI Layer)

**File:** `src/Blog/Post/UI/Cli/CreatePostCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog\Post\UI\Cli;

use App\Blog\Post\Application\Command\CreatePostCommand as CreatePostDomainCommand;
use App\Blog\Post\Application\UseCase\CreatePostUseCase;
use App\Blog\Post\Domain\Exception\InvalidPostDataException;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * UI Layer - CLI Command (PRIMARY ADAPTER)
 */
#[AsCommand(
    name: 'app:post:create',
    description: 'Create a new blog post',
)]
final class CreatePostCommand extends Command
{
    public function __construct(
        private readonly CreatePostUseCase $useCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('title', InputArgument::REQUIRED, 'Post title')
            ->addArgument('content', InputArgument::REQUIRED, 'Post content')
            ->addOption('publish', 'p', InputOption::VALUE_NONE, 'Publish immediately');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $title = $input->getArgument('title');
        $content = $input->getArgument('content');
        $publish = $input->getOption('publish');

        $command = new CreatePostDomainCommand(
            title: $title,
            content: $content,
            publishedAt: $publish ? new DateTimeImmutable() : null
        );

        try {
            $response = $this->useCase->execute($command);

            $io->success(sprintf(
                'Post "%s" created successfully with ID: %s',
                $response->post->getTitle(),
                $response->post->getId()
            ));

            return Command::SUCCESS;
        } catch (InvalidPostDataException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

---

## Example 2: E-commerce Order Management

### 2.1 Generation

```bash
# Order Entity
bin/console make:hexagonal:entity ecommerce/order Order

# Repository
bin/console make:hexagonal:repository ecommerce/order Order

# Value Objects
bin/console make:hexagonal:value-object ecommerce/order OrderId
bin/console make:hexagonal:value-object ecommerce/order Money
bin/console make:hexagonal:value-object ecommerce/order OrderStatus

# Exception
bin/console make:hexagonal:exception ecommerce/order InvalidOrderException

# Use Case
bin/console make:hexagonal:use-case ecommerce/order PlaceOrder

# API Controller
bin/console make:hexagonal:controller ecommerce/order PlaceOrder /api/orders
```

### 2.2 Money Value Object

**File:** `src/Ecommerce/Order/Domain/ValueObject/Money.php`

```php
<?php

declare(strict_types=1);

namespace App\Ecommerce\Order\Domain\ValueObject;

final readonly class Money
{
    private function __construct(
        public int $amount,      // in cents
        public string $currency  // EUR, USD, etc.
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        if (!in_array($currency, ['EUR', 'USD', 'GBP'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Currency "%s" is not supported',
                $currency
            ));
        }
    }

    public static function fromCents(int $cents, string $currency): self
    {
        return new self($cents, $currency);
    }

    public static function fromFloat(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);

        if ($this->amount < $other->amount) {
            throw new \InvalidArgumentException('Cannot subtract to negative amount');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    private function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot operate on different currencies: %s and %s',
                $this->currency,
                $other->currency
            ));
        }
    }
}
```

### 2.3 Order Entity with Business Logic

**File:** `src/Ecommerce/Order/Domain/Model/Order.php`

```php
<?php

declare(strict_types=1);

namespace App\Ecommerce\Order\Domain\Model;

use App\Ecommerce\Order\Domain\ValueObject\Money;
use App\Ecommerce\Order\Domain\ValueObject\OrderStatus;
use DateTimeImmutable;

final class Order
{
    private string $id;
    private string $customerId;
    private array $items = [];
    private OrderStatus $status;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $confirmedAt = null;

    public function __construct(string $id, string $customerId)
    {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->status = OrderStatus::PENDING;
        $this->createdAt = new DateTimeImmutable();
    }

    public function addItem(string $productId, Money $unitPrice, int $quantity): void
    {
        if (!$this->status->equals(OrderStatus::PENDING)) {
            throw new \DomainException('Cannot add items to a non-pending order');
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        $this->items[] = [
            'productId' => $productId,
            'unitPrice' => $unitPrice,
            'quantity' => $quantity,
        ];
    }

    public function confirm(): void
    {
        if (!$this->status->equals(OrderStatus::PENDING)) {
            throw new \DomainException('Only pending orders can be confirmed');
        }

        if (empty($this->items)) {
            throw new \DomainException('Cannot confirm an empty order');
        }

        $this->status = OrderStatus::CONFIRMED;
        $this->confirmedAt = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->status->equals(OrderStatus::SHIPPED)) {
            throw new \DomainException('Cannot cancel a shipped order');
        }

        $this->status = OrderStatus::CANCELLED;
    }

    public function getTotalAmount(): Money
    {
        if (empty($this->items)) {
            return Money::fromCents(0, 'EUR');
        }

        $total = Money::fromCents(0, 'EUR');

        foreach ($this->items as $item) {
            $itemTotal = $item['unitPrice']->multiply($item['quantity']);
            $total = $total->add($itemTotal);
        }

        return $total;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
```

### 2.4 Doctrine YAML Mapping for Order

**File:** `src/Ecommerce/Order/Infrastructure/Persistence/Doctrine/Orm/Mapping/Order.orm.yml`

```yaml
App\Ecommerce\Order\Domain\Model\Order:
    type: entity
    repositoryClass: App\Ecommerce\Order\Infrastructure\Persistence\Doctrine\DoctrineOrderRepository
    table: ecommerce_order

    id:
        id:
            type: string
            length: 36

    fields:
        customerId:
            type: string
            column: customer_id
            length: 36

        items:
            type: json

        status:
            type: string
            length: 20

        createdAt:
            type: datetime_immutable
            column: created_at

        confirmedAt:
            type: datetime_immutable
            column: confirmed_at
            nullable: true
```

---

## Example 3: Reservation System

### 3.1 Generation

```bash
# Reservation Entity
bin/console make:hexagonal:entity booking/reservation Reservation

# Repository
bin/console make:hexagonal:repository booking/reservation Reservation

# Value Objects
bin/console make:hexagonal:value-object booking/reservation Seat
bin/console make:hexagonal:value-object booking/reservation TimeSlot

# Exception
bin/console make:hexagonal:exception booking/reservation ReservationException

# Use Case
bin/console make:hexagonal:use-case booking/reservation CreateReservation
```

### 3.2 Seat Value Object

**File:** `src/Booking/Reservation/Domain/ValueObject/Seat.php`

```php
<?php

declare(strict_types=1);

namespace App\Booking\Reservation\Domain\ValueObject;

final readonly class Seat
{
    public function __construct(
        public string $row,      // A, B, C...
        public int $number       // 1, 2, 3...
    ) {
        if (!preg_match('/^[A-Z]$/', $row)) {
            throw new \InvalidArgumentException('Row must be a single uppercase letter');
        }

        if ($number < 1 || $number > 50) {
            throw new \InvalidArgumentException('Seat number must be between 1 and 50');
        }
    }

    public function toString(): string
    {
        return sprintf('%s%d', $this->row, $this->number);
    }

    public function equals(self $other): bool
    {
        return $this->row === $other->row && $this->number === $other->number;
    }
}
```

---

## Doctrine ORM Configuration

### Global Configuration

**File:** `config/packages/doctrine.yaml`

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'

        # For PostgreSQL with UUID
        types:
            uuid: Symfony\Bridge\Doctrine\Types\UuidType

    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true

        mappings:
            # Mapping for Blog/Post module
            BlogPost:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/Blog/Post/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\Blog\Post\Domain\Model'
                alias: BlogPost

            # Mapping for Ecommerce/Order module
            EcommerceOrder:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/Ecommerce/Order/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\Ecommerce\Order\Domain\Model'
                alias: EcommerceOrder

            # Mapping for Booking/Reservation module
            BookingReservation:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/Booking/Reservation/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\Booking\Reservation\Domain\Model'
                alias: BookingReservation

when@test:
    doctrine:
        dbal:
            dbname_suffix: '_test%env(default::TEST_TOKEN)%'

when@prod:
    doctrine:
        orm:
            auto_generate_proxy_classes: false
            query_cache_driver:
                type: pool
                pool: doctrine.system_cache_pool
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool

    framework:
        cache:
            pools:
                doctrine.result_cache_pool:
                    adapter: cache.app
                doctrine.system_cache_pool:
                    adapter: cache.system
```

### Services Configuration

**File:** `config/services.yaml`

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Exclude files that are not services
    App\:
        resource: '../src/'
        exclude:
            - '../src/*/Domain/Model/'
            - '../src/*/Domain/ValueObject/'
            - '../src/*/Domain/Exception/'
            - '../src/*/Application/Command/'
            - '../src/*/Application/Query/'
            - '../src/*/Application/Input/'
            - '../src/**/Infrastructure/Persistence/Doctrine/Orm/'
            - '../src/Kernel.php'

    # Controllers
    App\Blog\Post\UI\Http\Web\Controller\:
        resource: '../src/Blog/Post/UI/Http/Web/Controller/'
        tags: ['controller.service_arguments']

    # Repositories - Link interfaces to implementations
    App\Blog\Post\Domain\Port\PostRepositoryInterface:
        alias: App\Blog\Post\Infrastructure\Persistence\Doctrine\DoctrinePostRepository

    App\Ecommerce\Order\Domain\Port\OrderRepositoryInterface:
        alias: App\Ecommerce\Order\Infrastructure\Persistence\Doctrine\DoctrineOrderRepository

    App\Booking\Reservation\Domain\Port\ReservationRepositoryInterface:
        alias: App\Booking\Reservation\Infrastructure\Persistence\Doctrine\DoctrineReservationRepository
```

---

## Best Practices

### 1. Pure Entities (Domain)

```php
// GOOD - Pure entity without dependencies
final class Post
{
    private string $id;
    private string $title;

    public function __construct(string $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
    }
}

// BAD - Entity with Doctrine annotations
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]  // Don't put this in Domain!
final class Post
{
    #[ORM\Column]
    private string $title;
}
```

### 2. Validation in Value Objects

```php
// GOOD - Encapsulated validation
final readonly class Email
{
    public function __construct(public string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid email', $value)
            );
        }
    }
}

// Usage
$email = new Email('user@example.com'); // Throws exception if invalid

// BAD - Scattered validation
$email = $request->get('email');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Invalid email');
}
```

### 3. Business Logic in Entities

```php
// GOOD - Logic in entity
final class Order
{
    public function confirm(): void
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw new \DomainException('Only pending orders can be confirmed');
        }

        if (empty($this->items)) {
            throw new \DomainException('Cannot confirm empty order');
        }

        $this->status = OrderStatus::CONFIRMED;
    }
}

// Usage
$order->confirm(); // All business rules are applied

// BAD - Logic in use case/controller
if ($order->getStatus() === 'pending' && count($order->getItems()) > 0) {
    $order->setStatus('confirmed');
}
```

### 4. Ports and Adapters

```php
// GOOD - Interface in Domain, implementation in Infrastructure
namespace App\Blog\Post\Domain\Port;

interface PostRepositoryInterface
{
    public function save(Post $post): void;
    public function findById(string $id): ?Post;
}

namespace App\Blog\Post\Infrastructure\Persistence\Doctrine;

final class DoctrinePostRepository implements PostRepositoryInterface
{
    // Doctrine implementation
}

// BAD - Direct dependency on Doctrine in Domain
use Doctrine\ORM\EntityManagerInterface;

final class PostService
{
    public function __construct(
        private EntityManagerInterface $em // NO!
    ) {
    }
}
```

### 5. Immutable Commands

```php
// GOOD - Readonly and immutable Command
final readonly class CreatePostCommand
{
    public function __construct(
        public string $title,
        public string $content,
    ) {
    }
}

// BAD - Mutable Command
final class CreatePostCommand
{
    public string $title;
    public string $content;

    public function setTitle(string $title): void // NO!
    {
        $this->title = $title;
    }
}
```

### 6. Doctrine YAML Mapping (No Attributes)

```yaml
# GOOD - Separated YAML mapping in Infrastructure
App\Blog\Post\Domain\Model\Post:
    type: entity
    table: post
    fields:
        title:
            type: string
```

```php
// BAD - Doctrine Attributes in Domain
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'post')]
final class Post
{
    #[ORM\Column(type: 'string')]
    private string $title;
}
```

---

## Useful Commands

```bash
# Create database
bin/console doctrine:database:create

# Create migrations
bin/console doctrine:migrations:diff

# Execute migrations
bin/console doctrine:migrations:migrate

# Validate Doctrine mapping
bin/console doctrine:schema:validate

# Display SQL that will be executed
bin/console doctrine:schema:update --dump-sql

# Clear Doctrine cache
bin/console doctrine:cache:clear-metadata
bin/console doctrine:cache:clear-query
bin/console doctrine:cache:clear-result
```

---

**These examples show how to create a complete hexagonal architecture with Symfony and Doctrine ORM using the HexagonalMakerBundle.**
