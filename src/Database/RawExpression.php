<?php

declare(strict_types=1);

namespace VelvetCMS\Database;

/**
 * Raw SQL expression.
 * Never pass user input directly; use parameters for user data.
 */
final class RawExpression
{
    public function __construct(
        private readonly string $expression,
        private readonly array $bindings = []
    ) {
    }

    public function getValue(): string
    {
        return $this->expression;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
