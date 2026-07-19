<?php

namespace Halaei\Helpers\View;

/**
 * Swaps in {@see ViewFactory}, which disables Blade's `@parent` directive.
 *
 * We override only createFactory() (the narrow instantiation hook in
 * Illuminate\View\ViewServiceProvider::registerFactory) so the rest of the
 * framework's factory wiring is inherited unchanged.
 *
 * Upstream reference:
 * https://github.com/laravel/framework/blob/master/src/Illuminate/View/ViewServiceProvider.php
 */
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
