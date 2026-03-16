<?php

declare(strict_types=1);

namespace Convoy\Console;

/**
 * Definition of a command argument.
 */
final readonly class CommandArgument
{
    public function __construct(
        public string $name,
        public string $description = '',
        public bool $required = true,
        public mixed $default = null,
    ) {
    }
}
