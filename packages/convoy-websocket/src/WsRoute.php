<?php

declare(strict_types=1);

namespace Convoy\WebSocket;

use Closure;
use Convoy\Scope;
use Convoy\Task\Scopeable;

final readonly class WsRoute implements Scopeable
{
    public function __construct(
        public Closure $fn,
        public WsConfig $config = new WsConfig(),
    ) {
    }

    public function __invoke(Scope $scope): mixed
    {
        return ($this->fn)($scope);
    }
}
