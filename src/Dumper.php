<?php

namespace Foremind\DumpServer;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Server\Connection;

readonly class Dumper
{
    public function __construct(private ?Connection $connection = null) {}

    public function dump(mixed $value): void
    {
        if (class_exists(CliDumper::class)) {
            $data = (new VarCloner)->cloneVar($value);

            if ($this->connection === null || $this->connection->write($data) === false) {
                $dumper = in_array(PHP_SAPI, ['cli', 'phpdbg']) ? new CliDumper : new HtmlDumper;
                $dumper->dump($data);
            }
        }
    }
}
