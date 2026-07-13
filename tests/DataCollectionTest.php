<?php

namespace HalaeiTests;

use Halaei\Helpers\Objects\DataCollection;
use Illuminate\Contracts\Support\Arrayable;

class DataCollectionTest extends TestCase
{
    public function test_to_raw_returns_scalar_values(): void
    {
        $collection = new DataCollection(['plain']);

        $this->assertSame(['plain'], $collection->toRaw());
    }

    public function test_to_raw_uses_to_array_for_arrayable_values(): void
    {
        $collection = new DataCollection([new ArrayableValue(['key' => 'value'])]);

        $this->assertSame([['key' => 'value']], $collection->toRaw());
    }

    public function test_fuse_merges_items_with_duplicate_keys(): void
    {
        $left = new DataCollection([
            new User(['id' => 1, 'name' => 'A']),
        ]);
        $right = new DataCollection([
            new User(['id' => 1, 'name' => 'B']),
        ]);

        $fused = $left->fuse($right, 'id');

        $this->assertCount(1, $fused);
        $this->assertSame('B', $fused->first()->name);
    }

    public function test_union_by_keeps_existing_keys_only(): void
    {
        $left = new DataCollection([
            new User(['id' => 1, 'name' => 'A']),
        ]);
        $right = new DataCollection([
            new User(['id' => 1, 'name' => 'B']),
            new User(['id' => 2, 'name' => 'C']),
        ]);

        $union = $left->unionBy($right, 'id');

        $this->assertCount(2, $union);
        $this->assertSame('A', $union->firstWhere('id', 1)->name);
        $this->assertSame('C', $union->firstWhere('id', 2)->name);
    }
}

class ArrayableValue implements Arrayable
{
    public function __construct(private array $value)
    {
    }

    public function toArray()
    {
        return $this->value;
    }
}
