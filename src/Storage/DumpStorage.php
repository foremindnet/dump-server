<?php

namespace Foremind\DumpServer\Storage;

use Illuminate\Support\Collection;

class DumpStorage
{
    private array $onDumpAddedCallbacks = [];

    private array $onClearedCallbacks = [];

    public function __construct(
        private readonly int $maxDumps = 50,
        private ?Collection $dumps = null
    ) {
        $this->dumps ??= collect();
    }

    public function addDump(string $content, array $context = []): void
    {
        $dump = [
            'time' => date('Y-m-d H:i:s.u'),
            'content' => trim($content),
            'context' => $context,
        ];

        $this->dumps->push($dump);

        if ($this->dumps->count() > $this->maxDumps) {
            $this->dumps = $this->dumps->slice(-$this->maxDumps);
        }

        echo 'New dump received at '.$dump['time']."\n";

        foreach ($this->onDumpAddedCallbacks as $callback) {
            $callback($dump, $this->dumps->count());
        }
    }

    public function clear(): void
    {
        $this->dumps = collect();

        foreach ($this->onClearedCallbacks as $callback) {
            $callback();
        }
    }

    public function all(): array
    {
        return $this->dumps->all();
    }

    public function count(): int
    {
        return $this->dumps->count();
    }

    public function isEmpty(): bool
    {
        return $this->dumps->isEmpty();
    }

    public function reversed(): Collection
    {
        return $this->dumps->reverse();
    }

    public function onDumpAdded(callable $callback): void
    {
        $this->onDumpAddedCallbacks[] = $callback;
    }

    public function onCleared(callable $callback): void
    {
        $this->onClearedCallbacks[] = $callback;
    }
}
