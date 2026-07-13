<?php

namespace Halaei\Helpers\Listeners;

class RefreshDBConnections
{
    public function handle()
    {
        try {
            \DB::rollBack(0);
        } catch (\Throwable $e) {
            \DB::reconnect();
            report($e);
        }
    }

    public static function boot()
    {
        \Queue::looping(static::class);
    }
}
