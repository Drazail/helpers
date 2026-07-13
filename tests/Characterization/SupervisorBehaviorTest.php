<?php

namespace HalaeiTests\Characterization;

use Carbon\Carbon;
use Halaei\Helpers\Supervisor\Events\LoopBeginning;
use Halaei\Helpers\Supervisor\Events\LoopCompleting;
use Halaei\Helpers\Supervisor\Events\RunSucceed;
use Halaei\Helpers\Supervisor\Events\SupervisorStopping;
use Halaei\Helpers\Supervisor\Supervisor;
use Halaei\Helpers\Supervisor\SupervisorOptions;
use Halaei\Helpers\Supervisor\SupervisorState;
use HalaeiTests\SupervisorStub;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Characterization tests for Supervisor loop behavior (pre-migration baseline).
 */
class SupervisorBehaviorTest extends TestCase
{
    private $laravel;
    private $cache;
    private $bus;
    private $events;
    private $exceptions;
    private $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laravel = Mockery::mock(Application::class);
        $this->cache = Mockery::mock(Cache::class);
        $this->bus = Mockery::mock(Bus::class);
        $this->events = Mockery::mock(Events::class);
        $this->exceptions = Mockery::mock(ExceptionHandler::class);

        $this->supervisor = new SupervisorStub(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0);
        }
        parent::tearDown();
    }

    public function test_pauses_when_app_is_in_maintenance_mode(): void
    {
        $runs = 0;

        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn(new SupervisorState());
        $this->cache->shouldReceive('get')->with('illuminate:queue:restart')->twice()->andReturn(null, Carbon::now());
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(true);
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) {
            return $event instanceof SupervisorStopping && $event->status === 0;
        }))->andThrow(new \Exception('stopped'));

        try {
            $this->supervisor->supervise(function () use (&$runs) {
                $runs++;
            });
            $this->fail('Expected supervisor to stop');
        } catch (\Exception $e) {
            $this->assertSame('stopped', $e->getMessage());
        }

        $this->assertSame(0, $runs);
        $this->assertSame(1, $this->supervisor->paused);
    }

    public function test_event_listeners_can_pause_and_stop_the_loop(): void
    {
        $runs = 0;

        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn(new SupervisorState());
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(false);
        $this->cache->shouldReceive('get')->twice()->with('illuminate:queue:restart')->andReturn(null);

        $this->events->shouldReceive('until')->once()->with(Mockery::on(function ($event) {
            return $event instanceof LoopBeginning;
        }))->andReturn(false);

        $this->events->shouldReceive('until')->once()->with(Mockery::on(function ($event) {
            return $event instanceof LoopCompleting;
        }))->andReturn(false);

        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) {
            return $event instanceof SupervisorStopping && $event->status === 0;
        }))->andThrow(new \Exception('stopped'));

        try {
            $this->supervisor->supervise(function () use (&$runs) {
                $runs++;
            });
            $this->fail('Expected supervisor to stop');
        } catch (\Exception $e) {
            $this->assertSame('stopped', $e->getMessage());
        }

        $this->assertSame(0, $runs);
        $this->assertSame(1, $this->supervisor->paused);
    }

    public function test_runs_once_before_queue_restart_signal(): void
    {
        $runs = 0;

        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn(new SupervisorState());
        $this->cache->shouldReceive('get')->with('illuminate:queue:restart')->once()->andReturn(null);
        $this->cache->shouldReceive('get')->with('illuminate:queue:restart')->once()->andReturn(Carbon::now());
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(false);

        $this->events->shouldReceive('until')->once()->with(Mockery::on(function ($event) {
            return $event instanceof LoopBeginning;
        }))->andReturnNull();

        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) {
            return $event instanceof RunSucceed;
        }))->andReturnNull();

        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) {
            return $event instanceof SupervisorStopping && $event->status === 0;
        }))->andThrow(new \Exception('stopped'));

        try {
            $this->supervisor->supervise(function () use (&$runs) {
                $runs++;
            }, new SupervisorOptions());
            $this->fail('Expected supervisor to stop');
        } catch (\Exception $e) {
            $this->assertSame('stopped', $e->getMessage());
        }

        $this->assertSame(1, $runs);
        $this->assertSame(0, $this->supervisor->paused);
    }
}
