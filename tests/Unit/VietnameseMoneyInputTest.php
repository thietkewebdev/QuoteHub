<?php

namespace Tests\Unit;

use App\Support\Locale\VietnameseMoneyInput;
use PHPUnit\Framework\TestCase;

class VietnameseMoneyInputTest extends TestCase
{
    public function test_parse_vn_thousands(): void
    {
        $this->assertSame(1_080_000.0, VietnameseMoneyInput::parse('1.080.000'));
        $this->assertSame(282_366_000.0, VietnameseMoneyInput::parse('282.366.000'));
    }

    public function test_parse_plain_float_string(): void
    {
        $this->assertSame(1_080_000.1234, VietnameseMoneyInput::parse('1080000.1234'));
    }

    public function test_parse_vn_decimal_comma(): void
    {
        $this->assertSame(1_080_000.5, VietnameseMoneyInput::parse('1.080.000,5'));
    }

    public function test_format_round_trip(): void
    {
        $this->assertSame('1.080.000', VietnameseMoneyInput::format(1_080_000.0));
        $this->assertSame('1.080.000,5', VietnameseMoneyInput::format(1_080_000.5));
    }
}
