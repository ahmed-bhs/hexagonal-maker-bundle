<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
<?php if (!empty($repositories)): ?>
<?php foreach ($repositories as $repo): ?>
use <?= $repo['namespace'] ?>;
<?php endforeach; ?>
<?php endif; ?>
<?php if (!empty($entities)): ?>
<?php foreach ($entities as $entity): ?>
use <?= $entity['namespace'] ?>;
<?php endforeach; ?>
<?php endif; ?>

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
<?php if (!empty($repositories)): ?>
<?php foreach ($repositories as $repo): ?>
        private <?= $repo['interface'] ?> $<?= $repo['variable'] ?>,
<?php endforeach; ?>
<?php else: ?>
        // Inject your dependencies here (repositories, services, etc.)
<?php endif; ?>
    ) {
    }

    public function __invoke(<?= $command_type; ?>Command $command): void
    {
<?php if (!empty($pattern)): ?>
<?php if ($pattern === 'create'): ?>
        // Create new <?= $entityName ?? 'Entity' ?> entity
        $<?= strtolower($entityName ?? 'entity') ?> = <?= $entityName ?? 'Entity' ?>::create(
<?php if (!empty($properties)): ?>
<?php foreach ($properties as $prop): ?>
            $command-><?= $prop['name'] ?>,
<?php endforeach; ?>
<?php endif; ?>
        );

        // Save to repository
        $this-><?= strtolower($entityName ?? 'entity') ?>Repository->save($<?= strtolower($entityName ?? 'entity') ?>);
<?php elseif ($pattern === 'update'): ?>
        // Find existing entity
        $<?= strtolower($entityName ?? 'entity') ?> = $this-><?= strtolower($entityName ?? 'entity') ?>Repository->findById($command->id);

        if (!$<?= strtolower($entityName ?? 'entity') ?>) {
            throw new \InvalidArgumentException('<?= $entityName ?? 'Entity' ?> not found');
        }

        // Update entity (add setter methods to entity)
<?php if (!empty($properties)): ?>
<?php foreach ($properties as $prop): ?>
<?php if ($prop['name'] !== 'id'): ?>
        $<?= strtolower($entityName ?? 'entity') ?>->set<?= ucfirst($prop['name']) ?>($command-><?= $prop['name'] ?>);
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

        // Save updated entity
        $this-><?= strtolower($entityName ?? 'entity') ?>Repository->save($<?= strtolower($entityName ?? 'entity') ?>);
<?php elseif ($pattern === 'delete'): ?>
        // Find entity to delete
        $<?= strtolower($entityName ?? 'entity') ?> = $this-><?= strtolower($entityName ?? 'entity') ?>Repository->findById($command->id);

        if (!$<?= strtolower($entityName ?? 'entity') ?>) {
            throw new \InvalidArgumentException('<?= $entityName ?? 'Entity' ?> not found');
        }

        // Delete entity
        $this-><?= strtolower($entityName ?? 'entity') ?>Repository->delete($<?= strtolower($entityName ?? 'entity') ?>);
<?php endif; ?>
<?php else: ?>
        // TODO: Implement your business logic here

        // Example:
        // $entity = new Entity($command->property);
        // $this->repository->save($entity);
<?php endif; ?>
    }
}
