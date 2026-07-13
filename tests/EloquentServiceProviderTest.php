<?php

namespace HalaeiTests;

use Halaei\Helpers\Eloquent\EloquentServiceProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class EloquentServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('provider_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('status')->nullable();
        });
    }

    public function test_provider_registers_batch_update_and_insert_ignore_macros(): void
    {
        $this->assertTrue(Collection::hasMacro('update'));
        $this->assertTrue(Builder::hasMacro('batchUpdate'));
        $this->assertTrue(Builder::hasMacro('insertIgnore'));
    }

    public function test_batch_update_macro_updates_multiple_rows(): void
    {
        DB::table('provider_models')->insert([
            ['id' => 1, 'name' => 'a', 'status' => 'old'],
            ['id' => 2, 'name' => 'b', 'status' => 'old'],
        ]);

        $model = new class extends Model {
            protected $table = 'provider_models';
            protected $guarded = [];
        };

        $records = $model->newQuery()->orderBy('id')->get();
        $records[0]->status = 'new';
        $records[1]->status = 'new';
        $records->update();

        $this->assertSame('new', DB::table('provider_models')->where('id', 1)->value('status'));
        $this->assertSame('new', DB::table('provider_models')->where('id', 2)->value('status'));
    }

    public function test_insert_ignore_macro_on_mysql(): void
    {
        if (! $this->usesMysqlFromEnvironment()) {
            $this->markTestSkipped('insertIgnore requires MySQL');
        }

        DB::table('provider_models')->insert(['id' => 1, 'name' => 'first', 'status' => 'ok']);

        $this->assertTrue(
            DB::table('provider_models')->insertIgnore([
                ['id' => 1, 'name' => 'duplicate', 'status' => 'ignored'],
                ['id' => 2, 'name' => 'second', 'status' => 'added'],
            ])
        );

        $this->assertSame('first', DB::table('provider_models')->where('id', 1)->value('name'));
        $this->assertSame('second', DB::table('provider_models')->where('id', 2)->value('name'));
    }

    public function test_insert_ignore_accepts_single_row_shape(): void
    {
        if (! $this->usesMysqlFromEnvironment()) {
            $this->markTestSkipped('insertIgnore requires MySQL');
        }

        $this->assertTrue(DB::table('provider_models')->insertIgnore(['id' => 3, 'name' => 'solo', 'status' => 'ok']));
        $this->assertSame('solo', DB::table('provider_models')->where('id', 3)->value('name'));
    }

    public function test_provider_register_methods_are_callable(): void
    {
        $provider = new EloquentServiceProvider($this->app);
        $provider->registerBatchUpdate();
        $provider->registerInsertIgnore();

        $this->assertTrue(Collection::hasMacro('update'));
        $this->assertTrue(Builder::hasMacro('insertIgnore'));
    }
}
