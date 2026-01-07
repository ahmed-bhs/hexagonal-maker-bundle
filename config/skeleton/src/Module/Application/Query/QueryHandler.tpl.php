<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Query Handler
 *
 * Handles the execution of <?= $query_type; ?>Query.
 * Contains the read logic to fetch and return data.
 */
#[AsMessageHandler]
final readonly class <?= $class_name; ?>

{
    public function __construct(
        // Inject your dependencies here (repositories, services, etc.)
    ) {
    }

    public function __invoke(<?= $query_type; ?>Query $query): <?= $query_type; ?>Response

    {
        // TODO: Implement your read logic here

        // Example:
        // $data = $this->repository->findById($query->id);
        //
        // return new <?= $query_type; ?>Response(
        //     id: $data->getId(),
        //     name: $data->getName(),
        //     email: $data->getEmail()
        // );

        throw new \RuntimeException('Query handler not yet implemented');
    }
}
