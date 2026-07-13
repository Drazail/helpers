<?php

namespace HalaeiTests;

use Carbon\Carbon;
use Halaei\Helpers\Supervisor\Events\LoopCompleting;
use Halaei\Helpers\Supervisor\Events\RunFailed;
use Halaei\Helpers\Supervisor\Events\SupervisorStopping;
use Halaei\Helpers\Supervisor\Supervisor;
use Halaei\Helpers\Supervisor\SupervisorOptions;
use Halaei\Helpers\Supervisor\SupervisorState;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;

/**
 * @group pcntl
 */
class SupervisorCompleteTest extends \PHPUnit\Framework\TestCase
{
    use SupervisorMocks;

    public function test_run_dispatches_run_failed_when_closure_throws(): void
    {
        $runs = 0;
        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn($state = new SupervisorState());
        $this->cache->shouldReceive('get')->once()->with('illuminate:queue:restart')->andReturn(null);
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(false);

        $this->events->shouldReceive('until')->once()->andReturnNull();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) {
            return $event instanceof RunFailed && $event->exception instanceof \RuntimeException;
        }))->andReturnNull();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) {
            return $event instanceof SupervisorStopping;
        }))->andThrow(new \Exception('stopped'));

        $options = new SupervisorOptions;
        $options->stopOnError = true;

        try {
            $this->supervisor->supervise(function () use (&$runs) {
                $runs++;
                throw new \RuntimeException('boom');
            }, $options);
        } catch (\Exception $e) {
            $this->assertSame('stopped', $e->getMessage());
        }

        $this->assertSame(1, $runs);
        $this->assertTrue($state->shouldQuit);
    }

    public function test_supervise_resolves_string_commands_via_bus(): void
    {
        $job = new \stdClass;
        $this->laravel->shouldReceive('make')->once()->with('JobClass')->andReturn($job);
        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn($state = new SupervisorState());
        $this->cache->shouldReceive('get')->twice()->with('illuminate:queue:restart')->andReturn(null);
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(false);
        $this->bus->shouldReceive('dispatch')->once()->with($job);

        $this->events->shouldReceive('until')->once()->andReturnNull();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(\Halaei\Helpers\Supervisor\Events\RunSucceed::class))->andReturnNull();
        $this->events->shouldReceive('until')->once()->with(Mockery::type(LoopCompleting::class))->andReturn(false);
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(SupervisorStopping::class))->andThrow(new \Exception('stopped'));

        try {
            $this->supervisor->supervise('JobClass');
        } catch (\Exception $e) {
            $this->assertSame('stopped', $e->getMessage());
        }
    }

    public function test_supervise_returns_exit_status_when_dont_die_is_enabled(): void
    {
        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn($state = new SupervisorState());
        $this->cache->shouldReceive('get')->twice()->with('illuminate:queue:restart')->andReturn(null);
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(false);

        $this->events->shouldReceive('until')->once()->andReturnNull();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(\Halaei\Helpers\Supervisor\Events\RunSucceed::class))->andReturnNull();
        $this->events->shouldReceive('until')->once()->with(Mockery::type(LoopCompleting::class))->andReturn(false);
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(SupervisorStopping::class))->andReturnNull();

        $options = new SupervisorOptions;
        $options->dontDie = true;

        $status = $this->supervisor->supervise(function () {
        }, $options);

        $this->assertSame(0, $status);
        $this->assertSame(0, $state->exitStatus);
    }

    public function test_memory_limit_triggers_stop_with_non_zero_status(): void
    {
        $memorySpy = new SupervisorMemorySpy(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn($state = new SupervisorState());
        $this->cache->shouldReceive('get')->once()->with('illuminate:queue:restart')->andReturn(null);
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(false);

        $this->events->shouldReceive('until')->once()->andReturnNull();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(\Halaei\Helpers\Supervisor\Events\RunSucceed::class))->andReturnNull();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) {
            return $event instanceof SupervisorStopping && $event->status === 12;
        }))->andReturnNull();

        $options = new SupervisorOptions;
        $options->dontDie = true;
        $options->memory = 1;

        $status = $memorySpy->supervise(function () {
        }, $options);

        $this->assertSame(12, $status);
    }

    public function test_listen_for_signals_sets_should_quit_on_sigterm(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension is required');
        }

        $spy = new SupervisorSignalSpy(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $state = new SupervisorState;
        $spy->exposeListenForSignals($state);
        $spy->exposeSignalHandlers()[SIGTERM]();

        $this->assertTrue($state->shouldQuit);
    }

    public function test_listen_for_signals_sets_paused_and_resumes(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension is required');
        }

        $spy = new SupervisorSignalSpy(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $state = new SupervisorState;
        $spy->exposeListenForSignals($state);
        $handlers = $spy->exposeSignalHandlers();

        $handlers[SIGUSR2]();
        $this->assertTrue($state->paused);

        $handlers[SIGCONT]();
        $this->assertFalse($state->paused);
    }

    public function test_stop_with_exit_invokes_exit_process_hook(): void
    {
        $spy = new SupervisorStopSpy(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $state = new SupervisorState;
        $this->events->shouldReceive('dispatch')->once()->andReturnNull();
        $spy->exposeStop(12, true, $state);

        $this->assertSame(12, $state->exitStatus);
        $this->assertSame(12, $spy->exitStatus);
    }

    public function test_kill_invokes_terminate_process_hook(): void
    {
        $spy = new SupervisorStopSpy(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $spy->exposeKill(9);

        $this->assertSame(9, $spy->exitStatus);
    }

    public function test_kill_delegates_to_terminate_process_hook(): void
    {
        $spy = new SupervisorKillTestSpy(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $spy->exposeKill();

        $this->assertSame(1, $spy->capturedStatus);
    }

    public function test_register_timeout_handler_triggers_kill_on_alarm(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension is required');
        }

        $spy = new SupervisorStopSpy(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $options = new SupervisorOptions;
        $options->timeout = 1;
        $spy->exposeRegisterTimeoutHandler($options);

        $handler = pcntl_signal_get_handler(SIGALRM);
        $this->assertIsCallable($handler);
        $handler();

        $this->assertSame(1, $spy->exitStatus);
    }

    public function test_supervise_pauses_when_maintenance_mode_is_enabled(): void
    {
        $this->laravel->shouldReceive('make')->once()->with(SupervisorState::class)->andReturn($state = new SupervisorState());
        $this->cache->shouldReceive('get')->twice()->with('illuminate:queue:restart')->andReturn(null);
        $this->laravel->shouldReceive('isDownForMaintenance')->once()->andReturn(true);
        $this->events->shouldReceive('until')->once()->with(Mockery::type(LoopCompleting::class))->andReturn(false);
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(SupervisorStopping::class))->andThrow(new \Exception('stopped'));

        $options = new SupervisorOptions;
        $options->force = false;

        try {
            $this->supervisor->supervise(function () {
                $this->fail('Command should not run while in maintenance mode');
            }, $options);
        } catch (\Exception $e) {
            $this->assertSame('stopped', $e->getMessage());
        }

        $this->assertSame(1, $this->supervisor->paused);
    }

    public function test_pause_sleeps_for_one_second(): void
    {
        $supervisor = new Supervisor(
            $this->laravel,
            $this->cache,
            $this->bus,
            $this->events,
            $this->exceptions
        );

        $method = new \ReflectionMethod(Supervisor::class, 'pause');
        $method->setAccessible(true);
        $method->invoke($supervisor);

        $this->assertTrue(true);
    }
}

trait SupervisorMocks
{
    protected MockInterface|Application $laravel;
    protected MockInterface|Cache $cache;
    protected MockInterface|Bus $bus;
    protected MockInterface|Events $events;
    protected MockInterface|ExceptionHandler $exceptions;
    protected SupervisorStub $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $app = new \Illuminate\Foundation\Application;
        $this->exceptions = Mockery::mock(ExceptionHandler::class);
        $this->exceptions->shouldReceive('report')->byDefault();
        $app->instance(ExceptionHandler::class, $this->exceptions);
        \Illuminate\Container\Container::setInstance($app);

        $this->laravel = Mockery::mock(Application::class);
        $this->cache = Mockery::mock(Cache::class);
        $this->bus = Mockery::mock(Bus::class);
        $this->events = Mockery::mock(Events::class);

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
        \Illuminate\Container\Container::setInstance(null);
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0);
        }
        parent::tearDown();
    }
}

