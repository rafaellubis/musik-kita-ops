<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\EnrollmentPromotionService;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesPromotionClass;
use Tests\TestCase;

class EnrollmentPromotionCancellationInvoiceTest extends TestCase
{
    use RefreshDatabase, CreatesPromotionClass;

    #[\PHPUnit\Framework\Attributes\DataProvider('invoiceBindings')]
    public function test_unpaid_update_restores_original_invoice_and_all_item_attributes(bool $legacy): void
    {
        extract($this->promotionClass());
        $service = app(InvoiceService::class);
        $invoice = $service->createOneOff($student, [
            ['code' => 'SPP', 'description' => 'SPP asli', 'amount' => 340000, 'metadata' => ['package_id' => $package->id, 'catatan' => 'Tetap disimpan']],
            ['code' => 'REG', 'description' => 'Pendaftaran', 'amount' => 250000],
        ], classType: null, enrollmentId: $legacy ? null : $source->id, periodDate: Carbon::parse('2026-11-01'));
        $item = $invoice->items()->where('item_code', 'SPP')->firstOrFail();
        $item->update(['added_by' => $actor->id]);
        $beforeItems = $invoice->items()->orderBy('id')->get()->map->getAttributes()->all();
        $number = $invoice->invoice_number;
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);

        DB::transaction(fn () => $service->rollbackPromotion($promotion, $actor, 'Koreksi level'));

