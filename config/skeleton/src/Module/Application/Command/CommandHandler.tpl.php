<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Command Handler
 *
 * Handles the execution of <?= $command_type; ?>Command.
 * Contains the business logic for this write operation.
 */
#[AsMessageHandler]
final readonly class <?= $class_name; ?>

{
    public function __construct(
        // Inject your dependencies here (repositories, services, etc.)
    ) {
    }

    public function __invoke(<?= $command_type; ?>Command $command): void
    {
        // TODO: Implement your business logic here

        // Example:
        // $entity = new Entity($command->property);
        // $this->repository->save($entity);
    }
}
