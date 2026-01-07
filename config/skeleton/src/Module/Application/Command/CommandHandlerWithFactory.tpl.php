<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Command Handler with Factory
 *
 * Handles the execution of <?= $command_type; ?>Command.
 * Uses a factory to create domain aggregates.
 */
#[AsMessageHandler]
final readonly class <?= $class_name; ?>

{
    public function __construct(
        private <?= $aggregate_type; ?>Factory $factory,
    ) {
    }

    public function __invoke(<?= $command_type; ?>Command $command): void
    {
        $<?= $aggregate_name; ?> = $this->factory->create($command);

        // TODO: Persist the aggregate
        // Example:
        // $this->repository->save($<?= $aggregate_name; ?>);
    }
}
