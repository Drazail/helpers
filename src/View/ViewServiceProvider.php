<?php

namespace Halaei\Helpers\View;

class ViewServiceProvider extends \Illuminate\View\ViewServiceProvider
{
    /**
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @param  \Illuminate\View\ViewFinderInterface  $finder
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return \Illuminate\View\Factory
     */
    protected function createFactory($resolver, $finder, $events)
    {
        return new ViewFactory($resolver, $finder, $events);
    }
}
