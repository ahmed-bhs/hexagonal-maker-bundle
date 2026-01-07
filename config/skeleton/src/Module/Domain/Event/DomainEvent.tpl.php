<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

/**
 * Domain Event - Immutable Business Event
 *
 * Represents a fact that happened in the domain.
 * Domain events are immutable and should only contain data (no behavior).
 *
 * This is a DOMAIN event that communicates business facts.
 */
final readonly class <?= $class_name ?>

{
    public function __construct(
        // TODO: Add event properties here
        // Example:
        // public string $orderId,
        // public string $customerId,
        // public \DateTimeImmutable $occurredAt,
    ) {
    }

    // Optional: Factory methods for better semantics
    // public static function create(string $orderId, string $customerId): self
    // {
    //     return new self(
    //         orderId: $orderId,
    //         customerId: $customerId,
    //         occurredAt: new \DateTimeImmutable(),
    //     );
    // }
}
