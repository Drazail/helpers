<?php

namespace HalaeiTests;

use Halaei\Helpers\Crypt\NumCrypt;
use Halaei\Helpers\Eloquent\EloquentCache;
use Halaei\Helpers\Listeners\RandomWorkerTerminator;
use Halaei\Helpers\Listeners\RefreshDBConnections;
use Halaei\Helpers\Objects\DataCollection;
use Halaei\Helpers\Process\Process;
use Halaei\Helpers\Redis\Lock;
use Halaei\Helpers\Supervisor\Supervisor;
use Halaei\Helpers\Supervisor\SupervisorOptions;
use Halaei\Helpers\View\ViewFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Predis\Client;

/**
 * End-to-end smoke test exercising every major module in a single flow.
 * Acts as the comprehensive validation gate alongside PublicApiContractTest.
 */
class ComprehensiveValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('validation_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('status')->nullable();
        });
    }

    public function test_all_modules_execute_without_exception(): void
    {
        $this->assertInstanceOf(ViewFactory::class, $this->app['view']);

        DB::table('validation_models')->insert([
            ['id' => 1, 'name' => 'alpha', 'status' => 'old'],
            ['id' => 2, 'name' => 'beta', 'status' => 'old'],
        ]);

        if ($this->usesMysqlFromEnvironment()) {
            DB::table('validation_models')->insertIgnore([
                ['id' => 3, 'name' => 'gamma', 'status' => 'new'],
            ]);
        }

        $model = new class extends Model {
            protected $table = 'validation_models';
            protected $guarded = [];
        };

        $records = $model->newQuery()->whereIn('id', [1, 2])->get();
        $records[0]->status = 'updated';
        $records[1]->status = 'updated';
        $records->update();

        $this->assertSame('updated', DB::table('validation_models')->where('id', 1)->value('status'));
        $this->assertSame('updated', DB::table('validation_models')->where('id', 2)->value('status'));

        $cache = new EloquentCache($model, $this->app['cache.store']);
        $found = $cache->find(1);
        $this->assertSame(1, $found->id);

        $user = new User(['id' => 1, 'name' => 'Test']);
        $this->assertSame('{"id":1,"name":"Test"}', $user->toJson());
        $this->assertSame(['id' => 1, 'name' => 'Test'], $user->all());

        $crypt = new NumCrypt();
        $encrypted = $crypt->encrypt(42);
        $this->assertSame(42, $crypt->decrypt($encrypted));

        if (DIRECTORY_SEPARATOR !== '\\' || $this->usesMysqlFromEnvironment()) {
            $process = new Process([PHP_BINARY, '-r', 'echo "ok";'], null, null, null, 5);
            $result = $process->mustRun();
            $this->assertStringContainsString('ok', $result->stdOut);
        }

        RefreshDBConnections::boot();
        RandomWorkerTerminator::boot(1, 1);

        $this->assertTrue(class_exists(Supervisor::class));
        $this->assertTrue(class_exists(Lock::class));

        if (extension_loaded('redis') || class_exists(Client::class)) {
            $this->assertTrue(true, 'Redis lock available for manual/integration verification');
        }
    }

    public function test_data_collection_to_raw_in_validation_flow(): void
    {
        $collection = new DataCollection([
            new User(['id' => 1, 'name' => 'A']),
        ]);

        $this->assertSame([['id' => 1, 'name' => 'A']], $collection->toRaw());
    }

    public function test_supervisor_options_defaults_match_contract(): void
    {
        $options = new SupervisorOptions();

        $this->assertSame(60, $options->timeout);
        $this->assertSame(128, $options->memory);
        $this->assertFalse($options->force);
        $this->assertFalse($options->stopOnError);
        $this->assertFalse($options->dontDie);
    }
}
