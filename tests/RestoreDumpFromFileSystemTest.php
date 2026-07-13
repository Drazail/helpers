<?php

namespace HalaeiTests;

use Halaei\Helpers\Eloquent\Commands\RestoreDumpFromFileSystem;
use HalaeiTests\Support\InvokesPrivateMethods;
use HalaeiTests\Support\RunsConsoleCommands;
use Illuminate\Support\Facades\Storage;

/**
 * @group unix
 */
class RestoreDumpFromFileSystemTest extends TestCase
{
    use InvokesPrivateMethods;
    use RunsConsoleCommands;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('remote');
        Storage::fake('local');

        $this->fixtureScript('fake-mysql.sh');
    }

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

    private function mysqlCliOption(): array
    {
        return ['--mysqlcli' => $this->fixtureScript('fake-mysql.sh')];
    }

    public function test_mount_copies_remote_stream_to_local_disk(): void
    {
        Storage::disk('remote')->put('backups/dump.tar.gz', 'remote-archive');

        $command = $this->makeBoundCommand(new RestoreDumpFromFileSystem, $this->commandArguments());
        $path = $this->invokePrivateMethod($command, 'mount');

        $this->assertStringEndsWith('backups/dump.tar.gz', $path);
        $this->assertSame('remote-archive', Storage::disk('local')->get('backups/dump.tar.gz'));
    }

    public function test_mount_throws_when_remote_stream_is_unreadable(): void
    {
        $remote = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $remote->shouldReceive('readStream')->with('missing.tar.gz')->andReturn(false);

        Storage::extend('unreadable', function () use ($remote) {
            return $remote;
        });

        $this->app['config']->set('filesystems.disks.unreadable', [
            'driver' => 'unreadable',
        ]);

        $command = $this->makeBoundCommand(new RestoreDumpFromFileSystem, [
            'database' => 'testing',
            'disk' => 'unreadable',
            'path' => 'missing.tar.gz',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->invokePrivateMethod($command, 'mount');
    }

    public function test_uncompress_extracts_tar_gz_archive(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('tar is not available on Windows');
        }

        $dir = storage_path('framework/testing/restore');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $archive = $dir.'/payload.tar.gz';
        $source = $dir.'/payload.sql';
        file_put_contents($source, '-- sql dump');
        exec(sprintf('tar -czf %s -C %s payload.sql', escapeshellarg($archive), escapeshellarg($dir)));

        $command = $this->makeBoundCommand(new RestoreDumpFromFileSystem, $this->commandArguments());
        $extracted = $this->invokePrivateMethod($command, 'uncompress', [$archive]);

        $this->assertSame($dir.'/payload', $extracted);
        $this->assertFileExists($dir.'/payload.sql');
        $this->assertFileDoesNotExist($archive);
    }

    public function test_restore_pipes_dump_to_mysql_client(): void
    {
        $dump = storage_path('framework/testing/restore/restore.sql');
        if (! is_dir(dirname($dump))) {
            mkdir(dirname($dump), 0777, true);
        }
        file_put_contents($dump, 'SELECT 1;');

        $command = $this->makeBoundCommand(new RestoreDumpFromFileSystem, $this->commandArguments(), $this->mysqlCliOption());
        $this->invokePrivateMethod($command, 'restore', [$dump]);

        $this->assertFileDoesNotExist($dump);
    }

    public function test_restore_includes_force_flag_when_requested(): void
    {
        $dump = storage_path('framework/testing/restore/force.sql');
        if (! is_dir(dirname($dump))) {
            mkdir(dirname($dump), 0777, true);
        }
        file_put_contents($dump, 'SELECT 1;');

        $command = $this->makeBoundCommand(new RestoreDumpFromFileSystem, $this->commandArguments(), array_merge(
            $this->mysqlCliOption(),
            ['--force' => true]
        ));
        $this->invokePrivateMethod($command, 'restore', [$dump]);

        $this->assertFileDoesNotExist($dump);
    }

    public function test_handle_runs_mount_uncompress_and_restore(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('tar is not available on Windows');
        }

        $dir = storage_path('framework/testing/restore-e2e');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $sql = $dir.'/table.sql';
        file_put_contents($sql, 'SELECT 1;');
        $archive = $dir.'/table.sql.tar.gz';
        exec(sprintf('tar -czf %s -C %s table.sql', escapeshellarg($archive), escapeshellarg($dir)));
        $payload = file_get_contents($archive);

        Storage::disk('remote')->put('table.sql.tar.gz', $payload);

        $this->runCommand(new RestoreDumpFromFileSystem, [
            'database' => 'testing',
            'disk' => 'remote',
            'path' => 'table.sql.tar.gz',
        ], $this->mysqlCliOption());

        $this->assertFalse(Storage::disk('local')->exists('table.sql.tar.gz'));
    }

    private function commandArguments(): array
    {
        return [
            'database' => 'testing',
            'disk' => 'remote',
            'path' => 'backups/dump.tar.gz',
        ];
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
