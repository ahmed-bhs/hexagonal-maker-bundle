<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $aggregate_namespace ?>\<?= $aggregate_type ?>;

/**
 * Domain Entity Factory
 *
 * Responsible for creating complex domain aggregates.
 * Encapsulates the creation logic and ensures invariants are met.
 */
final readonly class <?= $class_name ?>

{
    public function __construct(
        // Inject your dependencies here (e.g., ID generator, clock, etc.)
    ) {
    }

    public function create(<?= $command_type ?>Command $command): <?= $aggregate_type ?>

    {
        // TODO: Implement factory logic
        // Example:
        // return new <?= $aggregate_type ?>(
        //     id: $this->idGenerator->generate(),
        //     name: $command->name,
        //     email: $command->email,
        // );

        throw new \RuntimeException('Factory not yet implemented');
    }
}
