<?php

namespace Tests\Unit;

use App\Support\Locale\VietnameseTextSpacing;
use PHPUnit\Framework\TestCase;

class VietnameseTextSpacingTest extends TestCase
{
    public function test_inserts_space_before_known_latin_units(): void
    {
        $this->assertStringContainsString(
            '300 dpi',
            VietnameseTextSpacing::insertLetterDigitBoundaries('Độ phân giải300dpi')
        );
    }

    public function test_does_not_split_vietnamese_before_digits(): void
    {
        // "đa" + "100" must stay glued for regex pass; LLM fixes "tối đa 100"
        $out = VietnameseTextSpacing::insertLetterDigitBoundaries('tốiđa100mm/giây');
        $this->assertStringContainsString('tốiđa', $out);
        $this->assertStringContainsString('100 mm', $out);
    }

    public function test_ampersand_digit(): void
    {
        $this->assertStringContainsString('& 2D', VietnameseTextSpacing::insertLetterDigitBoundaries('1D &2D'));
    }
}
