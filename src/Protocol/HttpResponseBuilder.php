<?php

namespace Foremind\DumpServer\Protocol;

class HttpResponseBuilder
{
    public static function build(string $content): string
    {
        return "HTTP/1.1 200 OK\r\n".
            "Content-Type: text/html; charset=UTF-8\r\n".
            'Content-Length: '.strlen($content)."\r\n".
            "Connection: close\r\n\r\n".
            $content;
    }
}
