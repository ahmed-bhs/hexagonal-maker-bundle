<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Base class for all aggregate root identifiers.
 *
 * Provides UUID validation and common behavior for entity IDs.
 */
abstract readonly class AggregateRootId
{
    public function __construct(
        protected string $uuid
    ) {
        if (!Uuid::isValid($uuid)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid UUID format: %s', $uuid)
            );
        }
    }

    public function getValue(): string
    {
        return $this->uuid;
    }

    public function equals(self $other): bool
    {
        return $this->uuid === $other->uuid;
    }

    public function __toString(): string
    {
        return $this->uuid;
    }
}
