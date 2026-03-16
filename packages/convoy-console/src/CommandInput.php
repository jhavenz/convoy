<?php

declare(strict_types=1);

namespace Convoy\Console;

final readonly class CommandInput
{
    public function __construct(
        public CommandArgs $args,
        public CommandOptions $options,
    ) {
    }
}
