<?php

namespace HalaeiTests\Characterization;

use Halaei\Helpers\Objects\DataCollection;
use HalaeiTests\User;
use PHPUnit\Framework\TestCase;

/**
 * Characterization tests for DataObject and DataCollection behavior.
 */
class DataObjectBehaviorTest extends TestCase
{
    public function test_all_returns_internal_data_array(): void
    {
        $user = new User(['id' => 5, 'name' => 'Test User']);

        $this->assertSame(['id' => 5, 'name' => 'Test User'], $user->all());
    }

    public function test_to_json_encodes_array_representation(): void
    {
        $user = new User(['id' => 5, 'name' => 'Test User']);

        $this->assertSame('{"id":5,"name":"Test User"}', $user->toJson());
    }

    public function test_magic_getter_and_setter_via_call(): void
    {
        $user = new User(['id' => 1, 'name' => 'Before']);
        $user->setName('After');

        $this->assertSame('After', $user->getName());
        $this->assertSame('After', $user->name);
    }

    public function test_magic_isset_and_unset(): void
    {
        $user = new User(['id' => 1, 'name' => 'Exists']);

        $this->assertTrue(isset($user->name));
        unset($user->name);
        $this->assertFalse(isset($user->name));
        $this->assertNull($user->name);
    }

    public function test_bad_method_call_throws(): void
    {
        $user = new User(['id' => 1]);

        $this->expectException(\BadMethodCallException::class);
        $user->unknownMethod();
    }

    public function test_data_collection_to_raw_converts_nested_objects(): void
    {
        $collection = new DataCollection([
            new User(['id' => 1, 'name' => 'A']),
            new User(['id' => 2, 'name' => 'B']),
        ]);

        $this->assertSame([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ], $collection->toRaw());
    }
}
