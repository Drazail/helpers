<?php

namespace Halaei\Helpers\Eloquent\Commands;

use Halaei\Helpers\Process\Process;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreDumpFromFileSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore-dump {database} {disk} {path} {--mysqlcli=mysql} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore dump from filesystem';

    public function handle()
    {
        $this->restore($this->uncompress($this->mount()));
    }

    private function mount(): string
    {
        $path = $this->argument('path');
        $remote = Storage::disk($this->argument('disk'));
        $local = Storage::disk('local');

        $local->delete($path);

        $stream = $remote->readStream($path);

        if ($stream === false) {
            throw new \RuntimeException("Unable to read dump stream from disk [{$this->argument('disk')}] at path [{$path}].");
        }

        try {
            $local->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $local->path($path);
    }

    private function uncompress(string $src)
    {
        $dst = Str::beforeLast($src, '.tar.gz');
        $process = new Process([
            'tar',
            '-xzf', $src,
        ], dirname($src), null, null, null);
        $process->mustRun();
        unlink($src);
        return $dst;
    }

    private function restore(string $dump)
    {
        // https://dev.mysql.com/doc/refman/8.0/en/mysql-command-options.html
        $command = [
            $this->option('mysqlcli'),
            '--host='.$this->connection()->getConfig('host'),
            '--password='.$this->connection()->getConfig('password'),
            '--port='.$this->connection()->getConfig('port'),
            '--user='.$this->connection()->getConfig('username'),
            '--database='.$this->database(),
            '--compress', // Deprecated as of MySQL 8.0.18
            '--compression-algorithms=zlib,zstd,uncompressed',
            '--default-character-set='.$this->connection()->getConfig('charset'),
        ];
        if ($this->option('force')) {
            $command[] = '--force';
        }
        $process = new Process($command, null, null, $file = fopen($dump, 'rb'), null);
        $process->mustRun();
        fclose($file);
        unlink($dump);
    }

    private function database(): string
    {
        return $this->connection()->getDatabaseName();
    }

    private function connection(): Connection
    {
        return DB::connection($this->argument('database'));
    }
}
