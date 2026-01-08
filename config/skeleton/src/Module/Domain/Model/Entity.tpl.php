<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

/**
 * Domain Entity.
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
<?php if (!empty($properties)): ?>
<?php foreach ($properties as $prop): ?>
    private <?= $prop['phpType'] ?> $<?= $prop['name'] ?>;
<?php endforeach; ?>
<?php else: ?>

    // TODO: Add your domain properties here
    // Example:
    // private string $name;
    // private string $email;
    // private \DateTimeImmutable $createdAt;
    // private bool $isActive = true;
<?php endif; ?>

    public function __construct(
        string $id,
<?php if (!empty($properties)): ?>
<?php foreach ($properties as $prop): ?>
        <?= $prop['phpType'] ?> $<?= $prop['name'] ?>,
<?php endforeach; ?>
<?php else: ?>
        // TODO: Add your domain properties here
<?php endif; ?>
    ) {
        $this->id = $id;
<?php if (!empty($properties)): ?>

        // Domain validation
<?php foreach ($properties as $prop): ?>
<?php if (!empty($prop['validationCode'])): ?>
        <?= $prop['validationCode'] ?>

<?php endif; ?>
<?php endforeach; ?>
        // Initialize properties
<?php foreach ($properties as $prop): ?>
        $this-><?= $prop['name'] ?> = <?php if ($prop['type'] === 'string' || $prop['type'] === 'email' || $prop['type'] === 'text'): ?>trim($<?= $prop['name'] ?>)<?php else: ?>$<?= $prop['name'] ?><?php endif; ?>;
<?php endforeach; ?>
<?php else: ?>
        // TODO: Initialize and validate your domain properties
        // Example:
        // $this->createdAt = new \DateTimeImmutable();
        // $this->isActive = true;
<?php endif; ?>
    }

    public function getId(): string
    {
        return $this->id;
    }
<?php if (!empty($properties)): ?>

    // Getters
<?php foreach ($properties as $prop): ?>

    public function get<?= ucfirst($prop['name']) ?>(): <?= $prop['phpType'] ?>

    {
        return $this-><?= $prop['name'] ?>;
    }
<?php endforeach; ?>

    // Business logic methods
    // Add your domain-specific methods here
<?php else: ?>

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
<?php endif; ?>
}
