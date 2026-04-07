<?php

namespace Tests\Unit;

use App\Support\Supplier\QuotationContactPersonParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuotationContactPersonParserTest extends TestCase
{
    #[DataProvider('samples')]
    public function test_parse(string $raw, ?array $expected): void
    {
        $this->assertSame($expected, QuotationContactPersonParser::parse($raw));
    }

    public function test_parse_phone_only_uses_translated_contact_label(): void
    {
        $parsed = QuotationContactPersonParser::parse('0912345678');
        $this->assertSame(__('Contact'), $parsed['name']);
        $this->assertSame('0912345678', $parsed['phone']);
    }

    public function test_parse_normalizes_country_code_84(): void
    {
        $parsed = QuotationContactPersonParser::parse('+84 916 789 025');
        $this->assertSame(__('Contact'), $parsed['name']);
        $this->assertSame('0916789025', $parsed['phone']);
    }

    /**
     * @return iterable<string, array{0: string, 1: array{name: string, phone: ?string}|null}>
     */
    public static function samples(): iterable
    {
        yield 'comma name phone' => [
            'Hoàng Yến, 0916789025',
            ['name' => 'Hoàng Yến', 'phone' => '0916789025'],
        ];

        yield 'name only' => [
            'Ms. Thu Hà',
            ['name' => 'Ms. Thu Hà', 'phone' => null],
        ];

        yield 'empty' => ['', null];
    }
}
