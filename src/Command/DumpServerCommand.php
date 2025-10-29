<?php

namespace Foremind\DumpServer\Command;

use Foremind\DumpServer\DumpWebServer;
use Exception;
use Illuminate\Console\Command;
use JsonException;

class DumpServerCommand extends Command
{
    protected $signature = 'dump-server:start {--port=9912 : Base port for the dump servers}';

    protected $description = 'Start the dump web server with HTTP, WebSocket, and dump servers';

    private bool $shouldStop = false;

    public function handle(): int
    {
        $basePort = (int) $this->option('port');

        $this->info('Starting dump web server...');

        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_signal(SIGHUP, [$this, 'handleShutdown']);
        }

        $server = new DumpWebServer($basePort);

        try {
            $this->startServers($server);
        } catch (Exception $e) {
            $this->error('Failed to start dump server: '.$e->getMessage());

            return parent::FAILURE;
        }

        return parent::SUCCESS;
    }

    /** @throws JsonException */
    private function startServers(DumpWebServer $server): void
    {
        $server->initialize();

        $this->info("✅ Dump server listening on tcp://127.0.0.1:{$server->getDumpServer()->getPort()}");
        $this->info("✅ HTTP server running on http://localhost:{$server->getHttpServer()->getPort()}");
        $this->info("✅ WebSocket server running on ws://localhost:{$server->getWebSocketServer()->getPort()}");
        $this->newLine();

        while (! $this->shouldStop) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $result = $server->handleConnections($this->shouldStop);
            if (! $result) {

                // Signal interruption occurred - continue to next iteration
                // The while condition will handle the shouldStop check
                continue;
            }

            // Small sleep to prevent CPU spinning
            usleep(10000); // 10ms
        }

        $this->info('Shutting down the dump server...');
    }

    public function handleShutdown(?int $signal = null): void
    {
        if ($signal) {
            $this->info("\nReceived signal {$signal}, initiating graceful shutdown...");
        }
        $this->shouldStop = true;
    }
}
