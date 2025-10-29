<?php

namespace Foremind\DumpServer\Server;

use Foremind\DumpServer\Protocol\WebSocketFrameEncoder;
use Foremind\DumpServer\Storage\DumpStorage;
use Illuminate\Support\Collection;
use JsonException;
use Override;

class WebSocketServer extends BaseServer
{
    /** @param  Collection<int, resource>|null  $clients */
    public function __construct(
        private readonly DumpStorage $storage,
        public ?Collection $clients = null,
        private ?WebSocketFrameEncoder $encoder = null
    ) {
        parent::__construct(true);
        $this->clients ??= collect();
        $this->encoder = new WebSocketFrameEncoder;
        $this->storage->onDumpAdded(fn ($dump, $total) => $this->broadcastNewDump($dump, $total));
        $this->storage->onCleared(fn () => $this->broadcastCleared());
    }

    /**
     * @param  array<resource>  $readSockets
     *
     * @throws JsonException
     */
    #[Override]
    public function handleRead(array $readSockets): void
    {
        // Accept new WebSocket connections
        if (in_array($this->socket, $readSockets, true)) {
            $client = stream_socket_accept($this->socket, 0);
            if ($client) {
                $this->handleHandshake($client);
            }
        }

        // Handle WebSocket messages
        $this->clients->each(function ($client, $key) use ($readSockets) {
            if (in_array($client, $readSockets, true) && ! fread($client, 1024)) {
                fclose($client);
                $this->clients->forget($key);
            }
        });
    }

    /**
     * @param  resource  $client
     *
     * @throws JsonException
     */
    private function handleHandshake(mixed $client): void
    {
        $request = fread($client, 1024);

        if (! preg_match('/Sec-WebSocket-Key: (.*)$/m', $request, $matches)) {
            fclose($client);

            return;
        }

        $key = trim($matches[1]);
        $acceptKey = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

        fwrite($client, $response);
        $this->clients->push($client);

        echo "🔌 WebSocket client connected\n";

        $this->sendInitialDumps($client);
    }

    /**
     * @param  resource  $client
     *
     * @throws JsonException
     */
    private function sendInitialDumps(mixed $client): void
    {
        $this->sendToClient($client, [
            'type' => 'initial_dumps',
            'dumps' => $this->storage->all(),
            'total_dumps' => $this->storage->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $dump
     *
     * @throws JsonException
     */
    private function broadcastNewDump(array $dump, int $total): void
    {
        $this->broadcast([
            'type' => 'new_dump',
            'dump' => $dump,
            'total_dumps' => $total,
        ]);
    }

    /**
     * @throws JsonException
     */
    private function broadcastCleared(): void
    {
        $this->broadcast([
            'type' => 'dumps_cleared',
            'total_dumps' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    private function broadcast(array $data): void
    {
        $message = $this->encoder->encode(json_encode($data, JSON_THROW_ON_ERROR));

        $this->clients->each(function ($client, $key) use ($message) {
            if (! @fwrite($client, $message)) {
                fclose($client);
                $this->clients->forget($key);
            }
        });
    }

    /**
     * @param  resource  $client
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    private function sendToClient(mixed $client, array $data): void
    {
        fwrite($client, $this->encoder->encode(json_encode($data, JSON_THROW_ON_ERROR)));
    }
}
