<?php

namespace Foremind\DumpServer;

use Foremind\DumpServer\Server\DumpServer;
use Foremind\DumpServer\Server\HttpServer;
use Foremind\DumpServer\Server\WebSocketServer;
use Foremind\DumpServer\Storage\DumpStorage;
use JsonException;

class DumpWebServer
{
    private DumpServer $dumpServer;

    private HttpServer $httpServer;

    private WebSocketServer $webSocketServer;

    public function __construct(protected int $basePort)
    {
        $storage = new DumpStorage(maxDumps: 50);
        $this->dumpServer = new DumpServer(storage: $storage);
        $this->webSocketServer = new WebSocketServer(storage: $storage);
        $this->httpServer = new HttpServer(wsServer: $this->webSocketServer, storage: $storage);
    }

    /** @return array<resource> */
    private function getAllReadSockets(): array
    {
        return array_merge(
            [$this->httpServer->socket, $this->webSocketServer->socket, $this->dumpServer->socket],
            $this->webSocketServer->clients->all(),
            $this->dumpServer->clients->all()
        );
    }

    public function initialize(): void
    {
        $this->dumpServer->initialize();
        $this->httpServer->initialize();
        $this->webSocketServer->initialize();
    }

    /** @throws JsonException */
    public function handleConnections(bool $isShuttingDown = false): bool
    {
        $read = $this->getAllReadSockets();
        $write = $except = [];

        $result = @stream_select($read, $write, $except, 0, 100000);

        if ($result === false) {
            $error = error_get_last();
            if ($error && (
                str_contains($error['message'], 'Interrupted system call') ||
                str_contains($error['message'], 'interrupted') ||
                $isShuttingDown
            )) {
                return false;
            }
        }

        if ($result > 0) {
            $this->dumpServer->handleRead($read);
            $this->httpServer->handleRead($read);
            $this->webSocketServer->handleRead($read);
        }

        return true;
    }

    public function getDumpServer(): DumpServer
    {
        return $this->dumpServer;
    }

    public function getHttpServer(): HttpServer
    {
        return $this->httpServer;
    }

    public function getWebSocketServer(): WebSocketServer
    {
        return $this->webSocketServer;
    }
}
