<?php

namespace Tests\Unit;

use App\Support\Locale\VietnamesePresentation;
use Tests\TestCase;

class VietnamesePresentationTest extends TestCase
{
    public function test_vnd_formats_with_dots_and_dong(): void
    {
        $this->assertSame('282.366.000 đ', VietnamesePresentation::vnd(282_366_000));
        $this->assertSame('1.000 đ', VietnamesePresentation::vnd(1000));
    }

    public function test_date_from_string_iso_to_vn(): void
    {
        $this->assertSame('02/04/2026', VietnamesePresentation::dateFromString('2026-04-02'));
    }

    public function test_date_from_string_slash_is_day_first_not_us(): void
    {
        // 2 April 2026 in Vietnamese dd/mm — must NOT parse as US Feb 4th
        $this->assertSame('02/04/2026', VietnamesePresentation::dateFromString('2/4/2026'));
        $this->assertSame('02/04/2026', VietnamesePresentation::dateFromString('02/04/2026'));
    }

    public function test_date_from_string_four_february(): void
    {
        $this->assertSame('04/02/2026', VietnamesePresentation::dateFromString('4/2/2026'));
    }
}
