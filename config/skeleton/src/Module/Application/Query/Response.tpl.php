<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

/**
 * Query Response
 *
 * Contains the data returned by a query.
 * Should be immutable and contain only the data needed by the client.
 */
final readonly class <?= $class_name; ?>

{
    public function __construct(
        // TODO: Add your response properties here
        // Example:
        // public string $id,
        // public string $name,
        // public string $email,
    ) {
    }
}
