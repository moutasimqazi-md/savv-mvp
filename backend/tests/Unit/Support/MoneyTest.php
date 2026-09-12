<?php

namespace Savv\Tests\Unit\Support;

use InvalidArgumentException;
use Savv\Support\Money;
use Savv\Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_plain_decimal_string(): void
    {
        $money = Money::fromDecimalString('1499.00');
        $this->assertSame(149900, $money->minorUnits);
        $this->assertSame('1499.00', $money->toDecimalString());
    }

    public function test_parses_string_with_currency_symbol_and_thousands_separator(): void
    {
        $money = Money::fromDecimalString('₹1,499.50');
        $this->assertSame(149950, $money->minorUnits);
    }

    public function test_parses_integer_only_amount(): void
    {
        $money = Money::fromDecimalString('500');
        $this->assertSame(50000, $money->minorUnits);
    }

    public function test_rejects_non_numeric_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimalString('free shipping');
    }

    public function test_never_uses_floating_point_for_addition(): void
    {
        $a = Money::fromMinorUnits(1000);
        $b = Money::fromMinorUnits(250);
        $this->assertSame(1250, $a->add($b)->minorUnits);
    }

    public function test_rejects_combining_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinorUnits(100, 'INR')->add(Money::fromMinorUnits(100, 'USD'));
    }
}
