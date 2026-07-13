<?php

namespace HalaeiTests;

use Halaei\Helpers\Eloquent\EloquentServiceProvider;
use Halaei\Helpers\View\ViewServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EloquentServiceProvider::class,
            ViewServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        if ($this->usesMysqlFromEnvironment()) {
            $app['config']->set('database.connections.testing', [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'database' => getenv('DB_DATABASE') ?: 'helpers_test',
                'username' => getenv('DB_USERNAME') ?: 'helpers',
                'password' => getenv('DB_PASSWORD') ?: 'secret',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        } else {
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }

        $app['config']->set('cache.default', 'array');
        $app['config']->set('view.paths', [__DIR__.'/fixtures/views']);
    }

    protected function usesMysqlFromEnvironment(): bool
    {
        $host = getenv('DB_HOST');

        return $host !== false && $host !== '';
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir(__DIR__.'/fixtures/views')) {
            mkdir(__DIR__.'/fixtures/views', 0777, true);
        }
    }

    protected function createTestTable(): void
    {
        $this->recreateTable('test_models', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('status')->nullable();
        });
    }

    protected function recreateTable(string $table, callable $callback): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();
        $schema->dropIfExists($table);
        $schema->create($table, $callback);
    }
}
