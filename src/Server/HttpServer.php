<?php

namespace Foremind\DumpServer\Server;

use Foremind\DumpServer\Protocol\HttpResponseBuilder;
use Foremind\DumpServer\Rendering\HtmlRenderer;
use Foremind\DumpServer\Storage\DumpStorage;
use JsonException;
use Override;

class HttpServer extends BaseServer
{
    public function __construct(
        public WebSocketServer $wsServer,
        private readonly DumpStorage $storage,
        private ?HtmlRenderer $renderer = null
    ) {
        parent::__construct();
        $this->renderer = new HtmlRenderer($storage, $this->wsServer);
    }

    /**
     * @param  array<resource>  $readSockets
     *
     * @throws JsonException
     */
    #[Override]
    public function handleRead(array $readSockets): void
    {
        if (! in_array($this->socket, $readSockets, true)) {
            return;
        }

        $client = stream_socket_accept($this->socket, 0);

        if (! $client) {
            return;
        }

        $this->handleRequest($client);
    }

    /**
     * @param  resource  $client
     *
     * @throws JsonException
     */
    private function handleRequest(mixed $client): void
    {
        $request = $this->readRequest($client);

        if (str_contains($request, 'GET /clear')) {
            $this->storage->clear();
        }

        $html = $this->renderer->render();
        $response = HttpResponseBuilder::build($html);

        fwrite($client, $response);
        fclose($client);
    }

    /** @param resource $client */
    private function readRequest(mixed $client): string
    {
        $request = '';
        while (($line = fgets($client)) !== false) {
            $request .= $line;
            if (trim($line) === '') {
                break;
            }
        }

        return $request;
    }
}
