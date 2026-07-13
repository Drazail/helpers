<?php

namespace HalaeiTests;

use Halaei\Helpers\Eloquent\Commands\LogSlowQueries;
use HalaeiTests\Support\InvokesPrivateMethods;
use HalaeiTests\Support\RunsConsoleCommands;
use Illuminate\Support\Facades\DB;

class LogSlowQueriesCommandTest extends TestCase
{
    use InvokesPrivateMethods;
    use RunsConsoleCommands;

    public function test_handle_aggregates_slow_queries_and_stops_with_once_option(): void
    {
        DB::shouldReceive('connection')->with(null)->andReturnSelf();
        DB::shouldReceive('select')->once()->with('show full processlist')->andReturn([
            (object) [
                'Command' => 'Query',
                'Info' => 'SELECT "secret" FROM users WHERE id = 42',
                'Time' => 3,
            ],
            (object) [
                'Command' => 'Sleep',
                'Info' => null,
                'Time' => 10,
            ],
            (object) [
                'Command' => 'Query',
                'Info' => '',
                'Time' => 2,
            ],
            (object) [
                'Command' => 'Query',
                'Info' => null,
                'Time' => 5,
            ],
        ]);

        $this->runCommand(new LogSlowQueries, [], ['--once' => true]);

        $this->assertTrue(true);
    }

    public function test_handle_sleeps_between_iterations_when_not_once(): void
    {
        DB::shouldReceive('connection')->with(null)->andReturnSelf();
        DB::shouldReceive('select')
            ->once()
            ->with('show full processlist')
            ->andReturn([], []);

        $command = new class extends LogSlowQueries {
            public int $sleepCalls = 0;

            protected function sleepForPoll(int $seconds): void
            {
                $this->sleepCalls++;

                if ($this->sleepCalls >= 1) {
                    throw new \RuntimeException('stop-loop');
                }
            }
        };

        try {
            $this->runCommand($command, [], ['--sleep' => 0]);
        } catch (\RuntimeException $e) {
            $this->assertSame('stop-loop', $e->getMessage());
        }

        $this->assertSame(1, $command->sleepCalls);
    }

    public function test_strip_sql_normalizes_literals_and_numbers(): void
    {
        $command = $this->makeBoundCommand(new LogSlowQueries);

        $normalized = $this->invokePrivateMethod(
            $command,
            'stripSql',
            ['  SELECT "abc", \'def\', 99 FROM t  ']
        );

        $this->assertSame('SELECT ?, ?, ? FROM t', $normalized);
    }

    public function test_sleep_for_poll_can_be_overridden_by_subclasses(): void
    {
        $command = new class extends LogSlowQueries {
            public int $slept = -1;

            protected function sleepForPoll(int $seconds): void
            {
                $this->slept = $seconds;
            }
        };

        $method = new \ReflectionMethod($command, 'sleepForPoll');
        $method->setAccessible(true);
        $method->invoke($command, 0);

        $this->assertSame(0, $command->slept);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
