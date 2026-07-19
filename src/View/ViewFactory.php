<?php

namespace Halaei\Helpers\View;

use Illuminate\View\Factory;

/**
 * A view factory that intentionally disables Blade's `@parent` directive.
 *
 * Stock Blade (see Illuminate\View\Concerns\ManagesLayouts) merges a child
 * section into its parent by replacing a `@parent` placeholder. This factory
 * overrides that behaviour so the first `@section` definition always wins and
 * the `@parent` placeholder is never expanded. This is a deliberate design
 * choice carried over from the original package (initial commit, Oct 2016:
 * "disabling `@parent`"), not a temporary framework bug workaround.
 *
 * Upstream reference (method signatures this class overrides):
 * https://github.com/laravel/framework/blob/master/src/Illuminate/View/Concerns/ManagesLayouts.php
 */
class ViewFactory extends Factory
{
    /**
     * Keep the existing (parent) section instead of merging via `@parent`.
     */
    protected function extendSection($section, $content)
    {
        if (isset($this->sections[$section])) {
            $content = $this->sections[$section];
        }
        $this->sections[$section] = $content;
    }

    /**
     * Return the section content as-is, without expanding `@parent`.
     */
    public function yieldContent($section, $default = '')
    {
        $sectionContent = $default;
        if (isset($this->sections[$section])) {
            $sectionContent = $this->sections[$section];
        }
        return $sectionContent;
    }
}
