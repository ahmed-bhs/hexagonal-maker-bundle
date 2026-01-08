<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

<?php if (!empty($entityNamespace) && !empty($entityName) && !empty($isCollection)): ?>
use <?= $entityNamespace ?>\<?= $entityName ?>;

<?php endif; ?>
/**
 * Query Response
 *
 * Contains the data returned by a query.
 * Should be immutable and contain only the data needed by the client.
 */
final readonly class <?= $class_name; ?>

{
<?php if (!empty($entityName) && !empty($isCollection)): ?>
    /**
     * @param <?= $entityName ?>[] $<?= lcfirst($entityName) ?>s
     */
    public function __construct(
        public array $<?= lcfirst($entityName) ?>s,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn(<?= $entityName ?> $<?= lcfirst($entityName) ?>) => [
                'id' => $<?= lcfirst($entityName) ?>->getId(),
                // TODO: Add other properties to expose
            ],
            $this-><?= lcfirst($entityName) ?>s
        );
    }
<?php elseif (!empty($entityName) && empty($isCollection)): ?>
    public function __construct(
        public <?= $entityName ?> $<?= lcfirst($entityName) ?>,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this-><?= lcfirst($entityName) ?>->getId(),
            // TODO: Add other properties to expose
        ];
    }
<?php elseif (!empty($properties)): ?>
    public function __construct(
<?php foreach ($properties as $prop): ?>
        public <?= $prop['phpType'] ?> $<?= $prop['name'] ?>,
<?php endforeach; ?>
    ) {
    }

    public function toArray(): array
    {
        return [
<?php foreach ($properties as $prop): ?>
            '<?= $prop['name'] ?>' => $this-><?= $prop['name'] ?>,
<?php endforeach; ?>
        ];
    }
<?php else: ?>
    public function __construct(
        // TODO: Add your response properties here
        // Example:
        // public string $id,
        // public string $name,
        // public string $email,
    ) {
    }
<?php endif; ?>
}
