<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\ValueObject;

/**
 * Base class for email value objects.
 *
 * Provides email validation and common behavior.
 */
abstract readonly class EmailValueObject
{
    public function __construct(
        protected string $value
    ) {
        $this->ensureIsValidEmail($value);
    }

    private function ensureIsValidEmail(string $email): void
    {
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                sprintf('The email <%s> is not valid', $email)
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
