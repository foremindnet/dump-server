<?php

namespace Foremind\DumpServer\Server;

use Illuminate\Support\Str;

abstract class BaseServer extends HtmlDumper
{
    public mixed $socket = null;

    protected int $port;

    private static int $basePort = 9912;

    private static int $lastUsedPort = 0;

    public function __construct(protected bool $isBlocking = false)
    {
        parent::__construct();
    }

    public function initialize(): void
    {
        $maxAttempts = 10;
        // if get the highest number between $basePort and last used port + 1
        // if last port used is 0, it will start from $basePort
        $currentPort = max(self::$basePort, self::$lastUsedPort + 1);

        // lets get the server type from the extending class name, e.g., DumpServer, HttpServer, WebSocketServer
        $serverType = Str::of(static::class)
            ->afterLast('\\')
            ->replace('Server', '')
            ->toString();

        for ($i = 0; $i < $maxAttempts; $i++) {
            // we need to set last used port here to avoid race conditions
            self::$lastUsedPort = $currentPort;
            $this->socket = @stream_socket_server($this->getSocketAddress($currentPort), $errno, $errstr);

            if ($this->socket) {
                $this->port = $currentPort;
                stream_set_blocking($this->socket, $this->isBlocking);
                echo "✓ {$serverType} server started on port {$currentPort}\n";
                break;
            }

            $currentPort++;
        }

        if (! $this->socket) {
            exit("Failed to start {$serverType} server after {$maxAttempts} attempts. Last error: {$errstr} ({$errno})\n");
        }
    }

    /** @param  array<resource>  $readSockets */
    abstract public function handleRead(array $readSockets): void;

    protected function getSocketAddress(int $port): string
    {
        return "tcp://0.0.0.0:{$port}";
    }

    protected function configureSocket(): void
    {
        // Override in subclasses if needed
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
