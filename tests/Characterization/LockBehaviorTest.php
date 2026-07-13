<?php

namespace HalaeiTests\Characterization;

use Halaei\Helpers\Redis\Lock;
use HalaeiTests\Support\RedisConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * Characterization tests for Redis lock contention behavior.
 */
#[Group('redis')]
class LockBehaviorTest extends TestCase
{
    private $redis;
    private $lock;

    protected function setUp(): void
    {
        if (! $this->redisIsAvailable()) {
            $this->markTestSkipped('Redis is not available');
        }

        parent::setUp();

        $this->redis = $this->getRedisClient();
        $this->redis->flushdb();
        $this->lock = new Lock($this->redis);
    }

    protected function tearDown(): void
    {
        if ($this->redis) {
            $this->redis->flushdb();
        }

        parent::tearDown();
    }

    public function test_lock_unlock_round_trip(): void
    {
        $this->assertTrue($this->lock->lock('char-test', 2));
        $this->lock->unlock('char-test');
        $this->assertTrue($this->lock->lock('char-test', 2));
    }

    public function test_block_executes_callback_and_releases_lock(): void
    {
        $value = $this->lock->block('char-block', function () {
            return 'executed';
        });

        $this->assertSame('executed', $value);
        $this->assertTrue($this->lock->lock('char-block', 2), 'Lock must be released after block()');
    }

    public function test_second_lock_attempt_fails_while_first_is_held(): void
    {
        $this->assertTrue($this->lock->lock('contention', 2));
        $this->assertFalse($this->lock->lock('contention', 1));
        $this->lock->unlock('contention');
    }

    private function redisIsAvailable(): bool
    {
        try {
            $client = $this->getRedisClient();
            $client->ping();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getRedisClient(): Client
    {
        return RedisConfig::client();
    }
}
