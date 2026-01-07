<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

/**
 * Domain Value Object
 *
 * Represents a domain concept defined by its attributes rather than identity.
 * Value objects are immutable and can be compared by value.
 *
 * In hexagonal architecture, value objects are part of the Domain layer
 * and help enforce domain invariants and encapsulate business rules.
 */
final readonly class <?= $class_name ?>

{
    public function __construct(
        // TODO: Add your value object properties here
        // Example:
        // public string $value,
    ) {
        // TODO: Add validation logic here
        // Example:
        // if (empty($value)) {
        //     throw new \InvalidArgumentException('Value cannot be empty');
        // }
    }

    // TODO: Add comparison and behavior methods
    // Example:
    // public function equals(self $other): bool
    // {
    //     return $this->value === $other->value;
    // }

    // public function toString(): string
    // {
    //     return $this->value;
    // }
}
