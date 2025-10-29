<?php

namespace Foremind\DumpServer\Protocol;

class WebSocketFrameEncoder
{
    public function encode(string $data): string
    {
        $length = strlen($data);

        if ($length < 126) {
            return chr(0x81).chr($length).$data;
        }

        if ($length < 65536) {
            return chr(0x81).chr(126).pack('n', $length).$data;
        }

        return chr(0x81).chr(127).pack('J', $length).$data;
    }
}
