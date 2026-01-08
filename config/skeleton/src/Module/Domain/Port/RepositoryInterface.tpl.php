<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $entity_namespace ?>\<?= $entity_name ?>;

/**
 * Repository Port (Interface)
 *
 * This is a port in hexagonal architecture - an interface that defines
 * the contract for persistence operations without coupling to infrastructure.
 *
 * The application layer depends on this abstraction, not on concrete implementations.
 */
interface <?= $class_name ?>

{
    public function save(<?= $entity_name ?> $<?= strtolower($entity_name) ?>): void;

    public function findById(string $id): ?<?= $entity_name ?>;

    public function delete(<?= $entity_name ?> $<?= strtolower($entity_name) ?>): void;

    /**
     * @return <?= $entity_name ?>[]
     */
    public function findAll(): array;
<?php if (!empty($properties)): ?>
<?php foreach ($properties as $prop): ?>
<?php if ($prop['unique'] ?? false): ?>

    public function findBy<?= ucfirst($prop['name']) ?>(<?= $prop['phpType'] ?> $<?= $prop['name'] ?>): ?<?= $entity_name ?>;

    public function existsBy<?= ucfirst($prop['name']) ?>(<?= $prop['phpType'] ?> $<?= $prop['name'] ?>): bool;
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
}
