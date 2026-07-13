<?php

namespace HalaeiTests;

use Halaei\Helpers\Redis\Lock;
use HalaeiTests\Support\RedisConfig;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Redis\RedisManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\ClientInterface;

class LockInstanceTest extends TestCase
{
    public function test_constructor_rejects_unsupported_clients(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Lock(new \stdClass);
    }

    public function test_instance_returns_lock_with_predis_client(): void
    {
        $predis = \Mockery::mock(ClientInterface::class);
        $connection = \Mockery::mock();
        $connection->shouldReceive('client')->once()->andReturn($predis);

        $manager = \Mockery::mock(RedisManager::class);
        $manager->shouldReceive('connection')->once()->with('default')->andReturn($connection);

        $app = new \Illuminate\Foundation\Application;
        $app->instance(RedisManager::class, $manager);
        $app->instance('redis', $manager);
        \Illuminate\Container\Container::setInstance($app);

        $lock = Lock::instance('default');

        $this->assertInstanceOf(Lock::class, $lock);
    }

    public function test_instance_wraps_phpredis_clients(): void
    {
        $phpredis = new class {
            public function eval($script, $args, $numKeys)
            {
                return 'token';
            }

            public function brpoplpush($source, $destination, $timeout)
            {
                return 'token';
            }

            public function expire($key, $seconds)
            {
                return true;
            }
        };

        $connection = \Mockery::mock();
        $connection->shouldReceive('client')->once()->andReturn($phpredis);

        $manager = \Mockery::mock(RedisManager::class);
        $manager->shouldReceive('connection')->once()->with(null)->andReturn($connection);

        $app = new \Illuminate\Foundation\Application;
        $app->instance(RedisManager::class, $manager);
        $app->instance('redis', $manager);
        \Illuminate\Container\Container::setInstance($app);

        $lock = Lock::instance();
        $this->assertInstanceOf(Lock::class, $lock);
        $this->assertTrue($lock->lock('phpredis', 2));
    }

    #[Group('redis')]
    public function test_lock_uses_brpoplpush_fallback_when_eval_returns_false(): void
    {
        $redis = \Mockery::mock(ClientInterface::class);
        $redis->shouldReceive('eval')->once()->andReturn(false);
        $redis->shouldReceive('brpoplpush')->once()->with('fallback2', 'fallback1', 2)->andReturn('token');
        $redis->shouldReceive('expire')->once()->with('fallback1', 2)->andReturn(true);

        $lock = new Lock($redis);

        $this->assertTrue($lock->lock('fallback', 2));
    }

    #[Group('redis')]
    public function test_lock_integration_with_real_redis(): void
    {
        try {
            $client = RedisConfig::client();
            $client->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis is not available');
        }

        $client->flushdb();
        $lock = new Lock($client);

        $this->assertTrue($lock->lock('integration', 2));
        $lock->unlock('integration');
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        \Illuminate\Container\Container::setInstance(null);
        parent::tearDown();
    }
}
