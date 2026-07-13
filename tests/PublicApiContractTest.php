<?php

namespace HalaeiTests;

use Halaei\Helpers\Crypt\NumCrypt;
use Halaei\Helpers\Eloquent\Cacheable;
use Halaei\Helpers\Eloquent\CacheableTrait;
use Halaei\Helpers\Eloquent\Commands\BackupTableToFileSystem;
use Halaei\Helpers\Eloquent\Commands\LogSlowQueries;
use Halaei\Helpers\Eloquent\Commands\RestoreDumpFromFileSystem;
use Halaei\Helpers\Eloquent\EloquentCache;
use Halaei\Helpers\Eloquent\EloquentServiceProvider;
use Halaei\Helpers\Eloquent\HasCastables;
use Halaei\Helpers\Eloquent\LogSlowQueries as LogSlowQueriesAlias;
use Halaei\Helpers\Eloquent\SqlState;
use Halaei\Helpers\Listeners\RandomWorkerTerminator;
use Halaei\Helpers\Listeners\RefreshDBConnections;
use Halaei\Helpers\Objects\Casting;
use Halaei\Helpers\Objects\DataCollection;
use Halaei\Helpers\Objects\DataObject;
use Halaei\Helpers\Objects\Rawable;
use Halaei\Helpers\Process\Process;
use Halaei\Helpers\Process\ProcessException;
use Halaei\Helpers\Process\ProcessResult;
use Halaei\Helpers\Redis\Lock;
use Halaei\Helpers\Supervisor\Events\LoopBeginning;
use Halaei\Helpers\Supervisor\Events\LoopCompleting;
use Halaei\Helpers\Supervisor\Events\Looping;
use Halaei\Helpers\Supervisor\Events\RunFailed;
use Halaei\Helpers\Supervisor\Events\RunSucceed;
use Halaei\Helpers\Supervisor\Events\SupervisorStopping;
use Halaei\Helpers\Supervisor\QuitsOnSignals;
use Halaei\Helpers\Supervisor\Supervisor;
use Halaei\Helpers\Supervisor\SupervisorOptions;
use Halaei\Helpers\Supervisor\SupervisorState;
use Halaei\Helpers\View\ViewFactory;
use Halaei\Helpers\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Ensures the public API surface documented in docs/laravel-13-migration-plan.md
 * remains stable across Laravel 10–13 migration releases.
 *
 */
#[CoversNothing]
class PublicApiContractTest extends TestCase
{
    public function test_supervisor_public_api(): void
    {
        $this->assertClassHasPublicMethods(Supervisor::class, ['supervise']);
        $this->assertClassHasPublicConstructor(Supervisor::class, 5);

        $options = new ReflectionClass(SupervisorOptions::class);
        foreach (['timeout', 'memory', 'force', 'stopOnError', 'dontDie'] as $property) {
            $this->assertTrue($options->hasProperty($property), "SupervisorOptions missing property: {$property}");
            $this->assertTrue($options->getProperty($property)->isPublic(), "SupervisorOptions::\${$property} must be public");
        }
        $this->assertClassHasPublicConstructor(SupervisorOptions::class, 5);

        $state = new ReflectionClass(SupervisorState::class);
        foreach (['paused', 'shouldQuit', 'lastRestart', 'exitStatus'] as $property) {
            $this->assertTrue($state->hasProperty($property), "SupervisorState missing property: {$property}");
            $this->assertTrue($state->getProperty($property)->isPublic(), "SupervisorState::\${$property} must be public");
        }
    }

    public function test_supervisor_event_classes_exist(): void
    {
        foreach ([
            Looping::class,
            LoopBeginning::class,
            LoopCompleting::class,
            RunSucceed::class,
            RunFailed::class,
            SupervisorStopping::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "Missing event class: {$class}");
        }
    }

    public function test_quits_on_signals_protected_api(): void
    {
        $this->assertTraitHasProtectedMethods(QuitsOnSignals::class, [
            'listenToSignals', 'stopListeningToSignals', 'quitIfSignaled',
        ]);
    }

    public function test_objects_public_api(): void
    {
        $this->assertTrue(interface_exists(Rawable::class));
        $this->assertInterfaceHasMethod(Rawable::class, 'toRaw');

        $this->assertClassHasPublicMethods(Casting::class, ['cast']);

        $this->assertClassHasPublicMethods(DataObject::class, [
            'relations', 'all', 'toArray', 'toRaw', 'toJson', 'fuse',
            '__call', '__get', '__set', '__isset', '__unset',
        ]);

        $this->assertClassHasPublicMethods(DataCollection::class, [
            'toRaw', 'fuse', 'unionBy',
        ]);
    }

    public function test_eloquent_public_api(): void
    {
        $this->assertTrue(interface_exists(Cacheable::class));
        $this->assertInterfaceHasMethods(Cacheable::class, ['isCached', 'markAsCached', 'syncWithDB']);

        $this->assertTraitHasPublicMethods(CacheableTrait::class, ['isCached', 'markAsCached', 'syncWithDB']);

        $this->assertClassHasPublicMethods(EloquentCache::class, [
            'find', 'findBySecondaryKey', 'update', 'delete', 'invalidateCache', 'forget',
        ]);

        $this->assertTraitHasPublicMethods(HasCastables::class, [
            'bootHasCastables', 'attributesToArray', 'getAttribute', 'setAttribute',
            'prepareSaving', 'offsetUnset',
        ]);

        $this->assertClassHasPublicMethods(SqlState::class, [
            'is_integrity_constraint_violation', 'is_transaction_rollback',
        ]);

        $this->assertTrue(is_subclass_of(LogSlowQueriesAlias::class, LogSlowQueries::class));

        $this->assertClassHasPublicMethods(EloquentServiceProvider::class, [
            'register', 'registerBatchUpdate', 'registerInsertIgnore',
        ]);
    }

