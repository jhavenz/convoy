<?php

declare(strict_types=1);

namespace Convoy\WebSocket;

use Convoy\Service\ServiceBundle;
use Convoy\Service\Services;

final class WsServiceBundle implements ServiceBundle
{
    public function __construct(
        private readonly array $subprotocols = [],
    ) {
    }

    public function services(Services $services, array $context): void
    {
        $services->singleton(WsGateway::class)
            ->factory(static fn() => new WsGateway());

        $subprotocols = $this->subprotocols;
        $services->singleton(WsHandshake::class)
            ->factory(static fn() => new WsHandshake($subprotocols));
    }
}
