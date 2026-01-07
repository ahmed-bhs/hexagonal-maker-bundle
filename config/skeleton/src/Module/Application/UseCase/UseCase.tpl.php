<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $command_namespace ?>\<?= $command_class ?>;
use <?= $response_namespace ?>\<?= $response_class ?>;
use <?= $repository_namespace ?>\<?= $repository_class ?>;

/**
 * Use Case (Application Service)
 *
 * Orchestrates domain logic to fulfill a specific business use case.
 * Contains no business logic itself - delegates to domain objects.
 *
 * Responsibilities:
 * - Receive command/query from UI layer
 * - Load domain entities via repositories
 * - Delegate business logic to domain entities
 * - Persist changes via repositories
 * - Return response/DTO to UI layer
 *
 * Alternative to CommandHandler/QueryHandler pattern.
 */
final readonly class <?= $class_name ?>

{
    public function __construct(
        private <?= $repository_class ?> $repository,
        // TODO: Add other dependencies (services, event dispatcher, etc.)
    ) {
    }

    public function execute(<?= $command_class ?> $command): <?= $response_class ?>

    {
        // TODO: Implement use case logic
        // Example:
        //
        // 1. Load domain entity
        // $entity = $this->repository->findById($command->id);
        // if (!$entity) {
        //     throw EntityNotFoundException::withId($command->id);
        // }
        //
        // 2. Execute business logic (in domain entity)
        // $entity->performBusinessAction($command->data);
        //
        // 3. Persist changes
        // $this->repository->save($entity);
        //
        // 4. Return response
        // return new <?= $response_class ?>($entity);

        throw new \RuntimeException('Use case not implemented yet');
    }
}
