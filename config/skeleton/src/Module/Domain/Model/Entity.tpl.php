<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

/**
 * Domain Entity
 *
 * Represents a domain concept with identity and lifecycle.
 * Contains business logic and enforces invariants.
 *
 * In hexagonal architecture, entities are part of the Domain layer (core)
 * and are completely independent of infrastructure concerns.
 *
 * ⚠️ IMPORTANT: This entity is PURE - no framework dependencies.
 * Doctrine ORM mapping is configured separately in:
 * Infrastructure/Persistence/Doctrine/Orm/Mapping/<?= $class_name ?>.orm.yml
 */
final class <?= $class_name ?>

{
    private string $id;

    // TODO: Add your domain properties here
    // Example:
    // private string $name;
    // private string $email;
    // private \DateTimeImmutable $createdAt;
    // private bool $isActive = true;

    public function __construct(
        string $id,
        // TODO: Add your domain properties here
    ) {
        $this->id = $id;
        // TODO: Initialize and validate your domain properties
        // Example:
        // $this->createdAt = new \DateTimeImmutable();
        // $this->isActive = true;
    }

    public function getId(): string
    {
        return $this->id;
    }

    // TODO: Add your business logic methods here
    // Example:
    // public function activate(): void
    // {
    //     $this->isActive = true;
    // }
    //
    // public function deactivate(): void
    // {
    //     $this->isActive = false;
    // }
    //
    // public function changeName(string $name): void
    // {
    //     if (empty($name)) {
    //         throw new \InvalidArgumentException('Name cannot be empty');
    //     }
    //     $this->name = $name;
    // }
}
