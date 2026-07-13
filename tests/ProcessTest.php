<?php

namespace HalaeiTests;

use Halaei\Helpers\Process\Process;
use Halaei\Helpers\Process\ProcessException;
use PHPUnit\Framework\TestCase;

/**
 * @group unix
 */
class ProcessTest extends TestCase
{
    private static $randPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$randPath = __DIR__.'/random.bin';
        file_put_contents(self::$randPath, random_bytes(2000000));
    }

    public static function tearDownAfterClass(): void
    {
        unlink(self::$randPath);
        parent::tearDownAfterClass();
    }

    public function test_pass_large_input_to_process()
    {
        $input = random_bytes(2000000);
        $p = new Process(['php', '-r', 'echo(1);'], null, null, $input, 2);
        $this->assertEquals("1", $p->mustRun()->stdOut);
    }

    public function test_pass_large_input_file_to_process()
    {
        $input = fopen(self::$randPath, 'rb');
        $p = new Process(['php', '-r', 'echo(1);'], null, null, $input, 2);
        $this->assertEquals("1", $p->mustRun()->stdOut);
        fclose($input);
    }

    public function test_get_large_output_from_process()
    {
        $p = new Process(['php', '-r', 'echo(random_bytes(2000000));'], null, null, 2);
        $this->assertEquals(2000000, strlen($p->mustRun()->stdOut));
    }

    public function test_get_large_error_from_process()
    {
        $p = new Process(['php', '-r', 'fwrite(STDERR, random_bytes(2000000));'], null, null, 2);
        $this->assertEquals(2000000, strlen($p->run()->stdErr));
    }

    public function test_process_with_all_large_io()
    {
        $input = random_bytes(2000000);
        $p = new Process(['php', '-r', 'fwrite(STDERR, $r = random_bytes(2000000)); echo($r);'], null, null, $input, 2);
        $result = $p->mustRun();
        $this->assertEquals(2000000, strlen($result->stdErr));
        $this->assertEquals($result->stdErr, $result->stdOut);
    }

    public function test_process_with_all_large_files()
    {
        $input = fopen(self::$randPath, 'rb');
        $p = new Process(['php', '-r', 'fwrite(STDERR, $r = random_bytes(2000000)); echo($r);'], null, null, $input, 2);
        $result = $p->mustRun();
        $this->assertEquals(2000000, strlen($result->stdErr));
        $this->assertEquals($result->stdErr, $result->stdOut);
    }

    public function test_read_large_input()
    {
        $input = random_bytes(2000000);
        $p = new Process(['php', '-r', 'fwrite(STDERR, $r = stream_get_contents(STDIN)); echo($r);'], null, null, $input, 2);
        $result = $p->mustRun();
        $this->assertEquals($input, $result->stdOut);
        $this->assertEquals($input, $result->stdErr);
    }

    public function test_read_large_input_file()
    {
        $input = fopen(self::$randPath, 'rb');
        $p = new Process(['php', '-r', 'fwrite(STDERR, $r = stream_get_contents(STDIN)); echo($r);'], null, null, $input, 2);
        $result = $p->mustRun();
        fclose($input);
        $content = file_get_contents(self::$randPath);
        $this->assertEquals($content, $result->stdOut);
        $this->assertEquals($content, $result->stdErr);
    }

    public function test_read_large_input_chunk_by_chunk()
    {
        $input = random_bytes(2000000);
        $p = new Process(['php', '-r', 'while($r = fread(STDIN, 999)) {fwrite(STDOUT, $r); fwrite(STDERR, $r);}'], null, null, $input, 2);
        $result = $p->mustRun();
        $this->assertEquals($input, $result->stdOut);
        $this->assertEquals($input, $result->stdErr);
    }

    public function test_read_large_input_file_chunk_by_chunk()
    {
        $input = fopen(self::$randPath, 'rb');
        $p = new Process(['php', '-r', 'while($r = fread(STDIN, 999)) {fwrite(STDOUT, $r); fwrite(STDERR, $r);}'], null, null, $input, 2);
        $result = $p->mustRun();
        $content = file_get_contents(self::$randPath);
        $this->assertEquals($content, $result->stdOut);
        $this->assertEquals($content, $result->stdErr);
    }

    public function test_timeout()
    {
        $t = microtime(true);
        $p = new Process(['sleep', '30'], null, null, null, 3);
        $this->assertTrue($p->run()->timedOut);
        $this->assertLessThan(5, microtime(true) - $t);
    }

    public function test_exit_code()
    {
        $error = false;
        $p = new Process(['php', '-r', 'fwrite(STDERR, "Error output."); fwrite(STDOUT, "Standard output."); exit(1);']);
        try {
            $p->mustRun();
        } catch (ProcessException $e) {
            $error = true;
            $this->assertEquals(1, $e->result->exitCode);
            $this->assertEquals('Error output.', $e->result->stdErr);
            $this->assertEquals('Standard output.', $e->result->stdOut);
            $this->assertStringContainsString('Error output.', $e->getMessage());
            $this->assertStringContainsString('Standard output.', $e->getMessage());
        }
        $this->assertTrue($error);
    }

    public function test_reading_zero_after_process_ends()
    {
        $p = new class(['echo', '-n', '0']) extends Process {
            protected function start()
            {
                $started = parent::start();
                sleep(1);
                return $started;
            }
        };
        $this->assertSame('0', $p->run()->stdOut);
    }

    public function test_run_without_timeout()
    {
        $process = new Process(['echo', 'hello'], null, null, null, null);
        $this->assertSame(0, $process->mustRun()->exitCode);
    }

    public function test_must_run_throws_when_process_cannot_start()
    {
        $process = new class(['echo', 'ok']) extends Process {
            protected function start()
            {
                return false;
            }
        };

        try {
            $process->mustRun();
            $this->fail('Expected ProcessException was not thrown');
        } catch (ProcessException $e) {
            $this->assertSame(ProcessException::CODE_START_ERROR, $e->getCode());
        }
    }

    public function test_run_returns_null_when_start_fails()
    {
        $process = new class(['echo', 'ok']) extends Process {
            protected function start()
            {
                return false;
            }
        };

        $this->assertNull($process->run());
    }

    public function test_must_run_throws_when_process_times_out()
    {
        $process = new Process(['sleep', '5'], null, null, null, 1);

        try {
            $process->mustRun();
            $this->fail('Expected ProcessException was not thrown');
        } catch (ProcessException $e) {
            $this->assertSame(ProcessException::CODE_TIMEOUT_ERROR, $e->getCode());
            $this->assertTrue($e->result->timedOut);
        }
    }

    /**
     * @group windows
     */
    public function test_escape_argument_quotes_windows_special_characters()
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Windows-only escapeArgument branch');
        }

        $method = new \ReflectionMethod(Process::class, 'escapeArgument');
        $method->setAccessible(true);

        $this->assertSame('"^&"', $method->invoke(null, '&'));
        $this->assertSame('"a^^b"', $method->invoke(null, 'a^b'));
    }

    public function test_kill_invokes_terminate_process_hook(): void
    {
        $process = new class(['sleep', '1']) extends Process {
            public bool $terminated = false;

            protected function kill($status = 0)
            {
                $this->terminated = true;
                $this->status['running'] = false;
            }
        };

        $reflection = new \ReflectionMethod($process, 'kill');
        $reflection->setAccessible(true);
        $reflection->invoke($process, 1);

        $this->assertTrue($process->terminated);
    }

    public function test_escape_argument_quotes_empty_values_on_unix(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Unix-only escapeArgument branch');
        }

        $method = new \ReflectionMethod(Process::class, 'escapeArgument');
        $method->setAccessible(true);

        $this->assertSame('""', $method->invoke(null, ''));
        $this->assertSame('""', $method->invoke(null, null));
    }

    public function test_timeout_kills_process_when_sigterm_is_ignored(): void
    {
        $process = new Process(['php', '-r', 'pcntl_signal(SIGTERM, SIG_IGN); sleep(30);'], null, null, null, 1);
        $process->waitForKill = 0.1;
        $process->usleep = 1000;

        $result = $process->run();

        $this->assertTrue($result->timedOut);
    }

    public function test_run_returns_null_when_proc_open_fails()
    {
        $process = new Process(['echo', 'ok'], '/definitely/missing/directory');

        $this->assertNull($process->run());
    }

    public function test_timeout_leaves_stderr_for_final_drain_phase(): void
    {
        $process = new Process(
            ['php', '-r', 'fwrite(STDERR, str_repeat("z", 50000)); sleep(5);'],
            null,
            null,
            null,
            1
        );
        $process->waitForKill = 0.2;
        $process->usleep = 1000;

        $result = $process->run();

        $this->assertTrue($result->timedOut);
        $this->assertGreaterThan(0, strlen($result->stdErr));
    }

    public function test_wait_recovers_when_stream_select_fails()
    {
        $process = new Process(['sleep', '1']);
        $start = new \ReflectionMethod($process, 'start');
        $start->setAccessible(true);
        $start->invoke($process);

        $pipesProperty = new \ReflectionProperty($process, 'pipes');
        $pipesProperty->setAccessible(true);
        foreach ($pipesProperty->getValue($process) as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $wait = new \ReflectionMethod($process, 'wait');
        $wait->setAccessible(true);

        $withInput = $wait->invoke($process);
        $this->assertSame([[true, true], [true]], $withInput);

        $inputClosedProperty = new \ReflectionProperty($process, 'inputClosed');
        $inputClosedProperty->setAccessible(true);
        $inputClosedProperty->setValue($process, true);

        $withoutInput = $wait->invoke($process);
        $this->assertSame([[true, true], []], $withoutInput);

        $processProperty = new \ReflectionProperty($process, 'process');
        $processProperty->setAccessible(true);
        $processHandle = $processProperty->getValue($process);
        if (is_resource($processHandle)) {
            proc_terminate($processHandle);
            proc_close($processHandle);
        }
    }

    public function test_wait_handles_stream_select_exceptions()
    {
        $process = new class(['php', '-r', 'echo "ok";']) extends Process {
            protected function wait()
            {
                try {
                    throw new \Exception('select failed');
                } catch (\Exception $e) {
                    usleep($this->usleep);

                    return $this->inputClosed ? [[true, true], []] : [[true, true], [true]];
                }
            }
        };

        $this->assertStringContainsString('ok', $process->mustRun()->stdOut);
    }
}
