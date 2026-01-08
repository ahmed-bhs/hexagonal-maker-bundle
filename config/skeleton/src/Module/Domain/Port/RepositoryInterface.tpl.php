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
    /**
     * Persist an entity to the storage.
     */
    public function save(<?= $entity_name ?> $<?= strtolower($entity_name) ?>): void;

    /**
     * Find an entity by its identifier.
     */
    public function find(string $id): ?<?= $entity_name ?>;

    /**
     * Retrieve all entities.
     *
     * @return <?= $entity_name ?>[]
     */
    public function findAll(): array;
}
