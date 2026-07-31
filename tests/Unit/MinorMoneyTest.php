<?php

namespace Tests\Unit;

use App\Domain\Payroll\ValueObjects\MinorMoney;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MinorMoneyTest extends TestCase
{
    public function test_decimal_conversion_and_integer_ratios_use_half_up_rounding(): void
    {
        $this->assertSame(11, MinorMoney::fromDecimal('0.105')->minor);
        $this->assertSame('0.11', MinorMoney::fromDecimal('0.105')->toDecimal());
        $this->assertSame(333, MinorMoney::fromMinor(1000)->multiplyRatio(1, 3)->minor);
        $this->assertSame(125, MinorMoney::fromMinor(1000)->multiplyPpm(125_000)->minor);
    }

    public function test_negative_and_out_of_range_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MinorMoney::fromDecimal('-1.00');
    }

    public function test_arithmetic_rejects_values_that_exceed_the_integer_minor_unit_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MinorMoney::fromMinor(PHP_INT_MAX)->add(MinorMoney::fromMinor(1));
    }
}