    public function test_artisan_command_signatures(): void
    {
        $this->assertCommandSignature(
            LogSlowQueries::class,
            'db:log-slow-queries {--connection=} {--sleep=2} {--once}'
        );
        $this->assertCommandSignature(
            BackupTableToFileSystem::class,
            'db:backup-table {database} {table} {disk} {dir} {--truncate} {--auto-increment=id} {--mysqldump=mysqldump}'
        );
        $this->assertCommandSignature(
            RestoreDumpFromFileSystem::class,
            'db:restore-dump {database} {disk} {path} {--mysqlcli=mysql} {--force}'
        );
    }

    public function test_redis_lock_public_api(): void
    {
        $this->assertClassHasPublicMethods(Lock::class, [
            'instance', 'lock', 'unlock', 'block',
        ]);
        $this->assertClassHasPublicConstructor(Lock::class, 1);
    }

    public function test_listeners_public_api(): void
    {
        $this->assertClassHasPublicMethods(RefreshDBConnections::class, ['handle', 'boot']);
        $this->assertClassHasPublicMethods(RandomWorkerTerminator::class, ['handle', 'boot']);
        $this->assertClassHasPublicConstructor(RandomWorkerTerminator::class, 2);
    }

    public function test_view_public_api(): void
    {
        $this->assertTrue(is_subclass_of(ViewFactory::class, \Illuminate\View\Factory::class));
        $this->assertClassHasPublicMethods(ViewFactory::class, ['yieldContent']);
        $this->assertTrue(is_subclass_of(ViewServiceProvider::class, \Illuminate\View\ViewServiceProvider::class));
        $this->assertClassHasPublicMethods(ViewServiceProvider::class, ['registerFactory']);
        $this->assertClassHasProtectedMethods(ViewServiceProvider::class, ['createFactory']);
    }

    public function test_process_and_crypt_public_api(): void
    {
        $this->assertClassHasPublicMethods(Process::class, ['run', 'mustRun']);
        $this->assertClassHasPublicConstructor(Process::class, 5);

        $result = new ReflectionClass(ProcessResult::class);
        foreach (['exitCode', 'stdOut', 'stdErr', 'timedOut', 'readError'] as $property) {
            $this->assertTrue($result->hasProperty($property), "ProcessResult missing: {$property}");
        }

        $exception = new ReflectionClass(ProcessException::class);
        $this->assertSame(1, $exception->getConstant('CODE_START_ERROR'));
        $this->assertSame(2, $exception->getConstant('CODE_TIMEOUT_ERROR'));
        $this->assertSame(3, $exception->getConstant('CODE_EXIT_CODE_ERROR'));
        $this->assertClassHasPublicMethods(ProcessException::class, ['setResult']);

        $this->assertClassHasPublicMethods(NumCrypt::class, ['encrypt', 'decrypt']);
        $this->assertClassHasPublicConstructor(NumCrypt::class, 2);
    }

    private function assertClassHasPublicMethods(string $class, array $methods): void
    {
        $this->assertTrue(class_exists($class) || trait_exists($class), "Class/trait not found: {$class}");

        $reflection = new ReflectionClass($class);
        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "{$class} missing public method: {$method}"
            );
            $this->assertTrue(
                $reflection->getMethod($method)->isPublic(),
                "{$class}::{$method}() must be public"
            );
        }
    }

    private function assertTraitHasPublicMethods(string $trait, array $methods): void
    {
        $this->assertClassHasPublicMethods($trait, $methods);
    }

    private function assertTraitHasProtectedMethods(string $trait, array $methods): void
    {
        $this->assertClassHasProtectedMethods($trait, $methods);
    }

    private function assertClassHasProtectedMethods(string $class, array $methods): void
    {
        $reflection = new ReflectionClass($class);
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "{$class} missing protected method: {$method}");
            $this->assertTrue(
                $reflection->getMethod($method)->isProtected(),
                "{$class}::{$method}() must be protected"
            );
        }
    }

    private function assertInterfaceHasMethod(string $interface, string $method): void
    {
        $this->assertInterfaceHasMethods($interface, [$method]);
    }

    private function assertInterfaceHasMethods(string $interface, array $methods): void
    {
        $reflection = new ReflectionClass($interface);
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "{$interface} missing method: {$method}");
            $this->assertTrue($reflection->getMethod($method)->isPublic(), "{$interface}::{$method}() must be public");
        }
    }

    private function assertClassHasPublicConstructor(string $class, int $parameterCount): void
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, "{$class} must have a constructor");
        $this->assertTrue($constructor->isPublic(), "{$class}::__construct() must be public");
        $this->assertCount($parameterCount, $constructor->getParameters(), "{$class}::__construct() parameter count mismatch");
    }

    private function assertCommandSignature(string $commandClass, string $expectedSignature): void
    {
        $reflection = new ReflectionClass($commandClass);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        $this->assertSame($expectedSignature, $property->getValue(new $commandClass));
    }
}
