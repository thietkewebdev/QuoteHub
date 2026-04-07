<?php

namespace Tests\Unit;

use App\Support\Locale\ProductLineSpecsSplitter;
use Tests\TestCase;

class ProductLineSpecsSplitterTest extends TestCase
{
    public function test_moves_text_after_paren_into_specs(): void
    {
        $raw = 'Máy in mã vạch HPRT HT330(USB+LAN+ COM) Độ phân giải 300 dpi cho bản in sắc nét, rõ ràng. Tốc độ in tối đa 100 mm/giây.';
        [$name, $specs] = ProductLineSpecsSplitter::split($raw, '', 60);

        $this->assertStringContainsString('HPRT HT330', $name);
        $this->assertStringContainsString('USB+LAN', $name);
        $this->assertStringContainsString('Độ phân giải', $specs);
        $this->assertStringNotContainsString('Độ phân giải', $name);
    }

    public function test_skips_short_lines(): void
    {
        $raw = 'Short product';
        [$name, $specs] = ProductLineSpecsSplitter::split($raw, '', 80);

        $this->assertSame($raw, $name);
        $this->assertSame('', $specs);
    }
}
