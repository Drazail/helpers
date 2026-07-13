<?php

namespace HalaeiTests\Characterization;

use HalaeiTests\TestCase;

/**
 * Runtime verification that EloquentServiceProvider registers documented macros.
 */
class MacroRegistrationTest extends TestCase
{
    public function test_eloquent_macros_are_registered_after_provider_boot(): void
    {
        $this->assertTrue(
            \Illuminate\Database\Eloquent\Collection::hasMacro('update'),
            'Collection::update macro must be registered'
        );
        $this->assertTrue(
            \Illuminate\Database\Query\Builder::hasMacro('batchUpdate'),
            'Builder::batchUpdate macro must be registered'
        );
        $this->assertTrue(
            \Illuminate\Database\Query\Builder::hasMacro('insertIgnore'),
            'Builder::insertIgnore macro must be registered'
        );
    }
}
