<?php

declare(strict_types=1);

namespace Convoy\WebSocket\Tests\Integration;

use Convoy\Application;
use Convoy\ExecutionScope;
use Convoy\Stream\Channel;
use Convoy\WebSocket\WsCloseCode;
use Convoy\WebSocket\WsConfig;
use Convoy\WebSocket\WsConnection;
use Convoy\WebSocket\WsConnectionHandler;
use Convoy\WebSocket\WsGateway;
use Convoy\WebSocket\WsMessage;
use Convoy\WebSocket\WsScope;
use Convoy\Http\RouteParams;
use Convoy\Task\Task;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ratchet\RFC6455\Messaging\Frame;
use React\Stream\ThroughStream;

use function React\Async\async;
use function React\Async\await;

final class WsConnectionLifecycleTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = Application::starting()->compile();
    }

    protected function tearDown(): void
    {
        $this->app->shutdown();
    }

    #[Test]
    public function connection_receives_inbound_messages_via_stream(): void
    {
        $received = [];

        $pump = Task::of(static function (ExecutionScope $es) use (&$received): void {
            if (!$es instanceof WsScope) {
                return;
            }

            $es->connection->stream($es)
                ->filter(static fn(WsMessage $m) => $m->isText)
                ->onEach(static function (WsMessage $m) use (&$received): void {
                    $received[] = $m->payload;
                })
                ->take(2)
                ->consume();
        });

        $gateway = new WsGateway();
        $config = new WsConfig(pingInterval: 0);
        $handler = new WsConnectionHandler($pump, $config, $gateway);

        $transport = new ThroughStream();
        $request = new ServerRequest('GET', '/ws/test');
        $scope = $this->app->createScope();

        async(static function () use ($handler, $scope, $transport, $request): void {
            $handler->handle($scope, $transport, $request, new RouteParams([]));
        })();

        $this->sendMaskedText($transport, 'message one');
        $this->sendMaskedText($transport, 'message two');

        $this->assertSame(['message one', 'message two'], $received);
        $this->assertSame(1, $gateway->count());
    }

    #[Test]
    public function connection_sends_outbound_messages_to_transport(): void
    {
        $written = [];

        $pump = Task::of(static function (ExecutionScope $es): void {
            if (!$es instanceof WsScope) {
                return;
            }

            $es->connection->sendText('hello from server');
            $es->connection->close();
        });

        $gateway = new WsGateway();
        $config = new WsConfig(pingInterval: 0);
        $handler = new WsConnectionHandler($pump, $config, $gateway);

        $transport = new ThroughStream();
        $transport->on('data', static function (string $data) use (&$written): void {
            $written[] = $data;
        });

        $request = new ServerRequest('GET', '/ws/test');
        $scope = $this->app->createScope();

        async(static function () use ($handler, $scope, $transport, $request): void {
            $handler->handle($scope, $transport, $request, new RouteParams([]));
        })();

        $this->assertNotEmpty($written, 'Expected outbound data written to transport');
    }

    #[Test]
    public function ws_scope_provides_typed_access(): void
    {
        $capturedScope = null;

        $pump = Task::of(static function (ExecutionScope $es) use (&$capturedScope): void {
            $capturedScope = $es;
            if ($es instanceof WsScope) {
                $es->connection->close();
            }
        });

        $gateway = new WsGateway();
        $config = new WsConfig(pingInterval: 0, maxMessageSize: 1024);
        $handler = new WsConnectionHandler($pump, $config, $gateway);

        $transport = new ThroughStream();
        $request = new ServerRequest('GET', '/ws/chat/lobby', ['Host' => 'localhost']);
        $params = new RouteParams(['room' => 'lobby']);
        $scope = $this->app->createScope();

        async(static function () use ($handler, $scope, $transport, $request, $params): void {
            $handler->handle($scope, $transport, $request, $params);
        })();

        $this->assertInstanceOf(WsScope::class, $capturedScope);
        $this->assertInstanceOf(WsConnection::class, $capturedScope->connection);
        $this->assertSame(1024, $capturedScope->config->maxMessageSize);
        $this->assertSame('/ws/chat/lobby', $capturedScope->request->getUri()->getPath());
        $this->assertSame('lobby', $capturedScope->params->get('room'));
    }

    #[Test]
    public function transport_close_completes_channels(): void
    {
        $pumpCompleted = false;

        $pump = Task::of(static function (ExecutionScope $es) use (&$pumpCompleted): void {
            if (!$es instanceof WsScope) {
                return;
            }

            $es->connection->stream($es)->consume();
            $pumpCompleted = true;
        });

        $gateway = new WsGateway();
        $config = new WsConfig(pingInterval: 0);
        $handler = new WsConnectionHandler($pump, $config, $gateway);

        $transport = new ThroughStream();
        $request = new ServerRequest('GET', '/ws');
        $scope = $this->app->createScope();

        async(static function () use ($handler, $scope, $transport, $request): void {
            $handler->handle($scope, $transport, $request, new RouteParams([]));
        })();

        $transport->close();

        $this->assertTrue($pumpCompleted);
    }

    #[Test]
    public function gateway_tracks_connection_during_lifecycle(): void
    {
        $pump = Task::of(static function (ExecutionScope $es): void {
            if ($es instanceof WsScope) {
                $es->connection->close();
            }
        });

        $gateway = new WsGateway();
        $config = new WsConfig(pingInterval: 0);
        $handler = new WsConnectionHandler($pump, $config, $gateway);

        $transport = new ThroughStream();
        $request = new ServerRequest('GET', '/ws');
        $scope = $this->app->createScope();

        $this->assertSame(0, $gateway->count());

        async(static function () use ($handler, $scope, $transport, $request): void {
            $handler->handle($scope, $transport, $request, new RouteParams([]));
        })();

        $this->assertSame(1, $gateway->count());
    }

    private function sendMaskedText(ThroughStream $transport, string $payload): void
    {
        $frame = new Frame($payload, true, Frame::OP_TEXT);
        $frame->maskPayload();
        $transport->write($frame->getContents());
    }
}
