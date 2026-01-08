<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?php if (!empty($dependencies)): ?>
<?php foreach ($dependencies as $dep): ?>
use <?= $dep['namespace'] ?>;
<?php endforeach; ?>
<?php endif; ?>
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Command Handler.
 *
 * Handles the execution of <?= $command_name ?>.
 * Contains the business logic for this write operation.
<?php if (!empty($pattern_description)): ?>
 *
 * Pattern detected: <?= $pattern_description ?>
<?php endif; ?>
 */
#[AsMessageHandler]
final readonly class <?= $class_name ?>

{
    public function __construct(
<?php if (!empty($dependencies)): ?>
<?php foreach ($dependencies as $i => $dep): ?>
        private <?= $dep['interface'] ?> $<?= $dep['varName'] ?><?= $i < count($dependencies) - 1 ? ',' : '' ?>

<?php endforeach; ?>
<?php else: ?>
        // Inject your dependencies here (repositories, services, etc.)
<?php endif; ?>
    ) {
    }

    public function __invoke(<?= $command_name ?> $command): void
    {
<?= $handler_code ?>
    }
}