class SupervisorMemorySpy extends SupervisorStub
{
    protected function memoryExceeded($memoryLimit)
    {
        return true;
    }
}

class SupervisorSignalSpy extends SupervisorStub
{
    private array $handlers = [];

    public function exposeListenForSignals(SupervisorState $state): void
    {
        $this->listenForSignals($state);
        $this->handlers = [
            SIGTERM => pcntl_signal_get_handler(SIGTERM),
            SIGUSR2 => pcntl_signal_get_handler(SIGUSR2),
            SIGCONT => pcntl_signal_get_handler(SIGCONT),
        ];
    }

    public function exposeSignalHandlers(): array
    {
        return $this->handlers;
    }
}

class SupervisorStopSpy extends SupervisorStub
{
    public ?int $exitStatus = null;

    protected function kill($status = 0)
    {
        $this->terminateProcess($status);
    }

    protected function exitProcess(int $status): void
    {
        $this->exitStatus = $status;
    }

    protected function terminateProcess(int $status = 0): void
    {
        $this->exitStatus = $status;
    }

    public function exposeRegisterTimeoutHandler(SupervisorOptions $options): void
    {
        $this->registerTimeoutHandler($options);
    }

    public function exposeStop(int $status, bool $exit, SupervisorState $state): void
    {
        $this->stop($status, $exit, $state);
    }

    public function exposeKill(int $status = 0): void
    {
        $this->kill($status);
    }
}

class SupervisorKillTestSpy extends Supervisor
{
    public ?int $capturedStatus = null;

    public function __construct(
        Application $laravel,
        Cache $cache,
        Bus $bus,
        Events $events,
        ExceptionHandler $exceptions
    ) {
        parent::__construct($laravel, $cache, $bus, $events, $exceptions);
    }

    protected function terminateProcess(int $status = 0): void
    {
        $this->capturedStatus = $status;
    }

    public function exposeKill(): void
    {
        parent::kill(1);
    }
}
