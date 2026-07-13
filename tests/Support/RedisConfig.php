<?php

namespace HalaeiTests\Support;

use Predis\Client;

class RedisConfig
{
    public static function client(array $overrides = []): Client
    {
        return new Client(array_merge([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'database' => 5,
            'timeout' => 10.0,
        ], $overrides));
    }
}
