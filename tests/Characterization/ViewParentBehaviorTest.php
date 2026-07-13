<?php

namespace HalaeiTests\Characterization;

use Halaei\Helpers\View\ViewFactory;
use HalaeiTests\TestCase;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\FileViewFinder;
use Illuminate\Filesystem\Filesystem;

/**
 * Characterization test: @parent must NOT stack section content (security fix).
 */
class ViewParentBehaviorTest extends TestCase
{
    public function test_extend_section_replaces_instead_of_appending_parent_content(): void
    {
        $factory = $this->makeViewFactory();

        $factory->startSection('content');
        echo 'child';
        $factory->stopSection();

        $existing = $factory->getSections()['content'] ?? '';

        $method = new \ReflectionMethod(ViewFactory::class, 'extendSection');
        $method->setAccessible(true);
        $method->invoke($factory, 'content', 'parent-should-not-win');

        $this->assertSame($existing, $factory->yieldContent('content'));
        $this->assertStringNotContainsString('parent-should-not-win', $factory->yieldContent('content'));
    }

    public function test_yield_content_returns_default_when_section_missing(): void
    {
        $factory = $this->makeViewFactory();

        $this->assertSame('default-value', $factory->yieldContent('missing', 'default-value'));
    }

    public function test_view_service_provider_registers_custom_view_factory(): void
    {
        $this->assertInstanceOf(ViewFactory::class, $this->app['view']);
    }

    public function test_blade_parent_directive_does_not_stack_in_rendered_output(): void
    {
        $layout = $this->fixturePath('layout.blade.php');
        $child = $this->fixturePath('child.blade.php');

        file_put_contents($layout, <<<'BLADE'
@section('body')
layout-body
@show
BLADE);

        file_put_contents($child, <<<'BLADE'
@extends('layout')
@section('body')
child-body
@parent
@endsection
BLADE);

        $rendered = trim($this->app['view']->file($child)->render());

        $this->assertStringContainsString('child-body', $rendered);
        $this->assertStringNotContainsString('layout-body', $rendered);
    }

    private function makeViewFactory(): ViewFactory
    {
        $files = new Filesystem;
        $resolver = new EngineResolver;
        $resolver->register('php', function () {
            return new PhpEngine;
        });
        $finder = new FileViewFinder($files, [__DIR__.'/../fixtures/views']);

        return new ViewFactory($resolver, $finder, $this->app['events']);
    }

    private function fixturePath(string $name): string
    {
        $dir = __DIR__.'/../fixtures/views';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir.'/'.$name;
    }
}
