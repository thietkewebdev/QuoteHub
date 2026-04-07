<?php

namespace Tests\Unit;

use App\Models\Quotation;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuotationLifecycleLabelsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function void_policy_shows_void_status(): void
    {
        $q = new Quotation([
            'pricing_policy' => Quotation::PRICING_POLICY_VOID,
            'approved_at' => now(),
        ]);

        $this->assertTrue($q->isVoidPolicy());
        $this->assertSame(__('Void'), $q->approvalStatusLabel());
    }

    #[Test]
    public function pending_when_not_approved(): void
    {
        $q = new Quotation([
            'pricing_policy' => Quotation::PRICING_POLICY_STANDARD,
            'approved_at' => null,
        ]);

        $this->assertSame(__('Pending approval'), $q->approvalStatusLabel());
        $this->assertSame('warning', $q->approvalStatusColor());
    }

    #[Test]
    public function expired_when_valid_until_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10'));

        $q = new Quotation([
            'pricing_policy' => Quotation::PRICING_POLICY_STANDARD,
            'approved_at' => now()->subDay(),
            'valid_until' => Carbon::parse('2026-04-09'),
        ]);

        $this->assertTrue($q->isValidUntilExpired());
        $this->assertSame(__('Expired'), $q->approvalStatusLabel());
    }
}
