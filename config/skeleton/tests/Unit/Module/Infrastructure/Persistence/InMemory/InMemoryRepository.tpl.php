<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use <?= $entity_namespace; ?>\<?= $entity_class; ?>;
use <?= $port_namespace; ?>\<?= $port_class; ?>;

/**
 * In-Memory implementation of <?= $port_class; ?> for testing
 *
 * Fast, isolated tests without database
 */
final class <?= $class_name; ?> implements <?= $port_class; ?>

{
    /** @var <?= $entity_class; ?>[] */
    private array $entities = [];

    public function save(<?= $entity_class; ?> $<?= $entity_var; ?>): void
    {
        $this->entities[$<?= $entity_var; ?>->getId()->value] = $<?= $entity_var; ?>;
    }

    public function findById(string $id): ?<?= $entity_class; ?>

    {
        return $this->entities[$id] ?? null;
    }

    public function delete(<?= $entity_class; ?> $<?= $entity_var; ?>): void
    {
        unset($this->entities[$<?= $entity_var; ?>->getId()->value]);
    }

    /**
     * Helper method for tests
     *
     * @return <?= $entity_class; ?>[]
     */
    public function all(): array
    {
        return array_values($this->entities);
    }

    /**
     * Helper method for tests
     */
    public function clear(): void
    {
        $this->entities = [];
    }

    /**
     * Helper method for tests
     */
    public function count(): int
    {
        return count($this->entities);
    }
}
