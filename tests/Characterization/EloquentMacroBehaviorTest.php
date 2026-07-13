<?php

namespace HalaeiTests\Characterization;

use HalaeiTests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Characterization tests for Eloquent batchUpdate and insertIgnore macros.
 */
class EloquentMacroBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('macro_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('status')->nullable();
        });
    }

    public function test_batch_update_generates_case_when_sql_and_updates_rows(): void
    {
        DB::table('macro_models')->insert([
            ['id' => 1, 'name' => 'a', 'status' => 'pending'],
            ['id' => 2, 'name' => 'b', 'status' => 'pending'],
        ]);

        $model = new class extends Model {
            protected $table = 'macro_models';
            protected $guarded = [];
        };

        $records = $model->newQuery()->orderBy('id')->get();
        $records[0]->status = 'done';
        $records[1]->status = 'done';

        DB::enableQueryLog();
        $records->update();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $updateSql = collect($queries)->pluck('query')->first(function ($sql) {
            return stripos($sql, 'update') !== false && stripos($sql, 'case') !== false;
        });

        $this->assertNotNull($updateSql, 'batchUpdate must emit CASE WHEN update SQL');
        $this->assertStringContainsStringIgnoringCase('case', $updateSql);
        $this->assertStringContainsStringIgnoringCase('status', $updateSql);
        $this->assertSame('done', DB::table('macro_models')->where('id', 1)->value('status'));
        $this->assertSame('done', DB::table('macro_models')->where('id', 2)->value('status'));
    }

    public function test_collection_update_is_no_op_when_nothing_is_dirty(): void
    {
        DB::table('macro_models')->insert([
            ['id' => 1, 'name' => 'a', 'status' => 'pending'],
        ]);

        $model = new class extends Model {
            protected $table = 'macro_models';
            protected $guarded = [];
        };

        $records = $model->newQuery()->get();

        DB::enableQueryLog();
        $records->update();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertEmpty($queries, 'Collection::update should not query when nothing is dirty');
    }

    public function test_insert_ignore_inserts_rows_without_error(): void
    {
        if (! $this->usesMysqlFromEnvironment()) {
            $this->markTestSkipped('insertIgnore macro requires MySQL');
        }

        DB::table('macro_models')->insert([
            ['id' => 1, 'name' => 'existing', 'status' => 'ok'],
        ]);

        $result = DB::table('macro_models')->insertIgnore([
            ['id' => 1, 'name' => 'duplicate', 'status' => 'ignored'],
            ['id' => 2, 'name' => 'new', 'status' => 'added'],
        ]);

        $this->assertTrue($result);
        $this->assertSame('existing', DB::table('macro_models')->where('id', 1)->value('name'));
        $this->assertSame('new', DB::table('macro_models')->where('id', 2)->value('name'));
    }

    public function test_insert_ignore_returns_true_for_empty_values(): void
    {
        if (! $this->usesMysqlFromEnvironment()) {
            $this->markTestSkipped('insertIgnore macro requires MySQL');
        }

        $this->assertTrue(DB::table('macro_models')->insertIgnore([]));
    }
}
