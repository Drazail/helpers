<?php

namespace HalaeiTests;

use Halaei\Helpers\Eloquent\Cacheable;
use Halaei\Helpers\Eloquent\CacheableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CacheableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('cacheable_trait_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
    }

    public function test_sync_with_db_reloads_model_when_cached(): void
    {
        DB::table('cacheable_trait_models')->insert(['id' => 1, 'name' => 'fresh']);

        $model = SyncableModel::find(1);
        $model->markAsCached();
        $model->name = 'stale';

        $model->syncWithDB();

        $this->assertSame('fresh', $model->name);
        $this->assertFalse($model->isCached());
    }
}

class SyncableModel extends Model implements Cacheable
{
    use CacheableTrait;

    protected $table = 'cacheable_trait_models';

    protected $guarded = [];
}
