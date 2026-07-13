<?php

namespace HalaeiTests;

use Halaei\Helpers\Objects\Casting;
use LogicException;

class CastingTest extends TestCase
{
    public function test_cast_throws_logic_exception_for_invalid_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot cast field into the given type');

        Casting::cast('value', 'Definitely\\Missing\\Class', 'field');
    }
}
