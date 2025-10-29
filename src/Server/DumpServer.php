<?php

namespace Foremind\DumpServer\Server;

use Foremind\DumpServer\Storage\DumpStorage;
use Illuminate\Support\Collection;
use Override;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Throwable;

class DumpServer extends BaseServer
{
    /** @var array<int, string> */
    private array $buffers = [];

    /** @param  Collection<int, resource>|null  $clients */
    public function __construct(
        private readonly DumpStorage $storage,
        public ?Collection $clients = null
    ) {
        parent::__construct();
        $this->clients = collect();
    }

    #[Override]
    protected function getSocketAddress(int $port): string
    {
        return "tcp://127.0.0.1:{$port}";
    }

    /** @param  array<resource>  $readSockets */
    #[Override]
    public function handleRead(array $readSockets): void
    {
        // Accept new connections
        if (in_array($this->socket, $readSockets, true)) {
            $this->acceptNewClient();
        }

        // Read from existing clients
        $this->clients->each(function ($client, $clientId) use ($readSockets) {
            if (in_array($client, $readSockets, true)) {
                $this->readFromClient($clientId, $client);
            }
        });
    }

    private function acceptNewClient(): void
    {
        $client = stream_socket_accept($this->socket, 0);

        if ($client) {
            stream_set_blocking($client, false);
            $clientId = (int) $client;
            $this->clients->put($clientId, $client);
            $this->buffers[$clientId] = '';
            echo "📥 New dump connection\n";
        }
    }

    /** @param resource $client */
    private function readFromClient(int $clientId, mixed $client): void
    {
        $data = fread($client, 65536);

        if ($data === false || $data === '') {
            $this->closeClient($clientId);

            return;
        }

        $this->buffers[$clientId] .= $data;

        if (str_contains($this->buffers[$clientId], "\n")) {
            $this->processDump($this->buffers[$clientId]);
            $this->buffers[$clientId] = '';
        }
    }

    private function processDump(string $rawData): void
    {
        try {
            $decoded = base64_decode($rawData);

            if ((bool) $decoded === false) {
                echo "⚠️ Failed to decode base64 data\n";

                return;
            }

            /**
             * Note, we use 'allowed_classes' to restrict which classes can be unserialized for security.
             *  Only Data, VarCloner, and Stub are allowed to be unserialized.
             */
            $payload = @unserialize($decoded, [
                'allowed_classes' => [
                    Data::class,
                    VarCloner::class,
                    Stub::class,
                ],
            ]);

            if (! is_array($payload) || ! isset($payload[0]) || ! $payload[0] instanceof Data) {
                echo "⚠️ Received non-Data payload\n";

                return;
            }

            $data = $payload[0];
            $context = $payload[1] ?? [];

            ob_start();
            $this->dump($data);
            $html = ob_get_clean();

            $this->storage->addDump($html, $context);
        } catch (Throwable $e) {
            echo '❌ Error processing dump: '.$e->getMessage()."\n";
        }
    }

    private function closeClient(int $clientId): void
    {
        /** @var resource $client */
        $client = $this->clients->get($clientId);
        fclose($client);
        $this->clients->forget([$clientId]);
        unset($this->buffers[$clientId]);
    }
}
