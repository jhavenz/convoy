<?php

declare(strict_types=1);

namespace Convoy\Console;

final readonly class CommandOption
{
    public function __construct(
        public string $name,
        public string $shorthand = '',
        public string $description = '',
        public bool $requiresValue = false,
        public mixed $default = null,
    ) {
    }
}
