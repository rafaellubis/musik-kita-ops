<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\EnrollmentPromotionService;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPromotionClass;
use Tests\TestCase;

class EnrollmentPromotionInvoiceTest extends TestCase
{
    use RefreshDatabase, CreatesPromotionClass;

    #[\PHPUnit\Framework\Attributes\DataProvider('payments')]
    public function test_invoice_is_adjusted_without_losing_payments_or_generating_duplicate_spp(int $paid, bool $legacy = false): void
    {
        extract($this->promotionClass());
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP Basic November', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        if ($legacy) {
            $invoice->update(['enrollment_id' => null]);
            $invoice->items()->firstOrFail()->update(['metadata' => ['package_id' => $source->package_id]]);
        }
        if ($paid) {
            Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0001', 'amount' => $paid, 'method' => 'CASH', 'payment_date' => '2026-10-20', 'created_by' => $actor->id]);
            $invoices->recalcStatus($invoice);
        }
        $number = $invoice->invoice_number;
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $this->assertSame($number, $invoice->fresh()->invoice_number);
        $this->assertEquals($paid ? 340000 : 370000, $invoice->fresh()->total_amount);
        $this->assertEquals($paid, $invoice->fresh()->paid_amount);
        if ($paid) {
            $adjustment = Invoice::where('enrollment_id', $promotion->successor_enrollment_id)->firstOrFail();
            $this->assertEquals(30000, $adjustment->total_amount);
            $this->assertSame('LEVEL_PROMOTION_DIFFERENCE', $adjustment->items->first()->metadata['kind']);
            $this->assertSame($legacy ? null : $source->id, $invoice->fresh()->enrollment_id);
        } else {
            $this->assertSame($promotion->successor_enrollment_id, $invoice->fresh()->enrollment_id);
        }
        $invoices->generateMonthlySPP(2026, 11);
        $invoices->generateMonthlySPP(2026, 11);
        $this->assertSame($paid ? 2 : 1, Invoice::where('student_id', $student->id)->count());
    }

    public function test_voided_promotion_bills_can_be_regenerated_without_duplicate_balance(): void
    {
        extract($this->promotionClass());
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        $payment = Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0001', 'amount' => 340000, 'method' => 'CASH', 'payment_date' => '2026-10-20']);
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $adjustment = Invoice::where('enrollment_id', $promotion->successor_enrollment_id)->firstOrFail();
        $payment->update(['voided_at' => now(), 'voided_by' => $actor->id, 'voided_reason' => 'Koreksi']);
        try {
            $invoices->voidInvoice($invoice->fresh(), $actor, 'Koreksi');
            $this->fail('Tagihan selisih aktif harus diselesaikan terlebih dahulu.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('selisih', $e->getMessage());
        }
        $invoices->voidInvoice($adjustment, $actor, 'Koreksi');
        $invoices->generateMonthlySPP(2026, 11);
        $this->assertEquals(340000, Invoice::notVoid()->where('student_id', $student->id)->sum('total_amount'));
        $invoices->voidInvoice($invoice->fresh(), $actor, 'Koreksi');
        $invoices->generateMonthlySPP(2026, 11);
        $invoices->generateMonthlySPP(2026, 11);
        $this->assertEquals(370000, Invoice::notVoid()->where('student_id', $student->id)->sum('total_amount'));
        $this->assertSame(1, Invoice::notVoid()->where('student_id', $student->id)->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('billingBoundaries')]
    public function test_billing_boundaries_preserve_the_correct_balance(string $case, bool $blocked): void
    {
        extract($this->promotionClass());
        $service = app(InvoiceService::class);
        $invoice = $service->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        if (str_contains($case, 'discount')) {
            $invoice->items()->create(['item_code' => 'DISKON', 'parent_item_id' => $invoice->items()->first()->id, 'description' => 'Diskon', 'amount' => -20000, 'discount_type' => 'NOMINAL', 'discount_value' => 20000, 'discount_reason' => 'Evaluasi']);
        }
        if ($case === 'unpaid_fine') {
            $invoice->items()->create(['item_code' => 'DENDA', 'description' => 'Denda', 'amount' => 5000]);
        }
        if (in_array($case, ['paid_discount', 'void_payment', 'zero_difference'])) {
            Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0001', 'amount' => $case === 'paid_discount' ? 320000 : 340000, 'method' => 'CASH', 'payment_date' => '2026-10-20', 'voided_at' => $case === 'void_payment' ? now() : null]);
            $invoice->update(['status' => 'PAID', 'paid_amount' => 340000]);
        }
        if ($case === 'zero_difference' || $case === 'lower_price') {
            $target->update(['price_per_month' => $case === 'zero_difference' ? 340000 : 330000]);
        }
        if ($case === 'ambiguous') {
            $service->createOneOff($student, [['code' => 'SPP', 'description' => 'Duplikat', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        }
        if ($case === 'void_original') {
            $service->voidInvoice($invoice, $actor, 'Koreksi');
        }
        if ($blocked) {
            try {
                app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
                $this->fail('Tagihan tidak aman harus memblokir seluruh kenaikan.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertFalse($source->promotion()->exists());
                $this->assertNull($source->fresh()->end_date);
            }
            return;
        }
        app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $service->generateMonthlySPP(2026, 11);
        $service->generateMonthlySPP(2026, 11);
        $expected = match ($case) { 'paid_discount' => 350000, 'zero_difference' => 340000, default => 370000 };
        $this->assertEquals($expected, Invoice::notVoid()->where('student_id', $student->id)->sum('total_amount'));
        $this->assertSame($case === 'paid_discount' ? 2 : 1, Invoice::notVoid()->where('student_id', $student->id)->count());
    }

    public static function billingBoundaries(): array
    {
        return [['void_payment', false], ['paid_discount', false], ['unpaid_discount', true], ['unpaid_fine', true], ['zero_difference', false], ['lower_price', true], ['ambiguous', true], ['void_original', false]];
    }

    public static function payments(): array
    {
        return [[0], [100000], [340000], [0, true], [340000, true]];
    }
}
