<?php

namespace HalaeiTests;

use Halaei\Helpers\Supervisor\Supervisor;

class SupervisorStub extends Supervisor
{
    public $paused = 0;

    protected function kill($status = 0)
    {
        throw new \Exception('killed');
    }

    protected function pause()
    {
        $this->paused++;
    }
}
