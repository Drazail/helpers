<?php

namespace HalaeiTests;

use Halaei\Helpers\Listeners\RefreshDBConnections;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;

class RefreshDBConnectionsTest extends TestCase
{
    public function test_handle_rolls_back_open_transactions(): void
    {
        DB::shouldReceive('rollBack')->once()->with(0);

        (new RefreshDBConnections)->handle();

        $this->addToAssertionCount(1);
    }

    public function test_handle_reconnects_when_rollback_fails(): void
    {
        $exception = new \RuntimeException('rollback failed');

        DB::shouldReceive('rollBack')->once()->with(0)->andThrow($exception);
        DB::shouldReceive('reconnect')->once();

        $reported = [];
        $this->app->instance(\Illuminate\Contracts\Debug\ExceptionHandler::class, new class($reported) implements \Illuminate\Contracts\Debug\ExceptionHandler {
            public function __construct(private array &$reported)
            {
            }

            public function report($e)
            {
                $this->reported[] = $e;
            }

            public function shouldReport($e)
            {
                return true;
            }

            public function render($request, $e)
            {
                throw $e;
            }

            public function renderForConsole($output, $e)
            {
                throw $e;
            }
        });

        (new RefreshDBConnections)->handle();

        $this->assertCount(1, $reported);
        $this->assertSame($exception, $reported[0]);
    }

    public function test_boot_registers_queue_looping_listener(): void
    {
        RefreshDBConnections::boot();

        DB::shouldReceive('rollBack')->once()->with(0);

        event(new Looping('default', 'default'));

        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
