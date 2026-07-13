<?php

namespace HalaeiTests;

use Halaei\Helpers\Objects\DataCollection;
use Illuminate\Database\Schema\Blueprint;

class HasCastablesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('user_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('mobile')->nullable();
            $table->timestamps();
        });
    }

    public function test_user_model()
    {
        // assign raw value
        $user = new UserModel();
        $this->assertNull($user->mobile);
        $user->mobile = '+98-9121231212';
        $this->assertInstanceOf(Mobile::class, $user->mobile);
        $this->assertEquals('98', $user->mobile->code);
        $this->assertEquals('9121231212', $user->mobile->number);
        $this->assertEquals(['code' => '98', 'number' => '9121231212'], $user->toArray()['mobile']);

        // assign data object
        $user = new UserModel();
        $user->mobile = new Mobile([
            'code' => '98',
            'number' => '9121231212',
        ]);
        $this->assertInstanceOf(Mobile::class, $user->mobile);
        $this->assertEquals('98', $user->mobile->code);
        $this->assertEquals('9121231212', $user->mobile->number);

        // assign in constructor
        $user = new UserModel([
            'mobile' => '+98-9121231212',
        ]);
        $this->assertInstanceOf(Mobile::class, $user->mobile);
        $this->assertEquals('98', $user->mobile->code);
        $this->assertEquals('9121231212', $user->mobile->number);

    }

    public function test_order()
    {
        $order = new OrderModel([
            'items' => [
                [
                    'code' => '#123',
                    'quantity' => 10,
                ],
                [
                    'code' => '#124',
                    'quantity' => 1,
                ],
            ]
        ]);
        $this->assertInstanceOf(DataCollection::class, $order->items);
        $this->assertInstanceOf(Item::class, $order->items[0]);
        $this->assertEquals('#124', $order->items[1]->code);
    }

    public function test_get_casted_attribute_returns_null_for_null_values(): void
    {
        $user = new UserModel;
        $user->setRawAttributes(['mobile' => null]);
        $user->syncOriginal();

        $this->assertNull($user->mobile);
        $this->assertNull($user->mobile);
    }

    public function test_prepare_saving_persists_raw_castable_values(): void
    {
        $user = new UserModel(['mobile' => '+98-9121231212']);
        $user->setConnection('testing');
        $this->assertInstanceOf(Mobile::class, $user->mobile);
        $user->save();

        $this->assertSame('+98-9121231212', $user->getAttributes()['mobile']);
    }

    public function test_get_attribute_falls_back_to_parent_for_non_castable_keys(): void
    {
        $user = new UserModel;
        $user->setConnection('testing');
        $user->setRawAttributes(['id' => 5, 'mobile' => '+98-9121231212']);
        $user->syncOriginal();

        $this->assertSame(5, $user->getAttribute('id'));
    }

    public function test_offset_unset_clears_casted_cache(): void
    {
        $user = new UserModel(['mobile' => '+98-9121231212']);
        $this->assertInstanceOf(Mobile::class, $user->mobile);

        unset($user['mobile']);

        $this->assertNull($user->mobile);
    }
}
