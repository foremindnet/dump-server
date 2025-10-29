<?php

namespace Foremind\DumpServer\Rendering;

use Foremind\DumpServer\Server\HtmlDumper;
use Foremind\DumpServer\Server\WebSocketServer;
use Foremind\DumpServer\Storage\DumpStorage;
use Illuminate\Support\Str;
use JsonException;

readonly class HtmlRenderer
{
    public function __construct(
        private DumpStorage $storage,
        private WebSocketServer $wsServer,
    ) {}

    /** @throws JsonException */
    public function render(): string
    {
        $template = file_get_contents(__DIR__.'/templates/viewer.html');

        return str_replace(
            ['{{WS_PORT}}', '{{DUMPS}}', '{{DUMP_COUNT}}', '{{DUMP_ITEMS}}', '{{SYMFONY_DUMP_HEADER}}'],
            [
                (string) $this->wsServer->getPort(),
                json_encode($this->storage->all(), JSON_THROW_ON_ERROR),
                (string) $this->storage->count(),
                $this->renderDumpItems(),
                HtmlDumper::extractHeader(),
            ],
            $template
        );
    }

    private function renderDumpItems(): string
    {
        if ($this->storage->isEmpty()) {
            return $this->renderEmptyState();
        }

        $html = Str::of('');

        $this->storage->reversed()->each(function ($dump, $index) use (&$html) {
            $time = htmlspecialchars($dump['time'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $content = htmlspecialchars($dump['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $number = $this->storage->count() - $index;

            $html->append(<<<HTML
                <div class="dump-item">
                    <div class="dump-header">#{$number} • {$time}</div>
                    <div class="dump-content"><pre>{$content}</pre></div>
                </div>
            HTML
            );
        });

        return $html->toString();
    }

    private function renderEmptyState(): string
    {
        return <<<'HTML'
            <div class="empty-state">
                <h2>No dumps yet</h2>
                <p>Use <code>dump($variable)</code> in your Laravel application to see output here.</p>
                <p style="margin-top: 1rem;" class="info-text">
                    Server is listening and ready to capture dumps...
                </p>
            </div>
        HTML;
    }
}
