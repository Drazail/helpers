<?php

namespace Halaei\Helpers\Redis;

/**
 * Predis-compatible facade for phpredis clients used by Lock::instance().
 *
 * @internal
 */
final class PhpRedisLockClient
{
    public function __construct(private readonly object $redis)
    {
    }

    /**
     * @param  string  $script
     * @param  int  $numKeys
     * @param  string  ...$arguments
     */
    public function eval($script, $numKeys = 0, ...$arguments)
    {
        return $this->redis->eval($script, array_values($arguments), (int) $numKeys);
    }

    public function brpoplpush($source, $destination, $timeout)
    {
        return $this->redis->brpoplpush($source, $destination, (float) $timeout);
    }

    public function expire($key, $seconds)
    {
        if ((float) $seconds !== (float) (int) $seconds) {
            return $this->redis->pexpire($key, (int) ceil($seconds * 1000));
        }

        return $this->redis->expire($key, (int) $seconds);
    }
}
