<?php

namespace HalaeiTests;

use Halaei\Helpers\Redis\PhpRedisLockClient;
use PHPUnit\Framework\TestCase;

class PhpRedisLockClientTest extends TestCase
{
    public function test_eval_maps_arguments_to_phpredis_signature(): void
    {
        $redis = new class {
            public array $calls = [];

            public function eval($script, $args, $numKeys)
            {
                $this->calls[] = compact('script', 'args', 'numKeys');

                return true;
            }
        };

        $client = new PhpRedisLockClient($redis);
        $client->eval('script', 2, 'key1', 'key2', '1000');

        $this->assertSame([
            'script' => 'script',
            'args' => ['key1', 'key2', '1000'],
            'numKeys' => 2,
        ], $redis->calls[0]);
    }

    public function test_brpoplpush_and_expire_delegate_to_phpredis(): void
    {
        $redis = new class {
            public array $brpoplpush = [];

            public array $expire = [];

            public function brpoplpush($source, $destination, $timeout)
            {
                $this->brpoplpush = compact('source', 'destination', 'timeout');

                return 'ok';
            }

            public function expire($key, $seconds)
            {
                $this->expire = compact('key', 'seconds');

                return true;
            }
        };

        $client = new PhpRedisLockClient($redis);
        $client->brpoplpush('a', 'b', 2.5);
        $client->expire('lock', 3.2);

        $this->assertSame(['source' => 'a', 'destination' => 'b', 'timeout' => 2], $redis->brpoplpush);
        $this->assertSame(['key' => 'lock', 'seconds' => 3], $redis->expire);
    }
}
