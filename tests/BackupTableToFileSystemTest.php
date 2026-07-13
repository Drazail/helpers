<?php

namespace HalaeiTests;

use Halaei\Helpers\Eloquent\Commands\BackupTableToFileSystem;
use HalaeiTests\Support\InvokesPrivateMethods;
use HalaeiTests\Support\RunsConsoleCommands;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;

#[Group('unix')]
class BackupTableToFileSystemTest extends TestCase
{
    use InvokesPrivateMethods;
    use RunsConsoleCommands;

    protected function fixtureScript(string $name): string
    {
        $path = __DIR__.'/fixtures/'.$name;
        if (is_file($path)) {
            $contents = file_get_contents($path);
            file_put_contents($path, str_replace("\r\n", "\n", $contents));
            @chmod($path, 0755);
        }

        return $path;
    }

    protected function setCommandDate(BackupTableToFileSystem $command): void
    {
        $property = new \ReflectionProperty($command, 'date');
        $property->setAccessible(true);
        $property->setValue($command, now());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backup');

        $this->fixtureScript('fake-mysqldump.sh');

        $this->recreateTable('backup_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        DB::table('backup_items')->insert([
            ['id' => 1, 'name' => 'alpha'],
            ['id' => 2, 'name' => 'beta'],
        ]);
    }

    public function test_handle_backs_up_compresses_and_uploads_without_truncate(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('tar/mysqldump scripts require Unix');
        }

        $events = [];
        $this->app['events']->listen('db:backup-table:starting', function ($payload) use (&$events) {
            $events[] = ['starting', $payload];
        });
        $this->app['events']->listen('db:backup-table:done', function ($payload) use (&$events) {
            $events[] = ['done', $payload];
        });

        $this->runCommand(new BackupTableToFileSystem, $this->commandArguments(), [
            '--mysqldump' => $this->fixtureScript('fake-mysqldump.sh'),
        ]);

        $files = Storage::disk('backup')->allFiles('archives');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('backup_items.sql.tar.gz', $files[0]);
        $this->assertCount(2, DB::table('backup_items')->get());
        $this->assertCount(2, $events);
    }

    public function test_handle_truncates_and_restores_auto_increment_when_requested(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('tar/mysqldump scripts require Unix');
        }

        if (! $this->usesMysqlFromEnvironment()) {
            $this->markTestSkipped('truncate path requires MySQL');
        }

        $this->runCommand(new BackupTableToFileSystem, $this->commandArguments(), [
            '--mysqldump' => $this->fixtureScript('fake-mysqldump.sh'),
            '--truncate' => true,
            '--auto-increment' => 'id',
        ]);

        $this->assertCount(0, DB::table('backup_items')->get());
        $status = DB::select("SHOW TABLE STATUS LIKE 'backup_items'");
        $this->assertGreaterThanOrEqual(1001, (int) $status[0]->Auto_increment);
    }

    public function test_upload_failure_throws_and_removes_dump_file(): void
    {
        $command = $this->makeBoundCommand(new BackupTableToFileSystem, $this->commandArguments());
        $this->setCommandDate($command);
        $dump = storage_path('app/backup/manual/fail.sql.tar.gz');
        if (! is_dir(dirname($dump))) {
            mkdir(dirname($dump), 0777, true);
        }
        file_put_contents($dump, 'payload');

        Storage::partialMock()->shouldReceive('disk')->with('backup')->andReturn(
            \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class)
                ->shouldReceive('put')->andReturn(false)
                ->getMock()
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upload failed');

        try {
            $this->invokePrivateMethod($command, 'upload', [$dump]);
        } finally {
            $this->assertFileDoesNotExist($dump);
        }
    }

    public function test_set_auto_increment_value_reports_errors_without_aborting(): void
    {
        $command = $this->makeBoundCommand(new BackupTableToFileSystem, [
            'database' => 'testing',
            'table' => 'missing_table',
            'disk' => 'backup',
            'dir' => 'archives',
        ], ['--auto-increment' => 'id']);

        $this->invokePrivateMethod($command, 'setAutoIncrementValue', [5]);

        $this->assertTrue(true);
    }

    public function test_get_auto_increment_value_returns_null_when_option_disabled(): void
    {
        $command = $this->makeBoundCommand(new BackupTableToFileSystem, $this->commandArguments(), [
            '--auto-increment' => '',
        ]);

        $this->assertNull($this->invokePrivateMethod($command, 'getAutoIncrementValue'));
    }

    public function test_log_writes_to_console_and_log_channel(): void
    {
        Log::shouldReceive('info')->once()->with('backup message');

        $command = $this->makeBoundCommand(new BackupTableToFileSystem, $this->commandArguments());
        $this->invokePrivateMethod($command, 'log', ['backup message']);

        $this->addToAssertionCount(1);
    }

    private function commandArguments(): array
    {
        return [
            'database' => 'testing',
            'table' => 'backup_items',
            'disk' => 'backup',
            'dir' => 'archives',
        ];
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
