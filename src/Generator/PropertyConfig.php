<?php

declare(strict_types=1);

/*
 * This file is part of the HexagonalMakerBundle package.
 *
 * (c) Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AhmedBhs\HexagonalMakerBundle\Generator;

/**
 * Represents a property configuration for code generation
 */
final class PropertyConfig
{
    private string $name;
    private string $type;
    private bool $nullable;
    private bool $unique;
    private ?int $minLength;
    private ?int $maxLength;
    private mixed $min;
    private mixed $max;
    private ?string $defaultValue;

    public function __construct(
        string $name,
        string $type = 'string',
        bool $nullable = false,
        bool $unique = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        mixed $min = null,
        mixed $max = null,
        ?string $defaultValue = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->nullable = $nullable;
        $this->unique = $unique;
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->min = $min;
        $this->max = $max;
        $this->defaultValue = $defaultValue;
    }

    /**
     * Parse property from string format: "name:type:options"
     * Example: "email:string:unique" or "age:int(0,150)" or "name:string(3,255):required"
     */
    public static function fromString(string $propertyString): self
    {
        // Split by colon but preserve parentheses content
        // Example: "nom:string(3,100)" => ["nom", "string(3,100)"]
        $parts = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($propertyString); $i++) {
            $char = $propertyString[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ':' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $parts[] = trim($current);
        }

        $name = $parts[0] ?? '';
        $typeString = $parts[1] ?? 'string';
        $options = array_slice($parts, 2);

        // Parse type with constraints: int(0,150) or string(3,255)
        $type = $typeString;
        $min = null;
        $max = null;
        $minLength = null;
        $maxLength = null;

        if (preg_match('/^(\w+)\((.+)\)$/', $typeString, $matches)) {
            $type = $matches[1];
            $constraints = explode(',', $matches[2]);

            if ($type === 'string') {
                $minLength = isset($constraints[0]) ? (int) trim($constraints[0]) : null;
                $maxLength = isset($constraints[1]) ? (int) trim($constraints[1]) : null;
            } else {
                $min = isset($constraints[0]) ? trim($constraints[0]) : null;
                $max = isset($constraints[1]) ? trim($constraints[1]) : null;
            }
        }

        // Parse options
        $nullable = in_array('nullable', $options, true);
        $unique = in_array('unique', $options, true);

        return new self(
            name: $name,
            type: $type,
            nullable: $nullable,
            unique: $unique,
            minLength: $minLength,
            maxLength: $maxLength,
            min: $min,
            max: $max
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPhpType(): string
    {
        return match ($this->type) {
            'int', 'integer' => 'int',
            'bool', 'boolean' => 'bool',
            'float', 'decimal' => 'float',
            'datetime', 'date' => '\DateTimeImmutable',
            'email', 'text' => 'string',
            default => 'string',
        };
    }

    public function getDoctrineType(): string
    {
        return match ($this->type) {
            'int', 'integer' => 'integer',
            'bool', 'boolean' => 'boolean',
            'float', 'decimal' => 'decimal',
            'datetime' => 'datetime_immutable',
            'date' => 'date_immutable',
            'text' => 'text',
            'email' => 'string',
            default => 'string',
        };
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function getMin(): mixed
    {
        return $this->min;
    }

    public function getMax(): mixed
    {
        return $this->max;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function getValidationCode(): string
    {
        $validations = [];
        $varName = '$' . $this->name;

        if (!$this->nullable && $this->type === 'string') {
            $validations[] = "if (empty(trim({$varName}))) {";
            $validations[] = "    throw new \\InvalidArgumentException('{$this->name} cannot be empty');";
            $validations[] = "}";
        }

        if ($this->type === 'email') {
            $validations[] = "if (!filter_var({$varName}, FILTER_VALIDATE_EMAIL)) {";
            $validations[] = "    throw new \\InvalidArgumentException('Invalid email format');";
            $validations[] = "}";
        }

        if ($this->min !== null && in_array($this->type, ['int', 'integer', 'float', 'decimal'])) {
            $validations[] = "if ({$varName} < {$this->min}) {";
            $validations[] = "    throw new \\InvalidArgumentException('{$this->name} must be at least {$this->min}');";
            $validations[] = "}";
        }

        if ($this->max !== null && in_array($this->type, ['int', 'integer', 'float', 'decimal'])) {
            $validations[] = "if ({$varName} > {$this->max}) {";
            $validations[] = "    throw new \\InvalidArgumentException('{$this->name} cannot exceed {$this->max}');";
            $validations[] = "}";
        }

        if ($this->minLength !== null && $this->type === 'string') {
            $validations[] = "if (strlen(trim({$varName})) < {$this->minLength}) {";
            $validations[] = "    throw new \\InvalidArgumentException('{$this->name} must be at least {$this->minLength} characters');";
            $validations[] = "}";
        }

        if ($this->maxLength !== null && $this->type === 'string') {
            $validations[] = "if (strlen(trim({$varName})) > {$this->maxLength}) {";
            $validations[] = "    throw new \\InvalidArgumentException('{$this->name} cannot exceed {$this->maxLength} characters');";
            $validations[] = "}";
        }

        return implode("\n        ", $validations);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'phpType' => $this->getPhpType(),
            'doctrineType' => $this->getDoctrineType(),
            'nullable' => $this->nullable,
            'unique' => $this->unique,
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'min' => $this->min,
            'max' => $this->max,
            'defaultValue' => $this->defaultValue,
            'validationCode' => $this->getValidationCode(),
        ];
    }
}
