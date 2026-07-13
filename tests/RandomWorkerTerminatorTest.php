<?php

namespace HalaeiTests;

use Halaei\Helpers\Listeners\RandomWorkerTerminator;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Worker;

class RandomWorkerTerminatorTest extends TestCase
{
    public function test_handle_stops_worker_after_ttl_expires(): void
    {
        $worker = \Mockery::mock(Worker::class);
        $worker->shouldReceive('stop')->once();
        $this->app->instance(Worker::class, $worker);

        $terminator = new RandomWorkerTerminator(0, 0);
        $this->setTerminatorClock($terminator, start: 0, ttl: 0);

        $terminator->handle();
        event(new Looping('default', 'default'));

        $this->addToAssertionCount(1);
    }

    public function test_handle_does_not_stop_worker_before_ttl(): void
    {
        $worker = \Mockery::mock(Worker::class);
        $worker->shouldReceive('stop')->never();
        $this->app->instance(Worker::class, $worker);

        $terminator = new RandomWorkerTerminator(3600, 3600);
        $this->setTerminatorClock($terminator, start: time(), ttl: 3600);

        $terminator->handle();
        event(new Looping('default', 'default'));

        $this->addToAssertionCount(1);
    }

    public function test_boot_stops_worker_after_ttl(): void
    {
        $worker = \Mockery::mock(Worker::class);
        $worker->shouldReceive('stop')->once();
        $this->app->instance(Worker::class, $worker);

        RandomWorkerTerminator::boot(0, 0);

        sleep(1);
        event(new Looping('default', 'default'));

        $this->addToAssertionCount(1);
    }

    public function test_boot_registers_looping_listener(): void
    {
        RandomWorkerTerminator::boot(0, 0);

        event(new Looping('default', 'default'));

        $this->addToAssertionCount(1);
    }

    private function setTerminatorClock(RandomWorkerTerminator $terminator, int $start, int $ttl): void
    {
        $reflection = new \ReflectionClass($terminator);

        $startProperty = $reflection->getProperty('start');
        $startProperty->setAccessible(true);
        $startProperty->setValue($terminator, $start);

        $ttlProperty = $reflection->getProperty('timeToLive');
        $ttlProperty->setAccessible(true);
        $ttlProperty->setValue($terminator, $ttl);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
