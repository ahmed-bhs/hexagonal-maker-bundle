<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input DTO (Data Transfer Object)
 *
 * Used for data validation and transfer from UI layer to Application layer.
 * Contains validation rules using Symfony Validator constraints.
 *
 * This DTO can be used with:
 * - Symfony Forms (Web UI)
 * - API Platform / REST APIs
 * - GraphQL mutations
 * - CLI Commands
 */
final class <?= $class_name ?>

{
    // TODO: Add your input properties with validation constraints
    // Examples:

    // #[Assert\NotBlank(message: 'Title cannot be blank')]
    // #[Assert\Length(
    //     min: 3,
    //     max: 255,
    //     minMessage: 'Title must be at least {{ limit }} characters',
    //     maxMessage: 'Title cannot be longer than {{ limit }} characters'
    // )]
    // public string $title;
    //
    // #[Assert\NotBlank(message: 'Email cannot be blank')]
    // #[Assert\Email(message: 'Email "{{ value }}" is not valid')]
    // public string $email;
    //
    // #[Assert\NotBlank(message: 'Content cannot be blank')]
    // #[Assert\Length(min: 10, minMessage: 'Content must be at least {{ limit }} characters')]
    // public string $content;
    //
    // #[Assert\Type(\DateTimeInterface::class)]
    // public ?\DateTimeInterface $publishedAt = null;
    //
    // #[Assert\Type('boolean')]
    // public bool $isActive = true;
    //
    // #[Assert\Positive]
    // #[Assert\LessThan(value: 1000)]
    // public ?int $quantity = null;
}
