<?php

namespace Tests\Unit;

use App\Support\Supplier\SupplierNameNormalizer;
use PHPUnit\Framework\TestCase;

class SupplierNameNormalizerTest extends TestCase
{
    public function test_normalizes_unicode_and_spacing(): void
    {
        $this->assertSame('công ty tnhh abc', SupplierNameNormalizer::normalize("  CÔNG  TY \n TNHH\tABC  "));
    }

    public function test_empty_after_trim(): void
    {
        $this->assertSame('', SupplierNameNormalizer::normalize('   '));
    }
}