        $invoice->refresh();
        $this->assertSame($number, $invoice->invoice_number);
        $this->assertSame($legacy ? null : $source->id, $invoice->enrollment_id);
        $this->assertNull($invoice->class_type);
        $this->assertSame(590000, $invoice->total_amount);
        $this->assertSame(0, $invoice->paid_amount);
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
        $this->assertSame($beforeItems, $invoice->items()->orderBy('id')->get()->map->getAttributes()->all());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('differenceHistories')]
    public function test_difference_is_voided_without_touching_paid_original(bool $legacy, bool $alreadyVoid, bool $laterOriginalPayment = false): void
    {
        extract($this->promotionClass());
        $service = app(InvoiceService::class);
        $invoice = $service->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP asli', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0001', 'amount' => $laterOriginalPayment ? 100000 : 340000, 'method' => 'CASH', 'payment_date' => '2026-10-20', 'created_by' => $actor->id]);
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $adjustment = Invoice::findOrFail($promotion->invoice_adjustments[0]['adjustment_invoice_id']);
        if ($laterOriginalPayment) {
            Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0002', 'amount' => 240000, 'method' => 'CASH', 'payment_date' => '2026-10-21', 'created_by' => $actor->id]);
            $service->recalcStatus($invoice);
        }
        $original = $invoice->fresh()->getAttributes();
        $originalPayments = Payment::orderBy('id')->get()->map->getAttributes()->all();
        if ($legacy) {
            $changes = $promotion->invoice_adjustments;
            unset($changes[0]['rollback']);
            $promotion->update(['invoice_adjustments' => $changes]);
        }
        if ($alreadyVoid) {
            $service->voidInvoice($adjustment, $actor, 'Koreksi tagihan');
        }

        DB::transaction(fn () => $service->rollbackPromotion($promotion, $actor, 'Koreksi level'));

        $this->assertSame($original, $invoice->fresh()->getAttributes());
        $this->assertSame($originalPayments, Payment::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(Invoice::STATUS_VOID, $adjustment->fresh()->status);
        $this->assertSame(2, Invoice::count());
        $this->assertSame($laterOriginalPayment ? 2 : 1, Payment::count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invoiceBindings')]
    public function test_json_object_key_order_does_not_prevent_safe_cancellation(bool $paid): void
    {
        extract($this->promotionClass());
        $service = app(InvoiceService::class);
        $invoice = $service->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP asli', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        if ($paid) {
            Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0001', 'amount' => 340000, 'method' => 'CASH', 'payment_date' => '2026-10-20']);
        }
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        // MySQL dapat mengubah urutan properti objek JSON tanpa mengubah nilainya.
        $sortKeys = function (array $value) use (&$sortKeys): array {
            if (!array_is_list($value)) {
                ksort($value);
            }
            return array_map(fn ($item) => is_array($item) ? $sortKeys($item) : $item, $value);
        };
        $promotion->update(['invoice_adjustments' => $sortKeys($promotion->invoice_adjustments)]);

        app(EnrollmentPromotionService::class)->cancel($promotion->fresh(), 'Koreksi level', $actor);

        $this->assertSame('CANCELLED', $promotion->fresh()->status);
        $this->assertSame(340000, $invoice->fresh()->total_amount);
        $this->assertSame($paid ? 340000 : 0, $invoice->fresh()->paid_amount);
        if ($paid) {
            $this->assertSame(Invoice::STATUS_VOID, Invoice::findOrFail($promotion->invoice_adjustments[0]['adjustment_invoice_id'])->status);
        }
    }

    public function test_update_with_payment_voided_before_scheduling_requires_manual_review(): void
    {
        extract($this->promotionClass());
        $invoice = app(InvoiceService::class)->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP asli', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        $payment = Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0001', 'amount' => 340000, 'method' => 'CASH', 'payment_date' => '2026-10-20', 'voided_at' => now()]);
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $before = $invoice->fresh()->getAttributes();
        $paymentBefore = $payment->fresh()->getAttributes();

        try {
            app(EnrollmentPromotionService::class)->cancel($promotion, 'Koreksi level', $actor);
            $this->fail('Riwayat pembayaran void tetap harus diperiksa sebelum pembatalan otomatis.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('promotion', $e->errors());
        }

        $this->assertSame('SCHEDULED', $promotion->fresh()->status);
        $this->assertSame($before, $invoice->fresh()->getAttributes());
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertSame('2026-11-01', $source->fresh()->end_date->toDateString());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeChanges')]
    public function test_unsafe_invoice_history_rejects_rollback_without_partial_changes(string $case): void
    {
        extract($this->promotionClass());
        $service = app(InvoiceService::class);
        $invoice = $service->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP asli', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-11-01'));
        $difference = str_starts_with($case, 'difference_');
        if ($difference) {
            Payment::create(['invoice_id' => $invoice->id, 'receipt_number' => 'KW/2026/11/0001', 'amount' => 340000, 'method' => 'CASH', 'payment_date' => '2026-10-20']);
        }
        $future = $case === 'future_item_changed'
            ? $service->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP Desember', 'amount' => 340000]], enrollmentId: $source->id, periodDate: Carbon::parse('2026-12-01'))
            : null;
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $affected = $difference ? Invoice::findOrFail($promotion->invoice_adjustments[0]['adjustment_invoice_id']) : $invoice->fresh();
        if ($future) {
            $affected = $future->fresh();
        }
        if ($case === 'difference_legacy_changed') {
            $changes = $promotion->invoice_adjustments;
            unset($changes[0]['rollback']);
            $promotion->update(['invoice_adjustments' => $changes]);
        }
        if (in_array($case, ['paid_update', 'voided_payment_update', 'difference_paid'])) {
            Payment::create(['invoice_id' => $affected->id, 'receipt_number' => 'KW/2026/11/0002', 'amount' => $affected->total_amount, 'method' => 'CASH', 'payment_date' => '2026-10-20', 'voided_at' => $case === 'voided_payment_update' ? now() : null]);
        } elseif ($case === 'legacy_update') {
            $changes = $promotion->invoice_adjustments;
            unset($changes[0]['rollback']);
            $promotion->update(['invoice_adjustments' => $changes]);
        } elseif ($case === 'duplicate_history') {
            $promotion->update(['invoice_adjustments' => [$promotion->invoice_adjustments[0], $promotion->invoice_adjustments[0]]]);
        } elseif ($case === 'future_invoice') {
            $service->createOneOff($student, [['code' => 'SPP', 'description' => 'SPP Desember', 'amount' => 370000]], enrollmentId: $promotion->successor_enrollment_id, periodDate: Carbon::parse('2026-12-01'));
        } elseif ($case === 'changed_header') {
            $affected->update(['notes' => 'Koreksi manual']);
        } elseif (in_array($case, ['changed_amount', 'difference_changed', 'difference_legacy_changed'])) {
            $affected->items()->firstOrFail()->update(['amount' => 1]);
        } else {
            $affected->items()->create(['item_code' => $case === 'added_discount' ? 'DISKON' : 'DENDA', 'description' => 'Penyesuaian manual', 'amount' => $case === 'added_discount' ? -10000 : 5000, 'added_by' => $actor->id]);
        }
        $before = Invoice::orderBy('id')->get()->map->getAttributes()->all();
        $beforeItems = \App\Models\InvoiceItem::orderBy('id')->get()->map->getAttributes()->all();
        try {
            DB::transaction(fn () => $service->rollbackPromotion($promotion, $actor, 'Koreksi level'));
            $this->fail('Riwayat tagihan tidak aman harus menolak pembatalan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('promotion', $e->errors());
        }
        $this->assertSame($before, Invoice::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeItems, \App\Models\InvoiceItem::orderBy('id')->get()->map->getAttributes()->all());
    }

    public static function invoiceBindings(): array
    {
        return [[false], [true]];
    }

    public static function differenceHistories(): array
    {
        return [[false, false], [true, false], [false, true], [true, true], [false, false, true], [true, false, true]];
    }

    public static function unsafeChanges(): array
    {
        return array_map(fn ($case) => [$case], ['paid_update', 'voided_payment_update', 'changed_amount', 'changed_header', 'added_discount', 'added_fine', 'legacy_update', 'duplicate_history', 'future_invoice', 'future_item_changed', 'difference_paid', 'difference_changed', 'difference_legacy_changed']);
    }
}
