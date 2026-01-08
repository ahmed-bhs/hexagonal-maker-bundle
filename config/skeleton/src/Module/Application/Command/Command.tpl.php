<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

/**
 * CQRS Command.
 *
 * Represents an intention to perform a write operation.
 * Commands should be immutable and contain all the data needed to execute the action.
 */
final readonly class <?= $class_name; ?>

{
    public function __construct(
<?php if (!empty($properties)): ?>
<?php foreach ($properties as $prop): ?>
        public <?= $prop['phpType'] ?> $<?= $prop['name'] ?>,
<?php endforeach; ?>
<?php else: ?>
        // TODO: Add your command properties here
        // Example:
        // public string $name,
        // public string $email,
<?php endif; ?>
    ) {
    }
}
