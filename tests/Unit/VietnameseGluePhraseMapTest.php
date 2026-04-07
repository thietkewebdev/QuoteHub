<?php

namespace Tests\Unit;

use App\Support\Locale\VietnameseTextSpacing;
use Tests\TestCase;

class VietnameseGluePhraseMapTest extends TestCase
{
    public function test_fixes_sample_quotation_line_like_user_json(): void
    {
        $raw = 'Máy in mãvạch HPRT HT330(USB+LAN+ COM) Độphân giải300dpi cho bản in sắc nét, rõràng. Tốcđộin tốiđa100mm/giây, tiết kiệm thời gian xửlý đơn hàng. Hỗtrợinđa dạng mãvạch1D &2D, bao gồm QR';

        $afterRegex = VietnameseTextSpacing::insertLetterDigitBoundaries($raw);
        $out = VietnameseTextSpacing::applyGluePhraseMap($afterRegex);

        $this->assertStringContainsString('mã vạch', $out);
        $this->assertStringContainsString('Độ phân giải', $out);
        $this->assertStringContainsString('300 dpi', $out);
        $this->assertStringContainsString('giải 300', $out);
        $this->assertStringContainsString('rõ ràng', $out);
        $this->assertStringContainsString('Tốc độ in', $out);
        $this->assertStringContainsString('tối đa', $out);
        $this->assertStringContainsString('100 mm', $out);
        $this->assertStringContainsString('xử lý', $out);
        $this->assertStringContainsString('Hỗ trợ in đa dạng', $out);
        $this->assertStringContainsString('mã vạch 1D', $out);
        $this->assertStringContainsString('& 2D', $out);
    }

    public function test_glue_map_still_matches_when_ocr_text_is_nfd(): void
    {
        if (! class_exists(\Normalizer::class)) {
            $this->markTestSkipped('PHP intl Normalizer not available.');
        }

        $nfd = \Normalizer::normalize('Độphân giải300dpi', \Normalizer::FORM_D);
        $this->assertNotSame('', $nfd);

        $after = VietnameseTextSpacing::insertLetterDigitBoundaries($nfd);
        $out = VietnameseTextSpacing::applyGluePhraseMap($after);

        $this->assertStringContainsString('Độ phân giải', $out);
        $this->assertStringContainsString('300 dpi', $out);
    }
}
