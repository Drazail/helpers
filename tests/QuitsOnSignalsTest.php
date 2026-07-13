<?php

namespace HalaeiTests;

use Halaei\Helpers\Supervisor\QuitsOnSignals;
use HalaeiTests\Support\InvokesPrivateMethods;
use PHPUnit\Framework\Attributes\Group;

#[Group('pcntl')]
class QuitsOnSignalsTest extends TestCase
{
    use InvokesPrivateMethods;

    public function test_listen_and_stop_listening_manage_signal_handlers(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension is required');
        }

        $host = new QuitsOnSignalsHost([SIGUSR1]);

        $this->invokePrivateMethod($host, 'listenToSignals');
        $this->setShouldQuit($host, false);

        $handler = pcntl_signal_get_handler(SIGUSR1);
        if (is_callable($handler)) {
            $handler();
            $this->assertTrue($this->getShouldQuit($host));
        }

        $this->invokePrivateMethod($host, 'stopListeningToSignals');
        $this->assertFalse($this->getShouldQuit($host));
    }

    public function test_quit_if_signaled_exits_when_flag_is_set(): void
    {
        $host = new QuitsOnSignalsHost;
        $this->setShouldQuit($host, true);

        $host->triggerQuitIfSignaled(9);

        $this->assertTrue($host->exited);
        $this->assertSame(9, $host->exitStatus);
    }

    public function test_quit_if_signaled_is_no_op_when_not_signaled(): void
    {
        $host = new QuitsOnSignalsHost;
        $host->triggerQuitIfSignaled();

        $this->assertFalse($host->exited);
    }

    private function setShouldQuit(QuitsOnSignalsHost $host, bool $value): void
    {
        $property = new \ReflectionProperty($host, 'shouldQuit');
        $property->setAccessible(true);
        $property->setValue($host, $value);
    }

    private function getShouldQuit(QuitsOnSignalsHost $host): bool
    {
        $property = new \ReflectionProperty($host, 'shouldQuit');
        $property->setAccessible(true);

        return $property->getValue($host);
    }
}

class QuitsOnSignalsHost
{
    use QuitsOnSignals;

    public bool $exited = false;

    public ?int $exitStatus = null;

    public array $quitOnSignals = [SIGUSR1];

    public function __construct(array $quitOnSignals = [SIGUSR1])
    {
        $this->quitOnSignals = $quitOnSignals;
    }

    public function triggerQuitIfSignaled(int $status = 0): void
    {
        $method = new \ReflectionMethod($this, 'quitIfSignaled');
        $method->setAccessible(true);
        $method->invoke($this, $status);
    }

    protected function exitOnSignal(int $status): void
    {
        $this->exited = true;
        $this->exitStatus = $status;
        $this->shouldQuit = false;
    }
}
