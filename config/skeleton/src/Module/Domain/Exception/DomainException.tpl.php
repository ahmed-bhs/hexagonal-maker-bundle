<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

/**
 * Domain Exception
 *
 * Represents a business rule violation in the domain layer.
 * Domain exceptions communicate business errors, not technical errors.
 *
 * Examples:
 * - InvalidEmailException
 * - UserAlreadyExistsException
 * - InsufficientBalanceException
 * - OrderCannotBeCancelledException
 */
class <?= $class_name ?> extends \DomainException
{
    public static function create(string $message): self
    {
        return new self($message);
    }

    // TODO: Add static factory methods for specific error cases
    // Example:
    // public static function emailInvalid(string $email): self
    // {
    //     return new self(sprintf('Email "%s" is invalid', $email));
    // }
}
