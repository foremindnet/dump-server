<?php

namespace Foremind\DumpServer\Server;

use Symfony\Component\VarDumper\Dumper\HtmlDumper as BaseHtmlDumper;

class HtmlDumper extends BaseHtmlDumper
{
    public static function extractHeader(): string
    {
        return (new self)->getDumpHeader();
    }
}
